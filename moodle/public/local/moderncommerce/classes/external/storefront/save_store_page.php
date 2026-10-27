<?php
// This file is part of Moodle and is licensed under the
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External API creating or updating a custom Modern Commerce store page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\storefront;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\event\store_page_created;
use local_moderncommerce\event\store_page_updated;
use local_moderncommerce\persistent\storepage;
use local_moderncommerce\storefront\storepage_service;
use moodle_exception;

/**
 * Creates a new custom public page, or updates an existing one.
 *
 * The slug is immutable after creation. Widget rows reference a page only by the bare
 * pagetype string, with no foreign key, so renaming a slug without re-keying every
 * widget would silently orphan the page's entire content.
 */
class save_store_page extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Store page id, or 0 to create.', VALUE_DEFAULT, 0),
            'slug' => new external_value(PARAM_RAW, 'URL slug. Ignored when updating.', VALUE_DEFAULT, ''),
            'title' => new external_value(PARAM_TEXT, 'Page title.'),
            'summary' => new external_value(PARAM_TEXT, 'Short page summary.', VALUE_DEFAULT, ''),
            'icon' => new external_value(PARAM_TEXT, 'Bootstrap icon class.', VALUE_DEFAULT, 'bi-file-text'),
            'template' => new external_value(PARAM_ALPHA, 'Zone template. Ignored when updating.', VALUE_DEFAULT, ''),
            'enabled' => new external_value(PARAM_BOOL, 'Whether the page is visible to buyers.', VALUE_DEFAULT, true),
            'copyfrom' => new external_value(
                PARAM_ALPHANUMEXT,
                'Page key to copy widgets from when creating. Ignored when updating.',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Store page id.
     * @param string $slug URL slug.
     * @param string $title Page title.
     * @param string $summary Page summary.
     * @param string $icon Bootstrap icon class.
     * @param string $template Zone template.
     * @param bool $enabled Whether the page is published.
     * @return array
     */
    public static function execute(
        int $id = 0,
        string $slug = '',
        string $title = '',
        string $summary = '',
        string $icon = 'bi-file-text',
        string $template = '',
        bool $enabled = true,
        string $copyfrom = ''
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'slug' => $slug,
            'title' => $title,
            'summary' => $summary,
            'icon' => $icon,
            'template' => $template,
            'enabled' => $enabled,
            'copyfrom' => $copyfrom,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        $cleantitle = trim($params['title']);
        if ($cleantitle === '') {
            throw new moodle_exception('storepage_error_titlerequired', 'local_moderncommerce');
        }

        $cleanicon = trim($params['icon']);
        if ($cleanicon === '' || !storepage_service::is_valid_icon($cleanicon)) {
            $cleanicon = 'bi-file-text';
        }

        $pageid = (int) $params['id'];
        $iscreate = $pageid <= 0;

        if ($iscreate) {
            $cleanslug = storepage_service::normalise_slug($params['slug']);
            $error = storepage_service::validate_slug($cleanslug);
            if ($error !== '') {
                throw new moodle_exception($error, 'local_moderncommerce');
            }

            // Copying from an existing page inherits that page's zone template, so the
            // cloned widgets land in zones the new page actually exposes.
            $copyfromkey = (string)$params['copyfrom'];
            if ($copyfromkey !== '') {
                $sources = array_column(storepage_service::copy_sources(), 'key');
                if (!in_array($copyfromkey, $sources, true)) {
                    throw new moodle_exception('storepage_error_invalidcopysource', 'local_moderncommerce');
                }
                $cleantemplate = storepage_service::template_for_page($copyfromkey);
            } else {
                $cleantemplate = $params['template'] !== '' ? $params['template'] : storepage_service::TEMPLATE_CONTENT;
            }

            if (!array_key_exists($cleantemplate, storepage_service::templates())) {
                throw new moodle_exception('storepage_error_invalidtemplate', 'local_moderncommerce');
            }

            $page = new storepage();
            $page->set('slug', $cleanslug);
            $page->set('template', $cleantemplate);
            $page->set('sortorder', self::next_sortorder());
        } else {
            $page = new storepage($pageid);
        }

        // Slug and template are fixed after creation: changing either would orphan the
        // widgets already placed on the page.
        $page->set('title', $cleantitle);
        $page->set('summary', trim($params['summary']));
        $page->set('icon', $cleanicon);
        $page->set('enabled', !empty($params['enabled']) ? 1 : 0);

        $copied = 0;
        $transaction = $DB->start_delegated_transaction();
        $page->save();
        if ($iscreate && (string)$params['copyfrom'] !== '') {
            // Purge first: copy_page_widgets() resolves the destination's zones through
            // zones::for_page(), which reads the page that was just inserted.
            storepage_service::purge_cache();
            $copied = storepage_service::copy_page_widgets((string)$params['copyfrom'], (string)$page->get('slug'));
        }
        $transaction->allow_commit();

        storepage_service::purge_cache();

        $record = $page->to_record();
        $eventdata = [
            'objectid' => (int) $page->get('id'),
            'context' => $context,
            'other' => ['slug' => (string) $page->get('slug')],
        ];
        if ($iscreate) {
            store_page_created::create($eventdata)->trigger();
        } else {
            store_page_updated::create($eventdata)->trigger();
        }

        if ($copied > 0) {
            $message = get_string('storepage_createdcopied', 'local_moderncommerce', $copied);
        } else {
            $message = get_string($iscreate ? 'storepage_created' : 'storepage_updated', 'local_moderncommerce');
        }

        return [
            'success' => true,
            'message' => $message,
            'page' => storepage_service::export_record($record),
            'warnings' => [],
        ];
    }

    /**
     * Next free sort order, so new pages land at the end of the list.
     *
     * @return int
     */
    private static function next_sortorder(): int {
        global $DB;

        return (int) $DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) + 1 FROM {' . storepage::TABLE . '}');
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the page was saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'page' => list_store_pages::page_structure(),
            'warnings' => new external_warnings(),
        ]);
    }
}

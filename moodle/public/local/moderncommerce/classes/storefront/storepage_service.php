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
 * Read/write helper for admin-created custom storefront pages.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront;

use cache;
use context_system;
use core_text;
use local_moderncommerce\output\public_page;
use local_moderncommerce\persistent\storepage;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\persistent\widget_slide;
use moodle_url;
use stdClass;

/**
 * Single access point for the custom storefront page registry.
 *
 * Custom pages are read on every storefront render (both public_page::page_catalog()
 * and the zones registry consult them), so reads go through a two-layer cache: a
 * request-scoped static plus a MUC application cache. Every write path must call
 * purge_cache().
 */
class storepage_service {
    /** @var string Plugin component name. */
    private const COMPONENT = 'local_moderncommerce';

    /** @var string Cache definition name declared in db/caches.php. */
    private const CACHE_AREA = 'storepages';

    /** @var string Single cache key holding the full page list. */
    private const CACHE_KEY = 'all';

    /** @var string Content page template: hero, main, aside, footer. */
    const TEMPLATE_CONTENT = 'content';

    /** @var string Landing page template: the richer home_* band set. */
    const TEMPLATE_LANDING = 'landing';

    /** @var int Maximum slug length, capped by local_moderncommerce_widget.pagetype char(30). */
    const SLUG_MAXLENGTH = 30;

    /** @var int Minimum slug length. */
    const SLUG_MINLENGTH = 2;

    /**
     * @var string[] Slugs an administrator may not claim.
     *
     * These are the root .php entry points of the plugin plus a few navigational
     * names. A custom page is served by page.php?slug=, so none of these is an
     * actual filesystem collision - they are reserved to avoid admin confusion
     * (/page.php?slug=checkout versus /checkout.php) and to keep a future
     * pretty-URL rewrite free of clashes. Built-in page keys are excluded
     * separately via zones::builtin_pages().
     */
    const RESERVED_SLUGS = [
        'admin',
        'bundle-details',
        'cart',
        'checkout',
        'course-details',
        'download-invoice',
        'help',
        'help-view',
        'icons-bootstrap',
        'index',
        'learner',
        'lib',
        'mycourses',
        'order',
        'orders',
        'page',
        'pages',
        'pricing',
        'redeem',
        'redeem-bundle',
        'redeem-multiple',
        'settings',
        'storefront-upload',
        'styleguide',
        'subscribe',
        'subscribe-plan',
        'success',
        'unsubscribe',
        'version',
    ];

    /** @var array<string, stdClass>|null Request-scoped page cache keyed by slug. */
    private static ?array $pages = null;

    /**
     * All custom pages, keyed by slug, in sort order.
     *
     * @return array<string, stdClass>
     */
    public static function all(): array {
        if (self::$pages !== null) {
            return self::$pages;
        }

        $cache = cache::make(self::COMPONENT, self::CACHE_AREA);
        $cached = $cache->get(self::CACHE_KEY);
        if (is_array($cached)) {
            self::$pages = $cached;
            return self::$pages;
        }

        global $DB;

        $records = $DB->get_records(storepage::TABLE, null, 'sortorder ASC, id ASC');

        $pages = [];
        foreach ($records as $record) {
            $pages[(string)$record->slug] = $record;
        }

        self::$pages = $pages;
        $cache->set(self::CACHE_KEY, $pages);

        return self::$pages;
    }

    /**
     * Fetch one custom page by slug.
     *
     * Returns null for built-in page keys, which are not stored in this table.
     *
     * @param string $slug Page slug.
     * @return stdClass|null
     */
    public static function by_slug(string $slug): ?stdClass {
        $pages = self::all();

        return $pages[$slug] ?? null;
    }

    /**
     * Whether a slug belongs to an admin-created custom page.
     *
     * @param string $slug Page slug.
     * @return bool
     */
    public static function exists(string $slug): bool {
        return self::by_slug($slug) !== null;
    }

    /**
     * All custom page slugs, in sort order.
     *
     * @return string[]
     */
    public static function slugs(): array {
        return array_keys(self::all());
    }

    /**
     * Custom pages shaped as public_page::page_catalog() entries.
     *
     * @return array<string, array>
     */
    public static function catalog_entries(): array {
        $entries = [];
        foreach (self::all() as $slug => $record) {
            $entries[$slug] = [
                'title' => (string)$record->title,
                'summary' => (string)($record->summary ?? ''),
                'icon' => (string)$record->icon,
                'route' => '/local/moderncommerce/page.php',
                'params' => ['slug' => $slug],
                'template' => (string)$record->template,
                'custom' => true,
                'id' => (int)$record->id,
            ];
        }

        return $entries;
    }

    /**
     * Shape one custom page the way the admin React table consumes it.
     *
     * Mirrors the row built in admin/pages.php so the create/update responses and the
     * server-rendered seed list stay identical.
     *
     * @param stdClass $record Raw storepage record.
     * @return array
     */
    public static function export_record(stdClass $record): array {
        $slug = (string)$record->slug;

        return [
            'id' => (int)$record->id,
            'key' => $slug,
            'title' => format_string(public_page::setting($slug, 'title', (string)$record->title)),
            'summary' => format_string(public_page::setting($slug, 'summary', (string)($record->summary ?? ''))),
            'icon' => (string)$record->icon,
            'url' => (new moodle_url('/local/moderncommerce/page.php', ['slug' => $slug]))->out(false),
            'enabled' => (bool)(int)$record->enabled,
            'required' => false,
            'custom' => true,
            'template' => (string)$record->template,
        ];
    }

    /**
     * Every custom page exported for the admin React table, in sort order.
     *
     * @return array[]
     */
    public static function export_all(): array {
        $rows = [];
        foreach (self::all() as $record) {
            $rows[] = self::export_record($record);
        }

        return $rows;
    }

    /**
     * Selectable zone templates with their human-readable labels.
     *
     * @return array<string, string>
     */
    public static function templates(): array {
        return [
            self::TEMPLATE_CONTENT => get_string('storepage_template_content', self::COMPONENT),
            self::TEMPLATE_LANDING => get_string('storepage_template_landing', self::COMPONENT),
        ];
    }

    /**
     * Normalise a free-text title or slug input into a candidate slug.
     *
     * @param string $value Raw input.
     * @return string
     */
    public static function normalise_slug(string $value): string {
        $slug = core_text::strtolower(trim($value));
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9-]/', '', (string)$slug);
        $slug = preg_replace('/-+/', '-', (string)$slug);

        return trim((string)$slug, '-');
    }

    /**
     * Validate a slug against shape, reserved-word and uniqueness rules.
     *
     * @param string $slug Candidate slug, already normalised.
     * @param int $excludeid Page id to ignore when checking uniqueness (0 when creating).
     * @return string Empty string when valid, otherwise a lang string identifier.
     */
    public static function validate_slug(string $slug, int $excludeid = 0): string {
        $slug = trim($slug);

        if ($slug === '') {
            return 'storepage_error_slugrequired';
        }

        // Underscores are rejected deliberately: public_page::config_name() maps '-' to
        // '_', so 'my-page' and 'my_page' would collide on identical config keys.
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            return 'storepage_error_slugshape';
        }

        $length = strlen($slug);
        if ($length < self::SLUG_MINLENGTH || $length > self::SLUG_MAXLENGTH) {
            return 'storepage_error_sluglength';
        }

        // Built-in page keys and the site-wide 'global' scope are never available.
        if (in_array($slug, zones::builtin_pages(), true)) {
            return 'storepage_error_slugreserved';
        }

        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            return 'storepage_error_slugreserved';
        }

        // Avoid pagetype/zone symmetry confusion.
        if (in_array($slug, zones::all_zones(), true)) {
            return 'storepage_error_slugreserved';
        }

        global $DB;

        if ($DB->record_exists_select(storepage::TABLE, 'slug = ? AND id <> ?', [$slug, $excludeid])) {
            return 'storepage_error_slugtaken';
        }

        return '';
    }

    /**
     * Copy every widget from one page onto another, preserving zones and order.
     *
     * Used when an administrator creates a page "from" an existing one. Slider slides are
     * duplicated too, but their uploaded images are re-pointed rather than re-uploaded:
     * the new slide rows reference the same stored file URLs.
     *
     * Only zones the destination template actually exposes are copied, so cloning a
     * landing page onto a content page silently drops the bands that have nowhere to go
     * rather than creating unreachable widgets.
     *
     * @param string $fromslug Source page key or slug.
     * @param string $toslug Destination custom page slug.
     * @return int Number of widgets copied.
     */
    public static function copy_page_widgets(string $fromslug, string $toslug): int {
        global $DB, $USER;

        $validzones = zones::for_page($toslug);
        if (empty($validzones)) {
            return 0;
        }

        $sources = $DB->get_records(widget::TABLE, ['pagetype' => $fromslug], 'zone ASC, sortorder ASC');
        if (empty($sources)) {
            return 0;
        }

        $now = time();
        $copied = 0;

        foreach ($sources as $source) {
            if (!in_array((string)$source->zone, $validzones, true)) {
                continue;
            }

            $record = clone $source;
            unset($record->id);
            $record->pagetype = $toslug;
            $record->usermodified = (int)$USER->id;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $newid = $DB->insert_record(widget::TABLE, $record);
            $copied++;

            if ((string)$source->type !== 'slider') {
                continue;
            }

            $slides = $DB->get_records(widget_slide::TABLE, ['instanceid' => (int)$source->id], 'sortorder ASC');
            foreach ($slides as $slide) {
                $slidecopy = clone $slide;
                unset($slidecopy->id);
                $slidecopy->instanceid = $newid;
                $slidecopy->timecreated = $now;
                $slidecopy->timemodified = $now;
                $DB->insert_record(widget_slide::TABLE, $slidecopy);
            }
        }

        return $copied;
    }

    /**
     * Pages an administrator can copy a new page's layout from.
     *
     * Every page that currently holds at least one widget, built-in or custom. The
     * site-wide 'global' scope is excluded: its bands already render on every page, so
     * copying them would double them up.
     *
     * @return array[] List of ['key' => string, 'title' => string, 'template' => string, 'widgets' => int].
     */
    public static function copy_sources(): array {
        global $DB;

        $counts = $DB->get_records_sql(
            'SELECT pagetype, COUNT(id) AS total
               FROM {' . widget::TABLE . '}
              WHERE pagetype <> :global
           GROUP BY pagetype',
            ['global' => zones::PAGE_GLOBAL]
        );

        $catalog = public_page::page_catalog();
        $sources = [];
        foreach ($catalog as $key => $page) {
            if (!isset($counts[$key]) || (int)$counts[$key]->total === 0) {
                continue;
            }
            $sources[] = [
                'key' => $key,
                'title' => format_string(public_page::setting($key, 'title', $page['title'])),
                'template' => self::template_for_page($key),
                'widgets' => (int)$counts[$key]->total,
            ];
        }

        return $sources;
    }

    /**
     * Which zone template a page uses, whether it is built-in or custom.
     *
     * @param string $key Page key or slug.
     * @return string One of the TEMPLATE_* values.
     */
    public static function template_for_page(string $key): string {
        $custom = self::by_slug($key);
        if ($custom !== null) {
            return (string)$custom->template;
        }

        // Built-ins: the catalog page uses the full home/catalog band set, the rest are
        // ordinary content pages.
        return $key === zones::PAGE_CATALOG ? self::TEMPLATE_LANDING : self::TEMPLATE_CONTENT;
    }

    /**
     * Validate a Bootstrap icon class name.
     *
     * @param string $icon Icon class, e.g. bi-briefcase.
     * @return bool
     */
    public static function is_valid_icon(string $icon): bool {
        return (bool)preg_match('/^bi-[a-z0-9-]{1,46}$/', $icon);
    }

    /**
     * Delete every widget placed on a page, along with slider slides and their files.
     *
     * Shared by delete_store_page and kept aligned with seed::reset_widgets(), which
     * performs the same cascade for the built-in pages.
     *
     * @param string $slug Page slug (the widget pagetype).
     */
    public static function delete_page_widgets(string $slug): void {
        global $DB;

        $widgets = $DB->get_records(widget::TABLE, ['pagetype' => $slug], '', 'id,type');
        if (empty($widgets)) {
            return;
        }

        $ids = [];
        $sliderids = [];
        foreach ($widgets as $record) {
            $id = (int)$record->id;
            $ids[] = $id;
            if ((string)$record->type === 'slider') {
                $sliderids[] = $id;
            }
        }

        if (!empty($sliderids)) {
            [$slidersql, $sliderparams] = $DB->get_in_or_equal($sliderids, SQL_PARAMS_NAMED, 'mcslider');
            $slides = $DB->get_records_select(widget_slide::TABLE, "instanceid {$slidersql}", $sliderparams, '', 'id');
            if (!empty($slides)) {
                $fs = get_file_storage();
                $context = context_system::instance();
                foreach ($slides as $slide) {
                    $fs->delete_area_files($context->id, self::COMPONENT, 'slideimage', (int)$slide->id);
                }
            }
            $DB->delete_records_select(widget_slide::TABLE, "instanceid {$slidersql}", $sliderparams);
        }

        [$idsql, $idparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'mcwidget');
        $DB->delete_records_select(widget::TABLE, "id {$idsql}", $idparams);
    }

    /**
     * Remove the per-page config overrides a custom page may have accumulated.
     *
     * Uses explicit unset_config() calls rather than a LIKE delete, which would also
     * match longer slugs sharing this slug as a prefix.
     *
     * @param string $slug Page slug.
     */
    public static function delete_page_config(string $slug): void {
        foreach (['title', 'summary', 'body', 'enabled'] as $field) {
            unset_config(public_page::config_name($slug, $field), self::COMPONENT);
        }
    }

    /**
     * Drop both cache layers. Must be called after every write.
     */
    public static function purge_cache(): void {
        self::$pages = null;
        cache::make(self::COMPONENT, self::CACHE_AREA)->delete(self::CACHE_KEY);
    }
}

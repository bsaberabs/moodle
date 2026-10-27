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
 * Persistent for an admin-created custom public storefront page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\persistent;

use core\persistent;
use lang_string;
use local_moderncommerce\storefront\storepage_service;

/**
 * Represents one custom storefront page created by an administrator.
 *
 * The six built-in pages (catalog, about, support, terms, privacy, refund-policy)
 * are NOT stored here - they stay hardcoded in public_page::page_catalog() and are
 * merged with these rows at read time.
 */
class storepage extends persistent {
    /** @var string Table name. */
    const TABLE = 'local_moderncommerce_storepage';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'slug' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'title' => [
                'type' => PARAM_TEXT,
            ],
            'summary' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'icon' => [
                'type' => PARAM_ALPHANUMEXT,
                'default' => 'bi-file-text',
            ],
            'template' => [
                'type' => PARAM_ALPHA,
                'default' => storepage_service::TEMPLATE_CONTENT,
                'choices' => [storepage_service::TEMPLATE_CONTENT, storepage_service::TEMPLATE_LANDING],
            ],
            'enabled' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'sortorder' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Validate the slug against shape, reserved-word and uniqueness rules.
     *
     * @param string $value Proposed slug.
     * @return true|lang_string
     */
    protected function validate_slug($value) {
        $error = storepage_service::validate_slug((string)$value, (int)$this->get('id'));
        if ($error !== '') {
            return new lang_string($error, 'local_moderncommerce');
        }

        return true;
    }

    /**
     * Validate the title is not blank once trimmed.
     *
     * @param string $value Proposed title.
     * @return true|lang_string
     */
    protected function validate_title($value) {
        if (trim((string)$value) === '') {
            return new lang_string('storepage_error_titlerequired', 'local_moderncommerce');
        }

        return true;
    }
}

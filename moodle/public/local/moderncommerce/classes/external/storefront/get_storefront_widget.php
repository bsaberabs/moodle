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
 * External API returning one widget's editable settings + field schema (gear editor).
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
use local_moderncommerce\persistent\widget;
use local_moderncommerce\persistent\widget_slide;
use local_moderncommerce\storefront\field_schema;
use local_moderncommerce\storefront\resolver\categories_resolver;

/**
 * Returns a widget's settings values, its declarative field schema, and (for sliders) its slides.
 */
class get_storefront_widget extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Widget id.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Widget id.
     * @return array
     */
    public static function execute(int $id): array {
        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        $w = widget::get_record(['id' => $params['id']], MUST_EXIST);
        $type = (string) $w->get('type');
        $schema = field_schema::for_type($type);

        // Build current values: schema defaults, overlaid with stored settings, plus row columns.
        $values = [];
        foreach ($schema as $field) {
            $values[$field['name']] = $field['default'] ?? '';
        }
        $storedsettings = $w->get_settings_array();
        foreach ($storedsettings as $key => $value) {
            $values[$key] = $value;
        }
        if (
            $type === 'breadcrumb'
            && !array_key_exists('backgroundsource', $storedsettings)
            && !empty($storedsettings['backgroundfile'])
        ) {
            $values['backgroundsource'] = 'upload';
        }
        $values['title'] = (string) $w->get('title');
        $values['subtitle'] = (string) $w->get('subtitle');
        if ($type === 'categories') {
            self::hydrate_category_items($w, $storedsettings, $values);
        }
        if ($type === 'content') {
            self::hydrate_content_image($storedsettings, $values);
            self::hydrate_content_benefits($storedsettings, $values);
        }
        if ($type === 'instructors') {
            self::hydrate_instructor_photos($values);
        }

        // Slides (slider only).
        $slides = [];
        if ($type === 'slider') {
            foreach (widget_slide::get_records(['instanceid' => (int) $w->get('id')], 'sortorder', 'ASC') as $s) {
                $image = (string) $s->get('image');
                $imagesource = self::slide_image_source($image);
                $slides[] = [
                    'id' => (int) $s->get('id'),
                    'image' => $image,
                    'imagesource' => $imagesource,
                    'imageurl' => $imagesource === 'url' ? $image : '',
                    'imagefile' => $imagesource === 'upload' ? $image : '',
                    'heading' => (string) $s->get('heading'),
                    'subheading' => (string) $s->get('subheading'),
                    'ctalabel' => (string) $s->get('ctalabel'),
                    'ctaurl' => (string) $s->get('ctaurl'),
                    'ctastyle' => (string) $s->get('ctastyle'),
                    'bgcolor' => (string) $s->get('bgcolor'),
                    'enabled' => (int) $s->get('enabled'),
                    'sortorder' => (int) $s->get('sortorder'),
                ];
            }
        }

        return [
            'id' => (int) $w->get('id'),
            'type' => $type,
            'pagetype' => (string) $w->get('pagetype'),
            'fields' => json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'values' => json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'styleconfig' => (string) ($w->get('styleconfig') ?: '{}'),
            'slides' => json_encode($slides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'warnings' => [],
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Widget id.'),
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Widget type key.'),
            'pagetype' => new external_value(PARAM_ALPHANUMEXT, 'Widget page scope.'),
            'fields' => new external_value(PARAM_RAW, 'Field schema as a JSON string.'),
            'values' => new external_value(PARAM_RAW, 'Current settings values as a JSON string.'),
            'styleconfig' => new external_value(PARAM_RAW, 'Current universal style config as a JSON string.'),
            'slides' => new external_value(PARAM_RAW, 'Slider slides as a JSON string (empty array otherwise).'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Ensure the Categories editor opens with the currently displayed category rows.
     *
     * Older widgets and widgets that relied on the automatic top-level fallback may
     * not have editable `items` saved yet. The focused editor needs concrete rows.
     *
     * @param widget $w Widget record.
     * @param array $storedsettings Stored settings JSON as an array.
     * @param array $values Editable values being returned.
     */
    private static function hydrate_category_items(widget $w, array $storedsettings, array &$values): void {
        $items = self::normalise_category_items($storedsettings['items'] ?? $storedsettings['categories'] ?? []);
        if (!empty($items)) {
            $values['items'] = $items;
            return;
        }

        try {
            $resolver = new categories_resolver();
            $payload = $resolver->resolve($w, ['pagetype' => (string) $w->get('pagetype')]);
            $values['items'] = self::normalise_category_items($payload['categories'] ?? []);
        } catch (\Throwable $e) {
            $values['items'] = [];
        }
    }

    /**
     * Convert stored or resolved category rows into the editable schema shape.
     *
     * @param mixed $rows Raw category rows.
     * @return array<int, array<string, string>>
     */
    private static function normalise_category_items($rows): array {
        if (!is_array($rows)) {
            return [];
        }
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $categoryid = (int) ($row['categoryid'] ?? $row['id'] ?? 0);
            if ($categoryid <= 0) {
                continue;
            }
            $items[] = [
                'categoryid' => (string) $categoryid,
                'icon' => (string) ($row['icon'] ?? 'collection'),
                'color' => (string) ($row['color'] ?? ''),
            ];
        }
        return $items;
    }

    /**
     * Ensure old content-section image values reopen in the new source selector.
     *
     * @param array $storedsettings Stored widget settings.
     * @param array $values Editable values being returned.
     */
    private static function hydrate_content_image(array $storedsettings, array &$values): void {
        if (array_key_exists('imagesource', $storedsettings)) {
            return;
        }

        $legacy = (string) ($storedsettings['image'] ?? '');
        $imageurl = (string) ($storedsettings['imageurl'] ?? '');
        $imagefile = (string) ($storedsettings['imagefile'] ?? '');
        $image = $imagefile !== '' ? $imagefile : ($imageurl !== '' ? $imageurl : $legacy);
        $source = self::slide_image_source($image);

        $values['imagesource'] = $source;
        if ($source === 'upload') {
            $values['imagefile'] = $image;
            $values['imageurl'] = $imageurl;
        } else {
            $values['imageurl'] = $image;
            $values['imagefile'] = $imagefile;
        }
    }

    /**
     * Keep existing content sections from inheriting sample benefit rows.
     *
     * @param array $storedsettings Stored widget settings.
     * @param array $values Editable values being returned.
     */
    private static function hydrate_content_benefits(array $storedsettings, array &$values): void {
        if (array_key_exists('benefits', $storedsettings)) {
            return;
        }
        $values['benefits'] = [];
    }

    /**
     * Ensure old instructor photo rows reopen in the new source selector.
     *
     * @param array $values Editable values being returned.
     */
    private static function hydrate_instructor_photos(array &$values): void {
        if (empty($values['instructors']) || !is_array($values['instructors'])) {
            return;
        }

        foreach ($values['instructors'] as &$row) {
            if (!is_array($row) || array_key_exists('photosource', $row)) {
                continue;
            }

            $legacy = (string) ($row['photo'] ?? '');
            $photourl = (string) ($row['photourl'] ?? '');
            $photofile = (string) ($row['photofile'] ?? '');
            $photo = $photofile !== '' ? $photofile : ($photourl !== '' ? $photourl : $legacy);
            $source = self::slide_image_source($photo);

            $row['photosource'] = $source;
            if ($source === 'upload') {
                $row['photofile'] = $photo;
                $row['photourl'] = $photourl;
            } else {
                $row['photourl'] = $photo;
                $row['photofile'] = $photofile;
            }
        }
        unset($row);
    }

    /**
     * Infer whether a stored slide image came from a plain URL field or the upload service.
     *
     * @param string $image Stored image reference.
     * @return string Source key used by the React editor.
     */
    private static function slide_image_source(string $image): string {
        if ($image === '') {
            return 'url';
        }
        return strpos($image, '/pluginfile.php/') !== false || strpos($image, 'pluginfile.php/') === 0 ? 'upload' : 'url';
    }
}

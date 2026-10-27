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
 * Resolver for the hero slider widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use context_system;
use local_moderncommerce\branding;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\persistent\widget_slide;
use moodle_url;

/**
 * Loads the slides for a slider instance into a render-ready payload.
 */
class slider_resolver implements widget_resolver {
    /**
     * Build the slides payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (unused for sliders).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $slides = widget_slide::get_records(
            ['instanceid' => (int)$instance->get('id'), 'enabled' => 1],
            'sortorder',
            'ASC'
        );

        $out = [];
        foreach ($slides as $slide) {
            $out[] = [
                'id' => (int)$slide->get('id'),
                'image' => self::slide_image_url($slide),
                'heading' => (string)$slide->get('heading'),
                'subheading' => (string)$slide->get('subheading'),
                'ctalabel' => (string)$slide->get('ctalabel'),
                'ctaurl' => self::to_url((string)$slide->get('ctaurl')),
                'ctastyle' => (string)$slide->get('ctastyle'),
                'bgcolor' => branding::runtime_colour(
                    (string)$slide->get('bgcolor'),
                    'var(--mc-primary)',
                    ['#0b7f75', '#0f766e', '#115e59', '#7c3aed', '#1f2937']
                ),
            ];
        }

        $settings = $instance->get_resolved_settings_array();
        $design = (string)($settings['design'] ?? 'overlay');
        if (!in_array($design, ['overlay', 'split', 'card'], true)) {
            $design = 'overlay';
        }

        return [
            'slides' => $out,
            'autoplay' => (bool)($settings['autoplay'] ?? true),
            'interval' => max(2000, (int)($settings['interval'] ?? 6000)),
            'showarrows' => (bool)($settings['showarrows'] ?? true),
            'showdots' => (bool)($settings['showdots'] ?? true),
            'design' => $design,
            'buttoncolor' => branding::runtime_colour((string)($settings['buttoncolor'] ?? ''), '', []),
            'buttontextcolor' => branding::runtime_colour((string)($settings['buttontextcolor'] ?? ''), '', []),
            'buttonfontsize' => self::number($settings['buttonfontsize'] ?? 0, 0, 96),
            'buttonradius' => self::number($settings['buttonradius'] ?? 0, 0, 240),
            'labels' => [
                'featured' => get_string('p1_storefront_featured', 'local_moderncommerce'),
                'previousslide' => get_string('p1_storefront_previousslide', 'local_moderncommerce'),
                'nextslide' => get_string('p1_storefront_nextslide', 'local_moderncommerce'),
                'chooseslide' => get_string('p1_storefront_chooseslide', 'local_moderncommerce'),
                'gotoslide' => get_string('p1_storefront_gotoslide', 'local_moderncommerce'),
            ],
        ];
    }

    /**
     * Bound an integer setting.
     *
     * @param mixed $value Raw value.
     * @param int $min Minimum value.
     * @param int $max Maximum value.
     * @return int Bounded value.
     */
    private static function number($value, int $min, int $max): int {
        return max($min, min($max, (int)$value));
    }

    /**
     * Resolve a slide's background image URL from the Files API, falling back to
     * the legacy image URL column for slides created before uploads existed.
     *
     * @param widget_slide $slide The slide.
     * @return string Absolute image URL, or empty string.
     */
    private static function slide_image_url(widget_slide $slide): string {
        $context = context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'local_moderncommerce',
            'slideimage',
            (int)$slide->get('id'),
            'itemid, filepath, filename',
            false
        );

        if ($files) {
            $file = reset($files);
            return moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
        }

        return self::to_url((string)$slide->get('image'));
    }

    /**
     * Normalise a stored link into an absolute URL.
     *
     * Accepts an absolute http(s) URL or a site-relative path such as
     * /local/moderncommerce/index.php.
     *
     * @param string $value Stored link or image reference.
     * @return string Absolute URL, or empty string.
     */
    private static function to_url(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        if ($value[0] === '/') {
            return (new moodle_url($value))->out(false);
        }
        // Anything else (anchors, mailto, already-relative) is passed through untouched.
        return $value;
    }
}

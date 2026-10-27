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
 * Resolves a logo URL for storefront widgets (footer brand column, etc.).
 *
 * Adapted from local_moderncoursereminder\local\logo_url_resolver. Produces an
 * absolute, login-free logo URL from a selectable source so an admin can point a
 * widget at the active theme's logo or the core Moodle logo without re-uploading.
 *
 * Source keys:
 *  - `theme`      : the active site theme's logo (first discovered logo of
 *                   $CFG->theme or a theme it inherits from; falls back to the
 *                   core site logo).
 *  - `site`       : the core site logo (Site administration > Logos / core_admin/logo).
 *  - `compact`    : the core compact site logo (core_admin/logocompact).
 *  - `file:<ref>` : a specific discovered theme logo file.
 *  - ``           : custom upload (the widget's own uploaded image is used instead).
 *
 * A setting is treated as a logo when it has an uploaded image file AND the
 * setting name (filearea) contains "logo" — so themes that store logos in custom
 * settings (logodark, logowhite, ...) are handled like ones using logo/logocompact.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront;

/**
 * Logo source resolver + selectable-source list for storefront widgets.
 */
class logo_source {
    /**
     * Resolve a source key to an absolute URL ('' when it yields nothing).
     *
     * @param string $source `theme` | `site` | `compact` | `file:<ref>` | ''.
     * @return string
     */
    public static function resolve(string $source): string {
        if ($source === '' || $source === 'custom') {
            return '';
        }
        if ($source === 'theme') {
            $themelogos = self::theme_logos();
            $first = reset($themelogos);
            return $first ? $first['url'] : self::core_logo_url('logo');
        }
        if ($source === 'compact') {
            return self::core_logo_url('logocompact');
        }
        if (strpos($source, 'file:') === 0) {
            $ref = self::decode_ref(substr($source, strlen('file:')));
            return $ref ? self::file_url($ref) : '';
        }
        // Default: the core site logo.
        return self::core_logo_url('logo');
    }

    /**
     * Select choices for the widget editor: ['key' => 'label', ...].
     *
     * Core logos + active-theme discovered logos, with a "custom upload" entry.
     * Plain-English labels (field_schema convention).
     *
     * @return array<string, string>
     */
    public static function choices(): array {
        $choices = [
            'theme' => 'Active theme logo',
            'site' => 'Core Moodle site logo',
            'compact' => 'Core Moodle compact logo',
        ];
        foreach (self::theme_logos() as $file) {
            $choices[$file['key']] = $file['label'];
        }
        $choices[''] = 'Custom upload (use the image below)';
        return $choices;
    }

    /**
     * Every selectable source with its resolved URL (key/label/url) — useful for
     * a preview gallery. Core logos first, then discovered theme logos.
     *
     * @return array[] Each: ['key' => string, 'label' => string, 'url' => string].
     */
    public static function sources(): array {
        $list = [
            ['key' => 'site', 'label' => 'Core Moodle site logo', 'url' => self::core_logo_url('logo')],
            ['key' => 'compact', 'label' => 'Core Moodle compact logo', 'url' => self::core_logo_url('logocompact')],
        ];
        foreach (self::theme_logos() as $file) {
            $list[] = $file;
        }
        return $list;
    }

    /**
     * Absolute URL for a core site logo, read straight from core_admin config.
     *
     * Mirrors the URL format core uses (size in the itemid slot, theme revision in
     * the path slot) so core_admin_pluginfile() can find the file.
     *
     * @param string $area `logo` or `logocompact`.
     * @return string
     */
    public static function core_logo_url(string $area): string {
        $filename = get_config('core_admin', $area);
        if (empty($filename)) {
            return '';
        }
        [$maxwidth, $maxheight] = ($area === 'logocompact') ? [300, 300] : [0, 200];
        $filepath = $maxwidth . 'x' . $maxheight . '/';

        $url = \moodle_url::make_pluginfile_url(
            \context_system::instance()->id,
            'core_admin',
            $area,
            $filepath,
            theme_get_revision(),
            $filename
        );
        return $url->out(false);
    }

    /**
     * Discover the active theme's logos: image files in a `logo`-named setting of
     * the site theme ($CFG->theme) or any theme it inherits from.
     *
     * @return array[] Each: ['key' => 'file:<ref>', 'label' => string, 'url' => string].
     */
    protected static function theme_logos(): array {
        global $DB, $CFG;

        try {
            $theme = \theme_config::load($CFG->theme);
            $names = array_merge([$theme->name], array_values($theme->parents));
        } catch (\Throwable $e) {
            $names = [$CFG->theme];
        }
        $components = array_map(function ($n) {
            return 'theme_' . $n;
        }, array_unique($names));

        [$insql, $inparams] = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED);
        $sql = "SELECT id, contextid, component, filearea, itemid, filepath, filename
                  FROM {files}
                 WHERE contextid = :ctx
                   AND component $insql
                   AND " . $DB->sql_like('mimetype', ':mime', false) . "
                   AND filename <> '.'
                   AND filesize > 0
              ORDER BY component, filearea, filename";
        $rows = $DB->get_records_sql($sql, [
            'ctx' => \context_system::instance()->id,
            'mime' => 'image/%',
        ] + $inparams);

        $files = [];
        foreach ($rows as $row) {
            // The setting name (filearea) must contain "logo".
            if (!preg_match('/logo/i', $row->filearea)) {
                continue;
            }
            $ref = [
                'contextid' => (int) $row->contextid,
                'component' => $row->component,
                'filearea' => $row->filearea,
                'itemid' => (int) $row->itemid,
                'filepath' => $row->filepath,
                'filename' => $row->filename,
            ];
            $files[] = [
                'key' => 'file:' . self::encode_ref($ref),
                'label' => self::source_label($row->component, $row->filearea),
                'url' => self::file_url($ref),
            ];
        }
        return $files;
    }

    /**
     * Human label for a discovered theme logo, e.g. "boost_union / logodark".
     *
     * @param string $component
     * @param string $filearea
     * @return string
     */
    protected static function source_label(string $component, string $filearea): string {
        $name = (strpos($component, 'theme_') === 0) ? substr($component, strlen('theme_')) : $component;
        return $name . ' / ' . $filearea;
    }

    /**
     * Build an absolute pluginfile URL from a stored file descriptor.
     *
     * The `logo`/`logocompact` fileareas are served in the core_admin style (a
     * `{w}x{h}/{themerev}/` path; a plain URL 404s); other logo settings use the
     * standard theme setting-file URL.
     *
     * @param array $ref
     * @return string
     */
    protected static function file_url(array $ref): string {
        $area = $ref['filearea'];

        if ($area === 'logo' || $area === 'logocompact') {
            [$maxwidth, $maxheight] = ($area === 'logocompact') ? [300, 300] : [0, 200];
            $url = \moodle_url::make_pluginfile_url(
                $ref['contextid'],
                $ref['component'],
                $area,
                $maxwidth . 'x' . $maxheight . '/',
                theme_get_revision(),
                '/' . ltrim($ref['filename'], '/')
            );
            return $url->out(false);
        }

        $url = \moodle_url::make_pluginfile_url(
            $ref['contextid'],
            $ref['component'],
            $area,
            $ref['itemid'],
            $ref['filepath'],
            $ref['filename']
        );
        return $url->out(false);
    }

    /**
     * Encode a file descriptor as a compact, form-safe source key.
     *
     * @param array $ref
     * @return string
     */
    protected static function encode_ref(array $ref): string {
        return base64_encode(json_encode($ref));
    }

    /**
     * Decode a file-source descriptor; null when invalid.
     *
     * @param string $raw
     * @return array|null
     */
    protected static function decode_ref(string $raw): ?array {
        $decoded = base64_decode($raw, true);
        if ($decoded === false) {
            return null;
        }
        $ref = json_decode($decoded, true);
        if (!is_array($ref) || empty($ref['component']) || empty($ref['filename'])) {
            return null;
        }
        return $ref + [
            'contextid' => \context_system::instance()->id,
            'filearea' => 'logo',
            'itemid' => 0,
            'filepath' => '/',
        ];
    }
}

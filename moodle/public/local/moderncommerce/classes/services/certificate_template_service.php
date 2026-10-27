<?php
// This file is part of Moodle and is licensed under the GNU GPL v3 or later.
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
 * Course Certificate template integration service.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

/**
 * Resolves the store-wide and per-program Course Certificate templates.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_template_service {
    /** Modern Commerce component name. */
    private const COMPONENT = 'local_moderncommerce';

    /** Configuration key for the global program certificate template. */
    private const CONFIG_DEFAULT_TEMPLATE = 'default_program_certificate_template_id';

    /**
     * Check whether Moodle Course Certificate and its template backend are available.
     *
     * @return bool
     */
    public static function available(): bool {
        global $DB;

        try {
            $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_coursecertificate');
            return $plugin !== null
                && $plugin->is_installed_and_upgraded()
                && class_exists(\tool_certificate\template::class)
                && $DB->get_manager()->table_exists(new \xmldb_table('tool_certificate_templates'));
        } catch (\Throwable $exception) {
            // Course Certificate is optional. A partial install, removal, or
            // pending upgrade must not break Modern Commerce admin pages.
            debugging('Course Certificate availability check failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Return templates visible to the current administrator.
     *
     * @return array
     */
    public static function options(): array {
        if (!self::available()) {
            return [];
        }

        try {
            $options = [];
            foreach (\tool_certificate\template::get_visible_templates_list() as $id => $name) {
                $options[(int)$id] = (string)$name;
            }
            return $options;
        } catch (\Throwable $exception) {
            debugging('Course Certificate template lookup failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Return the template manager URL when the current user may access it.
     *
     * @return string
     */
    public static function manage_url(): string {
        global $CFG;

        if (
            !self::available()
                || !class_exists(\tool_certificate\permission::class)
                || !is_readable($CFG->dirroot . '/admin/tool/certificate/manage_templates.php')
        ) {
            return '';
        }

        try {
            if (!\tool_certificate\permission::can_view_admin_tree()) {
                return '';
            }
            return (new \moodle_url('/admin/tool/certificate/manage_templates.php'))->out(false);
        } catch (\Throwable $exception) {
            debugging('Course Certificate template manager check failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * Return the configured global template when it is still valid and visible.
     *
     * @return int
     */
    public static function default_template_id(): int {
        $templateid = (int)(get_config(self::COMPONENT, self::CONFIG_DEFAULT_TEMPLATE) ?: 0);
        return self::exists($templateid) ? $templateid : 0;
    }

    /**
     * Resolve a program override, falling back to the store-wide default.
     *
     * @param int $overridetemplateid Program-specific template ID.
     * @return int
     */
    public static function resolve(int $overridetemplateid): int {
        if (self::exists($overridetemplateid)) {
            return $overridetemplateid;
        }
        return self::default_template_id();
    }

    /**
     * Check template existence without applying administrator visibility to learner-side issuance.
     *
     * @param int $templateid Template ID.
     * @return bool
     */
    public static function exists(int $templateid): bool {
        global $DB;

        if ($templateid <= 0 || !self::available()) {
            return false;
        }

        try {
            return $DB->record_exists('tool_certificate_templates', ['id' => $templateid]);
        } catch (\Throwable $exception) {
            debugging('Course Certificate template validation failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Validate and save the global template; zero disables the global fallback.
     *
     * @param int $templateid Template ID.
     * @return bool
     */
    public static function save_default(int $templateid): bool {
        if ($templateid > 0 && !array_key_exists($templateid, self::options())) {
            return false;
        }
        set_config(self::CONFIG_DEFAULT_TEMPLATE, max(0, $templateid), self::COMPONENT);
        return true;
    }
}

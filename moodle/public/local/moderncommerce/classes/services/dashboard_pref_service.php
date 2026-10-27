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
 * Per-admin dashboard preference storage with a site-default fallback.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

/**
 * Resolves and persists dashboard layout preferences.
 *
 * Two layers exist for every preference:
 *  - Personal: stored per user in {local_moderncommerce_dashpref} (each admin owns their own).
 *  - Site default: stored in plugin config by a site administrator; seeds admins who have not
 *    personalised yet.
 *
 * Resolution is always personal -> site default -> (caller's built-in catalog default).
 */
class dashboard_pref_service {
    /** @var string Per-user preference table. */
    public const TABLE = 'local_moderncommerce_dashpref';

    /** @var string Component for site-default config storage. */
    private const COMPONENT = 'local_moderncommerce';

    /** @var string Save scope: this admin only. */
    public const SCOPE_PERSONAL = 'personal';

    /** @var string Save scope: shared site default for every admin. */
    public const SCOPE_SITE = 'sitedefault';

    /** @var string Site-default config key holding the chart layout JSON. */
    public const CFG_CHARTS = 'dashboard_charts_layout';

    /** @var string Site-default config key holding the KPI panel layout JSON. */
    public const CFG_PANELS = 'dashboard_panel_layout';

    /** @var string Site-default config key holding the default date range. */
    public const CFG_RANGE = 'dashboard_default_range';

    /** @var string[] Allowed insight date ranges. */
    public const RANGES = ['7d', '30d', '90d', '12m', 'ytd'];

    /** @var string Fallback range when nothing is stored. */
    public const DEFAULT_RANGE = '30d';

    /**
     * Resolve a user id, defaulting to the current user.
     *
     * @param int|null $userid User id or null for current.
     * @return int
     */
    public static function uid(?int $userid = null): int {
        global $USER;
        return $userid !== null ? $userid : (int) $USER->id;
    }

    /**
     * The stored personal row for a user, or null.
     *
     * @param int $userid User id.
     * @return \stdClass|null
     */
    public static function personal_row(int $userid): ?\stdClass {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['userid' => $userid]);
        return $row ?: null;
    }

    /**
     * Decode a personal JSON layout field, or null when absent/blank/invalid.
     *
     * @param int $userid User id.
     * @param string $field Column name (chartslayout|panellayout).
     * @return array|null
     */
    public static function personal_layout(int $userid, string $field): ?array {
        $row = self::personal_row($userid);
        if (!$row || empty($row->$field)) {
            return null;
        }
        $decoded = json_decode((string) $row->$field, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Decode a site-default JSON layout config, or null when absent/invalid.
     *
     * @param string $cfgkey Config key.
     * @return array|null
     */
    public static function site_layout(string $cfgkey): ?array {
        $raw = get_config(self::COMPONENT, $cfgkey);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Resolve a layout (personal -> site default -> null for caller defaults).
     *
     * @param int $userid User id.
     * @param string $field Personal column name.
     * @param string $cfgkey Site-default config key.
     * @return array|null
     */
    public static function resolve_layout(int $userid, string $field, string $cfgkey): ?array {
        return self::personal_layout($userid, $field) ?? self::site_layout($cfgkey);
    }

    /**
     * Upsert one or more personal fields for a user.
     *
     * @param int $userid User id.
     * @param array $fields Column => value pairs.
     */
    public static function set_personal(int $userid, array $fields): void {
        global $DB;
        $now = time();
        $row = self::personal_row($userid);
        if ($row) {
            $update = (object) ($fields + ['id' => $row->id, 'timemodified' => $now]);
            $DB->update_record(self::TABLE, $update);
        } else {
            $insert = (object) ($fields + [
                'userid' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $DB->insert_record(self::TABLE, $insert);
        }
    }

    /**
     * Remove a user's personal customisation entirely (reset to site/built-in defaults).
     *
     * @param int $userid User id.
     */
    public static function clear_personal(int $userid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['userid' => $userid]);
    }

    /**
     * Store (or clear) a site-default layout JSON.
     *
     * @param string $cfgkey Config key.
     * @param array|null $items Items list, or null to clear.
     */
    public static function set_site_layout(string $cfgkey, ?array $items): void {
        if ($items === null) {
            unset_config($cfgkey, self::COMPONENT);
            return;
        }
        set_config($cfgkey, json_encode(array_values($items)), self::COMPONENT);
    }

    /**
     * Clear all site-default dashboard layouts.
     */
    public static function clear_site(): void {
        unset_config(self::CFG_CHARTS, self::COMPONENT);
        unset_config(self::CFG_PANELS, self::COMPONENT);
        unset_config(self::CFG_RANGE, self::COMPONENT);
    }

    /**
     * Resolve the preferred default date range (personal -> site -> built-in).
     *
     * @param int $userid User id.
     * @return string
     */
    public static function resolve_range(int $userid): string {
        $row = self::personal_row($userid);
        if ($row && in_array($row->daterange, self::RANGES, true)) {
            return (string) $row->daterange;
        }
        $site = get_config(self::COMPONENT, self::CFG_RANGE);
        return in_array($site, self::RANGES, true) ? (string) $site : self::DEFAULT_RANGE;
    }

    /**
     * Persist the preferred default date range for the given scope.
     *
     * @param int $userid User id (used for personal scope).
     * @param string $range Range key.
     * @param string $scope SCOPE_PERSONAL|SCOPE_SITE.
     */
    public static function save_range(int $userid, string $range, string $scope): void {
        $range = self::clamp_range($range);
        if ($scope === self::SCOPE_SITE) {
            set_config(self::CFG_RANGE, $range, self::COMPONENT);
        } else {
            self::set_personal($userid, ['daterange' => $range]);
        }
    }

    /**
     * Clamp a range to an allowed key.
     *
     * @param string $range Requested range.
     * @return string
     */
    public static function clamp_range(string $range): string {
        return in_array($range, self::RANGES, true) ? $range : self::DEFAULT_RANGE;
    }
}

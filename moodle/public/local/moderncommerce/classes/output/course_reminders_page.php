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

namespace local_moderncommerce\output;

/**
 * Shared renderer data for Modern Course Reminder admin routes.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_reminders_page {
    /** @var string Standalone reminder add-on component. */
    private const ADDON = 'local_moderncoursereminder';

    /** @var string[] Supported route views. */
    private const VIEWS = [
        'overview',
        'rules',
        'templates',
        'schedules',
        'queue',
        'logs',
        'managers',
    ];

    /**
     * Check whether the reminder add-on is installed and upgraded.
     *
     * @return bool
     */
    public static function addon_available(): bool {
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info(self::ADDON);
        return $plugininfo !== null && $plugininfo->is_installed_and_upgraded();
    }

    /**
     * Require access to the route.
     *
     * @param bool $available Add-on availability.
     * @param \context_system $context System context.
     * @return void
     */
    public static function require_access(bool $available, \context_system $context): void {
        if ($available) {
            require_capability('local/moderncoursereminder:viewdashboard', $context);
            return;
        }

        require_capability('local/moderncommerce:managecourses', $context);
    }

    /**
     * Get the page title.
     *
     * @param bool $available Add-on availability.
     * @return string
     */
    public static function title(bool $available, string $view = 'overview'): string {
        if (!$available) {
            return get_string('courseremindersunavailable', 'local_moderncommerce');
        }

        return self::view_label($view);
    }

    /**
     * Get the page subtitle.
     *
     * @param bool $available Add-on availability.
     * @return string
     */
    public static function subtitle(bool $available, string $view = 'overview'): string {
        return $available
            ? self::view_subtitle($view)
            : get_string('courseremindersunavailable_desc', 'local_moderncommerce');
    }

    /**
     * Render the React mount or add-on unavailable state.
     *
     * @param object $output Moodle renderer.
     * @param bool $available Add-on availability.
     * @return string
     */
    public static function content(object $output, bool $available, string $view = 'overview'): string {
        if (!$available) {
            return self::unavailable_card();
        }

        $config = json_encode(self::react_config($view), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return $output->render_from_template('local_moderncommerce/admin/course_reminders', [
            'courseremindersreactconfig' => $config,
        ]);
    }

    /**
     * Build topbar actions.
     *
     * @param bool $available Add-on availability.
     * @return string
     */
    public static function actions(bool $available): string {
        if (!$available) {
            return '';
        }

        return admin_shell::action_group([
            [
                'type' => 'button',
                'label' => get_string('refresh'),
                'icon' => 'bi-arrow-clockwise',
                'attributes' => ['id' => 'moderncommerce-course-reminders-refresh'],
            ],
        ]);
    }

    /**
     * Build React config.
     *
     * @return array
     */
    private static function react_config(string $view = 'overview'): array {
        $view = self::normalise_view($view);
        return [
            'component' => '@moodle/lms/local_moderncommerce/course_reminders_admin',
            'id' => 'moderncommerce-course-reminders-admin-app',
            'class' => 'local-moderncommerce-course-reminders-admin',
            'props' => [
                'methods' => [
                    'dashboard' => 'local_moderncoursereminder_get_dashboard',
                    'healthcheck' => 'local_moderncoursereminder_get_healthcheck',
                    'listRules' => 'local_moderncoursereminder_list_rules',
                    'listTemplates' => 'local_moderncoursereminder_list_templates',
                    'listSchedules' => 'local_moderncoursereminder_list_schedules',
                    'listQueue' => 'local_moderncoursereminder_list_queue',
                    'listLogs' => 'local_moderncoursereminder_list_logs',
                    'listManagerMaps' => 'local_moderncoursereminder_list_manager_maps',
                ],
                'initialView' => $view,
                'legacyUrl' => (new \moodle_url('/local/moderncoursereminder/index.php'))->out(false),
                'perPageOptions' => [5, 10, 25, 50],
                'labels' => self::labels(),
            ],
        ];
    }

    /**
     * Labels for React UI.
     *
     * @return array
     */
    private static function labels(): array {
        return [
            'title' => get_string('coursereminders', 'local_moderncommerce'),
            'overview' => get_string('courseremindersoverview', 'local_moderncommerce'),
            'health' => get_string('courseremindershealth', 'local_moderncommerce'),
            'rules' => get_string('courseremindersrules', 'local_moderncommerce'),
            'templates' => get_string('coursereminderstemplates', 'local_moderncommerce'),
            'schedules' => get_string('courseremindersschedules', 'local_moderncommerce'),
            'queue' => get_string('courseremindersqueue', 'local_moderncommerce'),
            'logs' => get_string('coursereminderslogs', 'local_moderncommerce'),
            'managers' => get_string('courseremindersmanagers', 'local_moderncommerce'),
            'activeRules' => get_string('courseremindersactiverules', 'local_moderncommerce'),
            'disabledRules' => get_string('courseremindersdisabledrules', 'local_moderncommerce'),
            'pendingQueue' => get_string('coursereminderspendingqueue', 'local_moderncommerce'),
            'failedQueue' => get_string('courseremindersfailedqueue', 'local_moderncommerce'),
            'sentToday' => get_string('coursereminderssenttoday', 'local_moderncommerce'),
            'sentWeek' => get_string('coursereminderssentweek', 'local_moderncommerce'),
            'failedToday' => get_string('courseremindersfailedtoday', 'local_moderncommerce'),
            'effectiveness' => get_string('coursereminderseffectiveness', 'local_moderncommerce'),
            'templatesTotal' => get_string('coursereminderstemplatestotal', 'local_moderncommerce'),
            'schedulesTotal' => get_string('courseremindersschedulestotal', 'local_moderncommerce'),
            'overdue' => get_string('courseremindersoverdue', 'local_moderncommerce'),
            'escalations' => get_string('courseremindersescalations', 'local_moderncommerce'),
            'analytics' => get_string('courseremindersanalytics', 'local_moderncommerce'),
            'cached' => get_string('coursereminderscached', 'local_moderncommerce'),
            'live' => get_string('coursereminderslive', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchPlaceholder' => get_string('coursereminderssearchplaceholder', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'course' => get_string('course', 'local_moderncommerce'),
            'name' => get_string('name'),
            'type' => get_string('type', 'local_moderncommerce'),
            'audience' => get_string('courseremindersaudience', 'local_moderncommerce'),
            'subject' => get_string('subject'),
            'recipient' => get_string('courseremindersrecipient', 'local_moderncommerce'),
            'channel' => get_string('coursereminderschannel', 'local_moderncommerce'),
            'date' => get_string('date', 'local_moderncommerce'),
            'scheduled' => get_string('courseremindersscheduled', 'local_moderncommerce'),
            'sent' => get_string('coursereminderssent', 'local_moderncommerce'),
            'attempts' => get_string('courseremindersattempts', 'local_moderncommerce'),
            'manager' => get_string('courseremindersmanager', 'local_moderncommerce'),
            'learner' => get_string('coursereminderslearner', 'local_moderncommerce'),
            'source' => get_string('coursereminderssource', 'local_moderncommerce'),
            'scope' => get_string('courseremindersscope', 'local_moderncommerce'),
            'trigger' => get_string('coursereminderstrigger', 'local_moderncommerce'),
            'priority' => get_string('coursereminderspriority', 'local_moderncommerce'),
            'frequency' => get_string('courseremindersfrequency', 'local_moderncommerce'),
            'enabled' => get_string('enabled', 'local_moderncommerce'),
            'disabled' => get_string('disabled', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'noresults' => get_string('noresults', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'previous' => get_string('previous'),
            'next' => get_string('next'),
            'page' => get_string('page', 'local_moderncommerce'),
            'view' => get_string('view'),
            'openStandalone' => get_string('courseremindersopenlegacy', 'local_moderncommerce'),
            'sectionUnavailable' => get_string('coursereminderssectionunavailable', 'local_moderncommerce'),
            'engineEnabled' => get_string('courseremindersengineenabled', 'local_moderncommerce'),
            'engineDisabled' => get_string('courseremindersenginedisabled', 'local_moderncommerce'),
        ];
    }

    /**
     * Normalise a requested route view.
     *
     * @param string $view Requested view.
     * @return string
     */
    public static function normalise_view(string $view): string {
        return in_array($view, self::VIEWS, true) ? $view : 'overview';
    }

    /**
     * Return the active navigation key for a route view.
     *
     * @param string $view Route view.
     * @return string
     */
    public static function active_nav(string $view): string {
        $view = self::normalise_view($view);
        return $view === 'overview' ? 'reminders' : 'reminders' . $view;
    }

    /**
     * Display label for a route view.
     *
     * @param string $view Route view.
     * @return string
     */
    private static function view_label(string $view): string {
        return match (self::normalise_view($view)) {
            'rules' => get_string('courseremindersrules', 'local_moderncommerce'),
            'templates' => get_string('coursereminderstemplates', 'local_moderncommerce'),
            'schedules' => get_string('courseremindersschedules', 'local_moderncommerce'),
            'queue' => get_string('courseremindersqueue', 'local_moderncommerce'),
            'logs' => get_string('coursereminderslogs', 'local_moderncommerce'),
            'managers' => get_string('courseremindersmanagers', 'local_moderncommerce'),
            default => get_string('coursereminders', 'local_moderncommerce'),
        };
    }

    /**
     * Subtitle for a route view.
     *
     * @param string $view Route view.
     * @return string
     */
    private static function view_subtitle(string $view): string {
        return match (self::normalise_view($view)) {
            'rules' => self::optional_string('courseremindersrules_desc'),
            'templates' => self::optional_string('coursereminderstemplates_desc'),
            'schedules' => self::optional_string('courseremindersschedules_desc'),
            'queue' => self::optional_string('courseremindersqueue_desc'),
            'logs' => self::optional_string('coursereminderslogs_desc'),
            'managers' => self::optional_string('courseremindersmanagers_desc'),
            default => get_string('coursereminders_desc', 'local_moderncommerce'),
        };
    }

    /**
     * Get an optional Modern Commerce string without raising a missing-string notice.
     *
     * @param string $identifier String identifier.
     * @return string
     */
    private static function optional_string(string $identifier): string {
        if (get_string_manager()->string_exists($identifier, 'local_moderncommerce')) {
            return get_string($identifier, 'local_moderncommerce');
        }

        return get_string('coursereminders_desc', 'local_moderncommerce');
    }

    /**
     * Render the add-on required empty state.
     *
     * @return string
     */
    private static function unavailable_card(): string {
        return '<section class="mc-card"><div class="mc-card-body">'
            . '<div class="mc-empty mc-empty--centered">'
            . '<span class="mc-empty__icon"><i class="bi bi-clock" aria-hidden="true"></i></span>'
            . '<p class="mc-empty__title">' . s(get_string('courseremindersunavailable', 'local_moderncommerce')) . '</p>'
            . '<p class="mc-empty__text">' . s(get_string('courseremindersunavailable_desc', 'local_moderncommerce')) . '</p>'
            . '</div></div></section>';
    }
}

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
 * Shared renderer data for Modern Enrolment Notifier admin routes.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment_notifier_page {
    /** @var string Standalone enrolment notifier add-on component. */
    private const ADDON = 'local_modernenrolnotifier';

    /** @var string[] Supported route views. */
    private const VIEWS = [
        'overview',
        'rules',
        'templates',
        'settings',
        'queue',
        'logs',
        'digest',
        'managers',
    ];

    /**
     * Check whether the notifier add-on is installed and upgraded.
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
            require_capability('local/modernenrolnotifier:viewlogs', $context);
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
            return get_string('enrolmentnotifierunavailable', 'local_moderncommerce');
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
            : get_string('enrolmentnotifierunavailable_desc', 'local_moderncommerce');
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

        return $output->render_from_template('local_moderncommerce/admin/enrolment_notifier', [
            'enrolmentnotifierreactconfig' => $config,
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
                'attributes' => ['id' => 'moderncommerce-enrolment-notifier-refresh'],
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
            'component' => '@moodle/lms/local_moderncommerce/enrolment_notifier_admin',
            'id' => 'moderncommerce-enrolment-notifier-admin-app',
            'class' => 'local-moderncommerce-enrolment-notifier-admin',
            'props' => [
                'methods' => [
                    'dashboard' => 'local_modernenrolnotifier_get_dashboard',
                    'listRules' => 'local_modernenrolnotifier_list_rules',
                    'listTemplates' => 'local_modernenrolnotifier_list_templates',
                    'listCourseSettings' => 'local_modernenrolnotifier_list_course_settings',
                    'listQueue' => 'local_modernenrolnotifier_list_queue',
                    'listLogs' => 'local_modernenrolnotifier_list_logs',
                    'listDigest' => 'local_modernenrolnotifier_list_digest',
                    'listManagerMaps' => 'local_modernenrolnotifier_list_manager_maps',
                ],
                'initialView' => $view,
                'legacyUrl' => (new \moodle_url('/local/modernenrolnotifier/dashboard.php'))->out(false),
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
            'title' => get_string('enrolmentnotifier', 'local_moderncommerce'),
            'overview' => get_string('enrolmentnotifieroverview', 'local_moderncommerce'),
            'rules' => get_string('enrolmentnotifierrules', 'local_moderncommerce'),
            'templates' => get_string('enrolmentnotifiertemplates', 'local_moderncommerce'),
            'courseSettings' => get_string('enrolmentnotifiercoursesettings', 'local_moderncommerce'),
            'queue' => get_string('enrolmentnotifierqueue', 'local_moderncommerce'),
            'logs' => get_string('enrolmentnotifierlogs', 'local_moderncommerce'),
            'digest' => get_string('enrolmentnotifierdigest', 'local_moderncommerce'),
            'managers' => get_string('enrolmentnotifiermanagers', 'local_moderncommerce'),
            'activeRules' => get_string('enrolmentnotifieractiverules', 'local_moderncommerce'),
            'disabledRules' => get_string('enrolmentnotifierdisabledrules', 'local_moderncommerce'),
            'sent30' => get_string('enrolmentnotifiersent30', 'local_moderncommerce'),
            'failed30' => get_string('enrolmentnotifierfailed30', 'local_moderncommerce'),
            'sentAll' => get_string('enrolmentnotifiersentall', 'local_moderncommerce'),
            'pendingQueue' => get_string('enrolmentnotifierpendingqueue', 'local_moderncommerce'),
            'failedQueue' => get_string('enrolmentnotifierfailedqueue', 'local_moderncommerce'),
            'cancelledQueue' => get_string('enrolmentnotifiercancelledqueue', 'local_moderncommerce'),
            'digestPending' => get_string('enrolmentnotifierdigestpending', 'local_moderncommerce'),
            'templatesTotal' => get_string('enrolmentnotifiertemplatestotal', 'local_moderncommerce'),
            'courseSettingsTotal' => get_string('enrolmentnotifiercoursesettingstotal', 'local_moderncommerce'),
            'expiringSoon' => get_string('enrolmentnotifierexpiringsoon', 'local_moderncommerce'),
            'recent' => get_string('enrolmentnotifierrecent', 'local_moderncommerce'),
            'byEvent' => get_string('enrolmentnotifierbyevent', 'local_moderncommerce'),
            'byChannel' => get_string('enrolmentnotifierbychannel', 'local_moderncommerce'),
            'byStatus' => get_string('enrolmentnotifierbystatus', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchPlaceholder' => get_string('enrolmentnotifiersearchplaceholder', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'course' => get_string('course', 'local_moderncommerce'),
            'name' => get_string('name'),
            'event' => get_string('enrolmentnotifierevent', 'local_moderncommerce'),
            'recipient' => get_string('enrolmentnotifierrecipient', 'local_moderncommerce'),
            'channel' => get_string('enrolmentnotifierchannel', 'local_moderncommerce'),
            'date' => get_string('date', 'local_moderncommerce'),
            'scheduled' => get_string('enrolmentnotifierscheduled', 'local_moderncommerce'),
            'sent' => get_string('enrolmentnotifiersent', 'local_moderncommerce'),
            'attempts' => get_string('enrolmentnotifierattempts', 'local_moderncommerce'),
            'manager' => get_string('enrolmentnotifiermanager', 'local_moderncommerce'),
            'learner' => get_string('enrolmentnotifierlearner', 'local_moderncommerce'),
            'source' => get_string('enrolmentnotifiersource', 'local_moderncommerce'),
            'scope' => get_string('enrolmentnotifierscope', 'local_moderncommerce'),
            'priority' => get_string('enrolmentnotifierpriority', 'local_moderncommerce'),
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
            'openStandalone' => get_string('enrolmentnotifieropenlegacy', 'local_moderncommerce'),
            'sectionUnavailable' => get_string('enrolmentnotifiersectionunavailable', 'local_moderncommerce'),
            'template' => get_string('enrolmentnotifiertemplate', 'local_moderncommerce'),
            'subject' => get_string('subject'),
            'updated' => get_string('lastmodified', 'local_moderncommerce'),
            'customMessage' => get_string('enrolmentnotifiercustommessage', 'local_moderncommerce'),
            'frequency' => get_string('enrolmentnotifierfrequency', 'local_moderncommerce'),
            'rule' => get_string('enrolmentnotifierrule', 'local_moderncommerce'),
            'error' => get_string('error'),
            'mcrManagermap' => get_string('enrolmentnotifiermcrmanagermap', 'local_moderncommerce'),
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
        return $view === 'overview' ? 'notifier' : 'notifier' . $view;
    }

    /**
     * Display label for a route view.
     *
     * @param string $view Route view.
     * @return string
     */
    private static function view_label(string $view): string {
        return match (self::normalise_view($view)) {
            'rules' => get_string('enrolmentnotifierrules', 'local_moderncommerce'),
            'templates' => get_string('enrolmentnotifiertemplates', 'local_moderncommerce'),
            'settings' => get_string('enrolmentnotifiercoursesettings', 'local_moderncommerce'),
            'queue' => get_string('enrolmentnotifierqueue', 'local_moderncommerce'),
            'logs' => get_string('enrolmentnotifierlogs', 'local_moderncommerce'),
            'digest' => get_string('enrolmentnotifierdigest', 'local_moderncommerce'),
            'managers' => get_string('enrolmentnotifiermanagers', 'local_moderncommerce'),
            default => get_string('enrolmentnotifier', 'local_moderncommerce'),
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
            'rules' => self::optional_string('enrolmentnotifierrules_desc'),
            'templates' => self::optional_string('enrolmentnotifiertemplates_desc'),
            'settings' => self::optional_string('enrolmentnotifiercoursesettings_desc'),
            'queue' => self::optional_string('enrolmentnotifierqueue_desc'),
            'logs' => self::optional_string('enrolmentnotifierlogs_desc'),
            'digest' => self::optional_string('enrolmentnotifierdigest_desc'),
            'managers' => self::optional_string('enrolmentnotifiermanagers_desc'),
            default => get_string('enrolmentnotifier_desc', 'local_moderncommerce'),
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

        return get_string('enrolmentnotifier_desc', 'local_moderncommerce');
    }

    /**
     * Render the add-on required empty state.
     *
     * @return string
     */
    private static function unavailable_card(): string {
        return '<section class="mc-card"><div class="mc-card-body">'
            . '<div class="mc-empty mc-empty--centered">'
            . '<span class="mc-empty__icon"><i class="bi bi-bell" aria-hidden="true"></i></span>'
            . '<p class="mc-empty__title">' . s(get_string('enrolmentnotifierunavailable', 'local_moderncommerce')) . '</p>'
            . '<p class="mc-empty__text">' . s(get_string('enrolmentnotifierunavailable_desc', 'local_moderncommerce')) . '</p>'
            . '</div></div></section>';
    }
}

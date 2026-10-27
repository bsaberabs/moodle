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
 * Admin dashboard (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;
use local_moderncommerce\api\order_api;
use local_moderncommerce\services\admin_access_service;
use local_moderncommerce\services\dashboard_pref_service;

require_login();

$context = context_system::instance();
if (!has_capability('local/moderncommerce:viewreports', $context)) {
    $fallbackurl = admin_access_service::resolve_landing_url($context);
    if ($fallbackurl !== null) {
        redirect($fallbackurl);
    }

    try {
        $requestedpath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (!$requestedpath) {
            $requestedpath = '/local/moderncommerce/admin/index.php';
        }

        order_api::log_audit(
            (int) $USER->id,
            'admin_access_denied',
            'admin',
            0,
            null,
            [
                'path' => $requestedpath,
                'requiredcapability' => 'local/moderncommerce:viewreports',
                'contextid' => (int) $context->id,
                'redirectedto' => '/local/moderncommerce/learner/index.php#/dashboard',
            ],
            'denied',
            'warning'
        );
    } catch (\Throwable $exception) {
        debugging('Modern Commerce admin access audit logging failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
    }

    redirect((new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/dashboard');
}
require_capability('local/moderncommerce:viewreports', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/index.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('pluginname', 'local_moderncommerce') . ': ' . get_string('overview', 'local_moderncommerce'));

$dashboardreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/dashboard_admin',
    'id' => 'moderncommerce-dashboard-admin-app',
    'class' => 'local-moderncommerce-dashboard-admin',
    'props' => [
        'methodName' => 'local_moderncommerce_admin_get_dashboard',
        'ordersUrl' => (new moodle_url('/local/moderncommerce/admin/orders.php'))->out(false),
        'productsUrl' => (new moodle_url('/local/moderncommerce/admin/pricing.php'))->out(false),
        'reportsUrl' => (new moodle_url('/local/moderncommerce/admin/reports.php'))->out(false),
        'useComponentTypography' => true,
        'labels' => [
            'title' => get_string('overview', 'local_moderncommerce'),
            'recentorders' => get_string('recentorders', 'local_moderncommerce'),
            'topproducts' => get_string('topproducts', 'local_moderncommerce'),
            'setup' => get_string('setupalerts', 'local_moderncommerce'),
            'viewall' => get_string('viewall', 'local_moderncommerce'),
            'order' => get_string('order', 'local_moderncommerce'),
            'customer' => get_string('customer', 'local_moderncommerce'),
            'total' => get_string('total', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'date' => get_string('date', 'local_moderncommerce'),
            'product' => get_string('product', 'local_moderncommerce'),
            'sold' => get_string('sold', 'local_moderncommerce'),
            'revenue' => get_string('revenue', 'local_moderncommerce'),
            'noorders' => get_string('noordersfound', 'local_moderncommerce'),
            'nodata' => get_string('nodata', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/dashboard', [
    'dashboardreactconfig' => $dashboardreactconfig,
]);

$chartsreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/dashboard_charts',
    'id' => 'moderncommerce-dashboard-charts-app',
    'class' => 'local-moderncommerce-dashboard-charts',
    'props' => [
        'methodName' => 'local_moderncommerce_admin_get_dashboard_charts',
        'defaultRange' => dashboard_pref_service::resolve_range((int) $USER->id),
        'canManage' => true,
        'canSaveDefault' => has_capability('local/moderncommerce:managesettings', $context),
        'layoutGetMethod' => 'local_moderncommerce_admin_get_dashboard_layout',
        'layoutSaveMethod' => 'local_moderncommerce_admin_save_dashboard_layout',
        'labels' => [
            'title' => get_string('chart_title', 'local_moderncommerce'),
            'range_7d' => get_string('chart_range_7d', 'local_moderncommerce'),
            'range_30d' => get_string('chart_range_30d', 'local_moderncommerce'),
            'range_90d' => get_string('chart_range_90d', 'local_moderncommerce'),
            'range_12m' => get_string('chart_range_12m', 'local_moderncommerce'),
            'range_ytd' => get_string('chart_range_ytd', 'local_moderncommerce'),
            'loading' => get_string('chart_loading', 'local_moderncommerce'),
            'error' => get_string('chart_error', 'local_moderncommerce'),
            'noresults' => get_string('chart_noresults', 'local_moderncommerce'),
            'nocharts' => get_string('chart_no_charts', 'local_moderncommerce'),
            'customize' => get_string('chart_customize', 'local_moderncommerce'),
            'managetitle' => get_string('chart_manage_title', 'local_moderncommerce'),
            'manageintro' => get_string('dash_manage_intro', 'local_moderncommerce'),
            'show' => get_string('chart_show', 'local_moderncommerce'),
            'size' => get_string('chart_size', 'local_moderncommerce'),
            'moveup' => get_string('chart_move_up', 'local_moderncommerce'),
            'movedown' => get_string('chart_move_down', 'local_moderncommerce'),
            'save' => get_string('savechanges', 'local_moderncommerce'),
            'cancel' => get_string('cancel'),
            'reset' => get_string('chart_reset_defaults', 'local_moderncommerce'),
            'panelsheading' => get_string('dash_panels_heading', 'local_moderncommerce'),
            'chartsheading' => get_string('dash_charts_heading', 'local_moderncommerce'),
            'rangeheading' => get_string('dash_range_heading', 'local_moderncommerce'),
            'rangehelp' => get_string('dash_range_help', 'local_moderncommerce'),
            'scopeheading' => get_string('dash_scope_heading', 'local_moderncommerce'),
            'scopepersonal' => get_string('dash_scope_personal', 'local_moderncommerce'),
            'scopesite' => get_string('dash_scope_site', 'local_moderncommerce'),
            'scopehelp' => get_string('dash_scope_help', 'local_moderncommerce'),
            'resethelp' => get_string('dash_reset_help', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml .= $OUTPUT->render_from_template('local_moderncommerce/admin/dashboard_charts', [
    'chartsreactconfig' => $chartsreactconfig,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'button',
        'label' => get_string('refresh'),
        'icon' => 'bi-arrow-clockwise',
        'attributes' => ['id' => 'moderncommerce-dashboard-refresh'],
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'dashboard',
    get_string('overview', 'local_moderncommerce'),
    $contenthtml,
    get_string('dashboardsubtitle', 'local_moderncommerce'),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();

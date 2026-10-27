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
 * Reports and analytics (React + webservice driven, with CSV export).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;
use local_moderncommerce\services\report_service;

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:viewreports', $context);

$reporttype = optional_param('type', 'sales', PARAM_ALPHA);
$period = optional_param('period', 'monthly', PARAM_ALPHA);
$daterange = optional_param('daterange', '', PARAM_ALPHANUMEXT);
$fromstr = optional_param('from', '', PARAM_TEXT);
$tostr = optional_param('to', '', PARAM_TEXT);
$productsearch = optional_param('productsearch', '', PARAM_TEXT);
$coursesearch = optional_param('coursesearch', '', PARAM_TEXT);
$tablesearch = optional_param('tablesearch', '', PARAM_TEXT);
$fromdate = $fromstr ? strtotime($fromstr) : strtotime('-30 days');
$todate = $tostr ? strtotime($tostr) : time();
if ($tostr && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tostr)) {
    $todate = strtotime($tostr . ' 23:59:59');
}
$export = optional_param('export', '', PARAM_ALPHA);

$baseurl = new moodle_url('/local/moderncommerce/admin/reports.php');
$daterangeoptions = ['today', 'last7days', 'last30days', 'thismonth', 'lastmonth', 'thisyear', 'custom'];
$initialdaterange = in_array($daterange, $daterangeoptions, true) ? $daterange : (($fromstr || $tostr) ? 'custom' : 'last30days');

// CSV export of the daily sales series for the selected range.
if ($export === 'csv') {
    require_sesskey();
    $rawcolumns = $_POST['columns'] ?? $_GET['columns'] ?? null;
    $columnarray = is_array($rawcolumns) ? optional_param_array('columns', [], PARAM_ALPHANUMEXT) : [];
    $columncsv = is_array($rawcolumns) ? '' : optional_param('columns', '', PARAM_TEXT);
    $columns = report_service::parse_columns($columnarray, $columncsv);
    $exportdata = report_service::get_export(
        $reporttype,
        $period,
        $fromdate,
        $todate,
        $context,
        $columns,
        $productsearch,
        $coursesearch,
        $tablesearch
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $exportdata['filename'] . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $exportdata['headers']);
    foreach ($exportdata['rows'] as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('reports', 'local_moderncommerce'));

$reportsreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/reports_admin',
    'id' => 'moderncommerce-reports-admin-app',
    'class' => 'local-moderncommerce-reports-admin',
    'props' => [
        'methodName' => 'local_moderncommerce_admin_get_report',
        'initialType' => in_array($reporttype, ['sales', 'courses', 'coupons'], true) ? $reporttype : 'sales',
        'initialPeriod' => in_array($period, ['daily', 'weekly', 'monthly', 'yearly'], true) ? $period : 'monthly',
        'initialDateRange' => $initialdaterange,
        'initialFrom' => (int) $fromdate,
        'initialTo' => (int) $todate,
        'initialProductSearch' => $productsearch,
        'initialCourseSearch' => $coursesearch,
        'initialTableSearch' => $tablesearch,
        'exportBase' => $baseurl->out(false),
        'sesskey' => sesskey(),
        'perPageOptions' => [10, 25, 50, 100],
        'reportTypes' => [
            ['value' => 'sales', 'label' => get_string('salesreport', 'local_moderncommerce')],
            ['value' => 'courses', 'label' => get_string('topcourses', 'local_moderncommerce')],
            ['value' => 'coupons', 'label' => get_string('couponusagereport', 'local_moderncommerce')],
        ],
        'periodOptions' => [
            ['value' => 'daily', 'label' => get_string('daily', 'local_moderncommerce')],
            ['value' => 'weekly', 'label' => get_string('weekly', 'local_moderncommerce')],
            ['value' => 'monthly', 'label' => get_string('monthly', 'local_moderncommerce')],
            ['value' => 'yearly', 'label' => get_string('yearly', 'local_moderncommerce')],
        ],
        'dateRangeOptions' => [
            ['value' => 'today', 'label' => get_string('daterange_today', 'local_moderncommerce')],
            ['value' => 'last7days', 'label' => get_string('daterange_last7days', 'local_moderncommerce')],
            ['value' => 'last30days', 'label' => get_string('daterange_last30days', 'local_moderncommerce')],
            ['value' => 'thismonth', 'label' => get_string('daterange_thismonth', 'local_moderncommerce')],
            ['value' => 'lastmonth', 'label' => get_string('daterange_lastmonth', 'local_moderncommerce')],
            ['value' => 'thisyear', 'label' => get_string('daterange_thisyear', 'local_moderncommerce')],
            ['value' => 'custom', 'label' => get_string('daterange_custom', 'local_moderncommerce')],
        ],
        'labels' => [
            'title' => get_string('reports', 'local_moderncommerce'),
            'reporttype' => get_string('reporttype', 'local_moderncommerce'),
            'period' => get_string('period', 'local_moderncommerce'),
            'daterange' => get_string('daterange', 'local_moderncommerce'),
            'datefrom' => get_string('datefrom', 'local_moderncommerce'),
            'dateto' => get_string('dateto', 'local_moderncommerce'),
            'productsearch' => get_string('reportproductsearch', 'local_moderncommerce'),
            'productsearchplaceholder' => get_string('reportproductsearchplaceholder', 'local_moderncommerce'),
            'coursesearch' => get_string('reportcoursesearch', 'local_moderncommerce'),
            'coursesearchplaceholder' => get_string('reportcoursesearchplaceholder', 'local_moderncommerce'),
            'totalrevenue' => get_string('totalrevenue', 'local_moderncommerce'),
            'totalorders' => get_string('totalorders', 'local_moderncommerce'),
            'averageorder' => get_string('averageorder', 'local_moderncommerce'),
            'couponsused' => get_string('couponsused', 'local_moderncommerce'),
            'salesreport' => get_string('salesreport', 'local_moderncommerce'),
            'topcourses' => get_string('topcourses', 'local_moderncommerce'),
            'couponusagereport' => get_string('couponusagereport', 'local_moderncommerce'),
            'date' => get_string('date', 'local_moderncommerce'),
            'revenue' => get_string('revenue', 'local_moderncommerce'),
            'orders' => get_string('orders', 'local_moderncommerce'),
            'average' => get_string('averageorder', 'local_moderncommerce'),
            'rank' => get_string('rank', 'local_moderncommerce'),
            'course' => get_string('course'),
            'enrollments' => get_string('enrollments', 'local_moderncommerce'),
            'coupon' => get_string('coupon', 'local_moderncommerce'),
            'type' => get_string('type', 'local_moderncommerce'),
            'value' => get_string('value', 'local_moderncommerce'),
            'usage' => get_string('usage', 'local_moderncommerce'),
            'totaldiscount' => get_string('totaldiscount', 'local_moderncommerce'),
            'nodata' => get_string('nodata', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'exportcsv' => get_string('exportcsv', 'local_moderncommerce'),
            'reportfilters' => get_string('reportfilters', 'local_moderncommerce'),
            'reportfiltersdesc' => get_string('reportfiltersdesc', 'local_moderncommerce'),
            'tablecontrols' => get_string('tablecontrols', 'local_moderncommerce'),
            'tablesearch' => get_string('reporttablesearch', 'local_moderncommerce'),
            'tablesearchplaceholder' => get_string('reporttablesearchplaceholder', 'local_moderncommerce'),
            'columns' => get_string('reportcolumns', 'local_moderncommerce'),
            'columnsdesc' => get_string('reportcolumnsdesc', 'local_moderncommerce'),
            'showcolumns' => get_string('showcolumns', 'local_moderncommerce'),
            'applycolumns' => get_string('applycolumns', 'local_moderncommerce'),
            'resetcolumns' => get_string('resetcolumns', 'local_moderncommerce'),
            'selectedcolumns' => get_string('selectedcolumns', 'local_moderncommerce'),
            'reporttable' => get_string('reporttable', 'local_moderncommerce'),
            'reporttablepreview' => get_string('reporttablepreview', 'local_moderncommerce'),
            'reporttabletruncated' => get_string('reporttabletruncated', 'local_moderncommerce'),
            'customize' => get_string('reportcustomize', 'local_moderncommerce'),
            'customizedesc' => get_string('reportcustomizedesc', 'local_moderncommerce'),
            'metrictiles' => get_string('metrictiles', 'local_moderncommerce'),
            'chartvisibility' => get_string('chartvisibility', 'local_moderncommerce'),
            'show' => get_string('chart_show', 'local_moderncommerce'),
            'size' => get_string('chart_size', 'local_moderncommerce'),
            'sizefull' => get_string('chart_size_full', 'local_moderncommerce'),
            'sizehalf' => get_string('chart_size_half', 'local_moderncommerce'),
            'sizethird' => get_string('chart_size_third', 'local_moderncommerce'),
            'sizequarter' => get_string('chart_size_quarter', 'local_moderncommerce'),
            'moveup' => get_string('chart_move_up', 'local_moderncommerce'),
            'movedown' => get_string('chart_move_down', 'local_moderncommerce'),
            'save' => get_string('savechanges', 'local_moderncommerce'),
            'reset' => get_string('reset', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'page' => get_string('page', 'local_moderncommerce'),
            'previous' => get_string('previous', 'local_moderncommerce'),
            'next' => get_string('next', 'local_moderncommerce'),
            'charts' => get_string('chart_title', 'local_moderncommerce'),
            'close' => get_string('close', 'local_moderncommerce'),
            'cancel' => get_string('cancel', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/reports', [
    'reportsreactconfig' => $reportsreactconfig,
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'reports',
    get_string('reports', 'local_moderncommerce'),
    $contenthtml,
    get_string('reportsdesc', 'local_moderncommerce')
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();

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
 * Learner workspace single-page React app.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\output\learner_shell;

/**
 * Load Modern Commerce labels for the learner React app.
 *
 * The app routes many learner pages from one mount, so it is cheaper and less
 * brittle to provide the component's language pack once than maintain duplicate
 * label lists in every legacy route.
 *
 * @return array
 */
function local_moderncommerce_learner_app_labels(): array {
    $labels = get_string_manager()->load_component_strings('local_moderncommerce', current_language());

    $labels['email'] = get_string('email');
    $labels['firstname'] = get_string('firstname');
    $labels['lastname'] = get_string('lastname');
    $labels['home'] = get_string('home');
    $labels['backtocart'] = get_string('backtocart', 'local_moderncommerce');

    return $labels;
}

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/learner/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('myaccount', 'local_moderncommerce'));
$PAGE->set_heading(get_string('myaccount', 'local_moderncommerce'));
$PAGE->requires->js_call_amd('core_calendar/popover');

$calendar = \calendar_information::create(time(), SITEID, null);
$renderer = $PAGE->get_renderer('core_calendar');

$monthhtml = $renderer->start_layout();
[$monthdata] = calendar_get_view($calendar, 'monthblock', isloggedin());
$monthdata->iscalendarblock = true;
$monthhtml .= html_writer::start_tag('div', [
    'id' => 'moderncommerce-learner-calendar-month',
    'data-template' => 'core_calendar/month_detailed',
]);
$monthhtml .= $renderer->render_from_template('core_calendar/header', $monthdata);
$monthhtml .= $renderer->render_from_template('core_calendar/month_detailed', $monthdata);
$monthhtml .= html_writer::end_tag('div');

[$footerdata, $footertemplate] = calendar_get_footer_options($calendar, [
    'showfullcalendarlink' => true,
]);
$monthfooterhtml = $renderer->render_from_template($footertemplate, $footerdata);
$monthhtml .= $renderer->complete_layout();

[$upcomingdata, $upcomingtemplate] = calendar_get_view($calendar, 'upcoming_mini');
$upcominghtml = $renderer->render_from_template($upcomingtemplate, $upcomingdata);

$shell = learner_shell::create('dashboard');
$learnerappurl = (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false);

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/learner_app',
    'id' => 'moderncommerce-learner-app',
    'class' => 'local-moderncommerce-learner-app',
    'props' => [
        'services' => [
            'catalog' => 'local_moderncommerce_get_catalog',
            'dashboard' => 'local_moderncommerce_get_learner_dashboard',
            'courses' => 'local_moderncommerce_list_learner_courses',
            'orders' => 'local_moderncommerce_list_learner_orders',
            'order' => 'local_moderncommerce_get_learner_order',
            'wishlist' => 'local_moderncommerce_list_learner_wishlist',
            'wishlistUpdate' => 'local_moderncommerce_update_learner_wishlist',
            'certificates' => 'local_moderncommerce_list_learner_certificates',
            'subscription' => 'local_moderncommerce_get_learner_subscription',
            'subscriptionAccess' => 'local_moderncommerce_get_learner_subscription_access',
            'planOptions' => 'local_moderncommerce_get_plan_options',
            'changeSubscription' => 'local_moderncommerce_change_subscription',
            'productAccess' => 'local_moderncommerce_get_learner_product_access',
            'grades' => 'local_moderncommerce_list_learner_grades',
            'cart' => 'local_moderncommerce_get_cart',
            'cartUpdate' => 'local_moderncommerce_update_cart',
            'checkoutStart' => 'local_moderncommerce_start_checkout',
            'checkoutPlaceOrder' => 'local_moderncommerce_place_order',
            'redeemResolve' => 'local_moderncommerce_resolve_key',
            'redeemAny' => 'local_moderncommerce_redeem_any_key',
            'profileGet' => 'local_moderncommerce_get_learner_profile',
            'profileSave' => 'local_moderncommerce_save_learner_profile',
            'profileSavePicture' => 'local_moderncommerce_save_learner_profile_picture',
        ],
        'layout' => $shell->get_react_layout_context(),
        'publicLinks' => local_moderncommerce_public_links(),
        'labels' => local_moderncommerce_learner_app_labels(),
        'calendar' => [
            'monthHtml' => $monthhtml,
            'monthFooterHtml' => $monthfooterhtml,
            'upcomingHtml' => $upcominghtml,
            'calendarUrl' => (new moodle_url('/calendar/view.php', ['view' => 'month']))->out(false),
            'upcomingUrl' => (new moodle_url('/calendar/view.php', ['view' => 'upcoming']))->out(false),
        ],
        'urls' => [
            'catalog' => $learnerappurl . '#/library',
            'courses' => $learnerappurl . '#/courses',
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
    'region' => 'moderncommerce-learner-app',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-grid-1x2',
]);

echo $OUTPUT->header();
echo $contenthtml;
echo $OUTPUT->footer();

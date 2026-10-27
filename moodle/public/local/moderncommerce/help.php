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
 * Modern Commerce help landing page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/moderncommerce/help.php'));
$PAGE->set_title(get_string('help', 'local_moderncommerce'));
$PAGE->set_heading(get_string('help', 'local_moderncommerce'));

echo $OUTPUT->header();

// Breadcrumbs.
$breadcrumbs = [
    ['text' => get_string('home'), 'url' => new moodle_url('/my/')],
    ['text' => get_string('moderncommerce', 'local_moderncommerce'), 'url' => new moodle_url('/local/moderncommerce/index.php')],
    ['text' => get_string('help', 'local_moderncommerce'), 'url' => null],
];

echo '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
foreach ($breadcrumbs as $crumb) {
    if ($crumb['url']) {
        echo '<li class="breadcrumb-item"><a href="' . $crumb['url'] . '">' . $crumb['text'] . '</a></li>';
    } else {
        echo '<li class="breadcrumb-item active">' . $crumb['text'] . '</li>';
    }
}
echo '</ol></nav>';

echo html_writer::tag('h2', get_string('help_guide', 'local_moderncommerce'), ['class' => 'mb-4']);

// Introduction.
echo html_writer::start_div('card mb-4');
echo html_writer::div(get_string('help_introduction', 'local_moderncommerce'), 'card-header h4');
echo html_writer::start_div('card-body');
echo html_writer::tag('p', get_string('help_intro_text', 'local_moderncommerce'));
echo html_writer::end_div();
echo html_writer::end_div();

// For Students.
echo html_writer::start_div('card mb-4');
echo html_writer::div(get_string('help_for_students', 'local_moderncommerce'), 'card-header h4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h5', get_string('help_browsing_courses', 'local_moderncommerce'), ['class' => 'mt-3']);
echo html_writer::tag('p', get_string('help_browsing_text', 'local_moderncommerce'));
echo html_writer::start_tag('ol');
echo html_writer::tag('li', get_string('help_browse_step1', 'local_moderncommerce'));
echo html_writer::tag('li', get_string('help_browse_step2', 'local_moderncommerce'));
echo html_writer::tag('li', get_string('help_browse_step3', 'local_moderncommerce'));
echo html_writer::end_tag('ol');

echo html_writer::tag('h5', get_string('help_purchasing_courses', 'local_moderncommerce'), ['class' => 'mt-4']);
echo html_writer::tag('p', get_string('help_purchasing_text', 'local_moderncommerce'));
echo html_writer::start_tag('ol');
echo html_writer::tag('li', get_string('help_purchase_step1', 'local_moderncommerce'));
echo html_writer::tag('li', get_string('help_purchase_step2', 'local_moderncommerce'));
echo html_writer::tag('li', get_string('help_purchase_step3', 'local_moderncommerce'));
echo html_writer::tag('li', get_string('help_purchase_step4', 'local_moderncommerce'));
echo html_writer::tag('li', get_string('help_purchase_step5', 'local_moderncommerce'));
echo html_writer::end_tag('ol');

echo html_writer::tag('h5', get_string('help_payment_methods', 'local_moderncommerce'), ['class' => 'mt-4']);
echo html_writer::start_tag('ul');
echo html_writer::tag('li', html_writer::tag('strong', 'Paystack: ') . get_string('help_paystack_desc', 'local_moderncommerce'));
echo html_writer::tag(
    'li',
    html_writer::tag('strong', 'Flutterwave: ') . get_string('help_flutterwave_desc', 'local_moderncommerce')
);
echo html_writer::tag(
    'li',
    html_writer::tag('strong', get_string('manualpayment', 'local_moderncommerce') . ': ') .
        get_string('help_manual_desc', 'local_moderncommerce')
);
echo html_writer::tag(
    'li',
    html_writer::tag('strong', get_string('enrollmentkey', 'local_moderncommerce') . ': ') .
        get_string('help_enrollkey_desc', 'local_moderncommerce')
);
echo html_writer::end_tag('ul');

echo html_writer::tag('h5', get_string('help_using_coupons', 'local_moderncommerce'), ['class' => 'mt-4']);
echo html_writer::tag('p', get_string('help_coupons_text', 'local_moderncommerce'));
echo html_writer::start_tag('ol');
echo html_writer::tag('li', get_string('help_coupon_step1', 'local_moderncommerce'));
echo html_writer::tag('li', get_string('help_coupon_step2', 'local_moderncommerce'));
echo html_writer::tag('li', get_string('help_coupon_step3', 'local_moderncommerce'));
echo html_writer::end_tag('ol');

echo html_writer::tag('h5', get_string('help_enrollment_keys', 'local_moderncommerce'), ['class' => 'mt-4']);
echo html_writer::tag('p', get_string('help_enrollment_keys_text', 'local_moderncommerce'));
echo html_writer::start_tag('ol');
echo html_writer::tag('li', get_string('help_enrollkey_step1', 'local_moderncommerce'));
echo html_writer::tag('li', get_string('help_enrollkey_step2', 'local_moderncommerce'));
echo html_writer::tag('li', get_string('help_enrollkey_step3', 'local_moderncommerce'));
echo html_writer::tag('li', get_string('help_enrollkey_step4', 'local_moderncommerce'));
echo html_writer::end_tag('ol');

echo html_writer::tag('h5', get_string('help_viewing_orders', 'local_moderncommerce'), ['class' => 'mt-4']);
echo html_writer::tag('p', get_string('help_orders_text', 'local_moderncommerce'));

echo html_writer::end_div();
echo html_writer::end_div();

// For Administrators.
if (has_capability('local/moderncommerce:managecourses', context_system::instance())) {
    echo html_writer::start_div('card mb-4');
    echo html_writer::div(get_string('help_for_admins', 'local_moderncommerce'), 'card-header h4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h5', get_string('help_setting_prices', 'local_moderncommerce'), ['class' => 'mt-3']);
    echo html_writer::tag('p', get_string('help_pricing_text', 'local_moderncommerce'));
    echo html_writer::start_tag('ol');
    echo html_writer::tag('li', get_string('help_pricing_step1', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_pricing_step2', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_pricing_step3', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_pricing_step4', 'local_moderncommerce'));
    echo html_writer::end_tag('ol');

    echo html_writer::tag('h5', get_string('help_configuring_payments', 'local_moderncommerce'), ['class' => 'mt-4']);
    echo html_writer::tag('p', get_string('help_payment_config_text', 'local_moderncommerce'));
    echo html_writer::start_tag('ol');
    echo html_writer::tag('li', get_string('help_payment_config_step1', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_payment_config_step2', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_payment_config_step3', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_payment_config_step4', 'local_moderncommerce'));
    echo html_writer::end_tag('ol');

    echo html_writer::tag('h5', get_string('help_checkout_fields', 'local_moderncommerce'), ['class' => 'mt-4']);
    echo html_writer::tag('p', get_string('help_checkout_fields_text', 'local_moderncommerce'));
    echo html_writer::start_tag('ul');
    echo html_writer::tag(
        'li',
        html_writer::tag('strong', get_string('hidden', 'local_moderncommerce') . ': ') .
            get_string('help_field_hidden', 'local_moderncommerce')
    );
    echo html_writer::tag(
        'li',
        html_writer::tag('strong', get_string('optional', 'local_moderncommerce') . ': ') .
            get_string('help_field_optional', 'local_moderncommerce')
    );
    echo html_writer::tag(
        'li',
        html_writer::tag('strong', get_string('required', 'local_moderncommerce') . ': ') .
            get_string('help_field_required', 'local_moderncommerce')
    );
    echo html_writer::end_tag('ul');

    echo html_writer::tag('h5', get_string('help_creating_coupons', 'local_moderncommerce'), ['class' => 'mt-4']);
    echo html_writer::tag('p', get_string('help_creating_coupons_text', 'local_moderncommerce'));
    echo html_writer::start_tag('ol');
    echo html_writer::tag('li', get_string('help_create_coupon_step1', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_create_coupon_step2', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_create_coupon_step3', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_create_coupon_step4', 'local_moderncommerce'));
    echo html_writer::end_tag('ol');

    echo html_writer::tag('h5', get_string('help_generating_keys', 'local_moderncommerce'), ['class' => 'mt-4']);
    echo html_writer::tag('p', get_string('help_generating_keys_text', 'local_moderncommerce'));
    echo html_writer::start_tag('ol');
    echo html_writer::tag('li', get_string('help_generate_key_step1', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_generate_key_step2', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_generate_key_step3', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_generate_key_step4', 'local_moderncommerce'));
    echo html_writer::end_tag('ol');

    echo html_writer::tag('h5', get_string('help_managing_orders', 'local_moderncommerce'), ['class' => 'mt-4']);
    echo html_writer::tag('p', get_string('help_managing_orders_text', 'local_moderncommerce'));
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', get_string('help_orders_pending', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_orders_paid', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_orders_failed', 'local_moderncommerce'));
    echo html_writer::tag('li', get_string('help_orders_refunded', 'local_moderncommerce'));
    echo html_writer::end_tag('ul');

    echo html_writer::end_div();
    echo html_writer::end_div();
}

// FAQ Section.
echo html_writer::start_div('card mb-4');
echo html_writer::div(get_string('help_faq', 'local_moderncommerce'), 'card-header h4');
echo html_writer::start_div('card-body');

$faqs = [
    ['q' => get_string('help_faq_q1', 'local_moderncommerce'), 'a' => get_string('help_faq_a1', 'local_moderncommerce')],
    ['q' => get_string('help_faq_q2', 'local_moderncommerce'), 'a' => get_string('help_faq_a2', 'local_moderncommerce')],
    ['q' => get_string('help_faq_q3', 'local_moderncommerce'), 'a' => get_string('help_faq_a3', 'local_moderncommerce')],
    ['q' => get_string('help_faq_q4', 'local_moderncommerce'), 'a' => get_string('help_faq_a4', 'local_moderncommerce')],
    ['q' => get_string('help_faq_q5', 'local_moderncommerce'), 'a' => get_string('help_faq_a5', 'local_moderncommerce')],
];

foreach ($faqs as $index => $faq) {
    echo html_writer::tag('h6', html_writer::tag('strong', 'Q: ' . $faq['q']), ['class' => 'mt-3']);
    echo html_writer::tag('p', 'A: ' . $faq['a']);
}

echo html_writer::end_div();
echo html_writer::end_div();

// Contact Support.
echo html_writer::start_div('card mb-4');
echo html_writer::div(get_string('help_support', 'local_moderncommerce'), 'card-header h4');
echo html_writer::start_div('card-body');
echo html_writer::tag('p', get_string('help_support_text', 'local_moderncommerce'));
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();

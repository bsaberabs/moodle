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
 * Buyer checkout page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\output\learner_shell;

$existingorderid = optional_param('orderid', 0, PARAM_INT);
$directcourseid = optional_param('courseid', 0, PARAM_INT);
$directbundleid = optional_param('bundleid', 0, PARAM_INT);

$urlparams = [];
if ($existingorderid > 0) {
    $urlparams['orderid'] = $existingorderid;
}
if ($directcourseid > 0) {
    $urlparams['courseid'] = $directcourseid;
}
if ($directbundleid > 0) {
    $urlparams['bundleid'] = $directbundleid;
}

$checkouturl = new moodle_url('/local/moderncommerce/checkout.php', $urlparams);

if (!isloggedin() || isguestuser()) {
    $context = context_system::instance();
    $PAGE->set_context($context);
    $PAGE->set_url($checkouturl);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title(get_string('checkout', 'local_moderncommerce'));
    $PAGE->set_heading(get_string('checkout', 'local_moderncommerce'));

    // Read optionally rather than with required_param, which would answer a
    // blank or malformed address with a fatal error page instead of a message
    // beside the field.
    $emailerror = '';
    $emailvalue = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_sesskey();
        $email = trim(optional_param('email', '', PARAM_RAW_TRIMMED));
        $emailvalue = $email;

        if ($email === '') {
            $emailerror = get_string('error:required', 'local_moderncommerce');
        } else if (!validate_email($email)) {
            $emailerror = get_string('error:invalidemail', 'local_moderncommerce');
        }

        if ($emailerror === '') {
            $SESSION->moderncommerce_checkout_email = $email;
            redirect(new moodle_url('/login/index.php', ['wantsurl' => $checkouturl->out(false)]));
        }
    }

    $loginurl = new moodle_url('/login/index.php', ['wantsurl' => $checkouturl->out(false)]);
    $hero = html_writer::tag(
        'section',
        html_writer::span(
            html_writer::tag('i', '', ['class' => 'bi bi-envelope-check']),
            'mc-public-hero__icon',
            ['aria-hidden' => 'true']
        ) .
        html_writer::span(s(get_string('checkout', 'local_moderncommerce')), 'mc-public-hero__eyebrow') .
        html_writer::tag('h1', get_string('p1_checkout_guest_title', 'local_moderncommerce'), [
            'class' => 'mc-public-hero__title',
        ]) .
        html_writer::tag(
            'p',
            get_string('p1_checkout_guest_summary', 'local_moderncommerce'),
            ['class' => 'mc-public-hero__summary']
        ),
        ['class' => 'mc-public-hero']
    );
    $emailattributes = [
        'class' => 'form-control form-control-lg' . ($emailerror !== '' ? ' mc-field-invalid' : ''),
        'id' => 'mc-checkout-email',
        'name' => 'email',
        'type' => 'email',
        'required' => 'required',
        'aria-required' => 'true',
        'autocomplete' => 'email',
        'value' => $emailvalue,
        'placeholder' => get_string('emailplaceholder', 'local_moderncommerce'),
    ];

    if ($emailerror !== '') {
        $emailattributes['aria-invalid'] = 'true';
        $emailattributes['aria-describedby'] = 'mc-checkout-email-error';
    }

    $emailmessage = $emailerror === '' ? '' : html_writer::div(
        html_writer::tag('i', '', ['class' => 'bi bi-exclamation-circle mc-field-error__icon',
            'aria-hidden' => 'true']) .
        html_writer::tag('span', s($emailerror)),
        'mc-field-error',
        ['id' => 'mc-checkout-email-error', 'role' => 'alert']
    );

    $emailfield = html_writer::div(
        html_writer::tag(
            'label',
            get_string('p1_checkout_emailaddress', 'local_moderncommerce') .
            html_writer::tag('span', '*', ['class' => 'mc-required', 'aria-hidden' => 'true']),
            ['class' => 'form-label', 'for' => 'mc-checkout-email']
        ) .
        html_writer::empty_tag('input', $emailattributes) .
        $emailmessage,
        'mb-3'
    );
    $actions = html_writer::div(
        html_writer::tag('button', get_string('p1_checkout_continue', 'local_moderncommerce'), [
            'class' => 'mc-button btn-mc-primary',
            'type' => 'submit',
            'data-mc-button' => 'primary',
        ]) .
        html_writer::link($loginurl, get_string('login'), [
            'class' => 'mc-button mc-btn-soft',
            'data-mc-button' => 'soft',
        ]),
        'd-flex gap-2 flex-wrap'
    );
    $form = html_writer::tag(
        'form',
        html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ]) .
        $emailfield .
        $actions .
        html_writer::tag(
            'p',
            get_string('p1_checkout_accountrequired', 'local_moderncommerce'),
            ['class' => 'text-muted small mt-3 mb-0']
        ),
        [
            'method' => 'post',
            'action' => $checkouturl->out(false),
            'class' => 'mc-public-card mc-support-form',
        ]
    );

    echo $OUTPUT->header();
    echo html_writer::div($hero . $form, 'mc-public-page');
    echo $OUTPUT->footer();
    exit;
}

require_login();

$context = context_system::instance();
require_capability('local/moderncommerce:purchase', $context);

$PAGE->set_context($context);
$PAGE->set_url($checkouturl);
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('checkout', 'local_moderncommerce'));
$PAGE->set_heading(get_string('checkout', 'local_moderncommerce'));

local_moderncommerce_set_breadcrumb(
    get_string('checkout', 'local_moderncommerce'),
    [
        [
            'text' => get_string('catalog', 'local_moderncommerce'),
            'url' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/library',
        ],
        [
            'text' => get_string('cart', 'local_moderncommerce'),
            'url' => (new moodle_url('/local/moderncommerce/cart.php'))->out(false),
        ],
    ]
);

$shell = learner_shell::create('checkout');
$publiclinks = local_moderncommerce_public_links();

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/checkout',
    'id' => 'moderncommerce-checkout-app',
    'class' => 'local-moderncommerce-checkout',
    'props' => [
        'startMethodName' => 'local_moderncommerce_start_checkout',
        'placeOrderMethodName' => 'local_moderncommerce_place_order',
        'cartUpdateMethodName' => 'local_moderncommerce_update_cart',
        'orderId' => $existingorderid,
        'courseId' => $directcourseid,
        'bundleId' => $directbundleid, 'layout' => $shell->get_react_layout_context(), 'publicLinks' => $publiclinks, 'labels' => [
            'applycoupon' => get_string('applycoupon', 'local_moderncommerce'),
            'backtocart' => get_string('backtocart', 'local_moderncommerce'),
            'billinginfo' => get_string('billinginfo', 'local_moderncommerce'),
            'browsecatalog' => get_string('browsecatalog', 'local_moderncommerce'),
            'carttotal' => get_string('carttotal', 'local_moderncommerce'),
            'checkout' => get_string('checkout', 'local_moderncommerce'),
            'checkoutdesc' => get_string('checkoutdesc', 'local_moderncommerce'),
            'couponcode' => get_string('couponcode', 'local_moderncommerce'),
            'discount' => get_string('discount', 'local_moderncommerce'),
            'emptycart' => get_string('emptycart', 'local_moderncommerce'),
            'emptycartmessage' => get_string('emptycartmessage', 'local_moderncommerce'),
            'entercoupon' => get_string('entercoupon', 'local_moderncommerce'),
            'error' => get_string('error', 'local_moderncommerce'),
            'item' => get_string('item', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'nopaymentmethodsenabled' => get_string('nopaymentmethodsenabled', 'local_moderncommerce'),
            'ordersummary' => get_string('ordersummary', 'local_moderncommerce'),
            'paymentmethod' => get_string('paymentmethod', 'local_moderncommerce'),
            'placeorder' => get_string('placeorder', 'local_moderncommerce'),
            'processing' => get_string('processing', 'local_moderncommerce'),
            'removecoupon' => get_string('removecoupon', 'local_moderncommerce'),
            'retry' => get_string('retry', 'local_moderncommerce'),
            'securecheckout' => get_string('securecheckout', 'local_moderncommerce'),
            'securepayment' => get_string('securepayment', 'local_moderncommerce'),
            'subtotal' => get_string('subtotal', 'local_moderncommerce'),
            'tax' => get_string('tax', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moderncommerce/react_mount', [
    'region' => 'moderncommerce-checkout',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-credit-card',
]);
echo $OUTPUT->footer();

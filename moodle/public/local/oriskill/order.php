<?php
// This file is part of Moodle - https://moodle.org/
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
 * Public order form of a trainer, and its order step: the buyer picks an offer, logs in (or signs up), accepts the
 * terms and pays. On the trainer's own domain the form is served at /bdc/{slug} (see README, "Domaine personnalisé").
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\coupon_service;
use local_oriskill\creator_service;
use local_oriskill\hook_callbacks;
use local_oriskill\orderform_service;

$id = optional_param('id', 0, PARAM_INT);
$domain = optional_param('domain', '', PARAM_HOST);
$slug = optional_param('slug', '', PARAM_ALPHANUMEXT);
$offerid = optional_param('offer', 0, PARAM_INT);
$mode = optional_param('mode', 'once', PARAM_ALPHA);
$mode = in_array($mode, ['instalments', 'subscription'], true) ? $mode : 'once';
$plan = optional_param('plan', 0, PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$found = orderform_service::public_form($id, $domain, $slug);
if (!$found) {
    $PAGE->set_url(new moodle_url('/local/oriskill/order.php', array_filter(['id' => $id, 'domain' => $domain,
        'slug' => $slug])));
    $PAGE->set_pagelayout('standard');
    send_header_404();
    $PAGE->set_title(get_string('error:formunavailable', 'local_oriskill'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('error:formunavailable', 'local_oriskill'), 'info');
    echo $OUTPUT->footer();
    exit;
}
[$form, $creator] = $found;
$formurl = new moodle_url('/local/oriskill/order.php', ['id' => $form->id]);
$currency = pricing_service::get_currency_config()->currency;
$offers = orderform_service::offers($form);
$cansell = orderform_service::can_sell($creator);

// Order step: on Oriskill itself, for a logged-in buyer.
if ($offerid) {
    $offer = $offers[$offerid] ?? null;
    if (!$offer) {
        redirect($formurl);
    }
    // One of the offer's payments in instalments (its main one is plan 0).
    if ($mode === 'instalments') {
        $offer = orderform_service::with_plan($offer, $plan) ?? redirect($formurl);
    }
    $described = orderform_service::describe_offer($offer, $currency);
    if (($mode === 'instalments' && (!$described->instalments || !orderform_service::can_sell_instalments($creator)))
            || ($mode === 'subscription' && (!$described->subscription || !orderform_service::can_sell_instalments($creator)))) {
        $mode = 'once';
    }
    // Promo code typed by the buyer, kept in the address until payment.
    // A subscription takes neither promo code nor extra.
    $code = $mode === 'subscription' ? '' : optional_param('coupon', '', PARAM_ALPHANUMEXT);
    [$coupon, $couponerror] = $code !== '' ? coupon_service::find($form, $code, $mode) : [null, null];
    $bump = $mode === 'subscription' ? null : orderform_service::bump($form);
    $withextra = $bump && optional_param('extra', 0, PARAM_BOOL);
    $quote = orderform_service::quote($offer, $mode, $coupon, $withextra ? $bump->price : 0);
    $url = new moodle_url($formurl, array_filter(['offer' => $offerid, 'mode' => $mode,
        'plan' => $mode === 'instalments' ? $plan : 0, 'coupon' => $coupon->code ?? '', 'extra' => $withextra ? 1 : 0]));
    $button = orderform_service::button($form);
    $fields = array_filter(orderform_service::buyer_fields($form), fn($state) => $state !== 'hidden');
    $PAGE->set_url($url);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title(format_string($form->title));
    $PAGE->set_heading(format_string($form->title));
    if ($mode === 'subscription') {
        $price = get_string('subscription:summary', 'local_oriskill', $quote->displayamount);
    } else if ($quote->instalments) {
        $price = get_string($quote->instalments->first ? 'instalments:summaryfirst' : 'instalments:summary',
            'local_oriskill', $quote->instalments);
    } else {
        $price = $quote->displaytotal;
    }
    if ($quote->discount > 0) {
        $price = html_writer::tag('s', $quote->displaybase, ['class' => 'text-muted me-2']) . $price;
    }
    if ($withextra) {
        $price .= html_writer::div(get_string('orderform:extraincluded', 'local_oriskill', s($bump->label)), 'small text-muted');
    }
    $recap = html_writer::tag('p', html_writer::tag('strong', s($offer->label)) . ' – ' . $price, ['class' => 'lead']) .
        html_writer::tag('ul', implode('', array_map(fn($c) => html_writer::tag('li', s($c->name)), $described->courses)));
    if (!optional_param('confirm', 0, PARAM_BOOL)) {
        \local_oriskill\formstats_service::track($form, (int) $offerid, 'checkout');
    }
    if (!isloggedin() || isguestuser()) {
        local_oriskill_visitor_page($url, html_writer::tag('p', get_string('orderform:loginfirst', 'local_oriskill')) . $recap);
    }
    require_login(null, false);

    // The buyer's details asked by the form, filled in from the profile.
    $profile = ['address' => $USER->address, 'zipcode' => '', 'city' => $USER->city, 'country' => $USER->country,
        'phone' => $USER->phone1 ?: $USER->phone2];
    $billing = [];
    foreach ($fields as $field => $state) {
        $billing[$field] = trim(optional_param('billing_' . $field, $profile[$field] ?? '', PARAM_TEXT));
    }
    $error = '';
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        $missing = array_keys(array_filter($fields, fn($state, $field) => $state === 'required' && $billing[$field] === '',
            ARRAY_FILTER_USE_BOTH));
        if ($form->termsrequired && !optional_param('terms', 0, PARAM_BOOL)) {
            $error = get_string('orderform:termsmissing', 'local_oriskill');
        } else if ($missing) {
            $error = get_string('orderform:fieldmissing', 'local_oriskill', implode(', ',
                array_map(fn($f) => get_string('orderform:field_' . $f, 'local_oriskill'), $missing)));
        } else {
            try {
                [, $payurl] = orderform_service::create_order((int) $USER->id, $form, $offer, $mode, $coupon, $withextra,
                    $billing);
                redirect($payurl);
            } catch (moodle_exception $e) {
                $error = $e->getMessage();
            }
        }
    }

    echo $OUTPUT->header();
    if ($error) {
        echo $OUTPUT->notification($error, 'error');
    }
    echo html_writer::start_div('card mx-auto', ['style' => 'max-width: 38rem']);
    echo html_writer::start_div('card-body');
    echo html_writer::tag('p', get_string('orderform:recap', 'local_oriskill', s($creator->displayname)), ['class' => 'text-muted']);
    echo $recap;
    if ($mode === 'subscription') {
        echo html_writer::tag('p', get_string('subscription:howitworks', 'local_oriskill', $quote->displayamount),
            ['class' => 'small text-muted']);
    } else if ($quote->instalments) {
        echo html_writer::tag('p', get_string($quote->instalments->first ? 'instalments:howitworksfirst'
            : 'instalments:howitworks', 'local_oriskill', $quote->instalments), ['class' => 'small text-muted']);
    }

    // Extra (order bump): ticking or unticking reloads the step with the new price.
    if ($bump) {
        $toggle = new moodle_url($url, ['extra' => $withextra ? 0 : 1]);
        echo html_writer::div(
            html_writer::div(
                html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'orderform-extra', 'class' => 'form-check-input',
                    'onchange' => 'window.location.href = ' . json_encode($toggle->out(false))] +
                    ($withextra ? ['checked' => 'checked'] : [])) .
                html_writer::tag('label', html_writer::tag('strong', get_string('orderform:extraadd', 'local_oriskill',
                    (object) ['label' => s($bump->label), 'price' => pricing_service::format_price($bump->price, $currency)])),
                    ['for' => 'orderform-extra', 'class' => 'form-check-label']), 'form-check') .
            ($bump->text !== '' ? html_writer::div(s($bump->text), 'small mt-1') : ''),
            'oriskill-bump mb-3');
    }

    // Promo code (not for a subscription).
    if ($coupon) {
        echo html_writer::div(get_string('coupon:applied', 'local_oriskill', (object) ['code' => s($coupon->code),
            'discount' => pricing_service::format_price($quote->discount, $currency)]) . ' ' .
            html_writer::link(new moodle_url($url, ['coupon' => '']), get_string('coupon:remove', 'local_oriskill'),
                ['class' => 'small']), 'alert alert-success py-2');
    } else if ($mode !== 'subscription') {
        if ($couponerror) {
            echo html_writer::div(get_string($couponerror, 'local_oriskill'), 'alert alert-warning py-2');
        }
        echo html_writer::tag('form',
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $form->id]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'offer', 'value' => $offerid]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'mode', 'value' => $mode]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'plan', 'value' => $plan]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'extra', 'value' => $withextra ? 1 : 0]) .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => 'coupon', 'class' => 'form-control form-control-sm',
                'placeholder' => get_string('coupon:field', 'local_oriskill'), 'aria-label' => get_string('coupon:field',
                'local_oriskill'), 'style' => 'max-width: 14rem', 'value' => s($code)]) .
            html_writer::tag('button', get_string('coupon:apply', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary']),
            ['method' => 'get', 'action' => (new moodle_url('/local/oriskill/order.php'))->out(false),
             'class' => 'd-flex gap-2 mb-3']);
    }

    $termslink = html_writer::link(new moodle_url('/local/moderncommerce/terms.php'),
        get_string('showcase:terms', 'local_oriskill'), ['target' => '_blank']);
    $inputs = '';
    if ($fields) {
        $countries = get_string_manager()->get_list_of_countries();
        foreach ($fields as $field => $state) {
            $attributes = ['id' => 'billing-' . $field, 'class' => 'form-control'] +
                ($state === 'required' ? ['required' => 'required'] : []);
            $label = get_string('orderform:field_' . $field, 'local_oriskill') . ($state === 'required' ? ' *' : '');
            $control = $field === 'country'
                ? html_writer::select($countries, 'billing_country', $billing['country'], ['' => get_string('choosedots')],
                    ['class' => 'form-select'] + $attributes)
                : html_writer::empty_tag('input', ['type' => $field === 'phone' ? 'tel' : 'text', 'name' => 'billing_' . $field,
                    'value' => $billing[$field]] + $attributes);
            $inputs .= html_writer::div(html_writer::tag('label', $label, ['for' => 'billing-' . $field,
                'class' => 'form-label small mb-1']) . $control, $field === 'address' ? 'col-12' : 'col-sm-6');
        }
        $inputs = html_writer::tag('h3', get_string('orderform:yourdetails', 'local_oriskill'), ['class' => 'h6']) .
            html_writer::div(s(fullname($USER)) . ' · ' . s($USER->email), 'small text-muted mb-2') .
            html_writer::div($inputs, 'row g-2 mb-3');
    }
    echo html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]) . $inputs .
        ($form->termsrequired ? html_writer::div(
            html_writer::checkbox('terms', 1, false, get_string('orderform:acceptterms', 'local_oriskill', $termslink),
                ['id' => 'orderform-terms', 'class' => 'form-check-input', 'required' => 'required']), 'form-check mb-3') : '') .
        html_writer::div(
            html_writer::tag('button', orderform_service::button_content($button,
                get_string('orderform:pay', 'local_oriskill', $quote->displayamount)),
                ['type' => 'submit', 'class' => 'btn btn-primary btn-lg', 'style' => orderform_service::button_css($button)]) . ' ' .
            html_writer::link($formurl, get_string('cancel'), ['class' => 'btn btn-link']), 'text-' .
            ['left' => 'start', 'center' => 'center', 'right' => 'end'][$button->align]),
        ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// The order form page, in the trainer's brand.
\local_oriskill\formstats_service::track($form, 0, 'view');
$PAGE->set_url($formurl);
$PAGE->set_pagelayout('embedded');
$PAGE->add_body_class('oriskill-vitrine-page');
$PAGE->set_title(format_string($form->title) . ' – ' . format_string($creator->displayname), false);
$logo = creator_service::logo_url($creator);
hook_callbacks::$headmeta = ['description' => $form->headline ?: format_string($form->title),
    'canonical' => orderform_service::url($form, $creator)->out(false), 'icon' => $logo ? $logo->out(false) : ''];

$files = get_file_storage()->get_area_files($context->id, 'local_oriskill', 'orderform', $form->id, 'filename', false);
$image = ($file = reset($files)) ? moodle_url::make_pluginfile_url($context->id, 'local_oriskill', 'orderform', $form->id, '/',
    $file->get_filename())->out(false) : '';
$button = orderform_service::button($form);
$optionurl = fn($offer, $option) => (new moodle_url($formurl, ['offer' => $offer->id, 'mode' => $option->mode] +
    ($option->mode === 'instalments' ? ['plan' => $option->plan] : [])))->out(false);
$items = [];
foreach ($offers as $offer) {
    $described = orderform_service::describe_offer($offer, $currency);
    // The payment chosen by default on the main button, the others below it.
    $options = orderform_service::payment_options($offer, $currency, orderform_service::can_sell_instalments($creator));
    $main = array_shift($options);
    $items[] = [
        'label' => format_string($offer->label),
        'details' => array_map('s', $described->details),
        'courses' => $described->courses,
        'displayprice' => $main->price,
        'highlight' => (bool) $offer->highlight,
        'buttonlabel' => orderform_service::button_content($button,
            s(format_string($form->buttonlabel ?: get_string('orderform:buttondefault', 'local_oriskill')))),
        'buttonstyle' => orderform_service::button_css($button),
        'buttonalign' => $button->align,
        'url' => $optionurl($offer, $main),
        'others' => array_map(fn($option) => ['label' => $option->other, 'url' => $optionurl($offer, $option)], $options),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_oriskill/orderform', [
    'displayname' => format_string($creator->displayname),
    'logo' => $logo ? $logo->out(false) : '',
    'initial' => core_text::strtoupper(core_text::substr($creator->displayname, 0, 1)),
    'showcaseurl' => (!empty($creator->customdomain) ? new moodle_url('https://' . $creator->customdomain . '/')
        : creator_service::showcase_url($creator))->out(false),
    'style' => creator_service::showcase_style($creator),
    'title' => format_string($form->title),
    'headline' => format_string($form->headline),
    'description' => format_text($form->description, $form->descriptionformat, ['context' => $context]),
    'image' => $image,
    'offers' => $items,
    'cansell' => $cansell,
    'year' => userdate(time(), '%Y'),
    'links' => [
        ['url' => (new moodle_url('/local/moderncommerce/terms.php'))->out(false),
         'label' => get_string('showcase:terms', 'local_oriskill')],
        ['url' => (new moodle_url('/local/moderncommerce/privacy.php'))->out(false),
         'label' => get_string('showcase:privacy', 'local_oriskill')],
    ],
]);
echo $OUTPUT->footer();

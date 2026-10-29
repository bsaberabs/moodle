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
use local_oriskill\creator_service;
use local_oriskill\hook_callbacks;
use local_oriskill\orderform_service;

$id = optional_param('id', 0, PARAM_INT);
$domain = optional_param('domain', '', PARAM_HOST);
$slug = optional_param('slug', '', PARAM_ALPHANUMEXT);
$offerid = optional_param('offer', 0, PARAM_INT);
$mode = optional_param('mode', 'once', PARAM_ALPHA) === 'instalments' ? 'instalments' : 'once';

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
    $described = orderform_service::describe_offer($offer, $currency);
    $url = new moodle_url($formurl, ['offer' => $offerid, 'mode' => $mode]);
    $PAGE->set_url($url);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title(format_string($form->title));
    $PAGE->set_heading(format_string($form->title));
    $amount = $mode === 'instalments' && $described->instalments
        ? get_string('instalments:summary', 'local_oriskill', $described->instalments) : $described->displayprice;
    $recap = html_writer::tag('p', html_writer::tag('strong', s($offer->label)) . ' – ' . $amount, ['class' => 'lead']) .
        html_writer::tag('ul', implode('', array_map(fn($c) => html_writer::tag('li', s($c->name)), $described->courses)));
    if (!isloggedin() || isguestuser()) {
        local_oriskill_visitor_page($url, html_writer::tag('p', get_string('orderform:loginfirst', 'local_oriskill')) . $recap);
    }
    require_login(null, false);

    $error = '';
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        if ($form->termsrequired && !optional_param('terms', 0, PARAM_BOOL)) {
            $error = get_string('orderform:termsmissing', 'local_oriskill');
        } else {
            try {
                [, $payurl] = orderform_service::create_order((int) $USER->id, $form, $offer, $mode);
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
    if ($mode === 'instalments' && $described->instalments) {
        echo html_writer::tag('p', get_string('instalments:howitworks', 'local_oriskill', $described->instalments),
            ['class' => 'small text-muted']);
    }
    $termslink = html_writer::link(new moodle_url('/local/moderncommerce/terms.php'),
        get_string('showcase:terms', 'local_oriskill'), ['target' => '_blank']);
    echo html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]) .
        ($form->termsrequired ? html_writer::div(
            html_writer::checkbox('terms', 1, false, get_string('orderform:acceptterms', 'local_oriskill', $termslink),
                ['id' => 'orderform-terms', 'class' => 'form-check-input', 'required' => 'required']), 'form-check mb-3') : '') .
        html_writer::tag('button', get_string('orderform:pay', 'local_oriskill',
            $mode === 'instalments' && $described->instalments ? $described->instalments->displayamount : $described->displayprice),
            ['type' => 'submit', 'class' => 'btn btn-primary btn-lg']) . ' ' .
        html_writer::link($formurl, get_string('cancel'), ['class' => 'btn btn-link']),
        ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// The order form page, in the trainer's brand.
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
$items = [];
foreach ($offers as $offer) {
    $described = orderform_service::describe_offer($offer, $currency);
    $items[] = [
        'label' => format_string($offer->label),
        'details' => array_map('s', $described->details),
        'courses' => $described->courses,
        'displayprice' => $described->displayprice,
        'highlight' => (bool) $offer->highlight,
        'buttonlabel' => format_string($form->buttonlabel ?: get_string('orderform:buttondefault', 'local_oriskill')),
        'url' => (new moodle_url($formurl, ['offer' => $offer->id, 'mode' => 'once']))->out(false),
        'instalments' => $described->instalments ? [
            'label' => get_string('instalments:orshort', 'local_oriskill', $described->instalments),
            'url' => (new moodle_url($formurl, ['offer' => $offer->id, 'mode' => 'instalments']))->out(false),
        ] : null,
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

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
 * Create or edit an order form and its offers.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\form\orderform_form;
use local_oriskill\log_service;
use local_oriskill\orderform_service;
use local_oriskill\seller_service;

$id = optional_param('id', 0, PARAM_INT);
$url = new moodle_url('/local/oriskill/orderform.php', $id ? ['id' => $id] : []);
$creator = creator_service::require_current_creator($url, 'menu:orderforms');
$listurl = new moodle_url('/local/oriskill/orderforms.php');
$context = context_system::instance();

$form = $id ? $DB->get_record('local_oriskill_orderforms', ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST) : null;
$offers = $form ? array_values(orderform_service::offers($form)) : [];
$courses = array_map(fn($row) => format_string($row->course->fullname), seller_service::priced_courses($creator));

$mform = new orderform_form($url, ['creator' => $creator, 'formid' => $id, 'courses' => $courses,
    'offercount' => count($offers)]);
$draftitemid = file_get_submitted_draft_itemid('image_filemanager');
file_prepare_draft_area($draftitemid, $context->id, 'local_oriskill', 'orderform', $id, orderform_service::IMAGE_OPTIONS);
$defaults = ['id' => $id, 'image_filemanager' => $draftitemid];
if ($form) {
    $defaults += [
        'title' => $form->title,
        'slug' => $form->slug,
        'headline' => $form->headline,
        'description_editor' => ['text' => $form->description, 'format' => $form->descriptionformat],
        'buttonlabel' => $form->buttonlabel,
        'thankyou' => $form->thankyou,
        'termsrequired' => $form->termsrequired,
        'status' => $form->status,
        'bumplabel' => $form->bumplabel,
        'bumptext' => $form->bumptext,
        'bumpcourseids' => array_filter(array_map('intval', explode(',', (string) $form->bumpcourseids))),
        'bumpprice' => (float) $form->bumpprice > 0 ? format_float((float) $form->bumpprice, 2, true) : '',
        'upselllabel' => $form->upselllabel,
        'upselltext' => $form->upselltext,
        'upsellcourseids' => array_filter(array_map('intval', explode(',', (string) $form->upsellcourseids))),
        'upsellprice' => (float) $form->upsellprice > 0 ? format_float((float) $form->upsellprice, 2, true) : '',
    ];
    foreach ($offers as $i => $offer) {
        $defaults["offerlabel[$i]"] = $offer->label;
        $defaults["offercourses[$i]"] = array_map('intval', explode(',', $offer->courseids));
        $defaults["offerdetails[$i]"] = $offer->details;
        $defaults["offerprice[$i]"] = format_float((float) $offer->price, 2, true);
        $defaults["offerinstalcount[$i]"] = $offer->instalcount;
        $defaults["offerinstaltotal[$i]"] = $offer->instalcount ? format_float((float) $offer->instaltotal, 2, true) : '';
        $defaults["offerinstalfirst[$i]"] = (float) $offer->instalfirst > 0 ? format_float((float) $offer->instalfirst, 2, true) : '';
        $defaults["offerinstalcut[$i]"] = (int) ($offer->instalcut ?? 1) ?: 1;
        $defaults["offerhighlight[$i]"] = $offer->highlight;
        $defaults["offeractags[$i]"] = $offer->actags;
        $defaults["offersubprice[$i]"] = (float) $offer->subprice > 0 ? format_float((float) $offer->subprice, 2, true) : '';
    }
}
$mform->set_data($defaults);

if ($mform->is_cancelled()) {
    redirect($listurl);
}
if ($data = $mform->get_data()) {
    $data->description = $data->description_editor['text'];
    $data->descriptionformat = $data->description_editor['format'];
    foreach (['bump', 'upsell'] as $kind) {
        $data->{$kind . 'price'} = unformat_float((string) $data->{$kind . 'price'}, true) ?: 0;
        $data->{$kind . 'courseids'} = $data->{$kind . 'courseids'} ?? [];
    }
    $data->offers = [];
    foreach (array_keys($data->offerlabel ?? []) as $i) {
        $data->offers[] = (object) [
            'label' => $data->offerlabel[$i],
            'courseids' => $data->offercourses[$i] ?? [],
            'details' => $data->offerdetails[$i],
            'price' => unformat_float($data->offerprice[$i], true),
            'instalcount' => (int) $data->offerinstalcount[$i],
            'instaltotal' => unformat_float((string) $data->offerinstaltotal[$i], true),
            'instalfirst' => unformat_float((string) ($data->offerinstalfirst[$i] ?? ''), true) ?: 0,
            'instalcut' => (int) ($data->offerinstalcut[$i] ?? 1),
            'highlight' => !empty($data->offerhighlight[$i]),
            'actags' => $data->offeractags[$i] ?? '',
            'subprice' => unformat_float((string) ($data->offersubprice[$i] ?? ''), true) ?: 0,
        ];
    }
    $saved = orderform_service::save($creator, $data);
    file_save_draft_area_files($data->image_filemanager, $context->id, 'local_oriskill', 'orderform', $saved->id,
        orderform_service::IMAGE_OPTIONS);
    log_service::add($creator, 'orderform_saved', ['title' => $saved->title, 'status' =>
        get_string('orderform:' . $saved->status, 'local_oriskill')]);
    redirect($listurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/orderforms.php',
    $form ? format_string($form->title) : get_string('orderform:new', 'local_oriskill'));
echo html_writer::div(html_writer::link($listurl, '<i class="fa fa-arrow-left me-1" aria-hidden="true"></i>' .
    get_string('menu:orderforms', 'local_oriskill')), 'mb-3');
if (!orderform_service::can_sell($creator)) {
    echo $OUTPUT->notification(get_string('orderform:cannotsell', 'local_oriskill',
        (new moodle_url('/local/oriskill/payment.php'))->out(false)), 'warning');
} else if (!orderform_service::can_sell_instalments($creator)) {
    echo $OUTPUT->notification(get_string('orderform:noinstalments', 'local_oriskill',
        (new moodle_url('/local/oriskill/payment.php'))->out(false)), 'info');
}
echo html_writer::start_div('oriskill-card');
$mform->display();
echo html_writer::end_div();
echo creator_service::space_end();
echo $OUTPUT->footer();

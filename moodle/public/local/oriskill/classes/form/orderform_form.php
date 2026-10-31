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

namespace local_oriskill\form;

use local_oriskill\instalment_service;
use local_oriskill\orderform_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * An order form: its page (texts, image, button, thank-you message) and its offers.
 *
 * Custom data: 'creator', 'formid' (0 for a new form), 'courses' (courseid => name), 'offercount'.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orderform_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['formid']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'pagehdr', get_string('orderform:page', 'local_oriskill'));
        $mform->addElement('text', 'title', get_string('orderform:title', 'local_oriskill'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', null, 'maxlength', 255, 'client');

        $mform->addElement('text', 'slug', get_string('orderform:slug', 'local_oriskill'), ['size' => 30]);
        $mform->setType('slug', PARAM_RAW_TRIMMED);
        $mform->addRule('slug', null, 'required', null, 'client');
        $mform->addHelpButton('slug', 'orderform:slug', 'local_oriskill');

        $mform->addElement('text', 'headline', get_string('orderform:headline', 'local_oriskill'), ['size' => 80]);
        $mform->setType('headline', PARAM_TEXT);

        $mform->addElement('editor', 'description_editor', get_string('orderform:description', 'local_oriskill'), null,
            ['maxfiles' => 0]);
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('filemanager', 'image_filemanager', get_string('orderform:image', 'local_oriskill'), null,
            orderform_service::IMAGE_OPTIONS);

        $mform->addElement('text', 'buttonlabel', get_string('orderform:buttonlabel', 'local_oriskill'), ['size' => 30]);
        $mform->setType('buttonlabel', PARAM_TEXT);
        $mform->setDefault('buttonlabel', get_string('orderform:buttondefault', 'local_oriskill'));

        $mform->addElement('textarea', 'thankyou', get_string('orderform:thankyou', 'local_oriskill'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('thankyou', PARAM_TEXT);
        $mform->addHelpButton('thankyou', 'orderform:thankyou', 'local_oriskill');

        $mform->addElement('text', 'redirecturl', get_string('orderform:redirecturl', 'local_oriskill'), ['size' => 60,
            'placeholder' => 'https://']);
        $mform->setType('redirecturl', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('redirecturl', 'orderform:redirecturl', 'local_oriskill');

        $mform->addElement('advcheckbox', 'termsrequired', get_string('orderform:termsrequired', 'local_oriskill'));
        $mform->setDefault('termsrequired', 1);

        $mform->addElement('select', 'status', get_string('status'), [
            'draft' => get_string('orderform:draft', 'local_oriskill'),
            'published' => get_string('orderform:published', 'local_oriskill'),
        ]);

        // Domain of the page, when the trainer has several.
        $domains = \local_oriskill\domain_service::options($this->_customdata['creator']);
        if (count($domains) > 1) {
            $mform->addElement('select', 'domainid', get_string('domains:pagedomain', 'local_oriskill'), $domains);
            $mform->addHelpButton('domainid', 'domains:pagedomain', 'local_oriskill');
        } else {
            $mform->addElement('hidden', 'domainid', 0);
        }
        $mform->setType('domainid', PARAM_INT);

        // Offers.
        $mform->addElement('header', 'offershdr', get_string('orderform:offers', 'local_oriskill'));
        $mform->setExpanded('offershdr');
        $mform->addElement('static', 'offersintro', '', get_string('orderform:offers_intro', 'local_oriskill'));
        $defaults = ['once' => get_string('orderform:offerdefault_once', 'local_oriskill'),
            'plan0' => get_string('orderform:offerdefault_plan0', 'local_oriskill')];
        for ($i = 1; $i <= 6; $i++) {
            $defaults['plan' . $i] = get_string('orderform:offerdefault_plan', 'local_oriskill', $i + 1);
        }
        $defaults['subscription'] = get_string('orderform:offerdefault_subscription', 'local_oriskill');
        $counts = [0 => get_string('instalments:none', 'local_oriskill')];
        foreach (instalment_service::COUNTS as $count) {
            $counts[$count] = get_string('instalments:times', 'local_oriskill', $count);
        }
        $repeat = [
            $mform->createElement('static', 'offerhead', '', \html_writer::tag('h5', get_string('orderform:offer', 'local_oriskill'),
                ['class' => 'mt-3 mb-0'])),
            $mform->createElement('text', 'offerlabel', get_string('orderform:offerlabel', 'local_oriskill'), ['size' => 50]),
            $mform->createElement('autocomplete', 'offercourses', get_string('orderform:offercourses', 'local_oriskill'),
                $this->_customdata['courses'], ['multiple' => true]),
            $mform->createElement('textarea', 'offerdetails', get_string('orderform:offerdetails', 'local_oriskill'),
                ['rows' => 3, 'cols' => 50]),
            $mform->createElement('text', 'offerprice', get_string('orderform:offerprice', 'local_oriskill'), ['size' => 10]),
            $mform->createElement('autocomplete', 'offergroups', get_string('orderform:offergroups', 'local_oriskill'),
                $this->_customdata['groups'] ?? [], ['multiple' => true,
                    'noselectionstring' => get_string('orderform:offergroups_none', 'local_oriskill')]),
            $mform->createElement('select', 'offerinstalcount', get_string('instalments:countcol', 'local_oriskill'), $counts),
            $mform->createElement('text', 'offerinstaltotal', get_string('orderform:offerinstaltotal', 'local_oriskill'),
                ['size' => 10]),
            $mform->createElement('text', 'offerinstalfirst', get_string('orderform:offerinstalfirst', 'local_oriskill'),
                ['size' => 10]),
            $mform->createElement('select', 'offerinstalcut', get_string('instalments:cutcol', 'local_oriskill'),
                array_combine(\local_oriskill\instalment_service::CUTS, array_map(fn($n) => get_string('instalments:cut',
                    'local_oriskill', $n), \local_oriskill\instalment_service::CUTS))),
            $mform->createElement('textarea', 'offerinstalplans', get_string('orderform:offerinstalplans', 'local_oriskill'),
                ['rows' => 3, 'cols' => 30, 'placeholder' => "2 ; 2498\n3 ; 2499"]),
            $mform->createElement('text', 'offersubprice', get_string('orderform:offersubprice', 'local_oriskill'), ['size' => 10]),
            $mform->createElement('select', 'offerdefault', get_string('orderform:offerdefault', 'local_oriskill'), $defaults),
            $mform->createElement('advcheckbox', 'offerhighlight', get_string('orderform:offerhighlight', 'local_oriskill')),
            $mform->createElement('text', 'offerhllabel', get_string('orderform:hllabel', 'local_oriskill'), ['size' => 20,
                'maxlength' => 40, 'placeholder' => get_string('orderform:recommended', 'local_oriskill')]),
            $mform->createElement('text', 'offerhlbg', get_string('orderform:hlbackground', 'local_oriskill'), ['size' => 9,
                'placeholder' => '#fff7ed']),
            $mform->createElement('text', 'offerhlborder', get_string('orderform:hlborder', 'local_oriskill'), ['size' => 9,
                'placeholder' => '#7c3aed']),
            $mform->createElement('select', 'offerhlstyle', get_string('orderform:hlborderstyle', 'local_oriskill'),
                array_combine(orderform_service::BORDER_STYLES, array_map(fn($s) => get_string('orderform:border_' . $s,
                    'local_oriskill'), orderform_service::BORDER_STYLES))),
            $mform->createElement('text', 'offerstock', get_string('orderform:offerstock', 'local_oriskill'), ['size' => 6]),
            $mform->createElement('text', 'offeractags', get_string('orderform:offeractags', 'local_oriskill'), ['size' => 50]),
        ];
        $options = [
            'offerlabel' => ['type' => PARAM_TEXT],
            'offerdetails' => ['type' => PARAM_TEXT],
            'offerprice' => ['type' => PARAM_RAW_TRIMMED],
            'offerinstaltotal' => ['type' => PARAM_RAW_TRIMMED, 'hideif' => ['offerinstalcount', 'eq', 0]],
            'offerinstalfirst' => ['type' => PARAM_RAW_TRIMMED, 'hideif' => ['offerinstalcount', 'eq', 0],
                'helpbutton' => ['orderform:offerinstalfirst', 'local_oriskill']],
            'offerinstalcut' => ['type' => PARAM_INT, 'default' => 1, 'hideif' => ['offerinstalcount', 'eq', 0],
                'helpbutton' => ['instalments:cutcol', 'local_oriskill']],
            'offercourses' => ['type' => PARAM_INT],
            'offergroups' => ['type' => PARAM_INT, 'helpbutton' => ['orderform:offergroups', 'local_oriskill']],
            'offeractags' => ['type' => PARAM_TEXT, 'helpbutton' => ['orderform:offeractags', 'local_oriskill']],
            'offerhllabel' => ['type' => PARAM_TEXT, 'hideif' => ['offerhighlight', 'notchecked']],
            'offerhlbg' => ['type' => PARAM_RAW_TRIMMED, 'hideif' => ['offerhighlight', 'notchecked']],
            'offerhlborder' => ['type' => PARAM_RAW_TRIMMED, 'hideif' => ['offerhighlight', 'notchecked']],
            'offerhlstyle' => ['type' => PARAM_ALPHA, 'hideif' => ['offerhighlight', 'notchecked']],
            'offerstock' => ['type' => PARAM_RAW_TRIMMED, 'helpbutton' => ['orderform:offerstock', 'local_oriskill']],
            'offersubprice' => ['type' => PARAM_RAW_TRIMMED, 'helpbutton' => ['orderform:offersubprice', 'local_oriskill']],
            'offerinstalplans' => ['type' => PARAM_TEXT, 'helpbutton' => ['orderform:offerinstalplans', 'local_oriskill']],
            'offerdefault' => ['type' => PARAM_ALPHANUM, 'default' => 'once',
                'helpbutton' => ['orderform:offerdefault', 'local_oriskill']],
        ];
        $this->repeat_elements($repeat, max(1, (int) $this->_customdata['offercount']), $options, 'offerrepeats',
            'offeradd', 1, get_string('orderform:addoffer', 'local_oriskill'), true);

        // Fields of the buyer at the payment step.
        $mform->addElement('header', 'fieldshdr', get_string('orderform:buyerfields', 'local_oriskill'));
        $mform->addElement('static', 'fieldsintro', '', get_string('orderform:buyerfields_intro', 'local_oriskill'));
        $states = [];
        foreach (orderform_service::FIELD_STATES as $state) {
            $states[$state] = get_string('orderform:fieldstate_' . $state, 'local_oriskill');
        }
        foreach (orderform_service::BUYER_FIELDS as $field) {
            $mform->addElement('select', 'field_' . $field, get_string('orderform:field_' . $field, 'local_oriskill'), $states);
        }

        // Order button.
        $mform->addElement('header', 'buttonhdr', get_string('orderform:button', 'local_oriskill'));
        $mform->addElement('static', 'buttonintro', '', get_string('orderform:button_intro', 'local_oriskill'));
        foreach (['btncolor', 'btntextcolor'] as $name) {
            $mform->addElement('text', $name, get_string('orderform:' . $name, 'local_oriskill'), ['size' => 9,
                'placeholder' => '#7c3aed']);
            $mform->setType($name, PARAM_RAW_TRIMMED);
        }
        $mform->addHelpButton('btncolor', 'orderform:btncolor', 'local_oriskill');
        $choices = fn(string $prefix, array $keys) => array_combine($keys, array_map(fn($k) => get_string($prefix . $k,
            'local_oriskill'), $keys));
        $mform->addElement('select', 'btnsize', get_string('orderform:btnsize', 'local_oriskill'),
            $choices('orderform:btnsize_', array_keys(orderform_service::BUTTON_SIZES)));
        $mform->setDefault('btnsize', 'md');
        $mform->addElement('select', 'btnalign', get_string('orderform:btnalign', 'local_oriskill'),
            $choices('orderform:btnalign_', orderform_service::BUTTON_ALIGNS));
        $mform->setDefault('btnalign', 'center');
        $mform->addElement('select', 'btnicon', get_string('orderform:btnicon', 'local_oriskill'),
            $choices('orderform:btnicon_', array_keys(orderform_service::BUTTON_ICONS)));
        $mform->addElement('text', 'btnradius', get_string('orderform:btnradius', 'local_oriskill'), ['size' => 4]);
        $mform->setType('btnradius', PARAM_RAW_TRIMMED);

        // Extra at the order step (order bump) and offer after payment (upsell).
        foreach (['bump', 'upsell'] as $kind) {
            $mform->addElement('header', $kind . 'hdr', get_string('orderform:' . $kind, 'local_oriskill'));
            $mform->addElement('static', $kind . 'intro', '', get_string('orderform:' . $kind . '_intro', 'local_oriskill'));
            $mform->addElement('text', $kind . 'label', get_string('orderform:extralabel', 'local_oriskill'), ['size' => 60]);
            $mform->setType($kind . 'label', PARAM_TEXT);
            $mform->addElement('textarea', $kind . 'text', get_string('orderform:extratext', 'local_oriskill'),
                ['rows' => 2, 'cols' => 60]);
            $mform->setType($kind . 'text', PARAM_TEXT);
            $mform->addElement('autocomplete', $kind . 'courseids', get_string('orderform:offercourses', 'local_oriskill'),
                $this->_customdata['courses'], ['multiple' => true]);
            $mform->setType($kind . 'courseids', PARAM_INT);
            $mform->addElement('text', $kind . 'price', get_string('orderform:extraprice', 'local_oriskill'), ['size' => 10]);
            $mform->setType($kind . 'price', PARAM_RAW_TRIMMED);
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $error = orderform_service::slug_error((string) $data['slug'], $this->_customdata['creator'], (int) $data['id']);
        if ($error) {
            $errors['slug'] = get_string($error, 'local_oriskill');
        }
        $valid = 0;
        foreach (array_keys($data['offerlabel'] ?? []) as $i) {
            if (trim((string) $data['offerlabel'][$i]) === '') {
                continue;
            }
            $price = unformat_float((string) ($data['offerprice'][$i] ?? ''), true);
            if (!$price || $price <= 0) {
                $errors["offerprice[$i]"] = get_string('error:offerprice', 'local_oriskill');
            }
            if (empty($data['offercourses'][$i])) {
                $errors["offercourses[$i]"] = get_string('error:offercourses', 'local_oriskill');
            }
            $total = trim((string) ($data['offerinstaltotal'][$i] ?? ''));
            if (!empty($data['offerinstalcount'][$i]) && $total !== ''
                    && (!($t = unformat_float($total, true)) || $t <= 0)) {
                $errors["offerinstaltotal[$i]"] = get_string('error:offerprice', 'local_oriskill');
            }
            // A deposit is below the total paid in instalments (the total, or the price when it is empty).
            $first = trim((string) ($data['offerinstalfirst'][$i] ?? ''));
            if (!empty($data['offerinstalcount'][$i]) && $first !== '') {
                $f = unformat_float($first, true);
                $limit = $total !== '' ? unformat_float($total, true) : $price;
                if (!$f || $f <= 0 || ($limit && $f >= $limit)) {
                    $errors["offerinstalfirst[$i]"] = get_string('error:instalfirst', 'local_oriskill');
                }
            }
            [, $bad] = orderform_service::parse_plans((string) ($data['offerinstalplans'][$i] ?? ''));
            if ($bad) {
                $errors["offerinstalplans[$i]"] = get_string('orderform:planlines', 'local_oriskill', implode(', ', $bad));
            }
            $valid++;
        }
        foreach (['btncolor', 'btntextcolor'] as $name) {
            if (trim((string) ($data[$name] ?? '')) !== '' && !preg_match('/^#[0-9a-f]{6}$/i', trim($data[$name]))) {
                $errors[$name] = get_string('orderform:btncolor_help', 'local_oriskill');
            }
        }
        if (trim((string) ($data['redirecturl'] ?? '')) !== '' && !preg_match('~^https?://\S+$~i', trim($data['redirecturl']))) {
            $errors['redirecturl'] = get_string('invalidurl', 'error');
        }
        foreach (['bump', 'upsell'] as $kind) {
            if (trim((string) ($data[$kind . 'label'] ?? '')) === '') {
                continue;
            }
            $price = unformat_float((string) ($data[$kind . 'price'] ?? ''), true);
            if (!$price || $price <= 0) {
                $errors[$kind . 'price'] = get_string('error:offerprice', 'local_oriskill');
            }
            if (empty($data[$kind . 'courseids'])) {
                $errors[$kind . 'courseids'] = get_string('error:offercourses', 'local_oriskill');
            }
        }
        if (!$valid && ($data['status'] ?? '') === 'published') {
            $errors['status'] = get_string('error:nooffer', 'local_oriskill');
        }
        return $errors;
    }
}

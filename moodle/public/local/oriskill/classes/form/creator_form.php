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

use local_oriskill\creator_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Trainer application, also used by an approved trainer to edit their showcase.
 *
 * Custom data: 'creator' (existing record or null).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class creator_form extends \moodleform {

    /** Logo file manager options. */
    public const LOGO_OPTIONS = ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 2097152,
        'accepted_types' => ['.png', '.jpg', '.jpeg', '.webp', '.svg']];

    /** Showcase banner file manager options. */
    public const BANNER_OPTIONS = ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 5242880,
        'accepted_types' => ['.png', '.jpg', '.jpeg', '.webp']];

    protected function definition() {
        $mform = $this->_form;
        $creator = $this->_customdata['creator'] ?? null;

        $mform->addElement('text', 'displayname', get_string('field:displayname', 'local_oriskill'), ['size' => 50]);
        $mform->setType('displayname', PARAM_TEXT);
        $mform->addRule('displayname', null, 'required', null, 'client');
        $mform->addRule('displayname', null, 'maxlength', 100, 'client');

        // The address is final once the space exists: links and the future subdomain depend on it.
        if ($creator) {
            $mform->addElement('static', 'slugstatic', get_string('field:slug', 'local_oriskill'),
                creator_service::showcase_url($creator)->out(false));
        } else {
            $mform->addElement('text', 'slug', get_string('field:slug', 'local_oriskill'), ['size' => 30]);
            $mform->setType('slug', PARAM_RAW_TRIMMED);
            $mform->addRule('slug', null, 'required', null, 'client');
            $mform->addHelpButton('slug', 'field:slug', 'local_oriskill');
        }

        $mform->addElement('text', 'tagline', get_string('field:tagline', 'local_oriskill'), ['size' => 80]);
        $mform->setType('tagline', PARAM_TEXT);
        $mform->addRule('tagline', null, 'maxlength', 255, 'client');

        $mform->addElement('editor', 'bio_editor', get_string('field:bio', 'local_oriskill'), null,
            ['maxfiles' => 0, 'noclean' => false]);
        $mform->setType('bio_editor', PARAM_RAW);

        $mform->addElement('filemanager', 'logo_filemanager', get_string('field:logo', 'local_oriskill'), null,
            self::LOGO_OPTIONS);

        if ($creator) {
            $this->appearance_fields($creator);
        }

        if (!$creator) {
            $mform->addElement('textarea', 'motivation', get_string('field:motivation', 'local_oriskill'),
                ['rows' => 5, 'cols' => 60]);
            $mform->setType('motivation', PARAM_TEXT);
            $mform->addRule('motivation', null, 'required', null, 'client');
        }

        $this->add_action_buttons(false, get_string($creator ? 'savechanges' : 'submitapplication',
            $creator ? 'moodle' : 'local_oriskill'));
    }

    /**
     * Showcase appearance: colours, typeface, banner and own domain name.
     *
     * @param \stdClass $creator Trainer.
     */
    private function appearance_fields(\stdClass $creator): void {
        global $PAGE;

        $mform = $this->_form;
        $mform->addElement('header', 'appearance', get_string('appearance', 'local_oriskill'));
        $mform->setExpanded('appearance');

        foreach (['primarycolor' => creator_service::DEFAULT_PRIMARY, 'accentcolor' => creator_service::DEFAULT_ACCENT]
                as $field => $default) {
            $mform->addElement('text', $field, get_string('field:' . $field, 'local_oriskill'),
                ['size' => 8, 'maxlength' => 7]);
            $mform->setType($field, PARAM_RAW_TRIMMED);
            $mform->setDefault($field, $default);
            $mform->addHelpButton($field, 'field:' . $field, 'local_oriskill');
        }
        // Browsers show a colour picker for these fields; without JavaScript they stay #rrggbb text fields.
        $PAGE->requires->js_amd_inline("document.querySelectorAll('#id_primarycolor, #id_accentcolor').forEach(function(i) {
            if (/^#[0-9a-fA-F]{6}$/.test(i.value)) {
                i.type = 'color';
                i.style.cssText = 'width: 5rem; height: 2.5rem; padding: .2rem; cursor: pointer;';
            }
        });");

        $fonts = [];
        foreach (array_keys(creator_service::FONTS) as $font) {
            $fonts[$font] = get_string('font:' . $font, 'local_oriskill');
        }
        $mform->addElement('select', 'fontstyle', get_string('field:fontstyle', 'local_oriskill'), $fonts);

        $mform->addElement('filemanager', 'banner_filemanager', get_string('field:banner', 'local_oriskill'), null,
            self::BANNER_OPTIONS);
        $mform->addHelpButton('banner_filemanager', 'field:banner', 'local_oriskill');

        $mform->addElement('static', 'domainslink', get_string('field:customdomain', 'local_oriskill'),
            \html_writer::link(new \moodle_url('/local/oriskill/domains.php'), get_string('domains:manage', 'local_oriskill')) .
            (!empty($creator->customdomain) ? ' · ' . s($creator->customdomain) : ''));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $creator = $this->_customdata['creator'] ?? null;
        if (!$creator) {
            $error = creator_service::slug_error((string) ($data['slug'] ?? ''));
            if ($error) {
                $errors['slug'] = get_string($error, 'local_oriskill');
            }
            return $errors;
        }
        foreach (['primarycolor', 'accentcolor'] as $field) {
            if (creator_service::valid_color((string) ($data[$field] ?? '')) === null) {
                $errors[$field] = get_string('error:color', 'local_oriskill');
            }
        }
        return $errors;
    }
}

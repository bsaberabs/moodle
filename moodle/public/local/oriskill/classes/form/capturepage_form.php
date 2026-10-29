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

use local_oriskill\capture_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * A capture page: texts, image, free courses, what happens after sign-up.
 *
 * Custom data: 'creator', 'pageid' (0 for a new one), 'courses' (id => name), 'forms' (id => title).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capturepage_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['pageid']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title', get_string('orderform:title', 'local_oriskill'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('text', 'slug', get_string('orderform:slug', 'local_oriskill'), ['size' => 30]);
        $mform->setType('slug', PARAM_RAW_TRIMMED);
        $mform->addRule('slug', null, 'required', null, 'client');
        $mform->addHelpButton('slug', 'capture:slug', 'local_oriskill');

        $mform->addElement('text', 'headline', get_string('orderform:headline', 'local_oriskill'), ['size' => 80]);
        $mform->setType('headline', PARAM_TEXT);

        $mform->addElement('editor', 'description_editor', get_string('orderform:description', 'local_oriskill'), null,
            ['maxfiles' => 0]);
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('filemanager', 'image_filemanager', get_string('orderform:image', 'local_oriskill'), null,
            capture_service::IMAGE_OPTIONS);

        $mform->addElement('autocomplete', 'courseids', get_string('capture:courses', 'local_oriskill'),
            $this->_customdata['courses'], ['multiple' => true]);
        $mform->setType('courseids', PARAM_INT);
        $mform->addRule('courseids', null, 'required', null, 'client');

        $mform->addElement('text', 'accessdays', get_string('capture:accessdays', 'local_oriskill'), ['size' => 5]);
        $mform->setType('accessdays', PARAM_INT);
        $mform->setDefault('accessdays', 0);
        $mform->addHelpButton('accessdays', 'capture:accessdays', 'local_oriskill');

        $mform->addElement('text', 'buttonlabel', get_string('orderform:buttonlabel', 'local_oriskill'), ['size' => 30]);
        $mform->setType('buttonlabel', PARAM_TEXT);
        $mform->setDefault('buttonlabel', get_string('capture:buttondefault', 'local_oriskill'));

        $mform->addElement('textarea', 'thankyou', get_string('orderform:thankyou', 'local_oriskill'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('thankyou', PARAM_TEXT);

        $mform->addElement('select', 'redirectformid', get_string('capture:redirect', 'local_oriskill'),
            [0 => get_string('capture:noredirect', 'local_oriskill')] + $this->_customdata['forms']);
        $mform->addHelpButton('redirectformid', 'capture:redirect', 'local_oriskill');

        $mform->addElement('text', 'actags', get_string('orderform:offeractags', 'local_oriskill'), ['size' => 50]);
        $mform->setType('actags', PARAM_TEXT);

        $mform->addElement('select', 'status', get_string('status'), [
            'draft' => get_string('orderform:draft', 'local_oriskill'),
            'published' => get_string('orderform:published', 'local_oriskill'),
        ]);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $error = capture_service::slug_error((string) $data['slug'], $this->_customdata['creator'], (int) $data['id']);
        if ($error) {
            $errors['slug'] = get_string($error, 'local_oriskill');
        }
        if (empty($data['courseids'])) {
            $errors['courseids'] = get_string('error:offercourses', 'local_oriskill');
        }
        return $errors;
    }
}

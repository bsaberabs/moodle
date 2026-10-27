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
 * External API for learner profile data.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\services\learner_profile_service;
use moodle_url;

/**
 * Returns current learner profile data for the Modern Commerce profile page.
 */
class get_profile extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute.
     *
     * @return array
     */
    public static function execute(): array {
        global $DB, $PAGE, $USER;

        self::validate_parameters(self::execute_parameters(), []);
        require_login();

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewcatalog', $context);

        $PAGE->set_context($context);
        $user = $DB->get_record('user', ['id' => $USER->id], '*', MUST_EXIST);
        $picture = new \user_picture($user);
        $picture->size = 160;
        $editstate = learner_profile_service::get_profile_edit_state($user);
        $standard = learner_profile_service::get_profile_editable_standard($user);

        return [
            'success' => true,
            'message' => '',
            'userid' => (int)$USER->id,
            'profileimage' => $picture->get_url($PAGE)->out(false),
            'profile' => learner_profile_service::get_profile_display_data((int)$USER->id),
            'canedit' => !empty($editstate['canedit']),
            'editmessage' => (string)$editstate['message'],
            'userediturl' => (string)$editstate['url'],
            'countryoptions' => learner_profile_service::get_country_options((string)$user->country),
            'editablecustomfields' => learner_profile_service::get_editable_custom_profile_fields((int)$USER->id),
            'editable' => $standard['editable'],
            'interests' => $standard['interests'],
            'timezoneoptions' => $standard['timezoneoptions'],
            'languageoptions' => $standard['languageoptions'],
            'maildisplayoptions' => $standard['maildisplayoptions'],
            'fieldlocks' => $standard['fieldlocks'],
            'urls' => [
                'profile' => (new moodle_url('/local/moderncommerce/learner/profile.php'))->out(false),
                'coreprofile' => (new moodle_url('/user/profile.php', ['id' => $USER->id]))->out(false),
                'editprofile' => (new moodle_url('/user/edit.php', ['id' => $USER->id, 'course' => SITEID]))->out(false),
            ],
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether profile loaded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'userid' => new external_value(PARAM_INT, 'User ID.'),
            'profileimage' => new external_value(PARAM_URL, 'Profile image URL.'),
            'profile' => self::profile_structure(),
            'canedit' => new external_value(PARAM_BOOL, 'Whether profile can be edited.'),
            'editmessage' => new external_value(PARAM_TEXT, 'Profile edit state message.'),
            'userediturl' => new external_value(PARAM_RAW, 'Full Moodle profile editor URL.'),
            'countryoptions' => new external_multiple_structure(new external_single_structure([
                'value' => new external_value(PARAM_TEXT, 'Country code.'),
                'label' => new external_value(PARAM_TEXT, 'Country label.'),
                'selected' => new external_value(PARAM_BOOL, 'Whether selected.'),
            ])),
            'editablecustomfields' => new external_multiple_structure(self::editable_custom_field_structure()),
            'editable' => self::editable_standard_structure(),
            'interests' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Interest tag.')),
            'timezoneoptions' => self::option_list_structure(),
            'languageoptions' => self::option_list_structure(),
            'maildisplayoptions' => self::option_list_structure(),
            'fieldlocks' => self::fieldlocks_structure(),
            'urls' => new external_single_structure([
                'profile' => new external_value(PARAM_RAW, 'Modern Commerce profile URL.'),
                'coreprofile' => new external_value(PARAM_RAW, 'Moodle profile URL.'),
                'editprofile' => new external_value(PARAM_RAW, 'Moodle edit profile URL.'),
            ]),
        ]);
    }

    /**
     * Reusable option-list structure (value/label/selected).
     *
     * @return external_multiple_structure
     */
    private static function option_list_structure(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'value' => new external_value(PARAM_RAW, 'Option value.'),
            'label' => new external_value(PARAM_RAW, 'Option label.'),
            'selected' => new external_value(PARAM_BOOL, 'Whether selected.'),
        ]));
    }

    /**
     * Editable standard-field values structure.
     *
     * @return external_single_structure
     */
    private static function editable_standard_structure(): external_single_structure {
        return new external_single_structure([
            'phone1' => new external_value(PARAM_RAW, 'Phone.'),
            'phone2' => new external_value(PARAM_RAW, 'Mobile phone.'),
            'address' => new external_value(PARAM_RAW, 'Address.'),
            'idnumber' => new external_value(PARAM_RAW, 'ID number.'),
            'timezone' => new external_value(PARAM_RAW, 'Timezone code.'),
            'maildisplay' => new external_value(PARAM_RAW, 'Email visibility value.'),
            'lang' => new external_value(PARAM_RAW, 'Language code.'),
            'description' => new external_value(PARAM_RAW, 'Description (plain text).'),
        ]);
    }

    /**
     * Per-field lock flags structure.
     *
     * @return external_single_structure
     */
    private static function fieldlocks_structure(): external_single_structure {
        $keys = [
            'firstname', 'lastname', 'email', 'city', 'country', 'department', 'institution',
            'phone1', 'phone2', 'address', 'idnumber', 'timezone', 'maildisplay', 'lang', 'description',
        ];
        $fields = [];
        foreach ($keys as $key) {
            $fields[$key] = new external_value(PARAM_BOOL, 'Whether the field is locked.');
        }
        return new external_single_structure($fields);
    }

    /**
     * Profile display structure.
     *
     * @return external_single_structure
     */
    private static function profile_structure(): external_single_structure {
        return new external_single_structure([
            'fullname' => new external_value(PARAM_TEXT, 'Full name.'),
            'firstname' => new external_value(PARAM_TEXT, 'First name.'),
            'lastname' => new external_value(PARAM_TEXT, 'Last name.'),
            'email' => new external_value(PARAM_TEXT, 'Email.'),
            'city' => new external_value(PARAM_TEXT, 'City.'),
            'country' => new external_value(PARAM_TEXT, 'Country.'),
            'department' => new external_value(PARAM_TEXT, 'Department.'),
            'institution' => new external_value(PARAM_TEXT, 'Institution.'),
            'phone1' => new external_value(PARAM_TEXT, 'Phone.'),
            'phone2' => new external_value(PARAM_TEXT, 'Mobile phone.'),
            'address' => new external_value(PARAM_TEXT, 'Address.'),
            'idnumber' => new external_value(PARAM_TEXT, 'ID number.'),
            'timezone' => new external_value(PARAM_TEXT, 'Timezone label.'),
            'maildisplay' => new external_value(PARAM_TEXT, 'Email visibility label.'),
            'language' => new external_value(PARAM_TEXT, 'Language label.'),
            'description' => new external_value(PARAM_RAW, 'Description text.'),
            'interests' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Interest tag.')),
            'customfields' => new external_multiple_structure(new external_single_structure([
                'inputname' => new external_value(PARAM_TEXT, 'Input name.'),
                'label' => new external_value(PARAM_TEXT, 'Field label.'),
                'displayvalue' => new external_value(PARAM_RAW, 'Display value.'),
                'hasdisplayvalue' => new external_value(PARAM_BOOL, 'Whether value exists.'),
            ])),
        ]);
    }

    /**
     * Editable custom field structure.
     *
     * @return external_single_structure
     */
    private static function editable_custom_field_structure(): external_single_structure {
        return new external_single_structure([
            'inputname' => new external_value(PARAM_TEXT, 'Input name.'),
            'label' => new external_value(PARAM_TEXT, 'Field label.'),
            'datatype' => new external_value(PARAM_TEXT, 'Datatype.'),
            'value' => new external_value(PARAM_RAW, 'Current value.'),
            'required' => new external_value(PARAM_BOOL, 'Whether required.'),
            'locked' => new external_value(PARAM_BOOL, 'Whether locked.'),
            'disabled' => new external_value(PARAM_BOOL, 'Whether disabled.'),
            'istext' => new external_value(PARAM_BOOL, 'Whether text input.'),
            'istextarea' => new external_value(PARAM_BOOL, 'Whether textarea.'),
            'ismenu' => new external_value(PARAM_BOOL, 'Whether menu.'),
            'ischeckbox' => new external_value(PARAM_BOOL, 'Whether checkbox.'),
            'isdatetime' => new external_value(PARAM_BOOL, 'Whether datetime.'),
            'isdateonly' => new external_value(PARAM_BOOL, 'Whether date only.'),
            'inputtype' => new external_value(PARAM_TEXT, 'HTML input type.'),
            'checked' => new external_value(PARAM_BOOL, 'Whether checked.', VALUE_OPTIONAL),
            'hasdatetime' => new external_value(PARAM_BOOL, 'Whether datetime includes time.', VALUE_OPTIONAL),
            'options' => new external_multiple_structure(new external_single_structure([
                'value' => new external_value(PARAM_TEXT, 'Option value.'),
                'label' => new external_value(PARAM_TEXT, 'Option label.'),
                'selected' => new external_value(PARAM_BOOL, 'Whether selected.'),
            ])),
            'minyear' => new external_value(PARAM_TEXT, 'Minimum year.'),
            'maxyear' => new external_value(PARAM_TEXT, 'Maximum year.'),
        ]);
    }
}

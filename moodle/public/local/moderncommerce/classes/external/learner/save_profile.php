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
 * External API for learner profile updates.
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

/**
 * Saves current learner profile fields.
 */
class save_profile extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User id.', VALUE_REQUIRED),
            'firstname' => new external_value(PARAM_NOTAGS, 'First name.', VALUE_REQUIRED),
            'lastname' => new external_value(PARAM_NOTAGS, 'Last name.', VALUE_REQUIRED),
            'email' => new external_value(PARAM_EMAIL, 'Email address.', VALUE_REQUIRED),
            'city' => new external_value(PARAM_TEXT, 'City.', VALUE_DEFAULT, ''),
            'country' => new external_value(PARAM_ALPHA, 'Country code.', VALUE_DEFAULT, ''),
            'department' => new external_value(PARAM_TEXT, 'Department.', VALUE_DEFAULT, ''),
            'institution' => new external_value(PARAM_TEXT, 'Institution.', VALUE_DEFAULT, ''),
            'customfields' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'Custom profile field input name.', VALUE_REQUIRED),
                    'value' => new external_value(PARAM_RAW, 'Custom profile field value.', VALUE_REQUIRED),
                ]),
                'Custom profile field values.',
                VALUE_DEFAULT,
                []
            ),
            'phone1' => new external_value(PARAM_NOTAGS, 'Phone.', VALUE_DEFAULT, ''),
            'phone2' => new external_value(PARAM_NOTAGS, 'Mobile phone.', VALUE_DEFAULT, ''),
            'address' => new external_value(PARAM_TEXT, 'Address.', VALUE_DEFAULT, ''),
            'idnumber' => new external_value(PARAM_NOTAGS, 'ID number.', VALUE_DEFAULT, ''),
            'timezone' => new external_value(PARAM_NOTAGS, 'Timezone code.', VALUE_DEFAULT, '99'),
            'maildisplay' => new external_value(PARAM_INT, 'Email visibility (0,1,2).', VALUE_DEFAULT, 2),
            'lang' => new external_value(PARAM_LANG, 'Language code.', VALUE_DEFAULT, ''),
            'description' => new external_value(PARAM_TEXT, 'Description (plain text).', VALUE_DEFAULT, ''),
            'interests' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Interest tag.'),
                'Interest tags.',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $userid User id.
     * @param string $firstname First name.
     * @param string $lastname Last name.
     * @param string $email Email address.
     * @param string $city City.
     * @param string $country Country code.
     * @param string $department Department.
     * @param string $institution Institution.
     * @param array $customfields Custom fields.
     * @return array
     */
    public static function execute(
        $userid,
        $firstname,
        $lastname,
        $email,
        $city = '',
        $country = '',
        $department = '',
        $institution = '',
        $customfields = [],
        $phone1 = '',
        $phone2 = '',
        $address = '',
        $idnumber = '',
        $timezone = '99',
        $maildisplay = 2,
        $lang = '',
        $description = '',
        $interests = []
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'city' => $city,
            'country' => $country,
            'department' => $department,
            'institution' => $institution,
            'customfields' => $customfields,
            'phone1' => $phone1,
            'phone2' => $phone2,
            'address' => $address,
            'idnumber' => $idnumber,
            'timezone' => $timezone,
            'maildisplay' => $maildisplay,
            'lang' => $lang,
            'description' => $description,
            'interests' => $interests,
        ]);

        require_login();

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewcatalog', $context);
        require_capability('moodle/user:editownprofile', $context);

        $customfieldvalues = [];
        foreach ($params['customfields'] as $field) {
            $customfieldvalues[$field['name']] = $field['value'];
        }

        $result = learner_profile_service::save_profile_from_data([
            'userid' => $params['userid'],
            'firstname' => $params['firstname'],
            'lastname' => $params['lastname'],
            'email' => $params['email'],
            'city' => $params['city'],
            'country' => $params['country'],
            'department' => $params['department'],
            'institution' => $params['institution'],
            'customfields' => $customfieldvalues,
            'phone1' => $params['phone1'],
            'phone2' => $params['phone2'],
            'address' => $params['address'],
            'idnumber' => $params['idnumber'],
            'timezone' => $params['timezone'],
            'maildisplay' => $params['maildisplay'],
            'lang' => $params['lang'],
            'description' => $params['description'],
            'interests' => $params['interests'],
        ]);

        $result['errors'] = learner_profile_service::format_external_errors($result['errors'] ?? []);
        $result['profile'] = $result['profile'] ?? self::empty_profile();

        return $result;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'errors' => self::errors_structure(),
            'profile' => self::profile_structure(),
        ]);
    }

    /**
     * Empty profile fallback.
     *
     * @return array
     */
    private static function empty_profile(): array {
        return [
            'fullname' => '',
            'firstname' => '',
            'lastname' => '',
            'email' => '',
            'city' => '',
            'country' => '',
            'department' => '',
            'institution' => '',
            'phone1' => '',
            'phone2' => '',
            'address' => '',
            'idnumber' => '',
            'timezone' => '',
            'maildisplay' => '',
            'language' => '',
            'description' => '',
            'interests' => [],
            'customfields' => [],
        ];
    }

    /**
     * Error list structure.
     *
     * @return external_multiple_structure
     */
    private static function errors_structure(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'field' => new external_value(PARAM_TEXT, 'Field the message belongs to.'),
            'name' => new external_value(PARAM_TEXT, 'Deprecated alias of field.'),
            'message' => new external_value(PARAM_TEXT, 'Error message.'),
        ]));
    }

    /**
     * Profile structure.
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
}

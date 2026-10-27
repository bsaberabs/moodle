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
 * Learner profile helper service.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

use context_course;
use context_system;
use context_user;
use core_user;
use moodle_exception;
use moodle_url;
use stdClass;
use user_picture;

/**
 * Profile helpers used by the Modern Commerce learner dashboard.
 */
class learner_profile_service {
    /**
     * Return whether the current user can edit the supplied profile.
     *
     * @param stdClass $user User record.
     * @return array Edit state.
     */
    public static function get_profile_edit_state(stdClass $user): array {
        global $CFG, $USER;

        require_once($CFG->libdir . '/authlib.php');

        $systemcontext = context_system::instance();
        $fallbackurl = new moodle_url('/user/edit.php', [
            'id' => $user->id,
            'course' => SITEID,
        ]);

        if ((int)$user->id !== (int)$USER->id) {
            return [
                'canedit' => false,
                'message' => get_string('profileeditnotallowed', 'local_moderncommerce'),
                'url' => $fallbackurl->out(false),
            ];
        }

        if (isguestuser($user) || !empty($user->deleted) || is_mnet_remote_user($user)) {
            return [
                'canedit' => false,
                'message' => get_string('profileeditunavailable', 'local_moderncommerce'),
                'url' => $fallbackurl->out(false),
            ];
        }

        if (!has_capability('moodle/user:editownprofile', $systemcontext)) {
            return [
                'canedit' => false,
                'message' => get_string('profileeditnotallowed', 'local_moderncommerce'),
                'url' => $fallbackurl->out(false),
            ];
        }

        $authplugin = get_auth_plugin($user->auth);
        if (!$authplugin->can_edit_profile()) {
            return [
                'canedit' => false,
                'message' => get_string('profileeditunavailable', 'local_moderncommerce'),
                'url' => $fallbackurl->out(false),
            ];
        }

        $externalurl = $authplugin->edit_profile_url();
        if (!empty($externalurl)) {
            return [
                'canedit' => false,
                'message' => get_string('profileeditexternal', 'local_moderncommerce'),
                'url' => $externalurl,
            ];
        }

        return [
            'canedit' => true,
            'message' => '',
            'url' => $fallbackurl->out(false),
        ];
    }

    /**
     * Build select options for the current user's country.
     *
     * @param string $selected Selected country code.
     * @return array Country options.
     */
    public static function get_country_options(string $selected): array {
        $options = [
            [
                'value' => '',
                'label' => get_string('selectacountry', 'local_moderncommerce'),
                'selected' => $selected === '',
            ],
        ];

        foreach (get_string_manager()->get_list_of_countries() as $code => $country) {
            $options[] = [
                'value' => $code,
                'label' => $country,
                'selected' => $selected === $code,
            ];
        }

        return $options;
    }

    /**
     * Return learner profile display values for core fields and visible custom fields.
     *
     * @param int $userid User id.
     * @return array Profile display data.
     */
    public static function get_profile_display_data(int $userid): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $country = '';
        if (!empty($user->country)) {
            $country = get_string($user->country, 'countries');
        }

        $notprovided = get_string('notprovided', 'local_moderncommerce');
        $customfields = [];
        foreach (profile_get_user_fields_with_data($userid) as $field) {
            if (!$field->is_visible()) {
                continue;
            }

            $displayvalue = '';
            if ($field->show_field_content()) {
                $displayvalue = $field->display_data();
            }

            $customfields[] = [
                'inputname' => $field->inputname,
                'label' => $field->display_name(false),
                'displayvalue' => $displayvalue,
                'hasdisplayvalue' => $displayvalue !== '',
            ];
        }

        $maildisplaylabels = [
            0 => get_string('emaildisplayno'),
            1 => get_string('emaildisplayyes'),
            2 => get_string('emaildisplaycourse'),
        ];
        $translations = get_string_manager()->get_list_of_translations();
        $descriptiontext = trim(content_to_text((string)($user->description ?? ''), $user->descriptionformat ?? FORMAT_MOODLE));
        $interests = array_values(\core_tag_tag::get_item_tags_array('core', 'user', $userid));

        return [
            'fullname' => fullname($user),
            'firstname' => format_string($user->firstname),
            'lastname' => format_string($user->lastname),
            'email' => $user->email,
            'city' => $user->city ?: $notprovided,
            'country' => $country ?: $notprovided,
            'department' => $user->department ?: $notprovided,
            'institution' => $user->institution ?: $notprovided,
            'phone1' => $user->phone1 ?: $notprovided,
            'phone2' => $user->phone2 ?: $notprovided,
            'address' => $user->address ?: $notprovided,
            'idnumber' => $user->idnumber ?: $notprovided,
            'timezone' => \core_date::get_localised_timezone(\core_date::get_user_timezone($user)),
            'maildisplay' => $maildisplaylabels[(int)$user->maildisplay] ?? (string)$user->maildisplay,
            'language' => $translations[$user->lang] ?? $user->lang,
            'description' => $descriptiontext !== '' ? $descriptiontext : $notprovided,
            'interests' => $interests,
            'customfields' => $customfields,
        ];
    }

    /**
     * Return displayable custom profile fields for the profile tab.
     *
     * @param int $userid User id.
     * @return array Custom profile field rows.
     */
    public static function get_display_custom_profile_fields(int $userid): array {
        return self::get_profile_display_data($userid)['customfields'];
    }

    /**
     * Convert menu profile field options to frontend-friendly data.
     *
     * @param array $options Menu options.
     * @param mixed $selected Selected value.
     * @return array Option rows.
     */
    public static function get_profile_menu_options(array $options, $selected): array {
        $rows = [];
        foreach ($options as $value => $label) {
            $rows[] = [
                'value' => $value,
                'label' => $label,
                'selected' => (string)$selected === (string)$value,
            ];
        }

        return $rows;
    }

    /**
     * Return editable custom profile fields for the learner profile editor.
     *
     * @param int $userid User id.
     * @return array Custom profile field form data.
     */
    public static function get_editable_custom_profile_fields(int $userid): array {
        global $CFG;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        $fields = [];
        $canupdateusers = has_capability('moodle/user:update', context_system::instance());
        foreach (profile_get_user_fields_with_data($userid) as $field) {
            if (!$field->is_editable()) {
                continue;
            }

            $islocked = $field->is_locked() && !$canupdateusers;
            $datatype = $field->field->datatype;
            $value = $field->data ?? '';
            $row = [
                'inputname' => $field->inputname,
                'label' => $field->display_name(false),
                'datatype' => $datatype,
                'value' => (string)$value,
                'required' => $field->is_required(),
                'locked' => $islocked,
                'disabled' => $islocked,
                'istext' => false,
                'istextarea' => false,
                'ismenu' => false,
                'ischeckbox' => false,
                'isdatetime' => false,
                'isdateonly' => false,
                'inputtype' => 'text',
                'options' => [],
                'minyear' => '',
                'maxyear' => '',
            ];

            if ($datatype === 'textarea') {
                $row['istextarea'] = true;
                $row['value'] = clean_text((string)$value, $field->dataformat);
            } else if ($datatype === 'menu' && property_exists($field, 'options')) {
                $row['ismenu'] = true;
                $row['options'] = self::get_profile_menu_options(
                    $field->options,
                    property_exists($field, 'datakey') ? $field->datakey : $value
                );
            } else if ($datatype === 'checkbox') {
                $row['ischeckbox'] = true;
                $row['checked'] = !empty($value);
            } else if ($datatype === 'datetime') {
                $row['isdatetime'] = true;
                $row['hasdatetime'] = !empty($field->field->param3);
                $row['isdateonly'] = empty($field->field->param3);
                $row['minyear'] = (int)$field->field->param1;
                $row['maxyear'] = (int)$field->field->param2;
                if (!empty($value)) {
                    $row['value'] = !empty($field->field->param3)
                        ? userdate($value, '%Y-%m-%dT%H:%M')
                        : userdate($value, '%Y-%m-%d');
                } else {
                    $row['value'] = '';
                }
            } else {
                $row['istext'] = true;
                if ($datatype === 'text' && !empty($field->field->param3)) {
                    $row['inputtype'] = 'password';
                } else if ($datatype === 'social' && $field->field->param1 === 'url') {
                    $row['inputtype'] = 'url';
                }
            }

            $fields[] = $row;
        }

        return $fields;
    }

    /**
     * Return per-field lock flags for standard user fields based on the auth plugin config.
     *
     * @param stdClass $user User record.
     * @return array Map of field name => bool.
     */
    public static function get_profile_field_locks(stdClass $user): array {
        global $CFG;

        require_once($CFG->libdir . '/authlib.php');

        $locked = [];
        $fields = [
            'firstname', 'lastname', 'email', 'city', 'country', 'department', 'institution',
            'phone1', 'phone2', 'address', 'idnumber', 'timezone', 'maildisplay', 'lang', 'description',
        ];
        $authplugin = get_auth_plugin($user->auth);
        foreach ($fields as $field) {
            $islocked = false;
            $configvar = 'field_lock_' . $field;
            if (isset($authplugin->config->{$configvar})) {
                $lockmode = $authplugin->config->{$configvar};
                if ($lockmode === 'locked') {
                    $islocked = true;
                } else if ($lockmode === 'unlockedifempty' && !empty($user->{$field})) {
                    $islocked = true;
                }
            }
            $locked[$field] = $islocked;
        }

        if (isset($CFG->forcetimezone) && $CFG->forcetimezone != 99) {
            $locked['timezone'] = true;
        }

        return $locked;
    }

    /**
     * Return editable standard user fields, option lists and lock flags.
     *
     * @param stdClass $user User record.
     * @return array
     */
    public static function get_profile_editable_standard(stdClass $user): array {
        $maildisplayoptions = [];
        foreach ([0 => 'emaildisplayno', 1 => 'emaildisplayyes', 2 => 'emaildisplaycourse'] as $value => $key) {
            $maildisplayoptions[] = [
                'value' => (string)$value,
                'label' => get_string($key),
                'selected' => (int)$user->maildisplay === $value,
            ];
        }

        $timezoneoptions = [];
        foreach (\core_date::get_list_of_timezones((string)$user->timezone, true) as $value => $label) {
            $timezoneoptions[] = [
                'value' => (string)$value,
                'label' => $label,
                'selected' => (string)$user->timezone === (string)$value,
            ];
        }

        $languageoptions = [];
        foreach (get_string_manager()->get_list_of_translations() as $value => $label) {
            $languageoptions[] = [
                'value' => (string)$value,
                'label' => $label,
                'selected' => (string)$user->lang === (string)$value,
            ];
        }

        return [
            'editable' => [
                'phone1' => (string)$user->phone1,
                'phone2' => (string)$user->phone2,
                'address' => (string)$user->address,
                'idnumber' => (string)$user->idnumber,
                'timezone' => (string)$user->timezone,
                'maildisplay' => (string)$user->maildisplay,
                'lang' => (string)$user->lang,
                'description' => trim(content_to_text(
                    (string)($user->description ?? ''),
                    $user->descriptionformat ?? FORMAT_MOODLE
                )),
            ],
            'interests' => array_values(\core_tag_tag::get_item_tags_array('core', 'user', $user->id)),
            'timezoneoptions' => $timezoneoptions,
            'languageoptions' => $languageoptions,
            'maildisplayoptions' => $maildisplayoptions,
            'fieldlocks' => self::get_profile_field_locks($user),
        ];
    }

    /**
     * Return the current user record after checking profile edit permission.
     *
     * @param int $userid User id.
     * @return stdClass User record.
     */
    public static function require_profile_edit_user(int $userid): stdClass {
        global $DB, $USER;

        if ((int)$userid !== (int)$USER->id) {
            throw new moodle_exception('profileeditnotallowed', 'local_moderncommerce');
        }

        require_capability('moodle/user:editownprofile', context_system::instance());

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $editstate = self::get_profile_edit_state($user);
        if (empty($editstate['canedit'])) {
            throw new moodle_exception('profileeditnotallowed', 'local_moderncommerce');
        }

        return $user;
    }

    /**
     * Create a draft file for a profile image from a local path.
     *
     * @param string $pathname Local file path.
     * @param string $filename Original file name.
     * @param int $filesize File size in bytes.
     * @param stdClass $user User record.
     * @param bool $mustbeuploaded Whether the source must be a PHP uploaded file.
     * @return int Draft item id.
     */
    public static function create_profile_picture_draft_from_path(
        string $pathname,
        string $filename,
        int $filesize,
        stdClass $user,
        bool $mustbeuploaded = false
    ): int {
        global $CFG, $USER;

        require_once($CFG->libdir . '/filelib.php');

        $filename = clean_param($filename, PARAM_FILE);
        if ($filename === '') {
            throw new moodle_exception('profileimagefilemissing', 'local_moderncommerce');
        }

        $maxbytes = get_max_upload_file_size($CFG->maxbytes);
        $detectedfilesize = is_readable($pathname) ? filesize($pathname) : false;
        $filesize = $detectedfilesize === false ? (int)$filesize : $detectedfilesize;
        if ($filesize <= 0) {
            throw new moodle_exception('profileimagefilemissing', 'local_moderncommerce');
        }
        if ($filesize > $maxbytes) {
            throw new moodle_exception('maxbytes', 'local_moderncommerce');
        }

        if (empty($pathname) || !is_readable($pathname)) {
            throw new moodle_exception('profileimagefilemissing', 'local_moderncommerce');
        }

        if ($mustbeuploaded && !is_uploaded_file($pathname)) {
            throw new moodle_exception('profileimagefilemissing', 'local_moderncommerce');
        }

        if (@getimagesize($pathname) === false) {
            throw new moodle_exception('profileimageinvalid', 'local_moderncommerce');
        }

        $context = context_user::instance($user->id, MUST_EXIST);
        $draftitemid = file_get_unused_draft_itemid();
        $filerecord = (object)[
            'contextid' => $context->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $USER->id,
            'license' => $CFG->sitedefaultlicense,
            'author' => fullname($USER),
            'source' => serialize((object)['source' => $filename]),
        ];

        get_file_storage()->create_file_from_pathname($filerecord, $pathname);

        return $draftitemid;
    }

    /**
     * Create a draft file for a profile image upload.
     *
     * @param stdClass $upload Uploaded file array as an object.
     * @param stdClass $user User record.
     * @return int Draft item id.
     */
    public static function create_profile_picture_draft(stdClass $upload, stdClass $user): int {
        if ($upload->error !== UPLOAD_ERR_OK) {
            throw new moodle_exception('profileimagefilemissing', 'local_moderncommerce');
        }

        return self::create_profile_picture_draft_from_path(
            $upload->tmp_name,
            $upload->name,
            $upload->size,
            $user,
            true
        );
    }

    /**
     * Save the current user's profile picture from a prepared draft item.
     *
     * @param int $userid User id.
     * @param bool $deletepicture Whether to delete the current picture.
     * @param int $draftitemid Draft item id for the new picture.
     * @return array Save result.
     */
    public static function save_profile_picture(int $userid, bool $deletepicture, int $draftitemid = 0): array {
        global $CFG, $DB, $PAGE, $USER;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        if (!empty($CFG->disableuserimages)) {
            throw new moodle_exception('profileimagesdisabled', 'local_moderncommerce');
        }

        $user = self::require_profile_edit_user($userid);
        if (!$deletepicture && $draftitemid <= 0) {
            return [
                'success' => false,
                'message' => get_string('profileimageupdatefailed', 'local_moderncommerce'),
                'errors' => [
                    'profileimage' => get_string('profileimagefilemissing', 'local_moderncommerce'),
                ],
            ];
        }

        $filemanageroptions = [
            'maxbytes' => $CFG->maxbytes,
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => 'optimised_image',
        ];
        $usernew = clone($user);
        $usernew->deletepicture = $deletepicture;
        $usernew->imagefile = $draftitemid;
        core_user::update_picture($usernew, $filemanageroptions);

        $updateduser = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        $USER->picture = $updateduser->picture;
        profile_load_custom_fields($USER);

        $userpicture = new user_picture($updateduser);
        $userpicture->size = 160;

        return [
            'success' => true,
            'message' => get_string('profileimagesaved', 'local_moderncommerce'),
            'profileimage' => $userpicture->get_url($PAGE)->out(false),
        ];
    }

    /**
     * Save the current user's profile picture from a dashboard request.
     *
     * @return array Save result.
     */
    public static function save_profile_picture_from_request(): array {
        $userid = required_param('userid', PARAM_INT);
        $deletepicture = optional_param('deletepicture', 0, PARAM_BOOL);
        $draftitemid = 0;

        if (!$deletepicture) {
            if (empty($_FILES['profileimage'])) {
                return [
                    'success' => false,
                    'message' => get_string('profileimageupdatefailed', 'local_moderncommerce'),
                    'errors' => [
                        'profileimage' => get_string('profileimagefilemissing', 'local_moderncommerce'),
                    ],
                ];
            }

            $user = self::require_profile_edit_user($userid);
            $draftitemid = self::create_profile_picture_draft((object)$_FILES['profileimage'], $user);
        }

        return self::save_profile_picture($userid, $deletepicture, $draftitemid);
    }

    /**
     * Save profile data submitted from the learner dashboard.
     *
     * @param array $data Submitted profile data.
     * @return array Save result.
     */
    public static function save_profile_from_data(array $data): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/user/editlib.php');
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->libdir . '/authlib.php');

        $userid = clean_param($data['userid'] ?? 0, PARAM_INT);
        $user = self::require_profile_edit_user($userid);

        $systemcontext = context_system::instance();
        $usernew = clone($user);
        $usernew->firstname = trim(clean_param($data['firstname'] ?? $user->firstname, PARAM_NOTAGS));
        $usernew->lastname = trim(clean_param($data['lastname'] ?? $user->lastname, PARAM_NOTAGS));
        $usernew->email = trim(clean_param($data['email'] ?? $user->email, PARAM_EMAIL));
        $usernew->city = trim(clean_param($data['city'] ?? '', PARAM_TEXT));
        $usernew->country = trim(clean_param($data['country'] ?? '', PARAM_ALPHA));
        $usernew->department = trim(clean_param($data['department'] ?? '', PARAM_TEXT));
        $usernew->institution = trim(clean_param($data['institution'] ?? '', PARAM_TEXT));

        $locks = self::get_profile_field_locks($user);
        if (empty($locks['phone1'])) {
            $usernew->phone1 = trim(clean_param($data['phone1'] ?? $user->phone1, PARAM_NOTAGS));
        }
        if (empty($locks['phone2'])) {
            $usernew->phone2 = trim(clean_param($data['phone2'] ?? $user->phone2, PARAM_NOTAGS));
        }
        if (empty($locks['address'])) {
            $usernew->address = trim(clean_param($data['address'] ?? $user->address, PARAM_TEXT));
        }
        if (empty($locks['idnumber'])) {
            $usernew->idnumber = trim(clean_param($data['idnumber'] ?? $user->idnumber, PARAM_NOTAGS));
        }
        if (empty($locks['maildisplay']) && array_key_exists('maildisplay', $data)) {
            $usernew->maildisplay = clean_param($data['maildisplay'], PARAM_INT);
        }
        if (empty($locks['timezone']) && array_key_exists('timezone', $data)) {
            $tzraw = (string)$data['timezone'];
            $timezone = $tzraw === '99' ? '99' : clean_param($tzraw, PARAM_TIMEZONE);
            if ($timezone !== '') {
                $usernew->timezone = $timezone;
            }
        }
        if (empty($locks['lang']) && array_key_exists('lang', $data)) {
            $lang = clean_param($data['lang'], PARAM_LANG);
            if ($lang !== '') {
                $usernew->lang = $lang;
            }
        }
        if (empty($locks['description']) && array_key_exists('description', $data)) {
            $usernew->description = clean_param((string)$data['description'], PARAM_TEXT);
            $usernew->descriptionformat = FORMAT_PLAIN;
        }

        $errors = [];
        if ($usernew->firstname === '') {
            $errors['firstname'] = get_string('required');
        }
        if ($usernew->lastname === '') {
            $errors['lastname'] = get_string('required');
        }
        if (!validate_email($usernew->email)) {
            $errors['email'] = get_string('invalidemail');
        } else if ($usernew->email !== $user->email && empty($CFG->allowaccountssameemail)) {
            $select = $DB->sql_equal('email', ':email', false) . ' AND mnethostid = :mnethostid AND id <> :userid';
            $params = [
                'email' => $usernew->email,
                'mnethostid' => $CFG->mnet_localhost_id,
                'userid' => $user->id,
            ];
            if ($DB->record_exists_select('user', $select, $params)) {
                $errors['email'] = get_string('emailexists');
            }
        }

        if ($usernew->country !== '') {
            $countries = get_string_manager()->get_list_of_countries(true);
            if (!isset($countries[$usernew->country])) {
                $errors['country'] = get_string('invaliddata', 'error');
            }
        }

        if (!in_array((int)$usernew->maildisplay, [0, 1, 2], true)) {
            $errors['maildisplay'] = get_string('invaliddata', 'error');
        }
        if (empty($locks['timezone']) && array_key_exists('timezone', $data) && (string)$usernew->timezone !== '99') {
            $timezones = \core_date::get_list_of_timezones();
            if (!isset($timezones[$usernew->timezone])) {
                $errors['timezone'] = get_string('invaliddata', 'error');
            }
        }
        if (empty($locks['lang']) && array_key_exists('lang', $data) && (string)$usernew->lang !== '') {
            $translations = get_string_manager()->get_list_of_translations();
            if (!isset($translations[$usernew->lang])) {
                $errors['lang'] = get_string('invaliddata', 'error');
            }
        }

        $errors += self::apply_custom_profile_submission($userid, $usernew, $data['customfields'] ?? []);
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => get_string('profileupdatefailed', 'local_moderncommerce'),
                'errors' => $errors,
            ];
        }

        $emailchanged = false;
        $emailchangedkey = '';
        if (
            !empty($CFG->emailchangeconfirmation) &&
            $usernew->email !== $user->email &&
            !has_capability('moodle/user:update', $systemcontext)
        ) {
            $validuntil = time() + 600;
            $emailchangedkey = create_user_key('core_user/email_change', $user->id, null, null, $validuntil);
            set_user_preference('newemail', $usernew->email, $user->id);
            set_user_preference('newemailattemptsleft', 3, $user->id);
            $emailchanged = $usernew->email;
            $usernew->email = $user->email;
        }

        $authplugin = get_auth_plugin($user->auth);
        $usernew->timemodified = time();
        if (!$authplugin->user_update($user, $usernew)) {
            throw new moodle_exception('cannotupdateprofile');
        }

        $transaction = $DB->start_delegated_transaction();
        user_update_user($usernew, false, false);
        useredit_update_bounces($user, $usernew);
        profile_save_data($usernew);
        \core\event\user_updated::create_from_userid($user->id)->trigger();
        $transaction->allow_commit();

        if (array_key_exists('interests', $data) && is_array($data['interests'])) {
            $interests = [];
            foreach ($data['interests'] as $interest) {
                $interest = trim(clean_param((string)$interest, PARAM_TEXT));
                if ($interest !== '') {
                    $interests[] = $interest;
                }
            }
            useredit_update_interests($usernew, $interests);
        }

        if ($emailchanged !== false) {
            self::send_email_change_confirmation($user, $emailchanged, $emailchangedkey);
        }

        $updateduser = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        foreach ((array)$updateduser as $variable => $value) {
            if ($variable === 'description' || $variable === 'password') {
                continue;
            }
            $USER->{$variable} = $value;
        }
        profile_load_custom_fields($USER);

        return [
            'success' => true,
            'message' => $emailchanged !== false
                ? get_string('profileemailconfirmation', 'local_moderncommerce', $emailchanged)
                : get_string('profilesaved', 'local_moderncommerce'),
            'profile' => self::get_profile_display_data($user->id),
        ];
    }

    /**
     * Save profile data submitted from a dashboard request.
     *
     * @return array Save result.
     */
    public static function save_profile_from_request(): array {
        global $CFG;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        $userid = required_param('userid', PARAM_INT);
        $customfields = [];
        foreach (profile_get_user_fields_with_data($userid) as $field) {
            if (!$field->is_editable()) {
                continue;
            }

            $customfields[$field->inputname] = $field->field->datatype === 'checkbox'
                ? optional_param($field->inputname, 0, PARAM_BOOL)
                : optional_param($field->inputname, '', PARAM_TEXT);
        }

        return self::save_profile_from_data([
            'userid' => $userid,
            'firstname' => optional_param('firstname', '', PARAM_NOTAGS),
            'lastname' => optional_param('lastname', '', PARAM_NOTAGS),
            'email' => optional_param('email', '', PARAM_EMAIL),
            'city' => optional_param('city', '', PARAM_TEXT),
            'country' => optional_param('country', '', PARAM_ALPHA),
            'department' => optional_param('department', '', PARAM_TEXT),
            'institution' => optional_param('institution', '', PARAM_TEXT),
            'customfields' => $customfields,
            'phone1' => optional_param('phone1', '', PARAM_NOTAGS),
            'phone2' => optional_param('phone2', '', PARAM_NOTAGS),
            'address' => optional_param('address', '', PARAM_TEXT),
            'idnumber' => optional_param('idnumber', '', PARAM_NOTAGS),
            'timezone' => optional_param('timezone', '99', PARAM_NOTAGS),
            'maildisplay' => optional_param('maildisplay', 2, PARAM_INT),
            'lang' => optional_param('lang', '', PARAM_LANG),
            'description' => optional_param('description', '', PARAM_TEXT),
        ]);
    }

    /**
     * Convert associative validation errors to an external-service friendly list.
     *
     * @param array $errors Errors keyed by field name.
     * @return array Error rows.
     */
    public static function format_external_errors(array $errors): array {
        $formatted = [];
        foreach ($errors as $name => $message) {
            $formatted[] = [
                // Field is the plugin-wide key; name is still emitted for any
                // consumer that has not been rebuilt yet. The React side
                // accepts either, so the alias can be dropped independently.
                'field' => (string)$name,
                'name' => (string)$name,
                'message' => (string)$message,
            ];
        }

        return $formatted;
    }

    /**
     * Check if a submitted custom profile value is empty.
     *
     * @param mixed $value Submitted value.
     * @param string $datatype Profile field datatype.
     * @return bool
     */
    private static function profile_value_is_empty($value, string $datatype): bool {
        if ($datatype === 'checkbox') {
            return empty($value);
        }

        if ($datatype === 'textarea' && is_array($value)) {
            return trim($value['text'] ?? '') === '';
        }

        return trim((string)$value) === '';
    }

    /**
     * Normalise a datetime-local or date custom field value for Moodle's profile field class.
     *
     * @param string $value Submitted field value.
     * @param bool $includetime Whether the field includes time.
     * @return string Normalised value.
     */
    private static function normalise_profile_datetime(string $value, bool $includetime): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if ($includetime) {
            $value = str_replace('T', '-', $value);
            $parts = explode('-', $value);
            if (count($parts) === 5) {
                $parts[] = '00';
            }
            return implode('-', $parts);
        }

        return $value;
    }

    /**
     * Return submitted values for editable custom profile fields.
     *
     * @param int $userid User id.
     * @param stdClass $usernew User object being updated.
     * @param array|null $submittedvalues Submitted custom field values keyed by input name.
     * @return array Validation errors.
     */
    private static function apply_custom_profile_submission(
        int $userid,
        stdClass $usernew,
        ?array $submittedvalues = null
    ): array {
        global $CFG;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        $errors = [];
        $canupdateusers = has_capability('moodle/user:update', context_system::instance());
        foreach (profile_get_user_fields_with_data($userid) as $field) {
            if (!$field->is_editable() || ($field->is_locked() && !$canupdateusers)) {
                continue;
            }

            $datatype = $field->field->datatype;
            if ($datatype === 'checkbox') {
                $value = $submittedvalues === null
                    ? optional_param($field->inputname, 0, PARAM_BOOL)
                    : clean_param($submittedvalues[$field->inputname] ?? 0, PARAM_BOOL);
            } else {
                $value = $submittedvalues === null
                    ? optional_param($field->inputname, '', PARAM_TEXT)
                    : clean_param($submittedvalues[$field->inputname] ?? '', PARAM_TEXT);
            }

            if ($datatype === 'textarea') {
                $value = [
                    'text' => $value,
                    'format' => FORMAT_PLAIN,
                ];
            } else if ($datatype === 'datetime') {
                $value = self::normalise_profile_datetime($value, !empty($field->field->param3));
            }

            if ($field->is_required() && self::profile_value_is_empty($value, $datatype)) {
                $errors[$field->inputname] = get_string('required');
            }

            $usernew->{$field->inputname} = $value;
        }

        $errors += profile_validation($usernew, []);

        return $errors;
    }

    /**
     * Send a Moodle email-change confirmation message.
     *
     * @param stdClass $user Existing user.
     * @param string $newemail New email address.
     * @param string $emailchangedkey Confirmation key.
     * @return void
     */
    private static function send_email_change_confirmation(
        stdClass $user,
        string $newemail,
        string $emailchangedkey
    ): void {
        global $CFG, $OUTPUT, $SITE;

        $tempuser = clone($user);
        $tempuser->email = $newemail;

        $a = new stdClass();
        $a->url = $CFG->wwwroot . '/user/emailupdate.php?key=' . $emailchangedkey . '&id=' . $user->id;
        $a->site = format_string($SITE->fullname, true, ['context' => context_course::instance(SITEID)]);

        foreach (core_user::get_name_placeholders($user) as $field => $value) {
            $a->{$field} = $value;
        }

        $a->supportemail = $OUTPUT->supportemail();

        $noreplyuser = core_user::get_noreply_user();
        email_to_user(
            $tempuser,
            $noreplyuser,
            get_string('emailupdatetitle', 'auth', $a),
            get_string('emailupdatemessage', 'auth', $a)
        );
    }
}

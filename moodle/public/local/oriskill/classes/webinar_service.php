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

namespace local_oriskill;

use moodle_url;

/**
 * Live webinars of a trainer on the BigBlueButton server of the site: a public sign-up page (no account needed, the person
 * goes into the CRM), a personal link to the room, reminders by e-mail, an offer shown during the live, the replay sent
 * afterwards.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webinar_service {

    /** Webinars and the people signed up. */
    const TABLE = 'local_oriskill_webinars';
    const REGS = 'local_oriskill_webregs';

    /** Reminders: field of the sign-up => seconds before the start (0: at the start). */
    const REMINDERS = ['remind24' => DAYSECS, 'remind1' => HOURSECS, 'remind0' => 0];

    /** A reminder later than this after its time is not sent any more. */
    const LATE = 1800;

    /** The room opens this long before the start. */
    const EARLY = 900;

    /**
     * Whether the site has a BigBlueButton server.
     *
     * @return bool
     */
    public static function available(): bool {
        global $CFG;
        return !empty($CFG->bigbluebuttonbn_server_url) && !empty($CFG->bigbluebuttonbn_shared_secret);
    }

    /**
     * Call the BigBlueButton API.
     *
     * @param string $call Call (create, join, getRecordings, isMeetingRunning).
     * @param array $params Parameters.
     * @param bool $urlonly Only build the address (join).
     * @return string|\SimpleXMLElement|null Address, answer, or null when unreachable.
     */
    public static function api(string $call, array $params, bool $urlonly = false) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $algorithm = strtolower($CFG->bigbluebuttonbn_checksum_algorithm ?? 'sha256') ?: 'sha256';
        $url = rtrim($CFG->bigbluebuttonbn_server_url, '/') . '/' . $call . '?' . $query . ($query !== '' ? '&' : '') .
            'checksum=' . hash(in_array($algorithm, ['sha1', 'sha256', 'sha384', 'sha512'], true) ? $algorithm : 'sha256',
                $call . $query . $CFG->bigbluebuttonbn_shared_secret);
        if ($urlonly) {
            return $url;
        }
        $curl = new \curl(['ignoresecurity' => true]);
        $answer = $curl->get($url, [], ['CURLOPT_TIMEOUT' => 15]);
        $xml = $answer ? @simplexml_load_string($answer) : false;
        return $xml ?: null;
    }

    /**
     * Webinars of a trainer, the next ones first.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function webinars(\stdClass $creator): array {
        global $DB;
        return $DB->get_records_sql("SELECT w.*, (SELECT COUNT(1) FROM {" . self::REGS . "} r WHERE r.webinarid = w.id) AS registered,
                                            (SELECT COUNT(1) FROM {" . self::REGS . "} r WHERE r.webinarid = w.id AND r.joined > 0) AS attended
                                       FROM {" . self::TABLE . "} w WHERE w.creatorid = ?
                                   ORDER BY CASE WHEN w.starttime + w.duration * 60 >= ? THEN 0 ELSE 1 END, w.starttime",
            [$creator->id, time()]);
    }

    /**
     * Create or change a webinar.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $data id, title, description, starttime, duration (minutes), offerurl, offerlabel, offerafter
     *                        (minutes after the start), replaydays
     * @return \stdClass
     */
    public static function save(\stdClass $creator, \stdClass $data): \stdClass {
        global $DB;

        $title = \core_text::substr(trim(clean_param((string) $data->title, PARAM_TEXT)), 0, 255);
        $offerurl = trim((string) ($data->offerurl ?? ''));
        if ($title === '' || (int) $data->starttime <= 0 || ($offerurl !== '' && !preg_match('~^https?://\S+$~i', $offerurl))) {
            throw new \moodle_exception('webinar:incomplete', 'local_oriskill');
        }
        $record = (object) ['creatorid' => $creator->id, 'title' => $title,
            'description' => \core_text::substr(trim(clean_param((string) ($data->description ?? ''), PARAM_TEXT)), 0, 5000),
            'starttime' => (int) $data->starttime, 'duration' => max(15, min(480, (int) ($data->duration ?? 60))),
            'offerurl' => $offerurl !== '' ? clean_param($offerurl, PARAM_URL) : '',
            'offerlabel' => \core_text::substr(trim(clean_param((string) ($data->offerlabel ?? ''), PARAM_TEXT)), 0, 100),
            'offerafter' => max(0, min(480, (int) ($data->offerafter ?? 45))), 'replaydays' => max(0, min(365, (int) ($data->replaydays ?? 7))),
            'timemodified' => time()];
        if (!empty($data->id)) {
            $existing = $DB->get_record(self::TABLE, ['id' => $data->id, 'creatorid' => $creator->id], '*', MUST_EXIST);
            $record->id = $existing->id;
            $DB->update_record(self::TABLE, $record);
            if ((int) $existing->starttime !== $record->starttime) {
                // A new date: the reminders start again.
                $DB->execute('UPDATE {' . self::REGS . '} SET remind24 = 0, remind1 = 0, remind0 = 0 WHERE webinarid = ?', [$record->id]);
            }
        } else {
            $record->slug = random_string(12);
            $record->moderatorpw = random_string(16);
            $record->attendeepw = random_string(16);
            $record->timecreated = time();
            $record->id = $DB->insert_record(self::TABLE, $record);
        }
        log_service::add($creator, 'webinar_saved', ['title' => $title]);
        return $DB->get_record(self::TABLE, ['id' => $record->id]);
    }

    /**
     * Public sign-up page of a webinar.
     *
     * @param \stdClass $webinar Webinar.
     * @return moodle_url
     */
    public static function public_url(\stdClass $webinar): moodle_url {
        return new moodle_url('/local/oriskill/webinar.php', ['w' => $webinar->slug]);
    }

    /**
     * Personal page of a person signed up (the room, the offer, the replay).
     *
     * @param \stdClass $reg Sign-up.
     * @return moodle_url
     */
    public static function room_url(\stdClass $reg): moodle_url {
        return new moodle_url('/local/oriskill/webinar.php', ['t' => $reg->token]);
    }

    /**
     * Sign someone up: CRM contact with the webinar's tag, consents, confirmation e-mail. Signing up twice gives the same link.
     *
     * @param \stdClass $webinar Webinar.
     * @param string $email E-mail address.
     * @param string $firstname First name.
     * @param array $consents direct, partners (ticked boxes)
     * @return \stdClass Sign-up.
     */
    public static function register(\stdClass $webinar, string $email, string $firstname, array $consents = []): \stdClass {
        global $DB;

        $email = \core_text::strtolower(trim($email));
        $firstname = \core_text::substr(trim(clean_param($firstname, PARAM_TEXT)), 0, 100);
        if (!validate_email($email) || $firstname === '') {
            throw new \moodle_exception('webinar:badsignup', 'local_oriskill');
        }
        if ($reg = $DB->get_record(self::REGS, ['webinarid' => $webinar->id, 'email' => $email])) {
            return $reg;
        }
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $webinar->creatorid], '*', MUST_EXIST);
        $contact = contact_service::upsert($creator, $email, ['firstname' => $firstname,
            'consentdirect' => !empty($consents['direct']), 'consentpartners' => !empty($consents['partners'])], 'capture');
        contact_service::add_tags($creator, [(int) $contact->id], [get_string('webinar:tag', 'local_oriskill', $webinar->title)]);
        $userid = (int) ($DB->get_field_select('user', 'id', 'LOWER(email) = ? AND deleted = 0', [$email], IGNORE_MULTIPLE) ?: 0);
        $reg = (object) ['webinarid' => $webinar->id, 'contactid' => $contact->id, 'userid' => $userid, 'email' => $email,
            'firstname' => $firstname, 'token' => random_string(32), 'remind24' => 0, 'remind1' => 0, 'remind0' => 0,
            'replaysent' => 0, 'joined' => 0, 'timecreated' => time()];
        $reg->id = $DB->insert_record(self::REGS, $reg);
        self::mail($creator, $webinar, $reg, 'confirm');
        webhook_service::dispatch($creator, 'lead.created', ['lead' => ['email' => $email, 'firstname' => $firstname,
            'webinar' => $webinar->title]]);
        return $reg;
    }

    /**
     * Whether the room is open (from EARLY before the start to the end).
     *
     * @param \stdClass $webinar Webinar.
     * @param int|null $now Time.
     * @return bool
     */
    public static function is_open(\stdClass $webinar, ?int $now = null): bool {
        $now = $now ?? time();
        return $now >= $webinar->starttime - self::EARLY && $now <= $webinar->starttime + $webinar->duration * 60;
    }

    /**
     * Address that takes a person into the room: the meeting is created first (it can be created again safely).
     *
     * @param \stdClass $webinar Webinar.
     * @param string $name Name shown in the room.
     * @param bool $host The trainer or their team (moderator).
     * @return string
     */
    public static function join_url(\stdClass $webinar, string $name, bool $host): string {
        global $DB;

        $creator = $DB->get_record('local_oriskill_creators', ['id' => $webinar->creatorid], '*', MUST_EXIST);
        $created = self::api('create', ['name' => $webinar->title, 'meetingID' => 'oriskill-webinar-' . $webinar->id,
            'attendeePW' => $webinar->attendeepw, 'moderatorPW' => $webinar->moderatorpw, 'record' => 'true',
            'autoStartRecording' => 'true', 'allowStartStopRecording' => 'true',
            'duration' => (int) $webinar->duration + 60, 'welcome' => format_string($creator->displayname),
            'logoutURL' => self::public_url($webinar)->out(false)]);
        if (!$created || (string) $created->returncode !== 'SUCCESS') {
            throw new \moodle_exception('webinar:serverdown', 'local_oriskill');
        }
        return self::api('join', ['fullName' => \core_text::substr($name, 0, 100), 'meetingID' => 'oriskill-webinar-' . $webinar->id,
            'password' => $host ? $webinar->moderatorpw : $webinar->attendeepw, 'redirect' => 'true'], true);
    }

    /**
     * Address of the recording of a webinar, once BigBlueButton has made it.
     *
     * @param \stdClass $webinar Webinar.
     * @return string Empty when there is none (yet).
     */
    public static function replay_url(\stdClass $webinar): string {
        if (!empty($webinar->replayurl)) {
            return $webinar->replayurl;
        }
        $answer = self::api('getRecordings', ['meetingID' => 'oriskill-webinar-' . $webinar->id]);
        $url = '';
        foreach ($answer->recordings->recording ?? [] as $recording) {
            foreach ($recording->playback->format ?? [] as $format) {
                if ((string) $format->type === 'presentation' || $url === '') {
                    $url = (string) $format->url;
                }
            }
        }
        if ($url !== '') {
            global $DB;
            $DB->set_field(self::TABLE, 'replayurl', $url, ['id' => $webinar->id]);
        }
        return $url;
    }

    /**
     * Reminders and replays due. Run by a scheduled task.
     *
     * @param int|null $now Time (tests).
     * @return int E-mails sent.
     */
    public static function send_due(?int $now = null): int {
        global $DB;

        $now = $now ?? time();
        $sent = 0;
        $webinars = $DB->get_records_select(self::TABLE, 'starttime > ? AND starttime < ?',
            [$now - 400 * DAYSECS, $now + DAYSECS + self::LATE]);
        foreach ($webinars as $webinar) {
            $creator = $DB->get_record('local_oriskill_creators', ['id' => $webinar->creatorid]);
            if (!$creator) {
                continue;
            }
            foreach (self::REMINDERS as $field => $before) {
                $at = $webinar->starttime - $before;
                if ($now < $at || $now > $at + self::LATE) {
                    continue;
                }
                foreach ($DB->get_records(self::REGS, ['webinarid' => $webinar->id, $field => 0]) as $reg) {
                    $DB->set_field(self::REGS, $field, $now, ['id' => $reg->id]);
                    $sent += (int) self::mail($creator, $webinar, $reg, $field);
                }
            }
            // After the live: the replay, while it can be watched, to everyone signed up.
            $end = $webinar->starttime + $webinar->duration * 60;
            if ($webinar->replaydays > 0 && $now > $end && $now < $end + $webinar->replaydays * DAYSECS
                    && $DB->record_exists(self::REGS, ['webinarid' => $webinar->id, 'replaysent' => 0])
                    && self::replay_url($webinar) !== '') {
                foreach ($DB->get_records(self::REGS, ['webinarid' => $webinar->id, 'replaysent' => 0]) as $reg) {
                    $DB->set_field(self::REGS, 'replaysent', $now, ['id' => $reg->id]);
                    $sent += (int) self::mail($creator, $webinar, $reg, 'replay');
                }
            }
        }
        return $sent;
    }

    /**
     * Send an e-mail about the webinar to a person signed up.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $webinar Webinar.
     * @param \stdClass $reg Sign-up.
     * @param string $kind confirm, remind24, remind1, remind0 or replay.
     * @return bool Sent.
     */
    protected static function mail(\stdClass $creator, \stdClass $webinar, \stdClass $reg, string $kind): bool {
        $user = \core_user::get_noreply_user();
        $user->id = -1;
        $user->email = $reg->email;
        $user->firstname = $reg->firstname;
        $user->lastname = '';
        $user->mailformat = 1;
        $from = \core_user::get_noreply_user();
        $from->firstname = format_string($creator->displayname);
        $from->lastname = '';
        $a = (object) ['firstname' => $reg->firstname, 'title' => $webinar->title, 'trainer' => format_string($creator->displayname),
            'date' => userdate($webinar->starttime, get_string('strftimedaydatetime', 'langconfig'),
                stats_service::timezone($creator)),
            'url' => self::room_url($reg)->out(false), 'days' => (int) $webinar->replaydays];
        $text = get_string('webinar:mail_' . $kind . '_body', 'local_oriskill', $a);
        $html = preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', text_to_html(s($text), false, false, true));
        try {
            return (bool) email_log::for_creator((int) $creator->id, 'marketing', fn() => email_to_user($user, $from,
                get_string('webinar:mail_' . $kind . '_subject', 'local_oriskill', $a), $text, $html));
        } catch (\Throwable $e) {
            debugging('Webinar e-mail: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
}

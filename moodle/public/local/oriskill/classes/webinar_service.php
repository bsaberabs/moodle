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

    /** Where a webinar is broadcast: a BigBlueButton room, a YouTube live, or a recorded video played as a live (auto). */
    const PLATFORMS = ['bbb', 'youtube', 'auto'];

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
                                   ORDER BY CASE WHEN w.platform = 'auto' OR w.starttime + w.duration * 60 >= ? THEN 0 ELSE 1 END,
                                            w.starttime",
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
        if ($title === '' || ((int) $data->starttime <= 0 && ($data->platform ?? '') !== 'auto') || ($offerurl !== '' && !preg_match('~^https?://\S+$~i', $offerurl))) {
            throw new \moodle_exception('webinar:incomplete', 'local_oriskill');
        }
        $platform = in_array($data->platform ?? 'bbb', self::PLATFORMS, true) ? $data->platform ?? 'bbb' : 'bbb';
        $streamurl = trim((string) ($data->streamurl ?? ''));
        if (($platform === 'youtube' && self::youtube_id($streamurl) === '')
                || ($platform === 'auto' && self::youtube_id($streamurl) === '' && !preg_match('~^https://\S+$~i', $streamurl))) {
            throw new \moodle_exception('webinar:badstream', 'local_oriskill');
        }
        $schedule = null;
        if ($platform === 'auto') {
            preg_match_all('~\b([01]?\d|2[0-3])\s*[:hH]\s*([0-5]\d)?\b~', (string) ($data->times ?? ''), $found, PREG_SET_ORDER);
            $times = array_values(array_unique(array_map(fn($t) => sprintf('%02d:%02d', $t[1], $t[2] ?? 0), $found)));
            sort($times);
            $days = array_values(array_unique(array_filter(array_map('intval', (array) ($data->days ?? [])),
                fn($d) => $d >= 0 && $d <= 6)));
            sort($days);
            if (!$times && empty($data->jit)) {
                throw new \moodle_exception('webinar:noschedule', 'local_oriskill');
            }
            $schedule = json_encode(['days' => $days, 'times' => $times, 'jit' => !empty($data->jit)]);
        }
        $record = (object) ['creatorid' => $creator->id, 'title' => $title,
            'description' => \core_text::substr(trim(clean_param((string) ($data->description ?? ''), PARAM_TEXT)), 0, 5000),
            'starttime' => (int) $data->starttime, 'duration' => max(15, min(480, (int) ($data->duration ?? 60))),
            'offerurl' => $offerurl !== '' ? clean_param($offerurl, PARAM_URL) : '',
            'offerlabel' => \core_text::substr(trim(clean_param((string) ($data->offerlabel ?? ''), PARAM_TEXT)), 0, 100),
            'offerafter' => max(0, min(480, (int) ($data->offerafter ?? 45))), 'replaydays' => max(0, min(365, (int) ($data->replaydays ?? 7))),
            'platform' => $platform, 'streamurl' => $platform !== 'bbb' ? clean_param($streamurl, PARAM_URL) : '',
            'schedule' => $schedule, 'timemodified' => time()];
        if ($platform === 'auto') {
            // No date of its own: the sessions are worked out from the schedule.
            $record->starttime = (int) ($DB->get_field(self::TABLE, 'starttime', ['id' => (int) ($data->id ?? 0)]) ?: time());
        }
        if (!empty($data->id)) {
            $existing = $DB->get_record(self::TABLE, ['id' => $data->id, 'creatorid' => $creator->id], '*', MUST_EXIST);
            $record->id = $existing->id;
            $DB->update_record(self::TABLE, $record);
            if ((int) $existing->starttime !== $record->starttime && $platform !== 'auto') {
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
    public static function register(\stdClass $webinar, string $email, string $firstname, array $consents = [],
            int $session = 0): \stdClass {
        global $DB;

        $email = \core_text::strtolower(trim($email));
        $firstname = \core_text::substr(trim(clean_param($firstname, PARAM_TEXT)), 0, 100);
        if (!validate_email($email) || $firstname === '') {
            throw new \moodle_exception('webinar:badsignup', 'local_oriskill');
        }
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $webinar->creatorid], '*', MUST_EXIST);
        if ($webinar->platform === 'auto') {
            $sessions = self::sessions($webinar, null, 50);
            $session = $session ?: (int) reset($sessions);
            if (!in_array($session, $sessions, true)) {
                throw new \moodle_exception('webinar:nosession', 'local_oriskill');
            }
        }
        if ($reg = $DB->get_record(self::REGS, ['webinarid' => $webinar->id, 'email' => $email])) {
            if ($webinar->platform === 'auto' && (int) $reg->sessiontime !== $session) {
                // Signed up again for another session: the reminders start again for that one.
                foreach (['sessiontime' => $session, 'remind24' => 0, 'remind1' => 0, 'remind0' => 0, 'replaysent' => 0] as $f => $v) {
                    $reg->$f = $v;
                }
                $DB->update_record(self::REGS, $reg);
                self::mail($creator, self::for_reg($webinar, $reg), $reg, 'confirm');
            }
            return $reg;
        }
        $contact = contact_service::upsert($creator, $email, ['firstname' => $firstname,
            'consentdirect' => !empty($consents['direct']), 'consentpartners' => !empty($consents['partners'])], 'capture');
        contact_service::add_tags($creator, [(int) $contact->id], [get_string('webinar:tag', 'local_oriskill', $webinar->title)]);
        $userid = (int) ($DB->get_field_select('user', 'id', 'LOWER(email) = ? AND deleted = 0', [$email], IGNORE_MULTIPLE) ?: 0);
        $reg = (object) ['webinarid' => $webinar->id, 'contactid' => $contact->id, 'userid' => $userid, 'email' => $email,
            'firstname' => $firstname, 'token' => random_string(32), 'remind24' => 0, 'remind1' => 0, 'remind0' => 0,
            'replaysent' => 0, 'joined' => 0, 'sessiontime' => $webinar->platform === 'auto' ? $session : 0, 'timecreated' => time()];
        $reg->id = $DB->insert_record(self::REGS, $reg);
        self::mail($creator, self::for_reg($webinar, $reg), $reg, 'confirm');
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
        if (($webinar->platform ?? 'bbb') !== 'bbb') {
            // YouTube keeps the live as a video at the same address; an auto-webinar replays its own video.
            $id = self::youtube_id((string) $webinar->streamurl);
            return $id !== '' ? 'https://www.youtube.com/watch?v=' . $id : (string) $webinar->streamurl;
        }
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
     * Id of the video in a YouTube link (watch, live, embed, shorts or youtu.be), or '' when it is not one.
     *
     * @param string $url Link.
     * @return string Id of 11 characters.
     */
    public static function youtube_id(string $url): string {
        return preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:\S*&)?v=|live/|embed/|shorts/)|youtu\.be/)' .
            '([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])~i', trim($url), $m) ? $m[1] : '';
    }

    /**
     * The YouTube player of the webinar, with the live chat beside it during the live.
     *
     * @param \stdClass $webinar Webinar on YouTube.
     * @param bool $chat Show the live chat.
     * @param string $host Host of the page (YouTube only shows its chat on the domain named).
     * @return string HTML.
     */
    public static function youtube_embed(\stdClass $webinar, bool $chat, string $host): string {
        $id = self::youtube_id((string) $webinar->streamurl);
        $player = \html_writer::div(\html_writer::tag('iframe', '', ['src' => 'https://www.youtube-nocookie.com/embed/' . $id .
            '?autoplay=1&rel=0&modestbranding=1', 'title' => format_string($webinar->title), 'class' => 'border-0 rounded',
            'allow' => 'autoplay; encrypted-media; picture-in-picture; fullscreen', 'allowfullscreen' => 'allowfullscreen']),
            'ratio ratio-16x9');
        if (!$chat) {
            return $player;
        }
        return \html_writer::div(\html_writer::div($player, 'col-lg-8') . \html_writer::div(\html_writer::tag('iframe', '', [
            'src' => 'https://www.youtube.com/live_chat?v=' . $id . '&embed_domain=' . rawurlencode($host),
            'title' => get_string('webinar:chat', 'local_oriskill'), 'class' => 'w-100 border rounded',
            'style' => 'height: 100%; min-height: 24rem']), 'col-lg-4'), 'row g-3');
    }

    /**
     * The webinar as one person lives it: an auto-webinar starts at the session they chose.
     *
     * @param \stdClass $webinar Webinar.
     * @param \stdClass $reg Sign-up.
     * @return \stdClass Webinar, its start moved to the session for an auto-webinar.
     */
    public static function for_reg(\stdClass $webinar, \stdClass $reg): \stdClass {
        if ($webinar->platform !== 'auto' || empty($reg->sessiontime)) {
            return $webinar;
        }
        $copy = clone $webinar;
        $copy->starttime = (int) $reg->sessiontime;
        return $copy;
    }

    /**
     * Next sessions of an auto-webinar, in the trainer's time zone.
     *
     * @param \stdClass $webinar Auto-webinar.
     * @param int|null $now Time to use (tests).
     * @param int $count Sessions wanted.
     * @return int[] Start times, soonest first.
     */
    public static function sessions(\stdClass $webinar, ?int $now = null, int $count = 6): array {
        global $DB;

        $now = $now ?? time();
        $schedule = json_decode((string) ($webinar->schedule ?? ''), true) ?: [];
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $webinar->creatorid]);
        $zone = \core_date::get_user_timezone_object(stats_service::timezone($creator));
        $days = array_map('intval', $schedule['days'] ?? []);
        $list = [];
        if (!empty($schedule['jit'])) {
            // Just in time: the next quarter of an hour, at least 5 minutes away.
            $list[] = (int) (ceil(($now + 5 * MINSECS) / (15 * MINSECS)) * 15 * MINSECS);
        }
        for ($d = 0; $d < 15; $d++) {
            $day = (new \DateTime('@' . ($now + $d * DAYSECS)))->setTimezone($zone);
            if ($days && !in_array((int) $day->format('w'), $days, true)) {
                continue;
            }
            foreach ($schedule['times'] ?? [] as $time) {
                [$hour, $minute] = array_map('intval', explode(':', $time));
                $at = (clone $day)->setTime($hour, $minute)->getTimestamp();
                if ($at > $now + MINSECS) {
                    $list[] = $at;
                }
            }
        }
        $list = array_values(array_unique($list));
        sort($list);
        return array_slice($list, 0, $count);
    }

    /**
     * The sessions of an auto-webinar in a few words: days and times.
     *
     * @param \stdClass $webinar Auto-webinar.
     * @return string Summary.
     */
    public static function schedule_summary(\stdClass $webinar): string {
        $schedule = json_decode((string) ($webinar->schedule ?? ''), true) ?: [];
        $weekdays = \core_calendar\type_factory::get_calendar_instance()->get_weekdays();
        $parts = [$schedule['days'] ?? [] ? implode(', ', array_map(fn($d) => $weekdays[$d]['shortname'], $schedule['days']))
            : get_string('webinar:everyday', 'local_oriskill')];
        if (!empty($schedule['times'])) {
            $parts[] = implode(', ', $schedule['times']);
        }
        if (!empty($schedule['jit'])) {
            $parts[] = get_string('webinar:jitshort', 'local_oriskill');
        }
        return implode(' · ', $parts);
    }

    /**
     * The player of an auto-webinar during its session: a button that starts the video where the session is now.
     *
     * @param \stdClass $webinar Auto-webinar, its start moved to the session.
     * @return string HTML.
     */
    public static function auto_player(\stdClass $webinar): string {
        $id = self::youtube_id((string) $webinar->streamurl);
        return \html_writer::div(\html_writer::div(\html_writer::tag('button', '<i class="fa fa-play me-2" aria-hidden="true"></i>' .
            get_string('webinar:launch', 'local_oriskill'), ['type' => 'button', 'class' => 'btn btn-primary btn-lg',
            'id' => 'auto-launch']), 'd-flex align-items-center justify-content-center bg-dark rounded'),
            'ratio ratio-16x9', ['id' => 'auto-player', 'data-youtube' => $id, 'data-src' => $id === '' ? $webinar->streamurl : '']);
    }

    /**
     * The video to watch again: the YouTube player, or the recorded video of an auto-webinar.
     *
     * @param \stdClass $webinar Webinar on YouTube or auto-webinar.
     * @param string $host Host of the page.
     * @return string HTML.
     */
    public static function video_embed(\stdClass $webinar, string $host): string {
        if (self::youtube_id((string) $webinar->streamurl) !== '') {
            return self::youtube_embed($webinar, false, $host);
        }
        return \html_writer::div(\html_writer::tag('video', '', ['src' => $webinar->streamurl, 'controls' => 'controls',
            'playsinline' => 'playsinline', 'class' => 'rounded bg-dark']), 'ratio ratio-16x9');
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
        $webinars = $DB->get_records_select(self::TABLE, "platform = 'auto' OR (starttime > ? AND starttime < ?)",
            [$now - 400 * DAYSECS, $now + DAYSECS + self::LATE]);
        foreach ($webinars as $webinar) {
            $creator = $DB->get_record('local_oriskill_creators', ['id' => $webinar->creatorid]);
            if (!$creator) {
                continue;
            }
            $replay = null;
            foreach ($DB->get_records_select(self::REGS, 'webinarid = ? AND (remind0 = 0 OR replaysent = 0)', [$webinar->id]) as $reg) {
                // An auto-webinar runs at the session each person chose.
                $w = self::for_reg($webinar, $reg);
                foreach (self::REMINDERS as $field => $before) {
                    $at = $w->starttime - $before;
                    if ($reg->$field || $now < $at || $now > $at + self::LATE) {
                        continue;
                    }
                    $DB->set_field(self::REGS, $field, $now, ['id' => $reg->id]);
                    $sent += (int) self::mail($creator, $w, $reg, $field);
                }
                // After the live: the replay, while it can be watched (asked to the server once per webinar).
                $end = $w->starttime + $w->duration * 60;
                if (!$reg->replaysent && $w->replaydays > 0 && $now > $end && $now < $end + $w->replaydays * DAYSECS
                        && ($replay ??= self::replay_url($w)) !== '') {
                    $DB->set_field(self::REGS, 'replaysent', $now, ['id' => $reg->id]);
                    $sent += (int) self::mail($creator, $w, $reg, 'replay');
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

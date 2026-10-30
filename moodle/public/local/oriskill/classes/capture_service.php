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
 * Capture pages: a trainer offers free courses in exchange for an e-mail address. The visitor gets an account (or
 * their existing one), the courses open, and they can be sent on to one of the trainer's order forms. Sign-ups go to
 * the trainer's ActiveCampaign account and webhooks when they agree to receive the trainer's e-mails.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capture_service {

    /** Tables. */
    const TABLE = 'local_oriskill_capturepages';
    const LEADS = 'local_oriskill_leads';

    /** Sign-ups allowed per browser session, against robots filling the form again and again. */
    const SESSION_LIMIT = 5;

    /** Image options. */
    const IMAGE_OPTIONS = ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 5242880,
        'accepted_types' => ['.png', '.jpg', '.jpeg', '.webp']];

    /**
     * Capture pages of a trainer, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function pages(\stdClass $creator): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['creatorid' => $creator->id], 'timecreated DESC, id DESC');
    }

    /**
     * Save a page (new when $data->id is empty).
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $data Form fields.
     * @return \stdClass
     */
    public static function save(\stdClass $creator, \stdClass $data): \stdClass {
        global $DB;

        $own = array_keys(seller_service::priced_courses($creator));
        $formids = array_keys(orderform_service::forms($creator));
        $now = time();
        $record = (object) [
            'creatorid' => $creator->id,
            'slug' => $data->slug,
            'title' => trim($data->title),
            'headline' => trim((string) $data->headline),
            'description' => $data->description,
            'descriptionformat' => $data->descriptionformat ?? FORMAT_HTML,
            'buttonlabel' => trim((string) $data->buttonlabel),
            'courseids' => implode(',', array_intersect(array_map('intval', (array) $data->courseids), $own)),
            'accessdays' => max(0, (int) $data->accessdays),
            'thankyou' => trim((string) $data->thankyou),
            'redirectformid' => in_array((int) $data->redirectformid, $formids, true) ? (int) $data->redirectformid : null,
            'actags' => activecampaign_service::clean_tags((string) $data->actags),
            'status' => $data->status === 'published' ? 'published' : 'draft',
            'timemodified' => $now,
        ];
        if (!empty($data->id)) {
            $record->id = $DB->get_field(self::TABLE, 'id', ['id' => $data->id, 'creatorid' => $creator->id], MUST_EXIST);
            $DB->update_record(self::TABLE, $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record(self::TABLE, $record);
        }
        return $DB->get_record(self::TABLE, ['id' => $record->id]);
    }

    /**
     * Why an address part cannot be used, or null if it can.
     *
     * @param string $slug Address part.
     * @param \stdClass $creator Trainer.
     * @param int $pageid The page it is for (0 for a new one).
     * @return string|null Language string key.
     */
    public static function slug_error(string $slug, \stdClass $creator, int $pageid): ?string {
        global $DB;

        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) < 3 || strlen($slug) > 60) {
            return 'error:formslug';
        }
        if ($DB->record_exists_select(self::TABLE, 'creatorid = ? AND slug = ? AND id <> ?', [$creator->id, $slug, $pageid])) {
            return 'error:formslugtaken';
        }
        return null;
    }

    /**
     * Published page a visitor asked for: by id, or by the trainer's own domain and the page's address.
     *
     * @param int $id Page id (0 when found by domain).
     * @param string $domain Trainer's domain.
     * @param string $slug Address part.
     * @return array|null [page, trainer]
     */
    public static function public_page(int $id, string $domain = '', string $slug = ''): ?array {
        global $DB;

        if ($id) {
            $page = $DB->get_record(self::TABLE, ['id' => $id, 'status' => 'published']);
            $creator = $page ? $DB->get_record('local_oriskill_creators', ['id' => $page->creatorid, 'status' => 'active']) : null;
        } else {
            $creator = creator_service::creator_by_domain($domain);
            $page = $creator ? $DB->get_record(self::TABLE, ['creatorid' => $creator->id, 'slug' => $slug,
                'status' => 'published']) : null;
        }
        return $page && $creator ? [$page, $creator] : null;
    }

    /**
     * Public address of a page: on the trainer's own domain when they have one, else on Oriskill.
     *
     * @param \stdClass $page Page.
     * @param \stdClass $creator Trainer.
     * @return moodle_url
     */
    public static function url(\stdClass $page, \stdClass $creator): moodle_url {
        if (!empty($creator->customdomain)) {
            return new moodle_url('https://' . $creator->customdomain . '/p/' . $page->slug);
        }
        return new moodle_url('/local/oriskill/capture.php', ['id' => $page->id]);
    }

    /**
     * Count a visitor of a page (once a day per visitor; robots and the trainer's team left out).
     *
     * @param \stdClass $page Page.
     * @return bool Whether it was counted.
     */
    public static function track_view(\stdClass $page): bool {
        global $DB, $SESSION, $USER;

        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($agent === '' || preg_match(formstats_service::ROBOTS, $agent)) {
            return false;
        }
        if (isloggedin() && !isguestuser()) {
            $creator = $DB->get_record('local_oriskill_creators', ['id' => $page->creatorid]);
            if ($creator && ((int) $creator->userid === (int) $USER->id
                    || in_array((int) $USER->id, team_service::member_userids($creator), true))) {
                return false;
            }
        }
        $day = (int) date('Ymd');
        $seen = $SESSION->local_oriskill_captureseen ?? [];
        if (($seen[$page->id] ?? 0) === $day) {
            return false;
        }
        $seen[$page->id] = $day;
        $SESSION->local_oriskill_captureseen = $seen;
        $DB->execute('UPDATE {' . self::TABLE . '} SET views = views + 1 WHERE id = ?', [$page->id]);
        return true;
    }

    /**
     * A visitor signs up: account (new or existing), free courses, lead, then ActiveCampaign and webhooks.
     *
     * @param \stdClass $page Published page.
     * @param string $firstname First name.
     * @param string $lastname Last name.
     * @param string $email E-mail address.
     * @param bool $consent Agrees to receive the trainer's e-mails.
     * @return array [user, bool account created]
     */
    public static function signup(\stdClass $page, string $firstname, string $lastname, string $email, bool $consent): array {
        global $DB, $SESSION;

        $count = (int) ($SESSION->local_oriskill_capturecount ?? 0);
        if ($count >= self::SESSION_LIMIT) {
            throw new \moodle_exception('error:capturelimit', 'local_oriskill');
        }
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $page->creatorid, 'status' => 'active'], '*', MUST_EXIST);
        if ($page->status !== 'published') {
            throw new \moodle_exception('error:formunavailable', 'local_oriskill');
        }
        $timeend = $page->accessdays ? time() + (int) $page->accessdays * DAYSECS : 0;
        $user = null;
        $created = false;
        foreach (array_filter(array_map('intval', explode(',', (string) $page->courseids))) as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course) {
                continue;
            }
            // Someone with access already (paid, offered) keeps it as it is.
            $existing = $user ?? self::user_by_email($email);
            if ($existing && is_enrolled(\context_course::instance($courseid), $existing, '', true)) {
                $user = $existing;
                continue;
            }
            [$user, $new] = learning_service::offer_access($course, $email, $firstname, $lastname, $timeend, 0);
            $created = $created || $new;
        }
        if (!$user) {
            // A page without course: the account alone (the lead is still recorded).
            $user = self::user_by_email($email);
            if (!$user) {
                throw new \moodle_exception('error:formunavailable', 'local_oriskill');
            }
        }
        $SESSION->local_oriskill_capturecount = $count + 1;

        if (!$DB->record_exists(self::LEADS, ['pageid' => $page->id, 'userid' => $user->id])) {
            $DB->insert_record(self::LEADS, (object) ['pageid' => $page->id, 'creatorid' => $creator->id, 'userid' => $user->id,
                'email' => $user->email, 'firstname' => $user->firstname, 'consent' => $consent ? 1 : 0, 'timecreated' => time()]);
        }
        contact_service::from_user($creator, (int) $user->id, 'capture', ['consentdirect' => $consent]);
        if ($consent) {
            $account = activecampaign_service::account($creator);
            if ($account && $account->enabled) {
                $tags = array_merge($account->tags ? explode(',', $account->tags) : [],
                    $page->actags ? explode(',', $page->actags) : []);
                activecampaign_service::queue($creator, (int) $user->id, array_values(array_unique($tags)), true);
            }
            webhook_service::dispatch($creator, 'lead.created', [
                'trainer' => ['id' => (int) $creator->id, 'name' => format_string($creator->displayname)],
                'lead' => ['id' => (int) $user->id, 'email' => $user->email, 'firstname' => $user->firstname,
                    'lastname' => $user->lastname],
                'page' => ['id' => (int) $page->id, 'title' => format_string($page->title)],
            ]);
        }
        return [$user, $created];
    }

    /**
     * Existing account of an e-mail address.
     *
     * @param string $email E-mail address.
     * @return \stdClass|null
     */
    private static function user_by_email(string $email): ?\stdClass {
        global $CFG, $DB;
        return $DB->get_record_select('user', 'LOWER(email) = ? AND deleted = 0 AND mnethostid = ?',
            [\core_text::strtolower(trim($email)), $CFG->mnet_localhost_id], '*', IGNORE_MULTIPLE) ?: null;
    }

    /**
     * Sign-ups of a page, newest first.
     *
     * @param \stdClass $page Page.
     * @return array
     */
    public static function leads(\stdClass $page): array {
        global $DB;
        return $DB->get_records(self::LEADS, ['pageid' => $page->id], 'timecreated DESC, id DESC');
    }

    /**
     * Number of sign-ups of each of a trainer's pages.
     *
     * @param \stdClass $creator Trainer.
     * @return array pageid => count
     */
    public static function lead_counts(\stdClass $creator): array {
        global $DB;
        return $DB->get_records_sql_menu('SELECT pageid, COUNT(1) FROM {' . self::LEADS . '} WHERE creatorid = ? GROUP BY pageid',
            [$creator->id]);
    }
}

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

use context_coursecat;
use context_system;
use core_course_category;
use core_user;
use local_moderncommerce\services\price_resolver;
use local_moderncommerce\services\pricing_service;
use moodle_exception;
use moodle_url;

/**
 * Trainer spaces: application, approval, suspension and showcase content.
 *
 * An approved trainer gets a course category of their own under "Formateurs" and Moodle's
 * course creator role on it, so they create and teach their own courses there and nowhere else.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class creator_service {

    /** Table. */
    private const TABLE = 'local_oriskill_creators';

    /** Parent category of all trainer categories. */
    private const PARENT_IDNUMBER = 'oriskill-creators';

    /**
     * Slugs kept for the platform. Showcases live at the site root (/slug), so every top-level Moodle
     * file or folder is refused as well (see slug_error()), along with the router's prefixes.
     */
    private const RESERVED = ['www', 'admin', 'api', 'app', 'mail', 'smtp', 'ftp', 'support', 'aide', 'help', 'blog',
        'shop', 'boutique', 'formation', 'formations', 'formateur', 'formateurs', 'oriskill', 'login', 'compte',
        'static', 'cdn', 'test', 'demo', 'dev', 'staging', 'r', 'rest', 'shim', 'swagger', 'cms', 'oauth2',
        'sections', 'create', 'f', 'healthz', 'robots', 'sitemap', 'favicon', 'contact', 'cgv', 'mentions-legales',
        'connexion', 'inscription', 'panier', 'paiement', 'abonnement', 'abonnements', 'catalogue'];

    /**
     * Whether a slug can be used: lowercase letters, digits and single hyphens, 3 to 30 characters.
     *
     * @param string $slug Slug.
     * @param int $exceptid Creator allowed to already own it.
     * @return string|null Error string key, or null if valid.
     */
    public static function slug_error(string $slug, int $exceptid = 0): ?string {
        global $CFG, $DB;

        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) < 3 || strlen($slug) > 30) {
            return 'error:slugshape';
        }
        if (in_array($slug, self::RESERVED, true) || file_exists("$CFG->dirroot/$slug")
                || file_exists("$CFG->dirroot/$slug.php")) {
            return 'error:slugreserved';
        }
        if ($DB->record_exists_select(self::TABLE, 'slug = ? AND id <> ?', [$slug, $exceptid])) {
            return 'error:slugtaken';
        }
        return null;
    }

    /**
     * Record an application.
     *
     * @param int $userid Applicant.
     * @param \stdClass $data Form data (displayname, slug, tagline, bio_editor, motivation).
     * @return int Creator id.
     */
    public static function apply(int $userid, \stdClass $data): int {
        global $DB;

        if ($DB->record_exists(self::TABLE, ['userid' => $userid])) {
            throw new moodle_exception('error:alreadyapplied', 'local_oriskill');
        }
        $now = time();
        $id = $DB->insert_record(self::TABLE, (object) [
            'userid' => $userid,
            'slug' => $data->slug,
            'displayname' => $data->displayname,
            'tagline' => $data->tagline ?? '',
            'bio' => $data->bio_editor['text'] ?? '',
            'bioformat' => $data->bio_editor['format'] ?? FORMAT_HTML,
            'motivation' => $data->motivation ?? '',
            'status' => 'pending',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $a = (object) ['name' => $data->displayname, 'url' => (new moodle_url('/local/oriskill/manage.php'))->out(false)];
        foreach (get_admins() as $admin) {
            email_to_user($admin, core_user::get_noreply_user(), get_string('mail:newapplication_subject', 'local_oriskill', $a),
                get_string('mail:newapplication_body', 'local_oriskill', $a));
        }
        return $id;
    }

    /**
     * Approve (or reactivate) a trainer space: own category plus course creator role on it.
     *
     * @param int $id Creator id.
     */
    public static function approve(int $id): void {
        global $DB;

        $creator = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        $category = $creator->categoryid ? core_course_category::get($creator->categoryid, IGNORE_MISSING, true) : null;
        if (!$category) {
            $category = core_course_category::create([
                'name' => $creator->displayname,
                'idnumber' => 'oriskill-creator-' . $creator->id,
                'parent' => self::parent_category()->id,
                'description' => $creator->tagline,
            ]);
        }
        role_assign(self::course_creator_role(), $creator->userid, context_coursecat::instance($category->id)->id,
            'local_oriskill', $creator->id);

        $DB->update_record(self::TABLE, (object) ['id' => $id, 'categoryid' => $category->id, 'status' => 'active',
            'timemodified' => time()]);

        $user = core_user::get_user($creator->userid);
        $a = (object) ['name' => $creator->displayname, 'url' => self::showcase_url($creator)->out(false),
            'courseurl' => (new moodle_url('/course/edit.php', ['category' => $category->id]))->out(false)];
        email_to_user($user, core_user::get_noreply_user(), get_string('mail:approved_subject', 'local_oriskill', $a),
            get_string('mail:approved_body', 'local_oriskill', $a));
    }

    /**
     * Suspend a trainer space: the showcase goes offline and the trainer can no longer create courses.
     * Existing courses and their learners are left untouched.
     *
     * @param int $id Creator id.
     */
    public static function suspend(int $id): void {
        global $DB;

        $creator = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        if ($creator->categoryid) {
            role_unassign(self::course_creator_role(), $creator->userid,
                context_coursecat::instance($creator->categoryid)->id, 'local_oriskill', $creator->id);
        }
        $DB->update_record(self::TABLE, (object) ['id' => $id, 'status' => 'suspended', 'timemodified' => time()]);
    }

    /**
     * Refuse an application.
     *
     * @param int $id Creator id.
     */
    public static function reject(int $id): void {
        global $DB;
        $DB->update_record(self::TABLE, (object) ['id' => $id, 'status' => 'rejected', 'timemodified' => time()]);
    }

    /**
     * Set up a page of the trainer space for the current user, who must be an active trainer.
     *
     * @param moodle_url $url Page URL.
     * @param string $titlekey Page title string key.
     * @return \stdClass Current user's trainer record.
     */
    public static function require_current_creator(moodle_url $url, string $titlekey): \stdClass {
        global $DB, $PAGE, $USER;

        $PAGE->set_context(context_system::instance());
        $PAGE->set_url($url);
        require_login(null, false);
        $creator = $DB->get_record(self::TABLE, ['userid' => $USER->id, 'status' => 'active']);
        if (!$creator) {
            redirect(new moodle_url('/local/oriskill/apply.php'));
        }
        $title = get_string($titlekey, 'local_oriskill');
        $PAGE->set_title($title);
        $PAGE->set_heading(get_string('editshowcase', 'local_oriskill'));
        $PAGE->set_secondary_navigation(false);
        return $creator;
    }

    /**
     * Opening of a trainer space page: side menu, then the page content column (closed by space_end()).
     *
     * @param \stdClass $creator Trainer.
     * @param string $current Path of the current page.
     * @param string $title Title of the page content.
     * @return string HTML.
     */
    public static function space_start(\stdClass $creator, string $current, string $title): string {
        global $OUTPUT, $USER;

        $items = [];
        foreach ([
            '/local/oriskill/dashboard.php' => ['menu:dashboard', 'fa-gauge-high'],
            '/local/oriskill/courses.php' => ['mycourses', 'fa-graduation-cap'],
            '/local/oriskill/videos.php' => ['menu:videos', 'fa-film'],
            '/local/oriskill/pricing.php' => ['pricing', 'fa-tags'],
            '/local/oriskill/sales.php' => ['sales', 'fa-chart-line'],
            '/local/oriskill/payment.php' => ['paymentsettings', 'fa-wallet'],
            '/local/oriskill/apply.php' => ['menu:profile', 'fa-store'],
        ] as $path => [$key, $icon]) {
            $items[] = ['url' => (new moodle_url($path))->out(false), 'label' => get_string($key, 'local_oriskill'),
                'icon' => $icon, 'active' => $path === $current];
        }
        $name = format_string($creator->displayname);
        return $OUTPUT->render_from_template('local_oriskill/space_nav', [
            'name' => $name,
            'person' => fullname($USER),
            'logo' => ($logo = self::logo_url($creator)) ? $logo->out(false) : null,
            'initials' => \core_text::strtoupper(\core_text::substr($name, 0, 1)),
            'items' => $items,
            'showcase' => self::showcase_url($creator)->out(false),
            'title' => $title,
        ]);
    }

    /**
     * Sale state of a trainer's course, as a badge.
     *
     * @param \stdClass $row Course row from seller_service::priced_courses().
     * @return string HTML.
     */
    public static function course_status_badge(\stdClass $row): string {
        if ($row->amount === null) {
            [$key, $class] = ['coursestatus:noprice', 'is-warn'];
        } else if ($row->onsale) {
            [$key, $class] = ['onsale', 'is-good'];
        } else {
            [$key, $class] = ['coursestatus:draft', ''];
        }
        return \html_writer::span(get_string($key, 'local_oriskill'), trim('oriskill-badge ' . $class));
    }

    /**
     * Closing of a trainer space page opened by space_start().
     *
     * @return string HTML.
     */
    public static function space_end(): string {
        return '</div></div>';
    }

    /**
     * Public showcase address.
     *
     * @param \stdClass $creator Creator.
     * @return moodle_url
     */
    public static function showcase_url(\stdClass $creator): moodle_url {
        return new moodle_url('/' . $creator->slug);
    }

    /**
     * Logo URL, if one was uploaded.
     *
     * @param \stdClass $creator Creator.
     * @return moodle_url|null
     */
    public static function logo_url(\stdClass $creator): ?moodle_url {
        $files = get_file_storage()->get_area_files(context_system::instance()->id, 'local_oriskill', 'logo',
            $creator->id, 'filename', false);
        $file = reset($files);
        return $file ? moodle_url::make_pluginfile_url($file->get_contextid(), 'local_oriskill', 'logo',
            $creator->id, '/', $file->get_filename()) : null;
    }

    /**
     * Visible courses of a trainer that are on sale, for the showcase.
     *
     * @param \stdClass $creator Creator.
     * @return array Template data per course.
     */
    public static function showcase_courses(\stdClass $creator): array {
        global $DB;

        $path = $creator->categoryid ? $DB->get_field('course_categories', 'path', ['id' => $creator->categoryid]) : false;
        if (!$path) {
            return [];
        }
        // The showcase is public: list visible courses of the trainer's categories directly, rather than through
        // the course category API, which hides them from visitors without the course list capability.
        $records = $DB->get_records_sql(
            "SELECT c.*
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE c.visible = 1 AND (cc.id = :categoryid OR " . $DB->sql_like('cc.path', ':path') . ")
           ORDER BY c.sortorder",
            ['categoryid' => $creator->categoryid, 'path' => $path . '/%']
        );
        $courses = [];
        foreach ($records as $record) {
            $course = new \core_course_list_element($record);
            $price = price_resolver::resolve_for_course($course->id, 1, true);
            if (!$price) {
                continue;
            }
            $image = '';
            foreach ($course->get_course_overviewfiles() as $file) {
                if ($file->is_valid_image()) {
                    $image = moodle_url::make_pluginfile_url($file->get_contextid(), 'course', 'overviewfiles', null,
                        '/', $file->get_filename())->out(false);
                    break;
                }
            }
            $courses[] = [
                'name' => format_string($course->get_formatted_name()),
                'summary' => shorten_text(html_to_text(format_text($course->summary, $course->summaryformat), 0), 180),
                'image' => $image,
                'price' => pricing_service::format_price($price->final_price, $price->currency),
                'compareprice' => !empty($price->on_sale)
                    ? pricing_service::format_price($price->regularprice, $price->currency) : '',
                'url' => (new moodle_url('/local/moderncommerce/course_details.php', ['id' => $course->id]))->out(false),
            ];
        }
        return $courses;
    }

    /**
     * Parent category of trainer categories, created on first use.
     *
     * @return core_course_category
     */
    private static function parent_category(): core_course_category {
        global $DB;

        $id = $DB->get_field('course_categories', 'id', ['idnumber' => self::PARENT_IDNUMBER]);
        if ($id) {
            return core_course_category::get($id, MUST_EXIST, true);
        }
        return core_course_category::create(['name' => get_string('creatorscategory', 'local_oriskill'),
            'idnumber' => self::PARENT_IDNUMBER]);
    }

    /**
     * Moodle's course creator role id.
     *
     * @return int
     */
    private static function course_creator_role(): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => 'coursecreator'], MUST_EXIST);
    }
}

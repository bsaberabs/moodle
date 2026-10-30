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
     * Set up a page of the trainer space for the current user: the trainer, or a collaborator whose role allows
     * the page (see team_service::PAGE_ROLES). A "space" parameter switches to another space of the user.
     *
     * @param moodle_url $url Page URL.
     * @param string $titlekey Page title string key.
     * @return \stdClass Trainer whose space it is.
     */
    public static function require_current_creator(moodle_url $url, string $titlekey): \stdClass {
        global $PAGE, $USER;

        $PAGE->set_context(context_system::instance());
        $PAGE->set_url($url);
        require_login(null, false);
        if ($switch = optional_param('space', 0, PARAM_INT)) {
            team_service::switch_space($switch);
        }
        $space = team_service::current_space();
        if (!$space) {
            redirect(new moodle_url('/local/oriskill/apply.php'));
        }
        if (!team_service::can($space['role'], $url->get_path(false))) {
            $home = team_service::home($space['role']);
            // The dashboard is where links lead: a role without it lands on its own first page, without a message.
            redirect(new moodle_url($home), $url->get_path(false) === '/local/oriskill/dashboard.php' ? ''
                : get_string('error:notallowed', 'local_oriskill'), null, \core\output\notification::NOTIFY_ERROR);
        }
        if (team_service::needs_mfa($space['role'], (int) $USER->id)) {
            redirect(new moodle_url('/admin/tool/mfa/user_preferences.php'), get_string('team:mfarequired', 'local_oriskill'),
                null, \core\output\notification::NOTIFY_WARNING);
        }
        $PAGE->set_title(get_string($titlekey, 'local_oriskill'));
        self::prepare_space_page();
        return $space['creator'];
    }

    /**
     * Role of the current user in the trainer space they work in.
     *
     * @return string owner, admin, teaching or support ('' outside a trainer space).
     */
    public static function current_role(): string {
        return team_service::current_space()['role'] ?? '';
    }

    /** Menu of the trainer space: group => [page => [string key, Bootstrap icon]]. */
    const SPACE_MENU = [
        'navgroup:overview' => [
            '/local/oriskill/dashboard.php' => ['menu:dashboard', 'bi-speedometer2'],
            '/local/oriskill/sales.php' => ['sales', 'bi-graph-up-arrow'],
            '/local/oriskill/stats.php' => ['menu:stats', 'bi-bar-chart'],
            '/local/oriskill/schedules.php' => ['menu:schedules', 'bi-calendar-check'],
            '/local/oriskill/unpaid.php' => ['menu:unpaid', 'bi-exclamation-triangle'],
            '/local/oriskill/invoices.php' => ['menu:invoices', 'bi-receipt'],
        ],
        'navgroup:courses' => [
            '/local/oriskill/courses.php' => ['mycourses', 'bi-mortarboard'],
            '/local/oriskill/videos.php' => ['menu:videos', 'bi-camera-video'],
            '/local/oriskill/tools.php' => ['menu:tools', 'bi-tools'],
            '/local/oriskill/pricing.php' => ['pricing', 'bi-tag'],
            '/local/oriskill/orderforms.php' => ['menu:orderforms', 'bi-file-earmark-text'],
            '/local/oriskill/coupons.php' => ['menu:coupons', 'bi-ticket-perforated'],
            '/local/oriskill/capturepages.php' => ['menu:capturepages', 'bi-envelope-paper'],
        ],
        'navgroup:learners' => [
            '/local/oriskill/contacts.php' => ['menu:contacts', 'bi-person-lines-fill'],
            '/local/oriskill/tickets.php' => ['menu:tickets', 'bi-life-preserver'],
            '/local/oriskill/learners.php' => ['menu:learners', 'bi-people'],
            '/local/oriskill/access.php' => ['menu:access', 'bi-unlock'],
        ],
        'navgroup:account' => [
            '/local/oriskill/payment.php' => ['paymentsettings', 'bi-wallet2'],
            '/local/oriskill/apply.php' => ['menu:profile', 'bi-shop'],
            '/local/oriskill/team.php' => ['menu:team', 'bi-person-badge'],
            '/local/oriskill/domains.php' => ['menu:domains', 'bi-globe'],
            '/local/oriskill/integrations.php' => ['menu:integrations', 'bi-plug'],
            '/local/oriskill/log.php' => ['menu:log', 'bi-journal-text'],
        ],
    ];

    /** @var array|null Trainer space page being built: [creator, current path, title, actions]. */
    protected static ?array $space = null;

    /**
     * Before the page header: the trainer space uses the Modern Commerce administration layout and styles.
     */
    public static function prepare_space_page(): void {
        global $PAGE;

        $PAGE->set_pagelayout('admin');
        $PAGE->set_secondary_navigation(false);
        // The administration styles apply to Modern Commerce pages (body class of their path).
        $PAGE->add_body_class('path-local-moderncommerce');
        $revision = (int) get_config('local_moderncommerce', 'version');
        foreach (['/local/moderncommerce/styles/design-system.css', '/local/moderncommerce/styles/bundles/admin.css'] as $css) {
            $PAGE->requires->css(new moodle_url($css, ['rev' => $revision]));
        }
    }

    /**
     * Opening of a trainer space page: the content echoed until space_end() goes into the administration layout
     * (side menu grouped by section, title strip).
     *
     * @param \stdClass $creator Trainer.
     * @param string $current Path of the current page, for the menu.
     * @param string $title Page title.
     * @param string $actionshtml Buttons of the title strip (default: see my showcase).
     * @return string Nothing: the page is rendered by space_end().
     */
    public static function space_start(\stdClass $creator, string $current, string $title, string $actionshtml = ''): string {
        self::$space = [$creator, $current, $title, $actionshtml];
        ob_start();
        return '';
    }

    /**
     * Closing of a trainer space page opened by space_start(): renders the whole space.
     *
     * @return string HTML.
     */
    public static function space_end(): string {
        global $CFG, $OUTPUT, $USER;

        [$creator, $current, $title, $actionshtml] = self::$space;
        self::$space = null;
        $content = ob_get_clean();

        $role = self::current_role() ?: 'owner';
        $navigation = [];
        foreach (self::SPACE_MENU as $group => $pages) {
            $items = [];
            foreach ($pages as $path => [$key, $icon]) {
                if (!team_service::can($role, $path)) {
                    continue;
                }
                // Tickets waiting for the team, next to the menu entry.
                $badge = $path === '/local/oriskill/tickets.php' ? ticket_service::open_count($creator) : 0;
                $items[] = ['key' => basename($path, '.php'), 'url' => (new moodle_url($path))->out(false),
                    'label' => get_string($key, 'local_oriskill'), 'icon' => $icon,
                    'active' => $path === $current, 'current' => $path === $current,
                    'hasbadge' => $badge > 0, 'badge' => $badge, 'badgedanger' => true];
            }
            if ($items) {
                $navigation[] = ['key' => $group, 'label' => get_string($group, 'local_oriskill'), 'items' => $items];
            }
        }
        $navigation[] = ['key' => 'navgroup:public', 'label' => get_string('navgroup:public', 'local_oriskill'), 'items' => [
            ['key' => 'showcase', 'url' => self::showcase_url($creator)->out(false),
                'label' => get_string('viewshowcase', 'local_oriskill'), 'icon' => 'bi-box-arrow-up-right', 'newtab' => true],
        ]];
        // A collaborator sees whose space it is and their role; someone in several spaces can switch.
        $spaces = team_service::spaces_of((int) $USER->id);
        if ($role !== 'owner' || count($spaces) > 1) {
            $switch = '';
            foreach ($spaces as $id => $space) {
                if ((int) $id !== (int) $creator->id) {
                    $switch .= \html_writer::link(new moodle_url('/local/oriskill/dashboard.php', ['space' => $id]),
                        get_string('team:switchto', 'local_oriskill', format_string($space['creator']->displayname)),
                        ['class' => 'ms-2']);
                }
            }
            $content = \html_writer::div(get_string('team:workingin', 'local_oriskill', (object) [
                'trainer' => format_string($creator->displayname),
                'role' => get_string('role:' . $role, 'local_oriskill')]) . $switch, 'alert alert-info py-2') . $content;
        }
        if ($actionshtml === '') {
            $actionshtml = \local_moderncommerce\output\admin_shell::action_group([[
                'label' => get_string('viewshowcase', 'local_oriskill'), 'icon' => 'bi-box-arrow-up-right',
                'url' => self::showcase_url($creator), 'attributes' => ['target' => '_blank', 'rel' => 'noopener'],
            ]]);
        }
        return $OUTPUT->render_from_template('local_moderncommerce/shell', [
            'title' => $title,
            'actionshtml' => $actionshtml,
            'contenthtml' => \html_writer::div($content, 'oriskill-space'),
            'navigation' => $navigation,
            'hasnavigation' => true,
            'config' => ['wwwroot' => $CFG->wwwroot],
        ]);
    }

    /**
     * Course chosen on a trainer page (the "courseid" parameter, or the first course), checked to be the trainer's.
     *
     * @param \stdClass $creator Trainer.
     * @return \stdClass|null Course record, or null when the trainer has no course yet.
     */
    public static function selected_course(\stdClass $creator): ?\stdClass {
        $courses = seller_service::priced_courses($creator);
        $courseid = optional_param('courseid', 0, PARAM_INT) ?: array_key_first($courses);
        return $courseid ? learning_service::require_own_course($creator, (int) $courseid) : null;
    }

    /**
     * Drop-down list to switch between the trainer's courses on a page.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Current course.
     * @param moodle_url $url Page.
     * @return string HTML.
     */
    public static function course_picker(\stdClass $creator, \stdClass $course, moodle_url $url): string {
        global $OUTPUT;

        $options = [];
        foreach (seller_service::priced_courses($creator) as $courseid => $row) {
            $options[$courseid] = format_string($row->course->fullname);
        }
        $select = new \single_select($url, 'courseid', $options, $course->id, null);
        $select->set_label(get_string('picker:course', 'local_oriskill'), ['class' => 'me-2 fw-semibold']);
        $select->class = 'oriskill-course-picker mb-4';
        return $OUTPUT->render($select);
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
     * Public showcase address.
     *
     * @param \stdClass $creator Creator.
     * @return moodle_url
     */
    public static function showcase_url(\stdClass $creator): moodle_url {
        return new moodle_url('/' . $creator->slug);
    }

    /**
     * Address of the page of one of the trainer's courses, in their brand (on their own domain when they have one).
     *
     * @param \stdClass $creator Trainer.
     * @param int $courseid Course.
     * @return moodle_url
     */
    public static function course_page_url(\stdClass $creator, int $courseid): moodle_url {
        if ($host = domain_service::host($creator, domain_service::course_domainid($courseid))) {
            return new moodle_url('https://' . $host . '/formation/' . $courseid);
        }
        return new moodle_url('/local/oriskill/coursepage.php', ['id' => $courseid]);
    }

    /**
     * Program of a course as a visitor sees it: its modules and the activities they hold (hidden ones left out).
     *
     * @param \stdClass $course Course.
     * @return array of ['name' => string, 'activities' => [['name', 'icon']], 'count' => int]
     */
    public static function course_program(\stdClass $course): array {
        $modinfo = get_fast_modinfo($course, -1);
        $program = [];
        foreach ($modinfo->get_section_info_all() as $num => $section) {
            if ($section->component || !$section->visible) {
                continue;
            }
            $activities = [];
            foreach ($modinfo->sections[$num] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if (!$cm->visible || $cm->deletioninprogress || $cm->modname === 'label') {
                    continue;
                }
                $activities[] = ['name' => $cm->get_formatted_name(), 'icon' => $cm->get_icon_url()->out(false)];
            }
            if (!$activities && $num == 0) {
                continue;
            }
            $program[] = ['name' => get_section_name($course, $section), 'activities' => $activities,
                'count' => count($activities)];
        }
        return $program;
    }

    /**
     * Logo URL, if one was uploaded.
     *
     * @param \stdClass $creator Creator.
     * @return moodle_url|null
     */
    public static function logo_url(\stdClass $creator): ?moodle_url {
        return self::image_url($creator, 'logo');
    }

    /**
     * Showcase banner URL, if one was uploaded.
     *
     * @param \stdClass $creator Creator.
     * @return moodle_url|null
     */
    public static function banner_url(\stdClass $creator): ?moodle_url {
        return self::image_url($creator, 'banner');
    }

    /**
     * URL of a trainer's image (logo or banner).
     *
     * @param \stdClass $creator Creator.
     * @param string $area File area.
     * @return moodle_url|null
     */
    private static function image_url(\stdClass $creator, string $area): ?moodle_url {
        $files = get_file_storage()->get_area_files(context_system::instance()->id, 'local_oriskill', $area,
            $creator->id, 'filename', false);
        $file = reset($files);
        return $file ? moodle_url::make_pluginfile_url($file->get_contextid(), 'local_oriskill', $area,
            $creator->id, '/', $file->get_filename()) : null;
    }

    /** Showcase typefaces: key => CSS font stack (system fonts only, nothing loaded from elsewhere). */
    const FONTS = [
        'modern' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        'elegant' => 'Georgia, "Times New Roman", serif',
        'classic' => '"Helvetica Neue", Helvetica, Arial, sans-serif',
    ];

    /** Oriskill colours, used when the trainer chose none. */
    const DEFAULT_PRIMARY = '#7c3aed';
    const DEFAULT_ACCENT = '#fbbf24';

    /**
     * Appearance of a trainer's showcase: colours, text colour readable on each, typeface.
     *
     * @param \stdClass $creator Creator.
     * @return array primary, onprimary, accent, onaccent, font, and ink: the primary colour, or a dark one when
     *     the primary colour would not read on white (prices, links)
     */
    public static function showcase_style(\stdClass $creator): array {
        $primary = self::valid_color($creator->primarycolor ?? '') ?? self::DEFAULT_PRIMARY;
        $accent = self::valid_color($creator->accentcolor ?? '') ?? self::DEFAULT_ACCENT;
        return [
            'primary' => $primary,
            'onprimary' => self::readable_on($primary),
            'accent' => $accent,
            'onaccent' => self::readable_on($accent),
            'ink' => self::readable_on($primary) === '#ffffff' ? $primary : '#111827',
            'font' => self::FONTS[$creator->fontstyle ?? ''] ?? self::FONTS['modern'],
        ];
    }

    /**
     * A colour as #rrggbb in lowercase, or null when it is not one.
     *
     * @param string $color Colour.
     * @return string|null
     */
    public static function valid_color(string $color): ?string {
        $color = strtolower(trim($color));
        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : null;
    }

    /**
     * Black or white, whichever reads better on a background colour (WCAG relative luminance).
     *
     * @param string $color Background #rrggbb.
     * @return string #ffffff or #111827
     */
    public static function readable_on(string $color): string {
        $channel = function(string $hex): float {
            $c = hexdec($hex) / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $luminance = 0.2126 * $channel(substr($color, 1, 2)) + 0.7152 * $channel(substr($color, 3, 2))
            + 0.0722 * $channel(substr($color, 5, 2));
        // Contrast with white (1.05 / (L + 0.05)) against contrast with near-black ((L + 0.05) / 0.0603).
        return 1.05 / ($luminance + 0.05) >= ($luminance + 0.05) / 0.0603 ? '#ffffff' : '#111827';
    }

    /**
     * A domain as stored: lowercase, without scheme, path, port or leading "www.", or null when it is not one.
     *
     * @param string $domain Domain as typed (wsmacademy.com, https://www.wsmacademy.com/...).
     * @return string|null
     */
    public static function normalise_domain(string $domain): ?string {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('~^[a-z]+://~', '', $domain);
        $domain = preg_replace('~[/:?#].*$~', '', $domain);
        $domain = preg_replace('/^www\./', '', $domain);
        if (!preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            return null;
        }
        return $domain;
    }

    /**
     * Why a domain cannot be attached to a trainer's showcase, or null if it can.
     *
     * @param string $domain Normalised domain.
     * @param int $creatorid The trainer attaching it.
     * @return string|null Language string key.
     */
    public static function domain_error(string $domain, int $creatorid): ?string {
        global $CFG, $DB;

        $site = strtolower((string) parse_url($CFG->wwwroot, PHP_URL_HOST));
        $site = preg_replace('/^www\./', '', $site);
        if ($domain === $site || str_ends_with($domain, '.' . $site)) {
            return 'error:domainsite';
        }
        if ($DB->record_exists_select('local_oriskill_domains', 'domain = ? AND creatorid <> ?', [$domain, $creatorid])) {
            return 'error:domaintaken';
        }
        return null;
    }

    /**
     * Active trainer whose showcase a domain opens (with or without "www.").
     *
     * @param string $domain Host of the request.
     * @return \stdClass|null
     */
    public static function creator_by_domain(string $domain): ?\stdClass {
        global $DB;

        // Any of the trainer's domains (primary or secondary).
        return domain_service::creator_by_domain($domain);
    }

    /** Public address of the Oriskill server, which a trainer's domain must point to. */
    const SERVER_IPV4 = '91.214.190.221';

    /**
     * Whether a domain and its "www." already point to the Oriskill server.
     *
     * @param string $domain Normalised domain.
     * @return bool
     */
    public static function domain_points_here(string $domain): bool {
        foreach ([$domain, 'www.' . $domain] as $host) {
            if (!in_array(self::SERVER_IPV4, gethostbynamel($host) ?: [], true)) {
                return false;
            }
        }
        return true;
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
                'url' => self::course_page_url($creator, (int) $course->id)->out(false),
                'instalments' => instalment_service::buyable_offer((int) $course->id),
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

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

/**
 * Header menu of the learner space of a trainer, and the trainer brand applied to it.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class menu_service {

    /** Menu entries of a trainer, and the courses under each. */
    const TABLE = 'local_oriskill_menus';
    const COURSES = 'local_oriskill_menucourses';

    /** Most modules shown under one course in the menu. */
    const MAXSECTIONS = 40;

    /**
     * Menu entries of a trainer, in order, each with its courses (courseid => section ids, empty = all modules).
     *
     * @param \stdClass $creator Trainer.
     * @param bool $onlyvisible Leave out the hidden entries.
     * @return array id => record with a "courses" property
     */
    public static function entries(\stdClass $creator, bool $onlyvisible = false): array {
        global $DB;

        $params = ['creatorid' => $creator->id];
        if ($onlyvisible) {
            $params['visible'] = 1;
        }
        $entries = $DB->get_records(self::TABLE, $params, 'sortorder, id');
        foreach ($entries as $entry) {
            $entry->courses = [];
            foreach ($DB->get_records(self::COURSES, ['menuid' => $entry->id], 'sortorder, id') as $row) {
                $entry->courses[(int) $row->courseid] = array_values(array_filter(array_map('intval',
                    explode(',', (string) $row->sections))));
            }
        }
        return $entries;
    }

    /**
     * Courses of the trainer that can go in the menu.
     *
     * @param \stdClass $creator Trainer.
     * @return array id => course record (id, fullname)
     */
    public static function trainer_courses(\stdClass $creator): array {
        global $DB;

        return $creator->categoryid
            ? $DB->get_records('course', ['category' => $creator->categoryid], 'fullname', 'id, fullname') : [];
    }

    /**
     * Modules (sections) of a course that can be shown in the menu, the general section left out.
     *
     * @param \stdClass $course Course.
     * @return array section id => name
     */
    public static function course_sections(\stdClass $course): array {
        $modinfo = get_fast_modinfo($course, -1);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $num => $info) {
            if ($num > 0 && !$info->component) {
                $sections[(int) $info->id] = get_section_name($course, $info);
            }
        }
        return $sections;
    }

    /**
     * Create or update a menu entry and its courses. Only the trainer's own courses are kept.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Entry to update (0: new).
     * @param string $label Label.
     * @param int $sortorder Order.
     * @param bool $visible Shown to learners.
     * @param array $courses courseid => section ids (empty: all modules)
     * @return int Entry id.
     */
    public static function save(\stdClass $creator, int $id, string $label, int $sortorder, bool $visible, array $courses): int {
        global $DB;

        $label = \core_text::substr(trim(clean_param($label, PARAM_TEXT)), 0, 100);
        if ($label === '') {
            throw new \moodle_exception('library:noname', 'local_oriskill');
        }
        $own = self::trainer_courses($creator);
        $now = time();
        $record = (object) ['label' => $label, 'sortorder' => max(0, min(99999, $sortorder)), 'visible' => (int) $visible,
            'timemodified' => $now];
        if ($id) {
            $record->id = $DB->get_field(self::TABLE, 'id', ['id' => $id, 'creatorid' => $creator->id], MUST_EXIST);
        }
        $transaction = $DB->start_delegated_transaction();
        if ($id) {
            $DB->update_record(self::TABLE, $record);
            $DB->delete_records(self::COURSES, ['menuid' => $id]);
        } else {
            $record->creatorid = $creator->id;
            $record->timecreated = $now;
            $id = $DB->insert_record(self::TABLE, $record);
        }
        $position = 0;
        foreach ($courses as $courseid => $sectionids) {
            if (!isset($own[$courseid])) {
                continue;
            }
            $valid = array_keys(self::course_sections($DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST)));
            $sectionids = array_values(array_intersect($valid, array_map('intval', (array) $sectionids)));
            $DB->insert_record(self::COURSES, (object) ['menuid' => $id, 'courseid' => $courseid,
                'sections' => implode(',', $sectionids), 'sortorder' => $position++]);
        }
        $transaction->allow_commit();
        return $id;
    }

    /**
     * Delete a menu entry of the trainer (the courses themselves are untouched).
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Entry.
     */
    public static function delete(\stdClass $creator, int $id): void {
        global $DB;

        if ($DB->record_exists(self::TABLE, ['id' => $id, 'creatorid' => $creator->id])) {
            $DB->delete_records(self::COURSES, ['menuid' => $id]);
            $DB->delete_records(self::TABLE, ['id' => $id]);
        }
    }

    /**
     * Menu as a learner sees it: every entry, with its courses and modules, a link only where the learner can open it
     * (enrolled, module visible and unlocked); what is locked has no link.
     *
     * @param \stdClass $creator Trainer.
     * @param int $userid Learner.
     * @return array of ['label', 'children' => [['label', 'url' (moodle_url|null), 'locked' (bool)]]]
     */
    public static function for_user(\stdClass $creator, int $userid): array {
        $menu = [];
        foreach (self::entries($creator, true) as $entry) {
            $children = [];
            foreach ($entry->courses as $courseid => $sectionids) {
                // Courses of another trainer never appear, even if the data were changed by hand.
                $owner = seller_service::owner_of_course($courseid);
                if (!$owner || (int) $owner->id !== (int) $creator->id) {
                    continue;
                }
                $course = get_course($courseid);
                $context = \context_course::instance($courseid);
                $canaccess = ($course->visible || has_capability('moodle/course:viewhiddencourses', $context, $userid))
                    && (is_enrolled($context, $userid, '', true) || has_capability('moodle/course:view', $context, $userid));
                $name = format_string($course->fullname, true, ['context' => $context]);
                if (!$canaccess) {
                    $children[] = ['label' => $name, 'url' => null, 'locked' => true];
                    continue;
                }
                $children[] = ['label' => $name, 'url' => new \moodle_url('/course/view.php', ['id' => $courseid]),
                    'locked' => false];
                $modinfo = get_fast_modinfo($course, $userid);
                $shown = 0;
                foreach ($modinfo->get_section_info_all() as $num => $info) {
                    if ($num == 0 || $info->component || !$info->visible
                            || ($sectionids && !in_array((int) $info->id, $sectionids, true))) {
                        continue;
                    }
                    if (++$shown > self::MAXSECTIONS) {
                        break;
                    }
                    $open = $info->uservisible;
                    $children[] = ['label' => '- ' . get_section_name($course, $info), 'locked' => !$open,
                        'url' => $open ? new \moodle_url('/course/section.php', ['id' => $info->id]) : null];
                }
            }
            if ($children) {
                $menu[] = ['label' => format_string($entry->label), 'children' => $children];
            }
        }
        return $menu;
    }

    /**
     * Whether the trainer brand is applied to the learner space of their courses.
     *
     * @param \stdClass $creator Trainer.
     * @return bool
     */
    public static function skin_enabled(\stdClass $creator): bool {
        return !empty($creator->studentskin);
    }

    /**
     * Turn the brand on the learner space on or off. Nothing else of the trainer record is touched.
     *
     * @param \stdClass $creator Trainer.
     * @param bool $on On or off.
     */
    public static function set_skin(\stdClass $creator, bool $on): void {
        global $DB;

        $DB->update_record('local_oriskill_creators', (object) ['id' => $creator->id, 'studentskin' => (int) $on,
            'timemodified' => time()]);
    }

    /**
     * CSS of the learner space of a trainer's course: header with the logo and a gradient of the main colour, course
     * index in the brand colours with whole titles, lesson page with the video first and resources as buttons, small
     * screens. It is written for the Boost family of themes (Boost Union) and applied only on that trainer's courses.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course being viewed.
     * @return string CSS (no tags).
     */
    public static function skin_css(\stdClass $creator, \stdClass $course): string {
        $style = creator_service::showcase_style($creator);
        $primary = $style['primary'];
        $on = $style['onprimary'];
        $accent = $style['accent'];
        $dark = self::shade($primary, 0.4);
        $deeper = self::shade($primary, 0.7);
        $font = $style['font'];
        $logo = creator_service::logo_url($creator);
        $coursename = preg_replace('/[^\p{L}\p{N} \'\-\.,]/u', '', html_entity_decode(format_string($course->fullname, true), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $css = <<<CSS
body {
    font-family: $font;
}
.navbar.fixed-top, .navbar.navbar-expand {
    background: linear-gradient(90deg, $primary 0%, $dark 100%) !important;
    border-bottom: 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .25);
}
.navbar .nav-link, .navbar .navbar-nav .nav-link, .navbar .dropdown-toggle, .navbar .navbar-brand,
.navbar .usermenu .login, .navbar .btn-link, .navbar .icon, .navbar .nav-link.active {
    color: $on !important;
}
.navbar .nav-link.active, .navbar .nav-link:hover {
    box-shadow: inset 0 -3px 0 $accent;
    background: transparent !important;
}
.navbar .dropdown-menu .dropdown-item {
    color: #1f2937 !important;
    white-space: normal;
}
.navbar .dropdown-menu .dropdown-item.disabled { opacity: .55; }
.primary-navigation .moremenu { background: transparent; }
#page-footer, .footer-popover + #page-footer {
    background: #1b1b1f;
    color: #d1d5db;
}
#page-footer a { color: #e5e7eb; }
/* Course index (left drawer): brand colours, whole titles, current lesson highlighted. */
#theme_boost-drawers-courseindex, .courseindex {
    background: #fff;
}
#theme_boost-drawers-courseindex .drawerheader {
    background: linear-gradient(180deg, $primary, $dark);
    color: $on;
}
#theme_boost-drawers-courseindex .drawerheader .btn, #theme_boost-drawers-courseindex .drawerheader .icon,
#theme_boost-drawers-courseindex .drawerheader h3, #theme_boost-drawers-courseindex .drawerheader .drawer-header-title {
    color: $on !important;
}
.courseindex::before {
    content: "$coursename";
    display: block;
    padding: .75rem 1rem .25rem;
    font-weight: 700;
    font-size: 1.1rem;
    color: $primary;
}
.courseindex .courseindex-section-title .courseindex-link {
    font-style: italic;
    font-weight: 600;
    color: $deeper;
}
.courseindex .courseindex-link, .courseindex .courseindex-name, .courseindex .courseindex-sectioncontent a {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
    overflow-wrap: anywhere;
    line-height: 1.35;
}
.courseindex .courseindex-item { align-items: flex-start; padding-top: .35rem; padding-bottom: .35rem; }
.courseindex .courseindex-item.pageitem, .courseindex .courseindex-item.pageitem .courseindex-link {
    background: $primary;
    color: $on !important;
    border-radius: .35rem;
}
.courseindex .courseindex-item .completion-dot, .courseindex .courseindex-item .icon { flex-shrink: 0; }
/* Lesson page: wide video first, resources as buttons. */
.path-mod-page #region-main h2 { color: $primary; font-weight: 700; }
.path-mod-page #region-main iframe, .path-mod-page #region-main video, .path-mod-page #region-main .video-container {
    display: block;
    width: 100%;
    max-width: 100%;
    aspect-ratio: 16 / 9;
    height: auto;
    border: 0;
    border-radius: .6rem;
    box-shadow: 0 6px 20px rgba(0, 0, 0, .18);
    margin: 1rem 0;
}
.path-mod-page #region-main audio { width: 100%; margin: .5rem 0; }
.path-mod-page #region-main a[href*="pluginfile.php"], .path-mod-page #region-main a.oriskill-resource {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    margin: .25rem .4rem .25rem 0;
    padding: .65rem 1.2rem;
    min-height: 44px;
    border-radius: 2rem;
    background: $primary;
    color: $on !important;
    font-weight: 600;
    text-decoration: none;
}
.path-mod-page #region-main a[href*="pluginfile.php"]:hover { background: $dark; }
.path-mod-page #region-main .activity-navigation .btn { background: $primary; border-color: $primary; color: $on; }
@media (max-width: 767.98px) {
    .navbar.fixed-top { padding-left: .5rem; padding-right: .5rem; }
    .navbar .navbar-toggler, .navbar .nav-link, .navbar .dropdown-item { min-height: 44px; }
    .path-mod-page #region-main a[href*="pluginfile.php"] { display: flex; justify-content: center; width: 100%; margin-right: 0; }
    .path-mod-page #region-main iframe, .path-mod-page #region-main video { border-radius: .35rem; margin: .5rem 0; }
    #page.drawers .main-inner { padding-left: .75rem; padding-right: .75rem; }
}
CSS;
        if ($logo) {
            $url = $logo->out(false);
            $css .= <<<CSS

.navbar .navbar-brand {
    background: url("$url") left center / contain no-repeat;
    min-width: 140px;
    min-height: 40px;
    font-size: 0;
}
.navbar .navbar-brand img, .navbar .navbar-brand .logo { display: none; }
CSS;
        }
        // Colours are validated #rrggbb, the font stack is a constant, the course name is reduced to letters and digits.
        return $css;
    }

    /**
     * A colour darkened by mixing it with black.
     *
     * @param string $color #rrggbb
     * @param float $amount 0 (unchanged) to 1 (black).
     * @return string #rrggbb
     */
    public static function shade(string $color, float $amount): string {
        $out = '#';
        foreach ([1, 3, 5] as $at) {
            $out .= str_pad(dechex((int) round(hexdec(substr($color, $at, 2)) * (1 - $amount))), 2, '0', STR_PAD_LEFT);
        }
        return $out;
    }
}

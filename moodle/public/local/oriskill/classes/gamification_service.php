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
 * Gamification of a trainer's course with Moodle's badges, made in one click: a badge for the whole course and one per
 * module, awarded automatically when the learner has completed every activity they cover.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gamification_service {

    /**
     * Badges of a course, with how many learners earned each.
     *
     * @param \stdClass $course Course.
     * @return array
     */
    public static function badges(\stdClass $course): array {
        global $DB;

        return $DB->get_records_sql("SELECT b.id, b.name, b.status, (SELECT COUNT(1) FROM {badge_issued} i WHERE i.badgeid = b.id) AS awarded
                                       FROM {badge} b WHERE b.type = 2 AND b.courseid = ? ORDER BY b.id", [$course->id]);
    }

    /**
     * Activities of a course that can be completed, by section.
     *
     * @param \stdClass $course Course.
     * @return array section number => [section name, cm ids]
     */
    public static function completable(\stdClass $course): array {
        $modinfo = get_fast_modinfo($course);
        $sections = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->completion && !$cm->deletioninprogress && $cm->visible) {
                $section = $modinfo->get_section_info($cm->sectionnum);
                $sections[$cm->sectionnum][0] = get_section_name($course, $section);
                $sections[$cm->sectionnum][1][] = (int) $cm->id;
            }
        }
        ksort($sections);
        return $sections;
    }

    /**
     * Make the badges of a course: the whole course, and one per module with activities to complete.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course of that trainer.
     * @param bool $modules Also one badge per module.
     * @return int Badges made.
     */
    public static function create(\stdClass $creator, \stdClass $course, bool $modules): int {
        global $DB;

        $sections = self::completable($course);
        $all = array_merge(...array_values(array_map(fn($s) => $s[1], $sections)) ?: [[]]);
        if (!$all) {
            throw new \moodle_exception('gamif:nocompletion', 'local_oriskill');
        }
        $existing = $DB->get_fieldset_select('badge', 'name', 'type = 2 AND courseid = ?', [$course->id]);
        $made = 0;
        $todo = [[get_string('gamif:coursebadge', 'local_oriskill', format_string($course->fullname)), $all, '★']];
        if ($modules) {
            $number = 0;
            foreach ($sections as $section) {
                $todo[] = [get_string('gamif:modulebadge', 'local_oriskill', $section[0]), $section[1], (string) ++$number];
            }
        }
        foreach ($todo as [$name, $cmids, $symbol]) {
            $name = \core_text::substr($name, 0, 255);
            if (in_array($name, $existing, true)) {
                continue;
            }
            self::make_badge($creator, $course, $name, $cmids, $symbol);
            $made++;
        }
        log_service::add($creator, 'badges_created', ['count' => $made], (int) $course->id);
        return $made;
    }

    /**
     * One badge, awarded when all the activities are completed.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course.
     * @param string $name Name.
     * @param int[] $cmids Activities.
     * @param string $symbol Shown on its image.
     */
    protected static function make_badge(\stdClass $creator, \stdClass $course, string $name, array $cmids, string $symbol): void {
        global $CFG;
        require_once($CFG->libdir . '/badgeslib.php');
        require_once($CFG->dirroot . '/badges/criteria/award_criteria.php');

        $badge = \core_badges\badge::create_badge((object) ['name' => $name, 'version' => '1', 'language' => current_language(),
            'description' => get_string('gamif:badgedesc', 'local_oriskill', $name), 'imagecaption' => $name,
            'issuername' => format_string($creator->displayname), 'issuerurl' => creator_service::showcase_url($creator)->out(false),
            'issuercontact' => ''], (int) $course->id);
        $image = self::image($creator, $symbol);
        badges_process_badge_image($badge, $image);
        @unlink($image);
        \award_criteria::build(['criteriatype' => BADGE_CRITERIA_TYPE_OVERALL, 'badgeid' => $badge->id])
            ->save(['agg' => BADGE_CRITERIA_AGGREGATION_ALL]);
        $params = ['agg' => BADGE_CRITERIA_AGGREGATION_ALL];
        foreach ($cmids as $cmid) {
            $params['module_' . $cmid] = $cmid;
        }
        \award_criteria::build(['criteriatype' => BADGE_CRITERIA_TYPE_ACTIVITY, 'badgeid' => $badge->id])->save($params);
        $badge = new \core_badges\badge($badge->id);
        $badge->set_status(BADGE_STATUS_ACTIVE);
    }

    /**
     * Image of a badge: a medal in the trainer's colour with a symbol.
     *
     * @param \stdClass $creator Trainer.
     * @param string $symbol Star or number.
     * @return string PNG file.
     */
    protected static function image(\stdClass $creator, string $symbol): string {
        $size = 256;
        $img = imagecreatetruecolor($size, $size);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        $hex = preg_match('/^#[0-9a-f]{6}$/i', (string) ($creator->primarycolor ?? '')) ? $creator->primarycolor : '#7c3aed';
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        imagefilledellipse($img, 128, 128, 240, 240, imagecolorallocate($img, 245, 185, 66));
        imagefilledellipse($img, 128, 128, 210, 210, imagecolorallocate($img, $r, $g, $b));
        $white = imagecolorallocate($img, 255, 255, 255);
        if ($symbol === '★') {
            $points = [];
            for ($i = 0; $i < 10; $i++) {
                $radius = $i % 2 ? 36 : 84;
                $angle = M_PI / 5 * $i - M_PI / 2;
                $points[] = 128 + $radius * cos($angle);
                $points[] = 128 + $radius * sin($angle);
            }
            imagefilledpolygon($img, $points, $white);
        } else {
            $scale = 6;
            $text = imagecreatetruecolor(imagefontwidth(5) * strlen($symbol), imagefontheight(5));
            imagefill($text, 0, 0, imagecolorallocate($text, $r, $g, $b));
            imagestring($text, 5, 0, 0, $symbol, imagecolorallocate($text, 255, 255, 255));
            $w = imagesx($text) * $scale;
            $h = imagesy($text) * $scale;
            imagecopyresized($img, $text, (int) (128 - $w / 2), (int) (128 - $h / 2), 0, 0, $w, $h, imagesx($text), imagesy($text));
        }
        $file = make_request_directory() . '/badge.png';
        imagepng($img, $file);
        return $file;
    }

    /**
     * Turn a badge of the course on or off.
     *
     * @param \stdClass $course Course.
     * @param int $id Badge.
     */
    public static function toggle(\stdClass $course, int $id): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/badgeslib.php');

        $DB->get_record('badge', ['id' => $id, 'courseid' => $course->id], 'id', MUST_EXIST);
        $badge = new \core_badges\badge($id);
        $badge->set_status($badge->is_active() ? BADGE_STATUS_INACTIVE : BADGE_STATUS_ACTIVE);
    }
}

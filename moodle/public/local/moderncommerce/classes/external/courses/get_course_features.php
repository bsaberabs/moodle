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
 * External API returning course merchandising metadata for the admin editor.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\courses;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\services\pricing_service;

/**
 * Get a course's advanced/merchandising metadata.
 */
class get_course_features extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $courseid Course ID.
     * @return array
     */
    public static function execute(int $courseid): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);

        $meta = $DB->get_record('local_moderncommerce_course_meta', ['courseid' => $course->id]);
        $durationminutes = $meta ? (int) $meta->durationminutes : 0;

        $objectives = [];
        foreach ($DB->get_records('local_moderncommerce_course_objectives', ['courseid' => $course->id], 'sortorder ASC') as $o) {
            $objectives[] = ['text' => (string) $o->objective];
        }

        $outline = [];
        foreach ($DB->get_records('local_moderncommerce_course_outline', ['courseid' => $course->id], 'sortorder ASC') as $r) {
            $outline[] = ['title' => (string) $r->sectiontitle, 'time' => (string) ($r->estimatedtime ?? '')];
        }

        $pricing = pricing_service::get_course_pricing($course->id);
        $price = $pricing ? (float) $pricing->price : 0.0;
        $saleprice = ($pricing && !empty($pricing->saleprice)) ? (float) $pricing->saleprice : 0.0;

        return [
            'courseid' => (int) $course->id,
            'coursename' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
            'durationhours' => intdiv($durationminutes, 60),
            'durationminutes' => $durationminutes % 60,
            'skilllevel' => $meta ? (string) ($meta->skilllevel ?? '') : '',
            'language' => $meta ? (string) ($meta->language ?? '') : '',
            'passgrade' => $meta && $meta->passgrade !== null ? (float) $meta->passgrade : 0.0,
            'certenabled' => $meta ? !empty($meta->certificateenabled) : false,
            'overviewauto' => $meta ? ($meta->overview === null) : true,
            'overviewtext' => $meta ? (string) ($meta->overview ?? '') : '',
            'featured' => $meta ? !empty($meta->featured) : false,
            'bestseller' => $meta ? !empty($meta->bestseller) : false,
            'trending' => $meta ? !empty($meta->trending) : false,
            'price' => $price,
            'saleprice' => $saleprice,
            'quizcount' => self::quiz_count($course, $CFG),
            'sectioncount' => self::section_count($course, $CFG),
            'objectives' => $objectives,
            'outline' => $outline,
            'levels' => self::options(['Beginner', 'Intermediate', 'Advanced', 'All Levels']),
            'languages' => self::options(['English', 'French', 'Yoruba', 'Arabic', 'Spanish']),
            'currency' => [
                'code' => (string) pricing_service::get_currency_config()->currency,
                'symbol' => (string) pricing_service::get_currency_config()->symbol,
            ],
            'warnings' => [],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
            'coursename' => new external_value(PARAM_TEXT, 'Course name.'),
            'durationhours' => new external_value(PARAM_INT, 'Duration hours.'),
            'durationminutes' => new external_value(PARAM_INT, 'Duration minutes.'),
            'skilllevel' => new external_value(PARAM_TEXT, 'Skill level.'),
            'language' => new external_value(PARAM_TEXT, 'Language.'),
            'passgrade' => new external_value(PARAM_FLOAT, 'Pass grade percentage.'),
            'certenabled' => new external_value(PARAM_BOOL, 'Certificate enabled.'),
            'overviewauto' => new external_value(PARAM_BOOL, 'Auto-generate overview.'),
            'overviewtext' => new external_value(PARAM_RAW, 'Overview text.'),
            'featured' => new external_value(PARAM_BOOL, 'Featured badge.'),
            'bestseller' => new external_value(PARAM_BOOL, 'Bestseller badge.'),
            'trending' => new external_value(PARAM_BOOL, 'Trending badge.'),
            'price' => new external_value(PARAM_FLOAT, 'Regular price.'),
            'saleprice' => new external_value(PARAM_FLOAT, 'Sale price.'),
            'quizcount' => new external_value(PARAM_INT, 'Quiz activity count.'),
            'sectioncount' => new external_value(PARAM_INT, 'Course section count.'),
            'objectives' => new external_multiple_structure(new external_single_structure([
                'text' => new external_value(PARAM_TEXT, 'Objective text.'),
            ])),
            'outline' => new external_multiple_structure(new external_single_structure([
                'title' => new external_value(PARAM_TEXT, 'Section title.'),
                'time' => new external_value(PARAM_TEXT, 'Estimated time.'),
            ])),
            'levels' => self::options_structure(),
            'languages' => self::options_structure(),
            'currency' => new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Currency code.'),
                'symbol' => new external_value(PARAM_TEXT, 'Currency symbol.'),
            ]),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Options structure.
     *
     * @return external_multiple_structure
     */
    private static function options_structure(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'value' => new external_value(PARAM_TEXT, 'Option value.'),
            'label' => new external_value(PARAM_TEXT, 'Option label.'),
        ]));
    }

    /**
     * Build value=label options.
     *
     * @param array $values Values.
     * @return array
     */
    private static function options(array $values): array {
        return array_map(static function (string $v): array {
            return ['value' => $v, 'label' => $v];
        }, $values);
    }

    /**
     * Count quiz activities in a course.
     *
     * @param \stdClass $course Course.
     * @param object $cfg Config.
     * @return int
     */
    private static function quiz_count(\stdClass $course, object $cfg): int {
        require_once($cfg->dirroot . '/course/lib.php');
        $count = 0;
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if (!$cm->deletioninprogress && $cm->modname === 'quiz') {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Count visible course sections.
     *
     * @param \stdClass $course Course.
     * @param object $cfg Config.
     * @return int
     */
    private static function section_count(\stdClass $course, object $cfg): int {
        require_once($cfg->dirroot . '/course/lib.php');
        $count = 0;
        foreach (get_fast_modinfo($course)->get_section_info_all() as $s) {
            if ((int) $s->section > 0) {
                $count++;
            }
        }
        return $count;
    }
}

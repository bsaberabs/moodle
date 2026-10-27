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
 * External API returning bundle merchandising metadata for the admin editor.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\bundles;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\api\bundle_api;
use local_moderncommerce\services\bundle_meta_service;
use local_moderncommerce\services\pricing_service;
use local_moderncommerce\services\certificate_template_service;

/**
 * Get a bundle's advanced/merchandising metadata.
 */
class get_bundle_features extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'bundleid' => new external_value(PARAM_INT, 'Bundle product ID.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $bundleid Bundle product ID.
     * @return array
     */
    public static function execute(int $bundleid): array {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['bundleid' => $bundleid]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $bundle = $DB->get_record_select(
            'local_moderncommerce_products',
            "id = :id AND producttype IN (:bundle, :program)",
            ['id' => $params['bundleid'], 'bundle' => 'bundle', 'program' => 'program'],
            '*',
            MUST_EXIST
        );
        $bundleid = (int) $bundle->id;

        $meta = bundle_meta_service::get_meta($bundleid);
        $learninghours = $meta['learning_hours'];

        $outline = [];
        foreach (bundle_meta_service::get_outline($bundleid) as $row) {
            $outline[] = ['title' => (string) ($row['title'] ?? '')];
        }

        // The get_mustpass() method returns a flat array of course ids.
        $mustpassids = array_values(array_map('intval', bundle_meta_service::get_mustpass($bundleid)));

        $courses = [];
        foreach (bundle_meta_service::get_bundle_courses($bundleid) as $c) {
            $courses[] = [
                'id' => (int) ($c['id'] ?? 0),
                'fullname' => format_string((string) ($c['fullname'] ?? '')),
            ];
        }

        $prereqcourses = [];
        foreach (bundle_meta_service::get_prereq_courses($bundleid) as $c) {
            $prereqcourses[] = [
                'id' => (int) ($c['id'] ?? 0),
                'fullname' => format_string((string) ($c['fullname'] ?? '')),
            ];
        }
        // Candidate lookup is search-ahead through the dedicated service. Keep
        // this field for compatibility with existing API consumers.
        $prereqcandidates = [];

        $tags = array_values(array_map('strval', bundle_meta_service::get_tags($bundleid)));

        // Live "Bundle Overview" sidebar data.
        $isprogram = ($bundle->producttype === 'program');
        $coursecertificateavailable = certificate_template_service::available();
        $certificatetemplates = $coursecertificateavailable ? self::certificate_template_options() : [];
        $defaultcertificatetemplateid = certificate_template_service::default_template_id();
        $defaultcertificatetemplatename = certificate_template_service::options()[$defaultcertificatetemplateid] ?? '';
        $apibundle = bundle_api::get($bundleid);
        $rawprice = (float) ($apibundle->price ?? 0);
        $rawsale = (float) ($apibundle->saleprice ?? 0);
        $onsale = $rawsale > 0 && $rawsale < $rawprice;
        $savings = bundle_api::calculate_savings($bundleid);

        $fs = get_file_storage();
        $hasimage = (bool) $fs->get_area_files($context->id, 'local_moderncommerce', 'bundleimage', $bundleid, 'id', false);
        $imageurl = $hasimage ? local_moderncommerce_get_bundle_image_url($bundleid) : '';

        return [
            'bundleid' => $bundleid,
            'bundlename' => format_string($bundle->name, true, ['context' => $context]),
            'isprogram' => $isprogram,
            'typelabel' => $isprogram
                ? get_string('program', 'local_moderncommerce')
                : get_string('bundle', 'local_moderncommerce'),
            'imageurl' => $imageurl,
            'hasimage' => $hasimage,
            'displayprice' => pricing_service::format_price($rawprice),
            'displaysaleprice' => $onsale ? pricing_service::format_price($rawsale) : '',
            'onsale' => $onsale,
            'coursescount' => count($courses),
            'totaldurationminutes' => (int) ($meta['total_duration_minutes'] ?? 0),
            'learninghoursfieldexists' => !empty($learninghours['fieldexists']),
            'learninghourssetcount' => (int) ($learninghours['setcount'] ?? 0),
            'learninghoursmissingcount' => (int) ($learninghours['missingcount'] ?? 0),
            'learninghoursmissingcourses' => array_values($learninghours['missingcourses'] ?? []),
            'kpiassessments' => (int) ($meta['kpi_assessments'] ?? 0),
            'savingsamount' => (float) ($savings['savings'] ?? 0),
            'savingspercent' => (int) ($savings['percentage'] ?? 0),
            'displaysavings' => pricing_service::format_price((float) ($savings['savings'] ?? 0)),
            'price' => (float) ($meta['price'] ?? 0),
            'compareat' => (float) ($meta['compare_at'] ?? 0),
            'visibility' => (string) ($meta['visibility_mode'] ?? 'Public'),
            'availstart' => (string) ($meta['avail_start'] ?? ''),
            'availend' => (string) ($meta['avail_end'] ?? ''),
            'featured' => !empty($meta['badge_featured']),
            'bestseller' => !empty($meta['badge_bestseller']),
            'trending' => !empty($meta['badge_trending']),
            'skilllevel' => (string) ($meta['skill_level'] ?? ''),
            'language' => (string) ($meta['language'] ?? ''),
            'hasprereq' => !empty($meta['has_prereq']),
            'autoduration' => !empty($meta['auto_duration']),
            'durationhours' => (int) ($meta['duration_hours'] ?? 0),
            'durationminutes' => (int) ($meta['duration_minutes'] ?? 0),
            'autoassessments' => !empty($meta['auto_assessments']),
            'assessmentscount' => (int) ($meta['assessments_count'] ?? 0),
            'autooutline' => !empty($meta['auto_outline']),
            'passgrade' => (float) ($meta['pass_grade'] ?? 70),
            'passpolicy' => (string) ($meta['pass_policy'] ?? 'all_must_pass'),
            'certenabled' => !empty($meta['bundle_cert_enabled']),
            'certificatetemplateid' => (int) ($meta['certificate_template_id'] ?? 0),
            'defaultcertificatetemplateid' => $defaultcertificatetemplateid,
            'defaultcertificatetemplatename' => $defaultcertificatetemplatename,
            'coursecertificateavailable' => $coursecertificateavailable,
            'certificatetemplates' => $certificatetemplates,
            'outline' => $outline,
            'mustpassids' => array_values(array_filter($mustpassids)),
            'courses' => $courses,
            'prereqcourses' => $prereqcourses,
            'prereqcandidates' => $prereqcandidates,
            'tags' => $tags,
            'levels' => self::options(['Beginner', 'Intermediate', 'Advanced', 'All Levels']),
            'languages' => self::language_options(),
            'visibilities' => self::options(['Public', 'Hidden', 'Scheduled']),
            'passpolicies' => [
                ['value' => 'all_must_pass', 'label' => get_string('acf_allcoursesmustpass', 'local_moderncommerce')],
                ['value' => 'weighted_avg', 'label' => get_string('acf_weightedaverage', 'local_moderncommerce')],
                ['value' => 'any_pass', 'label' => get_string('acf_anycoursepass', 'local_moderncommerce')],
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
            'bundleid' => new external_value(PARAM_INT, 'Bundle ID.'),
            'bundlename' => new external_value(PARAM_TEXT, 'Bundle name.'),
            'isprogram' => new external_value(PARAM_BOOL, 'Whether this is a program.'),
            'typelabel' => new external_value(PARAM_TEXT, 'Type label (Bundle/Program).'),
            'imageurl' => new external_value(PARAM_RAW, 'Resolved bundle image URL (empty when none).'),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether a custom image is stored.'),
            'displayprice' => new external_value(PARAM_TEXT, 'Formatted regular price.'),
            'displaysaleprice' => new external_value(PARAM_TEXT, 'Formatted sale price (empty when none).'),
            'onsale' => new external_value(PARAM_BOOL, 'Whether the bundle is on sale.'),
            'coursescount' => new external_value(PARAM_INT, 'Number of included courses.'),
            'totaldurationminutes' => new external_value(PARAM_INT, 'Auto-calculated total duration in minutes.'),
            'learninghoursfieldexists' => new external_value(PARAM_BOOL, 'Whether the learninghours course custom field exists.'),
            'learninghourssetcount' => new external_value(PARAM_INT, 'Courses with a learninghours value.'),
            'learninghoursmissingcount' => new external_value(PARAM_INT, 'Courses without a learninghours value.'),
            'learninghoursmissingcourses' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Course without a learninghours value.')
            ),
            'kpiassessments' => new external_value(PARAM_INT, 'Auto-calculated assessments count.'),
            'savingsamount' => new external_value(PARAM_FLOAT, 'Bundle savings amount.'),
            'savingspercent' => new external_value(PARAM_INT, 'Bundle savings percentage.'),
            'displaysavings' => new external_value(PARAM_TEXT, 'Formatted savings.'),
            'price' => new external_value(PARAM_FLOAT, 'Price.'),
            'compareat' => new external_value(PARAM_FLOAT, 'Compare-at price.'),
            'visibility' => new external_value(PARAM_TEXT, 'Visibility mode.'),
            'availstart' => new external_value(PARAM_TEXT, 'Availability start (Y-m-d).'),
            'availend' => new external_value(PARAM_TEXT, 'Availability end (Y-m-d).'),
            'featured' => new external_value(PARAM_BOOL, 'Featured badge.'),
            'bestseller' => new external_value(PARAM_BOOL, 'Bestseller badge.'),
            'trending' => new external_value(PARAM_BOOL, 'Trending badge.'),
            'skilllevel' => new external_value(PARAM_TEXT, 'Skill level.'),
            'language' => new external_value(PARAM_TEXT, 'Language.'),
            'hasprereq' => new external_value(PARAM_BOOL, 'Has prerequisites.'),
            'autoduration' => new external_value(PARAM_BOOL, 'Auto duration.'),
            'durationhours' => new external_value(PARAM_INT, 'Duration hours.'),
            'durationminutes' => new external_value(PARAM_INT, 'Duration minutes.'),
            'autoassessments' => new external_value(PARAM_BOOL, 'Auto assessments.'),
            'assessmentscount' => new external_value(PARAM_INT, 'Assessments count.'),
            'autooutline' => new external_value(PARAM_BOOL, 'Auto outline.'),
            'passgrade' => new external_value(PARAM_FLOAT, 'Pass grade.'),
            'passpolicy' => new external_value(PARAM_ALPHANUMEXT, 'Pass policy.'),
            'certenabled' => new external_value(PARAM_BOOL, 'Bundle certificate enabled.'),
            'certificatetemplateid' => new external_value(PARAM_INT, 'Selected Course Certificate template ID.'),
            'defaultcertificatetemplateid' => new external_value(
                PARAM_INT,
                'Store-wide default Course Certificate template ID.'
            ),
            'defaultcertificatetemplatename' => new external_value(
                PARAM_TEXT,
                'Store-wide default Course Certificate template name.'
            ),
            'coursecertificateavailable' => new external_value(PARAM_BOOL, 'Whether Moodle Course Certificate is available.'),
            'certificatetemplates' => self::options_structure(),
            'outline' => new external_multiple_structure(new external_single_structure([
                'title' => new external_value(PARAM_TEXT, 'Outline item title.'),
            ])),
            'mustpassids' => new external_multiple_structure(new external_value(PARAM_INT, 'Must-pass course ID.')),
            'courses' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course ID.'),
                'fullname' => new external_value(PARAM_TEXT, 'Course name.'),
            ])),
            'prereqcourses' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Prerequisite course ID.'),
                'fullname' => new external_value(PARAM_TEXT, 'Prerequisite course name.'),
            ])),
            'prereqcandidates' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Prerequisite candidate course ID.'),
                'fullname' => new external_value(PARAM_TEXT, 'Prerequisite candidate course name.'),
            ])),
            'tags' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Tag.')),
            'levels' => self::options_structure(),
            'languages' => self::options_structure(),
            'visibilities' => self::options_structure(),
            'passpolicies' => self::options_structure(),
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
     * Build the full list of content languages (value = language name, to match stored values).
     *
     * @return array
     */
    private static function language_options(): array {
        $langs = get_string_manager()->get_list_of_languages();
        asort($langs, SORT_LOCALE_STRING);
        $out = [];
        $seen = [];
        foreach ($langs as $name) {
            $name = trim((string) $name);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $out[] = ['value' => $name, 'label' => $name];
        }
        return $out;
    }

    /**
     * Whether the Moodle Course Certificate add-on and its template backend are available.
     *
     * @return bool
     */
    /**
     * Return Course Certificate templates visible to the current administrator.
     *
     * @return array
     */
    private static function certificate_template_options(): array {
        $options = [];
        foreach (certificate_template_service::options() as $id => $name) {
            $options[] = ['value' => (string)$id, 'label' => (string)$name];
        }
        return $options;
    }
}

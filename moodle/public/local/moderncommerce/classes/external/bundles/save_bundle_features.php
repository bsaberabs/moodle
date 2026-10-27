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
 * External API saving bundle merchandising metadata.
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
use local_moderncommerce\services\certificate_template_service;

/**
 * Save a bundle's advanced/merchandising metadata.
 *
 * Program prerequisites are saved alongside completion policy settings.
 */
class save_bundle_features extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'bundleid' => new external_value(PARAM_INT, 'Bundle product ID.'),
            'price' => new external_value(PARAM_FLOAT, 'Price.', VALUE_DEFAULT, 0),
            'compareat' => new external_value(PARAM_FLOAT, 'Compare-at price.', VALUE_DEFAULT, 0),
            'visibility' => new external_value(PARAM_TEXT, 'Visibility mode.', VALUE_DEFAULT, 'Public'),
            'availstart' => new external_value(PARAM_TEXT, 'Availability start (Y-m-d).', VALUE_DEFAULT, ''),
            'availend' => new external_value(PARAM_TEXT, 'Availability end (Y-m-d).', VALUE_DEFAULT, ''),
            'featured' => new external_value(PARAM_BOOL, 'Featured badge.', VALUE_DEFAULT, false),
            'bestseller' => new external_value(PARAM_BOOL, 'Bestseller badge.', VALUE_DEFAULT, false),
            'trending' => new external_value(PARAM_BOOL, 'Trending badge.', VALUE_DEFAULT, false),
            'skilllevel' => new external_value(PARAM_TEXT, 'Skill level.', VALUE_DEFAULT, ''),
            'language' => new external_value(PARAM_TEXT, 'Language.', VALUE_DEFAULT, ''),
            'hasprereq' => new external_value(PARAM_BOOL, 'Has prerequisites.', VALUE_DEFAULT, false),
            'autoduration' => new external_value(PARAM_BOOL, 'Auto duration.', VALUE_DEFAULT, true),
            'durationhours' => new external_value(PARAM_INT, 'Duration hours.', VALUE_DEFAULT, 0),
            'durationminutes' => new external_value(PARAM_INT, 'Duration minutes.', VALUE_DEFAULT, 0),
            'autoassessments' => new external_value(PARAM_BOOL, 'Auto assessments.', VALUE_DEFAULT, true),
            'assessmentscount' => new external_value(PARAM_INT, 'Assessments count.', VALUE_DEFAULT, 0),
            'autooutline' => new external_value(PARAM_BOOL, 'Auto outline.', VALUE_DEFAULT, true),
            'passgrade' => new external_value(PARAM_FLOAT, 'Pass grade.', VALUE_DEFAULT, 70),
            'passpolicy' => new external_value(PARAM_ALPHANUMEXT, 'Pass policy.', VALUE_DEFAULT, 'all_must_pass'),
            'certenabled' => new external_value(PARAM_BOOL, 'Bundle certificate enabled.', VALUE_DEFAULT, false),
            'certificatetemplateid' => new external_value(
                PARAM_INT,
                'Selected Moodle Course Certificate template ID.',
                VALUE_DEFAULT,
                0
            ),
            'outline' => new external_multiple_structure(
                new external_single_structure(['title' => new external_value(PARAM_TEXT, 'Outline item title.')]),
                'Outline items.',
                VALUE_DEFAULT,
                []
            ),
            'mustpassids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Must-pass course ID.'),
                'Must-pass course IDs.',
                VALUE_DEFAULT,
                []
            ),
            'prereqids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Prerequisite Moodle course ID.'),
                'Prerequisite course IDs.',
                VALUE_DEFAULT,
                []
            ),
            'tags' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Tag.'),
                'Tags.',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $bundleid Bundle ID.
     * @param float $price Price.
     * @param float $compareat Compare-at.
     * @param string $visibility Visibility.
     * @param string $availstart Avail start.
     * @param string $availend Avail end.
     * @param bool $featured Featured.
     * @param bool $bestseller Bestseller.
     * @param bool $trending Trending.
     * @param string $skilllevel Skill level.
     * @param string $language Language.
     * @param bool $hasprereq Has prereq.
     * @param bool $autoduration Auto duration.
     * @param int $durationhours Hours.
     * @param int $durationminutes Minutes.
     * @param bool $autoassessments Auto assessments.
     * @param int $assessmentscount Assessments count.
     * @param bool $autooutline Auto outline.
     * @param float $passgrade Pass grade.
     * @param string $passpolicy Pass policy.
     * @param bool $certenabled Certificate enabled.
     * @param int $certificatetemplateid Course Certificate template ID.
     * @param array $outline Outline items.
     * @param array $mustpassids Must-pass course IDs.
     * @param array $tags Tags.
     * @return array
     */
    public static function execute(
        int $bundleid,
        float $price = 0,
        float $compareat = 0,
        string $visibility = 'Public',
        string $availstart = '',
        string $availend = '',
        bool $featured = false,
        bool $bestseller = false,
        bool $trending = false,
        string $skilllevel = '',
        string $language = '',
        bool $hasprereq = false,
        bool $autoduration = true,
        int $durationhours = 0,
        int $durationminutes = 0,
        bool $autoassessments = true,
        int $assessmentscount = 0,
        bool $autooutline = true,
        float $passgrade = 70,
        string $passpolicy = 'all_must_pass',
        bool $certenabled = false,
        int $certificatetemplateid = 0,
        array $outline = [],
        array $mustpassids = [],
        array $prereqids = [],
        array $tags = []
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'bundleid' => $bundleid, 'price' => $price, 'compareat' => $compareat, 'visibility' => $visibility,
            'availstart' => $availstart, 'availend' => $availend, 'featured' => $featured, 'bestseller' => $bestseller,
            'trending' => $trending, 'skilllevel' => $skilllevel, 'language' => $language, 'hasprereq' => $hasprereq,
            'autoduration' => $autoduration, 'durationhours' => $durationhours, 'durationminutes' => $durationminutes,
            'autoassessments' => $autoassessments, 'assessmentscount' => $assessmentscount, 'autooutline' => $autooutline,
            'passgrade' => $passgrade, 'passpolicy' => $passpolicy, 'certenabled' => $certenabled,
            'certificatetemplateid' => $certificatetemplateid,
            'outline' => $outline, 'mustpassids' => $mustpassids, 'prereqids' => $prereqids, 'tags' => $tags,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $bundle = $DB->get_record_select(
            'local_moderncommerce_products',
            "id = :id AND producttype IN (:bundle, :program)",
            ['id' => $params['bundleid'], 'bundle' => 'bundle', 'program' => 'program'],
            'id, producttype',
            MUST_EXIST
        );

        $allowedpasspolicies = ['all_must_pass', 'weighted_avg', 'any_pass'];
        if (!in_array($params['passpolicy'], $allowedpasspolicies, true)) {
            return [
                'success' => false,
                'message' => get_string('invalidpasspolicy', 'local_moderncommerce'),
                'warnings' => [],
            ];
        }

        $mustpassids = [];
        if ($bundle->producttype === 'program' && $params['passpolicy'] !== 'all_must_pass') {
            $includedcourseids = array_map(
                static fn($mapping): int => (int)$mapping->courseid,
                bundle_api::get_courses((int)$bundle->id)
            );
            $requestedmustpassids = array_values(array_unique(array_filter(
                array_map('intval', $params['mustpassids']),
                static fn(int $courseid): bool => $courseid > 0
            )));
            $mustpassids = array_values(array_intersect($requestedmustpassids, $includedcourseids));
        }

        if ($bundle->producttype === 'program' && $params['certenabled']) {
            $available = certificate_template_service::available();
            if (!$available) {
                return [
                    'success' => false,
                    'message' => get_string('coursecertificateunavailableprogram', 'local_moderncommerce'),
                    'warnings' => [],
                ];
            }
            $effectivetemplateid = certificate_template_service::resolve($params['certificatetemplateid']);
            if ($effectivetemplateid <= 0) {
                return [
                    'success' => false,
                    'message' => get_string('certificatetemplaterequired', 'local_moderncommerce'),
                    'warnings' => [],
                ];
            }
            $templates = certificate_template_service::options();
            if (
                $params['certificatetemplateid'] > 0
                && !array_key_exists($params['certificatetemplateid'], $templates)
            ) {
                return [
                    'success' => false,
                    'message' => get_string('certificatetemplateinvalid', 'local_moderncommerce'),
                    'warnings' => [],
                ];
            }
        }

        $outlineitems = [];
        $position = 0;
        foreach ($params['outline'] as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $outlineitems[] = ['title' => $title, 'position' => $position++];
        }

        $payload = [
            'visibility_mode' => $params['visibility'],
            'avail_start' => $params['availstart'],
            'avail_end' => $params['availend'],
            'badge_featured' => $params['featured'] ? 1 : 0,
            'badge_bestseller' => $params['bestseller'] ? 1 : 0,
            'badge_trending' => $params['trending'] ? 1 : 0,
            'price' => $params['price'],
            'compare_at' => $params['compareat'],
            'skill_level' => $params['skilllevel'] !== '' ? $params['skilllevel'] : null,
            'language' => $params['language'] !== '' ? $params['language'] : null,
            'has_prereq' => $params['hasprereq'] ? 1 : 0,
            'auto_duration' => $params['autoduration'] ? 1 : 0,
            'duration_hours' => $params['durationhours'],
            'duration_minutes' => $params['durationminutes'],
            'auto_assessments' => $params['autoassessments'] ? 1 : 0,
            'assessments_count' => $params['assessmentscount'],
            'auto_outline' => $params['autooutline'] ? 1 : 0,
            'pass_grade' => $params['passgrade'],
            'pass_policy' => $params['passpolicy'],
            'bundle_cert_enabled' => $bundle->producttype === 'program' && $params['certenabled'] ? 1 : 0,
            'certificate_template_id' => $bundle->producttype === 'program'
                ? $params['certificatetemplateid']
                : 0,
            'outline_items' => $outlineitems,
            'mustpass_courseids' => $mustpassids,
            'prereq_courseids' => $bundle->producttype === 'program'
                ? array_values(array_filter(array_map('intval', $params['prereqids'])))
                : [],
            'tags' => array_values(array_filter(array_map(static function ($t): string {
                return trim((string) $t);
            }, $params['tags']))),
        ];

        try {
            bundle_meta_service::save_meta((int) $params['bundleid'], $payload);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'warnings' => []];
        }

        return [
            'success' => true,
            'message' => get_string('changessaved'),
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
            'success' => new external_value(PARAM_BOOL, 'Whether the metadata was saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }
}

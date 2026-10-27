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
 * Bundle details page renderable.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;


use renderable;
use templatable;
use core\output\renderer_base;
use moodle_url;
use local_moderncommerce\api\bundle_api;
use local_moderncommerce\services\price_resolver;
use local_moderncommerce\services\pricing_service;
use local_moderncommerce\services\meta_service;
/**
 * Bundle details page renderable class.
 */
class bundle_details_page implements renderable, templatable {
    /** @var object Bundle record */
    protected $bundle;

    /** @var array Bundle courses */
    protected $courses;

    /** @var bool User has purchased bundle */
    protected $purchased;

    /** @var array Owned course IDs */
    protected $ownedcourses = [];

    /** @var float Owned courses value */
    protected $ownedcoursesvalue = 0;

    /** @var float Adjusted price */
    protected $adjustedprice = 0;

    /** @var object Bundle meta */
    protected $bundlemeta;

    /**
     * Constructor.
     *
     * @param object $bundle Bundle record
     */
    public function __construct(object $bundle) {
        global $USER;

        $this->bundle = $bundle;
        $this->courses = bundle_api::get_courses($bundle->id);
        $this->purchased = bundle_api::is_purchased($bundle->id);
        $this->bundlemeta = \local_moderncommerce\services\bundle_meta_service::get_meta($bundle->id);

        // Calculate owned courses and adjusted pricing.
        $this->calculate_owned_courses();
    }

    /**
     * Calculate owned courses and adjusted pricing.
     */
    protected function calculate_owned_courses(): void {

        global $USER;
        $bundleprice = $this->get_bundle_price();
        if (empty($this->courses)) {
            $this->adjustedprice = $bundleprice;
            return;
        }

        foreach ($this->courses as $bundlecourse) {
            $isenrolled = \local_moderncommerce_is_user_enrolled((int)$USER->id, (int)$bundlecourse->courseid);
            if ($isenrolled) {
                $this->ownedcourses[] = $bundlecourse->courseid;
                $coursepricing = pricing_service::get_course_pricing((int)$bundlecourse->courseid);
                if ($coursepricing && empty($coursepricing->is_free)) {
                    $this->ownedcoursesvalue += (float)$coursepricing->final_price;
                }
            }
        }
        $this->adjustedprice = max(0, $bundleprice - $this->ownedcoursesvalue);
    }

    /**
     * Resolve bundle price from the supplied record or canonical price table.
     *
     * @return float Bundle price.
     */
    protected function get_bundle_price(): float {

        global $DB;
        $resolved = price_resolver::resolve_for_product((int)$this->bundle->id, 1, true);
        if ($resolved) {
            return (float)$resolved->unitprice;
        }

        $price = $DB->get_field_sql("SELECT amount
               FROM {local_moderncommerce_product_prices}
              WHERE productid = :productid
                AND enabled = 1
                AND pricetype = :pricetype
           ORDER BY amount ASC, id ASC", [
                'productid' => (int)$this->bundle->id, 'pricetype' => 'regular',
            ], IGNORE_MULTIPLE);
        return $price === false ? 0.0 : (float)$price;
    }
    /**
     * Prepare course data for template.
     *
     * @param object $bundlecourse Bundle course record
     * @return array|null Course template data
     */
    protected function prepare_course_data(object $bundlecourse): ?array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $bundlecourse->courseid]);
        if (!$course) {
            return null;
        }

        // Get pricing info.
        $pricing = pricing_service::get_course_pricing($course->id);
        $isfree = !$pricing || !empty($pricing->is_free);
        $regularprice = 0;
        $saleprice = 0;
        $hassale = false;

        if ($pricing) {
            $regularprice = (float)$pricing->price;
            if (!empty($pricing->has_sale)) {
                $saleprice = (float)$pricing->saleprice;
                $hassale = true;
            }
        }

        // Category.
        $category = $DB->get_record('course_categories', ['id' => $course->category]);
        // React escapes on render, so hand it unescaped text or "&" arrives as "&amp;".
        $categoryname = $category ? format_string($category->name, true, ['escape' => false]) : '';

        // Meta data.
        $coursemeta = meta_service::get_course_meta($course->id);
        $duration = '';
        if ($coursemeta) {
            $durationdata = meta_service::get_duration($course->id);
            if ($durationdata) {
                $duration = meta_service::format_duration($durationdata['hours'], $durationdata['minutes']);
            }
        }

        $skilllevel = $coursemeta ? $coursemeta->skill_level : null;
        $quizzescount = $coursemeta ? $coursemeta->quizzes_count : 0;

        // Activity count.
        $modinfo = get_fast_modinfo($course);
        $activitycount = count($modinfo->get_cms());

        // Summary.
        $summary = !empty($course->summary) ? strip_tags($course->summary) : '';
        $summary = strlen($summary) > 150 ? substr($summary, 0, 150) . '...' : $summary;

        // Enrolled status.
        $isenrolled = in_array($course->id, $this->ownedcourses);
        $imageurl = \local_moderncommerce_get_course_image_url($course->id);

        return [
            'id' => $course->id,
            'courseid' => $course->id,
            'courseurl' => (new moodle_url('/local/moderncommerce/course_details.php', [
                'id' => $course->id,
            ]))->out(false),
            'courseviewurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'imageurl' => $imageurl,
            'hasimage' => !empty($imageurl),
            'name' => format_string($course->fullname, true, ['escape' => false]),
            'summary' => $summary,
            'categoryname' => $categoryname,
            'duration' => $duration,
            'activitycount' => $activitycount,
            'level' => $skilllevel ?: '',
            'quizzescount' => $quizzescount,
            'rating' => '', 'isfree' => $isfree,
            'isenrolled' => $isenrolled,
            'hassale' => $hassale,
            'regularprice' => pricing_service::format_price($regularprice),
            'saleprice' => pricing_service::format_price($saleprice),
            'enrolledtext' => get_string('enrolled', 'local_moderncommerce'),
        ];
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // Badge flags.
        $showfeatured = !empty($this->bundlemeta['badge_featured']) ||
            (isset($this->bundle->featured) && !empty($this->bundle->featured));
        $showbestseller = !empty($this->bundlemeta['badge_bestseller']) ||
            (isset($this->bundle->bestseller) && !empty($this->bundle->bestseller));

        $hasownedcourses = count($this->ownedcourses) > 0;
        $accessallcourses = !empty($this->courses) && count($this->ownedcourses) === count($this->courses);
        $bundleprice = $this->get_bundle_price();
        $resolvedprice = price_resolver::resolve_for_product((int)$this->bundle->id, 1, true);
        $regularprice = $resolvedprice ? (float)$resolvedprice->regularprice : $bundleprice;
        $saleprice = $resolvedprice && !empty($resolvedprice->has_sale) ? (float)$resolvedprice->saleprice : null;
        $hassale = $saleprice !== null && $saleprice < $regularprice;
        $savings = $hassale ? max(0, $regularprice - $saleprice) : 0;
        $imageurl = !empty($this->bundle->imageurl)
            ? (string)$this->bundle->imageurl
            : \local_moderncommerce_get_bundle_image_url((int)$this->bundle->id);
        $isavailable = !empty($resolvedprice)
            && !empty($resolvedprice->enabled)
            && !empty($resolvedprice->has_stock)
            && !empty($this->courses);

        $data = [
            'bundleid' => $this->bundle->id,
            'name' => format_string($this->bundle->name, true, ['escape' => false]),
            'isprogram' => !empty($this->bundle->isprogram),
            'typelabel' => !empty($this->bundle->isprogram)
                ? get_string('program', 'local_moderncommerce')
                : get_string('bundle', 'local_moderncommerce'),
            'showfeatured' => $showfeatured,
            'showbestseller' => $showbestseller,
            'shortdescription' => !empty($this->bundle->shortdescription)
                ? format_text($this->bundle->shortdescription, FORMAT_PLAIN)
                : '',
            'description' => !empty($this->bundle->description)
                ? format_text($this->bundle->description, FORMAT_PLAIN)
                : '',
            'hasshortdescription' => !empty($this->bundle->shortdescription),
            'purchased' => $this->purchased,
            'ispurchased' => $this->purchased,
            'price' => pricing_service::format_price($bundleprice),
            'adjustedprice' => pricing_service::format_price($this->adjustedprice),
            'hasownedcourses' => $hasownedcourses,
            'accessallcourses' => $accessallcourses,
            'ownedcoursescount' => count($this->ownedcourses),
            'totalcoursescount' => count($this->courses),
            'coursecount' => count($this->courses),
            'hascourses' => !empty($this->courses),
            'isavailable' => $isavailable,
            'currencysymbol' => '',
        ];
        $data['bundle'] = [
            'id' => (int)$this->bundle->id,
            'name' => format_string($this->bundle->name, true, ['escape' => false]),
            'description' => !empty($this->bundle->description)
                ? format_text($this->bundle->description, FORMAT_PLAIN)
                : '',
            'shortdescription' => !empty($this->bundle->shortdescription)
                ? format_text($this->bundle->shortdescription, FORMAT_PLAIN)
                : '',
            'type' => !empty($this->bundle->isprogram) ? 'program' : 'bundle',
            'isprogram' => !empty($this->bundle->isprogram),
            'coursecount' => count($this->courses),
            'imageurl' => $imageurl,
            'hasimage' => !empty($imageurl),
        ];
        $data['price'] = [
            'current' => pricing_service::format_price($this->adjustedprice),
            'original' => pricing_service::format_price($regularprice),
            'hassale' => $hassale,
            'saleprice' => pricing_service::format_price($saleprice ?? $bundleprice),
            'savings' => $savings > 0 ? pricing_service::format_price($savings) : '',
        ];

        // Add URLs.
        $data['addtocarturl'] = (new moodle_url('/local/moderncommerce/cart.php', [
            'action' => 'add',
            'bundleid' => $this->bundle->id,
            'sesskey' => sesskey(),
        ]))->out(false);

        $data['checkouturl'] = (new moodle_url('/local/moderncommerce/checkout.php', [
            'bundleid' => $this->bundle->id,
        ]))->out(false);

        $data['catalogurl'] = (new moodle_url('/local/moderncommerce/index.php'))->out(false);

        // Prepare courses.
        $courses = [];
        foreach ($this->courses as $bundlecourse) {
            $coursedata = $this->prepare_course_data($bundlecourse);
            if ($coursedata) {
                $courses[] = $coursedata;
            }
        }
        $data['courses'] = $courses;

        return $data;
    }
}

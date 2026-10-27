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

namespace local_moderncommerce\output;


use renderable;
use templatable;
use moodle_url;
use local_moderncommerce\api\bundle_api;
use local_moderncommerce\services\category_service;
use local_moderncommerce\services\pricing_service;
use local_moderncommerce\services\meta_service;
use local_moderncommerce\services\review_service;
/**
 * Catalog page renderable.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog_page implements renderable, templatable {
    /** @var array Catalog configuration from admin settings. */
    protected $config;

    /**
     * Constructor.
     *
     * @param array $config Optional configuration overrides.
     */
    public function __construct(array $config = []) {
        $this->config = $config;
    }

    /**
     * Export data for template.
     *
     * @param mixed $output
     * @return array
     */
    public function export_for_template($output): array {

        global $DB, $CFG;

        // Get settings from admin config.
        $title = get_config('local_moderncommerce', 'catalog_title');
        if (empty($title)) {
            $title = get_string('catalog', 'local_moderncommerce');
        }

        $perpage = (int) get_config('local_moderncommerce', 'catalog_perpage');
        if ($perpage < 6 || $perpage > 36) {
            $perpage = 12;
        }

        $sidebarposition = get_config('local_moderncommerce', 'catalog_sidebarposition');
        if (empty($sidebarposition)) {
            $sidebarposition = 'left';
        }

        $bgcolor = get_config('local_moderncommerce', 'catalog_bgcolor');
        $margintop = (int) get_config('local_moderncommerce', 'catalog_margintop');
        $marginbottom = (int) get_config('local_moderncommerce', 'catalog_marginbottom');

        // Get global currency display settings.
        $currencyconfig = pricing_service::get_currency_config();
        $currencysymbol = $currencyconfig->symbol;
        // Get courses with active canonical product pricing.
        $sql = "SELECT c.id, c.fullname, c.summary, c.summaryformat,
                       p.id AS productid, p.timecreated as pricingcreated,
                       pr.id AS priceid, pr.amount AS price, pr.compareamount
                  FROM {course} c
                  JOIN {local_moderncommerce_product_courses} pc ON pc.courseid = c.id
                  JOIN {local_moderncommerce_products} p ON p.id = pc.productid
                  JOIN {local_moderncommerce_product_prices} pr ON pr.productid = p.id
                 WHERE pr.enabled = 1
                   AND pr.pricetype = :pricetype
                   AND pc.relationtype = :relationtype
                   AND p.producttype = :producttype
                   AND p.status = :status
                   AND p.visible = 1
                   AND c.visible = 1
                   AND c.id <> :siteid
              ORDER BY c.fullname ASC, p.timecreated DESC, p.id DESC, pr.id ASC";
        $recordset = $DB->get_recordset_sql($sql, [
            'pricetype' => 'regular',
            'relationtype' => 'included',
            'producttype' => 'course',
            'status' => 'active',
            'siteid' => SITEID,
        ]);
        $records = [];
        foreach ($recordset as $rec) {
            $courseid = (int) $rec->id;
            if (!isset($records[$courseid])) {
                $records[$courseid] = $rec;
            }
        }
        $recordset->close();
        // Get the Modern Commerce product categories for the filter. Moodle course categories
        // organise courses inside Moodle; the storefront filters on the merchant's own categories,
        // which cover bundles and programs too. The admin's drag-and-drop order is preserved here.
        $categorylist = category_service::get_visible_categories();

        // Bundles and programs are products in their own right, so fetch them up front and resolve
        // every product's category in a single query shared by both passes below.
        $bundles = bundle_api::get_all([
            'visible' => 1, 'status' => 'active',
        ]);
        $bundles = $this->dedupe_catalog_bundles($bundles);

        $productids = [];
        foreach ($records as $rec) {
            $productids[] = (int) $rec->productid;
        }
        foreach ($bundles as $bundle) {
            $productids[] = (int) $bundle->id;
        }
        $categorymap = category_service::get_primary_category_map($productids);

        // Build items array.
        $items = [];
        $reviewsummaries = review_service::get_course_summaries(array_map(static function ($record): int {
            return (int)$record->id;
        }, $records));

        // Add courses.
        foreach ($records as $rec) {
            $courseid = (int) $rec->id;

            // Get course thumbnail.
            $thumb = '';
            if (function_exists('local_moderncommerce_get_course_image_url')) {
                $thumb = \local_moderncommerce_get_course_image_url($courseid);
            }

            $displaydata = [];
            $courseduration = '';
            $courseprice = '<span class="mc-price">' . pricing_service::format_price((float)$rec->price) . '</span>';
            $stockstatus = '';
            $summary = $reviewsummaries[$courseid] ?? ['avgrating' => 0, 'reviewcount' => 0];
            $rating = (float)$summary['avgrating'];
            $reviewcount = (int)$summary['reviewcount'];
            $reviewhtml = $this->render_review_html($rating, $reviewcount);
            $level = '';
            $instructor = '';
            $bestseller = false;
            $coursemeta = meta_service::get_course_meta($courseid);
            if ($coursemeta) {
                $durationdata = meta_service::get_duration($courseid);
                if ($durationdata) {
                    $courseduration = meta_service::format_duration((int)$durationdata['hours'], (int)$durationdata['minutes']);
                }
                $level = !empty($coursemeta->skill_level) ? (string)$coursemeta->skill_level : '';
                $bestseller = !empty($coursemeta->badge_bestseller);
            }
            // Get the assigned product category. Unmapped products, and products whose category has
            // been hidden, fall back to no category so they stay out of every category filter.
            $productcategory = $categorymap[(int) $rec->productid] ?? null;

            // Get price value for filtering.
            $pricevalue = 0;
            $originalprice = 0;
            if ($pricevalue <= 0 && $rec->price !== null) {
                $pricevalue = (float) $rec->price;
            }
            if ($originalprice <= 0 && $rec->compareamount !== null && (float) $rec->compareamount > $pricevalue) {
                $originalprice = (float) $rec->compareamount;
            }
            if ($courseprice === '') {
                $courseprice = '<span class="mc-price">' . pricing_service::format_price($pricevalue) . '</span>';
            }
            $items[] = [
                'id' => $courseid,
                'productid' => (int)$rec->productid,
                'itemtype' => 'course',
                'thumb' => $thumb,
                'alt' => format_string($rec->fullname),
                'title' => format_string($rec->fullname),
                'instructor' => $instructor,
                'courseprice' => $courseprice,
                'pricevalue' => $pricevalue,
                'originalprice' => $originalprice,
                'displayoriginalprice' => $originalprice > 0 ? pricing_service::format_price($originalprice) : '',
                'hasoriginalprice' => $originalprice > 0,
                'stockstatus' => $stockstatus,
                'courseduration' => $courseduration,
                'reviewhtml' => $reviewhtml,
                'rating' => $rating,
                'reviewcount' => $reviewcount,
                'level' => $level,
                'category' => $productcategory['name'] ?? '',
                'categoryid' => $productcategory['id'] ?? 0,
                'coursetype' => 'Course',
                'isbundle' => false,
                'isprogram' => false,
                'bestseller' => $bestseller,
                'detailsurl' => (new moodle_url('/local/moderncommerce/course_details.php', ['id' => $courseid]))->out(false),
                'timecreated' => (int) ($rec->pricingcreated ?? 0),
            ];
        }

        // Add bundles and programs (already fetched and deduped above).
        foreach ($bundles as $bundle) {
            $bundleid = (int) $bundle->id;
            $isprogram = !empty($bundle->isprogram);
            // The bundle id is its product id, so it categorises exactly like any other product.
            $bundlecategory = $categorymap[$bundleid] ?? null;

            // Get bundle image.
            $thumb = '';
            if (function_exists('local_moderncommerce_get_bundle_image_url')) {
                $thumb = \local_moderncommerce_get_bundle_image_url($bundleid);
            }
            if (empty($thumb) && !empty($bundle->imageurl)) {
                $thumb = $bundle->imageurl;
            }
            if (empty($thumb) && function_exists('local_moderncommerce_get_placeholder_image_url')) {
                $thumb = \local_moderncommerce_get_placeholder_image_url($bundleid);
            }
            // Get bundle meta.
            $bundlemeta = null;
            $level = '';
            $bestseller = false;
            if (class_exists('\local_moderncommerce\services\bundle_meta_service')) {
                $bundlemeta = \local_moderncommerce\services\bundle_meta_service::get_meta($bundleid);
                if (!empty($bundlemeta['skill_level'])) {
                    $level = $bundlemeta['skill_level'];
                }
                if (!empty($bundlemeta['badge_bestseller'])) {
                    $bestseller = true;
                }
            }

            // Calculate bundle duration.
            $courseduration = '';
            if (!empty($bundlemeta['dur_hours']) || !empty($bundlemeta['dur_mins'])) {
                $hours = !empty($bundlemeta['dur_hours']) ? (int) $bundlemeta['dur_hours'] : 0;
                $mins = !empty($bundlemeta['dur_mins']) ? (int) $bundlemeta['dur_mins'] : 0;
                if ($hours > 0 || $mins > 0) {
                    $courseduration = ($hours > 0 ? $hours . 'h ' : '') . ($mins > 0 ? $mins . 'm' : '');
                }
            }

            // Get member courses (reused for the count and the aggregate rating below).
            $membercourses = bundle_api::get_courses($bundleid);
            $coursecount = count($membercourses);
            if (empty($courseduration) && $coursecount > 0) {
                $courseduration = get_string(
                    $coursecount === 1 ? 'coursecountsingle' : 'coursecountplural',
                    'local_moderncommerce',
                    $coursecount
                );
            }

            // Price formatting.
            $now = time();
            $pricevalue = floatval($bundle->price);
            $originalprice = 0;
            $displayprice = $pricevalue;

            $saleon = !empty($bundle->saleprice) &&
                      (empty($bundle->salestartdate) || $bundle->salestartdate <= $now) &&
                      (empty($bundle->saleenddate) || $bundle->saleenddate >= $now);
            if ($saleon) {
                $originalprice = $pricevalue;
                $displayprice = floatval($bundle->saleprice);
                $pricevalue = $displayprice;
            }

            $courseprice = '<span class="mc-price">' . pricing_service::format_price($displayprice) . '</span>';
            $coursetype = $isprogram ? 'Program' : 'Bundle';

            // Aggregate the bundle/program rating across its member courses.
            $membercourseids = array_map(static function ($course): int {
                return (int)$course->courseid;
            }, $membercourses);
            $bundlesummary = review_service::get_aggregate_summary($membercourseids);
            $bundlerating = (float)$bundlesummary['avgrating'];
            $bundlereviewcount = (int)$bundlesummary['reviewcount'];

            $items[] = [
                'id' => $bundleid,
                'productid' => $bundleid,
                'itemtype' => $isprogram ? 'program' : 'bundle',
                'thumb' => $thumb,
                'alt' => format_string($bundle->name),
                'title' => format_string($bundle->name),
                'instructor' => '',
                'courseprice' => $courseprice,
                'pricevalue' => $pricevalue,
                'originalprice' => $originalprice,
                'displayoriginalprice' => $originalprice > 0 ? pricing_service::format_price($originalprice) : '',
                'hasoriginalprice' => $originalprice > 0,
                'stockstatus' => '',
                'courseduration' => $courseduration,
                'reviewhtml' => $this->render_review_html($bundlerating, $bundlereviewcount),
                'rating' => $bundlerating,
                'reviewcount' => $bundlereviewcount,
                'level' => $level,
                'category' => $bundlecategory['name'] ?? '',
                'categoryid' => $bundlecategory['id'] ?? 0,
                'coursetype' => $coursetype,
                'isbundle' => !$isprogram,
                'isprogram' => $isprogram,
                'bestseller' => $bestseller,
                'detailsurl' => (new moodle_url('/local/moderncommerce/bundle_details.php', ['id' => $bundleid]))->out(false),
                'timecreated' => (int) $bundle->timecreated,
            ];
        }
        $items = $this->dedupe_catalog_items($items);
        // Calculate max price.
        $maxprice = 0;
        foreach ($items as $item) {
            if (!empty($item['pricevalue']) && $item['pricevalue'] > $maxprice) {
                $maxprice = $item['pricevalue'];
            }
        }
        $maxprice = max(300, (int) ceil($maxprice / 100) * 100);

        // Filter options.
        $coursetypes = [
            ['value' => 'Course', 'label' => get_string('catalog_type_course', 'local_moderncommerce')],
            ['value' => 'Bundle', 'label' => get_string('catalog_type_bundle', 'local_moderncommerce')],
            ['value' => 'Program', 'label' => get_string('catalog_type_program', 'local_moderncommerce')],
        ];

        $levels = [
            ['value' => 'Beginner', 'label' => get_string('catalog_level_beginner', 'local_moderncommerce')],
            ['value' => 'Intermediate', 'label' => get_string('catalog_level_intermediate', 'local_moderncommerce')],
            ['value' => 'Advanced', 'label' => get_string('catalog_level_advanced', 'local_moderncommerce')],
            ['value' => 'All Levels', 'label' => get_string('catalog_level_all', 'local_moderncommerce')],
        ];

        $ratings = [
            ['value' => 4.5, 'label' => get_string('catalog_rating_atleast', 'local_moderncommerce', '4.5'), 'stars' => 5],
            ['value' => 4.0, 'label' => get_string('catalog_rating_atleast', 'local_moderncommerce', '4.0'), 'stars' => 4],
            ['value' => 3.5, 'label' => get_string('catalog_rating_atleast', 'local_moderncommerce', '3.5'), 'stars' => 4],
            ['value' => 3.0, 'label' => get_string('catalog_rating_atleast', 'local_moderncommerce', '3.0'), 'stars' => 3],
        ];

        $data = [
            'title' => $title,
            'courses' => $items,
            'courses_json' => json_encode($items),
            'categories' => $categorylist,
            'coursetypes' => $coursetypes,
            'levels' => $levels,
            'ratings' => $ratings,
            'totalcourses' => count($items),
            'perpage' => $perpage,
            'maxprice' => $maxprice,
            'currencysymbol' => $currencysymbol,
            'currencyposition' => $currencyconfig->position,
            'currencydecimals' => $currencyconfig->decimals,
            'currencythousand' => $currencyconfig->thousand,
            'currencydecimal' => $currencyconfig->decimal,
            'minpricedisplay' => pricing_service::format_price(0),
            'maxpricedisplay' => pricing_service::format_price($maxprice),
            'sidebarposition' => $sidebarposition,
            'sidebarright' => ($sidebarposition === 'right'),
            'searchplaceholder' => get_string('catalog_search_placeholder', 'local_moderncommerce'),
            'filtertitle' => get_string('catalog_filter_title', 'local_moderncommerce'),
            'coursetypetitle' => get_string('catalog_coursetype_title', 'local_moderncommerce'),
            'topictitle' => get_string('catalog_topic_title', 'local_moderncommerce'),
            'ratingstitle' => get_string('catalog_ratings_title', 'local_moderncommerce'),
            'leveltitle' => get_string('catalog_level_title', 'local_moderncommerce'),
            'pricetitle' => get_string('catalog_price_title', 'local_moderncommerce'),
            'sortby' => get_string('catalog_sortby', 'local_moderncommerce'),
            'courseslabel' => get_string('courses'),
            'bestsellerlabel' => get_string('bestseller', 'local_moderncommerce'),
            'bundlelabel' => get_string('bundle', 'local_moderncommerce'),
            'programlabel' => get_string('program', 'local_moderncommerce'),
            'viewdetailslabel' => get_string('viewdetails', 'local_moderncommerce'),
            'mostpopular' => get_string('catalog_sort_popular', 'local_moderncommerce'),
            'newest' => get_string('catalog_sort_newest', 'local_moderncommerce'),
            'pricelowtohigh' => get_string('catalog_sort_pricelow', 'local_moderncommerce'),
            'pricehightolow' => get_string('catalog_sort_pricehigh', 'local_moderncommerce'),
            'nocourses' => get_string('catalog_no_courses', 'local_moderncommerce'),
            'clearfilters' => get_string('catalog_clear_filters', 'local_moderncommerce'),
            'showresults' => get_string('catalog_show_results', 'local_moderncommerce'),
            'results' => get_string('catalog_results', 'local_moderncommerce'),
            'addtocart' => get_string('catalog_add_to_cart', 'local_moderncommerce'),
            'andup' => get_string('catalog_and_up', 'local_moderncommerce'),
            'previous' => get_string('catalog_previous', 'local_moderncommerce'),
            'next' => get_string('catalog_next', 'local_moderncommerce'),
            'page' => get_string('catalog_page', 'local_moderncommerce'),
            'pagexofy' => get_string('catalog_page_x_of_y', 'local_moderncommerce'),
            'of' => get_string('catalog_of', 'local_moderncommerce'),
            'showlabel' => get_string('catalog_show', 'local_moderncommerce'),
            'sesskey' => sesskey(),
            'isloggedin' => isloggedin() && !isguestuser(),
            'loginurl' => (new moodle_url('/login/index.php', [
                'wantsurl' => (new moodle_url('/local/moderncommerce/index.php'))->out(false),
            ]))->out(false),
            // Use CCP Email First Auth signup if enabled, otherwise standard signup.
            'registerurl' => (is_enabled_auth('ccp')
                ? new moodle_url('/auth/ccp/signup.php')
                : new moodle_url('/login/signup.php'))->out(false),
            'loginrequiredtitle' => get_string('loginrequired', 'local_moderncommerce'),
            'loginrequiredmessage' => get_string('loginrequiredmessage', 'local_moderncommerce'),
            'loginbtn' => get_string('login'),
            'registerbtn' => get_string('startsignup'),
            'closelabel' => get_string('close', 'local_moderncommerce'),
            'noresultshelp' => get_string('catalog_no_results_help', 'local_moderncommerce'),
            'failedtoaddtocart' => get_string('failedtoaddtocart', 'local_moderncommerce'),
        ];

        // Section style.
        $sectionstyle = '';
        if (!empty($bgcolor)) {
            $raw = trim($bgcolor);
            if (preg_match('/^#?[0-9a-fA-F]{3,8}$/', $raw)) {
                if ($raw[0] !== '#') {
                    $raw = '#' . $raw;
                }
                $sectionstyle .= 'background-color: ' . $raw . '; ';
            } else if (preg_match('/^rgba?\([0-9.,\s]+\)$/', $raw)) {
                $sectionstyle .= 'background-color: ' . $raw . '; ';
            }
        }

        if ($margintop > 0) {
            $sectionstyle .= 'margin-top: ' . $margintop . 'px; ';
        }
        if ($marginbottom > 0) {
            $sectionstyle .= 'margin-bottom: ' . $marginbottom . 'px; ';
        }

        if ($sectionstyle !== '') {
            $data['section_style'] = trim($sectionstyle);
        }

        return $data;
    }

    /**
     * Build the star-rating markup for a catalog card.
     *
     * Returns an empty string when there are no visible reviews so the card hides the rating
     * row entirely. Used by the legacy mustache catalog; the React catalog reads the raw
     * rating/reviewcount fields instead.
     *
     * @param float $rating Average rating.
     * @param int $reviewcount Visible review count.
     * @return string Star-rating HTML, or empty string when there are no reviews.
     */
    private function render_review_html(float $rating, int $reviewcount): string {
        if ($reviewcount <= 0) {
            return '';
        }

        $rounded = (int)round($rating);
        $stars = '';
        for ($star = 1; $star <= 5; $star++) {
            $icon = $star <= $rounded ? 'bi-star-fill' : 'bi-star';
            $stars .= '<i class="bi ' . $icon . '" aria-hidden="true"></i>';
        }

        return '<span class="mc-rating-stars text-warning">' . $stars . '</span>'
            . '<span class="mc-rating-value">' . number_format($rating, 1) . '</span>'
            . '<span class="mc-rating-count">(' . $reviewcount . ')</span>';
    }

    /**
     * Collapse duplicated bundle/program products for the public catalog.
     *
     * The product table can contain historical/sample rows with different slugs but the same merchandised bundle name.
     * For buyers, those are duplicate cards. Keep the first record returned by bundle_api::get_all(), which is ordered
     * newest-first, and leave the admin/product data untouched.
     *
     * @param array $bundles Bundle/program product records.
     * @return array Deduplicated bundle/program records.
     */
    private function dedupe_catalog_bundles(array $bundles): array {

        $deduped = [];
        $seen = [];
        foreach ($bundles as $bundle) {
            $type = !empty($bundle->isprogram) ? 'program' : 'bundle';
            $name = trim((string)($bundle->name ?? ''));
            $key = $type . ':' . $this->normalise_catalog_key($name !== '' ? $name : (string)($bundle->slug ?? $bundle->id));
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $bundle;
        }

        return $deduped;
    }

    /**
     * Final guard against duplicate public cards.
     *
     * @param array $items Catalog card data.
     * @return array Deduplicated catalog card data.
     */
    private function dedupe_catalog_items(array $items): array {

        $deduped = [];
        $seen = [];
        foreach ($items as $item) {
            $type = (string)($item['itemtype'] ?? '');
            $identity = $type === 'course'
                ? (string)($item['id'] ?? '')
                : $this->normalise_catalog_key((string)($item['title'] ?? ($item['id'] ?? '')));
            $key = $type . ':' . $identity;
            if ($identity === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $item;
        }

        return $deduped;
    }

    /**
     * Normalise a catalog identity string for duplicate detection.
     *
     * @param string $value Raw identity value.
     * @return string Normalised identity.
     */
    private function normalise_catalog_key(string $value): string {

        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value);
        return $value ?: '';
    }
}

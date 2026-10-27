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
 * Public Modern Commerce course details page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Public storefront permits guest browsing; write actions enforce login.
// phpcs:disable moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');
// phpcs:enable moodle.Files.RequireLogin.Missing
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\api\cart_api;
use local_moderncommerce\services\commerce_settings_service;
use local_moderncommerce\services\pricing_service;

$courseid = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

if (!$courseid) {
    redirect(
        new moodle_url('/local/moderncommerce/index.php'),
        "Can't find data record in database table course.",
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
if (!$course) {
    redirect(
        new moodle_url('/local/moderncommerce/index.php'),
        get_string('invalidrecord', 'error', 'course'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$context = context_course::instance($course->id);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/course_details.php', ['id' => $courseid]));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-public-course-page');
$PAGE->set_pagetype('local-moderncommerce-course_details');
$PAGE->set_subpage($courseid);
$PAGE->set_secondary_navigation(false);
$PAGE->set_title($course->fullname);

$canmanageblocks = is_siteadmin() || has_capability('moodle/site:config', context_system::instance());

$pricing = pricing_service::get_course_pricing($courseid);
$haspurchaseoption = $pricing && !empty($pricing->enabled);
$isinsubscriptionplan = false;

if ($DB->get_manager()->table_exists(new xmldb_table('local_moderncommerce_subscription_access_rules'))) {
    $isinsubscriptionplan = $DB->record_exists('local_moderncommerce_subscription_access_rules', [
        'access_type' => 'course',
        'target_id' => $courseid,
    ]);

    if (!$isinsubscriptionplan) {
        $isinsubscriptionplan = $DB->record_exists('local_moderncommerce_subscription_access_rules', [
            'access_type' => 'category',
            'target_id' => $course->category,
        ]);
    }

    if (!$isinsubscriptionplan && $DB->get_manager()->table_exists(new xmldb_table('local_moderncommerce_product_courses'))) {
        $sql = "SELECT 1
                  FROM {local_moderncommerce_subscription_access_rules} par
                  JOIN {local_moderncommerce_product_courses} pc ON pc.productid = par.target_id
                 WHERE par.access_type IN ('bundle', 'program', 'product')
                   AND pc.courseid = :courseid
                   AND pc.relationtype = :relationtype";
        $isinsubscriptionplan = $DB->record_exists_sql($sql, [
            'courseid' => $courseid,
            'relationtype' => 'included',
        ]);
    }
}

if (!$haspurchaseoption && !$isinsubscriptionplan) {
    redirect(
        new moodle_url('/local/moderncommerce/index.php'),
        get_string('coursenotavailable', 'local_moderncommerce'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Keep old add-to-cart links working while the page itself renders through React.
if ($action === 'addtocart' && confirm_sesskey()) {
    if (!isloggedin() || isguestuser()) {
        redirect(new moodle_url('/login/index.php', ['wantsurl' => $PAGE->url->out(false)]));
    }

    try {
        cart_api::add_to_cart((int)$USER->id, $courseid);
        redirect(
            $PAGE->url,
            get_string('addedtocart', 'local_moderncommerce'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        \core\notification::error($e->getMessage());
    }
}

if ($canmanageblocks && in_array($action, ['resetblocks', 'adddefaultblocks'], true) && confirm_sesskey()) {
    if ($action === 'resetblocks') {
        $sql = "SELECT bi.*
                  FROM {block_instances} bi
                  JOIN {context} ctx ON ctx.id = bi.parentcontextid
                 WHERE ctx.contextlevel = :contextlevel
                   AND ctx.instanceid = :courseid
                   AND bi.pagetypepattern = :pagepattern
                   AND bi.subpagepattern = :subpage";

        $blockinstances = $DB->get_records_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'courseid' => $courseid,
            'pagepattern' => 'local-moderncommerce-course_details',
            'subpage' => (string)$courseid,
        ]);

        foreach ($blockinstances as $instance) {
            blocks_delete_instance($instance);
        }

        redirect(
            $PAGE->url,
            get_string('blocksreset', 'local_moderncommerce'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($action === 'adddefaultblocks') {
        $defaultblocks = [
            [
                'blockname' => 'ccp_course_header',
                'defaultregion' => 'above-fullwidth',
                'defaultweight' => -10,
            ],
            [
                'blockname' => 'ccp_course_information',
                'defaultregion' => 'sidebar',
                'defaultweight' => 0,
            ],
        ];
        $addedcount = 0;

        foreach ($defaultblocks as $blockconfig) {
            $existingblock = $DB->get_record('block_instances', [
                'blockname' => $blockconfig['blockname'],
                'parentcontextid' => $context->id,
                'pagetypepattern' => 'local-moderncommerce-course_details',
                'subpagepattern' => (string)$courseid,
            ]);

            if ($existingblock) {
                continue;
            }

            $blockinstance = (object)[
                'blockname' => $blockconfig['blockname'],
                'parentcontextid' => $context->id,
                'pagetypepattern' => 'local-moderncommerce-course_details',
                'subpagepattern' => (string)$courseid,
                'defaultregion' => $blockconfig['defaultregion'],
                'defaultweight' => $blockconfig['defaultweight'],
                'showinsubcontexts' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
                'configdata' => '',
            ];

            $DB->insert_record('block_instances', $blockinstance);
            $addedcount++;
        }

        if ($addedcount > 0) {
            redirect(
                $PAGE->url,
                get_string('blocksadded', 'local_moderncommerce', $addedcount),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        redirect(
            $PAGE->url,
            get_string('blocksalreadyexist', 'local_moderncommerce'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
}

local_moderncommerce_set_breadcrumb(
    format_string($course->fullname),
    (new moodle_url('/local/moderncommerce/index.php'))->out(false),
    get_string('catalog', 'local_moderncommerce')
);

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/course_details',
    'id' => 'moderncommerce-course-details-app',
    'class' => 'local-moderncommerce-course-details',
    'props' => [
        'methodName' => 'local_moderncommerce_get_course_details',
        'cartMethodName' => 'local_moderncommerce_update_cart',
        'wishlistUpdateMethodName' => 'local_moderncommerce_update_learner_wishlist',
        'reviewsMethodName' => 'local_moderncommerce_get_course_reviews',
        'submitReviewMethodName' => 'local_moderncommerce_submit_course_review',
        'reactionMethodName' => 'local_moderncommerce_set_course_review_reaction',
        'courseId' => $courseid,
        'sidebarPosition' => commerce_settings_service::course_detail_sidebar_position(),
        'labels' => [
            'activities' => get_string('activities', 'local_moderncommerce'),
            'addtocart' => get_string('addtocart', 'local_moderncommerce'),
            'addedtocart' => get_string('addedtocart', 'local_moderncommerce'),
            'bestseller' => get_string('bestseller', 'local_moderncommerce'),
            'browsecatalog' => get_string('browsecatalog', 'local_moderncommerce'),
            'buynow' => get_string('buynow', 'local_moderncommerce'),
            'savetowishlist' => get_string('savetowishlist', 'local_moderncommerce'),
            'removefromwishlist' => get_string('removefromwishlist', 'local_moderncommerce'),
            'wishlistadded' => get_string('wishlistadded', 'local_moderncommerce'),
            'wishlistremoved' => get_string('wishlistremoved', 'local_moderncommerce'),
            'cart' => get_string('cart', 'local_moderncommerce'),
            'catalog' => get_string('catalog', 'local_moderncommerce'),
            'certificate' => get_string('certificate', 'local_moderncommerce'),
            'course' => get_string('course'),
            'coursedetails' => get_string('coursedetails', 'local_moderncommerce'),
            'coursedetailsoverview' => get_string('coursedetailsoverview', 'local_moderncommerce'),
            'courseinformation' => get_string('courseinformation', 'local_moderncommerce'),
            'courseoutline' => get_string('courseoutline', 'local_moderncommerce'),
            'courseprice' => get_string('courseprice', 'local_moderncommerce'),
            'coursenotavailable' => get_string('coursenotavailable', 'local_moderncommerce'),
            'duration' => get_string('acf_totalduration', 'local_moderncommerce'),
            'error' => get_string('error', 'local_moderncommerce'),
            'featured' => get_string('featured', 'local_moderncommerce'),
            'free' => get_string('free', 'local_moderncommerce'),
            'gotocourse' => get_string('gotocourse', 'local_moderncommerce'),
            'language' => get_string('acf_primarylanguage', 'local_moderncommerce'),
            'level' => get_string('catalog_level_title', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'login' => get_string('login'),
            'loginrequired' => get_string('loginrequired', 'local_moderncommerce'),
            'loginrequiredmessage' => get_string('loginrequiredmessage', 'local_moderncommerce'),
            'onsale' => get_string('onsale', 'local_moderncommerce'),
            'overview' => get_string('overview', 'local_moderncommerce'),
            'producttype' => get_string('producttype', 'local_moderncommerce'),
            'productfeatures' => get_string('productfeatures', 'local_moderncommerce'),
            'quizzes' => get_string('modulenameplural', 'quiz'),
            'ratingdistribution' => get_string('ratingdistribution', 'local_moderncommerce'),
            'reviews' => get_string('reviews', 'local_moderncommerce'),
            'reviewsummary' => get_string('reviewsummary', 'local_moderncommerce'),
            'studentfeedback' => get_string('studentfeedback', 'local_moderncommerce'),
            'averagerating' => get_string('averagerating', 'local_moderncommerce'),
            'noreviews' => get_string('noreviews', 'local_moderncommerce'),
            'writeareview' => get_string('writeareview', 'local_moderncommerce'),
            'submitreview' => get_string('submitreview', 'local_moderncommerce'),
            'comment' => get_string('comment', 'local_moderncommerce'),
            'rating' => get_string('rating', 'local_moderncommerce'),
            'reviewcommentplaceholder' => get_string('reviewcommentplaceholder', 'local_moderncommerce'),
            'alreadyreviewed' => get_string('alreadyreviewed', 'local_moderncommerce'),
            'cannotreviewcourse' => get_string('cannotreviewcourse', 'local_moderncommerce'),
            'commentrequired' => get_string('commentrequired', 'local_moderncommerce'),
            'error:invalidrating' => get_string('error:invalidrating', 'local_moderncommerce'),
            'like' => get_string('like', 'local_moderncommerce'),
            'dislike' => get_string('dislike', 'local_moderncommerce'),
            'love' => get_string('love', 'local_moderncommerce'),
            'outof5' => get_string('outof5', 'local_moderncommerce'),
            'loadmore' => get_string('loadmore', 'local_moderncommerce'),
            'retry' => get_string('retry', 'local_moderncommerce'),
            'securecheckout' => get_string('securecheckout', 'local_moderncommerce'),
            'securepayment' => get_string('securepayment', 'local_moderncommerce'),
            'instantaccess' => get_string('instantaccess', 'local_moderncommerce'),
            'startsignup' => get_string('startsignup'),
            'takecourse' => get_string('takecourse', 'local_moderncommerce'),
            'trending' => get_string('acf_trending', 'local_moderncommerce'),
            'whatyoulllearn' => get_string('whatyoulllearn', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_moderncommerce/react_mount', [
    'region' => 'moderncommerce-course-details',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-mortarboard',
]);

if ($canmanageblocks && $PAGE->user_is_editing()) {
    $reseturl = new moodle_url($PAGE->url, ['action' => 'resetblocks', 'sesskey' => sesskey()]);
    $addurl = new moodle_url($PAGE->url, ['action' => 'adddefaultblocks', 'sesskey' => sesskey()]);

    echo $OUTPUT->render_from_template('local_moderncommerce/block_management_toolbar', [
        'addurl' => $addurl->out(false),
        'reseturl' => $reseturl->out(false),
        'addlabel' => get_string('adddefaultblocks', 'local_moderncommerce'),
        'resetlabel' => get_string('resetblocks', 'local_moderncommerce'),
        'confirmreset' => get_string('confirmresetblocks', 'local_moderncommerce'),
    ]);
}

echo $OUTPUT->footer();

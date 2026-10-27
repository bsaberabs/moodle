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
 * Bundle/Program Details Page
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

use local_moderncommerce\api\bundle_api;
use local_moderncommerce\api\cart_api;
use local_moderncommerce\services\access_service;

$bundleid = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

// Validate bundle id and redirect to plugin index if invalid or missing.
if (!$bundleid) {
    $message = get_string('bundlenotfound', 'local_moderncommerce');
    redirect(new moodle_url('/local/moderncommerce/index.php'), $message, null, \core\output\notification::NOTIFY_ERROR);
}

$bundle = bundle_api::get($bundleid);
if (!$bundle) {
    $message = get_string('bundlenotfound', 'local_moderncommerce');
    redirect(new moodle_url('/local/moderncommerce/index.php'), $message, null, \core\output\notification::NOTIFY_ERROR);
}

// Check if bundle is visible and active.
if (!$bundle->visible || $bundle->status !== 'active') {
    $message = get_string('bundlenotavailable', 'local_moderncommerce');
    redirect(new moodle_url('/local/moderncommerce/index.php'), $message, null, \core\output\notification::NOTIFY_WARNING);
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/bundle_details.php', ['id' => $bundleid]));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-public-bundle-page');
$PAGE->set_pagetype('local-moderncommerce-bundle_details');
$PAGE->set_title($bundle->name, true);
$PAGE->set_secondary_navigation(false);

// Handle add to cart action.
if ($action === 'addtocart' && confirm_sesskey()) {
    try {
        cart_api::add_bundle_to_cart($USER->id, $bundleid);
        redirect(
            $PAGE->url,
            get_string('bundleaddedtocart', 'local_moderncommerce'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        \core\notification::error($e->getMessage());
    }
}

// Check if user can manage blocks (admins and managers).
$canmanageblocks = is_siteadmin() || has_capability('moodle/site:config', context_system::instance());

// Learners who already own this bundle/program should use the protected learner layout.
if (
    !$PAGE->user_is_editing()
    && !$canmanageblocks
    && isloggedin()
    && !isguestuser()
    && access_service::user_has_product_purchase_access((int)$USER->id, $bundleid)
) {
    $producttype = strtolower((string)($bundle->producttype ?? (!empty($bundle->isprogram) ? 'program' : 'bundle')));
    redirect(access_service::learner_product_access_url($bundleid, $producttype));
}

// Handle block management actions.
if ($canmanageblocks && in_array($action, ['resetblocks', 'adddefaultblocks']) && confirm_sesskey()) {
    if ($action === 'resetblocks') {
        // Delete all blocks for this specific bundle details page.
        $sql = "SELECT bi.*
                  FROM {block_instances} bi
                  JOIN {context} ctx ON ctx.id = bi.parentcontextid
                 WHERE ctx.contextlevel = :contextlevel
                   AND bi.pagetypepattern = :pagepattern";

        $params = [
            'contextlevel' => CONTEXT_SYSTEM,
            'pagepattern' => 'local-moderncommerce-bundle_details',
        ];

        $blockinstances = $DB->get_records_sql($sql, $params);

        foreach ($blockinstances as $instance) {
            blocks_delete_instance($instance);
        }

        redirect($PAGE->url, get_string('blocksreset', 'local_moderncommerce'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'adddefaultblocks') {
        // Define default blocks with their settings.
        $defaultblocks = [
            [
                'blockname' => 'ccp_bundle_header',
                'defaultregion' => 'above-fullwidth',
                'defaultweight' => -10,
            ],
            [
                'blockname' => 'ccp_bundle_information',
                'defaultregion' => 'sidebar',
                'defaultweight' => 0,
            ],
        ];

        $addedcount = 0;

        foreach ($defaultblocks as $blockconfig) {
            // Check if block already exists.
            $existingblock = $DB->get_record('block_instances', [
                'blockname' => $blockconfig['blockname'],
                'parentcontextid' => $context->id,
                'pagetypepattern' => 'local-moderncommerce-bundle_details',
            ]);

            if (!$existingblock) {
                // Create new block instance.
                $blockinstance = new stdClass();
                $blockinstance->blockname = $blockconfig['blockname'];
                $blockinstance->parentcontextid = $context->id;
                $blockinstance->pagetypepattern = 'local-moderncommerce-bundle_details';
                $blockinstance->subpagepattern = '';
                $blockinstance->defaultregion = $blockconfig['defaultregion'];
                $blockinstance->defaultweight = $blockconfig['defaultweight'];
                $blockinstance->showinsubcontexts = 0;
                $blockinstance->timecreated = time();
                $blockinstance->timemodified = time();
                $blockinstance->configdata = '';

                $DB->insert_record('block_instances', $blockinstance);
                $addedcount++;
            }
        }

        if ($addedcount > 0) {
            $message = get_string('blocksadded', 'local_moderncommerce', $addedcount);
            redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            $message = get_string('blocksalreadyexist', 'local_moderncommerce');
            redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_INFO);
        }
    }
}

// Set breadcrumbs.
local_moderncommerce_set_breadcrumb(
    format_string($bundle->name),
    (new moodle_url('/local/moderncommerce/index.php'))->out(false),
    get_string('catalog', 'local_moderncommerce')
);

$bundledetailslabels = get_string_manager()->load_component_strings('local_moderncommerce', current_language());
$bundledetailslabels = array_merge($bundledetailslabels, [
    'course' => get_string('course'),
    'login' => get_string('login'),
    'startsignup' => get_string('startsignup'),
]);

$bundledetailsreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/bundle_details',
    'id' => 'moderncommerce-bundle-details-app',
    'class' => 'local-moderncommerce-bundle-details',
    'props' => [
        'methodName' => 'local_moderncommerce_get_bundle_details',
        'wishlistUpdateMethodName' => 'local_moderncommerce_update_learner_wishlist',
        'bundleId' => $bundleid,
        'labels' => $bundledetailslabels,
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_moderncommerce/bundle_details', [
    'bundledetailsreactconfig' => $bundledetailsreactconfig,
]);

// Display floating admin button for block management (admins and managers only, when editing is on).
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

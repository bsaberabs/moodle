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
 * Component testing ground for Modern Commerce UI adoption.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;

$context = context_system::instance();
require_login();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/components.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('components', 'local_moderncommerce'));
$PAGE->requires->js_call_amd('local_moderncommerce/components_demo', 'init');

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/components', [
    'usecomponenttypography' => true,
    'toastpreview' => [
        'type' => 'success',
        'icon' => 'bi-check-circle',
        'title' => 'Payment successful',
        'message' => 'Order #1042 marked paid.',
        'dismissible' => true,
        'showprogress' => true,
        'delay' => 4000,
    ],
    'alertpreviews' => [
        [
            'type' => 'success',
            'icon' => 'bi-check-circle',
            'title' => 'Payment received',
            'message' => 'Order #1042 is paid and the learner receipt is available.',
        ],
        [
            'type' => 'warning',
            'icon' => 'bi-exclamation-triangle',
            'title' => 'Gateway setup needed',
            'message' => 'Enable at least one payment gateway before publishing checkout.',
        ],
        [
            'type' => 'danger',
            'icon' => 'bi-x-octagon',
            'title' => 'Refund failed',
            'message' => 'The gateway rejected the refund request. Review the payment event log.',
            'role' => 'alert',
        ],
    ],
    'statusbadges' => [
        ['variant' => 'success', 'tone' => 'soft', 'dot' => true, 'label' => 'Active'],
        ['variant' => 'warning', 'tone' => 'soft', 'dot' => true, 'label' => 'Pending'],
        ['variant' => 'danger', 'tone' => 'soft', 'dot' => true, 'label' => 'Failed'],
        ['variant' => 'info', 'tone' => 'soft', 'dot' => true, 'label' => 'Processing'],
        ['variant' => 'neutral', 'tone' => 'soft', 'dot' => true, 'label' => 'Archived'],
    ],
    'brandbadges' => [
        ['variant' => 'primary', 'tone' => 'soft', 'icon' => 'bi-stars', 'label' => 'Featured'],
        ['variant' => 'secondary', 'tone' => 'soft', 'icon' => 'bi-shield-check', 'label' => 'Moderated'],
        ['variant' => 'accent', 'tone' => 'soft', 'icon' => 'bi-lightning-charge', 'label' => 'Promotion'],
        ['variant' => 'neutral', 'tone' => 'soft', 'icon' => 'bi-tag', 'label' => 'Internal'],
    ],
    'tintbadges' => [
        ['variant' => 'primary', 'tone' => 'subtle', 'label' => 'Subtle'],
        ['variant' => 'primary', 'tone' => 'soft', 'label' => 'Soft'],
        ['variant' => 'primary', 'tone' => 'medium', 'label' => 'Medium'],
        ['variant' => 'primary', 'tone' => 'strong', 'label' => 'Strong'],
    ],
    'emptystate' => [
        'icon' => 'bi-inbox',
        'title' => 'No coupons yet',
        'description' => 'Create a coupon to test discount messaging and checkout validation.',
        'actionurl' => '#',
        'actionlabel' => 'Create coupon',
    ],
    'loadingpreview' => [
        'small' => true,
        'label' => 'Loading orders...',
    ],
    'pricepreviews' => [
        [
            'displayprice' => '$129.00',
            'currencybefore' => '$',
            'currencyafter' => '',
            'price' => '129.00',
            'saleprice' => '',
        ],
        [
            'hassale' => true,
            'currencybefore' => '$',
            'currencyafter' => '',
            'price' => '199.00',
            'saleprice' => '149.00',
        ],
        [
            'isfree' => true,
            'currencybefore' => '$',
            'currencyafter' => '',
            'price' => '0.00',
            'saleprice' => '',
        ],
    ],
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'link',
        'url' => new moodle_url('/local/moderncommerce/styleguide.php'),
        'label' => get_string('styleguide', 'local_moderncommerce'),
        'icon' => 'bi-palette',
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'components',
    get_string('components', 'local_moderncommerce'),
    $contenthtml,
    get_string('componentsdesc', 'local_moderncommerce'),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();

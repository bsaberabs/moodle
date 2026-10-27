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
 * Checkout success / thank-you page for Modern Commerce.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_moderncommerce\api\order_api;
use local_moderncommerce\output\learner_shell;
use local_moderncommerce\services\access_service;
use local_moderncommerce\services\pricing_service;

require_login();

$orderid = optional_param('orderid', optional_param('id', 0, PARAM_INT), PARAM_INT);
if ($orderid <= 0) {
    redirect(new moodle_url('/local/moderncommerce/learner/index.php#/orders'));
}

$context = context_system::instance();
$order = order_api::get_order($orderid);
if ((int)$order->userid !== (int)$USER->id) {
    require_capability('local/moderncommerce:viewallorders', $context);
} else {
    require_capability('local/moderncommerce:viewownorders', $context);
}

$items = order_api::get_order_items($orderid);
$ispaid = in_array((string)$order->status, ['paid', 'completed'], true);
$ispending = in_array((string)$order->status, ['pending', 'processing'], true);
$title = $ispaid
    ? get_string('p1_success_title_paid', 'local_moderncommerce')
    : ($ispending
        ? get_string('p1_success_title_pending', 'local_moderncommerce')
        : get_string('p1_success_title_updated', 'local_moderncommerce'));
$heroicon = $ispaid ? 'bi-check2-circle' : ($ispending ? 'bi-hourglass-split' : 'bi-bag-check');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/success.php', ['orderid' => $orderid]));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$learnerbase = (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false);
$orderurl = $learnerbase . '#/orders/' . $orderid;
$ordersurl = $learnerbase . '#/orders';
$receipturl = new moodle_url('/local/moderncommerce/download_invoice.php', ['orderid' => $orderid, 'type' => 'receipt']);
$checkouturl = $learnerbase . '#/checkout?orderid=' . $orderid;

$starturl = $learnerbase . '#/dashboard';
foreach ($items as $item) {
    $producttype = strtolower((string)($item->producttype ?? $item->itemtype ?? ''));
    if ((int)($item->courseid ?? 0) > 0) {
        $starturl = access_service::course_view_url((int)$item->courseid)->out(false);
        break;
    }
    if ((int)($item->productid ?? 0) > 0 && in_array($producttype, ['bundle', 'program'], true)) {
        $starturl = access_service::learner_product_access_url((int)$item->productid, $producttype)->out(false);
        break;
    }
}

if ($ispaid) {
    $summary = get_string('p1_success_summary_paid', 'local_moderncommerce');
} else if ($ispending) {
    $summary = get_string('p1_success_summary_pending', 'local_moderncommerce');
} else {
    $summary = get_string('p1_success_summary_updated', 'local_moderncommerce');
}

$actions = '';
if ($ispaid) {
    $actions .= html_writer::link($starturl, get_string('startlearning', 'local_moderncommerce'), [
        'class' => 'mc-button btn-mc-primary',
        'data-mc-button' => 'primary',
    ]);
    $actions .= html_writer::link($receipturl, get_string('downloadreceipt', 'local_moderncommerce'), [
        'class' => 'mc-button mc-btn-soft',
        'data-mc-button' => 'soft',
    ]);
} else if ($ispending) {
    $actions .= html_writer::link($checkouturl, get_string('continuepayment', 'local_moderncommerce'), [
        'class' => 'mc-button btn-mc-primary',
        'data-mc-button' => 'primary',
    ]);
}
$actions .= html_writer::link($orderurl, get_string('vieworder', 'local_moderncommerce'), [
    'class' => 'mc-button mc-btn-soft',
    'data-mc-button' => 'soft',
]);

$itemrows = '';
$stringmanager = get_string_manager();
foreach ($items as $item) {
    $itemname = format_string((string)($item->coursename ?? $item->productname ?? get_string('item', 'local_moderncommerce')));
    $rawitemtype = strtolower((string)($item->producttype ?? $item->itemtype ?? 'course'));
    $typekey = 'producttype_' . preg_replace('/[^a-z0-9_]+/', '', $rawitemtype);
    $itemtype = $stringmanager->string_exists($typekey, 'local_moderncommerce')
        ? get_string($typekey, 'local_moderncommerce')
        : get_string('course', 'local_moderncommerce');
    $itemrows .= html_writer::tag(
        'li',
        html_writer::span(
            html_writer::tag('strong', s($itemname)) .
            html_writer::span(s($itemtype), 'd-block text-muted small')
        ) .
        html_writer::span(s(pricing_service::format_order_price((float)$item->total, $order))),
        ['class' => 'mc-success-item']
    );
}
$itemshtml = html_writer::tag(
    'section',
    html_writer::tag('h2', get_string('purchaseditems', 'local_moderncommerce'), ['class' => 'mc-public-card__title']) .
    html_writer::tag('ul', $itemrows, ['class' => 'mc-success-items']),
    ['class' => 'mc-public-card']
);

$status = strtolower((string)$order->status);
$statuskey = 'orderstatus_' . preg_replace('/[^a-z0-9_]+/', '', $status);
$statuslabel = $stringmanager->string_exists($statuskey, 'local_moderncommerce')
    ? get_string($statuskey, 'local_moderncommerce')
    : s((string)$order->status);

$summaryhtml = html_writer::tag(
    'aside',
    html_writer::tag('h2', get_string('ordersummary', 'local_moderncommerce'), ['class' => 'mc-public-card__title']) .
    html_writer::tag(
        'dl',
        html_writer::tag('dt', get_string('ordernumber', 'local_moderncommerce')) .
        html_writer::tag('dd', s($order->ordernumber)) .
        html_writer::tag('dt', get_string('status', 'local_moderncommerce')) .
        html_writer::tag('dd', s($statuslabel)) .
        html_writer::tag('dt', get_string('total', 'local_moderncommerce')) .
        html_writer::tag('dd', s(pricing_service::format_order_price((float)$order->total, $order))) .
        html_writer::tag('dt', get_string('date', 'local_moderncommerce')) .
        html_writer::tag('dd', s(userdate((int)$order->timecreated, get_string('strftimedatetime')))),
        ['class' => 'mb-0']
    ),
    ['class' => 'mc-public-card']
);

$cardshtml = html_writer::div($itemshtml . $summaryhtml, 'mc-success-summary');

// The confirmation hero renders inside the learner shell content area (mc-modern-learner-main),
// below the shell's standard learner header band. It carries its own icon/eyebrow/title/summary/actions.
$herohtml = html_writer::tag(
    'section',
    html_writer::span(
        html_writer::tag('i', '', ['class' => 'bi ' . $heroicon]),
        'mc-success-hero__icon',
        ['aria-hidden' => 'true']
    ) .
    html_writer::tag('span', s($order->ordernumber), ['class' => 'mc-success-hero__eyebrow']) .
    html_writer::tag('h1', s($title), ['class' => 'mc-success-hero__title']) .
    html_writer::tag('p', s($summary), ['class' => 'mc-success-hero__summary']) .
    html_writer::div($actions, 'mc-success-hero__actions'),
    ['class' => 'mc-success-hero']
);

$contenthtml = $herohtml . $cardshtml;

$shell = learner_shell::create('orders');
$layoutcontext = $shell->get_react_layout_context();

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/learner_success',
    'id' => 'moderncommerce-learner-success-app',
    'class' => 'local-moderncommerce-learner-success',
    'props' => [
        'title' => $title,
        'eyebrow' => $order->ordernumber,
        'subtitle' => $summary,
        'contentHtml' => $contenthtml,
        'labels' => $layoutcontext['labels'],
        'layout' => $layoutcontext,
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$mounthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
    'region' => 'moderncommerce-learner-success',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-bag-check',
]);

echo $OUTPUT->header();
echo $shell->set_content($mounthtml)
    ->add_breadcrumb(get_string('myorders', 'local_moderncommerce'), $ordersurl)
    ->add_breadcrumb($title, '', true)
    ->render($OUTPUT);
echo $OUTPUT->footer();

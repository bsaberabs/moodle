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
 * Email Notifications for Modern Commerce
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\services\commerce_settings_service;
use local_moderncommerce\services\pricing_service;
use local_moderncommerce\api\email_api;
use local_moderncommerce\email\notification_catalog;
use local_moderncommerce\email\placeholder_engine;
use local_moderncommerce\email\renderer;

/**
 * Sends Modern Commerce order notification emails.
 */
class email_notifications {
    /**
     * Get display name for an order item (course or bundle).
     *
     * @param object $item Order item record
     * @return string Item display name
     */
    protected static function get_item_display_name($item): string {
        if (!empty($item->bundleid)) {
            return $item->bundlename ?: ($item->coursename ?: ($item->itemname ?? get_string('bundle', 'local_moderncommerce')));
        }

        // For courses, use stored name or fetch from course table.
        if (!empty($item->coursename)) {
            return $item->coursename;
        }

        if (!empty($item->courseid)) {
            global $DB;
            return $DB->get_field('course', 'fullname', ['id' => $item->courseid]) ?: get_string('course');
        }

        return get_string('course');
    }

    /**
     * Send order confirmation email
     *
     * @param object $order Order record
     * @param array $items Order items
     * @return bool Success status
     */
    public static function send_order_confirmation($order, $items) {
        global $DB, $CFG;

        // Check if email is enabled.
        if (!notification_catalog::is_enabled('orderconfirmation')) {
            return true; // Skip sending.
        }

        $user = $DB->get_record('user', ['id' => $order->userid], '*', MUST_EXIST);

        // Route through the notification hub when installed (in-app + email + audit).
        $hubplaceholders = local_moderncommerce_prepare_order_placeholders($order, $items, $user) + [
            'order_number' => $order->ordernumber,
            'order_date' => userdate($order->timecreated),
            'order_total' => pricing_service::format_order_price((float) $order->total, $order),
            'courses_list' => self::items_to_courses_list($items),
            'retry_payment_url' => $CFG->wwwroot . '/local/moderncommerce/order.php?id=' . $order->id,
        ];
        if (
            self::notify_hub(
                'order_placed',
                'transactional',
                'moderncommerce_order_placed',
                (int) $order->userid,
                $hubplaceholders,
                $CFG->wwwroot . '/local/moderncommerce/order.php?id=' . $order->id,
                (int) $order->id
            )
        ) {
            return true;
        }

        $placeholders = local_moderncommerce_prepare_order_placeholders($order, $items, $user);
        if (
            self::send_configured_template(
                $user,
                'orderconfirmation_template',
                'moderncommerce_order_placed',
                $placeholders,
                'order confirmation'
            )
        ) {
            return true;
        }

        // Use configured subject/body or fall back to hard-coded content.
        $subject = get_config('local_moderncommerce', 'orderconfirmation_subject');
        if (empty($subject)) {
            $subject = get_string('email_orderconfirmation_subject', 'local_moderncommerce', $order->ordernumber);
        } else {
            // Replace placeholders in subject.
            $subject = self::replace_simple_placeholders($subject, $placeholders);
        }

        $body = get_config('local_moderncommerce', 'orderconfirmation_body');
        if (!empty($body)) {
            $messagehtml = self::replace_simple_placeholders($body, $placeholders);
        } else {
            $messagehtml = self::render_order_confirmation_html($order, $items, $user);
        }

        $messagetext = html_to_text($messagehtml);

        return self::send_email($user, $subject, $messagehtml, $messagetext);
    }

    /**
     * Send payment receipt email
     *
     * @param object $order Order record
     * @param object $transaction Transaction record
     * @param array $items Order items
     * @return bool Success status
     */
    public static function send_payment_receipt($order, $transaction, $items) {
        global $DB, $CFG;

        // Operational "new sale" alert to admins — independent of the learner receipt setting.
        self::notify_admins_new_sale($order, $transaction, $items);

        // Check if email is enabled.
        if (!notification_catalog::is_enabled('paymentreceipt')) {
            return true;
        }

        $user = $DB->get_record('user', ['id' => $order->userid], '*', MUST_EXIST);

        // Route through the notification hub when installed (in-app + email + audit).
        $hubplaceholders = local_moderncommerce_prepare_payment_placeholders($order, $transaction, $items, $user) + [
            'order_number' => $order->ordernumber,
            'order_date' => userdate($order->timecreated),
            'order_total' => pricing_service::format_order_price((float) $order->total, $order),
            'payment_method' => ucfirst((string) ($transaction->gateway ?? '')),
            'courses_list' => self::items_to_courses_list($items),
            'my_courses_url' => $CFG->wwwroot . '/my/',
            'order_view_link' => $CFG->wwwroot . '/local/moderncommerce/order.php?id=' . $order->id,
        ];
        if (
            self::notify_hub(
                'payment_received',
                'transactional',
                'moderncommerce_payment_receipt',
                (int) $order->userid,
                $hubplaceholders,
                $CFG->wwwroot . '/local/moderncommerce/order.php?id=' . $order->id,
                (int) $order->id
            )
        ) {
            return true;
        }

        $placeholders = local_moderncommerce_prepare_payment_placeholders($order, $transaction, $items, $user);
        if (
            self::send_configured_template(
                $user,
                'paymentreceipt_template',
                'moderncommerce_payment_receipt',
                $placeholders,
                'payment receipt'
            )
        ) {
            return true;
        }

        // Use configured subject/body or fall back.
        $subject = get_config('local_moderncommerce', 'paymentreceipt_subject');
        if (empty($subject)) {
            $subject = get_string('email_paymentreceipt_subject', 'local_moderncommerce', $order->ordernumber);
        } else {
            $subject = self::replace_simple_placeholders($subject, $placeholders);
        }

        $body = get_config('local_moderncommerce', 'paymentreceipt_body');
        if (!empty($body)) {
            $messagehtml = self::replace_simple_placeholders($body, $placeholders);
        } else {
            $messagehtml = self::render_payment_receipt_html($order, $transaction, $items, $user);
        }

        $messagetext = html_to_text($messagehtml);

        return self::send_email($user, $subject, $messagehtml, $messagetext);
    }

    /**
     * Send enrollment confirmation email
     *
     * @param object $user User record
     * @param object $course Course record
     * @param string $ordernumber Order number
     * @return bool Success status
     */
    public static function send_enrollment_confirmation($user, $course, $ordernumber) {
        global $CFG;

        // Check if email is enabled.
        if (!notification_catalog::is_enabled('enrollmentconfirmation')) {
            return true;
        }

        // Route through the notification hub when installed (in-app + email + audit).
        $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $hubplaceholders = local_moderncommerce_prepare_enrollment_placeholders($user, $course, $ordernumber) + [
            'order_number' => $ordernumber,
            'courses_list' => $course->fullname,
            'my_courses_url' => $courseurl,
        ];
        if (
            self::notify_hub(
                'enrolled',
                'transactional',
                'moderncommerce_enrollment_confirmation',
                (int) $user->id,
                $hubplaceholders,
                $courseurl,
                (int) $course->id
            )
        ) {
            return true;
        }

        $placeholders = local_moderncommerce_prepare_enrollment_placeholders($user, $course, $ordernumber);
        if (
            self::send_configured_template(
                $user,
                'enrollmentconfirmation_template',
                'moderncommerce_enrollment_confirmation',
                $placeholders,
                'enrollment confirmation'
            )
        ) {
            return true;
        }

        // Use configured subject/body or fall back.
        $subject = get_config('local_moderncommerce', 'enrollmentconfirmation_subject');
        if (empty($subject)) {
            $a = new \stdClass();
            $a->coursename = $course->fullname;
            $a->ordernumber = $ordernumber;
            $subject = get_string('email_enrollment_subject', 'local_moderncommerce', $a);
        } else {
            $subject = self::replace_simple_placeholders($subject, $placeholders);
        }

        $body = get_config('local_moderncommerce', 'enrollmentconfirmation_body');
        if (!empty($body)) {
            $messagehtml = self::replace_simple_placeholders($body, $placeholders);
        } else {
            $messagehtml = self::render_enrollment_html($user, $course, $ordernumber);
        }

        $messagetext = html_to_text($messagehtml);

        return self::send_email($user, $subject, $messagehtml, $messagetext);
    }

    /**
     * Send key redemption notification email
     *
     * @param object $user User record
     * @param object $course Course record
     * @param string $keycode Key code
     * @return bool Success status
     */
    public static function send_key_redemption($user, $course, $keycode) {
        global $CFG;

        // Check if email is enabled.
        if (!notification_catalog::is_enabled('keyredemption')) {
            return true;
        }

        // Route through the notification hub when installed (in-app + email + audit).
        $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $hubplaceholders = local_moderncommerce_prepare_key_placeholders($user, $course, $keycode) + [
            'key_target_name' => $course->fullname,
            'key_code' => $keycode,
            'redeem_url' => $courseurl,
        ];
        if (
            self::notify_hub(
                'key_redeemed',
                'transactional',
                'moderncommerce_key_redeemed',
                (int) $user->id,
                $hubplaceholders,
                $courseurl,
                (int) $course->id
            )
        ) {
            return true;
        }

        $placeholders = local_moderncommerce_prepare_key_placeholders($user, $course, $keycode);
        if (
            self::send_configured_template(
                $user,
                'keyredemption_template',
                'moderncommerce_key_redeemed',
                $placeholders,
                'key redemption'
            )
        ) {
            return true;
        }

        // Use configured subject/body or fall back.
        $subject = get_config('local_moderncommerce', 'keyredemption_subject');
        if (empty($subject)) {
            $a = new \stdClass();
            $a->coursename = $course->fullname;
            $a->keycode = $keycode;
            $subject = get_string('email_keyredemption_subject', 'local_moderncommerce', $a);
        } else {
            $subject = self::replace_simple_placeholders($subject, $placeholders);
        }

        $body = get_config('local_moderncommerce', 'keyredemption_body');
        if (!empty($body)) {
            $messagehtml = self::replace_simple_placeholders($body, $placeholders);
        } else {
            $messagehtml = self::render_key_redemption_html($user, $course, $keycode);
        }

        $messagetext = html_to_text($messagehtml);

        return self::send_email($user, $subject, $messagehtml, $messagetext);
    }

    /**
     * Send refund confirmation email
     *
     * @param object $order Order record
     * @param object $refund Refund record
     * @return bool Success status
     */
    public static function send_refund_confirmation($order, $refund) {
        global $DB, $CFG;

        // Check if email is enabled.
        if (!notification_catalog::is_enabled('refundconfirmation')) {
            return true;
        }

        $user = $DB->get_record('user', ['id' => $order->userid], '*', MUST_EXIST);

        // Route through the notification hub when installed (in-app + email + audit).
        $hubplaceholders = local_moderncommerce_prepare_refund_placeholders($order, $refund, $user) + [
            'refund_amount' => pricing_service::format_order_price((float) $refund->amount, $order),
            'order_number' => $order->ordernumber,
            'refund_reference' => $refund->refundreference ?? (string) ($refund->id ?? ''),
            'payment_method' => '',
        ];
        if (
            self::notify_hub(
                'refund_issued',
                'transactional',
                'moderncommerce_refund_confirmation',
                (int) $order->userid,
                $hubplaceholders,
                $CFG->wwwroot . '/local/moderncommerce/order.php?id=' . $order->id,
                (int) $order->id
            )
        ) {
            return true;
        }

        $placeholders = local_moderncommerce_prepare_refund_placeholders($order, $refund, $user);
        if (
            self::send_configured_template(
                $user,
                'refundconfirmation_template',
                'moderncommerce_refund_confirmation',
                $placeholders,
                'refund confirmation'
            )
        ) {
            return true;
        }

        // Use configured subject/body or fall back.
        $subject = get_config('local_moderncommerce', 'refundconfirmation_subject');
        if (empty($subject)) {
            $subject = get_string('email_refund_subject', 'local_moderncommerce', $order->ordernumber);
        } else {
            $subject = self::replace_simple_placeholders($subject, $placeholders);
        }

        $body = get_config('local_moderncommerce', 'refundconfirmation_body');
        if (!empty($body)) {
            $messagehtml = self::replace_simple_placeholders($body, $placeholders);
        } else {
            $messagehtml = self::render_refund_html($order, $refund, $user);
        }

        $messagetext = html_to_text($messagehtml);

        return self::send_email($user, $subject, $messagehtml, $messagetext);
    }

    /**
     * Send a payment-reminder notification for an unpaid (pending) order.
     *
     * @param object $user Recipient user record.
     * @param object $order Order record.
     * @param array $items Order items.
     * @param int $remindernum Which reminder this is (1 or 2).
     * @return bool Success status.
     */
    public static function send_payment_reminder($user, $order, $items, $remindernum = 1) {
        global $CFG;

        $checkouturl = (new \moodle_url('/local/moderncommerce/checkout.php', ['orderid' => $order->id]))->out(false);
        $placeholders = local_moderncommerce_prepare_order_placeholders($order, $items, $user) + [
            'order_number' => $order->ordernumber,
            'order_total' => pricing_service::format_order_price((float) $order->total, $order),
            'courses_list' => self::items_to_courses_list($items),
            'retry_payment_url' => $checkouturl,
            'checkout_url' => $checkouturl,
            'reminder_number' => (int) $remindernum,
        ];

        return self::notify_hub(
            'payment_reminder',
            'reminder',
            'moderncommerce_payment_pending_reminder',
            (int) $order->userid,
            $placeholders,
            $checkouturl,
            (int) $order->id
        );
    }

    /**
     * Send an order-cancelled notification to the buyer.
     *
     * @param object $order Order record.
     * @return bool Success status.
     */
    public static function send_order_cancelled($order) {
        global $CFG;

        $items = \local_moderncommerce\api\order_api::get_order_items((int) $order->id);
        $orderurl = $CFG->wwwroot . '/local/moderncommerce/order.php?id=' . $order->id;
        $placeholders = [
            'order_number' => $order->ordernumber,
            'courses_list' => self::items_to_courses_list($items),
            'order_view_link' => $orderurl,
        ];

        return self::notify_hub(
            'order_cancelled',
            'transactional',
            'moderncommerce_order_cancelled',
            (int) $order->userid,
            $placeholders,
            $orderurl,
            (int) $order->id
        );
    }

    /**
     * Render order confirmation HTML
     */
    private static function render_order_confirmation_html($order, $items, $user) {
        global $CFG, $OUTPUT;

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0f6cbf; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .order-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .order-table th, .order-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            .order-table th { background: #f0f0f0; }
            .total-row { font-weight: bold; background: #e9ecef; }
            .button {
                display: inline-block;
                padding: 12px 24px;
                background: #0f6cbf;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin: 10px 0;
            }
        </style></head><body><div class="container">';

        $html .= '<div class="header"><h1>' . get_string('orderconfirmation', 'local_moderncommerce') . '</h1></div>';

        $html .= '<div class="content">';
        $html .= '<p>' . get_string('email_hello', 'local_moderncommerce', fullname($user)) . '</p>';
        $html .= '<p>' . get_string('email_orderconfirmation_body', 'local_moderncommerce', $order->ordernumber) . '</p>';

        $html .= '<h3>' . get_string('orderdetails', 'local_moderncommerce') . '</h3>';
        $html .= '<p><strong>' . get_string('ordernumber', 'local_moderncommerce') . ':</strong> ' . $order->ordernumber . '<br>';
        $html .= '<strong>' . get_string('orderdate', 'local_moderncommerce') . ':</strong> '
            . userdate($order->timecreated) . '<br>';
        $html .= '<strong>' . get_string('status', 'local_moderncommerce') . ':</strong> '
            . get_string('orderstatus_' . $order->status, 'local_moderncommerce') . '</p>';

        $html .= '<h3>' . get_string('orderitems', 'local_moderncommerce') . '</h3>';
        $html .= '<table class="order-table">';
        $html .= '<thead><tr><th>' . get_string('course', 'local_moderncommerce') . '</th><th>'
            . get_string('price', 'local_moderncommerce') . '</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($items as $item) {
            $itemname = self::get_item_display_name($item);
            $html .= '<tr><td>' . $itemname . '</td><td>' .
                pricing_service::format_order_price((float)$item->total, $order) . '</td></tr>';
        }

        $html .= '<tr class="total-row"><td>' . get_string('total', 'local_moderncommerce') . '</td><td>' .
            pricing_service::format_order_price((float)$order->total, $order) . '</td></tr>';
        $html .= '</tbody></table>';

        $html .= '<p><a href="' . $CFG->wwwroot . '/local/moderncommerce/order.php?id=' . $order->id .
            '" class="button">' . get_string('vieworder', 'local_moderncommerce') . '</a></p>';

        $html .= '<p>' . get_string('email_thankyou', 'local_moderncommerce') . '</p>';
        $html .= '</div>';

        $html .= self::render_footer_html();

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Render payment receipt HTML
     */
    private static function render_payment_receipt_html($order, $transaction, $items, $user) {
        global $CFG;

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .info-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745; }
            .order-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .order-table th, .order-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            .order-table th { background: #f0f0f0; }
            .total-row { font-weight: bold; background: #e9ecef; }
        </style></head><body><div class="container">';

        $html .= '<div class="header"><h1>✓ ' . get_string('paymentreceived', 'local_moderncommerce') . '</h1></div>';

        $html .= '<div class="content">';
        $html .= '<p>' . get_string('email_hello', 'local_moderncommerce', fullname($user)) . '</p>';
        $html .= '<p>' . get_string(
            'email_paymentreceipt_body',
            'local_moderncommerce',
            pricing_service::format_order_price((float)$order->total, $order)
        ) . '</p>';

        $html .= '<div class="info-box">';
        $html .= '<h3>' . get_string('paymentdetails', 'local_moderncommerce') . '</h3>';
        $transactionid = $transaction->gatewaytransactionid ?? ($transaction->transactionid ?? ($transaction->reference ?? ''));
        $html .= '<p><strong>' . get_string('transactionid', 'local_moderncommerce') . ':</strong> ' . $transactionid . '<br>';
        $html .= '<strong>' . get_string('paymentmethod', 'local_moderncommerce') . ':</strong> '
            . ucfirst((string)($transaction->gateway ?? '')) . '<br>';
        $html .= '<strong>' . get_string('amount', 'local_moderncommerce') . ':</strong> ' .
            pricing_service::format_order_price((float)($transaction->amount ?? $order->total), $order) . '<br>';
        $html .= '<strong>' . get_string('date', 'local_moderncommerce') . ':</strong> '
            . userdate($transaction->timecreated ?? time()) . '</p>';
        $html .= '</div>';

        $html .= '<h3>' . get_string('purchaseditems', 'local_moderncommerce') . '</h3>';
        $html .= '<table class="order-table">';
        $html .= '<thead><tr><th>' . get_string('course', 'local_moderncommerce') . '</th><th>'
            . get_string('price', 'local_moderncommerce') . '</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($items as $item) {
            $itemname = self::get_item_display_name($item);
            $html .= '<tr><td>' . $itemname . '</td><td>' .
                pricing_service::format_order_price((float)$item->total, $order) . '</td></tr>';
        }

        $html .= '<tr class="total-row"><td>' . get_string('total', 'local_moderncommerce') . '</td><td>' .
            pricing_service::format_order_price((float)$order->total, $order) . '</td></tr>';
        $html .= '</tbody></table>';

        $html .= '<p>' . get_string('email_accesscourses', 'local_moderncommerce') . '</p>';
        $html .= '</div>';

        $html .= self::render_footer_html();

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Render enrollment confirmation HTML
     */
    private static function render_enrollment_html($user, $course, $ordernumber) {
        global $CFG;

        $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #17a2b8; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .course-box { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center; }
            .button {
                display: inline-block;
                padding: 12px 24px;
                background: #17a2b8;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin: 10px 0;
            }
        </style></head><body><div class="container">';

        $html .= '<div class="header"><h1>🎓 ' . get_string('enrollmentconfirmation', 'local_moderncommerce') . '</h1></div>';

        $html .= '<div class="content">';
        $html .= '<p>' . get_string('email_hello', 'local_moderncommerce', fullname($user)) . '</p>';
        $html .= '<p>' . get_string('email_enrollment_body', 'local_moderncommerce', $course->fullname) . '</p>';

        $html .= '<div class="course-box">';
        $html .= '<h2>' . $course->fullname . '</h2>';
        if (!empty($course->summary)) {
            $html .= '<p>' . format_text($course->summary, FORMAT_HTML) . '</p>';
        }
        $html .= '<p><a href="' . $courseurl->out(false) . '" class="button">'
            . get_string('gotocourse', 'local_moderncommerce') . '</a></p>';
        $html .= '</div>';

        $html .= '<p>' . get_string('email_ordernumber', 'local_moderncommerce', $ordernumber) . '</p>';
        $html .= '</div>';

        $html .= self::render_footer_html();

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Render key redemption HTML
     */
    private static function render_key_redemption_html($user, $course, $keycode) {
        global $CFG;

        $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #6f42c1; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .key-box {
                background: white;
                padding: 20px;
                margin: 20px 0;
                border: 2px dashed #6f42c1;
                border-radius: 8px;
                text-align: center;
            }
            .button {
                display: inline-block;
                padding: 12px 24px;
                background: #6f42c1;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin: 10px 0;
            }
        </style></head><body><div class="container">';

        $html .= '<div class="header"><h1>🔑 ' . get_string('keyredeemed', 'local_moderncommerce') . '</h1></div>';

        $html .= '<div class="content">';
        $html .= '<p>' . get_string('email_hello', 'local_moderncommerce', fullname($user)) . '</p>';
        $html .= '<p>' . get_string('email_keyredemption_body', 'local_moderncommerce', $course->fullname) . '</p>';

        $html .= '<div class="key-box">';
        $html .= '<p><strong>' . get_string('enrollmentkey', 'local_moderncommerce') . ':</strong></p>';
        $html .= '<p style="font-size: 20px; font-family: monospace; color: #6f42c1;">' . $keycode . '</p>';
        $html .= '</div>';

        $html .= '<h3>' . $course->fullname . '</h3>';
        $html .= '<p><a href="' . $courseurl->out(false) . '" class="button">'
            . get_string('gotocourse', 'local_moderncommerce') . '</a></p>';
        $html .= '</div>';

        $html .= self::render_footer_html();

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Render refund confirmation HTML
     */
    private static function render_refund_html($order, $refund, $user) {
        global $CFG;

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #ffc107; color: #333; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .refund-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #ffc107; }
        </style></head><body><div class="container">';

        $html .= '<div class="header"><h1>' . get_string('refundprocessed', 'local_moderncommerce') . '</h1></div>';

        $html .= '<div class="content">';
        $html .= '<p>' . get_string('email_hello', 'local_moderncommerce', fullname($user)) . '</p>';
        $html .= '<p>' . get_string('email_refund_body', 'local_moderncommerce', $order->ordernumber) . '</p>';

        $html .= '<div class="refund-box">';
        $html .= '<h3>' . get_string('refunddetails', 'local_moderncommerce') . '</h3>';
        $html .= '<p><strong>' . get_string('ordernumber', 'local_moderncommerce') . ':</strong> ' . $order->ordernumber . '<br>';
        $html .= '<strong>' . get_string('refundamount', 'local_moderncommerce') . ':</strong> ' .
            pricing_service::format_order_price((float)$refund->amount, $order) . '<br>';
        $html .= '<strong>' . get_string('refundreason', 'local_moderncommerce') . ':</strong> ' . $refund->reason . '<br>';
        $html .= '<strong>' . get_string('date', 'local_moderncommerce') . ':</strong> ' . userdate($refund->timecreated) . '</p>';
        $html .= '</div>';

        $html .= '<p>' . get_string('email_refund_timeline', 'local_moderncommerce') . '</p>';
        $html .= '</div>';

        $html .= self::render_footer_html();

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Route a notification through the Modern Commerce notification subsystem.
     *
     * Always returns true: the subsystem is part of core, so the caller should stop
     * and not also send inline. (The inline email paths in the send_* methods remain
     * as a defensive fallback only.)
     *
     * @param string $eventkey Canonical event key.
     * @param string $category Notification category (transactional|operational|...).
     * @param string $templatekey Email template key to render.
     * @param int $userid Recipient user id.
     * @param array $placeholders Entity placeholder data.
     * @param string|null $contexturl Deep-link URL.
     * @param int $relatedid Related object id (orderid/courseid).
     * @return bool
     */
    private static function notify_hub(
        string $eventkey,
        string $category,
        string $templatekey,
        int $userid,
        array $placeholders,
        ?string $contexturl,
        int $relatedid
    ): bool {
        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', $eventkey))
            ->category($category)
            ->template($templatekey)
            ->to_user($userid)
            ->placeholders($placeholders)
            ->related($relatedid);
        if (!empty($contexturl)) {
            $notification->context_url($contexturl);
        }

        \local_moderncommerce\notifications\api::notify($notification);
        return true;
    }

    /**
     * Send the "new sale" operational alert to store admins.
     *
     * Fires independently of the learner receipt setting via the core notification
     * subsystem.
     *
     * @param object $order Order record.
     * @param object $transaction Transaction record.
     * @param array $items Order items.
     * @return void
     */
    private static function notify_admins_new_sale($order, $transaction, $items): void {
        global $CFG, $DB;

        $buyer = $DB->get_record('user', ['id' => $order->userid]);
        $orderurl = $CFG->wwwroot . '/local/moderncommerce/order.php?id=' . $order->id;

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'new_sale'))
            ->category('operational')
            ->template('ops_new_sale')
            ->placeholders([
                'order_number' => $order->ordernumber,
                'order_total' => pricing_service::format_order_price((float) $order->total, $order),
                'customer_name' => $buyer ? fullname($buyer) : '',
                'product_name' => self::items_to_courses_list($items),
                'admin_order_url' => $orderurl,
            ])
            ->context_url($orderurl)
            ->related((int) $order->id);

        \local_moderncommerce\notifications\api::notify_admins($notification);
    }

    /**
     * Build a <br>-separated list of order item display names.
     *
     * @param array $items Order items.
     * @return string
     */
    private static function items_to_courses_list($items): string {
        $names = [];
        foreach ($items as $item) {
            $names[] = self::get_item_display_name($item);
        }
        return implode('<br>', $names);
    }

    /**
     * Send email using Moodle's email API
     */
    private static function send_email($user, $subject, $messagehtml, $messagetext) {
        $noreplyuser = \core_user::get_noreply_user();

        try {
            $bodyhtml = self::extract_body_content((string) $messagehtml);
            $rendered = renderer::render_subject_body((string) $subject, $bodyhtml);
            $subject = $rendered['subject'];
            $messagehtml = $rendered['html'];
            $messagetext = $rendered['plain'];
        } catch (\Throwable $e) {
            debugging('Error applying Modern Commerce email shell: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return email_to_user(
            $user,
            $noreplyuser,
            $subject,
            $messagetext,
            $messagehtml
        );
    }

    /**
     * Send a core template selected in settings or fall back to a seeded key.
     *
     * @param object $user Recipient user record.
     * @param string $configkey Config setting storing an optional template id.
     * @param string $defaultkey Seeded template key to use when no override is set.
     * @param array $placeholders Placeholder data.
     * @param string $debugcontext Human-readable debug label.
     * @return bool True when sent.
     */
    private static function send_configured_template(
        $user,
        string $configkey,
        string $defaultkey,
        array $placeholders,
        string $debugcontext
    ): bool {
        $templatekey = self::configured_template_key($configkey, $defaultkey);
        if ($templatekey === '') {
            return false;
        }

        try {
            return email_api::send($templatekey, (int) $user->id, $placeholders);
        } catch (\Throwable $e) {
            debugging('Error rendering ' . $debugcontext . ' template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Resolve a selected template id to a template key.
     *
     * @param string $configkey Config setting storing an optional template id.
     * @param string $defaultkey Seeded fallback template key.
     * @return string Template key or empty string when the table is not ready.
     */
    private static function configured_template_key(string $configkey, string $defaultkey): string {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(new \xmldb_table('local_moderncommerce_emailtpl'))) {
            return '';
        }

        $templateid = (int) get_config('local_moderncommerce', $configkey);
        if ($templateid > 0) {
            $templatekey = $DB->get_field('local_moderncommerce_emailtpl', 'template_key', [
                'id' => $templateid,
                'status' => 'active',
            ]);
            if (!empty($templatekey)) {
                return (string) $templatekey;
            }
        }

        return $defaultkey;
    }

    /**
     * Extract a full HTML document body before passing fallback content through the shell.
     *
     * @param string $html HTML content.
     * @return string Inner body HTML when available.
     */
    private static function extract_body_content(string $html): string {
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches)) {
            return trim($matches[1]);
        }

        return $html;
    }

    /**
     * Render the common default email footer.
     *
     * @return string Footer HTML.
     */
    private static function render_footer_html(): string {
        global $CFG;

        $settings = commerce_settings_service::get_admin_settings();
        $storename = $settings->businessname ?: format_string($CFG->sitename);

        $html = '<div class="footer"><p>' . s($storename);
        if (!empty($settings->supportemail)) {
            $html .= '<br>' . s($settings->supportemail);
        }
        $html .= '<br>' . s($CFG->wwwroot);
        if (!empty($settings->supporturl)) {
            $html .= '<br><a href="' . s($settings->supporturl) . '">' . s($settings->supporturl) . '</a>';
        }
        $html .= '</p></div>';

        return $html;
    }

    /**
     * Simple placeholder replacement for configured subject/body
     *
     * @param string $text Text with placeholders
     * @param array $placeholders Key-value pairs for replacement
     * @return string Text with placeholders replaced
     */
    private static function replace_simple_placeholders($text, $placeholders) {
        if ($text === '' || $text === null) {
            return (string)$text;
        }

        $globaldefaults = placeholder_engine::get_global_placeholder_values();
        $engine = new placeholder_engine();

        return $engine->substitute_placeholders($text, $placeholders, $globaldefaults);
    }
}

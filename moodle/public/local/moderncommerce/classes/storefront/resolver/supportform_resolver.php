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
 * Resolver for the public commerce support form widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use context_system;
use local_moderncommerce\output\public_page;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\services\captcha_service;
use local_moderncommerce\storefront\style_controls;
use moodle_url;

/**
 * Builds the support form payload.
 */
class supportform_resolver implements widget_resolver {
    /**
     * Build the support form payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context.
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        global $USER;

        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();
        $supportemail = public_page::support_email();

        return [
            'heading' => self::clean((string)($settings['heading'] ?? $instance->get('title')), $ctx),
            'description' => self::clean((string)($settings['description'] ?? $instance->get('subtitle')), $ctx),
            'action' => (new moodle_url('/local/moderncommerce/support.php'))->out(false),
            'supportemail' => $supportemail,
            'emailurl' => $supportemail !== '' ? 'mailto:' . $supportemail : '',
            'buttonlabel' => self::clean(
                (string)($settings['buttonlabel'] ?? get_string('p1_support_buttonlabel', 'local_moderncommerce')),
                $ctx
            ),
            'emailbuttonlabel' => self::clean(
                (string)($settings['emailbuttonlabel'] ?? get_string('p1_support_emailbuttonlabel', 'local_moderncommerce')),
                $ctx
            ),
            'messagelabel' => self::clean(
                (string)($settings['messagelabel'] ?? get_string('p1_support_message', 'local_moderncommerce')),
                $ctx
            ),
            'messageplaceholder' => self::clean((string)($settings['messageplaceholder'] ?? ''), $ctx),
            'bgcolor' => style_controls::colour($settings['bgcolor'] ?? ''),
            'cardbgcolor' => style_controls::colour($settings['cardbgcolor'] ?? ''),
            'cardbordercolor' => style_controls::colour($settings['cardbordercolor'] ?? ''),
            'cardborderwidth' => style_controls::number($settings['cardborderwidth'] ?? 1, 1, 0, 24),
            'cardradius' => style_controls::number($settings['cardradius'] ?? 8, 8, 0, 96),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'formlabelcolor' => style_controls::colour($settings['formlabelcolor'] ?? ''),
            'inputbgcolor' => style_controls::colour($settings['inputbgcolor'] ?? ''),
            'inputbordercolor' => style_controls::colour($settings['inputbordercolor'] ?? ''),
            'inputtextcolor' => style_controls::colour($settings['inputtextcolor'] ?? ''),
            'buttoncolor' => style_controls::colour($settings['buttoncolor'] ?? ''),
            'buttontextcolor' => style_controls::colour($settings['buttontextcolor'] ?? ''),
            'secondarybuttoncolor' => style_controls::colour($settings['secondarybuttoncolor'] ?? ''),
            'secondarybuttontextcolor' => style_controls::colour($settings['secondarybuttontextcolor'] ?? ''),
            'buttonradius' => style_controls::number($settings['buttonradius'] ?? 0, 0, 0, 96),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 0),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 0),
            'defaultname' => isloggedin() && !isguestuser() ? fullname($USER) : '',
            'defaultemail' => isloggedin() && !isguestuser() ? (string)$USER->email : '',
            'recaptcha' => captcha_service::widget_config(),
            'categories' => [
                ['value' => 'payment', 'label' => get_string('p1_support_category_payment', 'local_moderncommerce')],
                ['value' => 'access', 'label' => get_string('p1_support_category_access', 'local_moderncommerce')],
                ['value' => 'invoice', 'label' => get_string('p1_support_category_invoice', 'local_moderncommerce')],
                ['value' => 'refund', 'label' => get_string('p1_support_category_refund', 'local_moderncommerce')],
                ['value' => 'key', 'label' => get_string('p1_support_category_key', 'local_moderncommerce')],
                ['value' => 'subscription', 'label' => get_string('p1_support_category_subscription', 'local_moderncommerce')],
                ['value' => 'general', 'label' => get_string('p1_support_category_general', 'local_moderncommerce')],
            ],
            'labels' => [
                'name' => get_string('p1_support_name', 'local_moderncommerce'),
                'email' => get_string('p1_support_email', 'local_moderncommerce'),
                'category' => get_string('p1_support_category_prompt', 'local_moderncommerce'),
                'ordernumber' => get_string('ordernumber', 'local_moderncommerce'),
                'optional' => get_string('optional', 'local_moderncommerce'),
                'message' => get_string('p1_support_message', 'local_moderncommerce'),
                'sending' => get_string('p1_support_sending', 'local_moderncommerce'),
            ],
        ];
    }

    /**
     * Clean display text.
     *
     * @param string $value Raw text.
     * @param \context $ctx Context.
     * @return string
     */
    private static function clean(string $value, \context $ctx): string {
        return format_string(trim($value), true, ['context' => $ctx, 'escape' => false]);
    }
}

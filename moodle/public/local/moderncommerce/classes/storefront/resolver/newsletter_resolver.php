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
 * Resolver for the newsletter / lead capture widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use local_moderncommerce\persistent\widget;
use local_moderncommerce\services\captcha_service;
use local_moderncommerce\storefront\style_controls;

/**
 * Builds the configuration payload for a newsletter widget.
 */
class newsletter_resolver implements widget_resolver {
    /**
     * Build the newsletter payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (unused).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();

        return [
            'method' => 'local_moderncommerce_newsletter_subscribe',
            'heading' => (string)($settings['heading'] ?? '') ?: (string) $instance->get('title'),
            'description' => (string)($settings['description'] ?? '') ?: (string) $instance->get('subtitle'),
            'placeholder' => (string)($settings['placeholder'] ?? '')
                ?: get_string('emailplaceholder', 'local_moderncommerce'),
            'buttonlabel' => (string)($settings['buttonlabel'] ?? '')
                ?: get_string('subscribe', 'local_moderncommerce'),
            'successmessage' => (string)($settings['successmessage'] ?? '')
                ?: get_string('subscribed', 'local_moderncommerce'),
            'recaptcha' => captcha_service::widget_config(),
            'bgcolor' => style_controls::colour($settings['bgcolor'] ?? ''),
            'panelbgcolor' => style_controls::colour($settings['panelbgcolor'] ?? ''),
            'panelbordercolor' => style_controls::colour($settings['panelbordercolor'] ?? ''),
            'panelborderwidth' => style_controls::number($settings['panelborderwidth'] ?? 1, 1, 0, 24),
            'panelradius' => style_controls::number($settings['panelradius'] ?? 8, 8, 0, 96),
            'panelpaddingtop' => style_controls::number($settings['panelpaddingtop'] ?? 0),
            'panelpaddingright' => style_controls::number($settings['panelpaddingright'] ?? 0),
            'panelpaddingbottom' => style_controls::number($settings['panelpaddingbottom'] ?? 0),
            'panelpaddingleft' => style_controls::number($settings['panelpaddingleft'] ?? 0),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'inputbgcolor' => style_controls::colour($settings['inputbgcolor'] ?? ''),
            'inputbordercolor' => style_controls::colour($settings['inputbordercolor'] ?? ''),
            'inputtextcolor' => style_controls::colour($settings['inputtextcolor'] ?? ''),
            'placeholdercolor' => style_controls::colour($settings['placeholdercolor'] ?? ''),
            'buttoncolor' => style_controls::colour($settings['buttoncolor'] ?? ''),
            'buttontextcolor' => style_controls::colour($settings['buttontextcolor'] ?? ''),
            'buttonradius' => style_controls::number($settings['buttonradius'] ?? 0, 0, 0, 96),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 0),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 0),
            'labels' => [
                'emailrequired' => get_string('emailrequired', 'local_moderncommerce'),
                'invalidemail' => get_string('invalidemail', 'local_moderncommerce'),
                'subscribing' => get_string('p1_newsletter_subscribing', 'local_moderncommerce'),
                'servicerequestfailed' => get_string('p1_storefront_servicerequestfailed', 'local_moderncommerce'),
            ],
        ];
    }
}

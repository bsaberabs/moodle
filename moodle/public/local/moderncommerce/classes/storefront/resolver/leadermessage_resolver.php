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
 * Resolver for the leader message widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use context_system;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\style_controls;
use moodle_url;

/**
 * Builds the portrait + signed message payload for a named leader.
 *
 * Deliberately generic: the same widget serves an ED/CEO welcome, a
 * Vice-Chancellor's address, a Director's statement, or a founder's note. Every
 * element beyond the message body is optional and individually switchable, so a
 * site that only wants a heading, portrait and paragraphs gets exactly that.
 */
class leadermessage_resolver implements widget_resolver {
    /** @var string[] Supported design treatments. */
    private const STYLES = ['elevated', 'overlay', 'quote', 'flat'];

    /**
     * Build the leader message payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (unused).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();

        $style = (string) ($settings['style'] ?? 'elevated');
        if (!in_array($style, self::STYLES, true)) {
            $style = 'elevated';
        }

        // Portrait: accepts either a pasted URL or an uploaded file, matching the
        // instructors widget. Root-relative uploads are expanded to absolute URLs.
        $photo = style_controls::sourced_image($settings, 'photosource', 'photourl', 'photofile', 'photo');
        if ($photo !== '' && !preg_match('#^https?://#i', $photo) && $photo[0] === '/') {
            $photo = (new moodle_url($photo))->out(false);
        }

        $signature = style_controls::sourced_image(
            $settings,
            'signaturesource',
            'signatureurl',
            'signaturefile',
            'signature'
        );
        if ($signature !== '' && !preg_match('#^https?://#i', $signature) && $signature[0] === '/') {
            $signature = (new moodle_url($signature))->out(false);
        }

        // Each optional element is gated by BOTH its toggle and having content:
        // an admin who enables a row but leaves it blank should not get an empty
        // slot in the layout.
        $eyebrow = self::clean((string) ($settings['eyebrow'] ?? ''), $ctx);
        $name = self::clean((string) ($settings['leadername'] ?? ''), $ctx);
        $role = self::clean((string) ($settings['leaderrole'] ?? ''), $ctx);
        $ctalabel = self::clean((string) ($settings['ctalabel'] ?? ''), $ctx);
        $ctaurl = style_controls::url($settings['ctaurl'] ?? '');

        $showphoto = self::truthy($settings['showphoto'] ?? true) && $photo !== '';
        $showsignature = self::truthy($settings['showsignature'] ?? false) && $signature !== '';
        $showcta = self::truthy($settings['showcta'] ?? false) && $ctalabel !== '' && $ctaurl !== '';

        return [
            'style' => $style,
            // Which side the portrait sits on; the copy takes the other column.
            'mediaposition' => ((string) ($settings['mediaposition'] ?? 'right')) === 'left' ? 'left' : 'right',

            'eyebrow' => $eyebrow,
            'showeyebrow' => self::truthy($settings['showeyebrow'] ?? true) && $eyebrow !== '',
            'title' => self::clean((string) $instance->get('title'), $ctx),
            'showtitle' => self::truthy($settings['showtitle'] ?? true),
            // Rich body copy: the message itself, the one element that is never optional.
            'message' => self::html((string) ($settings['message'] ?? ''), $ctx),

            'photo' => $photo,
            'showphoto' => $showphoto,
            'photoalt' => $name !== '' ? $name : self::clean((string) $instance->get('title'), $ctx),

            'leadername' => $name,
            'leaderrole' => $role,
            'showattribution' => self::truthy($settings['showattribution'] ?? true)
                && ($name !== '' || $role !== ''),

            'signature' => $signature,
            'showsignature' => $showsignature,

            'ctalabel' => $ctalabel,
            'ctaurl' => $ctaurl,
            'showcta' => $showcta,

            'showaccent' => self::truthy($settings['showaccent'] ?? true),
            'justifytext' => self::truthy($settings['justifytext'] ?? false),

            'bgcolor' => style_controls::colour($settings['bgcolor'] ?? ''),
            'accentcolor' => style_controls::colour($settings['accentcolor'] ?? ''),
            'eyebrowcolor' => style_controls::colour($settings['eyebrowcolor'] ?? ''),
            'eyebrowfontsize' => style_controls::number($settings['eyebrowfontsize'] ?? 0, 0, 0, 96),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'namecolor' => style_controls::colour($settings['namecolor'] ?? ''),
            'namefontsize' => style_controls::number($settings['namefontsize'] ?? 0, 0, 0, 96),
            'rolecolor' => style_controls::colour($settings['rolecolor'] ?? ''),
            'rolefontsize' => style_controls::number($settings['rolefontsize'] ?? 0, 0, 0, 96),
            'buttoncolor' => style_controls::colour($settings['buttoncolor'] ?? ''),
            'buttontextcolor' => style_controls::colour($settings['buttontextcolor'] ?? ''),
            'buttonradius' => style_controls::number($settings['buttonradius'] ?? 8, 8, 0, 96),
            'mediaradius' => style_controls::number($settings['mediaradius'] ?? 16, 16, 0, 96),
            'mediawidth' => style_controls::number($settings['mediawidth'] ?? 38, 38, 20, 60),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 72, 72),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 72, 72),
        ];
    }

    /**
     * Normalise a stored toggle to a boolean.
     *
     * Settings arrive as strings ('0'/'1') from the widget editor, so a plain
     * cast is not enough - '0' is truthy as a string.
     *
     * @param mixed $value Raw setting value.
     * @return bool
     */
    private static function truthy($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return $value !== '' && $value !== '0' && strtolower($value) !== 'false';
        }

        return !empty($value);
    }

    /**
     * Clean a single-line display string.
     *
     * @param string $value Raw text.
     * @param \context $ctx Context.
     * @return string
     */
    private static function clean(string $value, \context $ctx): string {
        return format_string(trim($value), true, ['context' => $ctx, 'escape' => false]);
    }

    /**
     * Clean the rich message body, preserving the admin's paragraphs.
     *
     * @param string $value Raw HTML.
     * @param \context $ctx Context.
     * @return string
     */
    private static function html(string $value, \context $ctx): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return format_text($value, FORMAT_HTML, ['context' => $ctx, 'noclean' => false]);
    }
}

<?php
// This file is part of Moodle - https://moodle.org/
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

namespace local_oriskill;

/**
 * Download links of the free Moodle mobile app (App Store and Google Play), shown on the store home page and on the login
 * page, with the site address to type in the app.
 *
 * The app ids are those of the mobile app settings (Moodle's official app unless a branded app is set there).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile_app {

    /** Page types showing the block: store home page, login page. */
    const PAGETYPES = ['local-moderncommerce-index', 'login-index'];

    /**
     * Whether the block goes on a page of this type: the app must be able to connect (mobile services on).
     *
     * @param string $pagetype Page type.
     * @return bool
     */
    public static function shown_on(string $pagetype): bool {
        global $CFG;

        return in_array($pagetype, self::PAGETYPES, true) && !empty($CFG->enablemobilewebservice);
    }

    /**
     * Store pages of the app.
     *
     * @return array ios, android => URL
     */
    public static function store_urls(): array {
        $ios = get_config('tool_mobile', 'iosappid') ?: \tool_mobile\api::DEFAULT_IOS_APP_ID;
        $android = get_config('tool_mobile', 'androidappid') ?: \tool_mobile\api::DEFAULT_ANDROID_APP_ID;
        return [
            'ios' => 'https://apps.apple.com/app/id' . rawurlencode($ios),
            'android' => 'https://play.google.com/store/apps/details?id=' . rawurlencode($android),
        ];
    }

    /**
     * The block: why use the app, the address to type in it, the two store buttons.
     *
     * @return string HTML
     */
    public static function html(): string {
        global $CFG;

        $urls = self::store_urls();
        $button = function(string $url, string $icon, string $label): string {
            return \html_writer::link($url, \html_writer::tag('i', '', ['class' => "fa-brands $icon", 'aria-hidden' => 'true']) .
                ' ' . \html_writer::span($label), ['class' => 'btn oriskill-mobileapp-store', 'target' => '_blank',
                'rel' => 'noopener']);
        };
        $address = \html_writer::tag('strong', s(parse_url($CFG->wwwroot, PHP_URL_HOST)));
        return \html_writer::tag('section',
            \html_writer::div(
                \html_writer::tag('h2', get_string('mobileapp:title', 'local_oriskill'), ['class' => 'h5 mb-1']) .
                \html_writer::tag('p', get_string('mobileapp:text', 'local_oriskill', $address), ['class' => 'mb-0']),
                'oriskill-mobileapp-text') .
            \html_writer::div(
                $button($urls['ios'], 'fa-apple', get_string('mobileapp:ios', 'local_oriskill')) .
                $button($urls['android'], 'fa-google-play', get_string('mobileapp:android', 'local_oriskill')),
                'oriskill-mobileapp-stores'),
            ['class' => 'oriskill-mobileapp', 'id' => 'oriskill-mobileapp']);
    }
}

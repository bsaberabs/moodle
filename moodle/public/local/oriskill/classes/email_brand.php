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
 * An e-mail sent for a trainer carries the trainer's brand rather than the platform's: their name as the sender, in
 * the subject and the text, their logo and their colour in the shop's e-mail layout, replies to their address.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_brand {

    /** @var array|null Names, logos and colours of the platform, read once per request. */
    protected static ?array $platform = null;

    /**
     * Put the trainer's brand on an e-mail about to leave.
     *
     * @param \core\email $email E-mail (changed in place).
     * @param \stdClass $creator Trainer.
     */
    public static function apply(\core\email $email, \stdClass $creator): void {
        global $DB;

        $platform = self::platform();
        $name = format_string($creator->displayname);
        if ($name === '') {
            return;
        }
        // Names of the platform, longest first so that the full site name goes before its short name.
        $names = array_filter($platform['names'], fn($n) => $n !== '' && $n !== $name);
        usort($names, fn($a, $b) => \core_text::strlen($b) <=> \core_text::strlen($a));
        $replace = function(string $text, bool $html) use ($names, $name): string {
            foreach ($names as $platformname) {
                $text = str_replace($html ? s($platformname) : $platformname, $html ? s($name) : $name, $text);
                if ($html) {
                    $text = str_replace($platformname, $name, $text);
                }
            }
            return $text;
        };
        $email->subject = $replace($email->subject, false);
        $email->messagetext = $replace($email->messagetext, false);
        if ($email->messagehtml !== '') {
            $html = $replace($email->messagehtml, true);
            $logo = creator_service::logo_url($creator);
            foreach ($platform['logos'] as $platformlogo) {
                if ($platformlogo === '' || !str_contains($html, $platformlogo)) {
                    continue;
                }
                $html = $logo ? str_replace($platformlogo, $logo->out(false), $html)
                    // No logo of their own: the platform's is taken out, their name stays next to it.
                    : preg_replace('~<img[^>]+' . preg_quote($platformlogo, '~') . '[^>]*>~i', '', $html);
            }
            $style = creator_service::showcase_style($creator);
            foreach ($platform['colours'] as $colour) {
                $html = str_ireplace($colour, $style['primary'], $html);
            }
            // The platform's contact in the footer: the trainer's address and showcase instead.
            $traineremail = (string) $DB->get_field('user', 'email', ['id' => $creator->userid]);
            foreach ($platform['emails'] as $platformemail) {
                $html = str_ireplace($platformemail, $traineremail, $html);
                $email->messagetext = str_ireplace($platformemail, $traineremail, $email->messagetext);
            }
            $showcase = !empty($creator->customdomain) ? 'https://' . $creator->customdomain . '/'
                : creator_service::showcase_url($creator)->out(false);
            $root = rtrim($platform['siteurl'], '/');
            $html = preg_replace('~<a([^>]*)href="' . preg_quote($root, '~') . '/?"([^>]*)>\s*' . preg_quote($root, '~') . '/?\s*</a>~i',
                '<a$1href="' . s($showcase) . '"$2>' . s($showcase) . '</a>', $html);
            $email->messagehtml = $html;
        }
        // The platform's sending accounts (no-reply, support) speak in the trainer's name, unless a sender name was
        // already chosen for this e-mail (the trainer's access e-mail).
        $platformsenders = array_merge($platform['names'], [\core_user::get_noreply_user()->firstname,
            \core_user::get_support_user()->firstname, '']);
        if ((int) ($email->from->id ?? 0) <= 0 && in_array(trim((string) $email->from->firstname), $platformsenders, true)) {
            $email->from->firstname = $name;
            $email->from->lastname = '';
            $email->from->firstnamephonetic = $email->from->lastnamephonetic = $email->from->middlename = $email->from->alternatename = '';
        }
        if (trim((string) $email->replyto) === '') {
            $email->replyto = (string) $DB->get_field('user', 'email', ['id' => $creator->userid]);
            $email->replytoname = $name;
        }
    }

    /**
     * Names, logos and colours the platform puts in its e-mails.
     *
     * @return array ['names' => string[], 'logos' => string[], 'colours' => string[]]
     */
    protected static function platform(): array {
        global $SITE;

        if (self::$platform !== null) {
            return self::$platform;
        }
        global $CFG;

        $names = [format_string($SITE->fullname), format_string($SITE->shortname)];
        $logos = [];
        $colours = [];
        $emails = [(string) ($CFG->supportemail ?? '')];
        if (class_exists('\local_moderncommerce\email\placeholder_engine')) {
            try {
                $values = \local_moderncommerce\email\placeholder_engine::get_global_placeholder_values();
                $names[] = (string) ($values['sitename'] ?? '');
                $logos = [(string) ($values['logo'] ?? ''), (string) ($values['logo_compact'] ?? '')];
                $emails[] = (string) ($values['supportemail'] ?? '');
            } catch (\Throwable $e) {
                debugging('E-mail brand: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
            $defaults = class_exists('\local_moderncommerce\branding') ? \local_moderncommerce\branding::get_defaults() : [];
            foreach (['brand_primary', 'brand_secondary'] as $key) {
                $colour = (string) get_config('local_moderncommerce', $key) ?: (string) ($defaults[$key] ?? '');
                if (preg_match('/^#[0-9a-f]{6}$/i', $colour)) {
                    $colours[] = $colour;
                }
            }
        }
        return self::$platform = ['names' => array_values(array_unique(array_filter($names))),
            'logos' => array_values(array_unique(array_filter($logos))), 'colours' => array_values(array_unique($colours)),
            'emails' => array_values(array_unique(array_filter($emails, fn($e) => validate_email($e)))),
            'siteurl' => (string) $CFG->wwwroot];
    }
}

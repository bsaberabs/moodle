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
 * Privacy of a trainer's pages: the texts of the consents to marketing (asked on the capture pages and the order step,
 * never ticked in advance, each one kept with its date, text and page) and the cookie banner of their public pages
 * ("Refuse all" as visible as "Accept all"; audience measurement and advertising load only once accepted).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privacy_service {

    /** Settings of each trainer. */
    const TABLE = 'local_oriskill_privacy';

    /** Consents given. */
    const CONSENTS = 'local_oriskill_consents';

    /** Kinds of consent to marketing. */
    const KINDS = ['direct', 'partners'];

    /** Days the visitor's choice of cookies is kept. */
    const CHOICE_DAYS = 180;

    /**
     * Settings of a trainer, defaults filled in.
     *
     * @param \stdClass $creator Trainer.
     * @return \stdClass
     */
    public static function settings(\stdClass $creator): \stdClass {
        global $DB;

        $saved = json_decode((string) $DB->get_field(self::TABLE, 'settings', ['creatorid' => $creator->id]), true) ?: [];
        $name = format_string($creator->displayname);
        $defaults = [
            'textdirect' => get_string('privacy:default_direct', 'local_oriskill', $name),
            'textpartners' => get_string('privacy:default_partners', 'local_oriskill', $name),
            'askpartners' => 0,
            'bannersite' => 0,
            'bannerfunnels' => 0,
            'autopopup' => 0,
            'position' => 'bottom',
            'bannertext' => get_string('privacy:default_banner', 'local_oriskill'),
            'policyurl' => '',
            'background' => '#ffffff',
            'textcolor' => '#1f2937',
            'buttoncolor' => '',
            'gaid' => '',
            'metapixel' => '',
        ];
        return (object) (array_intersect_key($saved, $defaults) + $defaults);
    }

    /**
     * Save the settings of a trainer.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $data Form data.
     */
    public static function save(\stdClass $creator, \stdClass $data): void {
        global $DB;

        $color = fn($value, $default) => preg_match('/^#[0-9a-f]{6}$/i', trim((string) $value)) ? strtolower(trim($value)) : $default;
        $settings = [
            'textdirect' => \core_text::substr(trim((string) $data->textdirect), 0, 1000),
            'textpartners' => \core_text::substr(trim((string) $data->textpartners), 0, 1000),
            'askpartners' => empty($data->askpartners) ? 0 : 1,
            'bannersite' => empty($data->bannersite) ? 0 : 1,
            'bannerfunnels' => empty($data->bannerfunnels) ? 0 : 1,
            'autopopup' => empty($data->autopopup) ? 0 : 1,
            'position' => ($data->position ?? '') === 'top' ? 'top' : 'bottom',
            'bannertext' => \core_text::substr(trim((string) $data->bannertext), 0, 1000),
            'policyurl' => preg_match('~^https?://\S+$~i', trim((string) $data->policyurl)) ? trim($data->policyurl) : '',
            'background' => $color($data->background ?? '', '#ffffff'),
            'textcolor' => $color($data->textcolor ?? '', '#1f2937'),
            'buttoncolor' => $color($data->buttoncolor ?? '', ''),
            'gaid' => preg_match('/^(G|UA|AW)-[A-Z0-9-]{4,20}$/i', trim((string) $data->gaid)) ? strtoupper(trim($data->gaid)) : '',
            'metapixel' => preg_match('/^\d{6,20}$/', trim((string) $data->metapixel)) ? trim($data->metapixel) : '',
        ];
        $record = (object) ['creatorid' => $creator->id, 'settings' => json_encode($settings), 'timemodified' => time()];
        if ($id = $DB->get_field(self::TABLE, 'id', ['creatorid' => $creator->id])) {
            $record->id = $id;
            $DB->update_record(self::TABLE, $record);
        } else {
            $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Text of a consent to marketing, as shown next to its box.
     *
     * @param \stdClass $creator Trainer.
     * @param string $kind direct or partners.
     * @return string
     */
    public static function consent_text(\stdClass $creator, string $kind): string {
        return self::settings($creator)->{'text' . $kind};
    }

    /**
     * Boxes of the consents to marketing (never ticked in advance), for a form.
     *
     * @param \stdClass $creator Trainer.
     * @return string HTML.
     */
    public static function consent_boxes(\stdClass $creator): string {
        $settings = self::settings($creator);
        $html = '';
        foreach (self::KINDS as $kind) {
            if ($kind === 'partners' && !$settings->askpartners) {
                continue;
            }
            $html .= \html_writer::div(
                \html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'consent' . $kind, 'value' => 1,
                    'id' => 'consent-' . $kind, 'class' => 'form-check-input']) .
                \html_writer::tag('label', s($settings->{'text' . $kind}), ['for' => 'consent-' . $kind,
                    'class' => 'form-check-label small']), 'form-check mb-2');
        }
        return $html;
    }

    /**
     * Record the consents a person just gave on a page: on their contact, and in the history (date, text, page).
     *
     * @param \stdClass $creator Trainer.
     * @param int $userid Person.
     * @param array $given kind => bool (direct, partners).
     * @param string $page Address of the page.
     * @param string $source Source of the contact when it is new (capture, order...).
     */
    public static function record(\stdClass $creator, int $userid, array $given, string $page, string $source): void {
        global $DB;

        $given = array_filter(array_intersect_key($given, array_flip(self::KINDS)));
        if (!$given) {
            return;
        }
        $contact = contact_service::from_user($creator, $userid, $source, ['consentdirect' => !empty($given['direct']),
            'consentpartners' => !empty($given['partners'])]);
        foreach (array_keys($given) as $kind) {
            $DB->insert_record(self::CONSENTS, (object) ['creatorid' => $creator->id, 'contactid' => $contact->id ?? 0,
                'userid' => $userid, 'kind' => $kind, 'text' => self::consent_text($creator, $kind),
                'page' => \core_text::substr($page, 0, 255), 'timecreated' => time()]);
        }
    }

    /**
     * Consents a contact gave, latest first.
     *
     * @param \stdClass $contact Contact.
     * @return array
     */
    public static function history(\stdClass $contact): array {
        global $DB;
        return $DB->get_records(self::CONSENTS, ['contactid' => $contact->id], 'timecreated DESC, id DESC');
    }

    /**
     * History of a contact's consents, for their page.
     *
     * @param \stdClass $contact Contact.
     * @return string HTML ('' when none was recorded).
     */
    public static function history_html(\stdClass $contact): string {
        $items = '';
        foreach (self::history($contact) as $consent) {
            $items .= \html_writer::tag('li', \html_writer::tag('strong', get_string('privacy:kind_' . $consent->kind,
                'local_oriskill')) . ' · ' . userdate($consent->timecreated, get_string('strftimedatetimeshort', 'langconfig')) .
                ($consent->page ? ' · ' . \html_writer::link($consent->page, s(parse_url($consent->page, PHP_URL_PATH) ?: $consent->page),
                    ['target' => '_blank', 'rel' => 'noopener']) : '') .
                \html_writer::div('« ' . s($consent->text) . ' »', 'text-muted'));
        }
        return $items ? \html_writer::div(\html_writer::div(get_string('privacy:history', 'local_oriskill'), 'oriskill-stat-label') .
            \html_writer::tag('ul', $items, ['class' => 'small ps-3 mb-0']), 'mt-3') : '';
    }

    /**
     * The cookie banner of a trainer's public page, when turned on for that kind of page.
     *
     * Necessary cookies (session, payment) need no consent. Audience measurement (Google Analytics) and advertising
     * (Meta pixel) load only after the visitor accepts them; the choice is kept in a cookie for CHOICE_DAYS days and
     * can be changed at any time from the "Cookies" link.
     *
     * @param \stdClass $creator Trainer.
     * @param string $where site (showcase, course pages) or funnel (order forms, capture pages).
     * @return string HTML and script ('' when the banner is off).
     */
    public static function banner(\stdClass $creator, string $where): string {
        $s = self::settings($creator);
        if (!($where === 'funnel' ? $s->bannerfunnels : $s->bannersite)) {
            return '';
        }
        $str = fn(string $key) => get_string('privacy:' . $key, 'local_oriskill');
        $button = $s->buttoncolor ?: '#7c3aed';
        $btn = 'border: 2px solid ' . $button . '; border-radius: 8px; padding: .5rem 1rem; font-weight: 600; cursor: pointer; ' .
            'font: inherit; ';
        $primary = $btn . 'background: ' . $button . '; color: #fff;';
        $categories = [
            ['necessary', true, $str('cat_necessary'), $str('cat_necessary_desc')],
            ['measure', false, $str('cat_measure'), $str('cat_measure_desc') . ($s->gaid ? ' (Google Analytics)' : '')],
            ['marketing', false, $str('cat_marketing'), $str('cat_marketing_desc') . ($s->metapixel ? ' (Meta)' : '')],
        ];
        $rows = '';
        foreach ($categories as [$key, $always, $label, $desc]) {
            $rows .= '<label style="display: flex; gap: .6rem; align-items: flex-start; margin: .6rem 0">' .
                '<input type="checkbox" data-cat="' . $key . '"' . ($always ? ' checked disabled' : '') . ' style="margin-top: .3rem">' .
                '<span><strong>' . s($label) . '</strong><br><span style="opacity: .8; font-size: .9em">' . s($desc) .
                '</span></span></label>';
        }
        $policy = $s->policyurl ? ' <a href="' . s($s->policyurl) . '" target="_blank" rel="noopener" style="color: inherit; text-decoration: underline">' .
            s($str('policy')) . '</a>' : '';
        $config = json_encode(['name' => 'oriskill_consent_' . (int) $creator->id, 'days' => self::CHOICE_DAYS,
            'ga' => $s->gaid, 'pixel' => $s->metapixel, 'auto' => (bool) $s->autopopup]);
        return '<div id="oriskill-cookies" role="dialog" aria-live="polite" aria-label="' . s($str('banner_title')) . '" hidden ' .
            'style="position: fixed; left: 1rem; right: 1rem; ' . ($s->position === 'top' ? 'top' : 'bottom') . ': 1rem; ' .
            'z-index: 9999; max-width: 46rem; margin: 0 auto; background: ' . $s->background . '; color: ' . $s->textcolor . '; ' .
            'border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.2); padding: 1.1rem 1.25rem; font-size: .95rem">' .
            '<p style="margin: 0 0 .8rem">' . s($s->bannertext) . $policy . '</p>' .
            '<div data-details hidden>' . $rows . '</div>' .
            '<div style="display: flex; flex-wrap: wrap; gap: .5rem; justify-content: flex-end">' .
            '<button type="button" data-act="custom" style="' . $btn . 'background: transparent; color: inherit; border-color: transparent; ' .
            'text-decoration: underline">' . s($str('customise')) . '</button>' .
            '<button type="button" data-act="save" hidden style="' . $btn . 'background: transparent; color: ' . $s->textcolor . '">' .
            s($str('save')) . '</button>' .
            // Refusing is as easy and as visible as accepting.
            '<button type="button" data-act="refuse" style="' . $primary . '">' . s($str('refuseall')) . '</button>' .
            '<button type="button" data-act="accept" style="' . $primary . '">' . s($str('acceptall')) . '</button>' .
            '</div></div>' .
            '<button type="button" id="oriskill-cookies-open" style="position: fixed; left: .75rem; bottom: .75rem; z-index: 9998; ' .
            'border: 0; border-radius: 99px; padding: .3rem .7rem; font-size: .8rem; background: rgba(0,0,0,.65); color: #fff; ' .
            'cursor: pointer">' . s($str('manage')) . '</button>' .
            '<script>(function() {
                var c = ' . $config . ', box = document.getElementById("oriskill-cookies"),
                    details = box.querySelector("[data-details]"), saveBtn = box.querySelector("[data-act=save]");
                function read() {
                    var m = document.cookie.match(new RegExp("(?:^|; )" + c.name + "=([^;]*)"));
                    try { return m ? JSON.parse(decodeURIComponent(m[1])) : null; } catch (e) { return null; }
                }
                function load(choice) {
                    if (choice.measure && c.ga && !window.oriskillGa) {
                        window.oriskillGa = true;
                        var s = document.createElement("script");
                        s.async = true;
                        s.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(c.ga);
                        document.head.appendChild(s);
                        window.dataLayer = window.dataLayer || [];
                        window.gtag = function() { window.dataLayer.push(arguments); };
                        window.gtag("js", new Date());
                        window.gtag("config", c.ga, {anonymize_ip: true});
                    }
                    if (choice.marketing && c.pixel && !window.fbq) {
                        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                        n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;
                        n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];
                        s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");
                        window.fbq("init", c.pixel);
                        window.fbq("track", "PageView");
                    }
                }
                function store(choice) {
                    choice.time = Date.now();
                    document.cookie = c.name + "=" + encodeURIComponent(JSON.stringify(choice)) + "; max-age=" + (c.days * 86400) +
                        "; path=/; SameSite=Lax" + (location.protocol === "https:" ? "; Secure" : "");
                    box.hidden = true;
                    load(choice);
                }
                function open(withDetails) {
                    var current = read() || {};
                    box.querySelectorAll("input[data-cat]").forEach(function(input) {
                        if (!input.disabled) { input.checked = !!current[input.dataset.cat]; }
                    });
                    details.hidden = !withDetails;
                    saveBtn.hidden = !withDetails;
                    box.hidden = false;
                }
                box.addEventListener("click", function(e) {
                    var act = e.target.dataset && e.target.dataset.act;
                    if (act === "accept") { store({measure: true, marketing: true}); }
                    if (act === "refuse") { store({measure: false, marketing: false}); }
                    if (act === "custom") { open(true); }
                    if (act === "save") {
                        store({measure: box.querySelector("[data-cat=measure]").checked,
                            marketing: box.querySelector("[data-cat=marketing]").checked});
                    }
                });
                document.getElementById("oriskill-cookies-open").addEventListener("click", function() { open(true); });
                var choice = read();
                if (choice) { load(choice); } else { open(c.auto); }
            })();</script>';
    }
}

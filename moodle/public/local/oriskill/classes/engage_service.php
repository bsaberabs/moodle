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

use moodle_url;

/**
 * What draws the visitors of a trainer's public pages in: pop-ups (after a delay, on scroll or when leaving the page) and
 * social proof (the latest real purchases, "Léa M. bought ... 2 hours ago").
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engage_service {

    /** Pop-ups. */
    const POPUPS = 'local_oriskill_popups';

    /** Public pages a pop-up or the social proof can show on. */
    const PAGES = ['site', 'coursepage', 'orderform', 'capture'];

    /** What opens a pop-up. */
    const TRIGGERS = ['delay', 'scroll', 'exit'];

    /** Purchases shown by the social proof: the latest, within these days. */
    const PROOF_COUNT = 10;
    const PROOF_DAYS = 30;

    /**
     * Pop-ups of a trainer.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function popups(\stdClass $creator): array {
        global $DB;
        return $DB->get_records(self::POPUPS, ['creatorid' => $creator->id], 'name');
    }

    /**
     * Create or change a pop-up.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $data id, name, enabled, title, body, buttonlabel, buttonurl, trigger, triggervalue, pages[], frequency
     * @return \stdClass
     */
    public static function save_popup(\stdClass $creator, \stdClass $data): \stdClass {
        global $DB;

        $name = \core_text::substr(trim(clean_param((string) $data->name, PARAM_TEXT)), 0, 100);
        $title = \core_text::substr(trim(clean_param((string) $data->title, PARAM_TEXT)), 0, 255);
        $pages = array_values(array_intersect(self::PAGES, (array) ($data->pages ?? [])));
        $url = trim((string) ($data->buttonurl ?? ''));
        if ($name === '' || $title === '' || !$pages || ($url !== '' && !preg_match('~^https?://\S+$~i', $url))) {
            throw new \moodle_exception('popup:incomplete', 'local_oriskill');
        }
        $record = (object) ['creatorid' => $creator->id, 'name' => $name, 'enabled' => empty($data->enabled) ? 0 : 1,
            'title' => $title, 'body' => \core_text::substr(trim(clean_param((string) ($data->body ?? ''), PARAM_TEXT)), 0, 2000),
            'buttonlabel' => \core_text::substr(trim(clean_param((string) ($data->buttonlabel ?? ''), PARAM_TEXT)), 0, 100),
            'buttonurl' => $url !== '' ? clean_param($url, PARAM_URL) : '',
            'opentrigger' => in_array($data->trigger ?? '', self::TRIGGERS, true) ? $data->trigger : 'delay',
            'triggervalue' => max(0, min(100, (int) ($data->triggervalue ?? 5))), 'pages' => implode(',', $pages),
            'frequency' => max(0, min(365, (int) ($data->frequency ?? 7))), 'timemodified' => time()];
        if (!empty($data->id)) {
            $record->id = $DB->get_field(self::POPUPS, 'id', ['id' => $data->id, 'creatorid' => $creator->id], MUST_EXIST);
            $DB->update_record(self::POPUPS, $record);
        } else {
            $record->timecreated = time();
            $record->views = 0;
            $record->clicks = 0;
            $record->id = $DB->insert_record(self::POPUPS, $record);
        }
        return $DB->get_record(self::POPUPS, ['id' => $record->id]);
    }

    /**
     * Count a pop-up shown, or its button clicked.
     *
     * @param int $id Pop-up.
     * @param string $kind view or click.
     */
    public static function count(int $id, string $kind): void {
        global $DB;

        if (in_array($kind, ['view', 'click'], true)) {
            $field = $kind === 'view' ? 'views' : 'clicks';
            $DB->execute("UPDATE {" . self::POPUPS . "} SET $field = $field + 1 WHERE id = ? AND enabled = 1", [$id]);
        }
    }

    /**
     * Social proof settings of a trainer.
     *
     * @param \stdClass $creator Trainer.
     * @return \stdClass {pages[], anonymous}
     */
    public static function proof_settings(\stdClass $creator): \stdClass {
        $saved = json_decode((string) get_config('local_oriskill', 'socialproof_' . $creator->id), true) ?: [];
        return (object) ['pages' => array_values(array_intersect(self::PAGES, (array) ($saved['pages'] ?? []))),
            'anonymous' => !empty($saved['anonymous'])];
    }

    /**
     * Save the social proof settings.
     *
     * @param \stdClass $creator Trainer.
     * @param array $pages Pages it shows on (none: off).
     * @param bool $anonymous Show "Someone" instead of the first name and initial.
     */
    public static function save_proof(\stdClass $creator, array $pages, bool $anonymous): void {
        set_config('socialproof_' . $creator->id, json_encode(['pages' => array_values(array_intersect(self::PAGES, $pages)),
            'anonymous' => $anonymous]), 'local_oriskill');
    }

    /**
     * Latest real purchases of the trainer (of one order form when given), for the social proof.
     *
     * @param \stdClass $creator Trainer.
     * @param int $formid Order form (0: all).
     * @return array of {who, product, ago}
     */
    public static function recent_purchases(\stdClass $creator, int $formid = 0): array {
        global $DB;

        $anonymous = self::proof_settings($creator)->anonymous;
        [$orders, $params] = transaction_service::orders_sql($creator);
        $where = "o.id IN ($orders) AND o.status IN ('paid', 'completed') AND o.timecreated >= :since";
        $params['since'] = time() - self::PROOF_DAYS * DAYSECS;
        if ($formid) {
            $where .= ' AND ' . $DB->sql_like('o.notes', ':form');
            $params['form'] = '%"orderform":' . $formid . ',%';
        }
        $records = $DB->get_records_sql("SELECT o.id, o.timepaid, o.timecreated, u.firstname, u.lastname,
                                                (SELECT MIN(i.itemname) FROM {local_moderncommerce_order_items} i
                                                  WHERE i.orderid = o.id) AS product
                                           FROM {local_moderncommerce_orders} o
                                           JOIN {user} u ON u.id = o.userid
                                          WHERE $where
                                       ORDER BY o.timecreated DESC", $params, 0, self::PROOF_COUNT);
        return array_values(array_map(fn($r) => (object) [
            'who' => $anonymous || trim($r->firstname) === '' ? get_string('proof:someone', 'local_oriskill')
                : trim($r->firstname) . ' ' . \core_text::strtoupper(\core_text::substr(trim($r->lastname), 0, 1)) . '.',
            'product' => get_string('proof:bought', 'local_oriskill', format_string((string) $r->product)),
            'ago' => get_string('proof:ago', 'local_oriskill', format_time(time() - (int) ($r->timepaid ?: $r->timecreated))),
        ], $records));
    }

    /**
     * Pop-ups and social proof of a public page of the trainer.
     *
     * @param \stdClass $creator Trainer.
     * @param string $page One of PAGES.
     * @param int $formid Order form shown (its purchases only).
     * @return string HTML
     */
    public static function html(\stdClass $creator, string $page, int $formid = 0): string {
        global $DB;

        $popups = array_values(array_filter($DB->get_records(self::POPUPS, ['creatorid' => $creator->id, 'enabled' => 1]),
            fn($p) => in_array($page, explode(',', $p->pages), true)));
        $proof = in_array($page, self::proof_settings($creator)->pages, true) ? self::recent_purchases($creator, $formid) : [];
        if (!$popups && !$proof) {
            return '';
        }
        $data = [
            'stat' => (new moodle_url('/local/oriskill/engagestat.php'))->out(false),
            'popups' => array_map(fn($p) => ['id' => (int) $p->id, 'title' => $p->title, 'body' => $p->body,
                'label' => $p->buttonlabel, 'url' => $p->buttonurl, 'trigger' => $p->opentrigger, 'value' => (int) $p->triggervalue,
                'frequency' => (int) $p->frequency], $popups),
            'proof' => $proof,
            'close' => get_string('closebuttontitle'),
        ];
        $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        return '<script>(function() {
    var d = ' . $json . ', shown = false;
    function el(tag, css, text) { var e = document.createElement(tag); e.style.cssText = css || ""; if (text) { e.textContent = text; } return e; }
    function beacon(id, kind) {
        try { navigator.sendBeacon(d.stat, new URLSearchParams({id: id, kind: kind})); } catch (e) {}
    }
    function seen(p) {
        try { var t = Number(localStorage.getItem("oriskill_popup_" + p.id)); return t && Date.now() - t < p.frequency * 86400000; }
        catch (e) { return false; }
    }
    function open(p) {
        if (shown || seen(p)) { return; }
        shown = true;
        try { localStorage.setItem("oriskill_popup_" + p.id, String(Date.now())); } catch (e) {}
        var back = el("div", "position:fixed;inset:0;background:rgba(17,24,39,.55);z-index:10000;display:flex;" +
            "align-items:center;justify-content:center;padding:1rem");
        var box = el("div", "background:#fff;color:#111827;border-radius:16px;max-width:28rem;width:100%;padding:1.75rem;" +
            "position:relative;box-shadow:0 20px 50px rgba(0,0,0,.25)");
        box.setAttribute("role", "dialog");
        box.setAttribute("aria-modal", "true");
        var close = el("button", "position:absolute;top:.5rem;right:.75rem;border:0;background:none;font-size:1.5rem;cursor:pointer", "×");
        close.setAttribute("aria-label", d.close);
        box.appendChild(close);
        box.appendChild(el("h2", "font-size:1.35rem;margin:0 0 .75rem", p.title));
        if (p.body) { box.appendChild(el("p", "white-space:pre-line;margin:0 0 1rem", p.body)); }
        if (p.label && p.url) {
            var a = el("a", "display:inline-block;padding:.7rem 1.4rem;border-radius:10px;text-decoration:none;font-weight:700;" +
                "background:var(--v-primary,#7c3aed);color:var(--v-on-primary,#fff)", p.label);
            a.href = p.url;
            a.addEventListener("click", function() { beacon(p.id, "click"); });
            box.appendChild(a);
        }
        back.appendChild(box);
        function hide() { back.remove(); }
        close.addEventListener("click", hide);
        back.addEventListener("click", function(e) { if (e.target === back) { hide(); } });
        document.addEventListener("keydown", function(e) { if (e.key === "Escape") { hide(); } });
        document.body.appendChild(back);
        close.focus();
        beacon(p.id, "view");
    }
    d.popups.forEach(function(p) {
        if (p.trigger === "delay") {
            setTimeout(function() { open(p); }, p.value * 1000);
        } else if (p.trigger === "scroll") {
            window.addEventListener("scroll", function onscroll() {
                var h = document.documentElement.scrollHeight - innerHeight;
                if (h <= 0 || scrollY / h * 100 >= p.value) { window.removeEventListener("scroll", onscroll); open(p); }
            }, {passive: true});
        } else {
            document.addEventListener("mouseout", function onexit(e) {
                if (!e.relatedTarget && e.clientY <= 0) { document.removeEventListener("mouseout", onexit); open(p); }
            });
        }
    });
    if (d.proof.length) {
        var i = 0, toast = el("div", "position:fixed;left:1rem;bottom:3.5rem;z-index:9997;background:#fff;color:#111827;" +
            "border-radius:12px;padding:.75rem 1rem;box-shadow:0 10px 30px rgba(0,0,0,.18);max-width:20rem;font-size:.9rem;" +
            "transition:opacity .4s;opacity:0");
        toast.setAttribute("role", "status");
        document.body.appendChild(toast);
        function next() {
            var p = d.proof[i++ % d.proof.length];
            toast.textContent = "";
            toast.appendChild(el("strong", "", p.who));
            toast.appendChild(document.createTextNode(" " + p.product));
            toast.appendChild(el("div", "font-size:.8rem;color:#6b7280", p.ago));
            toast.style.opacity = 1;
            setTimeout(function() { toast.style.opacity = 0; }, 6000);
        }
        setTimeout(function() { next(); setInterval(next, 18000); }, 8000);
    }
})();</script>';
    }
}

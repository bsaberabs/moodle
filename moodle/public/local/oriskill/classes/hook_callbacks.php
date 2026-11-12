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
 * Hook callbacks.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /** @var array|null Meta tags for the page being rendered, set by creator.php. */
    public static ?array $headmeta = null;

    /**
     * Remember the affiliate code of a visitor arriving with ?ref=code, on any page, and send visitors who are not
     * logged in from the site home page to the store (the home page itself requires login).
     *
     * @param \core\hook\after_config $hook Hook.
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $SCRIPT;

        if (CLI_SCRIPT || AJAX_SCRIPT || during_initial_install()) {
            return;
        }
        // A device kept logged in whose session has ended: the person is logged back in.
        if (!empty($_COOKIE[remember_service::COOKIE])) {
            try {
                remember_service::restore();
            } catch (\dml_exception $e) {
                debugging($e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        if (!empty($_GET['ref']) && is_string($_GET['ref'])) {
            try {
                affiliate_service::track_visit($_GET['ref'], (int) ($_GET['cmp'] ?? 0));
            } catch (\dml_exception $e) {
                // Plugin tables not installed yet (upgrade pending): the visit is simply not tracked.
                debugging($e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        if ($SCRIPT === '/index.php' && (!isloggedin() || isguestuser())) {
            redirect(new \moodle_url('/local/moderncommerce/index.php'));
        }
        // The dashboard (where users land after logging in) is otherwise sent to the learner space by Modern
        // Commerce: a trainer lands in their trainer space instead.
        if ($SCRIPT === '/my/index.php' && self::current_creator()) {
            redirect(new \moodle_url('/local/oriskill/dashboard.php'));
        }
    }

    /**
     * Add "My trainer space" to the top menu of approved trainers.
     *
     * @param \core\hook\navigation\primary_extend $hook Hook.
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        global $DB, $USER;

        if (self::current_creator()) {
            $hook->get_primaryview()->add(get_string('editshowcase', 'local_oriskill'),
                new \moodle_url('/local/oriskill/dashboard.php'), \navigation_node::TYPE_CUSTOM, null, 'oriskilltrainer');
        }
        // Header menu the trainer set up for their learners, in the courses of that trainer only.
        self::add_trainer_menu($hook);
        // Learners of a trainer write to the trainer's team.
        if (isloggedin() && !isguestuser() && !self::current_creator()
                && ($DB->record_exists('local_oriskill_tickets', ['userid' => $USER->id])
                    || ticket_service::trainers_of((int) $USER->id))) {
            $hook->get_primaryview()->add(get_string('support:title', 'local_oriskill'),
                new \moodle_url('/local/oriskill/support.php'), \navigation_node::TYPE_CUSTOM, null, 'oriskillsupport');
        }
        // Subscribers of a trainer's offer reach their subscriptions (to see or cancel them).
        if (isloggedin() && !isguestuser()
                && $DB->record_exists('local_oriskill_instalments', ['userid' => $USER->id, 'count' => 0])) {
            $hook->get_primaryview()->add(get_string('subscription:mine', 'local_oriskill'),
                new \moodle_url('/local/oriskill/mysubscriptions.php'), \navigation_node::TYPE_CUSTOM, null,
                'oriskillsubscriptions');
        }
    }

    /**
     * Entries of the menu of the training being viewed (with its modules as a sub-menu), for a learner of that trainer.
     * What the learner cannot open yet is shown greyed, without link.
     *
     * @param \core\hook\navigation\primary_extend $hook Hook.
     */
    private static function add_trainer_menu(\core\hook\navigation\primary_extend $hook): void {
        global $PAGE, $USER;

        if (!isloggedin() || isguestuser() || !$PAGE->has_set_url() || empty($PAGE->course->id)
                || $PAGE->course->id == SITEID) {
            return;
        }
        $creator = comment_service::creator_of($PAGE->course);
        if (!$creator) {
            return;
        }
        foreach (menu_service::for_user($creator, (int) $USER->id, (int) $PAGE->course->id) as $index => $entry) {
            $node = $hook->get_primaryview()->add(self::plain($entry['label']), null, \navigation_node::TYPE_CUSTOM, null,
                'oriskillmenu' . $index);
            foreach ($entry['children'] as $child) {
                // The main navigation does not export "classes": the lock is shown in the label, and the link of what
                // is locked goes nowhere (no redirect loop to an enrolment page).
                // Before the launch of the training, the opening date instead of "locked".
                $label = self::plain($child['label']) . (!empty($child['note']) ? ' ' . $child['note']
                    : ($child['locked'] ? ' ' . get_string('menus:locked', 'local_oriskill') : ''));
                $node->add($label, $child['url'] ?? new \moodle_url('#'), \navigation_node::TYPE_CUSTOM);
                // The main navigation dropdown has two levels: the lessons of a module are shown as a flyout (third
                // level) built by a small script from the data kept here.
                if (!empty($child['lessons']) && $child['url']) {
                    $items = [];
                    foreach ($child['lessons'] as $lesson) {
                        $items[] = ['t' => self::plain($lesson['label']), 'u' => $lesson['url']->out(false)];
                    }
                    self::$flyouts[$child['url']->out(false)] = $items;
                }
            }
        }
    }

    /**
     * Plain text of a label already made safe by format_string(): the navigation (mustache) and the script (textContent)
     * escape it again, so it must not stay escaped here ("Test & accents", not "Test &amp; accents").
     *
     * @param string $label Label.
     * @return string Plain text.
     */
    private static function plain(string $label): string {
        return html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @var array Lessons of each module of the trainer's menu, by module address. */
    private static $flyouts = [];

    /**
     * Script that adds the lessons flyout (hover with a mouse, accordion on touch) to the modules of the trainer's menu.
     *
     * @return string HTML (script tag), empty when there is nothing to add.
     */
    private static function flyout_html(): string {
        if (!self::$flyouts) {
            return '';
        }
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
        $data = json_encode(self::$flyouts, $flags);
        $toggle = json_encode(get_string('menus:showlessons', 'local_oriskill'), $flags);
        $css = <<<CSS
.oriskill-hasflyout { position: relative; display: flex; flex-wrap: wrap; align-items: center; }
.oriskill-hasflyout > a { flex: 1 1 0; min-width: 0; }
.oriskill-flyout-toggle { border: 0; background: transparent; color: inherit; min-width: 44px; min-height: 36px; cursor: pointer; }
.oriskill-open > .oriskill-flyout-toggle { transform: rotate(90deg); }
ul.oriskill-flyout { list-style: none; margin: 0; padding: .25rem 0 .25rem 1rem; display: none; flex: 0 0 100%; }
ul.oriskill-flyout li { list-style: none; margin: 0; padding: 0; }
ul.oriskill-flyout li::marker { content: ''; }
ul.oriskill-flyout .dropdown-item { white-space: normal; color: #1f2937 !important; }
.oriskill-open > ul.oriskill-flyout { display: block !important; }
@media (hover: hover) and (min-width: 768px) {
    .oriskill-flyout-toggle { display: none; }
    ul.oriskill-flyout.oriskill-fixed {
        position: fixed; display: block !important; flex: none; margin: 0; padding: .5rem 0; box-sizing: border-box;
        min-width: 14rem; max-width: 22rem; overflow-x: hidden; overflow-y: auto; overscroll-behavior: contain;
        background: #fff; color: #1f2937; border: 1px solid rgba(0, 0, 0, .15); border-radius: .5rem;
        box-shadow: 0 6px 20px rgba(0, 0, 0, .18); z-index: 2000;
    }
    ul.oriskill-flyout.oriskill-fixed .dropdown-item { white-space: normal; overflow-wrap: anywhere; }
}
.dropdown-menu.oriskill-menu {
    overflow-x: hidden; overflow-y: auto; min-width: 16rem; max-width: min(22rem, calc(100vw - 1rem)); max-height: calc(100vh - 6rem);
}
.dropdown-menu.oriskill-menu .dropdown-item { white-space: normal; overflow-wrap: anywhere; }
CSS;
        $js = <<<JS
(function() {
    var data = $data, label = $toggle;
    var timer = null, cur = null;
    function desktop() { return window.matchMedia('(hover: hover) and (min-width: 768px)').matches; }
    function closeFly() {
        clearTimeout(timer);
        if (!cur) { return; }
        var c = cur;
        cur = null;
        c.ul.classList.remove('oriskill-fixed');
        ['left', 'top', 'maxHeight'].forEach(function(k) { c.ul.style[k] = ''; });
        c.ul.style.display = 'none';
        c.li.appendChild(c.ul);
        c.a.setAttribute('aria-expanded', 'false');
    }
    function later() { clearTimeout(timer); timer = setTimeout(closeFly, 150); }
    function openFly(li, ul, a, focusFirst) {
        if (!desktop()) { return; }
        clearTimeout(timer);
        if (cur && cur.ul === ul) { return; }
        closeFly();
        cur = {li: li, ul: ul, a: a};
        document.body.appendChild(ul);
        ul.classList.add('oriskill-fixed');
        ul.style.display = 'block';
        ul.style.maxHeight = (window.innerHeight - 16) + 'px';
        var r = li.getBoundingClientRect(), w = ul.offsetWidth, h = ul.offsetHeight;
        var left = r.right;
        if (left + w > window.innerWidth - 4 && r.left - w >= 4) { left = r.left - w; }
        left = Math.max(4, Math.min(left, window.innerWidth - w - 4));
        var top = Math.max(8, Math.min(r.top, window.innerHeight - 8 - h));
        ul.style.left = left + 'px';
        ul.style.top = top + 'px';
        a.setAttribute('aria-expanded', 'true');
        if (focusFirst) { var f = ul.querySelector('a'); if (f) { f.focus(); } }
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && cur) { var a = cur.a; closeFly(); a.focus(); }
    });
    window.addEventListener('resize', closeFly);
    window.addEventListener('scroll', function(e) {
        if (cur && !(e.target && e.target.nodeType === 1 && cur.ul.contains(e.target))) { closeFly(); }
    }, true);
    document.addEventListener('hide.bs.dropdown', closeFly);
    function build() {
        document.querySelectorAll('.primary-navigation .dropdown-menu a[href]').forEach(function(a) {
            var items = data[a.href];
            var menu = a.closest('.dropdown-menu');
            if (!items || !menu || a.hasAttribute('data-oriskill-flyout')) { return; }
            a.setAttribute('data-oriskill-flyout', '1');
            menu.classList.add('oriskill-menu');
            // Moodle's dropdown items are links straight in the menu (no li): wrap the link, never use the
            // first level li of the navigation bar.
            var li = a.parentElement;
            if (li === menu || !menu.contains(li)) {
                li = document.createElement('div');
                a.parentNode.insertBefore(li, a);
                li.appendChild(a);
            }
            li.classList.add('oriskill-hasflyout');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'oriskill-flyout-toggle';
            btn.setAttribute('aria-label', label);
            btn.setAttribute('aria-expanded', 'false');
            btn.textContent = '\\u25B8';
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var open = li.classList.toggle('oriskill-open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            var ul = document.createElement('ul');
            ul.className = 'oriskill-flyout';
            ul.style.display = 'none';
            ul.style.listStyle = 'none';
            items.forEach(function(it) {
                var l = document.createElement('li');
                var x = document.createElement('a');
                x.className = 'dropdown-item';
                x.href = it.u;
                x.textContent = it.t;
                l.appendChild(x);
                ul.appendChild(l);
            });
            a.insertAdjacentElement('afterend', btn);
            li.appendChild(ul);
            a.setAttribute('aria-haspopup', 'true');
            a.setAttribute('aria-expanded', 'false');
            li.addEventListener('mouseenter', function() { openFly(li, ul, a, false); });
            li.addEventListener('mouseleave', later);
            ul.addEventListener('mouseenter', function() { clearTimeout(timer); });
            ul.addEventListener('mouseleave', later);
            a.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowRight' && desktop()) { e.preventDefault(); openFly(li, ul, a, true); }
            });
            ul.addEventListener('keydown', function(e) {
                var links = Array.prototype.slice.call(ul.querySelectorAll('a')), i = links.indexOf(document.activeElement);
                if (e.key === 'ArrowLeft') { e.preventDefault(); closeFly(); a.focus(); }
                else if (e.key === 'ArrowDown' && i < links.length - 1) { e.preventDefault(); links[i + 1].focus(); }
                else if (e.key === 'ArrowUp' && i > 0) { e.preventDefault(); links[i - 1].focus(); }
            });
        });
    }
    document.addEventListener('DOMContentLoaded', build);
    window.addEventListener('load', build);
    document.addEventListener('shown.bs.dropdown', build);
})();
JS;
        return \html_writer::tag('style', $css) . \html_writer::tag('script', $js);
    }

    /**
     * Approved trainer space of the logged-in user, looked up once per request.
     *
     * @return \stdClass|null
     */
    private static function current_creator(): ?\stdClass {
        // The trainer, or a collaborator working in the trainer's space.
        return team_service::current_space()['creator'] ?? null;
    }

    /**
     * For a trainer's courses, offer only the methods that pay into the trainer's own accounts: SecureAndPay when
     * they entered it, Stripe and PayPal when they turned them on (resolve_gateway_config already leaves them out
     * otherwise). Any other method (manual, Paystack...) would collect the money on Oriskill's account.
     *
     * @param \local_moderncommerce\hook\filter_payment_methods $hook Hook.
     */
    public static function filter_payment_methods(\local_moderncommerce\hook\filter_payment_methods $hook): void {
        $sellers = array_filter(seller_service::purchase_sellers($hook->order, $hook->userid));
        if (!$sellers) {
            return;
        }
        $creator = reset($sellers);
        $keep = seller_service::GATEWAYS;
        // SecureAndPay stays when the trainer entered it, and also when the purchase cannot be paid at all (several
        // sellers, trainer without any payment method): its refusal then tells the buyer why.
        if (count($sellers) > 1 || seller_service::merchant($creator) || !seller_service::usable_gateways($creator)) {
            $keep[] = \local_secureandpay\gateway::GATEWAY;
        }
        foreach (array_keys($hook->methods) as $gatewayid) {
            if (!in_array($gatewayid, $keep)) {
                $hook->remove($gatewayid);
            }
        }
    }

    /**
     * Collect a purchase of a trainer's courses with Stripe or PayPal on the trainer's own account.
     *
     * The account is "creator-<id>" (also in the address of the trainer's webhooks). A purchase mixing several
     * sellers gets no account it could be paid on.
     *
     * @param \local_moderncommerce\hook\resolve_gateway_config $hook Hook.
     */
    public static function resolve_gateway_config(\local_moderncommerce\hook\resolve_gateway_config $hook): void {
        global $DB;

        // SecureAndPay has its own routing (resolve_merchant); other gateways are withdrawn by filter_payment_methods.
        if (!in_array($hook->gatewayid, seller_service::GATEWAYS)) {
            return;
        }
        if ($hook->webhookaccount !== null) {
            if ($hook->webhookaccount === '') {
                return;
            }
            $creator = preg_match('/^creator-(\d+)$/', $hook->webhookaccount, $m)
                ? $DB->get_record('local_oriskill_creators', ['id' => (int) $m[1]]) : null;
            $account = $hook->webhookaccount;
        } else {
            $sellers = seller_service::purchase_sellers($hook->order, $hook->userid);
            if (!array_filter($sellers)) {
                return;
            }
            $creator = count($sellers) === 1 ? reset($sellers) : null;
            $account = $creator ? 'creator-' . $creator->id : 'mixed';
        }
        // Unknown account or several sellers: the gateway is off and none of the store's keys may be used.
        $hook->use_account($account, $creator
            ? seller_service::gateway_config($creator, $hook->gatewayid, $hook->config)
            : array_merge($hook->config, ['enabled' => false, 'secret_key' => '', 'public_key' => '', 'client_id' => '',
                'merchantid' => '', 'webhook_secret' => '', 'webhook_id' => '', 'secret_hash' => '']));
    }

    /**
     * Route a trainer's order to the trainer's own SecureAndPay account.
     *
     * An order can only be paid into one account, so mixing courses of several sellers (trainers, or a
     * trainer and Oriskill) is refused at payment. Platform orders (no trainer course) are left alone.
     *
     * @param \local_secureandpay\hook\resolve_merchant $hook Hook.
     */
    public static function resolve_merchant(\local_secureandpay\hook\resolve_merchant $hook): void {
        global $DB;

        $owners = [];
        foreach ($DB->get_records('local_moderncommerce_order_items', ['orderid' => $hook->order->id]) as $item) {
            $owner = $item->courseid ? seller_service::owner_of_course((int) $item->courseid) : null;
            $owners[$owner->id ?? 0] = $owner ? format_string($owner->displayname) : get_string('platformseller', 'local_oriskill');
        }
        if (count($owners) > 1) {
            $hook->refuse(get_string('error:mixedsellers', 'local_oriskill', implode(', ', $owners)));
            return;
        }
        $creatorid = (int) array_key_first($owners);
        if (!$creatorid) {
            return;
        }

        $creator = $DB->get_record('local_oriskill_creators', ['id' => $creatorid]);
        if ($hook->forpayment && ($blocker = seller_service::selling_blocker($creator))) {
            $hook->refuse(get_string('error:sellercannotsell', 'local_oriskill', format_string($creator->displayname)));
            return;
        }
        if ($merchant = seller_service::merchant($creator)) {
            $hook->set_merchant($merchant);
        } else if ($hook->forpayment) {
            $hook->refuse(get_string('error:sellercannotsell', 'local_oriskill', format_string($creator->displayname)));
        }
    }

    /**
     * Add the showcase description and canonical address to the page head, for search engines.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook Hook.
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        global $PAGE;

        // Learner space of a trainer who turned their brand on: styles for their courses only.
        if (!during_initial_install() && $PAGE->has_set_url() && !empty($PAGE->course->id) && $PAGE->course->id != SITEID) {
            $creator = comment_service::creator_of($PAGE->course);
            if ($creator && menu_service::skin_enabled($creator)) {
                $hook->add_html(\html_writer::tag('style', menu_service::skin_css($creator, $PAGE->course)));
            }
        }
        if (self::$headmeta === null) {
            return;
        }
        $hook->add_html(\html_writer::empty_tag('meta', ['name' => 'description', 'content' => self::$headmeta['description']]));
        $hook->add_html(\html_writer::empty_tag('link', ['rel' => 'canonical', 'href' => self::$headmeta['canonical']]));
        // The trainer's logo replaces the Oriskill icon in the browser tab (declared after it, so browsers use it).
        if (!empty(self::$headmeta['icon'])) {
            $hook->add_html(\html_writer::empty_tag('link', ['rel' => 'icon', 'href' => self::$headmeta['icon']]));
        }
    }

    /**
     * On the store page of a trainer course sold in instalments, offer to pay that way; on one locked until its launch,
     * say when it opens.
     *
     * The page itself is rendered by Modern Commerce's React application, so the offer comes as a bar above it.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook Hook.
     */
    public static function before_standard_top_of_body_html_generation(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE;

        if (during_initial_install() || !$PAGE->has_set_url()
                || $PAGE->url->get_path() !== (new \moodle_url('/local/moderncommerce/course_details.php'))->get_path()) {
            return;
        }
        // A training on sale before its launch: when it opens.
        if ($opens = launch_service::opens_label((int) $PAGE->url->get_param('id'))) {
            $hook->add_html(\html_writer::div(\html_writer::span(s($opens)), 'oriskill-instalment-bar oriskill-launch-bar'));
        }
        $offer = instalment_service::buyable_offer((int) $PAGE->url->get_param('id'));
        if ($offer) {
            $hook->add_html(\html_writer::div(
                \html_writer::span(get_string('instalments:available', 'local_oriskill', $offer)) . ' ' .
                \html_writer::link($offer->url, get_string('instalments:choose', 'local_oriskill'),
                    ['class' => 'btn btn-sm btn-light ms-2']),
                'oriskill-instalment-bar'));
        }
    }

    /**
     * On the pages of a trainer's course, count the time the learner spends (when the course tracks it).
     *
     * @param \core\hook\output\before_footer_html_generation $hook Hook.
     */
    public static function before_footer_html_generation(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE;

        $hook->add_html(self::flyout_html());
        if (!during_initial_install() && $PAGE->pagetype === 'login-index' && !isloggedin()) {
            $PAGE->requires->js_init_code(remember_service::login_script());
        }
        // In the store, a visitor's "Add to cart" goes through the login (or sign-up), then into their cart.
        if (!during_initial_install() && (!isloggedin() || isguestuser())
                && str_starts_with((string) $PAGE->pagetype, 'local-moderncommerce')) {
            $PAGE->requires->js_init_code(self::guest_cart_script());
        }
        // Store home page and login page: download links of the mobile app.
        if (!during_initial_install() && mobile_app::shown_on((string) $PAGE->pagetype)) {
            $hook->add_html(mobile_app::html());
        }
        if (during_initial_install() ||!in_array($PAGE->context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE])) {
            return;
        }
        $mode = studytime_service::page_mode($PAGE->course);
        if ($mode !== null) {
            $PAGE->requires->js_init_code(studytime_service::page_script((int) $PAGE->course->id, $mode));
        }
        // At the bottom of a course of a trainer: its frequently asked questions.
        if ($PAGE->context->contextlevel == CONTEXT_COURSE && str_starts_with($PAGE->pagetype, 'course-view')
                && comment_service::creator_of($PAGE->course)) {
            $hook->add_html(faq_service::html((int) $PAGE->course->id, get_string('faq:title', 'local_oriskill')));
        }
        // Under a lesson: the comments of the learners and the answers of the team.
        if ($PAGE->context->contextlevel == CONTEXT_MODULE && $PAGE->cm && str_ends_with($PAGE->pagetype, '-view')) {
            $hook->add_html(comment_service::lesson_html($PAGE->cm));
        }
    }

    /**
     * Script of the store for visitors: "Add to cart" leads to tocart.php with the course or bundle of the card (or of the
     * details page), where the login comes first.
     *
     * @return string JavaScript
     */
    public static function guest_cart_script(): string {
        $script = <<<'JS'
document.addEventListener('click', function(e) {
    var button = e.target.closest('button, a');
    if (!button || /cart\.php/.test(button.getAttribute('href') || '')
            || !(button.querySelector('.bi-cart-plus, .fa-cart-plus') || /panier|cart/i.test(button.textContent))) {
        return;
    }
    var scope = button.closest('article, .mc-course-card, .card') || document;
    var link = scope.querySelector('a[href*="course_details.php?id="], a[href*="bundle_details.php?id="]');
    var match = (link ? link.getAttribute('href') : location.href).match(/(course|bundle)_details\.php\?id=(\d+)/);
    if (!match) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    location.href = TARGET + '?' + match[1] + '=' + match[2];
}, true);
JS;
        return str_replace('TARGET', json_encode((new \moodle_url('/local/oriskill/tocart.php'))->out(false)), $script);
    }

    /**
     * The invoice or receipt of an order of a trainer's courses is the invoice in the trainer's name.
     *
     * @param \local_moderncommerce\hook\order_document $hook Hook.
     */
    public static function order_document(\local_moderncommerce\hook\order_document $hook): void {
        if ($invoice = billing_service::issue_invoice((int) $hook->order->id)) {
            [$filename, $content] = billing_service::pdf($invoice);
            $hook->provide($filename, $content);
        } else if (\local_secureandpay\gateway::is_resumed($hook->order)) {
            // A schedule taken over from another platform: invoiced here one instalment at a time, never in full (what
            // was paid before was invoiced there). No instalment invoiced here yet: no document rather than the store's.
            throw new \moodle_exception('resume:noinvoice', 'local_oriskill');
        }
    }
}

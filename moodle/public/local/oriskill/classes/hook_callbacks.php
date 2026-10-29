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
        if (!empty($_GET['ref']) && is_string($_GET['ref'])) {
            try {
                affiliate_service::track_visit($_GET['ref']);
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
        if (self::current_creator()) {
            $hook->get_primaryview()->add(get_string('editshowcase', 'local_oriskill'),
                new \moodle_url('/local/oriskill/dashboard.php'), \navigation_node::TYPE_CUSTOM, null, 'oriskilltrainer');
        }
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
     * On the store page of a trainer course sold in instalments, offer to pay that way.
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
     * The invoice or receipt of an order of a trainer's courses is the invoice in the trainer's name.
     *
     * @param \local_moderncommerce\hook\order_document $hook Hook.
     */
    public static function order_document(\local_moderncommerce\hook\order_document $hook): void {
        if ($invoice = billing_service::issue_invoice((int) $hook->order->id)) {
            [$filename, $content] = billing_service::pdf($invoice);
            $hook->provide($filename, $content);
        }
    }
}

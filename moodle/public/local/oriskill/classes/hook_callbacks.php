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
    }

    /**
     * Offer only SecureAndPay for a trainer's courses: it is the only method that pays into the trainer's own
     * account. Any other method (Stripe, PayPal, manual...) would collect the money on Oriskill's account.
     *
     * @param \local_moderncommerce\hook\filter_payment_methods $hook Hook.
     */
    public static function filter_payment_methods(\local_moderncommerce\hook\filter_payment_methods $hook): void {
        global $DB;

        if ($hook->order) {
            $courseids = $DB->get_fieldset_select('local_moderncommerce_order_items', 'courseid',
                'orderid = ? AND courseid IS NOT NULL', [$hook->order->id]);
        } else if ($hook->userid && !isguestuser($hook->userid)) {
            $courseids = array_column(\local_moderncommerce\api\cart_api::get_cart_items($hook->userid), 'courseid');
        } else {
            return;
        }
        foreach (array_filter(array_map('intval', $courseids)) as $courseid) {
            if (seller_service::owner_of_course($courseid)) {
                foreach (array_keys($hook->methods) as $gatewayid) {
                    if ($gatewayid !== \local_secureandpay\gateway::GATEWAY) {
                        $hook->remove($gatewayid);
                    }
                }
                return;
            }
        }
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
    }
}

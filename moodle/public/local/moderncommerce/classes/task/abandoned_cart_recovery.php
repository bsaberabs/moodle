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

namespace local_moderncommerce\task;


/**
 * Abandoned-cart recovery: a 1h / 24h / 72h marketing nudge sequence.
 *
 * Each stage sends at most once per cart (dedupe tag). Marketing category, so the
 * one-click unsubscribe + suppression list apply. Guest carts (no user) are skipped.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class abandoned_cart_recovery extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return 'Modern Commerce abandoned-cart recovery';
    }

    /**
     * Run the recovery sweep.
     */
    public function execute() {
        global $DB;

        $now = time();
        // Ordered longest-first so each cart matches a single stage.
        $stages = [
            ['tag' => 'cart72h', 'tpl' => 'moderncommerce_cart_abandoned_72h', 'min' => 72 * HOURSECS, 'max' => PHP_INT_MAX],
            ['tag' => 'cart24h', 'tpl' => 'moderncommerce_cart_abandoned_24h', 'min' => 24 * HOURSECS, 'max' => 72 * HOURSECS],
            ['tag' => 'cart1h', 'tpl' => 'moderncommerce_cart_abandoned_1h', 'min' => HOURSECS, 'max' => 24 * HOURSECS],
        ];

        $carts = $DB->get_records_select(
            'local_moderncommerce_carts',
            "status = 'active' AND userid > 0 AND total > 0"
        );
        $sent = 0;
        foreach ($carts as $cart) {
            $names = $this->cart_products((int) $cart->id);
            if (empty($names)) {
                continue;
            }
            $age = $now - (int) $cart->timemodified;
            foreach ($stages as $stage) {
                if ($age >= $stage['min'] && $age < $stage['max']) {
                    $this->notify_cart($cart, $names, $stage['tpl'], $stage['tag']);
                    $sent++;
                    break;
                }
            }
        }

        if ($sent > 0) {
            mtrace("abandoned_cart_recovery: queued {$sent} recovery emails.");
        }
    }

    /**
     * Product display names in a cart.
     *
     * @param int $cartid Cart id.
     * @return string[]
     */
    private function cart_products(int $cartid): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT ci.id, p.name
               FROM {local_moderncommerce_cart_items} ci
               JOIN {local_moderncommerce_products} p ON p.id = ci.productid
              WHERE ci.cartid = :cartid",
            ['cartid' => $cartid]
        );
        $names = [];
        foreach ($rows as $r) {
            $names[] = $r->name;
        }
        return $names;
    }

    /**
     * Dispatch one recovery email for a cart stage.
     *
     * @param \stdClass $cart Cart record.
     * @param string[] $names Product names.
     * @param string $templatekey Template key for the stage.
     * @param string $tag Dedupe tag (one send per cart per stage).
     * @return void
     */
    private function notify_cart(\stdClass $cart, array $names, string $templatekey, string $tag): void {
        global $CFG;

        $items = '';
        foreach ($names as $name) {
            $items .= '<li>' . s($name) . '</li>';
        }
        if (class_exists('\local_moderncommerce\services\pricing_service')) {
            $total = \local_moderncommerce\services\pricing_service::format_price((float) $cart->total);
        } else {
            $total = number_format((float) $cart->total, 2);
        }
        $carturl = $CFG->wwwroot . '/local/moderncommerce/cart.php';
        $coupon = (string) get_config('local_moderncommerce', 'cart_recovery_coupon');

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'cart_abandoned'))
            ->category('marketing')
            ->template($templatekey)
            ->to_user((int) $cart->userid)
            ->placeholders([
                'cart_items' => $items,
                'cart_items_count' => count($names),
                'cart_total' => $total,
                'cart_url' => $carturl,
                'course_name' => $names[0],
                'coupon_code' => $coupon,
            ])
            ->context_url($carturl)
            ->related((int) $cart->id)
            ->dedup_tag($tag);

        \local_moderncommerce\notifications\api::notify($notification);
    }
}

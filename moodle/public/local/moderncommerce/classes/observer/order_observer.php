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

namespace local_moderncommerce\observer;

use local_moderncommerce\api\order_api;

/**
 * Order lifecycle observer.
 *
 * Notifies the buyer when their order is cancelled (e.g. a self-service cancellation),
 * which would otherwise be silent.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class order_observer {
    /**
     * Handle an order status change.
     *
     * @param \local_moderncommerce\event\order_status_changed $event
     */
    public static function status_changed(\local_moderncommerce\event\order_status_changed $event) {
        $newstatus = (string) ($event->other['newstatus'] ?? '');
        if ($newstatus !== 'cancelled') {
            return;
        }

        try {
            $order = order_api::get_order((int) $event->objectid);
            if ($order) {
                \local_moderncommerce\email_notifications::send_order_cancelled($order);
            }
        } catch (\Throwable $e) {
            debugging('Order-cancelled notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

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

namespace local_moderncommerce\notifications;

/**
 * Public facade. Producers call api::notify() / api::notify_admins(); nothing else.
 *
 * Example:
 *   \local_moderncommerce\notifications\api::notify(
 *       (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'order_paid'))
 *           ->category(\local_moderncommerce\notifications\notification::CAT_TRANSACTIONAL)
 *           ->template('moderncommerce_payment_receipt')
 *           ->to_user($order->userid)
 *           ->placeholders($placeholders)
 *           ->context_url($orderurl)
 *           ->related($order->id)
 *   );
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /**
     * Queue a notification for delivery.
     *
     * @param notification $n The notification.
     * @return array{queued:int, skipped:int}
     */
    public static function notify(notification $n): array {
        return dispatcher::dispatch($n);
    }

    /**
     * Queue a notification addressed to store-operations admins.
     *
     * @param notification $n The notification.
     * @return array{queued:int, skipped:int}
     */
    public static function notify_admins(notification $n): array {
        $n->to_admins(true);
        return dispatcher::dispatch($n);
    }
}

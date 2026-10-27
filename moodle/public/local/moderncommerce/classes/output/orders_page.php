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

/**
 * Orders list page renderable.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;


use renderable;
use templatable;
use renderer_base;
use moodle_url;
use local_moderncommerce\services\pricing_service;

/**
 * Orders list page renderable class.
 */
class orders_page implements renderable, templatable {
    /** @var int User ID */
    protected $userid;

    /** @var array Orders */
    protected $orders;

    /**
     * Constructor.
     *
     * @param int $userid User ID
     */
    public function __construct(int $userid) {
        global $DB;

        $this->userid = $userid;
        $this->orders = $DB->get_records(
            'local_moderncommerce_orders',
            ['userid' => $userid],
            'timecreated DESC'
        );
    }

    /**
     * Check if there are orders.
     *
     * @return bool
     */
    public function has_orders(): bool {
        return !empty($this->orders);
    }

    /**
     * Get status badge class.
     *
     * @param string $status Order status
     * @return string CSS class
     */
    protected function get_status_class(string $status): string {
        switch ($status) {
            case 'paid':
            case 'completed':
                return 'bg-success';
            case 'pending':
            case 'processing':
                return 'bg-warning text-dark';
            case 'failed':
            case 'cancelled':
                return 'bg-danger';
            case 'refunded':
                return 'bg-info';
            default:
                return 'bg-secondary';
        }
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $data = [
            'hasorders' => $this->has_orders(),
            'catalogurl' => (new moodle_url('/local/moderncommerce/index.php'))->out(false),
        ];

        if (!$this->has_orders()) {
            return $data;
        }

        $orders = [];
        foreach ($this->orders as $order) {
            // Get item count.
            $itemcount = $DB->count_records('local_moderncommerce_order_items', ['orderid' => $order->id]);
            $orders[] = [
                'id' => $order->id,
                'ordernumber' => $order->ordernumber,
                'date' => userdate($order->timecreated, get_string('strftimedatetime', 'langconfig')),
                'daterelative' => format_time(time() - $order->timecreated) . ' ago',
                'itemcount' => $itemcount,
                'itemtext' => $itemcount . ' ' . ($itemcount == 1
                    ? get_string('item', 'local_moderncommerce')
                    : get_string('items', 'local_moderncommerce')),
                'total' => pricing_service::format_order_price($order->total, $order), 'status' => $order->status,
                'statustext' => get_string('status_' . $order->status, 'local_moderncommerce'),
                'statusclass' => $this->get_status_class($order->status),
                'ispaid' => in_array($order->status, ['paid', 'completed'], true), 'ispending' => $order->status === 'pending',
                'viewurl' => (new moodle_url('/local/moderncommerce/learner/order.php', ['id' => $order->id]))->out(false),
            ];
        }

        $data['orders'] = $orders;
        $data['ordercount'] = count($orders);

        return $data;
    }
}

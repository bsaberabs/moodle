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

namespace local_secureandpay;

use moodle_url;

/**
 * Hook callbacks.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Send Modern Commerce's "Start free trial" requests to trial.php, so the trial starts
     * with a card registered at SecureAndPay instead of without any payment method.
     *
     * @param \core\hook\after_config $hook Hook.
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $SCRIPT;

        if ($SCRIPT !== '/local/moderncommerce/subscribe_plan.php' || during_initial_install()
                || !optional_param('trial', 0, PARAM_BOOL) || !get_config('local_secureandpay', 'trialwithcard')
                || !(new gateway())->is_enabled()) {
            return;
        }
        redirect(new moodle_url('/local/secureandpay/trial.php', array_filter([
            'plan' => optional_param('plan', '', PARAM_ALPHANUMEXT),
            'planid' => optional_param('planid', 0, PARAM_INT),
        ])));
    }
}

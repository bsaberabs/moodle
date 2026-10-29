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

namespace local_moderncommerce\hook;

/**
 * Lets the plugin that bills a subscription plan state its terms, shown to buyers on the plans page.
 *
 * Oriskill addition (not in upstream Modern Commerce): dispatched for each plan the public subscribe page lists.
 * A billing plugin may say that the trial is not free (a token amount) and that the plan is billed for a fixed
 * number of cycles, after which access stops.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to state the trial amount and number of payments of a subscription plan.')]
#[\core\attribute\tags('local_moderncommerce', 'subscription')]
final class plan_terms {

    /** @var float Amount paid when the trial starts (0: free trial). */
    public float $trialprice = 0.0;

    /** @var int Number of payments the plan is billed for (0: until cancelled). */
    public int $payments = 0;

    /**
     * Constructor.
     *
     * @param \stdClass $plan Subscription plan record.
     */
    public function __construct(
        /** @var \stdClass Subscription plan record. */
        public readonly \stdClass $plan,
    ) {
    }
}

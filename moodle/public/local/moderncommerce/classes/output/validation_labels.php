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
 * Shared client-side validation messages for Modern Commerce React forms.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;

/**
 * Supplies the generic validation strings every React form needs.
 *
 * The React validation rules take their messages as arguments so they stay
 * translatable. Rather than repeat the same dozen get_string() calls on every
 * admin page, spread this into the page's labels array:
 *
 *     'labels' => validation_labels::all() + [ ... page specific ... ]
 *
 * Messages containing {$a} are substituted client side by the fmt() helper in
 * the validation module, using the same placeholder convention as get_string().
 */
class validation_labels {
    /** @var string Component the strings belong to. */
    private const COMPONENT = 'local_moderncommerce';

    /**
     * Generic per-field validation messages.
     *
     * @return array Label key to translated message.
     */
    public static function all(): array {
        return [
            'error:required' => get_string('error:required', self::COMPONENT),
            'error:selectoption' => get_string('error:selectoption', self::COMPONENT),
            'error:invalidemail' => get_string('error:invalidemail', self::COMPONENT),
            'error:invalidemaillist' => get_string('error:invalidemaillist', self::COMPONENT),
            'error:invalidurl' => get_string('error:invalidurl', self::COMPONENT),
            'error:invalidcolour' => get_string('error:invalidcolour', self::COMPONENT),
            'error:invalidnumber' => get_string('error:invalidnumber', self::COMPONENT),
            'error:invalidinteger' => get_string('error:invalidinteger', self::COMPONENT),
            'error:invalidpercent' => get_string('error:invalidpercent', self::COMPONENT),
            'error:invaliddate' => get_string('error:invaliddate', self::COMPONENT),
            'error:invalidslug' => get_string('error:invalidslug', self::COMPONENT),
            'error:minvalue' => get_string('error:minvalue', self::COMPONENT),
            'error:maxvalue' => get_string('error:maxvalue', self::COMPONENT),
            'error:minlength' => get_string('error:minlength', self::COMPONENT),
            'error:maxlength' => get_string('error:maxlength', self::COMPONENT),
            'error:formhaserrors' => get_string('error:formhaserrors', self::COMPONENT),
        ];
    }

    /**
     * Cross-field messages, added only by the forms that compare two fields.
     *
     * @return array Label key to translated message.
     */
    public static function crossfield(): array {
        return [
            'error:enddatebeforestart' => get_string('error:enddatebeforestart', self::COMPONENT),
            'error:expirydateinpast' => get_string('error:expirydateinpast', self::COMPONENT),
            'error:maxqtybelowmin' => get_string('error:maxqtybelowmin', self::COMPONENT),
            'error:salepriceabovebase' => get_string('error:salepriceabovebase', self::COMPONENT),
            'error:refundabovetotal' => get_string('error:refundabovetotal', self::COMPONENT),
            'error:peruserlimitaboveusagelimit' =>
                get_string('error:peruserlimitaboveusagelimit', self::COMPONENT),
        ];
    }
}

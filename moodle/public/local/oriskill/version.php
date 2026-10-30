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

/**
 * Oriskill: trainer spaces (application, own course category, public showcase).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_oriskill';
$plugin->version = 2026103033;
$plugin->requires = 2026100500;
$plugin->supported = [503, 503];
$plugin->maturity = MATURITY_BETA;
$plugin->release = '0.28.0';
$plugin->dependencies = [
    'local_moderncommerce' => 2026081811,
    'local_secureandpay' => 2026102909,
    'availability_enroldays' => 2026103000,
    'availability_forcedaccess' => 2026103000,
];

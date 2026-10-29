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
 * Language strings.
 *
 * @package    availability_enroldays
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['days_after'] = 'day(s) after enrolment';
$string['description'] = 'Release the content a number of days after each user enrolled in the course.';
$string['error_days'] = 'Enter a whole number of days (0 or more).';
$string['full_after'] = 'You have been enrolled for at least <strong>{$a}</strong> day(s)';
$string['full_after_date'] = 'You have been enrolled for at least {$a->days} day(s) (from <strong>{$a->date}</strong>)';
$string['full_before'] = 'You have been enrolled for less than <strong>{$a}</strong> day(s)';
$string['label_days'] = 'Available';
$string['pluginname'] = 'Restriction by days since enrolment';
$string['privacy:metadata'] = 'The Restriction by days since enrolment plugin does not store any personal data.';
$string['title'] = 'Days after enrolment';

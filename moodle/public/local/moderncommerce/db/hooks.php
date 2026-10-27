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
 * Hook callback definitions for local_moderncommerce.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \local_moderncommerce\hook\callbacks::class . '::redirect_signup_to_ccp',
        'priority' => 500,
    ],
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \local_moderncommerce\hook\callbacks::class . '::redirect_frontpage_to_catalog',
        'priority' => 400,
    ],
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \local_moderncommerce\hook\callbacks::class . '::redirect_my_to_learner_dashboard',
        'priority' => 300,
    ],
    [
        'hook' => \core_user\hook\extend_user_menu::class,
        'callback' => \local_moderncommerce\hook\callbacks::class . '::add_learner_dashboard_link',
        'priority' => 100,
    ],
    [
        'hook' => \core_user\hook\extend_default_homepage::class,
        'callback' => \local_moderncommerce\hook\callbacks::class . '::extend_default_homepage',
        'priority' => 100,
    ],
    [
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => \local_moderncommerce\hook\callbacks::class . '::extend_primary_navigation',
        'priority' => 100,
    ],
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => \local_moderncommerce\hook\callbacks::class . '::inject_custom_css',
        'priority' => 100,
    ],
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => \local_moderncommerce\hook\callbacks::class . '::initialise_floating_notifications',
        'priority' => 100,
    ],
];

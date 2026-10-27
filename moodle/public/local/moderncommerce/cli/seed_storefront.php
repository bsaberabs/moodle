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
 * Seeds the default Modern Commerce storefront widgets.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'reset' => false,
    ],
    [
        'h' => 'help',
    ]
);

if (!empty($unrecognized)) {
    $unrecognized = implode(PHP_EOL . '  ', $unrecognized);
    cli_error("Unknown options:\n  {$unrecognized}");
}

if (!empty($options['help'])) {
    echo "Seed the Modern Commerce storefront widgets.\n\n";
    echo "By default this command is idempotent and only creates missing default widgets.\n";
    echo "Use --reset to overwrite the storefront widget layout for all public store pages.\n\n";
    echo "Options:\n";
    echo "  -h, --help      Show this help.\n";
    echo "      --reset     Delete existing storefront widgets for known public pages, then seed the full layout.\n\n";
    echo "Example:\n";
    echo "  php public/local/moderncommerce/cli/seed_storefront.php\n";
    echo "  php public/local/moderncommerce/cli/seed_storefront.php --reset\n";
    exit(0);
}

\local_moderncommerce\storefront\seed::run(!empty($options['reset']));

echo !empty($options['reset'])
    ? 'Modern Commerce storefront widgets reset and seeded.' . PHP_EOL
    : 'Modern Commerce storefront widgets seeded.' . PHP_EOL;

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
 * Seeds Modern Commerce Moodle role presets.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_moderncommerce\services\role_preset_service;

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'dry-run' => false,
        'role' => '',
        'json' => false,
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
    echo "Seed Modern Commerce Moodle role presets.\n\n";
    echo "By default this command creates missing Modern Commerce preset roles and adds missing preset capabilities.\n";
    echo "It never assigns users and it skips existing shortname collisions unless the role has the Modern Commerce marker.\n\n";
    echo "Options:\n";
    echo "  -h, --help            Show this help.\n";
    echo "      --dry-run         Report the changes that would be made without writing roles or capabilities.\n";
    echo "      --role=SHORTNAME  Seed one preset by shortname or preset key.\n";
    echo "      --json            Print the full machine-readable summary as JSON.\n\n";
    echo "Examples:\n";
    echo "  php public/local/moderncommerce/cli/seed_role_presets.php --dry-run\n";
    echo "  php public/local/moderncommerce/cli/seed_role_presets.php --role=moderncommercefinance\n";
    echo "  php public/local/moderncommerce/cli/seed_role_presets.php --json\n";
    exit(0);
}

$result = role_preset_service::seed_presets(
    !empty($options['dry-run']),
    trim((string) $options['role']) !== '' ? (string) $options['role'] : null
);

if (!empty($result['unknown'])) {
    cli_error('Unknown Modern Commerce role preset: ' . $result['unknown']);
}

if (!empty($options['json'])) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

cli_heading(!empty($result['dryrun'])
    ? 'Modern Commerce role preset dry run'
    : 'Modern Commerce role presets seeded');

if (!empty($result['dryrun'])) {
    echo 'Would create roles: ' . $result['wouldcreate'] . PHP_EOL;
    echo 'Would update roles: ' . $result['wouldupdate'] . PHP_EOL;
    echo 'Would leave unchanged: ' . $result['wouldleaveunchanged'] . PHP_EOL;
} else {
    echo 'Created roles: ' . $result['created'] . PHP_EOL;
    echo 'Updated roles: ' . $result['updated'] . PHP_EOL;
    echo 'Unchanged roles: ' . $result['unchanged'] . PHP_EOL;
    echo 'Capabilities added: ' . $result['capabilitiesadded'] . PHP_EOL;
}
echo 'Skipped collisions: ' . $result['skipped'] . PHP_EOL . PHP_EOL;

foreach ($result['roles'] as $role) {
    $changed = !empty($result['dryrun'])
        ? count($role['capabilities_to_add'] ?? [])
        : count($role['capabilities_added'] ?? []);
    echo $role['shortname'] . ': ' . $role['status'];
    if ($changed > 0) {
        echo ' (' . $changed . ' capabilities)';
    }
    if (!empty($role['reason'])) {
        echo ' - ' . $role['reason'];
    }
    echo PHP_EOL;
}

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
 * Modern Commerce add-on catalog.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\output\admin_shell;

/**
 * Check whether a plugin is installed and upgraded.
 *
 * @param string $component Plugin component.
 * @return bool
 */
function local_moderncommerce_addons_plugin_available(string $component): bool {
    static $availability = [];

    if (!array_key_exists($component, $availability)) {
        $pluginman = core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info($component);
        $availability[$component] = $plugininfo !== null && $plugininfo->is_installed_and_upgraded();
    }

    return $availability[$component];
}

/**
 * Resolve the installed component from a candidate list.
 *
 * @param string[] $components Component candidates.
 * @return string Installed component or empty string.
 */
function local_moderncommerce_addons_resolve_component(array $components): string {
    foreach ($components as $component) {
        if (local_moderncommerce_addons_plugin_available($component)) {
            return $component;
        }
    }

    return '';
}

/**
 * Build a catalog URL for an add-on.
 *
 * @param string $slug Add-on catalog slug.
 * @return string Catalog URL.
 */
function local_moderncommerce_addons_catalog_url(string $slug): string {
    return 'https://agunfoninteractivity.com/moderncommerce/addons/' . $slug;
}

/**
 * Add-on catalog definitions.
 *
 * @return array[]
 */
function local_moderncommerce_addons_definitions(): array {
    return [
        [
            'key' => 'enrolmentnotifier',
            'name' => get_string('addon_enrolmentnotifier', 'local_moderncommerce'),
            'description' => get_string('addon_enrolmentnotifier_desc', 'local_moderncommerce'),
            'icon' => 'bi-bell',
            'tier' => 'premium',
            'requirement' => 'optional',
            'component' => 'local_ccp_enrolmentnotifier',
            'components' => [
                'local_ccp_enrolmentnotifier',
                'local_modernenrolnotifier',
            ],
            'geturl' => 'https://marketplace.moodle.com/plugins/76',
            'openurls' => [
                'local_ccp_enrolmentnotifier' => '/local/ccp_enrolmentnotifier/admin/index.php',
                'local_modernenrolnotifier' => '/local/moderncommerce/admin/enrolment_notifier.php',
            ],
        ],
        [
            'key' => 'coursereminder',
            'name' => get_string('addon_coursereminder', 'local_moderncommerce'),
            'description' => get_string('addon_coursereminder_desc', 'local_moderncommerce'),
            'icon' => 'bi-clock',
            'tier' => 'premium',
            'requirement' => 'optional',
            'component' => 'local_ccp_coursereminder',
            'components' => [
                'local_ccp_coursereminder',
                'local_moderncoursereminder',
            ],
            'geturl' => 'https://marketplace.moodle.com/plugins/68',
            'openurls' => [
                'local_ccp_coursereminder' => '/local/ccp_coursereminder/admin/index.php',
                'local_moderncoursereminder' => '/local/moderncommerce/admin/course_reminders.php',
            ],
        ],
        [
            'key' => 'coursecertificate',
            'name' => get_string('addon_coursecertificate', 'local_moderncommerce'),
            'description' => get_string('addon_coursecertificate_desc', 'local_moderncommerce'),
            'icon' => 'bi-award',
            'tier' => 'free',
            'requirement' => 'optional',
            'dependency' => get_string('addon_coursecertificate_dependency', 'local_moderncommerce'),
            'component' => 'mod_coursecertificate',
            'components' => ['mod_coursecertificate'],
            'geturl' => 'https://moodle.org/plugins/mod_coursecertificate',
            'openurls' => [
                'mod_coursecertificate' => '/admin/tool/certificate/manage_templates.php',
            ],
        ],
    ];
}

/**
 * Build rows for the add-ons template.
 *
 * @return array
 */
function local_moderncommerce_addons_build_rows(): array {
    $rows = [];

    foreach (local_moderncommerce_addons_definitions() as $definition) {
        $installedcomponent = local_moderncommerce_addons_resolve_component($definition['components']);
        $installed = $installedcomponent !== '';
        $component = $installed ? $installedcomponent : $definition['component'];
        $openurl = '';

        if ($installed && !empty($definition['openurls'][$installedcomponent])) {
            $openurl = (new moodle_url($definition['openurls'][$installedcomponent]))->out(false);
        }

        $rows[] = [
            'key' => $definition['key'],
            'name' => $definition['name'],
            'description' => $definition['description'],
            'icon' => $definition['icon'],
            'component' => $component,
            'geturl' => $definition['geturl'],
            'openurl' => $openurl,
            'installed' => $installed,
            'tier' => $definition['tier'],
            'statuslabel' => get_string(
                $installed ? 'addoninstalled' : 'addonnotinstalled',
                'local_moderncommerce'
            ),
            'statusclass' => $installed ? 'success' : 'neutral',
            'tierlabel' => get_string(
                $definition['tier'] === 'free' ? 'addonfree' : 'addonpremium',
                'local_moderncommerce'
            ),
            'tierclass' => $definition['tier'] === 'free' ? 'success' : 'primary',
            'requirementlabel' => get_string(
                $definition['requirement'] === 'required' ? 'addonrequired' : 'addonoptional',
                'local_moderncommerce'
            ),
            'requirementclass' => $definition['requirement'] === 'required' ? 'danger' : 'neutral',
            'hasdependency' => !empty($definition['dependency']),
            'dependency' => $definition['dependency'] ?? '',
        ];
    }

    return $rows;
}

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/addons.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('addons', 'local_moderncommerce'));

$addons = local_moderncommerce_addons_build_rows();
$installedcount = count(array_filter($addons, static function (array $addon): bool {
    return !empty($addon['installed']);
}));
$premiumcount = count(array_filter($addons, static function (array $addon): bool {
    return $addon['tier'] === 'premium';
}));

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/addons', [
    'addons' => $addons,
    'comparison' => local_moderncommerce_addons_comparison(),
    'installedsummary' => get_string(
        'addonsinstalledsummary',
        'local_moderncommerce',
        (object) [
            'installed' => $installedcount,
            'total' => count($addons),
        ]
    ),
    'premiumsummary' => get_string(
        'addonspremiumsummary',
        'local_moderncommerce',
        (object) [
            'premium' => $premiumcount,
            'total' => count($addons),
        ]
    ),
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'link',
        'url' => local_moderncommerce_addons_catalog_url(''),
        'label' => get_string('addonscatalog', 'local_moderncommerce'),
        'icon' => 'bi-box-arrow-up-right',
        'attributes' => [
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        ],
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'addons',
    get_string('addons', 'local_moderncommerce'),
    $contenthtml,
    get_string('addons_desc', 'local_moderncommerce'),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();

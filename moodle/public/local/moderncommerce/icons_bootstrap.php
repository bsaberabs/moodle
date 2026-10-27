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
 * List curated Bootstrap category icons for admin use.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\output\admin_shell;

$context = context_system::instance();
require_login();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/icons_bootstrap.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(get_string('iconbrowser_title', 'local_moderncommerce'));

// Load category icons from JSON file.
$jsonpath = __DIR__ . '/category-icons.json';
$domains = [];

if (file_exists($jsonpath)) {
    $jsondata = file_get_contents($jsonpath);
    $data = json_decode($jsondata, true);

    if ($data && is_array($data)) {
        // Group by domain.
        foreach ($data as $item) {
            $domain = $item['domain'] ?? 'Other';
            if (!isset($domains[$domain])) {
                $domains[$domain] = [];
            }
            $domains[$domain][] = $item;
        }
    }
}

// Build the page content (captured, then rendered inside the admin shell).
ob_start();

echo html_writer::start_div('icon-gallery-header');
echo html_writer::tag('h3', get_string('iconbrowser_heading', 'local_moderncommerce'));
echo html_writer::tag(
    'p',
    get_string('iconbrowser_intro', 'local_moderncommerce')
);
echo html_writer::end_div();

// Search box.
echo html_writer::start_div('icon-search-box');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'iconSearch',
    'class' => 'form-control',
    'placeholder' => get_string('iconbrowser_searchplaceholder', 'local_moderncommerce'),
    'autocomplete' => 'off',
]);
echo html_writer::end_div();

echo html_writer::div(
    get_string('iconbrowser_copied', 'local_moderncommerce'),
    'copy-notification',
    ['id' => 'copyNotification']
);

// Render by domain sections.
foreach ($domains as $domain => $items) {
    echo html_writer::tag('h4', $domain, [
        'class' => 'domain-heading',
        'style' => 'margin: 2rem 0 1rem; padding: 0.5rem; background: #f5f5f5; border-left: 4px solid #0066cc;',
    ]);

    echo html_writer::start_tag('div', ['class' => 'mc-icon-browser__grid icon-domain-grid']);
    foreach ($items as $item) {
        $iconclass = 'bi ' . $item['icon'];
        $label = $item['label'];
        $searchtext = strtolower($label . ' ' . $domain . ' ' . $item['icon']);

        echo html_writer::start_tag('div', [
            'class' => 'mc-icon-browser__item',
            'data-icon' => $iconclass,
            'data-label' => $searchtext,
            'title' => get_string('iconbrowser_clicktocopy', 'local_moderncommerce', $iconclass),
        ]);
        echo html_writer::tag('i', '', ['class' => $iconclass, 'style' => 'font-size: 2rem;']);
        echo html_writer::start_div('icon-details');
        echo html_writer::tag('strong', $label, ['style' => 'display: block; margin-bottom: 0.25rem;']);
        echo html_writer::tag('code', $iconclass, ['style' => 'font-size: 0.85rem;']);
        echo html_writer::end_div();
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');
}

// If no icons loaded, show message.
if (empty($domains)) {
    echo html_writer::tag(
        'p',
        get_string('iconbrowser_noicons', 'local_moderncommerce'),
        ['class' => 'alert alert-warning']
    );
}

// External link.
$exturl = new moodle_url('https://icons.getbootstrap.com/');
echo html_writer::start_div('icon-footer');
echo html_writer::tag(
    'p',
    html_writer::link($exturl, get_string('iconbrowser_viewcatalog', 'local_moderncommerce'), [
        'target' => '_blank',
        'rel' => 'noopener',
    ])
);
echo html_writer::end_div();

$PAGE->requires->js_call_amd('local_moderncommerce/icon_browser', 'init');

$contenthtml = ob_get_clean();

// Build the shell before the header so its CSS requirements register in time.
$shellhtml = admin_shell::render_page(
    $OUTPUT,
    '',
    get_string('iconbrowser_title', 'local_moderncommerce'),
    $contenthtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();

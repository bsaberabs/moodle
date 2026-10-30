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
 * Privacy and cookies of the trainer's pages: consents to marketing, cookie banner, measurement tools.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\privacy_service;

$url = new moodle_url('/local/oriskill/privacy.php');
$creator = creator_service::require_current_creator($url, 'menu:privacy');

$mform = new \local_oriskill\form\privacy_form($url);
$mform->set_data((array) privacy_service::settings($creator));
if ($data = $mform->get_data()) {
    $before = privacy_service::settings($creator);
    privacy_service::save($creator, $data);
    $after = privacy_service::settings($creator);
    $state = fn($on) => get_string($on ? 'yes' : 'no');
    \local_oriskill\log_service::add($creator, 'privacy_saved', [], null, null, [
        get_string('privacy:bannersite', 'local_oriskill') => [$state($before->bannersite), $state($after->bannersite)],
        get_string('privacy:bannerfunnels', 'local_oriskill') => [$state($before->bannerfunnels), $state($after->bannerfunnels)],
        get_string('privacy:textdirect', 'local_oriskill') => [$before->textdirect, $after->textdirect],
        get_string('privacy:textpartners', 'local_oriskill') => [$before->askpartners ? $before->textpartners : '–',
            $after->askpartners ? $after->textpartners : '–'],
        get_string('privacy:gaid', 'local_oriskill') => [$before->gaid ?: '–', $after->gaid ?: '–'],
        get_string('privacy:metapixel', 'local_oriskill') => [$before->metapixel ?: '–', $after->metapixel ?: '–'],
    ]);
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/privacy.php', get_string('menu:privacy', 'local_oriskill'));
echo html_writer::tag('p', get_string('privacy:intro', 'local_oriskill'), ['class' => 'text-muted']);
echo html_writer::start_div('oriskill-card');
$mform->display();
echo html_writer::end_div();
echo creator_service::space_end();
echo $OUTPUT->footer();

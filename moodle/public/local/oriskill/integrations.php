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
 * Trainer's integrations: ActiveCampaign after payment.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\activecampaign_service;
use local_oriskill\creator_service;
use local_oriskill\form\activecampaign_form;
use local_oriskill\log_service;

$url = new moodle_url('/local/oriskill/integrations.php');
$creator = creator_service::require_current_creator($url, 'menu:integrations');
$account = activecampaign_service::account($creator);

// Lists loaded by "Test", kept for the session so the list can be picked by name.
$listskey = 'local_oriskill_aclists_' . $creator->id;
$lists = $SESSION->$listskey ?? [];
$form = new activecampaign_form($url, ['haskey' => !empty($account->apikey), 'lists' => $lists]);
$form->set_data([
    'enabled' => $account->enabled ?? 0,
    'apiurl' => $account->apiurl ?? '',
    'listid' => $account->listid ?? 0,
    'tags' => $account->tags ?? '',
    'coursetags' => $account->coursetags ?? 1,
    'unpaidtag' => $account->unpaidtag ?? '',
]);

if ($data = $form->get_data()) {
    activecampaign_service::save($creator, $data);
    log_service::add($creator, 'activecampaign_saved', ['state' => get_string(!empty($data->enabled) ? 'yes' : 'no')]);
    if (!empty($data->testbutton)) {
        try {
            $SESSION->$listskey = activecampaign_service::lists(activecampaign_service::account($creator));
            redirect($url, get_string('ac:testok', 'local_oriskill', count($SESSION->$listskey)), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } catch (moodle_exception $e) {
            redirect($url, get_string('ac:testfailed', 'local_oriskill', $e->getMessage()), null,
                \core\output\notification::NOTIFY_ERROR);
        }
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/integrations.php', get_string('menu:integrations', 'local_oriskill'));
echo html_writer::start_div('oriskill-card');
echo html_writer::tag('h3', 'ActiveCampaign', ['class' => 'h5']);
echo html_writer::tag('p', get_string('ac:intro', 'local_oriskill'), ['class' => 'text-muted']);
if (!empty($account->lastresult)) {
    echo html_writer::div(s($account->lastresult), 'small mb-3 ' .
        (str_starts_with($account->lastresult, '✓') ? 'text-success' : 'text-danger'));
}
$form->display();
echo html_writer::end_div();
echo creator_service::space_end();
echo $OUTPUT->footer();

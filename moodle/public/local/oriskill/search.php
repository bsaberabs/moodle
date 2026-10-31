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
 * Search of the whole trainer space, from the box of its header.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\search_service;

$q = trim(optional_param('q', '', PARAM_TEXT));
$only = optional_param('only', '', PARAM_ALPHA);
$only = isset(search_service::KINDS[$only]) ? $only : '';
$url = new moodle_url('/local/oriskill/search.php', array_filter(['q' => $q, 'only' => $only]));
$creator = creator_service::require_current_creator($url, 'search:title');
$results = search_service::search($creator, creator_service::current_role(), $q, $only);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/search.php', get_string('search:title', 'local_oriskill'));
if (core_text::strlen($q) < search_service::MINLENGTH) {
    echo html_writer::div(get_string('search:tooshort', 'local_oriskill', search_service::MINLENGTH), 'oriskill-card oriskill-empty');
} else if (!$results) {
    echo html_writer::div('<i class="fa fa-magnifying-glass" aria-hidden="true"></i>' .
        get_string('search:none', 'local_oriskill', s($q)), 'oriskill-card oriskill-empty');
} else {
    echo html_writer::tag('p', get_string('search:for', 'local_oriskill', s($q)), ['class' => 'text-muted']);
    $cards = '';
    foreach ($results as $kind => $items) {
        $list = '';
        foreach ($items as $item) {
            $list .= html_writer::tag('li', html_writer::link($item->url, s($item->title), ['class' => 'fw-semibold']) .
                ($item->subtitle !== '' ? html_writer::div(s($item->subtitle), 'small text-muted') : ''), ['class' => 'py-2 border-bottom']);
        }
        $more = !$only && count($items) >= search_service::LIMIT ? html_writer::link(new moodle_url($url, ['only' => $kind]),
            get_string('search:more', 'local_oriskill'), ['class' => 'small']) : '';
        $cards .= html_writer::div(html_writer::tag('h3', get_string('search:kind_' . $kind, 'local_oriskill') . ' (' .
            count($items) . ')', ['class' => 'h6 text-uppercase text-muted']) .
            html_writer::tag('ul', $list, ['class' => 'list-unstyled mb-2']) . $more, 'oriskill-card');
    }
    echo html_writer::div($cards, 'oriskill-grid-2');
}
echo creator_service::space_end();
echo $OUTPUT->footer();

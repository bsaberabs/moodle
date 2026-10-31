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
 * Library of the trainer's files: folders, upload, fixed links, rename, move, bin, space used.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\library_service as ls;
use local_oriskill\log_service;
use local_oriskill\team_service;

$folderid = optional_param('folder', 0, PARAM_INT);
$trash = optional_param('trash', 0, PARAM_BOOL);
$search = trim(optional_param('q', '', PARAM_TEXT));
$url = new moodle_url('/local/oriskill/library.php', array_filter(['folder' => $folderid, 'trash' => (int) $trash,
    'q' => $search]));
$creator = creator_service::require_current_creator($url, 'menu:library');
$canchange = team_service::can_change(creator_service::current_role(), '/local/oriskill/library.php');
$folder = ls::require_folder($creator, $folderid);

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    if (!$canchange) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    try {
        $message = get_string('changessaved');
        if ($action === 'upload') {
            $files = [];
            foreach ($_FILES['files']['name'] ?? [] as $i => $name) {
                if (($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['files']['tmp_name'][$i])) {
                    throw new moodle_exception('library:uploadfailed', 'local_oriskill', '', s($name));
                }
                $files[] = ['name' => $name, 'path' => $_FILES['files']['tmp_name'][$i]];
            }
            $added = ls::add($creator, $folderid, $files);
            log_service::add($creator, 'library_added', ['count' => count($added)]);
            $message = get_string('library:added', 'local_oriskill', count($added));
        } else if ($action === 'newfolder' || $action === 'renamefolder') {
            ls::save_folder($creator, required_param('name', PARAM_TEXT), $action === 'newfolder' ? $folderid
                : (int) ($folder->parentid ?? 0), $action === 'renamefolder' ? $folderid : 0);
        } else if ($action === 'deletefolder') {
            ls::delete_folder($creator, $folderid);
            redirect(new moodle_url('/local/oriskill/library.php', ['folder' => $folder->parentid]), $message, null,
                \core\output\notification::NOTIFY_SUCCESS);
        } else {
            $file = ls::require_file($creator, required_param('id', PARAM_INT));
            if ($action === 'rename') {
                ls::rename($file, required_param('name', PARAM_TEXT));
            } else if ($action === 'move') {
                ls::move($creator, $file, required_param('to', PARAM_INT));
            } else if ($action === 'delete') {
                ls::trash($file, true);
                log_service::add($creator, 'library_trashed', ['name' => $file->name]);
                $message = get_string('library:trashed', 'local_oriskill', ls::TRASH_DAYS);
            } else if ($action === 'restore') {
                ls::trash($file, false);
            } else if ($action === 'erase') {
                ls::erase($file);
                log_service::add($creator, 'library_erased', ['name' => $file->name]);
            }
        }
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$folders = ls::folders($creator);
$post = fn(string $action, string $inner, array $hidden = [], string $confirm = '', string $class = 'd-inline') =>
    html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(),
        'action' => $action] + $hidden)) . $inner, ['method' => 'post', 'action' => $url->out(false), 'class' => $class] +
        ($confirm !== '' ? ['onsubmit' => 'return confirm(' . json_encode($confirm) . ')'] : []));
$button = fn(string $label, string $class = 'btn-outline-secondary') => html_writer::tag('button', $label,
    ['type' => 'submit', 'class' => 'btn btn-sm ' . $class]);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/library.php', get_string('menu:library', 'local_oriskill'));
echo html_writer::tag('p', get_string('library:intro', 'local_oriskill'), ['class' => 'text-muted']);

// Space used.
$used = ls::used($creator);
$quota = ls::quota($creator);
$percent = min(100, $quota ? round(100 * $used / $quota, 1) : 100);
echo html_writer::div(
    html_writer::div(get_string('library:usage', 'local_oriskill', (object) ['used' => display_size($used),
        'quota' => display_size($quota)]), 'small mb-1') .
    html_writer::div(html_writer::div('', 'progress-bar' . ($percent >= 90 ? ' bg-danger' : ''), ['role' => 'progressbar',
        'style' => 'width: ' . $percent . '%', 'aria-valuenow' => $percent, 'aria-valuemin' => 0, 'aria-valuemax' => 100,
        'aria-label' => get_string('library:usagelabel', 'local_oriskill')]), 'progress', ['style' => 'height: 8px']),
    'oriskill-card mb-3');

// Where we are: the folders, the bin, the search.
$crumbs = [html_writer::link(new moodle_url('/local/oriskill/library.php'), get_string('library:top', 'local_oriskill'))];
for ($f = $folder, $chain = []; $f; $f = $folders[$f->parentid] ?? null) {
    array_unshift($chain, html_writer::link(new moodle_url('/local/oriskill/library.php', ['folder' => $f->id]), s($f->name)));
}
$crumbs = array_merge($crumbs, $chain ?? []);
echo html_writer::div(
    html_writer::div(($trash ? get_string('library:trash', 'local_oriskill') : implode(' / ', $crumbs)), 'fw-semibold') .
    html_writer::div(
        html_writer::tag('form', html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $search,
            'class' => 'form-control form-control-sm', 'placeholder' => get_string('library:search', 'local_oriskill'),
            'aria-label' => get_string('library:search', 'local_oriskill')]), ['method' => 'get', 'action' => $url->out(false)]) .
        html_writer::link(new moodle_url('/local/oriskill/library.php', $trash ? [] : ['trash' => 1]),
            '<i class="fa ' . ($trash ? 'fa-folder-open' : 'fa-trash-can') . ' me-1" aria-hidden="true"></i>' .
            get_string($trash ? 'library:backtofiles' : 'library:trash', 'local_oriskill'), ['class' => 'btn btn-sm btn-outline-secondary']),
        'd-flex gap-2'),
    'd-flex flex-wrap justify-content-between align-items-center gap-2 mb-3');

if (!$trash && $search === '' && $canchange) {
    // Upload: choose or drop several files.
    echo html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(),
        'action' => 'upload'])) .
        html_writer::tag('label', '<i class="fa fa-cloud-arrow-up fa-2x d-block mb-2" aria-hidden="true"></i>' .
            get_string('library:drop', 'local_oriskill') .
            html_writer::empty_tag('input', ['type' => 'file', 'name' => 'files[]', 'multiple' => 'multiple',
                'class' => 'visually-hidden', 'onchange' => 'this.form.submit()']),
            ['class' => 'd-block text-center p-4 border border-2 rounded', 'style' => 'border-style: dashed !important; cursor: pointer',
             'ondragover' => 'event.preventDefault(); this.classList.add("bg-light")',
             'ondragleave' => 'this.classList.remove("bg-light")',
             'ondrop' => 'event.preventDefault(); var i = this.querySelector("input"); i.files = event.dataTransfer.files; i.form.submit()']) .
        html_writer::div(get_string('library:drop_help', 'local_oriskill', display_size(get_max_upload_file_size())), 'form-text'),
        ['method' => 'post', 'action' => $url->out(false), 'enctype' => 'multipart/form-data', 'class' => 'oriskill-card mb-3']);
}

// Folders of this folder.
if (!$trash && $search === '') {
    $subfolders = array_filter($folders, fn($f) => (int) $f->parentid === $folderid);
    $items = '';
    foreach ($subfolders as $sub) {
        $items .= html_writer::link(new moodle_url('/local/oriskill/library.php', ['folder' => $sub->id]),
            '<i class="fa fa-folder me-2 text-warning" aria-hidden="true"></i>' . s($sub->name),
            ['class' => 'oriskill-card d-block py-2 text-decoration-none']);
    }
    $tools = '';
    if ($canchange) {
        $tools = html_writer::tag('details', html_writer::tag('summary', get_string('library:newfolder', 'local_oriskill'),
            ['class' => 'btn btn-sm btn-outline-primary']) . $post('newfolder', html_writer::empty_tag('input', ['type' => 'text',
            'name' => 'name', 'required' => 'required', 'maxlength' => 100, 'class' => 'form-control form-control-sm mb-1',
            'aria-label' => get_string('name')]) . $button(get_string('create'), 'btn-primary'), [], '', 'mt-2'),
            ['class' => 'd-inline-block me-2']);
        if ($folder) {
            $tools .= html_writer::tag('details', html_writer::tag('summary', get_string('library:renamefolder', 'local_oriskill'),
                ['class' => 'btn btn-sm btn-outline-secondary']) . $post('renamefolder', html_writer::empty_tag('input',
                ['type' => 'text', 'name' => 'name', 'value' => $folder->name, 'required' => 'required', 'maxlength' => 100,
                'class' => 'form-control form-control-sm mb-1', 'aria-label' => get_string('name')]) .
                $button(get_string('savechanges'), 'btn-primary'), [], '', 'mt-2'), ['class' => 'd-inline-block me-2']) .
                $post('deletefolder', $button(get_string('library:deletefolder', 'local_oriskill'), 'btn-outline-danger'), [],
                    get_string('library:deletefolder_confirm', 'local_oriskill'));
        }
    }
    if ($items || $tools) {
        echo html_writer::div(($items ? html_writer::div($items, 'oriskill-grid-3 mb-2') : '') . $tools, 'mb-3');
    }
}

// Files.
$files = ls::files($creator, $folderid, $trash, $search);
if (!$files) {
    echo html_writer::div('<i class="fa fa-folder-open" aria-hidden="true"></i>' . get_string($trash ? 'library:trashempty'
        : 'library:empty', 'local_oriskill'), 'oriskill-card oriskill-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle oriskill-txlist';
    $table->head = ['', get_string('name'), get_string('library:size', 'local_oriskill'), get_string('date'), ''];
    $moveto = [0 => get_string('library:top', 'local_oriskill')] + array_map(fn($f) => $f->path, $folders);
    foreach ($files as $file) {
        $link = ls::url($file)->out(false);
        $isimage = str_starts_with((string) $file->mimetype, 'image/');
        $preview = $isimage && !$trash ? html_writer::empty_tag('img', ['src' => $link, 'alt' => '', 'loading' => 'lazy',
            'style' => 'width: 48px; height: 48px; object-fit: cover; border-radius: 6px'])
            : html_writer::tag('i', '', ['class' => 'fa fa-2x text-muted ' . (str_starts_with((string) $file->mimetype, 'video/')
                ? 'fa-file-video' : (str_starts_with((string) $file->mimetype, 'audio/') ? 'fa-file-audio'
                : ($file->mimetype === 'application/pdf' ? 'fa-file-pdf' : 'fa-file'))), 'aria-hidden' => 'true']);
        $actions = '';
        if ($trash) {
            $actions = $canchange ? $post('restore', $button(get_string('library:restore', 'local_oriskill')), ['id' => $file->id]) .
                ' ' . $post('erase', $button(get_string('library:erase', 'local_oriskill'), 'btn-outline-danger'), ['id' => $file->id],
                get_string('library:erase_confirm', 'local_oriskill')) : '';
            $date = get_string('library:deletedon', 'local_oriskill', userdate($file->deleted, get_string('strftimedate',
                'langconfig')));
        } else {
            $usage = ls::usage($creator, $file);
            $actions = html_writer::tag('button', '<i class="fa fa-link me-1" aria-hidden="true"></i>' .
                get_string('library:copylink', 'local_oriskill'), ['type' => 'button', 'class' => 'btn btn-sm btn-outline-primary',
                'onclick' => 'navigator.clipboard.writeText(' . json_encode($link) . '); this.textContent = ' .
                    json_encode(get_string('library:copied', 'local_oriskill'))]) . ' ' .
                html_writer::link($link, get_string('download'), ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank']);
            if ($canchange) {
                $actions .= ' ' . html_writer::tag('details', html_writer::tag('summary', get_string('library:more', 'local_oriskill'),
                        ['class' => 'btn btn-sm btn-link']) .
                    $post('rename', html_writer::empty_tag('input', ['type' => 'text', 'name' => 'name', 'value' => $file->name,
                        'class' => 'form-control form-control-sm mb-1', 'aria-label' => get_string('name')]) .
                        $button(get_string('library:rename', 'local_oriskill')), ['id' => $file->id], '', 'mt-2') .
                    $post('move', html_writer::select($moveto, 'to', (int) $file->folderid, false, ['class' => 'form-select form-select-sm mb-1',
                        'aria-label' => get_string('library:move', 'local_oriskill')]) .
                        $button(get_string('library:move', 'local_oriskill')), ['id' => $file->id], '', 'mt-2') .
                    ($usage ? html_writer::div(get_string('library:usedin', 'local_oriskill', count($usage)) .
                        html_writer::tag('ul', implode('', array_map(fn($u) => html_writer::tag('li', html_writer::link($u->url,
                            s($u->name))), $usage)), ['class' => 'mb-1']), 'small text-danger mt-2') : '') .
                    $post('delete', $button(get_string('delete'), 'btn-outline-danger'), ['id' => $file->id],
                        $usage ? get_string('library:delete_usedconfirm', 'local_oriskill', count($usage))
                            : get_string('library:delete_confirm', 'local_oriskill', ls::TRASH_DAYS), 'mt-2'),
                    ['class' => 'd-inline-block']);
            }
            $date = userdate($file->timecreated, get_string('strftimedate', 'langconfig'));
        }
        $table->data[] = [$preview, s($file->name) . ($search !== '' && $file->folderid && isset($folders[$file->folderid])
            ? html_writer::div(s($folders[$file->folderid]->path), 'small text-muted') : ''), display_size($file->filesize),
            $date, html_writer::div($actions, 'd-flex flex-wrap align-items-start gap-1')];
    }
    echo html_writer::div(html_writer::table($table), 'oriskill-card table-responsive');
}
echo creator_service::space_end();
echo $OUTPUT->footer();

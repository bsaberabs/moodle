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

namespace local_oriskill;

use moodle_url;

/**
 * Library of a trainer's files (videos, images, PDF, audio) in folders, used in their lessons and pages.
 *
 * Each file has a fixed link: its number and a key, not its name nor its folder, so renaming or moving it breaks nothing.
 * A deleted file goes to the bin for TRASH_DAYS (its link stops working), then it is erased. Before deleting, the pages
 * that use the file are shown. The space used is measured against the trainer's quota.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class library_service {

    /** Files and folders. */
    const FILES = 'local_oriskill_libfiles';
    const FOLDERS = 'local_oriskill_libfolders';

    /** File area (item = file number). */
    const FILEAREA = 'library';

    /** Days a deleted file stays in the bin. */
    const TRASH_DAYS = 30;

    /** Space of a trainer when none is set, in gigabytes. */
    const QUOTA_GB = 50;

    /** Extensions that are not accepted (they could run in the browser or on a computer). */
    const BLOCKED = ['php', 'phtml', 'phar', 'js', 'mjs', 'html', 'htm', 'svg', 'exe', 'bat', 'cmd', 'sh', 'msi', 'dll', 'jar'];

    /**
     * Space a trainer may use, in bytes.
     *
     * @param \stdClass $creator Trainer.
     * @return int
     */
    public static function quota(\stdClass $creator): int {
        $gb = (float) (get_config('local_oriskill', 'libraryquota_' . $creator->id) ?: get_config('local_oriskill', 'libraryquota')
            ?: self::QUOTA_GB);
        return (int) round($gb * 1024 * 1024 * 1024);
    }

    /**
     * Space used, the bin included (its files are still stored).
     *
     * @param \stdClass $creator Trainer.
     * @return int Bytes.
     */
    public static function used(\stdClass $creator): int {
        global $DB;

        return (int) $DB->get_field_sql('SELECT COALESCE(SUM(filesize), 0) FROM {' . self::FILES . '} WHERE creatorid = ?',
            [$creator->id]);
    }

    /**
     * Folders of a trainer.
     *
     * @param \stdClass $creator Trainer.
     * @return array id => record, with ->path (names from the top, " / ")
     */
    public static function folders(\stdClass $creator): array {
        global $DB;

        $folders = $DB->get_records(self::FOLDERS, ['creatorid' => $creator->id], 'name');
        foreach ($folders as $folder) {
            $names = [];
            for ($f = $folder, $guard = 0; $f && $guard < 20; $f = $folders[$f->parentid] ?? null, $guard++) {
                array_unshift($names, $f->name);
            }
            $folder->path = implode(' / ', $names);
        }
        \core_collator::asort_objects_by_property($folders, 'path');
        return $folders;
    }

    /**
     * A folder of the trainer (0: the top), or an exception.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Folder.
     * @return \stdClass|null
     */
    public static function require_folder(\stdClass $creator, int $id): ?\stdClass {
        global $DB;

        return $id ? $DB->get_record(self::FOLDERS, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST) : null;
    }

    /**
     * Create, rename or move a folder.
     *
     * @param \stdClass $creator Trainer.
     * @param string $name Name.
     * @param int $parentid Folder it is in (0: the top).
     * @param int $id Folder to rename (0: a new one).
     * @return \stdClass
     */
    public static function save_folder(\stdClass $creator, string $name, int $parentid, int $id = 0): \stdClass {
        global $DB;

        $name = \core_text::substr(trim(clean_param($name, PARAM_TEXT)), 0, 100);
        if ($name === '') {
            throw new \moodle_exception('library:noname', 'local_oriskill');
        }
        self::require_folder($creator, $parentid);
        if ($id) {
            $folder = self::require_folder($creator, $id);
            $folder->name = $name;
            $DB->update_record(self::FOLDERS, $folder);
            return $folder;
        }
        $folder = (object) ['creatorid' => $creator->id, 'parentid' => $parentid, 'name' => $name, 'timecreated' => time()];
        $folder->id = $DB->insert_record(self::FOLDERS, $folder);
        return $folder;
    }

    /**
     * Delete an empty folder.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Folder.
     */
    public static function delete_folder(\stdClass $creator, int $id): void {
        global $DB;

        $folder = self::require_folder($creator, $id);
        if ($DB->record_exists(self::FOLDERS, ['parentid' => $folder->id])
                || $DB->record_exists(self::FILES, ['folderid' => $folder->id, 'deleted' => 0])) {
            throw new \moodle_exception('library:foldernotempty', 'local_oriskill');
        }
        // Files of the bin that were in it go to the top when restored.
        $DB->set_field(self::FILES, 'folderid', 0, ['folderid' => $folder->id]);
        $DB->delete_records(self::FOLDERS, ['id' => $folder->id]);
    }

    /**
     * Add files to a folder, within the quota.
     *
     * @param \stdClass $creator Trainer.
     * @param int $folderid Folder (0: the top).
     * @param array $files of ['name' => file name, 'path' => file on disk]
     * @return array Files added.
     */
    public static function add(\stdClass $creator, int $folderid, array $files): array {
        global $DB, $USER;

        self::require_folder($creator, $folderid);
        $size = array_sum(array_map(fn($f) => (int) filesize($f['path']), $files));
        if (self::used($creator) + $size > self::quota($creator)) {
            throw new \moodle_exception('library:full', 'local_oriskill');
        }
        $context = \context_system::instance();
        $fs = get_file_storage();
        $added = [];
        foreach ($files as $file) {
            $name = clean_filename(\core_text::substr(trim($file['name']), -150)) ?: 'fichier';
            if (in_array(\core_text::strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::BLOCKED, true)) {
                throw new \moodle_exception('library:blocked', 'local_oriskill', '', $name);
            }
            $record = (object) ['creatorid' => $creator->id, 'folderid' => $folderid, 'name' => $name,
                'filekey' => random_string(16), 'storedname' => $name, 'filesize' => (int) filesize($file['path']),
                'mimetype' => mimeinfo('type', $name), 'deleted' => 0, 'userid' => $USER->id, 'timecreated' => time(),
                'timemodified' => time()];
            $record->id = $DB->insert_record(self::FILES, $record);
            $fs->create_file_from_pathname(['contextid' => $context->id, 'component' => 'local_oriskill',
                'filearea' => self::FILEAREA, 'itemid' => $record->id, 'filepath' => '/' . $record->filekey . '/',
                'filename' => $name, 'userid' => $USER->id], $file['path']);
            $added[] = $record;
        }
        return $added;
    }

    /**
     * A file of the trainer, or an exception.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id File.
     * @return \stdClass
     */
    public static function require_file(\stdClass $creator, int $id): \stdClass {
        global $DB;

        return $DB->get_record(self::FILES, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
    }

    /**
     * Files of a folder, or of the bin, or found by name.
     *
     * @param \stdClass $creator Trainer.
     * @param int $folderid Folder (0: the top).
     * @param bool $trash The bin.
     * @param string $search Part of the name (all the folders).
     * @return array
     */
    public static function files(\stdClass $creator, int $folderid, bool $trash = false, string $search = ''): array {
        global $DB;

        $where = 'creatorid = :creator AND deleted ' . ($trash ? '> 0' : '= 0');
        $params = ['creator' => $creator->id];
        if (($search = trim($search)) !== '') {
            $where .= ' AND ' . $DB->sql_like('name', ':q', false);
            $params['q'] = '%' . $DB->sql_like_escape($search) . '%';
        } else if (!$trash) {
            $where .= ' AND folderid = :folder';
            $params['folder'] = $folderid;
        }
        return $DB->get_records_select(self::FILES, $where, $params, $trash ? 'deleted DESC' : 'name');
    }

    /**
     * Fixed link of a file: its number and its key, whatever its name and folder.
     *
     * @param \stdClass $file File.
     * @return moodle_url
     */
    public static function url(\stdClass $file): moodle_url {
        return moodle_url::make_pluginfile_url(\context_system::instance()->id, 'local_oriskill', self::FILEAREA, $file->id,
            '/' . $file->filekey . '/', $file->storedname);
    }

    /**
     * Rename a file (its link does not change).
     *
     * @param \stdClass $file File.
     * @param string $name New name.
     */
    public static function rename(\stdClass $file, string $name): void {
        global $DB;

        $name = clean_filename(\core_text::substr(trim($name), 0, 150));
        if ($name === '') {
            throw new \moodle_exception('library:noname', 'local_oriskill');
        }
        $DB->update_record(self::FILES, (object) ['id' => $file->id, 'name' => $name, 'timemodified' => time()]);
    }

    /**
     * Move a file to another folder (its link does not change).
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $file File.
     * @param int $folderid Folder (0: the top).
     */
    public static function move(\stdClass $creator, \stdClass $file, int $folderid): void {
        global $DB;

        self::require_folder($creator, $folderid);
        $DB->update_record(self::FILES, (object) ['id' => $file->id, 'folderid' => $folderid, 'timemodified' => time()]);
    }

    /**
     * Put a file in the bin (its link stops working), or take it out.
     *
     * @param \stdClass $file File.
     * @param bool $trash In the bin.
     */
    public static function trash(\stdClass $file, bool $trash): void {
        global $DB;

        $DB->update_record(self::FILES, (object) ['id' => $file->id, 'deleted' => $trash ? time() : 0, 'timemodified' => time()]);
    }

    /**
     * Erase a file for good.
     *
     * @param \stdClass $file File.
     */
    public static function erase(\stdClass $file): void {
        global $DB;

        get_file_storage()->delete_area_files(\context_system::instance()->id, 'local_oriskill', self::FILEAREA, $file->id);
        $DB->delete_records(self::FILES, ['id' => $file->id]);
    }

    /**
     * Erase the files in the bin for more than TRASH_DAYS. Run by a task.
     *
     * @param int|null $now Time (tests).
     * @return int Files erased.
     */
    public static function empty_trash(?int $now = null): int {
        global $DB;

        $files = $DB->get_records_select(self::FILES, 'deleted > 0 AND deleted < ?',
            [($now ?? time()) - self::TRASH_DAYS * DAYSECS]);
        foreach ($files as $file) {
            self::erase($file);
        }
        return count($files);
    }

    /**
     * Pages that use a file (its link appears in them): lessons, labels, books, sections and summaries of the trainer's
     * courses, order forms and capture pages.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $file File.
     * @return array of {name, url}
     */
    public static function usage(\stdClass $creator, \stdClass $file): array {
        global $DB;

        $like = '%' . $DB->sql_like_escape('/local_oriskill/' . self::FILEAREA . '/' . $file->id . '/' . $file->filekey . '/') . '%';
        $courses = $DB->get_records('course', ['category' => $creator->categoryid], '', 'id, fullname');
        $found = [];
        if ($courses) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
            foreach (['page' => 'content', 'label' => 'intro', 'book' => 'intro', 'resource' => 'intro', 'url' => 'intro',
                    'assign' => 'intro', 'quiz' => 'intro', 'forum' => 'intro'] as $module => $field) {
                if (!$DB->get_manager()->table_exists($module)) {
                    continue;
                }
                foreach ($DB->get_records_select($module, "course $insql AND " . $DB->sql_like($field, ':l', false),
                        $params + ['l' => $like], '', 'id, course, name') as $record) {
                    $cm = get_coursemodule_from_instance($module, $record->id, $record->course);
                    $found[] = (object) ['name' => format_string($courses[$record->course]->fullname) . ' › ' .
                        format_string($record->name), 'url' => $cm ? new moodle_url('/mod/' . $module . '/view.php',
                        ['id' => $cm->id]) : new moodle_url('/course/view.php', ['id' => $record->course])];
                }
            }
            foreach ($DB->get_records_sql("SELECT ch.id, ch.title, b.course, b.id AS bookid FROM {book_chapters} ch
                                             JOIN {book} b ON b.id = ch.bookid
                                            WHERE b.course $insql AND " . $DB->sql_like('ch.content', ':l', false),
                    $params + ['l' => $like]) as $chapter) {
                $cm = get_coursemodule_from_instance('book', $chapter->bookid, $chapter->course);
                $found[] = (object) ['name' => format_string($courses[$chapter->course]->fullname) . ' › ' .
                    format_string($chapter->title), 'url' => new moodle_url('/mod/book/view.php', ['id' => $cm->id ?? 0,
                    'chapterid' => $chapter->id])];
            }
            foreach ($DB->get_records_select('course_sections', "course $insql AND " . $DB->sql_like('summary', ':l', false),
                    $params + ['l' => $like], '', 'id, course, section, name') as $section) {
                $found[] = (object) ['name' => format_string($courses[$section->course]->fullname) . ' › ' .
                    ($section->name !== null && $section->name !== '' ? format_string($section->name)
                        : get_string('section') . ' ' . $section->section),
                    'url' => new moodle_url('/course/view.php', ['id' => $section->course, 'section' => $section->section])];
            }
            foreach ($courses as $course) {
                if ($DB->record_exists_select('course', 'id = :id AND ' . $DB->sql_like('summary', ':l', false),
                        ['id' => $course->id, 'l' => $like])) {
                    $found[] = (object) ['name' => format_string($course->fullname),
                        'url' => new moodle_url('/course/edit.php', ['id' => $course->id])];
                }
            }
        }
        foreach (['local_oriskill_orderforms' => ['orderform.php', ['description', 'thankyou', 'bumptext', 'upselltext']],
                'local_oriskill_capturepages' => ['capturepage.php', ['description', 'thankyou']]] as $table => [$page, $fields]) {
            $where = implode(' OR ', array_map(fn($f) => $DB->sql_like($f, ':l' . $f, false), $fields));
            foreach ($DB->get_records_select($table, "creatorid = :creator AND ($where)", ['creator' => $creator->id] +
                    array_combine(array_map(fn($f) => 'l' . $f, $fields), array_fill(0, count($fields), $like))) as $record) {
                $found[] = (object) ['name' => format_string($record->title),
                    'url' => new moodle_url('/local/oriskill/' . $page, ['id' => $record->id])];
            }
        }
        return $found;
    }

    /**
     * Serve a file from its link: the file must be the one of that number and key, not in the bin.
     *
     * @param int $id File number.
     * @param array $args Rest of the link: key, name.
     * @param array $options Sending options.
     * @return bool False when not found.
     */
    public static function serve(int $id, array $args, array $options): bool {
        global $DB;

        $key = (string) array_shift($args);
        $file = $DB->get_record(self::FILES, ['id' => $id, 'deleted' => 0]);
        if (!$file || !hash_equals($file->filekey, $key)) {
            return false;
        }
        $stored = get_file_storage()->get_file(\context_system::instance()->id, 'local_oriskill', self::FILEAREA, $id,
            '/' . $file->filekey . '/', $file->storedname);
        if (!$stored) {
            return false;
        }
        // Shown in the page (images, video, audio, PDF); the other kinds are downloaded under their current name.
        $inline = (bool) preg_match('~^(image|video|audio)/|^application/pdf$~', (string) $file->mimetype);
        send_stored_file($stored, DAYSECS, 0, !$inline, $options + ['filename' => $file->name]);
        return true;
    }
}

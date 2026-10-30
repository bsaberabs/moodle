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

/**
 * Import of contacts from a CSV file (migration from another platform: hundreds of thousands of rows).
 *
 * The file is kept in the file storage; rows are processed in the background, BATCH at a time, each batch queuing
 * the next one from where it stopped (byte position in the file). Contacts are merged by e-mail address.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contact_import {

    /** Imports. */
    const TABLE = 'local_oriskill_imports';

    /** File area of the uploaded files. */
    const FILEAREA = 'contactimport';

    /** Rows per background run. */
    const BATCH = 2000;

    /** Errors kept for the trainer to read. */
    const MAX_ERRORS = 200;

    /** Fields a column can go to ('' = ignored). */
    const FIELDS = ['', 'email', 'firstname', 'lastname', 'fullname', 'phone', 'address', 'postcode', 'city', 'country', 'tags',
        'consentdirect', 'consentpartners', 'created', 'course', 'groups', 'state', 'timespent', 'progress', 'lastactivity'];

    /** Earlier time and progress of a learner in a course, brought from the previous platform. */
    const PRIOR = 'local_oriskill_priorprogress';

    /** Distinct values of the course column shown for mapping, at most. */
    const MAX_COURSES = 200;

    /** Column names recognised for each field (lower case, without accents or punctuation). */
    const GUESSES = [
        'email' => ['email', 'e mail', 'mail', 'courriel', 'adresse email', 'adresse e mail', 'email address'],
        'firstname' => ['prenom', 'first name', 'firstname', 'given name'],
        'lastname' => ['nom', 'nom de famille', 'last name', 'lastname', 'surname', 'family name'],
        'fullname' => ['nom complet', 'full name', 'fullname', 'name', 'nom prenom', 'prenom nom'],
        'phone' => ['telephone', 'tel', 'phone', 'mobile', 'portable', 'numero de telephone', 'phone number'],
        'address' => ['adresse', 'address', 'adresse postale', 'rue', 'street'],
        'postcode' => ['code postal', 'cp', 'postcode', 'zip', 'zip code', 'postal code'],
        'city' => ['ville', 'city', 'town', 'commune'],
        'country' => ['pays', 'country'],
        'tags' => ['tags', 'tag', 'etiquettes', 'etiquette', 'labels'],
        'consentdirect' => ['prospection directe', 'consentement', 'optin', 'opt in', 'newsletter', 'accepte les emails',
            'marketing'],
        'consentpartners' => ['prospection partenaires', 'partenaires', 'partners', 'offres partenaires'],
        'created' => ['date d inscription', 'inscription', 'date de creation', 'cree le', 'created', 'created at',
            'date added', 'signup date', 'subscribed'],
        'course' => ['formations', 'formation', 'course', 'courses', 'cours', 'produit', 'product'],
        'groups' => ['groupes', 'groupe', 'groups', 'group'],
        'state' => ['etat', 'statut', 'state', 'status', 'acces'],
        'timespent' => ['temps passe', 'time spent', 'duree', 'progression en secondes'],
        'progress' => ['progression', 'progress', 'avancement'],
        'lastactivity' => ['date de derniere activite', 'derniere activite', 'last activity', 'derniere connexion'],
    ];

    // ----- Preparing ------------------------------------------------------------------------------------------------

    /**
     * Keep an uploaded CSV file and read its columns: the import waits for the trainer to map them.
     *
     * @param \stdClass $creator Trainer.
     * @param string $path Uploaded file.
     * @param string $filename Its name.
     * @return \stdClass Import record.
     */
    public static function prepare(\stdClass $creator, string $path, string $filename): \stdClass {
        global $DB, $USER;

        $handle = fopen($path, 'r');
        $first = $handle ? (string) fgets($handle) : '';
        if ($first === '') {
            throw new \moodle_exception('import:empty', 'local_oriskill');
        }
        $first = self::to_utf8(preg_replace('/^\xEF\xBB\xBF/', '', $first));
        $delimiter = self::delimiter($first);
        $headers = array_map('trim', str_getcsv(rtrim($first, "\r\n"), $delimiter, '"', ''));
        $samples = [];
        while (count($samples) < 3 && ($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                $samples[] = str_getcsv(rtrim(self::to_utf8($line), "\r\n"), $delimiter, '"', '');
            }
        }
        fclose($handle);
        if (count(array_filter($headers, fn($h) => $h !== '')) === 0) {
            throw new \moodle_exception('import:empty', 'local_oriskill');
        }

        $now = time();
        $record = (object) ['creatorid' => $creator->id, 'userid' => $USER->id, 'filename' => \core_text::substr($filename, 0, 255),
            'delimiter' => $delimiter, 'headers' => json_encode(['headers' => $headers, 'samples' => $samples],
                JSON_UNESCAPED_UNICODE), 'mapping' => null, 'options' => null, 'status' => 'draft', 'position' => 0,
            'processed' => 0, 'created' => 0, 'updated' => 0, 'enrolled' => 0, 'failed' => 0, 'errors' => null,
            'timecreated' => $now, 'timemodified' => $now];
        $record->id = $DB->insert_record(self::TABLE, $record);
        get_file_storage()->create_file_from_pathname(['contextid' => \context_system::instance()->id,
            'component' => 'local_oriskill', 'filearea' => self::FILEAREA, 'itemid' => $record->id, 'filepath' => '/',
            'filename' => 'import.csv'], $path);
        return $record;
    }

    /**
     * Field each column goes to, guessed from its name.
     *
     * @param string[] $headers Column names.
     * @return string[] One of FIELDS per column.
     */
    public static function guess(array $headers): array {
        $taken = [];
        $mapping = [];
        foreach ($headers as $header) {
            $key = trim(preg_replace('/[^a-z0-9]+/', ' ', \core_text::strtolower(\core_text::specialtoascii($header))));
            $field = '';
            foreach (self::GUESSES as $candidate => $names) {
                if (!isset($taken[$candidate]) && in_array($key, $names, true)) {
                    $field = $candidate;
                    break;
                }
            }
            if ($field !== '') {
                $taken[$field] = true;
            }
            $mapping[] = $field;
        }
        return $mapping;
    }

    /**
     * Start an import: the mapping and options are saved and the first batch is queued.
     *
     * @param \stdClass $import Import (draft).
     * @param string[] $mapping One of FIELDS per column.
     * @param array $options tags (string[]), courseid, groupid, sendcredentials; with a course column: coursemap
     *                       (value of the column => course id, 0 to give no access), creategroups.
     */
    public static function start(\stdClass $import, array $mapping, array $options): void {
        global $DB;

        $mapping = array_map(fn($f) => in_array($f, self::FIELDS, true) ? $f : '', array_values($mapping));
        if (!in_array('email', $mapping, true)) {
            throw new \moodle_exception('import:noemail', 'local_oriskill');
        }
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $import->creatorid], '*', MUST_EXIST);
        $courseid = (int) ($options['courseid'] ?? 0);
        if ($courseid && !$DB->record_exists('course', ['id' => $courseid, 'category' => $creator->categoryid])) {
            throw new \moodle_exception('invalidcourseid');
        }
        $groupid = $courseid ? (int) ($options['groupid'] ?? 0) : 0;
        if ($groupid && !$DB->record_exists('groups', ['id' => $groupid, 'courseid' => $courseid])) {
            $groupid = 0;
        }
        $own = $DB->get_records_menu('course', ['category' => $creator->categoryid], '', 'id, id AS same');
        $coursemap = [];
        foreach ($options['coursemap'] ?? [] as $value => $id) {
            $coursemap[(string) $value] = isset($own[(int) $id]) ? (int) $id : 0;
        }
        $DB->update_record(self::TABLE, (object) ['id' => $import->id, 'mapping' => json_encode($mapping),
            'options' => json_encode(['tags' => array_values($options['tags'] ?? []), 'courseid' => $courseid,
                'groupid' => $groupid, 'sendcredentials' => !empty($options['sendcredentials']), 'coursemap' => $coursemap,
                'creategroups' => !empty($options['creategroups'])], JSON_UNESCAPED_UNICODE),
            'status' => 'pending', 'timemodified' => time()]);
        self::queue((int) $import->id);
    }

    /**
     * Keep the mapping and options while the course of each value of the course column is chosen.
     *
     * @param \stdClass $import Import (draft).
     * @param string[] $mapping One of FIELDS per column.
     * @param array $options Options of the mapping step.
     */
    public static function save_mapping(\stdClass $import, array $mapping, array $options): void {
        global $DB;

        $mapping = array_map(fn($f) => in_array($f, self::FIELDS, true) ? $f : '', array_values($mapping));
        if (!in_array('email', $mapping, true)) {
            throw new \moodle_exception('import:noemail', 'local_oriskill');
        }
        $DB->update_record(self::TABLE, (object) ['id' => $import->id, 'mapping' => json_encode($mapping),
            'options' => json_encode($options, JSON_UNESCAPED_UNICODE), 'timemodified' => time()]);
    }

    /**
     * Values of the course column in the whole file, with how many rows have each and the groups they list.
     *
     * @param \stdClass $import Import (draft, mapping saved).
     * @return array value => ['rows' => int, 'groups' => [name => rows]]
     */
    public static function course_values(\stdClass $import): array {
        $mapping = json_decode((string) $import->mapping, true) ?: [];
        $coursecol = array_search('course', $mapping, true);
        $groupcol = array_search('groups', $mapping, true);
        $file = get_file_storage()->get_file(\context_system::instance()->id, 'local_oriskill', self::FILEAREA, $import->id,
            '/', 'import.csv');
        if ($coursecol === false || !$file) {
            return [];
        }
        $values = [];
        $handle = $file->get_content_file_handle();
        fgets($handle);
        while (($line = fgets($handle)) !== false) {
            while (substr_count($line, '"') % 2 === 1 && ($next = fgets($handle)) !== false) {
                $line .= $next;
            }
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv(rtrim(self::to_utf8($line), "\r\n"), $import->delimiter, '"', '');
            $value = trim((string) ($row[$coursecol] ?? ''));
            if (!isset($values[$value]) && count($values) >= self::MAX_COURSES) {
                continue;
            }
            $values[$value]['rows'] = ($values[$value]['rows'] ?? 0) + 1;
            $values[$value]['groups'] = $values[$value]['groups'] ?? [];
            if ($groupcol !== false) {
                foreach (self::split_groups((string) ($row[$groupcol] ?? '')) as $group) {
                    $values[$value]['groups'][$group] = ($values[$value]['groups'][$group] ?? 0) + 1;
                }
            }
        }
        fclose($handle);
        uksort($values, 'strnatcasecmp');
        return $values;
    }

    /**
     * Course of the trainer with the same name as a value, if any (to suggest the mapping).
     *
     * @param array $courses id => course (fullname, shortname).
     * @param string $value Value of the course column.
     * @return int Course id, 0 when none.
     */
    public static function guess_course(array $courses, string $value): int {
        $key = \core_text::strtolower(trim($value));
        foreach ($courses as $course) {
            if ($key !== '' && in_array($key, [\core_text::strtolower(trim(format_string($course->fullname))),
                    \core_text::strtolower(trim($course->shortname))], true)) {
                return (int) $course->id;
            }
        }
        return 0;
    }

    /**
     * Group names of a cell ("Règlement en plusieurs mensualités, certification").
     *
     * @param string $value Cell.
     * @return string[]
     */
    public static function split_groups(string $value): array {
        $groups = array_map(fn($g) => \core_text::substr(trim($g), 0, 254), preg_split('/[,;|]/', $value));
        return array_values(array_unique(array_filter($groups, fn($g) => $g !== '')));
    }

    /**
     * A duration cell ("33h 20min 51s", "1:05:00" or seconds) in seconds.
     *
     * @param string $value Cell.
     * @return int
     */
    public static function duration(string $value): int {
        $value = trim($value);
        if (preg_match('/^\d+$/', $value)) {
            return (int) $value;
        }
        if (preg_match('/^(\d+):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) ($m[3] ?? 0);
        }
        $seconds = 0;
        foreach (['h' => 3600, 'min' => 60, 'm' => 60, 's' => 1] as $unit => $factor) {
            if (preg_match('/(\d+)\s*' . $unit . '(?![a-z])/i', $value, $m)) {
                $seconds += (int) $m[1] * $factor;
                $value = str_replace($m[0], '', $value);
            }
        }
        return $seconds;
    }

    /**
     * Queue the next batch of an import.
     *
     * @param int $importid Import.
     */
    public static function queue(int $importid): void {
        $task = new task\contacts_import();
        $task->set_custom_data(['importid' => $importid]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Stop an import; the rows already imported stay.
     *
     * @param \stdClass $import Import.
     */
    public static function cancel(\stdClass $import): void {
        global $DB;

        if (in_array($import->status, ['draft', 'pending', 'running'], true)) {
            $DB->update_record(self::TABLE, (object) ['id' => $import->id, 'status' => 'cancelled', 'timemodified' => time()]);
            self::delete_file((int) $import->id);
        }
    }

    // ----- Running --------------------------------------------------------------------------------------------------

    /**
     * Import the next rows of an import.
     *
     * @param int $importid Import.
     * @param int $batch Rows to process at most.
     * @return bool Whether rows are left (another batch is needed).
     */
    public static function run(int $importid, int $batch = self::BATCH): bool {
        global $DB;

        $import = $DB->get_record(self::TABLE, ['id' => $importid]);
        if (!$import || !in_array($import->status, ['pending', 'running'], true)) {
            return false;
        }
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $import->creatorid]);
        $file = get_file_storage()->get_file(\context_system::instance()->id, 'local_oriskill', self::FILEAREA, $import->id,
            '/', 'import.csv');
        if (!$creator || !$file) {
            $DB->update_record(self::TABLE, (object) ['id' => $import->id, 'status' => 'failed', 'timemodified' => time()]);
            return false;
        }
        $mapping = json_decode($import->mapping, true);
        $options = json_decode($import->options, true);
        $course = $options['courseid'] ? $DB->get_record('course', ['id' => $options['courseid']]) : null;
        $errors = json_decode((string) $import->errors, true) ?: [];
        $counts = ['processed' => (int) $import->processed, 'created' => (int) $import->created,
            'updated' => (int) $import->updated, 'enrolled' => (int) $import->enrolled, 'failed' => (int) $import->failed];

        $handle = $file->get_content_file_handle();
        if ($import->position > 0) {
            fseek($handle, (int) $import->position);
        } else {
            fgets($handle);
        }
        $done = 0;
        $more = true;
        $position = 0;
        // One transaction per batch: much faster, and a batch cut short (server stopped) is done again as a whole.
        $transaction = $DB->start_delegated_transaction();
        while (true) {
            if ($done >= $batch) {
                // Where the next batch starts, if anything is left.
                $position = ftell($handle);
                $more = fgets($handle) !== false;
                break;
            }
            $line = fgets($handle);
            if ($line === false) {
                $more = false;
                break;
            }
            // A quoted value may hold line breaks: read on until its closing quote.
            while (substr_count($line, '"') % 2 === 1 && ($next = fgets($handle)) !== false) {
                $line .= $next;
            }
            if (trim($line) === '') {
                continue;
            }
            $done++;
            $counts['processed']++;
            $row = str_getcsv(rtrim(self::to_utf8($line), "\r\n"), $import->delimiter, '"', '');
            try {
                $result = self::import_row($creator, $row, $mapping, $options, $course);
                $counts[$result['new'] ? 'created' : 'updated']++;
                $counts['enrolled'] += (int) $result['enrolled'];
            } catch (\Throwable $e) {
                $counts['failed']++;
                if (count($errors) < self::MAX_ERRORS) {
                    $errors[] = ['row' => $counts['processed'] + 1, 'email' => \core_text::substr(trim((string) ($row[
                        array_search('email', $mapping, true)] ?? '')), 0, 100), 'error' => $e->getMessage()];
                }
            }
        }
        fclose($handle);
        $transaction->allow_commit();
        // Stopped by the trainer meanwhile: keep what was done, do not go on.
        $status = $DB->get_field(self::TABLE, 'status', ['id' => $import->id]);
        $finished = !$more || $status === 'cancelled';
        $DB->update_record(self::TABLE, (object) ($counts + ['id' => $import->id, 'position' => $position,
            'status' => $status === 'cancelled' ? 'cancelled' : ($more ? 'running' : 'done'),
            'errors' => $errors ? json_encode($errors, JSON_UNESCAPED_UNICODE) : null, 'timemodified' => time()]));
        if ($finished) {
            self::delete_file((int) $import->id);
            if ($status !== 'cancelled') {
                log_service::add($creator, 'contacts_imported', ['file' => $import->filename, 'created' => $counts['created'],
                    'updated' => $counts['updated'], 'failed' => $counts['failed']]);
            }
        }
        return !$finished;
    }

    /**
     * Import one row.
     *
     * @param \stdClass $creator Trainer.
     * @param array $row Values.
     * @param string[] $mapping Field of each column.
     * @param array $options Options.
     * @param \stdClass|null $course Course to give access to.
     * @return array ['new' => bool, 'enrolled' => bool]
     */
    protected static function import_row(\stdClass $creator, array $row, array $mapping, array $options,
            ?\stdClass $course): array {
        global $DB;

        $data = [];
        $tags = $options['tags'];
        foreach ($mapping as $i => $field) {
            $value = trim((string) ($row[$i] ?? ''));
            if ($field === '' || $value === '') {
                continue;
            }
            if ($field === 'tags') {
                $tags = array_merge($tags, contact_service::parse_tags(str_replace([';', '|'], ',', $value)));
            } else if ($field === 'fullname') {
                [$first, $last] = array_pad(preg_split('/\s+/', $value, 2), 2, '');
                $data['firstname'] = $data['firstname'] ?? $first;
                $data['lastname'] = $data['lastname'] ?? $last;
            } else if ($field === 'consentdirect' || $field === 'consentpartners') {
                $data[$field] = self::yes($value);
            } else if ($field === 'created' || $field === 'lastactivity') {
                $data[$field] = self::date($value);
            } else if ($field === 'timespent') {
                $data['timespent'] = max($data['timespent'] ?? 0, self::duration($value));
            } else if ($field === 'progress') {
                $data['progress'] = min(100, max(0, (float) str_replace([',', '%'], ['.', ''], $value)));
            } else {
                $data[$field] = $value;
            }
        }
        $email = \core_text::strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '' || !validate_email($email)) {
            throw new \moodle_exception('import:bademail', 'local_oriskill');
        }
        $contact = contact_service::upsert($creator, $email, $data, 'import');
        $new = !empty($contact->isnew);
        if (!empty($data['created']) && $data['created'] < $contact->timecreated) {
            $DB->set_field(contact_service::TABLE, 'timecreated', $data['created'], ['id' => $contact->id]);
        }
        if ($tags) {
            contact_service::add_tags($creator, [$contact->id], array_values(array_unique($tags)), true);
        }
        // The course of the row (course column) or of the whole file (option).
        $groupnames = [];
        if (array_key_exists('course', $data) || in_array('course', $mapping, true)) {
            $courseid = (int) ($options['coursemap'][(string) ($data['course'] ?? '')] ?? 0);
            $course = $courseid ? self::course($courseid) : null;
            $groupnames = self::split_groups((string) ($data['groups'] ?? ''));
        }
        $enrolled = false;
        if ($course) {
            $local = strstr($email, '@', true);
            [$user] = access_mail::with_trigger('import', fn() => learning_service::offer_access($course, $email,
                $data['firstname'] ?? '' ?: $local, $data['lastname'] ?? '' ?: '-', 0, $groupnames ? 0 : (int) $options['groupid'],
                !empty($options['sendcredentials'])));
            if (!$contact->userid) {
                $DB->set_field(contact_service::TABLE, 'userid', $user->id, ['id' => $contact->id]);
            }
            foreach ($groupnames as $name) {
                if ($groupid = self::group($course, $name, !empty($options['creategroups']))) {
                    groups_add_member($groupid, $user->id);
                }
            }
            // The start of the access is the sign-up date on the previous platform: modules released J+X follow it.
            // On several rows of the same course, the earliest date wins.
            $current = \availability_enroldays\condition::enrolment_start((int) $course->id, (int) $user->id);
            if (!empty($data['created']) && $data['created'] < ($current ?? time())) {
                learning_service::set_timestart($course, (int) $user->id, $data['created']);
            }
            if (isset($data['state']) && in_array(\core_text::strtolower(\core_text::specialtoascii($data['state'])),
                    ['desactive', 'desactivee', 'inactif', 'inactive', 'disabled', 'suspendu', 'suspended', '0', 'non'], true)) {
                learning_service::set_suspended($course, (int) $user->id, true);
            }
            if (isset($data['timespent']) || isset($data['progress']) || !empty($data['lastactivity'])) {
                self::save_prior((int) $course->id, (int) $user->id, (int) ($data['timespent'] ?? 0),
                    isset($data['progress']) ? (float) $data['progress'] : null, (int) ($data['lastactivity'] ?? 0));
            }
            $enrolled = true;
        }
        return ['new' => $new, 'enrolled' => $enrolled];
    }

    /** @var array Courses read during the import, by id. */
    protected static $courses = [];

    /** @var array Group ids by "courseid|name" during the import. */
    protected static $groups = [];

    /**
     * A course (kept for the rows of the batch).
     *
     * @param int $id Course.
     * @return \stdClass|null
     */
    protected static function course(int $id): ?\stdClass {
        global $DB;
        if (!array_key_exists($id, self::$courses)) {
            self::$courses[$id] = $DB->get_record('course', ['id' => $id]) ?: null;
        }
        return self::$courses[$id];
    }

    /**
     * Group of a course with this name, created when missing (if allowed).
     *
     * @param \stdClass $course Course.
     * @param string $name Name.
     * @param bool $create Create it when missing.
     * @return int Group id, 0 when none.
     */
    protected static function group(\stdClass $course, string $name, bool $create): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');

        $key = $course->id . '|' . \core_text::strtolower($name);
        if (!isset(self::$groups[$key])) {
            $id = (int) $DB->get_field_select('groups', 'id', 'courseid = ? AND ' . $DB->sql_equal('name', '?', false),
                [$course->id, $name], IGNORE_MULTIPLE);
            if (!$id && $create) {
                $id = (int) groups_create_group((object) ['courseid' => $course->id, 'name' => $name]);
            }
            self::$groups[$key] = $id;
        }
        return self::$groups[$key];
    }

    /**
     * Keep the time and progress a learner had in a course on the previous platform (the longest kept).
     *
     * @param int $courseid Course.
     * @param int $userid Learner.
     * @param int $seconds Time spent.
     * @param float|null $progress Progress in %.
     * @param int $lastactivity Last activity there.
     */
    protected static function save_prior(int $courseid, int $userid, int $seconds, ?float $progress, int $lastactivity): void {
        global $DB;

        $row = $DB->get_record(self::PRIOR, ['courseid' => $courseid, 'userid' => $userid]);
        $record = ['courseid' => $courseid, 'userid' => $userid, 'seconds' => max($seconds, (int) ($row->seconds ?? 0)),
            'progress' => $progress === null ? ($row->progress ?? null) : max($progress, (float) ($row->progress ?? 0)),
            'lastactivity' => max($lastactivity, (int) ($row->lastactivity ?? 0)), 'timemodified' => time()];
        if ($row) {
            $DB->update_record(self::PRIOR, (object) (['id' => $row->id] + $record));
        } else {
            $DB->insert_record(self::PRIOR, (object) $record);
        }
    }

    /**
     * Earlier time and progress of a learner in a course, from the previous platform.
     *
     * @param int $courseid Course.
     * @param int $userid Learner.
     * @return \stdClass|null {seconds, progress, lastactivity}
     */
    public static function prior(int $courseid, int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::PRIOR, ['courseid' => $courseid, 'userid' => $userid]) ?: null;
    }

    // ----- Helpers --------------------------------------------------------------------------------------------------

    /**
     * Separator of a CSV file, from its first line.
     *
     * @param string $line First line.
     * @return string
     */
    public static function delimiter(string $line): string {
        $counts = [];
        foreach ([';', ',', "\t", '|'] as $candidate) {
            $counts[$candidate] = count(str_getcsv($line, $candidate, '"', ''));
        }
        arsort($counts);
        return (string) array_key_first($counts);
    }

    /**
     * A line in UTF-8 (files saved by Excel are often in Windows-1252).
     *
     * @param string $text Text.
     * @return string
     */
    public static function to_utf8(string $text): string {
        return mb_check_encoding($text, 'UTF-8') ? $text : \core_text::convert($text, 'windows-1252', 'utf-8');
    }

    /**
     * Whether a cell means "yes".
     *
     * @param string $value Cell.
     * @return bool
     */
    public static function yes(string $value): bool {
        return in_array(\core_text::strtolower(trim($value)), ['1', 'oui', 'yes', 'y', 'o', 'true', 'vrai', 'x', 'on'], true);
    }

    /**
     * A date cell (31/12/2025, 2025-12-31, with or without the time) as a timestamp.
     *
     * @param string $value Cell.
     * @return int 0 when not a date.
     */
    public static function date(string $value): int {
        $value = trim($value);
        if (preg_match('~^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?~', $value, $m)) {
            return make_timestamp((int) $m[3], (int) $m[2], (int) $m[1], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0));
        }
        if (preg_match('~^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?~', $value, $m)) {
            return make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0));
        }
        return 0;
    }

    /**
     * Forget the uploaded file of an import (once finished or stopped).
     *
     * @param int $importid Import.
     */
    protected static function delete_file(int $importid): void {
        get_file_storage()->delete_area_files(\context_system::instance()->id, 'local_oriskill', self::FILEAREA, $importid);
    }

    /**
     * Imports of a trainer, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function imports(\stdClass $creator): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['creatorid' => $creator->id], 'timecreated DESC', '*', 0, 20);
    }
}

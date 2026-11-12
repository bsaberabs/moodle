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
 * Import of a PDF with bookmarks as a new course: chapters become modules, sub-chapters become lessons (pages).
 *
 * The PDF is read with the poppler tools (pdftohtml for the outline, pdftotext for the text, pdfinfo for the page
 * count). The analysis (the plan) and the file wait in a temporary folder of the user between the preview and the
 * creation; the folder is named after the user id and the files after a random token generated here.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_import_service {

    /** Largest PDF accepted, in bytes. */
    const MAXBYTES = 100 * 1024 * 1024;

    /** Seconds a poppler command may run. */
    const TIMEOUT = 60;

    /** Most output read from a command, in bytes. */
    const MAXOUTPUT = 100 * 1024 * 1024;

    /** From more lessons or pages than this, the course is created in the background. */
    const LARGE_LESSONS = 50;
    const LARGE_PAGES = 200;

    /** Most bookmarks kept (a safety net against a broken outline). */
    const MAXITEMS = 1000;

    /** Set by create_course: true when the original PDF could not be added to the general section. */
    public static bool $pdfnotattached = false;

    /** Seconds a leftover temporary file is kept. */
    const KEEP = 86400;

    /**
     * Command of a poppler tool (plugin settings pdfbin_pdftohtml, pdfbin_pdftotext, pdfbin_pdfinfo).
     *
     * @param string $tool Tool name.
     * @return string
     */
    private static function binary(string $tool): string {
        $path = trim((string) get_config('local_oriskill', 'pdfbin_' . $tool));
        return $path !== '' ? $path : '/usr/bin/' . $tool;
    }

    /**
     * Why the PDF tools cannot be used, or null when they can.
     *
     * @return string|null Language string key.
     */
    public static function tools_problem(): ?string {
        if (!function_exists('proc_open')) {
            return 'pdfimport:err:noexec';
        }
        foreach (['pdftohtml', 'pdftotext', 'pdfinfo'] as $tool) {
            if (!is_file(self::binary($tool)) || !is_executable(self::binary($tool))) {
                return 'pdfimport:err:notools';
            }
        }
        return null;
    }

    /**
     * Run a tool and return what it printed.
     *
     * @param string $tool Tool name.
     * @param array $args Arguments (passed as they are, without a shell).
     * @return string Standard output.
     */
    private static function run(string $tool, array $args): string {
        if ($key = self::tools_problem()) {
            throw new \moodle_exception($key, 'local_oriskill');
        }
        // Command as an array: no shell, so proc_terminate stops the tool itself.
        $command = array_merge([self::binary($tool)], array_map('strval', $args));
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new \moodle_exception('pdfimport:err:noexec', 'local_oriskill');
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $deadline = time() + self::TIMEOUT;
        $failed = null;
        while (true) {
            $chunk = (string) fread($pipes[1], 65536);
            $out .= $chunk;
            fread($pipes[2], 65536); // Drained so the command never blocks on it.
            if ($chunk === '' && !proc_get_status($process)['running']) {
                $out .= (string) stream_get_contents($pipes[1]);
                break;
            }
            if (strlen($out) > self::MAXOUTPUT) {
                $failed = 'pdfimport:err:toobig';
                break;
            }
            if (time() > $deadline) {
                $failed = 'pdfimport:err:timeout';
                break;
            }
            if ($chunk === '') {
                usleep(20000);
            }
        }
        if ($failed) {
            proc_terminate($process, 9);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        if ($failed) {
            throw new \moodle_exception($failed, 'local_oriskill');
        }
        return $out;
    }

    /**
     * Read the bookmarks of a PDF and cut it into modules and lessons.
     *
     * @param string $path PDF file.
     * @return \stdClass Plan: pages, modules[] (title, lessons[] (title, start, end)).
     */
    public static function analyse(string $path): \stdClass {
        $pages = 0;
        if (preg_match('/^Pages:\s+(\d+)/m', self::run('pdfinfo', [$path]), $m)) {
            $pages = (int) $m[1];
        }
        // The outline is read on the first page only (light); the whole document is read when that gives no outline.
        $xml = '';
        if ($pages > 0) {
            $xml = self::run('pdftohtml', ['-xml', '-i', '-stdout', '-q', '-f', '1', '-l', '1', $path]);
        }
        if ($pages < 1 || strpos($xml, '<outline>') === false || strrpos($xml, '</outline>') === false) {
            $xml = self::run('pdftohtml', ['-xml', '-i', '-stdout', '-q', $path]);
            if ($pages < 1) {
                $pages = substr_count($xml, '<page number=');
            }
        }
        $from = strpos($xml, '<outline>');
        $to = strrpos($xml, '</outline>');
        if ($pages < 1) {
            throw new \moodle_exception('pdfimport:err:unreadable', 'local_oriskill');
        }
        if ($from === false || $to === false) {
            throw new \moodle_exception('pdfimport:err:nobookmarks', 'local_oriskill');
        }
        $items = [];
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML('<?xml version="1.0" encoding="UTF-8"?>' . substr($xml, $from, $to + 10 - $from));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($loaded) {
            self::collect($dom->documentElement, 1, $items);
        }
        $items = array_values(array_filter($items, fn($i) => $i->page >= 1 && $i->page <= $pages && $i->title !== ''));
        if (!$items) {
            throw new \moodle_exception('pdfimport:err:nobookmarks', 'local_oriskill');
        }
        if (count($items) > self::MAXITEMS) {
            throw new \moodle_exception('pdfimport:err:toobig', 'local_oriskill');
        }
        return self::build($items, $pages);
    }

    /**
     * Flatten the outline in document order, keeping the first two levels.
     * In poppler's XML, the children of an item are an outline element that follows it.
     *
     * @param \DOMElement $outline Outline element.
     * @param int $level Level of its items.
     * @param array $items Collected: level, title, page.
     */
    private static function collect(\DOMElement $outline, int $level, array &$items): void {
        $last = null;
        foreach ($outline->childNodes as $node) {
            if (!($node instanceof \DOMElement)) {
                continue;
            }
            if ($node->nodeName === 'item' && $level <= 2) {
                $items[] = (object) ['level' => $level, 'page' => (int) $node->getAttribute('page'),
                    'title' => self::clean_title($node->textContent)];
                $last = count($items) - 1;
            } else if ($node->nodeName === 'outline') {
                $before = count($items);
                self::collect($node, $level + 1, $items);
                // A first-level bookmark without a page takes the page of its first sub-bookmark.
                if ($level === 1 && $last !== null && $items[$last]->page < 1) {
                    for ($k = $before; $k < count($items); $k++) {
                        if ($items[$k]->page >= 1) {
                            $items[$last]->page = $items[$k]->page;
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * A title on one line, without extra spaces, short enough for a section or activity name.
     *
     * @param string $title Raw title.
     * @return string
     */
    public static function clean_title(string $title): string {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        return \core_text::substr($title, 0, 250);
    }

    /**
     * Page ranges of the modules and lessons.
     *
     * A range goes from the page of the bookmark to the page before the next bookmark (the last one goes to the last
     * page); two bookmarks on one page share it. Text before the first bookmark is left out. A chapter with
     * sub-chapters whose own pages come before the first of them gets an "Introduction" lesson; a chapter without any
     * gets one lesson with its own title.
     *
     * @param array $items Level 1 and 2 bookmarks in order.
     * @param int $pages Pages of the PDF.
     * @return \stdClass Plan.
     */
    private static function build(array $items, int $pages): \stdClass {
        $plan = (object) ['pages' => $pages, 'modules' => []];
        $module = null;
        $ownend = 0;
        $finish = function () use (&$module, &$ownend): void {
            if (!$module) {
                return;
            }
            if (!$module->lessons) {
                $module->lessons[] = (object) ['title' => $module->title, 'start' => $module->start, 'end' => $ownend];
            } else if ($module->lessons[0]->start > $module->start) {
                array_unshift($module->lessons, (object) ['title' => get_string('pdfimport:intro', 'local_oriskill'),
                    'start' => $module->start, 'end' => $module->lessons[0]->start - 1]);
            }
            unset($module->start);
        };
        foreach ($items as $i => $item) {
            $next = $items[$i + 1] ?? null;
            $end = $next ? max($item->page, $next->page - 1) : $pages;
            if ($item->level === 1 || !$module) {
                $finish();
                $module = (object) ['title' => $item->title, 'start' => $item->page, 'lessons' => []];
                $plan->modules[] = $module;
                $ownend = $end;
                if ($item->level === 1) {
                    continue;
                }
            }
            $module->lessons[] = (object) ['title' => $item->title, 'start' => $item->page, 'end' => $end];
        }
        $finish();
        return $plan;
    }

    /**
     * Number of pages of a range.
     *
     * @param \stdClass $range Object with start and end.
     * @return int
     */
    public static function page_count(\stdClass $range): int {
        return max(1, $range->end - $range->start + 1);
    }

    /**
     * Whether creating this course should be left to the background.
     *
     * @param \stdClass $plan Plan (the modules and lessons kept).
     * @return bool
     */
    public static function is_large(\stdClass $plan): bool {
        $lessons = $pages = 0;
        foreach ($plan->modules as $module) {
            foreach ($module->lessons as $lesson) {
                $lessons++;
                $pages += self::page_count($lesson);
            }
        }
        return $lessons > self::LARGE_LESSONS || $pages > self::LARGE_PAGES;
    }

    /**
     * HTML of the text of a range of pages.
     *
     * @param string $path PDF file.
     * @param int $start First page.
     * @param int $end Last page.
     * @return string
     */
    public static function lesson_html(string $path, int $start, int $end): string {
        $text = self::run('pdftotext', ['-f', (string) $start, '-l', (string) $end, '-enc', 'UTF-8', $path, '-']);
        $html = self::text_to_html($text);
        return $html !== '' ? $html : self::p(get_string('pdfimport:emptylesson', 'local_oriskill'));
    }

    /**
     * A paragraph of escaped text.
     *
     * @param string $text Plain text.
     * @return string
     */
    private static function p(string $text): string {
        return '<p>' . s($text) . '</p>';
    }

    /**
     * Plain text of a PDF to paragraphs: lines are joined, a word cut at the end of a line is joined back, blank lines
     * and page breaks start a new paragraph. Everything is escaped. Headers and footers are not looked for.
     *
     * @param string $text Text from pdftotext.
     * @return string HTML
     */
    public static function text_to_html(string $text): string {
        $text = str_replace(["\r\n", "\r", "\f"], ["\n", "\n", "\n\n"], \core_text::convert($text, 'UTF-8', 'UTF-8'));
        $html = '';
        foreach (preg_split('/\n\s*\n/', $text) as $block) {
            $paragraph = '';
            foreach (explode("\n", $block) as $line) {
                $line = trim(preg_replace('/\s+/u', ' ', $line));
                if ($line === '') {
                    continue;
                }
                if ($paragraph !== '' && preg_match('/\p{L}-$/u', $paragraph) && preg_match('/^\p{Ll}/u', $line)) {
                    $paragraph = substr($paragraph, 0, -1) . $line;
                } else {
                    $paragraph .= ($paragraph === '' ? '' : ' ') . $line;
                }
            }
            if ($paragraph !== '') {
                $html .= self::p($paragraph);
            }
        }
        return $html;
    }

    /**
     * Temporary folder of a user (created when missing).
     *
     * @param int $userid User.
     * @return string
     */
    public static function temp_dir(int $userid): string {
        return make_temp_directory('local_oriskill_pdfimport/' . $userid);
    }

    /**
     * Path of a temporary file of the user.
     *
     * @param int $userid User.
     * @param string $token Token.
     * @param string $ext Extension (pdf or json).
     * @return string
     */
    public static function temp_file(int $userid, string $token, string $ext): string {
        if (!preg_match('/^[a-zA-Z0-9]{20}$/', $token)) {
            throw new \moodle_exception('pdfimport:err:expired', 'local_oriskill');
        }
        return self::temp_dir($userid) . '/' . $token . '.' . $ext;
    }

    /**
     * Keep an uploaded PDF and its analysis for the preview; old leftovers of the user are deleted.
     *
     * @param int $userid User.
     * @param string $uploaded Uploaded file path.
     * @param string $filename Name of the file as sent.
     * @return string Token.
     */
    public static function stash(int $userid, string $uploaded, string $filename): string {
        $dir = self::temp_dir($userid);
        foreach (glob($dir . '/*') ?: [] as $old) {
            if (!is_file($old)) {
                continue;
            }
            // Leftovers older than a day, and earlier uploads of the user that are not waiting in the queue.
            $queued = is_file(preg_replace('/\.(queued\.json|pdf|json)$/', '', $old) . '.queued.json');
            if (filemtime($old) < time() - self::KEEP || !$queued) {
                @unlink($old);
            }
        }
        $token = random_string(20);
        $pdf = self::temp_file($userid, $token, 'pdf');
        if (!move_uploaded_file($uploaded, $pdf)) {
            throw new \moodle_exception('pdfimport:err:unreadable', 'local_oriskill');
        }
        try {
            $plan = self::analyse($pdf);
        } catch (\Throwable $e) {
            @unlink($pdf);
            throw $e;
        }
        $plan->filename = clean_param($filename, PARAM_FILE) ?: 'document.pdf';
        file_put_contents(self::temp_file($userid, $token, 'json'), json_encode($plan));
        return $token;
    }

    /**
     * Plan kept for a token.
     *
     * @param int $userid User.
     * @param string $token Token.
     * @param bool $queued Read the plan handed to the background task instead of the preview.
     * @return \stdClass
     */
    public static function load(int $userid, string $token, bool $queued = false): \stdClass {
        $json = self::temp_file($userid, $token, $queued ? 'queued.json' : 'json');
        $pdf = self::temp_file($userid, $token, 'pdf');
        if (!is_file($json) || !is_file($pdf) || !($plan = json_decode((string) file_get_contents($json)))) {
            throw new \moodle_exception('pdfimport:err:expired', 'local_oriskill');
        }
        return $plan;
    }

    /**
     * Whether the creation of this upload has already been handed to the background.
     *
     * @param int $userid User.
     * @param string $token Token.
     * @return bool
     */
    public static function is_queued(int $userid, string $token): bool {
        return is_file(self::temp_file($userid, $token, 'queued.json'));
    }

    /**
     * Delete the temporary files of a token.
     *
     * @param int $userid User.
     * @param string $token Token.
     */
    public static function discard(int $userid, string $token): void {
        foreach (['pdf', 'json', 'queued.json'] as $ext) {
            @unlink(self::temp_file($userid, $token, $ext));
        }
    }

    /**
     * Apply the trainer's edits to the plan: titles, and the modules and lessons left out (by position).
     *
     * @param \stdClass $plan Plan as analysed.
     * @param array $mtitle Module titles by position.
     * @param array $mskip Positions of the modules left out (keys).
     * @param array $ltitle Lesson titles by "module_lesson".
     * @param array $lskip "module_lesson" of the lessons left out (keys).
     * @return \stdClass Plan kept (modules without lessons dropped).
     */
    public static function apply_edits(\stdClass $plan, array $mtitle, array $mskip, array $ltitle, array $lskip): \stdClass {
        $kept = (object) ['pages' => $plan->pages, 'filename' => $plan->filename ?? '', 'modules' => []];
        foreach ($plan->modules as $m => $module) {
            if (isset($mskip[$m])) {
                continue;
            }
            $lessons = [];
            foreach ($module->lessons as $l => $lesson) {
                if (isset($lskip["{$m}_{$l}"])) {
                    continue;
                }
                $title = self::clean_title($ltitle["{$m}_{$l}"] ?? '');
                $lessons[] = (object) ['title' => $title !== '' ? $title : $lesson->title, 'start' => $lesson->start,
                    'end' => $lesson->end];
            }
            if ($lessons) {
                $title = self::clean_title($mtitle[$m] ?? '');
                $kept->modules[] = (object) ['title' => $title !== '' ? $title : $module->title, 'lessons' => $lessons];
            }
        }
        return $kept;
    }

    /**
     * Whether a short name is free.
     *
     * @param string $shortname Short name.
     * @return bool
     */
    public static function shortname_free(string $shortname): bool {
        global $DB;
        return !$DB->record_exists_select('course', $DB->sql_equal('shortname', ':s', false, false), ['s' => $shortname]);
    }

    /**
     * Create the course: one module per chapter, one page per lesson, the PDF in the general section. The course is
     * hidden from learners, and the trainer is its teacher. The course is deleted again if a step fails.
     *
     * @param \stdClass $creator Trainer.
     * @param int $userid User who imports (their temporary folder and draft area).
     * @param string $token Token of the stashed PDF.
     * @param \stdClass $plan Plan kept (see apply_edits).
     * @param string $fullname Course name.
     * @param string $shortname Short name.
     * @return int Course id (see $pdfnotattached when the original PDF could not be added).
     */
    public static function create_course(\stdClass $creator, int $userid, string $token, \stdClass $plan, string $fullname,
            string $shortname): int {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/enrol/locallib.php');
        require_once($CFG->libdir . '/resourcelib.php');

        $pdf = self::temp_file($userid, $token, 'pdf');
        if (!is_file($pdf)) {
            throw new \moodle_exception('pdfimport:err:expired', 'local_oriskill');
        }
        if (!$creator->categoryid || !self::shortname_free($shortname)) {
            throw new \moodle_exception('pdfimport:err:shortname', 'local_oriskill');
        }
        self::$pdfnotattached = false;
        \core_php_time_limit::raise(0);
        raise_memory_limit(MEMORY_EXTRA);

        $course = create_course((object) ['fullname' => $fullname, 'shortname' => $shortname,
            'category' => (int) $creator->categoryid, 'format' => 'topics', 'visible' => 0, 'summary' => '',
            'summaryformat' => FORMAT_HTML]);
        try {
            enrol_try_internal_enrol($course->id, (int) $creator->userid, (int) $CFG->creatornewroleid);
            if ((int) $USER->id === (int) $creator->userid) {
                // The new teacher role is not in the capabilities already loaded for this request (or this task).
                reload_all_capabilities();
            }
            course_create_sections_if_missing($course, range(0, count($plan->modules)));
            rebuild_course_cache($course->id, true);
            $modinfo = get_fast_modinfo($course->id);

            course_update_section($course, $modinfo->get_section_info(0),
                ['name' => get_string('pdfimport:general', 'local_oriskill')]);
            self::$pdfnotattached = !self::attach_pdf($course, $pdf, (string) ($plan->filename ?: 'document.pdf'), $userid);

            foreach ($plan->modules as $i => $module) {
                course_update_section($course, $modinfo->get_section_info($i + 1), ['name' => $module->title]);
                foreach ($module->lessons as $lesson) {
                    create_module((object) [
                        'modulename' => 'page',
                        'course' => $course->id,
                        'section' => $i + 1,
                        'visible' => 1,
                        'name' => $lesson->title,
                        'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                        'content' => self::lesson_html($pdf, (int) $lesson->start, (int) $lesson->end),
                        'contentformat' => FORMAT_HTML,
                        'display' => RESOURCELIB_DISPLAY_OPEN,
                        'printintro' => 0,
                        'printlastmodified' => 1,
                        'cmidnumber' => '',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            delete_course($course, false);
            fix_course_sortorder();
            throw $e;
        }
        rebuild_course_cache($course->id, true);
        if (self::$pdfnotattached) {
            log_service::add($creator, 'pdf_attach_failed', ['name' => format_string($fullname)], (int) $course->id);
        }
        log_service::add($creator, 'pdf_imported', ['name' => format_string($fullname), 'modules' => count($plan->modules),
            'lessons' => array_sum(array_map(fn($m) => count($m->lessons), $plan->modules))], (int) $course->id);
        return (int) $course->id;
    }

    /**
     * Add the original PDF as a file in the general section (a failure here does not stop the import, the caller tells the trainer).
     *
     * @param \stdClass $course Course.
     * @param string $pdf PDF file.
     * @param string $filename File name shown.
     * @param int $userid User whose draft area is used.
     * @return bool Whether the PDF was added.
     */
    private static function attach_pdf(\stdClass $course, string $pdf, string $filename, int $userid): bool {
        try {
            $draftid = file_get_unused_draft_itemid();
            get_file_storage()->create_file_from_pathname([
                'contextid' => \context_user::instance($userid)->id, 'component' => 'user', 'filearea' => 'draft',
                'itemid' => $draftid, 'filepath' => '/', 'filename' => $filename], $pdf);
            create_module((object) [
                'modulename' => 'resource', 'course' => $course->id, 'section' => 0, 'visible' => 1,
                'name' => \core_text::substr(preg_replace('/\.pdf$/i', '', $filename), 0, 250),
                'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0], 'files' => $draftid,
                'display' => RESOURCELIB_DISPLAY_AUTO, 'showsize' => 1, 'showtype' => 1, 'showdate' => 0,
                'printintro' => 0, 'cmidnumber' => '',
            ]);
            return true;
        } catch (\Throwable $e) {
            debugging('PDF import: original PDF not attached: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Queue the creation in the background (large PDFs).
     *
     * @param \stdClass $creator Trainer.
     * @param int $userid User who imports.
     * @param string $token Token.
     * @param \stdClass $plan Plan kept.
     * @param string $fullname Course name.
     * @param string $shortname Short name.
     */
    public static function queue(\stdClass $creator, int $userid, string $token, \stdClass $plan, string $fullname,
            string $shortname): void {
        if (self::is_queued($userid, $token)) {
            throw new \moodle_exception('pdfimport:err:queued', 'local_oriskill');
        }
        // The plan of the task is kept apart: the preview stays as it was analysed.
        file_put_contents(self::temp_file($userid, $token, 'queued.json'), json_encode($plan));
        $task = new task\pdf_import();
        $task->set_userid($userid);
        $task->set_custom_data(['creatorid' => (int) $creator->id, 'token' => $token, 'fullname' => $fullname,
            'shortname' => $shortname]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Tell the user by a Moodle message that the course is ready (or why it is not).
     *
     * @param int $userid User.
     * @param string $subjectkey Language string key of the subject.
     * @param string $bodykey Language string key of the message.
     * @param \stdClass|string $a Values.
     * @param \moodle_url|null $url Link.
     */
    public static function notify(int $userid, string $subjectkey, string $bodykey, $a, ?\moodle_url $url = null): void {
        $message = new \core\message\message();
        $message->component = 'moodle';
        $message->name = 'instantmessage';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $userid;
        $message->subject = get_string($subjectkey, 'local_oriskill');
        $message->fullmessage = get_string($bodykey, 'local_oriskill', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . s($message->fullmessage) . '</p>';
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        if ($url) {
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('pdfimport:open', 'local_oriskill');
        }
        message_send($message);
    }
}

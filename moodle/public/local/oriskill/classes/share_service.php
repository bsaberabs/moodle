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
 * Share codes: a trainer gives a code; another trainer who types it gets a copy of a course (its content, without its
 * learners) or of an order form (its texts and settings, as a draft, its offers to link to their own courses).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class share_service {

    /** Table. */
    const TABLE = 'local_oriskill_sharecodes';

    /** What can be shared. */
    const TYPES = ['course', 'orderform'];

    /**
     * Codes of a trainer, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function codes(\stdClass $creator): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['creatorid' => $creator->id], 'timecreated DESC');
    }

    /**
     * Make a code for a course or an order form of the trainer.
     *
     * @param \stdClass $creator Trainer.
     * @param string $type course or orderform.
     * @param int $itemid Course or order form.
     * @return \stdClass
     */
    public static function create(\stdClass $creator, string $type, int $itemid): \stdClass {
        global $DB;

        $own = $type === 'course' ? $DB->record_exists('course', ['id' => $itemid, 'category' => $creator->categoryid])
            : ($type === 'orderform' && $DB->record_exists('local_oriskill_orderforms', ['id' => $itemid, 'creatorid' => $creator->id]));
        if (!$own) {
            throw new \moodle_exception('error:notallowed', 'local_oriskill');
        }
        do {
            $code = strtoupper(implode('-', str_split(random_string(12), 4)));
        } while ($DB->record_exists(self::TABLE, ['code' => $code]));
        $record = (object) ['creatorid' => $creator->id, 'code' => $code, 'itemtype' => $type, 'itemid' => $itemid,
            'enabled' => 1, 'uses' => 0, 'timecreated' => time()];
        $record->id = $DB->insert_record(self::TABLE, $record);
        log_service::add($creator, 'share_created', ['code' => $code]);
        return $record;
    }

    /**
     * Turn a code on or off, or delete it.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Code.
     * @param string $action toggle or delete.
     */
    public static function change(\stdClass $creator, int $id, string $action): void {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
        if ($action === 'delete') {
            $DB->delete_records(self::TABLE, ['id' => $record->id]);
        } else {
            $DB->set_field(self::TABLE, 'enabled', $record->enabled ? 0 : 1, ['id' => $record->id]);
        }
    }

    /**
     * Name of what a code shares.
     *
     * @param \stdClass $record Code.
     * @return string
     */
    public static function item_name(\stdClass $record): string {
        global $DB;

        $name = $record->itemtype === 'course' ? $DB->get_field('course', 'fullname', ['id' => $record->itemid])
            : $DB->get_field('local_oriskill_orderforms', 'title', ['id' => $record->itemid]);
        return $name !== false ? format_string($name) : '?';
    }

    /**
     * Copy what a code shares into a trainer's space.
     *
     * @param \stdClass $creator Trainer who types the code.
     * @param string $code Code.
     * @return array [type, id of the copy]
     */
    public static function import(\stdClass $creator, string $code): array {
        global $DB;

        $code = strtoupper(trim($code));
        $record = $DB->get_record(self::TABLE, ['code' => $code, 'enabled' => 1]);
        if (!$record) {
            throw new \moodle_exception('share:unknown', 'local_oriskill');
        }
        if ($record->itemtype === 'course') {
            $course = $DB->get_record('course', ['id' => $record->itemid]);
            if (!$course) {
                throw new \moodle_exception('share:unknown', 'local_oriskill');
            }
            $newid = course_list::duplicate($creator, $course, false, (int) $creator->categoryid, true);
        } else {
            $form = $DB->get_record('local_oriskill_orderforms', ['id' => $record->itemid]);
            if (!$form) {
                throw new \moodle_exception('share:unknown', 'local_oriskill');
            }
            $newid = self::copy_orderform($creator, $form);
        }
        $DB->execute('UPDATE {' . self::TABLE . '} SET uses = uses + 1 WHERE id = ?', [$record->id]);
        log_service::add($creator, 'share_imported', ['code' => $code]);
        return [$record->itemtype, $newid];
    }

    /**
     * Copy an order form of another trainer: texts and settings as a draft; the offers keep their prices but open no
     * course until the trainer chooses theirs.
     *
     * @param \stdClass $creator Trainer receiving the copy.
     * @param \stdClass $form Order form copied.
     * @return int Id of the copy.
     */
    protected static function copy_orderform(\stdClass $creator, \stdClass $form): int {
        global $DB;

        $copy = clone $form;
        unset($copy->id);
        $copy->creatorid = $creator->id;
        $copy->status = 'draft';
        $copy->domainid = 0;
        $copy->bumpcourseids = '';
        $copy->upsellcourseids = '';
        $base = \core_text::substr($form->slug, 0, 50);
        $copy->slug = $base;
        for ($i = 2; $DB->record_exists('local_oriskill_orderforms', ['creatorid' => $creator->id, 'slug' => $copy->slug]); $i++) {
            $copy->slug = $base . '-' . $i;
        }
        $copy->timecreated = $copy->timemodified = time();
        $copy->id = $DB->insert_record('local_oriskill_orderforms', $copy);
        foreach ($DB->get_records('local_oriskill_formoffers', ['formid' => $form->id], 'sortorder') as $offer) {
            unset($offer->id);
            $offer->formid = $copy->id;
            $offer->courseids = '';
            $offer->groupids = '';
            $offer->actags = '';
            $DB->insert_record('local_oriskill_formoffers', $offer);
        }
        return (int) $copy->id;
    }
}

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
 * Categories of a trainer, common to their courses, order forms and capture pages, to sort and filter their lists.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_service {

    /** Categories, and what is in them. */
    const TABLE = 'local_oriskill_categories';
    const ITEMS = 'local_oriskill_catitems';

    /** Kinds of things a category holds, with the table that says they are the trainer's. */
    const TYPES = ['course' => 'course', 'orderform' => 'local_oriskill_orderforms', 'capturepage' => 'local_oriskill_capturepages'];

    /**
     * Categories of a trainer, by name.
     *
     * @param \stdClass $creator Trainer.
     * @return array id => record
     */
    public static function categories(\stdClass $creator): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['creatorid' => $creator->id], 'name');
    }

    /**
     * Names of the categories, for a select.
     *
     * @param \stdClass $creator Trainer.
     * @return array id => name
     */
    public static function menu(\stdClass $creator): array {
        return array_map(fn($c) => format_string($c->name), self::categories($creator));
    }

    /**
     * Create or rename a category.
     *
     * @param \stdClass $creator Trainer.
     * @param string $name Name.
     * @param int $id Category to rename (0: a new one).
     * @return \stdClass
     */
    public static function save(\stdClass $creator, string $name, int $id = 0): \stdClass {
        global $DB;

        $name = \core_text::substr(trim(clean_param($name, PARAM_TEXT)), 0, 100);
        if ($name === '') {
            throw new \moodle_exception('library:noname', 'local_oriskill');
        }
        if ($id) {
            $record = $DB->get_record(self::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
            $record->name = $name;
            $DB->update_record(self::TABLE, $record);
            return $record;
        }
        $record = (object) ['creatorid' => $creator->id, 'name' => $name, 'timecreated' => time()];
        $record->id = $DB->insert_record(self::TABLE, $record);
        return $record;
    }

    /**
     * Delete a category (what it holds stays, without it).
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Category.
     */
    public static function delete(\stdClass $creator, int $id): void {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
        $DB->delete_records(self::ITEMS, ['categoryid' => $record->id]);
        $DB->delete_records(self::TABLE, ['id' => $record->id]);
    }

    /**
     * What a category holds.
     *
     * @param int $id Category.
     * @return array type => ids
     */
    public static function items(int $id): array {
        global $DB;

        $items = array_fill_keys(array_keys(self::TYPES), []);
        foreach ($DB->get_records(self::ITEMS, ['categoryid' => $id]) as $item) {
            $items[$item->itemtype][] = (int) $item->itemid;
        }
        return $items;
    }

    /**
     * Set what a category holds, only things of the trainer.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Category.
     * @param array $items type => ids
     */
    public static function set_items(\stdClass $creator, int $id, array $items): void {
        global $DB;

        $category = $DB->get_record(self::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
        $DB->delete_records(self::ITEMS, ['categoryid' => $category->id]);
        foreach (self::TYPES as $type => $table) {
            $ids = array_filter(array_map('intval', (array) ($items[$type] ?? [])));
            if (!$ids) {
                continue;
            }
            [$insql, $params] = $DB->get_in_or_equal($ids);
            $owner = $type === 'course' ? 'category' : 'creatorid';
            $ownerid = $type === 'course' ? (int) $creator->categoryid : (int) $creator->id;
            foreach ($DB->get_fieldset_select($table, 'id', "$owner = ? AND id $insql", array_merge([$ownerid], $params)) as $itemid) {
                $DB->insert_record(self::ITEMS, (object) ['categoryid' => $category->id, 'itemtype' => $type,
                    'itemid' => (int) $itemid]);
            }
        }
    }

    /**
     * Ids of a kind of thing in a category, to filter a list.
     *
     * @param int $id Category.
     * @param string $type course, orderform or capturepage.
     * @return int[]
     */
    public static function ids(int $id, string $type): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select(self::ITEMS, 'itemid', 'categoryid = ? AND itemtype = ?', [$id, $type]));
    }

    /**
     * Names of the categories of each thing of a kind.
     *
     * @param \stdClass $creator Trainer.
     * @param string $type course, orderform or capturepage.
     * @return array item id => names
     */
    public static function labels(\stdClass $creator, string $type): array {
        global $DB;

        $labels = [];
        foreach ($DB->get_records_sql("SELECT i.id, i.itemid, c.name FROM {" . self::ITEMS . "} i
                                         JOIN {" . self::TABLE . "} c ON c.id = i.categoryid
                                        WHERE c.creatorid = ? AND i.itemtype = ? ORDER BY c.name", [$creator->id, $type]) as $row) {
            $labels[(int) $row->itemid][] = format_string($row->name);
        }
        return $labels;
    }
}

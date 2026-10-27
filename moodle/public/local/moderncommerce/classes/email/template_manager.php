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
 * Template manager for Modern Commerce Core Email Templates.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\email;

/**
 * CRUD + lookup helper for the email template table.
 */
class template_manager {
    /** @var string Database table that stores the templates. */
    private const TABLE = 'local_moderncommerce_emailtpl';

    /**
     * Get a template by its unique key.
     *
     * @param string $templatekey Unique template identifier.
     * @return \stdClass|false Template object or false if not found.
     */
    public function get_template($templatekey) {
        global $DB;

        return $DB->get_record(self::TABLE, [
            'template_key' => $templatekey,
            'status' => 'active',
        ]);
    }

    /**
     * List templates with optional filtering.
     *
     * @param array $filter Filter criteria.
     * @param string $sort Sort field(s).
     * @param int $limit Limit results (0 = no limit).
     * @return array Array of template objects.
     */
    public function list_templates($filter = [], $sort = 'name', $limit = 0) {
        global $DB;

        $where = [];
        $params = [];

        if (!empty($filter['status'])) {
            $where[] = 'status = ?';
            $params[] = $filter['status'];
        } else {
            $where[] = 'status = ?';
            $params[] = 'active';
        }

        if (!empty($filter['component'])) {
            $where[] = 'component = ?';
            $params[] = $filter['component'];
        }

        if (!empty($filter['search'])) {
            $where[] = '(name LIKE ? OR description LIKE ?)';
            $search = '%' . $DB->sql_like_escape($filter['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $sql = 'SELECT * FROM {' . self::TABLE . '}';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . $sort;

        if ($limit > 0) {
            return $DB->get_records_sql($sql, $params, 0, $limit);
        }

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Create a new template.
     *
     * @param \stdClass $template Template object.
     * @return int Template ID.
     * @throws \moodle_exception If validation fails.
     */
    public function create_template($template) {
        global $DB, $USER;

        // Validate required fields.
        $required = ['name', 'subject', 'body'];
        foreach ($required as $field) {
            if (empty($template->$field)) {
                throw new \moodle_exception('error_missingfield', 'local_moderncommerce', '', $field);
            }
        }

        // Auto-generate template_key from name if not provided.
        if (empty($template->template_key)) {
            $basekey = 'mc_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $template->name));
            $key = $basekey;
            $counter = 1;
            while ($DB->record_exists(self::TABLE, ['template_key' => $key])) {
                $key = $basekey . '_' . $counter++;
            }
            $template->template_key = $key;
        } else if ($DB->record_exists(self::TABLE, ['template_key' => $template->template_key])) {
            throw new \moodle_exception('error_duplicate_key', 'local_moderncommerce');
        }

        // Set component default if not provided.
        if (empty($template->component)) {
            $template->component = 'local_moderncommerce';
        }

        // Extract body text from editor format if needed.
        if (is_array($template->body)) {
            $template->body = $template->body['text'];
        }

        // Validate template syntax.
        $engine = new placeholder_engine();
        $validation = $engine->validate_template($template->subject);
        if (!$validation['valid']) {
            throw new \moodle_exception('invalid_subject', 'local_moderncommerce', '', implode(', ', $validation['errors']));
        }
        $validation = $engine->validate_template($template->body);
        if (!$validation['valid']) {
            throw new \moodle_exception('invalid_body', 'local_moderncommerce', '', implode(', ', $validation['errors']));
        }

        // Set defaults.
        $template->format = $template->format ?? 'html';
        $template->status = $template->status ?? 'active';
        $template->description = $template->description ?? null;
        $template->template_type = $template->template_type ?? null;
        $template->locked = $template->locked ?? 0;
        $template->created_by = $USER->id ?? 0;
        $template->timecreated = time();
        $template->timemodified = time();

        // Store placeholders as JSON if array provided.
        if (is_array($template->placeholders ?? null)) {
            $template->placeholders = json_encode($template->placeholders);
        }

        $template->id = $DB->insert_record(self::TABLE, $template);

        return $template->id;
    }

    /**
     * Update an existing template.
     *
     * @param int $templateid Template ID to update.
     * @param \stdClass $template Template object with updated fields.
     * @return bool True on success.
     * @throws \moodle_exception If validation fails.
     */
    public function update_template($templateid, $template) {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $templateid], '*', MUST_EXIST);

        // Extract body text from editor format if needed.
        if (isset($template->body) && is_array($template->body)) {
            $template->body = $template->body['text'];
        }

        // Merge with existing data (template_key/timecreated/created_by are immutable).
        foreach ($template as $key => $value) {
            if (!in_array($key, ['id', 'legacyid', 'template_key', 'timecreated', 'created_by', 'locked'], true)) {
                $existing->$key = $value;
            }
        }

        // Validate template syntax.
        $engine = new placeholder_engine();
        if (!empty($existing->subject)) {
            $validation = $engine->validate_template($existing->subject);
            if (!$validation['valid']) {
                throw new \moodle_exception('invalid_subject', 'local_moderncommerce', '', implode(', ', $validation['errors']));
            }
        }
        if (!empty($existing->body)) {
            $validation = $engine->validate_template($existing->body);
            if (!$validation['valid']) {
                throw new \moodle_exception('invalid_body', 'local_moderncommerce', '', implode(', ', $validation['errors']));
            }
        }

        $existing->timemodified = time();

        // Store placeholders as JSON if array provided.
        if (is_array($existing->placeholders ?? null)) {
            $existing->placeholders = json_encode($existing->placeholders);
        }

        return $DB->update_record(self::TABLE, $existing);
    }

    /**
     * Delete a template.
     *
     * @param int $templateid Template ID to delete.
     * @return bool True on success.
     */
    public function delete_template($templateid) {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $templateid], '*', MUST_EXIST);
        if (!empty($existing->locked)) {
            throw new \moodle_exception('error_lockedtemplate', 'local_moderncommerce');
        }

        return $DB->delete_records(self::TABLE, ['id' => $templateid]);
    }

    /**
     * Get template by ID.
     *
     * @param int $templateid Template ID.
     * @return \stdClass|false Template object.
     */
    public function get_template_by_id($templateid) {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $templateid]);
    }

    /**
     * Get all templates for a component.
     *
     * @param string $component Component identifier.
     * @return array Array of template objects.
     */
    public function get_component_templates($component) {
        return $this->list_templates(['component' => $component]);
    }

    /**
     * Check if a template exists and is active.
     *
     * @param string $templatekey Template key.
     * @return bool True if template exists and is active.
     */
    public function template_exists($templatekey) {
        global $DB;

        return $DB->record_exists(self::TABLE, [
            'template_key' => $templatekey,
            'status' => 'active',
        ]);
    }
}

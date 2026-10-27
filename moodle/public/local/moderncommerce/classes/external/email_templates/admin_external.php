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
 * Admin web services consumed by the Modern Commerce Core Email Templates React screen.
 *
 * Modern Commerce owns the data, shell, and admin endpoints.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\email_templates;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\email\placeholder_engine;
use local_moderncommerce\email\renderer;
use local_moderncommerce\email\template_manager;

/**
 * Email templates admin webservice methods.
 */
class admin_external extends external_api {
    /** @var string Database table. */
    private const TABLE = 'local_moderncommerce_emailtpl';

    /** @var int Maximum rows per page. */
    private const MAX_PER_PAGE = 100;

    /** @var string[] Valid template statuses. */
    private const STATUSES = ['active', 'inactive'];

    // Get metadata.

    /**
     * Parameters for get_metadata.
     *
     * @return external_function_parameters
     */
    public static function get_metadata_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return the placeholder palette and filter options.
     *
     * @return array
     */
    public static function get_metadata(): array {
        global $DB;

        require_login();
        self::validate_context(context_system::instance());
        require_capability('local/moderncommerce:viewemailtemplates', context_system::instance());

        // Placeholder palette grouped by category.
        $placeholders = [];
        foreach (placeholder_engine::get_available_placeholders() as $category => $items) {
            foreach ($items as $token => $description) {
                $placeholders[] = [
                    'category' => $category,
                    'label' => ucfirst($category),
                    'token' => $token,
                    'description' => $description,
                ];
            }
        }

        // Distinct components present in the table.
        $components = [];
        $records = $DB->get_records_sql(
            'SELECT DISTINCT component FROM {' . self::TABLE . '} ORDER BY component'
        );
        foreach ($records as $record) {
            if ($record->component !== null && $record->component !== '') {
                $components[] = [
                    'value' => $record->component,
                    'label' => $record->component,
                    'installed' => self::component_installed($record->component),
                ];
            }
        }

        // Template types are the canonical notification categories (single source of truth),
        // so the picker is a defined select rather than free text.
        $types = \local_moderncommerce\notifications\local\category_registry::options();

        $statuses = [
            ['value' => 'active', 'label' => get_string('status_active', 'local_moderncommerce')],
            ['value' => 'inactive', 'label' => get_string('status_inactive', 'local_moderncommerce')],
        ];

        return [
            'placeholders' => $placeholders,
            'components' => $components,
            'types' => $types,
            'statuses' => $statuses,
            'warnings' => [],
        ];
    }

    /**
     * Returns for get_metadata.
     *
     * @return external_single_structure
     */
    public static function get_metadata_returns(): external_single_structure {
        return new external_single_structure([
            'placeholders' => new external_multiple_structure(new external_single_structure([
                'category' => new external_value(PARAM_ALPHANUMEXT, 'Placeholder category key.'),
                'label' => new external_value(PARAM_TEXT, 'Category label.'),
                'token' => new external_value(PARAM_RAW, 'Placeholder token, e.g. {firstname}.'),
                'description' => new external_value(PARAM_TEXT, 'Placeholder description.'),
            ])),
            'components' => new external_multiple_structure(new external_single_structure([
                'value' => new external_value(PARAM_RAW, 'Component value.'),
                'label' => new external_value(PARAM_TEXT, 'Component label.'),
                'installed' => new external_value(PARAM_BOOL, 'Whether the owning add-on is installed.'),
            ])),
            'types' => new external_multiple_structure(new external_single_structure([
                'value' => new external_value(PARAM_RAW, 'Type value.'),
                'label' => new external_value(PARAM_TEXT, 'Type label.'),
            ])),
            'statuses' => new external_multiple_structure(new external_single_structure([
                'value' => new external_value(PARAM_ALPHA, 'Status value.'),
                'label' => new external_value(PARAM_TEXT, 'Status label.'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Whether the add-on that owns a template's component is installed.
     *
     * Modern Commerce core is always "installed". Templates owned by an absent add-on
     * (e.g. local_moderncoursereminder) are shown read-only as a teaser, never editable.
     *
     * @param string $component Owning component frankenstyle.
     * @return bool
     */
    private static function component_installed(string $component): bool {
        if ($component === '' || $component === 'local_moderncommerce') {
            return true;
        }
        $info = \core_plugin_manager::instance()->get_plugin_info($component);
        return $info !== null && $info->is_installed_and_upgraded();
    }

    // List templates.

    /**
     * Parameters for list_templates.
     *
     * @return external_function_parameters
     */
    public static function list_templates_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search text.', VALUE_DEFAULT, ''),
            'component' => new external_value(PARAM_RAW, 'Component filter, or empty.', VALUE_DEFAULT, ''),
            'type' => new external_value(PARAM_TEXT, 'Template type filter, or empty.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHA, 'Status filter (active/inactive), or empty for all.', VALUE_DEFAULT, ''),
            'sort' => new external_value(PARAM_ALPHAEXT, 'newest, oldest, name_asc, name_desc.', VALUE_DEFAULT, 'name_asc'),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * List templates with filters and pagination.
     *
     * @param string $search Search text.
     * @param string $component Component filter.
     * @param string $type Type filter.
     * @param string $status Status filter.
     * @param string $sort Sort key.
     * @param int $page Zero-based page.
     * @param int $perpage Rows per page.
     * @return array
     */
    public static function list_templates(
        string $search = '',
        string $component = '',
        string $type = '',
        string $status = '',
        string $sort = 'name_asc',
        int $page = 0,
        int $perpage = 10
    ): array {
        global $DB;

        $params = self::validate_parameters(self::list_templates_parameters(), [
            'search' => $search,
            'component' => $component,
            'type' => $type,
            'status' => $status,
            'sort' => $sort,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        require_login();
        self::validate_context(context_system::instance());
        require_capability('local/moderncommerce:viewemailtemplates', context_system::instance());

        $page = max(0, (int) $params['page']);
        $perpage = max(1, min(self::MAX_PER_PAGE, (int) $params['perpage']));

        $where = ['1 = 1'];
        $sqlparams = [];

        if ($params['search'] !== '') {
            $likes = [];
            foreach (['name', 'template_key', 'description', 'subject'] as $col) {
                $likes[] = $DB->sql_like($col, ':s' . $col, false);
                $sqlparams['s' . $col] = '%' . $DB->sql_like_escape($params['search']) . '%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }
        if ($params['component'] !== '') {
            $where[] = 'component = :component';
            $sqlparams['component'] = $params['component'];
        }
        if ($params['type'] !== '') {
            $where[] = 'template_type = :ttype';
            $sqlparams['ttype'] = $params['type'];
        }
        if ($params['status'] !== '' && in_array($params['status'], self::STATUSES, true)) {
            $where[] = 'status = :status';
            $sqlparams['status'] = $params['status'];
        }
        $wheresql = implode(' AND ', $where);

        switch ($params['sort']) {
            case 'newest':
                $order = 'timemodified DESC';
                break;
            case 'oldest':
                $order = 'timemodified ASC';
                break;
            case 'name_desc':
                $order = 'name DESC';
                break;
            default:
                $order = 'name ASC';
                break;
        }

        $total = $DB->count_records_select(self::TABLE, $wheresql, $sqlparams);

        $records = $DB->get_records_sql(
            'SELECT * FROM {' . self::TABLE . '} WHERE ' . $wheresql . ' ORDER BY ' . $order,
            $sqlparams,
            $page * $perpage,
            $perpage
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_row($record);
        }

        // Unfiltered stat tiles.
        $stats = [
            'total' => $DB->count_records(self::TABLE, []),
            'active' => $DB->count_records(self::TABLE, ['status' => 'active']),
            'inactive' => $DB->count_records(self::TABLE, ['status' => 'inactive']),
        ];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'stats' => $stats,
            'warnings' => [],
        ];
    }

    /**
     * Returns for list_templates.
     *
     * @return external_single_structure
     */
    public static function list_templates_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(self::row_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching rows.'),
            'page' => new external_value(PARAM_INT, 'Current page.'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.'),
            'stats' => new external_single_structure([
                'total' => new external_value(PARAM_INT, 'Total templates.'),
                'active' => new external_value(PARAM_INT, 'Active templates.'),
                'inactive' => new external_value(PARAM_INT, 'Inactive templates.'),
            ]),
            'warnings' => new external_warnings(),
        ]);
    }

    // Get template.

    /**
     * Parameters for get_template.
     *
     * @return external_function_parameters
     */
    public static function get_template_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Template id.'),
        ]);
    }

    /**
     * Get a single template.
     *
     * @param int $id Template id.
     * @return array
     */
    public static function get_template(int $id): array {
        global $DB;

        $params = self::validate_parameters(self::get_template_parameters(), ['id' => $id]);

        require_login();
        self::validate_context(context_system::instance());
        require_capability('local/moderncommerce:viewemailtemplates', context_system::instance());

        $record = $DB->get_record(self::TABLE, ['id' => $params['id']], '*', MUST_EXIST);

        return [
            'template' => self::format_template($record),
            'warnings' => [],
        ];
    }

    /**
     * Returns for get_template.
     *
     * @return external_single_structure
     */
    public static function get_template_returns(): external_single_structure {
        return new external_single_structure([
            'template' => self::template_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    // Save template.

    /**
     * Parameters for save_template.
     *
     * @return external_function_parameters
     */
    public static function save_template_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Template id (0 to create).', VALUE_DEFAULT, 0),
            'template_key' => new external_value(PARAM_RAW, 'Unique key (blank to auto-generate on create).', VALUE_DEFAULT, ''),
            'component' => new external_value(PARAM_RAW, 'Owning component.', VALUE_DEFAULT, ''),
            'name' => new external_value(PARAM_TEXT, 'Template name.'),
            'template_type' => new external_value(PARAM_TEXT, 'Template type/category.', VALUE_DEFAULT, ''),
            'description' => new external_value(PARAM_TEXT, 'Description.', VALUE_DEFAULT, ''),
            'subject' => new external_value(PARAM_RAW, 'Email subject.'),
            'body' => new external_value(PARAM_RAW, 'Email body HTML.'),
            'format' => new external_value(PARAM_ALPHA, 'Format (html/text).', VALUE_DEFAULT, 'html'),
            'status' => new external_value(PARAM_ALPHA, 'Status (active/inactive).', VALUE_DEFAULT, 'active'),
            'placeholders' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Placeholder token.'),
                'Placeholders used in this template.',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Create or update a template.
     *
     * @param int $id Template id (0 to create).
     * @param string $templatekey Unique key.
     * @param string $component Owning component.
     * @param string $name Template name.
     * @param string $type Template type.
     * @param string $description Description.
     * @param string $subject Subject.
     * @param string $body Body HTML.
     * @param string $format Format.
     * @param string $status Status.
     * @param array $placeholders Placeholder tokens.
     * @return array
     */
    public static function save_template(
        int $id = 0,
        string $templatekey = '',
        string $component = '',
        string $name = '',
        string $type = '',
        string $description = '',
        string $subject = '',
        string $body = '',
        string $format = 'html',
        string $status = 'active',
        array $placeholders = []
    ): array {
        global $DB;

        $params = self::validate_parameters(self::save_template_parameters(), [
            'id' => $id,
            'template_key' => $templatekey,
            'component' => $component,
            'name' => $name,
            'template_type' => $type,
            'description' => $description,
            'subject' => $subject,
            'body' => $body,
            'format' => $format,
            'status' => $status,
            'placeholders' => $placeholders,
        ]);

        require_login();
        self::validate_context(context_system::instance());
        require_capability('local/moderncommerce:manageemailtemplates', context_system::instance());

        // Block edits to templates owned by an add-on that is not installed (shown read-only as a teaser).
        $effectivecomponent = $params['component'];
        if ((int) $params['id'] > 0) {
            $owner = $DB->get_field(self::TABLE, 'component', ['id' => (int) $params['id']]);
            if ($owner !== false) {
                $effectivecomponent = (string) $owner;
            }
        }
        if (!self::component_installed($effectivecomponent)) {
            throw new \moodle_exception('et_addonlocked', 'local_moderncommerce');
        }

        $status = in_array($params['status'], self::STATUSES, true) ? $params['status'] : 'active';
        $format = $params['format'] === 'text' ? 'text' : 'html';

        $record = (object) [
            'component' => $params['component'],
            'name' => $params['name'],
            'template_type' => \local_moderncommerce\notifications\local\category_registry::normalise($params['template_type']),
            'description' => $params['description'] !== '' ? $params['description'] : null,
            'subject' => $params['subject'],
            'body' => $params['body'],
            'format' => $format,
            'status' => $status,
            'placeholders' => $params['placeholders'],
        ];

        $manager = new template_manager();

        try {
            if ((int) $params['id'] > 0) {
                $existing = $DB->get_record(self::TABLE, ['id' => (int) $params['id']]);
                if ($existing && !empty($existing->locked)) {
                    $record->component = $existing->component;
                    $record->name = $existing->name;
                    $record->template_type = $existing->template_type;
                    $record->format = $existing->format;
                }
                $manager->update_template((int) $params['id'], $record);
                $savedid = (int) $params['id'];
                $message = get_string('template_updated', 'local_moderncommerce', $params['name']);
            } else {
                $record->template_key = trim($params['template_key']);
                $savedid = $manager->create_template($record);
                $message = get_string('template_created', 'local_moderncommerce', $params['name']);
            }
        } catch (\moodle_exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'id' => 0,
                'warnings' => [],
            ];
        }

        return [
            'success' => true,
            'message' => $message,
            'id' => $savedid,
            'warnings' => [],
        ];
    }

    /**
     * Returns for save_template.
     *
     * @return external_single_structure
     */
    public static function save_template_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the save succeeded.'),
            'message' => new external_value(PARAM_RAW, 'Result message.'),
            'id' => new external_value(PARAM_INT, 'Saved template id (0 on failure).'),
            'warnings' => new external_warnings(),
        ]);
    }

    // Delete template.

    /**
     * Parameters for delete_template.
     *
     * @return external_function_parameters
     */
    public static function delete_template_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Template id.'),
        ]);
    }

    /**
     * Delete a template.
     *
     * @param int $id Template id.
     * @return array
     */
    public static function delete_template(int $id): array {
        global $DB;

        $params = self::validate_parameters(self::delete_template_parameters(), ['id' => $id]);

        require_login();
        self::validate_context(context_system::instance());
        require_capability('local/moderncommerce:manageemailtemplates', context_system::instance());

        $record = $DB->get_record(self::TABLE, ['id' => $params['id']], '*', MUST_EXIST);

        if (!self::component_installed((string) $record->component)) {
            throw new \moodle_exception('et_addonlocked', 'local_moderncommerce');
        }

        $manager = new template_manager();
        try {
            $manager->delete_template((int) $params['id']);
            return [
                'success' => true,
                'message' => get_string('template_deleted', 'local_moderncommerce', $record->name),
                'warnings' => [],
            ];
        } catch (\moodle_exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'warnings' => [],
            ];
        }
    }

    /**
     * Returns for delete_template.
     *
     * @return external_single_structure
     */
    public static function delete_template_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the delete succeeded.'),
            'message' => new external_value(PARAM_RAW, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }

    // Clone template.

    /**
     * Parameters for clone_template.
     *
     * @return external_function_parameters
     */
    public static function clone_template_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Template id to clone.'),
        ]);
    }

    /**
     * Clone a template.
     *
     * @param int $id Template id.
     * @return array
     */
    public static function clone_template(int $id): array {
        global $DB;

        $params = self::validate_parameters(self::clone_template_parameters(), ['id' => $id]);

        require_login();
        self::validate_context(context_system::instance());
        require_capability('local/moderncommerce:manageemailtemplates', context_system::instance());

        $record = $DB->get_record(self::TABLE, ['id' => $params['id']], '*', MUST_EXIST);

        if (!self::component_installed((string) $record->component)) {
            throw new \moodle_exception('et_addonlocked', 'local_moderncommerce');
        }

        $clone = clone $record;
        unset($clone->id, $clone->template_key);
        $clone->name = $record->name . ' ' . get_string('clonesuffix', 'local_moderncommerce');
        if (!empty($clone->placeholders)) {
            $decoded = json_decode($clone->placeholders, true);
            $clone->placeholders = is_array($decoded) ? $decoded : [];
        }

        $manager = new template_manager();
        $newid = $manager->create_template($clone);

        return [
            'success' => true,
            'message' => get_string('template_cloned', 'local_moderncommerce', $record->name),
            'id' => $newid,
            'warnings' => [],
        ];
    }

    /**
     * Returns for clone_template.
     *
     * @return external_single_structure
     */
    public static function clone_template_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the clone succeeded.'),
            'message' => new external_value(PARAM_RAW, 'Result message.'),
            'id' => new external_value(PARAM_INT, 'New template id.'),
            'warnings' => new external_warnings(),
        ]);
    }

    // Preview template.

    /**
     * Parameters for preview_template.
     *
     * @return external_function_parameters
     */
    public static function preview_template_parameters(): external_function_parameters {
        return new external_function_parameters([
            'subject' => new external_value(PARAM_RAW, 'Subject to render.', VALUE_DEFAULT, ''),
            'body' => new external_value(PARAM_RAW, 'Body to render.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Render a subject/body preview with sample data.
     *
     * @param string $subject Subject template.
     * @param string $body Body template.
     * @return array
     */
    public static function preview_template(string $subject = '', string $body = ''): array {
        $params = self::validate_parameters(self::preview_template_parameters(), [
            'subject' => $subject,
            'body' => $body,
        ]);

        require_login();
        self::validate_context(context_system::instance());
        require_capability('local/moderncommerce:viewemailtemplates', context_system::instance());

        $sample = self::sample_data();
        $rendered = renderer::render_subject_body($params['subject'], $params['body'], $sample);

        return [
            'subject' => $rendered['subject'],
            'body' => $rendered['html'],
            'warnings' => [],
        ];
    }

    /**
     * Returns for preview_template.
     *
     * @return external_single_structure
     */
    public static function preview_template_returns(): external_single_structure {
        return new external_single_structure([
            'subject' => new external_value(PARAM_RAW, 'Rendered subject.'),
            'body' => new external_value(PARAM_RAW, 'Rendered body HTML.'),
            'warnings' => new external_warnings(),
        ]);
    }

    // Shell management.

    /**
     * Parameters for get_shell.
     *
     * @return external_function_parameters
     */
    public static function get_shell_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get the active raw shell HTML.
     *
     * @return array
     */
    public static function get_shell(): array {
        require_login();
        self::validate_context(context_system::instance());
        require_capability('local/moderncommerce:viewemailtemplates', context_system::instance());

        return [
            'shell' => renderer::get_shell_html(),
            'defaultshell' => renderer::default_shell(),
            'warnings' => [],
        ];
    }

    /**
     * Returns for get_shell.
     *
     * @return external_single_structure
     */
    public static function get_shell_returns(): external_single_structure {
        return new external_single_structure([
            'shell' => new external_value(PARAM_RAW, 'Active shell HTML.'),
            'defaultshell' => new external_value(PARAM_RAW, 'Bundled default shell HTML.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for save_shell.
     *
     * @return external_function_parameters
     */
    public static function save_shell_parameters(): external_function_parameters {
        return new external_function_parameters([
            'shell' => new external_value(PARAM_RAW, 'Raw shell HTML containing {content_html}.'),
        ]);
    }

    /**
     * Save the active raw shell HTML.
     *
     * @param string $shell Shell HTML.
     * @return array
     */
    public static function save_shell(string $shell): array {
        $params = self::validate_parameters(self::save_shell_parameters(), ['shell' => $shell]);

        require_login();
        self::validate_context(context_system::instance());
        require_capability('local/moderncommerce:manageemailtemplates', context_system::instance());

        try {
            renderer::save_shell_html($params['shell']);
        } catch (\moodle_exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'shell' => renderer::get_shell_html(),
                'warnings' => [],
            ];
        }

        return [
            'success' => true,
            'message' => get_string('emailshellsaved', 'local_moderncommerce'),
            'shell' => renderer::get_shell_html(),
            'warnings' => [],
        ];
    }

    /**
     * Returns for save_shell/reset_shell.
     *
     * @return external_single_structure
     */
    public static function save_shell_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the save succeeded.'),
            'message' => new external_value(PARAM_RAW, 'Result message.'),
            'shell' => new external_value(PARAM_RAW, 'Active shell HTML.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for preview_shell.
     *
     * @return external_function_parameters
     */
    public static function preview_shell_parameters(): external_function_parameters {
        return new external_function_parameters([
            'shell' => new external_value(PARAM_RAW, 'Raw shell HTML.', VALUE_DEFAULT, ''),
            'content' => new external_value(PARAM_RAW, 'Preview content HTML.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Preview raw shell HTML with sample content.
     *
     * @param string $shell Shell HTML.
     * @param string $content Preview content.
     * @return array
     */
    public static function preview_shell(string $shell = '', string $content = ''): array {
        $params = self::validate_parameters(self::preview_shell_parameters(), [
            'shell' => $shell,
            'content' => $content,
        ]);

        require_login();
        self::validate_context(context_system::instance());
        require_capability('local/moderncommerce:viewemailtemplates', context_system::instance());

        $sample = self::sample_data();
        $content = trim($params['content']) !== ''
            ? $params['content']
            : '<h2>Hi {firstname},</h2><p>This is a preview of your Modern Commerce email shell.</p>'
                . '<p><a class="mc-button" href="{siteurl}">Open site</a></p>';
        $rendered = renderer::render_subject_body(
            'Shell preview',
            $content,
            $sample,
            ['shell' => $params['shell'] !== '' ? $params['shell'] : renderer::get_shell_html(), 'marketing' => true]
        );

        return [
            'body' => $rendered['html'],
            'warnings' => [],
        ];
    }

    /**
     * Returns for preview_shell.
     *
     * @return external_single_structure
     */
    public static function preview_shell_returns(): external_single_structure {
        return new external_single_structure([
            'body' => new external_value(PARAM_RAW, 'Rendered shell preview HTML.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for reset_shell.
     *
     * @return external_function_parameters
     */
    public static function reset_shell_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Reset the shell to the bundled default.
     *
     * @return array
     */
    public static function reset_shell(): array {
        require_login();
        self::validate_context(context_system::instance());
        require_capability('local/moderncommerce:manageemailtemplates', context_system::instance());

        renderer::reset_shell_html();

        return [
            'success' => true,
            'message' => get_string('emailshellreset', 'local_moderncommerce'),
            'shell' => renderer::get_shell_html(),
            'warnings' => [],
        ];
    }

    /**
     * Returns for reset_shell.
     *
     * @return external_single_structure
     */
    public static function reset_shell_returns(): external_single_structure {
        return self::save_shell_returns();
    }

    // Helpers.

    /**
     * Structure for a list row.
     *
     * @return external_single_structure
     */
    private static function row_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Template id.'),
            'template_key' => new external_value(PARAM_RAW, 'Unique key.'),
            'component' => new external_value(PARAM_RAW, 'Owning component.'),
            'name' => new external_value(PARAM_TEXT, 'Template name.'),
            'template_type' => new external_value(PARAM_TEXT, 'Template type.'),
            'status' => new external_value(PARAM_ALPHA, 'Status.'),
            'locked' => new external_value(PARAM_BOOL, 'Whether this bundled template is protected.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'timemodified' => new external_value(PARAM_INT, 'Modified timestamp.'),
        ]);
    }

    /**
     * Structure for a full template record.
     *
     * @return external_single_structure
     */
    private static function template_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Template id.'),
            'template_key' => new external_value(PARAM_RAW, 'Unique key.'),
            'component' => new external_value(PARAM_RAW, 'Owning component.'),
            'name' => new external_value(PARAM_TEXT, 'Template name.'),
            'template_type' => new external_value(PARAM_TEXT, 'Template type.'),
            'description' => new external_value(PARAM_RAW, 'Description.'),
            'subject' => new external_value(PARAM_RAW, 'Subject.'),
            'body' => new external_value(PARAM_RAW, 'Body HTML.'),
            'format' => new external_value(PARAM_ALPHA, 'Format.'),
            'status' => new external_value(PARAM_ALPHA, 'Status.'),
            'locked' => new external_value(PARAM_BOOL, 'Whether this bundled template is protected.'),
            'placeholders' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Placeholder token.')
            ),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'timemodified' => new external_value(PARAM_INT, 'Modified timestamp.'),
        ]);
    }

    /**
     * Format a DB record into a list row array.
     *
     * @param \stdClass $record Template record.
     * @return array
     */
    private static function format_row($record): array {
        return [
            'id' => (int) $record->id,
            'template_key' => (string) $record->template_key,
            'component' => (string) $record->component,
            'name' => (string) $record->name,
            'template_type' => (string) ($record->template_type ?? ''),
            'status' => (string) $record->status,
            'locked' => !empty($record->locked),
            'timecreated' => (int) $record->timecreated,
            'timemodified' => (int) $record->timemodified,
        ];
    }

    /**
     * Format a DB record into a full template array.
     *
     * @param \stdClass $record Template record.
     * @return array
     */
    private static function format_template($record): array {
        $placeholders = [];
        if (!empty($record->placeholders)) {
            $decoded = json_decode($record->placeholders, true);
            if (is_array($decoded)) {
                $placeholders = array_values(array_map('strval', $decoded));
            }
        }

        return [
            'id' => (int) $record->id,
            'template_key' => (string) $record->template_key,
            'component' => (string) $record->component,
            'name' => (string) $record->name,
            'template_type' => (string) ($record->template_type ?? ''),
            'description' => (string) ($record->description ?? ''),
            'subject' => (string) $record->subject,
            'body' => (string) $record->body,
            'format' => (string) $record->format,
            'status' => (string) $record->status,
            'locked' => !empty($record->locked),
            'placeholders' => $placeholders,
            'timecreated' => (int) $record->timecreated,
            'timemodified' => (int) $record->timemodified,
        ];
    }

    /**
     * Sample placeholder data used for the preview.
     *
     * @return array
     */
    private static function sample_data(): array {
        global $CFG, $USER;

        $sample = [
            'firstname' => $USER->firstname ?? 'Jane',
            'lastname' => $USER->lastname ?? 'Doe',
            'fullname' => fullname($USER),
            'email' => $USER->email ?? 'jane.doe@example.com',
            'phone' => '123-456-7890',
            'city' => 'Sample City',
            'country' => 'NG',
            'course_name' => 'Sample Course Name',
            'course_code' => 'SC101',
            'course_summary' => 'This is a sample course description used in the preview.',
            'course_link' => $CFG->wwwroot . '/course/view.php?id=1',
            'course_startdate' => userdate(time()),
            'course_enddate' => userdate(time() + (90 * DAYSECS)),
            'instructor_name' => 'Dr. John Smith',
            'instructor_email' => 'john.smith@example.com',
            'order_number' => 'ORD-2026-001234',
            'order_date' => userdate(time()),
            'order_status' => 'paid',
            'order_total' => '15,999.00',
            'subtotal' => '15,000.00',
            'discount' => '500.00',
            'tax' => '499.00',
            'currency' => 'USD',
            'payment_method' => 'Stripe',
            'plan_name' => 'Pro Plan',
            'billing_cycle' => 'monthly',
            'trial_days' => 7,
            'subscription_startdate' => userdate(time()),
            'subscription_enddate' => userdate(time() + (30 * DAYSECS)),
            'courses_list' => implode("\n", [
                '- Introduction to Project Management',
                '- Advanced Excel for Data Analysis',
                '- Communication Skills Masterclass',
            ]),
        ];

        return array_merge(placeholder_engine::get_global_placeholder_values(), $sample);
    }
}

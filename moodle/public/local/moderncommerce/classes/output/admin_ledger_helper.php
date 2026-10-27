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

namespace local_moderncommerce\output;

use local_moderncommerce\localisation;

/**
 * Shared admin ledger rendering helpers.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_ledger_helper {
    /** @var int Default records per page. */
    private const DEFAULT_PER_PAGE = 10;

    /** @var int Maximum records per page. */
    private const MAX_PER_PAGE = 100;

    /**
     * Clean per-page request value.
     *
     * @param int $perpage Requested rows per page.
     * @return int Safe rows per page.
     */
    public static function clean_per_page(int $perpage): int {
        $allowed = [25, 50, 100];
        return in_array($perpage, $allowed, true) ? min($perpage, self::MAX_PER_PAGE) : self::DEFAULT_PER_PAGE;
    }

    /**
     * Build metric tiles.
     *
     * @param array $metrics Metric definitions.
     * @return string
     */
    public static function metrics(array $metrics): string {
        $html = \html_writer::start_div('row g-3 mb-4');
        foreach ($metrics as $metric) {
            $icon = $metric['icon'] ?? 'bi-activity';
            $class = $metric['class'] ?? 'primary';
            $html .= \html_writer::start_div('col-12 col-sm-6 col-xl-3');
            $html .= \html_writer::start_div('mc-card h-100');
            $html .= \html_writer::start_div('mc-card-body d-flex align-items-center justify-content-between gap-3');
            $html .= \html_writer::start_div('min-width-0');
            $html .= \html_writer::tag('div', s($metric['label'] ?? ''), ['class' => 'mc-filter-label mb-2']);
            $html .= \html_writer::tag('div', s((string)($metric['value'] ?? '0')), ['class' => 'h4 mb-0 fw-bold']);
            $html .= \html_writer::end_div();
            $html .= \html_writer::tag(
                'span',
                \html_writer::tag('i', '', ['class' => 'bi ' . $icon, 'aria-hidden' => 'true']),
                ['class' => 'metric-icon metric-icon-' . $class]
            );
            $html .= \html_writer::end_div();
            $html .= \html_writer::end_div();
            $html .= \html_writer::end_div();
        }
        $html .= \html_writer::end_div();

        return $html;
    }

    /**
     * Build a compact status badge.
     *
     * @param string $label Badge label.
     * @param string $class Badge class suffix.
     * @param string $icon Optional Bootstrap icon class.
     * @return string
     */
    public static function badge(string $label, string $class = 'neutral', string $icon = ''): string {
        $iconhtml = $icon === '' ? '' : \html_writer::tag('i', '', [
            'class' => 'bi ' . $icon . ' me-1',
            'aria-hidden' => 'true',
        ]);

        return \html_writer::tag(
            'span',
            $iconhtml . s($label),
            ['class' => 'mc-badge mc-badge--' . $class . ' mc-badge--' . $class]
        );
    }

    /**
     * Build a badge for common ledger statuses.
     *
     * @param string|null $status Status.
     * @return string
     */
    public static function status_badge(?string $status): string {
        $status = strtolower((string)($status ?: 'unknown'));
        $class = 'neutral';
        if (in_array($status, ['success', 'processed', 'paid', 'completed'], true)) {
            $class = 'success';
        } else if (in_array($status, ['pending', 'received', 'processing'], true)) {
            $class = 'warning';
        } else if (in_array($status, ['failed', 'error', 'cancelled'], true)) {
            $class = 'danger';
        } else if (in_array($status, ['refunded', 'info'], true)) {
            $class = 'info';
        }

        return self::badge(localisation::status_label($status), $class);
    }

    /**
     * Build a boolean badge.
     *
     * @param bool $value Boolean value.
     * @param string $truelabel Label for true.
     * @param string $falselabel Label for false.
     * @return string
     */
    public static function bool_badge(bool $value, string $truelabel, string $falselabel): string {
        return $value
            ? self::badge($truelabel, 'success', 'bi-check-circle')
            : self::badge($falselabel, 'neutral', 'bi-dash-circle');
    }

    /**
     * Shorten a hash or opaque identifier for table display.
     *
     * @param string|null $value Raw value.
     * @param int $length Display length.
     * @return string
     */
    public static function short_value(?string $value, int $length = 12): string {
        $value = trim((string)$value);
        if ($value === '') {
            return '-';
        }

        return strlen($value) > $length ? substr($value, 0, $length) . '...' : $value;
    }

    /**
     * Return compact JSON preview without raw long payload output.
     *
     * @param string|null $json JSON payload.
     * @param int $limit Maximum display length.
     * @return string
     */
    public static function json_preview(?string $json, int $limit = 140): string {
        $json = trim((string)$json);
        if ($json === '') {
            return '-';
        }

        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $parts = [];
            foreach ($decoded as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $parts[] = $key . '={...}';
                } else {
                    $parts[] = $key . '=' . (string)$value;
                }
                if (count($parts) >= 4) {
                    break;
                }
            }
            $preview = implode(', ', $parts);
        } else {
            $preview = $json;
        }

        return strlen($preview) > $limit ? substr($preview, 0, $limit) . '...' : $preview;
    }

    /**
     * Wrap a table in the standard admin table container.
     *
     * @param string $tablehtml Table HTML.
     * @return string
     */
    public static function table_wrapper(string $tablehtml): string {
        return \html_writer::div($tablehtml, 'mc-table-wrapper table-responsive');
    }

    /**
     * Build a standard empty state.
     *
     * @param string $title Empty state title.
     * @param string $description Empty state description.
     * @param string $icon Bootstrap icon class.
     * @return string
     */
    public static function empty_state(string $title, string $description, string $icon = 'bi-inbox'): string {
        $html = \html_writer::tag(
            'span',
            \html_writer::tag('i', '', ['class' => 'bi ' . $icon, 'aria-hidden' => 'true']),
            ['class' => 'mc-empty__icon']
        );
        $html .= \html_writer::tag('p', s($title), ['class' => 'mc-empty__title']);
        $html .= \html_writer::tag('p', s($description), ['class' => 'mc-empty__desc']);

        return \html_writer::div($html, 'mc-empty');
    }

    /**
     * Build pagination controls.
     *
     * @param \moodle_url $baseurl URL without page param.
     * @param int $page Current page.
     * @param int $totalpages Total pages.
     * @return string
     */
    public static function pagination(\moodle_url $baseurl, int $page, int $totalpages): string {
        if ($totalpages <= 1) {
            return '';
        }

        $html = \html_writer::start_div('mc-pagination mt-3');
        $html .= \html_writer::start_div('mc-pagination-pages');

        $start = max(1, $page - 2);
        $end = min($totalpages, $page + 2);

        if ($page > 1) {
            $previous = clone $baseurl;
            $previous->param('page', $page - 1);
            $html .= \html_writer::link($previous, get_string('previous'), ['class' => 'mc-pagination-prev']);
        }

        for ($i = $start; $i <= $end; $i++) {
            $url = clone $baseurl;
            $url->param('page', $i);
            $attrs = ['class' => 'mc-button mc-pagination-btn', 'data-mc-button' => 'light'];
            if ($i === $page) {
                $attrs['class'] .= ' active';
                $attrs['aria-current'] = 'page';
            }
            $html .= \html_writer::link($url, (string)$i, $attrs);
        }

        if ($page < $totalpages) {
            $next = clone $baseurl;
            $next->param('page', $page + 1);
            $html .= \html_writer::link($next, get_string('next'), ['class' => 'mc-pagination-next']);
        }

        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();

        return $html;
    }

    /**
     * Build select options from a list of values.
     *
     * @param array $values Values.
     * @param string $alllabel Label for the all option.
     * @return array
     */
    public static function options(array $values, string $alllabel): array {
        $options = ['all' => $alllabel];
        foreach ($values as $value) {
            $value = (string)$value;
            if ($value !== '') {
                $options[$value] = ucfirst($value);
            }
        }

        return $options;
    }

    /**
     * Build options from distinct table values.
     *
     * @param string $table Table name.
     * @param string $field Field name.
     * @param string $alllabel Label for all option.
     * @return array
     */
    public static function distinct_options(string $table, string $field, string $alllabel): array {
        global $DB;

        $columns = $DB->get_columns($table);
        if (!isset($columns[$field])) {
            return ['all' => $alllabel];
        }

        $records = $DB->get_records_sql(
            "SELECT DISTINCT {$field} AS valuefield
               FROM {{$table}}
              WHERE {$field} IS NOT NULL AND {$field} <> ''
           ORDER BY {$field} ASC"
        );

        $values = [];
        foreach ($records as $record) {
            $values[] = $record->valuefield;
        }

        return self::options($values, $alllabel);
    }
}

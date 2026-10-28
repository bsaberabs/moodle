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
 * Hook callbacks.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /** @var array|null Meta tags for the page being rendered, set by creator.php. */
    public static ?array $headmeta = null;

    /**
     * Add the showcase description and canonical address to the page head, for search engines.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook Hook.
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        if (self::$headmeta === null) {
            return;
        }
        $hook->add_html(\html_writer::empty_tag('meta', ['name' => 'description', 'content' => self::$headmeta['description']]));
        $hook->add_html(\html_writer::empty_tag('link', ['rel' => 'canonical', 'href' => self::$headmeta['canonical']]));
    }
}

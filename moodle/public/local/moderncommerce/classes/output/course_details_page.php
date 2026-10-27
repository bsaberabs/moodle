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
 * Course details page renderable.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;


use renderable;
use templatable;
use renderer_base;
use moodle_url;
use context_course;

/**
 * Course details page content renderable class.
 */
class course_details_page implements renderable, templatable {
    /** @var object Course record */
    protected $course;

    /** @var int Course ID */
    protected $courseid;

    /**
     * Constructor.
     *
     * @param object $course Course record
     */
    public function __construct(object $course) {
        $this->course = $course;
        $this->courseid = $course->id;
    }

    /**
     * Build section hierarchy from course modules.
     *
     * @return array Section hierarchy data
     */
    protected function build_section_hierarchy(): array {
        global $DB;

        $modinfo = get_fast_modinfo($this->course);
        $sections = $modinfo->get_section_info_all();

        // Get section data to identify subsections and their parents.
        $dbsections = $DB->get_records(
            'course_sections',
            ['course' => $this->courseid],
            'section',
            'id, section, component, sequence, itemid'
        );

        // Get subsection course modules to map parent-child relationships.
        $subsectionmodules = $DB->get_records_sql(
            "SELECT cm.id, cm.section as parentsectionid, cm.instance
             FROM {course_modules} cm
             JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = ? AND m.name = 'subsection'",
            [$this->courseid]
        );

        // Build a map: subsection itemid -> parent section id.
        $subsectionparentmap = [];
        foreach ($subsectionmodules as $cm) {
            $subsectionparentmap[$cm->instance] = $cm->parentsectionid;
        }

        // Build section hierarchy.
        $sectionhierarchy = [];
        $subsectionids = [];

        foreach ($dbsections as $dbsection) {
            if ($dbsection->section == 0) {
                continue;
            }

            $issubsection = ($dbsection->component === 'mod_subsection');

            if ($issubsection) {
                $subsectionids[$dbsection->id] = $dbsection->itemid;
            } else {
                $sectionhierarchy[$dbsection->id] = [
                    'section' => null,
                    'subsections' => [],
                ];
            }
        }

        // Populate section info and assign subsections to their parents.
        foreach ($sections as $section) {
            if ($section->section == 0) {
                continue;
            }

            if (isset($sectionhierarchy[$section->id])) {
                $sectionhierarchy[$section->id]['section'] = $section;
            }

            if (isset($subsectionids[$section->id])) {
                $itemid = $subsectionids[$section->id];
                if (isset($subsectionparentmap[$itemid])) {
                    $parentsectionid = $subsectionparentmap[$itemid];
                    if (isset($sectionhierarchy[$parentsectionid])) {
                        $sectionhierarchy[$parentsectionid]['subsections'][] = $section;
                    }
                }
            }
        }

        return $sectionhierarchy;
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = [
            'courseid' => $this->courseid,
            'coursename' => format_string($this->course->fullname),
            'hassummary' => !empty($this->course->summary),
            'summary' => !empty($this->course->summary)
                ? format_text($this->course->summary, FORMAT_HTML)
                : '',
        ];

        // Build sections.
        $sectionhierarchy = $this->build_section_hierarchy();
        $sections = [];
        $sectioncount = 0;
        $isfirst = true;

        foreach ($sectionhierarchy as $sectiondata) {
            $section = $sectiondata['section'];
            if (!$section) {
                continue;
            }

            $sectioncount++;

            $sectionname = !empty($section->name)
                ? format_string($section->name)
                : get_string('section') . ' ' . $sectioncount;

            $subsectionsdata = [];
            if (!empty($sectiondata['subsections'])) {
                foreach ($sectiondata['subsections'] as $subsection) {
                    $subsectionname = !empty($subsection->name)
                        ? format_string($subsection->name)
                        : get_string('section') . ' ' . $subsection->section;

                    $subsectionsdata[] = [
                        'name' => $subsectionname,
                        'sectionnum' => $subsection->section,
                        'icon' => 'file-text',
                        'completed' => false,
                        'locked' => false,
                    ];
                }
            }

            $sections[] = [
                'id' => $section->id,
                'name' => $sectionname,
                'sectionnum' => $section->section,
                'first' => $isfirst,
                'hassubsections' => !empty($subsectionsdata),
                'subsections' => $subsectionsdata,
            ];

            $isfirst = false;
        }

        $data['hassections'] = !empty($sections);
        $data['sections'] = $sections;
        $data['sectioncount'] = count($sections);

        return $data;
    }
}

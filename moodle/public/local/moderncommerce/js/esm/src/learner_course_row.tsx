// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Shared learner course row for Modern Commerce.
 *
 * The dense, column-aligned row used wherever a learner sees a list of their own
 * courses: My courses, and the dashboard's "Continue learning" list view.
 *
 * Layout is a three-column grid — thumbnail, title + meta, then a right-hand aside
 * holding progress, last-opened and the action. The aside columns are fixed width so
 * the progress tracks share one x-origin down the list and can be compared at a glance.
 *
 * The row is a div, not an anchor: only the title links, stretched over the row via
 * ::after. That keeps the accessible name to the course title rather than the
 * concatenation of every field, and leaves the row free to hold a real action button.
 *
 * @module     local_moderncommerce/learner_course_row
 * @copyright  2026 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Labels} from "./learner_common";
import {clampProgress, formatCount} from "./learner_common";
import {mcClasses} from "./design_system";

/**
 * The course fields this row renders. Declared structurally so each screen can keep
 * its own richer Course type without converting between them.
 */
export type CourseRowCourse = {
    id: number;
    name: string;
    categoryname: string;
    imageurl: string;
    hasimage: boolean;
    progress: number;
    progresslabel: string;
    completed: boolean;
    courseurl: string;
    modulecount: number;
    lastaccesslabel: string;
    sourcelabel: string;
};

const label = (labels: Labels, key: string, fallback = ""): string => labels[key] || fallback || key;

/**
 * Name the action for what pressing it will do, rather than a generic "open".
 *
 * @param course Course being rendered.
 * @param labels Language strings.
 * @returns Button label.
 */
export const courseRowActionLabel = (course: CourseRowCourse, labels: Labels): string => {
    if (course.completed) {
        return label(labels, "reviewcourse", "Review course");
    }

    return clampProgress(course.progress) > 0
        ? label(labels, "resumecourse", "Resume course")
        : label(labels, "startcourse", "Start course");
};

export default function LearnerCourseRow({
    course,
    labels,
}: {
    course: CourseRowCourse;
    labels: Labels;
}) {
    const progress = clampProgress(course.progress);
    const actionLabel = courseRowActionLabel(course, labels);

    return (
        <div className={mcClasses("mc-learner-course-card")} role="listitem">
            {course.hasimage ? (
                <img
                    src={course.imageurl}
                    alt=""
                    className={mcClasses("mc-learner-course-card__thumb")}
                    width={72}
                    height={54}
                    loading="lazy"
                />
            ) : (
                <span className={mcClasses("mc-learner-course-card__thumb d-flex align-items-center justify-content-center")}>
                    <i className="bi bi-play-circle text-muted" aria-hidden="true" />
                </span>
            )}
            <div className={mcClasses("mc-learner-course-card__body")}>
                <a
                    className={mcClasses("mc-learner-course-card__title mc-learner-course-card__link")}
                    href={course.courseurl}
                >
                    {course.name}
                </a>
                <div className={mcClasses("mc-learner-course-card__meta d-flex flex-wrap gap-2")}>
                    {course.categoryname && <span>{course.categoryname}</span>}
                    {course.modulecount > 0 && (
                        <span>{formatCount(course.modulecount)} {label(labels, "includedcourses")}</span>
                    )}
                    {course.sourcelabel && <span>{course.sourcelabel}</span>}
                </div>
            </div>
            <div className={mcClasses("mc-learner-course-card__aside")}>
                <div
                    className={mcClasses("mc-learner-course-card__progress")}
                    role="progressbar"
                    aria-valuenow={progress}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label={label(labels, "progress", "Progress")}
                >
                    <span className={mcClasses("mc-progress-bar-wrap")} aria-hidden="true">
                        <span
                            className={mcClasses(
                                "mc-progress-bar-fill",
                                course.completed && "mc-progress-bar-fill--complete"
                            )}
                            style={{width: `${progress}%`}}
                        />
                    </span>
                    <span className={mcClasses("mc-learner-course-card__percent")}>{course.progresslabel}</span>
                </div>
                {/* Always rendered, even when empty, so the column keeps its width and the
                    progress tracks stay aligned down the list. */}
                <span className={mcClasses("mc-learner-course-card__seen")}>
                    {course.lastaccesslabel
                        ? `${label(labels, "lastopened", "Last opened")}: ${course.lastaccesslabel}`
                        : ""}
                </span>
                <a
                    className={mcClasses("mc-button btn-mc-secondary py-1 px-3 mc-learner-course-card__action")}
                    href={course.courseurl}
                >
                    {actionLabel}
                    <span className="visually-hidden"> &mdash; {course.name}</span>
                </a>
            </div>
        </div>
    );
}

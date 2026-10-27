// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React learner grades page for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_grades
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {badgeClass, callMoodleService, clampProgress, formatCount, Labels} from "./learner_common";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";
import {LearnerStatStrip, LearnerStatTile} from "./learner_stat_tiles";

type GradeCourse = {
    courseid: number;
    fullname: string;
    shortname: string;
    courseurl: string;
    progress: number;
    completedactivities: number;
    totalactivities: number;
    completionenabled: boolean;
    iscomplete: boolean;
    statuslabel: string;
    statusclass: string;
    hasgrade: boolean;
    gradepercentage: number;
    gradelabel: string;
};

type GradeStats = {
    courses: number;
    gradedcourses: number;
    completedcourses: number;
    gradeaverage: number;
    hasgradeaverage: boolean;
};

type GradesResponse = {
    success: boolean;
    message: string;
    courses: GradeCourse[];
    stats: GradeStats;
    urls: {
        fullreport: string;
        courses: string;
    };
};

type LearnerGradesProps = {
    methodName: string;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

const label = (labels: Labels, key: string): string => labels[key] || key;

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}>
                <i className="bi bi-clipboard-check" aria-hidden="true" />
            </span>
            <p className={mcClasses("mc-empty__title")}>{label(labels, "loading")}</p>
        </div>
    );
}

function GradeValue({course}: {course: GradeCourse}) {
    // The grade is a single number, so it is shown as text: a bar alongside it restates
    // the same value in a second visual language without adding information.
    return (
        <span className={mcClasses(course.hasgrade ? "fw-semibold" : "mc-cell-muted")}>
            {course.gradelabel}
        </span>
    );
}

function EmptyGrades({
    labels,
    coursesUrl,
}: {
    labels: Labels;
    coursesUrl: string;
}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}>
                <i className="bi bi-clipboard-check" aria-hidden="true" />
            </span>
            <p className={mcClasses("mc-empty__title")}>{label(labels, "nogrades")}</p>
            <p className={mcClasses("mc-empty__desc")}>
                {label(labels, "nogradesdesc")}
            </p>
            <a href={coursesUrl} className={mcClasses("mc-button btn-mc-primary")}>
                <i className="bi bi-play-circle me-1" aria-hidden="true" />
                {label(labels, "mycourses")}
            </a>
        </div>
    );
}

export default function LearnerGrades({
    methodName,
    labels,
    layout,
}: LearnerGradesProps) {
    useModernCommerceClassSync();

    const [data, setData] = useState<GradesResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");

        callMoodleService<GradesResponse>(methodName, {})
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                }
                return result;
            })
            .catch((caught: Error) => {
                if (!cancelled) {
                    setError(caught.message);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [methodName]);

    const actions = data?.urls.fullreport ? (
        <a className={mcClasses("mc-button btn-mc-secondary")} href={data.urls.fullreport}>
            <i className="bi bi-box-arrow-up-right" aria-hidden="true" />
            {label(labels, "openfullgradereport")}
        </a>
    ) : null;

    const courses = data?.courses ?? [];
    const stats = data?.stats ?? {
        courses: 0,
        gradedcourses: 0,
        completedcourses: 0,
        gradeaverage: 0,
        hasgradeaverage: false,
    };

    return (
        <ModernLearnerLayout
            activeNav="grades"
            title={label(labels, "mygrades")}
            subtitle={label(labels, "mygradesdesc")}
            labels={labels}
            layout={layout}
            actions={actions}
        >
            <div className={mcClasses("mc-learner-grades")}>
                {loading && <LoadingState labels={labels} />}

                {!loading && (error || (data && !data.success)) && (
                    <div className={mcClasses("mc-alert mc-alert--warning")} role="alert">
                        <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{error || data?.message}</div>
                    </div>
                )}

                {!loading && !error && data?.success && courses.length === 0 && (
                    <EmptyGrades labels={labels} coursesUrl={data.urls.courses} />
                )}

                {!loading && !error && data?.success && courses.length > 0 && (
                    <>
                        <LearnerStatStrip>
                            <LearnerStatTile
                                label={label(labels, "enrolledcourses")}
                                value={stats.courses}
                                icon="bi-mortarboard"
                                variant="primary"
                            />
                            <LearnerStatTile
                                label={label(labels, "gradedcourses")}
                                value={stats.gradedcourses}
                                icon="bi-clipboard-check"
                                variant="info"
                            />
                            <LearnerStatTile
                                label={label(labels, "completedcourses")}
                                value={stats.completedcourses}
                                icon="bi-check2-circle"
                                variant="success"
                            />
                            {/* Averaged over graded courses only, so the label names that
                                basis rather than implying an average across every course. */}
                            <LearnerStatTile
                                label={stats.hasgradeaverage
                                    ? `${label(labels, "gradeaverage")} (${formatCount(stats.gradedcourses)})`
                                    : label(labels, "gradeaverage")}
                                value={stats.hasgradeaverage
                                    ? `${formatCount(stats.gradeaverage)}%`
                                    : label(labels, "gradeungraded")}
                                icon="bi-graph-up"
                                variant="warning"
                            />
                        </LearnerStatStrip>

                        <div className={mcClasses("mc-card")}>
                            <div className={mcClasses("mc-card-header")}>
                                <div>
                                    <h2 className={mcClasses("mc-card-title")}>
                                        {label(labels, "gradeoverview")}
                                    </h2>
                                    <p className={mcClasses("mc-card-subtitle mb-0")}>
                                        {label(labels, "gradeoverviewdesc")}
                                    </p>
                                </div>
                            </div>
                            <div className={mcClasses("mc-card-body p-0")}>
                                <div className="table-responsive">
                                    <table className="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col">{label(labels, "course")}</th>
                                                <th scope="col">{label(labels, "progress")}</th>
                                                <th scope="col">{label(labels, "status")}</th>
                                                <th scope="col" className="text-end">
                                                    {label(labels, "gradeaverage")}
                                                </th>
                                                <th scope="col" className="text-end">
                                                    {label(labels, "actions")}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {courses.map((course) => (
                                                <tr key={course.courseid}>
                                                    <td>
                                                        <a className="fw-semibold text-decoration-none" href={course.courseurl}>
                                                            {course.fullname}
                                                        </a>
                                                        <div className={mcClasses("mc-cell-muted small")}>{course.shortname}</div>
                                                    </td>
                                                    <td>
                                                        {/* Completion off means progress is untracked, not zero, so no bar. */}
                                                        {course.completionenabled ? (
                                                            <div
                                                                className={mcClasses("mc-progress-cell")}
                                                                role="img"
                                                                aria-label={`${formatCount(course.progress)}%`}
                                                            >
                                                                <span className={mcClasses("mc-progress-cell__value")}>
                                                                    {formatCount(course.progress)}%
                                                                </span>
                                                                <span
                                                                    className={mcClasses("mc-progress-bar-wrap")}
                                                                    aria-hidden="true"
                                                                >
                                                                    <span
                                                                        className={mcClasses(
                                                                            "mc-progress-bar-fill",
                                                                            course.iscomplete && "mc-progress-bar-fill--complete"
                                                                        )}
                                                                        style={{width: `${clampProgress(course.progress)}%`}}
                                                                    />
                                                                </span>
                                                            </div>
                                                        ) : (
                                                            <span className={mcClasses("mc-cell-muted")}>&mdash;</span>
                                                        )}
                                                    </td>
                                                    <td>
                                                        <span
                                                            className={mcClasses(
                                                                `mc-badge mc-badge--${badgeClass(course.statusclass)}`
                                                            )}
                                                        >
                                                            {course.statuslabel}
                                                        </span>
                                                    </td>
                                                    <td className="text-end">
                                                        <GradeValue course={course} />
                                                    </td>
                                                    <td className="text-end">
                                                        <a
                                                            className={mcClasses("mc-button btn-mc-secondary py-1 px-2")}
                                                            href={course.courseurl}
                                                        >
                                                            {label(labels, "view")}
                                                        </a>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </ModernLearnerLayout>
    );
}

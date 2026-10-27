// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Shared subscription-access sections (course / bundle / category cards) used by
 * the learner subscription page. Kept separate so the subscription page and any
 * other surface can render the "what your subscription unlocks" lists without
 * duplicating markup.
 *
 * @module     local_moderncommerce/subscription_access_sections
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useMemo, useState} from "react";
import {formatCount, Labels} from "./learner_common";
import {mcClasses} from "./design_system";

export type AccessCourse = {
    id: number;
    name: string;
    shortname: string;
    summary: string;
    categoryid: number;
    categoryname: string;
    imageurl: string;
    hasimage: boolean;
    courseurl: string;
    enrollurl: string;
    isenrolled: boolean;
};

export type AccessGroup = {
    id: number;
    name: string;
    producttype: string;
    typelabel: string;
    coursecount: number;
    courses: AccessCourse[];
};

export type SubscriptionAccess = {
    courses: AccessCourse[];
    categories: AccessGroup[];
    bundles: AccessGroup[];
};

type AccessFilter = "all" | "enrolled" | "open";

const pageLocale = () => document.documentElement.lang || undefined;

const normalise = (value: string): string => {
    return value.toLocaleLowerCase(pageLocale());
};

const interpolate = (template: string, values: Record<string, string>): string => {
    return Object.entries(values).reduce((text, [key, value]) => {
        return text.split(`{${key}}`).join(value);
    }, template);
};

const courseMatchesFilter = (course: AccessCourse, filter: AccessFilter): boolean => {
    if (filter === "enrolled") {
        return course.isenrolled;
    }

    if (filter === "open") {
        return !course.isenrolled;
    }

    return true;
};

const courseMatchesSearch = (course: AccessCourse, query: string): boolean => {
    if (!query) {
        return true;
    }

    const haystack = normalise([
        course.name,
        course.shortname,
        course.summary,
        course.categoryname,
    ].filter(Boolean).join(" "));

    return haystack.includes(query);
};

const filterCourses = (courses: AccessCourse[], query: string, filter: AccessFilter): AccessCourse[] => {
    return courses.filter((course) => courseMatchesFilter(course, filter) && courseMatchesSearch(course, query));
};

const filterGroups = (groups: AccessGroup[], query: string, filter: AccessFilter): AccessGroup[] => {
    return groups
        .map((group) => {
            const groupMatches = query
                ? normalise([group.name, group.typelabel, group.producttype].filter(Boolean).join(" ")).includes(query)
                : false;
            const courses = group.courses.filter((course) => {
                return courseMatchesFilter(course, filter) && (groupMatches || courseMatchesSearch(course, query));
            });

            return courses.length > 0 ? {...group, coursecount: courses.length, courses} : null;
        })
        .filter((group): group is AccessGroup => group !== null);
};

const filterAccess = (access: SubscriptionAccess, search: string, filter: AccessFilter): SubscriptionAccess => {
    const query = normalise(search.trim());

    return {
        courses: filterCourses(access.courses, query, filter),
        categories: filterGroups(access.categories, query, filter),
        bundles: filterGroups(access.bundles, query, filter),
    };
};

const countCourses = (access: SubscriptionAccess): number => {
    return access.courses.length
        + access.bundles.reduce((total, group) => total + group.courses.length, 0)
        + access.categories.reduce((total, group) => total + group.courses.length, 0);
};

function CourseRow({course, labels}: {course: AccessCourse; labels: Labels}) {
    return (
        <a className={mcClasses("mc-learner-course-card")} href={course.isenrolled ? course.courseurl : course.enrollurl}>
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
            <span className={mcClasses("mc-learner-course-card__body")}>
                <span className="d-flex align-items-start justify-content-between gap-2">
                    <span className={mcClasses("mc-learner-course-card__title")}>{course.name}</span>
                    <span className={mcClasses(`mc-badge mc-badge--${course.isenrolled ? "success" : "warning"}`)}>
                        {course.isenrolled ? labels.enrolled : labels.openenrollment}
                    </span>
                </span>
                <span className={mcClasses("mc-learner-course-card__meta d-flex flex-wrap gap-2")}>
                    {course.categoryname && <span>{course.categoryname}</span>}
                    {course.shortname && <span>{course.shortname}</span>}
                </span>
                {course.summary && <span className={mcClasses("mc-cell-muted small d-block")}>{course.summary}</span>}
                <span className="visually-hidden">{course.isenrolled ? labels.takecourse : labels.openenrollment}</span>
            </span>
        </a>
    );
}

function CourseGroup({group, labels}: {group: AccessGroup; labels: Labels}) {
    return (
        <section className="mb-3">
            <div className="d-flex align-items-center justify-content-between gap-2 mb-2">
                <div>
                    <h3 className="h6 mb-0">{group.name}</h3>
                    <span className={mcClasses("mc-cell-muted small")}>{group.typelabel}</span>
                </div>
                <span className={mcClasses("mc-badge mc-badge--neutral")}>
                    {formatCount(group.coursecount)} {labels.courses}
                </span>
            </div>
            {group.courses.map((course) => (
                <CourseRow key={course.id} course={course} labels={labels} />
            ))}
        </section>
    );
}

/**
 * Render the direct courses, bundle groups, and category groups a subscription
 * grants. Renders an "included nothing yet" note when there is no access.
 */
export default function SubscriptionAccessSections({
    access,
    labels,
}: {
    access: SubscriptionAccess;
    labels: Labels;
}) {
    const hasAccess = access.courses.length > 0 || access.bundles.length > 0 || access.categories.length > 0;
    const [search, setSearch] = useState("");
    const [filter, setFilter] = useState<AccessFilter>("all");
    const filteredAccess = useMemo(() => filterAccess(access, search, filter), [access, filter, search]);
    const visibleCount = useMemo(() => countCourses(filteredAccess), [filteredAccess]);
    const totalCount = useMemo(() => countCourses(access), [access]);
    const hasActiveFilters = search.trim() !== "" || filter !== "all";

    if (!hasAccess) {
        return (
            <p className={mcClasses("mc-cell-muted")}>{labels.subscriptionnocourses}</p>
        );
    }

    return (
        <div className={mcClasses("mc-subscription-access-sections")}>
            <div className={mcClasses("mc-toolbar mc-learner-access-toolbar mb-3")}>
                <label className={mcClasses("mc-filter-label d-flex flex-column gap-1")} htmlFor="mc-subscription-access-search">
                    <span>{labels.accesssearchlabel}</span>
                    <span className={mcClasses("mc-search")}>
                        <i className="bi bi-search mc-search__icon" aria-hidden="true" />
                        <input
                            id="mc-subscription-access-search"
                            className={mcClasses("mc-search-input")}
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.currentTarget.value)}
                            placeholder={labels.accesssearchplaceholder}
                        />
                    </span>
                </label>
                <label className={mcClasses("mc-filter-label d-flex flex-column gap-1")} htmlFor="mc-subscription-access-filter">
                    <span>{labels.accessfilterlabel}</span>
                    <select
                        id="mc-subscription-access-filter"
                        className={mcClasses("mc-select")}
                        value={filter}
                        onChange={(event) => setFilter(event.currentTarget.value as AccessFilter)}
                    >
                        <option value="all">{labels.accessfilter_all}</option>
                        <option value="enrolled">{labels.accessfilter_enrolled}</option>
                        <option value="open">{labels.accessfilter_open}</option>
                    </select>
                </label>
                {hasActiveFilters && (
                    <button
                        className={mcClasses("mc-button mc-btn-soft align-self-end")}
                        type="button"
                        onClick={() => {
                            setSearch("");
                            setFilter("all");
                        }}
                    >
                        <i className="bi bi-x-lg" aria-hidden="true" />
                        {labels.clearfilter}
                    </button>
                )}
            </div>

            <p className={mcClasses("mc-cell-muted small mb-3")}>
                {interpolate(labels.accessresultscount, {
                    shown: formatCount(visibleCount),
                    total: formatCount(totalCount),
                })}
            </p>

            {visibleCount === 0 && (
                <div className={mcClasses("mc-empty mc-empty--centered")}>
                    <span className={mcClasses("mc-empty__icon")}><i className="bi bi-search" aria-hidden="true" /></span>
                    <p className={mcClasses("mc-empty__title")}>{labels.accessnoresults}</p>
                    <button
                        className={mcClasses("mc-button btn-mc-secondary")}
                        type="button"
                        onClick={() => {
                            setSearch("");
                            setFilter("all");
                        }}
                    >
                        {labels.clearfilter}
                    </button>
                </div>
            )}

            {filteredAccess.courses.length > 0 && (
                <div className="mb-3">
                    <h3 className="h6 mb-2">{labels.directcourses}</h3>
                    {filteredAccess.courses.map((course) => (
                        <CourseRow key={course.id} course={course} labels={labels} />
                    ))}
                </div>
            )}
            {filteredAccess.bundles.length > 0 && (
                <div className="mb-3">
                    <h3 className="h6 mb-2">{labels.bundleaccess}</h3>
                    {filteredAccess.bundles.map((group) => (
                        <CourseGroup key={`bundle-${group.id}`} group={group} labels={labels} />
                    ))}
                </div>
            )}
            {filteredAccess.categories.length > 0 && (
                <div className="mb-3">
                    <h3 className="h6 mb-2">{labels.categoryaccess}</h3>
                    {filteredAccess.categories.map((group) => (
                        <CourseGroup key={`category-${group.id}`} group={group} labels={labels} />
                    ))}
                </div>
            )}
        </div>
    );
}

// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React learner courses page for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_courses
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import type {Dispatch} from "react";
import {callMoodleService, clampProgress, formatCount, Labels} from "./learner_common";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";
import {LearnerStatStrip, LearnerStatTile} from "./learner_stat_tiles";
import LearnerCourseRow from "./learner_course_row";
import {McTablePagination} from "./table_components";

type Course = {
    id: number;
    name: string;
    shortname: string;
    summary: string;
    categoryid: number;
    categoryname: string;
    imageurl: string;
    hasimage: boolean;
    progress: number;
    progresslabel: string;
    completed: boolean;
    status: string;
    statuslabel: string;
    courseurl: string;
    modulecount: number;
    enrolleddate: number;
    enrolleddatelabel: string;
    lastaccess: number;
    lastaccesslabel: string;
    source: string;
    sourcelabel: string;
    producttype: string;
    productname: string;
};

type Category = {
    id: number;
    name: string;
    selected: boolean;
};

type CourseStats = {
    total: number;
    completed: number;
    ongoing: number;
    pastdue: number;
};

type Filters = {
    search: string;
    categoryid: number;
    sort: string;
    page: number;
    perpage: number;
};

type CoursesResponse = {
    success: boolean;
    message: string;
    courses: Course[];
    stats?: CourseStats;
    total: number;
    page: number;
    perpage: number;
    totalpages: number;
    hasprevious: boolean;
    hasnext: boolean;
    filters: {
        search: string;
        categoryid: number;
        sort: string;
    };
    categories: Category[];
    urls: {
        catalog: string;
        courses: string;
    };
};

type LearnerCoursesProps = {
    methodName: string;
    initialFilters: Filters;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

const sortOptions = (labels: Labels) => [
    {value: "recent", label: labels.sortrecent},
    {value: "name", label: labels.sortnameasc},
    {value: "name_desc", label: labels.sortnamedesc},
    {value: "progress", label: labels.sortprogress},
    {value: "lastaccess", label: labels.sortlastaccess},
];

const label = (labels: Labels, key: string, fallback = ""): string => labels[key] || fallback || key;

const syncUrl = (filters: Filters) => {
    const url = new URL(window.location.href);

    if (filters.search) {
        url.searchParams.set("search", filters.search);
    } else {
        url.searchParams.delete("search");
    }

    if (filters.categoryid > 0) {
        url.searchParams.set("category", String(filters.categoryid));
    } else {
        url.searchParams.delete("category");
    }

    if (filters.sort !== "recent") {
        url.searchParams.set("sort", filters.sort);
    } else {
        url.searchParams.delete("sort");
    }

    if (filters.page > 0) {
        url.searchParams.set("page", String(filters.page));
    } else {
        url.searchParams.delete("page");
    }

    window.history.replaceState(null, "", url.toString());
};

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-play-circle" aria-hidden="true" /></span>
            <p className={mcClasses("mc-empty__title")}>{labels.loading}</p>
        </div>
    );
}


function Pagination({
    data,
    labels,
    onPage,
}: {
    data: CoursesResponse;
    labels: Labels;
    onPage: Dispatch<number>;
}) {
    if (data.totalpages <= 1) {
        return null;
    }

    return (
        <McTablePagination
            className="px-3 py-3"
            previousLabel={label(labels, "previous", "Previous")}
            nextLabel={label(labels, "next", "Next")}
            pageLabel={`${formatCount(data.page + 1)} ${labels.of} ${formatCount(data.totalpages)}`}
            previousDisabled={!data.hasprevious}
            nextDisabled={!data.hasnext}
            onPrevious={() => onPage(Math.max(0, data.page - 1))}
            onNext={() => onPage(data.page + 1)}
        />
    );
}

export default function LearnerCourses({
    methodName,
    initialFilters,
    labels,
    layout,
}: LearnerCoursesProps) {
    useModernCommerceClassSync();
    const [filters, setFilters] = useState<Filters>({
        ...initialFilters,
        page: Math.max(0, initialFilters.page || 0),
        perpage: Math.max(1, initialFilters.perpage || 10),
    });
    const [data, setData] = useState<CoursesResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");
        syncUrl(filters);

        callMoodleService<CoursesResponse>(methodName, filters)
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
    }, [filters, methodName]);

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({
            ...current,
            ...changes,
            page: changes.page ?? 0,
        }));
    };

    const catalogUrl = data?.urls.catalog ?? "#";
    const courses = data?.courses ?? [];
    const stats = data?.stats ?? {
        total: data?.total ?? 0,
        completed: 0,
        ongoing: 0,
        pastdue: 0,
    };
    const actions = (
        <a className={mcClasses("mc-button btn-mc-secondary")} href={catalogUrl}>
            <i className="bi bi-grid" aria-hidden="true" />
            {labels.browsecourses}
        </a>
    );

    return (
        <ModernLearnerLayout
            activeNav="courses"
            title={labels.mycourses}
            subtitle={labels.mycoursesdesc}
            labels={labels}
            layout={layout}
            actions={actions}
        >
            <div className={mcClasses("mc-learner-courses")}>
            <LearnerStatStrip>
                <LearnerStatTile
                    label={label(labels, "totalcourses")}
                    value={stats.total}
                    icon="bi-mortarboard"
                    variant="primary"
                />
                <LearnerStatTile
                    label={label(labels, "completedcourses")}
                    value={stats.completed}
                    icon="bi-check2-circle"
                    variant="success"
                />
                <LearnerStatTile
                    label={label(labels, "ongoing")}
                    value={stats.ongoing}
                    icon="bi-graph-up"
                    variant="info"
                />
                <LearnerStatTile
                    label={label(labels, "pastdue")}
                    value={stats.pastdue}
                    icon="bi-exclamation-triangle"
                    variant="warning"
                />
            </LearnerStatStrip>

            <div className={mcClasses("mc-toolbar mb-3")}>
                <div className="row g-2 align-items-center">
                    <div className="col-lg-5">
                        <input
                            type="search"
                            className="form-control"
                            value={filters.search}
                            placeholder={labels.searchcourses}
                            onChange={(event) => updateFilters({search: event.target.value})}
                        />
                    </div>
                    <div className="col-sm-6 col-lg-3">
                        <select
                            className="form-select"
                            value={filters.categoryid}
                            aria-label={labels.category}
                            onChange={(event) => updateFilters({categoryid: Number(event.target.value)})}
                        >
                            <option value={0}>{labels.allcategories}</option>
                            {(data?.categories ?? []).map((category) => (
                                <option key={category.id} value={category.id}>{category.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="col-sm-6 col-lg-3">
                        <select
                            className="form-select"
                            value={filters.sort}
                            aria-label={labels.sortrecent}
                            onChange={(event) => updateFilters({sort: event.target.value})}
                        >
                            {sortOptions(labels).map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </select>
                    </div>
                    <div className="col-lg-1 text-lg-end">
                        <button
                            type="button"
                            className={mcClasses("mc-button btn-mc-secondary w-100")}
                            onClick={() => updateFilters({search: "", categoryid: 0, sort: "recent"})}
                            aria-label={labels.clearsearch}
                            title={labels.clearsearch}
                        >
                            <i className="bi bi-x-lg" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>

            {loading && <LoadingState labels={labels} />}

            {!loading && (error || (data && !data.success)) && (
                <div className={mcClasses("mc-alert mc-alert--warning")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error || data?.message}</div>
                </div>
            )}

            {!loading && !error && data?.success && courses.length === 0 && (
                <div className={mcClasses("mc-empty mc-empty--centered")}>
                    <span className={mcClasses("mc-empty__icon")}><i className="bi bi-play-circle" aria-hidden="true" /></span>
                    <p className={mcClasses("mc-empty__title")}>{labels.nocoursesenrolled}</p>
                    <p className={mcClasses("mc-empty__desc")}>{labels.browsecoursestoenroll}</p>
                    <a className={mcClasses("mc-button btn-mc-primary")} href={catalogUrl}>
                        <i className="bi bi-grid me-1" aria-hidden="true" />
                        {labels.browsecourses}
                    </a>
                </div>
            )}

            {!loading && !error && courses.length > 0 && data && (
                <div className={mcClasses("mc-card")}>
                    <div className={mcClasses("mc-card-header")}>
                        <h2 className={mcClasses("mc-card-title")}>{formatCount(data.total)} {labels.course}</h2>
                    </div>
                    <div className={mcClasses("mc-card-body p-0")}>
                        <div role="list" aria-label={label(labels, "mycourses", "My courses")}>
                            {courses.map((course) => (
                                <LearnerCourseRow key={course.id} course={course} labels={labels} />
                            ))}
                        </div>
                        <Pagination data={data} labels={labels} onPage={(page) => updateFilters({page})} />
                    </div>
                </div>
            )}
            </div>
        </ModernLearnerLayout>
    );
}

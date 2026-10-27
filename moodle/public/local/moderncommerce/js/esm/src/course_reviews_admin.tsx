// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin console for core Modern Commerce course reviews.
 *
 * @module     local_moderncommerce/course_reviews_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactNode} from "react";
import {useEffect, useState} from "react";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {confirmDialog} from "./modal";
import {McTableActionMenu, McTableCard, McTableFooter, McTablePagination} from "./table_components";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type Mode = "overview" | "courses" | "reviews";

type Methods = {
    overview: string;
    listCourses: string;
    listReviews: string;
    reviewAction: string;
};

type Urls = {
    overview: string;
    courses: string;
    reviews: string;
};

type SelectOption = {
    value: string;
    label: string;
};

type Stats = {
    totalreviews: number;
    visiblereviews: number;
    hiddenreviews: number;
    avgrating: number;
    displayavgrating: string;
    totalreactions: number;
    totalcourses: number;
};

type RatingDistribution = {
    stars: number;
    count: number;
    percent: number;
};

type CourseRow = {
    id: number;
    fullname: string;
    shortname: string;
    reviewcount: number;
    visiblecount: number;
    hiddencount: number;
    avgrating: number;
    displayavgrating: string;
    courseurl: string;
    viewurl: string;
    manageurl: string;
};

type ReviewRow = {
    id: number;
    courseid: number;
    coursename: string;
    userid: number;
    username: string;
    userimage: string;
    rating: number;
    displayrating: string;
    ratingclass: string;
    comment: string;
    timecreated: number;
    timemodified: number;
    timeformatted: string;
    hidden: boolean;
    likes: number;
    dislikes: number;
    loves: number;
    userreaction: number;
    courseurl: string;
    reviewsurl: string;
};

type CourseContext = {
    id: number;
    fullname: string;
    shortname: string;
    courseurl: string;
    viewurl: string;
    manageurl: string;
};

type OverviewResponse = {
    stats: Stats;
    ratingdist: RatingDistribution[];
    courses: CourseRow[];
    topreviews: ReviewRow[];
    recentreviews: ReviewRow[];
};

type CourseListResponse = {
    items: CourseRow[];
    total: number;
    page: number;
    perpage: number;
    stats: Stats;
};

type ReviewListResponse = {
    items: ReviewRow[];
    total: number;
    page: number;
    perpage: number;
    course: CourseContext;
    stats: Stats;
    ratingdist: RatingDistribution[];
};

type ActionResponse = {
    success: boolean;
    message: string;
};

type Filters = {
    search: string;
    filter: string;
    page: number;
    perpage: number;
};

type CourseReviewsAdminProps = {
    mode: Mode;
    courseId: number;
    methods: Methods;
    urls: Urls;
    statusOptions: SelectOption[];
    perPageOptions: number[];
    labels: Labels;
};

const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php?sesskey=${encodeURIComponent(M.cfg.sesskey)}&info=${encodeURIComponent(methodName)}`;
    const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify([
            {
                index: 0,
                methodname: methodName,
                args,
            },
        ]),
    });

    if (!response.ok) {
        throw new Error(`${response.status} ${response.statusText}`);
    }

    const payload = await response.json();
    const first = Array.isArray(payload) ? payload[0] : payload;

    if (!first) {
        throw new Error("Empty Moodle service response.");
    }

    if (first.error) {
        const exception = first.exception ?? {};
        throw new Error(exception.message ?? first.message ?? "Moodle service request failed.");
    }

    return (first.data ?? first) as T;
};

const formatCount = (value: number): string => new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);

const badgeVariant = (review: ReviewRow): McBadgeVariant => review.hidden ? "warning" : "success";

export default function CourseReviewsAdmin({
    mode,
    courseId,
    methods,
    urls,
    statusOptions,
    perPageOptions,
    labels,
}: CourseReviewsAdminProps) {
    useModernCommerceClassSync();

    const [overview, setOverview] = useState<OverviewResponse | null>(null);
    const [courses, setCourses] = useState<CourseListResponse | null>(null);
    const [reviews, setReviews] = useState<ReviewListResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [busyId, setBusyId] = useState(0);
    const [reloadToken, setReloadToken] = useState(0);
    const [searchInput, setSearchInput] = useState("");
    const [filters, setFilters] = useState<Filters>({
        search: "",
        filter: "",
        page: 0,
        perpage: 10,
    });

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setFilters((current) => current.search === searchInput ? current : {...current, search: searchInput, page: 0});
        }, 350);

        return () => window.clearTimeout(timer);
    }, [searchInput]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        const run = async() => {
            if (mode === "overview") {
                const result = await callMoodleService<OverviewResponse>(methods.overview, {});
                if (!cancelled) {
                    setOverview(result);
                }
                return;
            }

            if (mode === "courses") {
                const result = await callMoodleService<CourseListResponse>(methods.listCourses, {
                    search: filters.search,
                    page: filters.page,
                    perpage: filters.perpage,
                });
                if (!cancelled) {
                    setCourses(result);
                }
                return;
            }

            const result = await callMoodleService<ReviewListResponse>(methods.listReviews, {
                courseid: courseId,
                filter: filters.filter || "all",
                search: filters.search,
                page: filters.page,
                perpage: filters.perpage,
            });
            if (!cancelled) {
                setReviews(result);
            }
        };

        void run()
            .catch((caught: unknown) => {
                if (!cancelled) {
                    setError(caught instanceof Error ? caught.message : String(caught));
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
    }, [courseId, filters, methods, mode, reloadToken]);

    useEffect(() => {
        const refreshButton = document.getElementById("moderncommerce-course-reviews-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);

        return () => refreshButton?.removeEventListener("click", refresh);
    }, []);

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({...current, ...changes, page: changes.page ?? 0}));
    };

    const runAction = async(review: ReviewRow, action: "hide" | "show" | "delete") => {
        if (action === "delete" && !await confirmDialog({message: labels.confirmdelete, danger: true})) {
            return;
        }

        setBusyId(review.id);
        setError("");

        try {
            const result = await callMoodleService<ActionResponse>(methods.reviewAction, {id: review.id, action});
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setBusyId(0);
        }
    };

    const renderStats = (stats: Stats) => (
        <div className={mcClasses("mc-stat-strip")} aria-label={labels.title}>
            {statTile(labels.totalreviews, stats.totalreviews, "primary", "bi-star")}
            {statTile(labels.visiblereviews, stats.visiblereviews, "success", "bi-eye")}
            {statTile(labels.hiddenreviews, stats.hiddenreviews, "warning", "bi-eye-slash")}
            {statTile(labels.avgrating, stats.displayavgrating, "info", "bi-star-half")}
        </div>
    );

    const statTile = (label: string, value: number | string, variant: string, icon: string) => (
        <article className={mcClasses(`mc-stat-tile mc-stat-tile--${variant}`)} key={label}>
            <i className={`bi ${icon} mc-stat-tile__icon`} aria-hidden="true" />
            <div className={mcClasses("mc-stat-tile__body")}>
                <span className={mcClasses("mc-stat-tile__label")}>{label}</span>
                <strong className={mcClasses("mc-stat-tile__value")}>{typeof value === "number" ? formatCount(value) : value}</strong>
            </div>
            <i className={`bi ${icon} mc-stat-tile__watermark`} aria-hidden="true" />
        </article>
    );

    const renderDistribution = (rows: RatingDistribution[]) => (
        <div className={mcClasses("mc-card mc-card--table-design h-100")}>
            <div className={mcClasses("mc-card-header")}>
                <span className={mcClasses("mc-card-title")}>{labels.ratingdistribution}</span>
            </div>
            <div className={mcClasses("mc-card-body")}>
                {rows.map((row) => (
                    <div className="d-flex align-items-center gap-2 mb-2" key={row.stars}>
                        <span className={mcClasses("mc-cell-nowrap fw-semibold")}>{row.stars} <i className="bi bi-star-fill text-warning" aria-hidden="true" /></span>
                        <div className="progress flex-grow-1" aria-hidden="true" style={{height: "0.5rem"}}>
                            <div className="progress-bar" style={{width: `${row.percent}%`}} />
                        </div>
                        <span className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{formatCount(row.count)} ({row.percent}%)</span>
                    </div>
                ))}
            </div>
        </div>
    );

    const renderOverview = () => {
        if (!overview) {
            return null;
        }

        return (
            <>
                {renderStats(overview.stats)}
                <div className="row g-3 mb-4">
                    <div className="col-12 col-lg-5">{renderDistribution(overview.ratingdist)}</div>
                    <div className="col-12 col-lg-7">{renderCoursesTable(overview.courses, true)}</div>
                </div>
                <div className="row g-3">
                    <div className="col-12 col-xl-6">{renderReviewsCard(labels.topreviews, overview.topreviews)}</div>
                    <div className="col-12 col-xl-6">{renderReviewsCard(labels.recentreviews, overview.recentreviews)}</div>
                </div>
            </>
        );
    };

    const renderCourses = () => {
        if (!courses) {
            return null;
        }

        return (
            <>
                {renderStats(courses.stats)}
                {renderCoursesTable(courses.items, false, renderCourseToolbar(), renderTableFooter(courses.total, courses.items.length))}
            </>
        );
    };

    const renderReviews = () => {
        if (!reviews) {
            return null;
        }

        return (
            <>
                {renderStats(reviews.stats)}
                {reviews.course.id > 0 && (
                    <div className={mcClasses("mc-alert mc-alert--info d-flex flex-wrap justify-content-between align-items-center gap-2")} role="status">
                        <span>{reviews.course.fullname}</span>
                        <a className={mcClasses("mc-button mc-btn-soft")} href={reviews.course.courseurl}>{labels.viewcourse}</a>
                    </div>
                )}
                <div className="row g-3 mb-4">
                    <div className="col-12 col-lg-5">{renderDistribution(reviews.ratingdist)}</div>
                    <div className="col-12 col-lg-7">{renderReviewToolbar()}</div>
                </div>
                {renderReviewsTableCard(reviews.items, renderTableFooter(reviews.total, reviews.items.length))}
            </>
        );
    };

    const renderCourseToolbar = () => (
        <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
            <div className={mcClasses("mc-product-toolbar__search")}>
                <label className={mcClasses("mc-filter-label")} htmlFor="mc-review-course-search">{labels.search}</label>
                <input
                    className={mcClasses("mc-form-control")}
                    id="mc-review-course-search"
                    onChange={(event) => setSearchInput(event.target.value)}
                    placeholder={labels.searchcourses}
                    type="search"
                    value={searchInput}
                />
            </div>
            {renderPerPageSelect()}
        </div>
    );

    const renderReviewToolbar = () => (
        <div className={mcClasses("mc-card mc-card--table-design h-100")}>
            <div className={mcClasses("mc-card-body")}>
                <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar mb-0")}>
                    <div className={mcClasses("mc-product-toolbar__search")}>
                        <label className={mcClasses("mc-filter-label")} htmlFor="mc-review-search">{labels.search}</label>
                        <input
                            className={mcClasses("mc-form-control")}
                            id="mc-review-search"
                            onChange={(event) => setSearchInput(event.target.value)}
                            placeholder={labels.searchreviews}
                            type="search"
                            value={searchInput}
                        />
                    </div>
                    <label className={mcClasses("mc-product-toolbar__field")}>
                        <span className={mcClasses("mc-filter-label")}>{labels.status}</span>
                        <select
                            className={mcClasses("mc-select")}
                            onChange={(event) => updateFilters({filter: event.target.value})}
                            value={filters.filter}
                        >
                            <option value="">{labels.allstatuses}</option>
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </select>
                    </label>
                    {renderPerPageSelect()}
                </div>
            </div>
        </div>
    );

    const renderPerPageSelect = () => (
        <label className={mcClasses("mc-product-toolbar__field mc-product-toolbar__field--small")}>
            <span className={mcClasses("mc-filter-label")}>{labels.perpage}</span>
            <select
                className={mcClasses("mc-select")}
                onChange={(event) => updateFilters({perpage: Number(event.target.value) || 10})}
                value={filters.perpage}
            >
                {perPageOptions.map((option) => (
                    <option key={option} value={option}>{option}</option>
                ))}
            </select>
        </label>
    );

    const renderCoursesTable = (
        rows: CourseRow[],
        compact: boolean,
        controls: ReactNode = null,
        footer: ReactNode = null,
    ) => (
        <McTableCard
            className={mcClasses("h-100")}
            title={<span className={mcClasses("mc-card-title")}>{labels.courseswithreviews}</span>}
            actions={compact ? (
                    <a className={mcClasses("mc-button mc-btn-soft")} href={urls.courses}>
                        {labels.view}
                    </a>
            ) : undefined}
            toolbar={controls ?? undefined}
            footer={footer ?? undefined}
        >
            <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.courseswithreviews}>
                <thead>
                    <tr>
                        <th scope="col">{labels.course}</th>
                        <th scope="col" className="text-end">{labels.totalreviews}</th>
                        <th scope="col" className="text-end">{labels.avgrating}</th>
                        <th scope="col" className="text-end">{labels.hiddenreviews}</th>
                        <th scope="col" className="text-end">{labels.actions}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={5}>
                                <div className={mcClasses("mc-empty mc-empty--centered")}>
                                    <p className={mcClasses("mc-empty__title")}>{labels.nocoursesreviewed}</p>
                                </div>
                            </td>
                        </tr>
                    )}
                    {rows.map((course) => (
                        <tr key={course.id}>
                            <td>
                                <a className="fw-semibold" href={course.manageurl}>{course.fullname}</a>
                                <div className={mcClasses("mc-cell-muted")}>{course.shortname}</div>
                            </td>
                            <td className="text-end">{formatCount(course.reviewcount)}</td>
                            <td className="text-end">{course.displayavgrating}</td>
                            <td className="text-end">{formatCount(course.hiddencount)}</td>
                            <td className="text-end">
                                <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                    <McTableActionMenu
                                        label={`${labels.actions}: ${course.fullname}`}
                                        items={[
                                            {
                                                key: "view",
                                                label: labels.viewreviews,
                                                icon: "bi bi-eye",
                                                href: course.viewurl,
                                            },
                                            {
                                                key: "manage",
                                                label: labels.managereviews,
                                                icon: "bi bi-pencil",
                                                href: course.manageurl,
                                            },
                                        ]}
                                    />
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </McTableCard>
    );

    const renderReviewsCard = (title: string, rows: ReviewRow[]) => (
        <McTableCard
            className={mcClasses("h-100")}
            title={<span className={mcClasses("mc-card-title")}>{title}</span>}
            actions={<a className={mcClasses("mc-button mc-btn-soft")} href={urls.reviews}>{labels.view}</a>}
        >
            {renderReviewsTable(rows, true)}
        </McTableCard>
    );

    const renderReviewsTableCard = (rows: ReviewRow[], footer: ReactNode = null) => (
        <McTableCard
            title={<span className={mcClasses("mc-card-title")}>{labels.moderation}</span>}
            footer={footer ?? undefined}
        >
            {renderReviewsTable(rows)}
        </McTableCard>
    );

    const renderReviewsTable = (rows: ReviewRow[], compact = false) => (
                <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.moderation}>
                    <thead>
                        <tr>
                            <th scope="col">{labels.reviewer}</th>
                            {!compact && <th scope="col">{labels.course}</th>}
                            <th scope="col" className="text-end">{labels.rating}</th>
                            <th scope="col">{labels.comment}</th>
                            {!compact && <th scope="col">{labels.status}</th>}
                            {!compact && <th scope="col" className="text-end">{labels.actions}</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && !loading && (
                            <tr>
                                <td colSpan={compact ? 3 : 6}>
                                    <div className={mcClasses("mc-empty mc-empty--centered")}>
                                        <p className={mcClasses("mc-empty__title")}>{labels.noreviews}</p>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {rows.map((review) => (
                            <tr key={review.id}>
                                <td>
                                    <div className="d-flex align-items-center gap-2">
                                        {review.userimage !== "" && <img alt="" className="rounded-circle" height="32" src={review.userimage} width="32" />}
                                        <div>
                                            <div className="fw-semibold">{review.username}</div>
                                            <div className={mcClasses("mc-cell-muted small")}>{review.timeformatted}</div>
                                        </div>
                                    </div>
                                </td>
                                {!compact && <td><a href={review.reviewsurl}>{review.coursename}</a></td>}
                                <td className="text-end">
                                    <span className={mcClasses("fw-semibold")}>{review.displayrating}</span>
                                    <i className="bi bi-star-fill text-warning ms-1" aria-hidden="true" />
                                </td>
                                <td>
                                    <div>{review.comment}</div>
                                    <div className={mcClasses("mc-cell-muted small")}>
                                        {labels.likes}: {formatCount(review.likes)} · {labels.dislikes}: {formatCount(review.dislikes)} · {labels.loves}: {formatCount(review.loves)}
                                    </div>
                                </td>
                                {!compact && (
                                    <td>
                                        <McBadge variant={badgeVariant(review)} tone="soft" dot>
                                            {review.hidden ? labels.hidden : labels.visible}
                                        </McBadge>
                                    </td>
                                )}
                                {!compact && (
                                    <td className="text-end">
                                        <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                            <McTableActionMenu
                                                label={`${labels.actions}: ${review.username}`}
                                                disabled={busyId === review.id}
                                                items={[
                                                    {
                                                        key: review.hidden ? "show" : "hide",
                                                        label: review.hidden ? labels.show : labels.hide,
                                                        icon: review.hidden ? "bi bi-eye" : "bi bi-eye-slash",
                                                        onClick: () => void runAction(review, review.hidden ? "show" : "hide"),
                                                    },
                                                    {
                                                        key: "delete",
                                                        label: labels.delete,
                                                        icon: "bi bi-trash",
                                                        danger: true,
                                                        onClick: () => void runAction(review, "delete"),
                                                    },
                                                ]}
                                            />
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ))}
                        {loading && (
                            <tr>
                                <td colSpan={compact ? 3 : 6}>
                                    <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
    );

    const renderTableFooter = (total: number, visibleCount: number) => {
        const visibleFrom = total === 0 || visibleCount === 0 ? 0 : (filters.page * filters.perpage) + 1;
        const visibleTo = visibleCount === 0 ? 0 : Math.min(total, (filters.page * filters.perpage) + visibleCount);

        return (
            <McTableFooter
                summary={(
                    <span>
                        {labels.showing} {formatCount(visibleFrom)}-{formatCount(visibleTo)} / {formatCount(total)}
                    </span>
                )}
                pagination={renderPagination(total)}
            />
        );
    };

    const renderPagination = (total: number) => {
        const totalPages = Math.max(1, Math.ceil(total / filters.perpage));
        return (
            <McTablePagination
                previousLabel={labels.previous}
                nextLabel={labels.next}
                pageLabel={labels.page}
                page={filters.page + 1}
                totalPages={totalPages}
                previousDisabled={loading || filters.page <= 0}
                nextDisabled={loading || filters.page + 1 >= totalPages}
                onPrevious={() => updateFilters({page: Math.max(0, filters.page - 1)})}
                onNext={() => updateFilters({page: filters.page + 1})}
            />
        );
    };

    return (
        <section className={mcClasses("mc-product-admin mc-course-reviews-admin")} aria-label={labels.title}>
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}
            {loading && !overview && !courses && !reviews && (
                <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
            )}
            {mode === "overview" && renderOverview()}
            {mode === "courses" && renderCourses()}
            {mode === "reviews" && renderReviews()}
        </section>
    );
}

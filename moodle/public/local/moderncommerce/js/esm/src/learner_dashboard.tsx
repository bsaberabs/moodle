// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React learner account dashboard for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_dashboard
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useMemo, useState} from "react";
import type {ReactNode} from "react";
import type {Labels} from "./learner_common";
import {badgeClass, callMoodleService, clampProgress, formatCount} from "./learner_common";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import ModernLearnerLayout, {
    learnerAppHashUrl,
    learnerAppHref,
    type LearnerLayoutContext,
    welcomeTitle,
} from "./learner_layout";
import LearnerListRow from "./learner_list_row";
import LearnerCourseRow from "./learner_course_row";

type UserData = {
    fullname: string;
    initials: string;
    avatarurl: string;
    membersince: string;
};

type Stats = {
    courses: number;
    completedcourses: number;
    bundles: number;
    programs: number;
    subscriptions: number;
    plans: number;
    orders: number;
    invoices: number;
    outstandinginvoices: number;
    displayinvoiceoutstanding: string;
    certificates: number;
};

type Course = {
    id: number;
    name: string;
    shortname: string;
    summary: string;
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
    lastaccess: number;
    lastaccesslabel: string;
    source: string;
    sourcelabel: string;
    producttype: string;
    productname: string;
};

type CourseViewMode = "grid" | "list";

type Product = {
    id: number;
    name: string;
    description: string;
    producttype: string;
    typelabel: string;
    coursecount: number;
    imageurl: string;
    hasimage: boolean;
    detailsurl: string;
    dashboardurl: string;
    status: string;
    statuslabel: string;
    statusclass: string;
    source: string;
};

type ManualInvoice = {
    id: number;
    invoicenumber: string;
    date: string;
    datetime: string;
    duedate: string;
    total: string;
    status: string;
    statuslabel: string;
    statusclass: string;
    downloadurl: string;
};

type Urls = {
    catalog: string;
    dashboard: string;
    courses: string;
    orders: string;
    subscriptions: string;
};

type DashboardData = {
    success: boolean;
    message: string;
    user: UserData;
    stats: Stats;
    access: {
        courses: Course[];
        products: Product[];
        bundles: Product[];
        programs: Product[];
        subscriptions: Product[];
        plans: Product[];
    };
    recentorders: unknown[];
    recentinvoices: ManualInvoice[];
    urls: Urls;
};

type LearnerDashboardProps = {
    methodName: string;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

type PortalStatProps = {
    icon: string;
    value: string;
    labelText: string;
    progress?: number;
    tone?: "primary" | "secondary" | "success" | "warning" | "info";
};

type DashboardStatTileVariant = "primary" | "success" | "warning" | "danger" | "info" | "muted" | "neutral";

type DashboardStatTileProps = {
    icon: string;
    labelText: string;
    value: string;
    progress?: number;
    variant?: DashboardStatTileVariant;
    href?: string;
    featured?: boolean;
};

const label = (labels: Labels, key: string, fallback = ""): string => labels[key] || fallback || key;

const certificatesUrl = (): string => learnerAppHashUrl("certificates");

const redeemUrl = (): string => learnerAppHashUrl("redeem");

const cartUrl = (): string => learnerAppHashUrl("cart");


const calendarUrl = (): string => learnerAppHashUrl("calendar");

const profileUrl = (): string => learnerAppHashUrl("profile");

const subscriptionAccessUrl = (): string => learnerAppHashUrl("access");

const completionRate = (stats: Stats): number => {
    if (stats.courses <= 0) {
        return 0;
    }
    return Math.round((stats.completedcourses / stats.courses) * 100);
};

const averageCourseProgress = (courses: Course[]): number => {
    if (courses.length === 0) {
        return 0;
    }

    const total = courses.reduce((sum, course) => sum + clampProgress(course.progress), 0);
    return Math.round(total / courses.length);
};

const inProgressCourses = (courses: Course[]): Course[] => {
    return courses.filter((course) => !course.completed && clampProgress(course.progress) > 0);
};

const activeAccessCount = (stats: Stats): number => {
    return stats.bundles + stats.programs + stats.subscriptions + stats.plans;
};

const courseActionLabel = (course: Course, labels: Labels): string => {
    if (course.completed) {
        return label(labels, "reviewcourse");
    }

    if (clampProgress(course.progress) > 0) {
        return label(labels, "resumecourse");
    }

    return label(labels, "startcourse");
};

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-grid-1x2" aria-hidden="true" /></span>
            <p className={mcClasses("mc-empty__title")}>{label(labels, "loading")}</p>
        </div>
    );
}

function ErrorState({
    message,
    labels,
    catalogUrl,
}: {
    message: string;
    labels: Labels;
    catalogUrl: string;
}) {
    return (
        <div className={mcClasses("mc-alert mc-alert--warning")} role="alert">
            <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
            <div className={mcClasses("mc-alert__body")}>
                <div className="fw-semibold mb-2">{message}</div>
                <a className={mcClasses("mc-button btn-mc-secondary d-inline-flex align-items-center")} href={catalogUrl}>
                    <i className="bi bi-grid me-1" aria-hidden="true" />
                    {label(labels, "browsecatalog")}
                </a>
            </div>
        </div>
    );
}

function EmptyDashboard({
    labels,
    catalogUrl,
}: {
    labels: Labels;
    catalogUrl: string;
}) {
    return (
        <div className={mcClasses("mc-learner-empty mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-bag-check" aria-hidden="true" /></span>
            <p className={mcClasses("mc-empty__title")}>
                {label(labels, "dashboardempty")}
            </p>
            <p className={mcClasses("mc-empty__body")}>
                {label(
                    labels,
                    "learneremptysubtitle",
                    "Purchased courses, certificates, memberships, and bundles will appear here."
                )}
            </p>
            <a className={mcClasses("mc-button btn-mc-primary")} href={catalogUrl}>
                <i className="bi bi-grid me-1" aria-hidden="true" />
                {label(labels, "browsecatalog")}
            </a>
        </div>
    );
}

function DashboardFrame({
    labels,
    layout,
    children,
}: {
    labels: Labels;
    layout?: LearnerLayoutContext;
    children: ReactNode;
}) {
    const title = layout?.user
        ? welcomeTitle(layout.user, labels)
        : label(labels, "dashboard");

    return (
        <ModernLearnerLayout
            activeNav="dashboard"
            title={title}
            subtitle={label(labels, "learnerdashboardsubtitle")}
            labels={labels}
            layout={layout}
        >
            {children}
        </ModernLearnerLayout>
    );
}

function DashboardStatTile({
    icon,
    labelText,
    value,
    href,
    progress,
    variant = "primary",
    featured = false,
}: DashboardStatTileProps) {
    const progressValue = typeof progress === "number" ? clampProgress(progress) : null;
    const className = mcClasses(
        "mc-stat-tile",
        `mc-stat-tile--${variant}`,
        "mc-dashboard-stat-tile",
        href && "mc-dashboard-stat-tile--link",
        featured && "mc-dashboard-stat-tile--featured",
    );
    const content = (
        <>
            <i className={`bi ${icon} mc-stat-tile__icon`} aria-hidden="true" />
            <div className={mcClasses("mc-stat-tile__body")}>
                <span className={mcClasses("mc-stat-tile__label")}>{labelText}</span>
                <strong className={mcClasses("mc-stat-tile__value")}>{value}</strong>
                {progressValue !== null && (
                    <span
                        className={mcClasses("mc-dashboard-stat-tile__progress")}
                        aria-label={`${value} ${labelText}`}
                    >
                        <span style={{width: `${progressValue}%`}} />
                    </span>
                )}
            </div>
            <i className={`bi ${icon} mc-stat-tile__watermark`} aria-hidden="true" />
        </>
    );

    if (href) {
        return (
            <a className={className} href={href}>
                {content}
            </a>
        );
    }

    return (
        <article className={className} role="listitem">
            {content}
        </article>
    );
}

function ModernCommerceTools({
    data,
    labels,
}: {
    data: DashboardData;
    labels: Labels;
}) {
    const stats = data.stats;
    const productCount = data.access.products.length || activeAccessCount(stats);

    const tiles = [
        {
            icon: "bi-grid-3x3-gap",
            labelText: label(labels, "browsecatalog"),
            href: learnerAppHref(data.urls.catalog, "library"),
            value: label(labels, "storefront"),
            variant: "primary" as const,
            featured: true,
        },
        {
            icon: "bi-mortarboard",
            labelText: label(labels, "mycourses"),
            href: learnerAppHref(data.urls.courses, "courses"),
            value: formatCount(stats.courses),
            variant: "info" as const,
        },
        {
            icon: "bi-layers",
            labelText: label(labels, "mybundles"),
            href: learnerAppHashUrl("bundles"),
            value: formatCount(data.access.bundles.length),
            variant: "primary" as const,
        },
        {
            icon: "bi-receipt",
            labelText: label(labels, "ordersandinvoices"),
            href: learnerAppHref(data.urls.orders, "orders"),
            value: formatCount(stats.orders + stats.invoices),
            variant: "neutral" as const,
        },
        {
            icon: "bi-credit-card",
            labelText: label(labels, "subscriptions"),
            href: learnerAppHref(data.urls.subscriptions, "subscriptions"),
            value: formatCount(stats.subscriptions + stats.plans),
            variant: "success" as const,
        },
        {
            icon: "bi-unlock",
            labelText: label(labels, "accesslibrary"),
            href: subscriptionAccessUrl(),
            value: formatCount(productCount),
            variant: "info" as const,
        },
        {
            icon: "bi-key",
            labelText: label(labels, "redeemkeys"),
            href: redeemUrl(),
            value: label(labels, "keys"),
            variant: "warning" as const,
        },
        {
            icon: "bi-patch-check-fill",
            labelText: label(labels, "mycertificates"),
            href: certificatesUrl(),
            value: formatCount(stats.certificates),
            variant: "success" as const,
        },
        {
            icon: "bi-bag-check",
            labelText: label(labels, "cartandcheckout"),
            href: cartUrl(),
            value: label(labels, "checkout"),
            variant: "primary" as const,
        },
        {
            icon: "bi-calendar",
            labelText: label(labels, "calendar"),
            href: calendarUrl(),
            value: label(labels, "schedule"),
            variant: "info" as const,
        },
        {
            icon: "bi-person-fill",
            labelText: label(labels, "myprofile"),
            href: profileUrl(),
            value: label(labels, "account"),
            variant: "neutral" as const,
        },
    ];

    return (
        <section className={mcClasses("mc-modern-feature-hub")} aria-labelledby="mc-modern-feature-hub-title">
            <div className={mcClasses("mc-modern-section-header")}>
                <div>
                    <h2 id="mc-modern-feature-hub-title">
                        {label(labels, "moderncommercetools")}
                    </h2>
                    <span>
                        {label(
                            labels,
                            "moderncommercetoolsdesc",
                            "Everything learners use to buy, access, redeem, and track learning."
                        )}
                    </span>
                </div>
                {/* Points at the cart, not #/checkout: checkout is only reachable with a
                    non-empty cart, so linking it directly is a dead end when empty. */}
                <a className={mcClasses("mc-button mc-btn-soft mc-modern-feature-hub__action")} href={cartUrl()}>
                    <i className="bi bi-bag-check" aria-hidden="true" />
                    {label(labels, "cart")}
                </a>
            </div>
            <div className={mcClasses("mc-stat-strip mc-dashboard-stat-strip mc-dashboard-stat-strip--tools")}>
                {tiles.map((tile) => (
                    <DashboardStatTile
                        icon={tile.icon}
                        labelText={tile.labelText}
                        href={tile.href}
                        value={tile.value}
                        variant={tile.variant}
                        featured={tile.featured}
                        key={tile.labelText}
                    />
                ))}
            </div>
        </section>
    );
}

function RecentInvoices({
    invoices,
    stats,
    labels,
}: {
    invoices: ManualInvoice[];
    stats: Stats;
    labels: Labels;
}) {
    if (invoices.length === 0) {
        return null;
    }

    return (
        <section className={mcClasses("mc-modern-recent-invoices")} aria-labelledby="mc-recent-invoices-title">
            <div className={mcClasses("mc-modern-section-header")}>
                <div>
                    <h2 id="mc-recent-invoices-title">{label(labels, "recentinvoices")}</h2>
                    <span>
                        {formatCount(stats.outstandinginvoices)} {label(labels, "outstanding")}
                        {" · "}
                        {stats.displayinvoiceoutstanding}
                    </span>
                </div>
                <a className={mcClasses("mc-button mc-btn-soft btn-sm")} href={learnerAppHashUrl("orders")}>
                    <i className="bi bi-receipt" aria-hidden="true" />
                    {label(labels, "ordersandinvoices")}
                </a>
            </div>
            <div className={mcClasses("mc-card")}>
                <div className={mcClasses("mc-card-body p-0")}>
                    <div className="table-responsive">
                        <table className="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{label(labels, "invoice")}</th>
                                    <th scope="col">{label(labels, "status")}</th>
                                    <th scope="col">{label(labels, "duedate")}</th>
                                    <th scope="col" className="text-end">{label(labels, "total")}</th>
                                    <th scope="col" className="text-end">{label(labels, "actions")}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.map((invoice) => (
                                    <tr key={invoice.id}>
                                        <td>
                                            <div className="fw-semibold">{invoice.invoicenumber}</div>
                                            <div className={mcClasses("mc-cell-muted small")}>{invoice.date}</div>
                                        </td>
                                        <td>
                                            <span className={mcClasses(`mc-badge mc-badge--${badgeClass(invoice.statusclass)}`)}>
                                                {invoice.statuslabel}
                                            </span>
                                        </td>
                                        <td className={mcClasses("mc-cell-muted")}>{invoice.duedate}</td>
                                        <td className="text-end fw-semibold">{invoice.total}</td>
                                        <td className="text-end">
                                            <a className={mcClasses("mc-button btn-mc-secondary py-1 px-2")} href={invoice.downloadurl}>
                                                <i className="bi bi-download me-1" aria-hidden="true" />
                                                {label(labels, "downloadinvoice")}
                                            </a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    );
}

function PortalStat({
    icon,
    value,
    labelText,
    progress,
    tone = "primary",
}: PortalStatProps) {
    const variant: DashboardStatTileVariant = tone === "secondary" ? "muted" : tone;

    return (
        <DashboardStatTile
            icon={icon}
            labelText={labelText}
            value={value}
            progress={progress}
            variant={variant}
        />
    );
}

function StatGrid({
    data,
    labels,
}: {
    data: DashboardData;
    labels: Labels;
}) {
    const stats = data.stats;
    const courses = data.access.courses;
    const inprogress = inProgressCourses(courses).length;
    const progressAverage = averageCourseProgress(courses);
    const accessCount = activeAccessCount(stats);
    const rate = completionRate(stats);

    return (
        <section className={mcClasses("mc-learning-summary")} aria-labelledby="mc-learning-summary-title">
            <div className={mcClasses("mc-learning-summary__header")}>
                <div>
                    <span>{label(labels, "overview")}</span>
                    <h2 id="mc-learning-summary-title">{label(labels, "learningsummary")}</h2>
                </div>
                <span className={mcClasses("mc-learning-summary__meta")}>
                    {formatCount(stats.courses)} {label(labels, "courses")}
                </span>
            </div>
            <div className={mcClasses("mc-stat-strip mc-dashboard-stat-strip")} role="list">
                <PortalStat
                    icon="bi-journal-text"
                    value={formatCount(stats.courses)}
                    labelText={label(labels, "totalcourses")}
                />
                <PortalStat
                    icon="bi-graph-up"
                    value={`${progressAverage}%`}
                    labelText={label(labels, "courseprogress")}
                    progress={progressAverage}
                    tone="info"
                />
                <PortalStat
                    icon="bi-check-circle-fill"
                    value={formatCount(stats.completedcourses)}
                    labelText={label(labels, "completedcourses")}
                    progress={rate}
                    tone="success"
                />
                <PortalStat
                    icon="bi-play-circle"
                    value={formatCount(inprogress)}
                    labelText={label(labels, "inprogress")}
                    tone="warning"
                />
                <PortalStat
                    icon="bi-shield-check"
                    value={formatCount(accessCount)}
                    labelText={label(labels, "activeaccess")}
                    tone="secondary"
                />
                <PortalStat
                    icon="bi-patch-check-fill"
                    value={formatCount(stats.certificates)}
                    labelText={label(labels, "mycertificates")}
                    tone="success"
                />
            </div>
        </section>
    );
}

function CourseImage({
    course,
}: {
    course: Course;
}) {
    if (course.hasimage) {
        return (
            <a className={mcClasses("mc-modern-course-image")} href={course.courseurl}>
                <img src={course.imageurl} alt="" loading="lazy" />
            </a>
        );
    }

    return (
        <a className={mcClasses("mc-modern-course-image mc-modern-course-image--fallback")} href={course.courseurl}>
            <span aria-hidden="true">
                <i className="bi bi-play-circle" />
            </span>
        </a>
    );
}

function CourseCard({
    course,
    labels,
}: {
    course: Course;
    labels: Labels;
}) {
    const progress = clampProgress(course.progress);
    const buttonLabel = courseActionLabel(course, labels);

    return (
        <article className={mcClasses("mc-modern-course-card")}>
            <CourseImage course={course} />
            <div className={mcClasses("mc-modern-course-body")}>
                <h3><a href={course.courseurl}>{course.name}</a></h3>
                <div className={mcClasses("mc-modern-course-progress")}>
                    <progress value={progress} max={100}>{progress}%</progress>
                    <span>{course.progresslabel}</span>
                </div>
                <div className={mcClasses("mc-modern-course-footer")}>
                    <span>
                        <i className="bi bi-clock-fill" aria-hidden="true" />
                        {course.lastaccesslabel || course.statuslabel}
                    </span>
                    <a className={mcClasses("mc-button mc-btn-soft btn-sm")} href={course.courseurl}>
                        <i className="bi bi-play-circle-fill" aria-hidden="true" />
                        {buttonLabel}
                    </a>
                </div>
            </div>
        </article>
    );
}


function ContinueLearning({
    courses,
    labels,
    coursesUrl,
}: {
    courses: Course[];
    labels: Labels;
    coursesUrl: string;
}) {
    const visibleCourses = courses.slice(0, 3);
    const [viewMode, setViewMode] = useState<CourseViewMode>("grid");
    const isGridView = viewMode === "grid";
    const courseListId = "mc-continue-learning-courses";
    const gridLabel = label(labels, "gridview");
    const listLabel = label(labels, "listview");

    return (
        <section className={mcClasses("mc-modern-recent-courses")}>
            <div className={mcClasses("mc-modern-section-header")}>
                <div>
                    <h2>{label(labels, "continuelearning")}</h2>
                    <span>
                        {label(labels, "showingrecentcourses")}
                    </span>
                </div>
                <div className={mcClasses("mc-modern-section-actions")}>
                    <div
                        className={mcClasses("mc-modern-view-toggle")}
                        role="group"
                        aria-label={label(labels, "courseviewtoggle")}
                    >
                        <button
                            className={mcClasses("mc-button", isGridView ? "active" : "")}
                            data-mc-button={isGridView ? "primary" : "light"}
                            data-mc-button-size="icon"
                            type="button"
                            aria-controls={courseListId}
                            aria-label={gridLabel}
                            aria-pressed={isGridView}
                            title={gridLabel}
                            onClick={() => setViewMode("grid")}
                        >
                            <i className="bi bi-grid-3x3-gap-fill" aria-hidden="true" />
                        </button>
                        <button
                            className={mcClasses("mc-button", !isGridView ? "active" : "")}
                            data-mc-button={!isGridView ? "primary" : "light"}
                            data-mc-button-size="icon"
                            type="button"
                            aria-controls={courseListId}
                            aria-label={listLabel}
                            aria-pressed={!isGridView}
                            title={listLabel}
                            onClick={() => setViewMode("list")}
                        >
                            <i className="bi bi-list-ul" aria-hidden="true" />
                        </button>
                    </div>
                    <a className={mcClasses("mc-button mc-btn-soft btn-sm")} href={coursesUrl}>
                        {label(labels, "viewallcourses")}
                    </a>
                </div>
            </div>

            {visibleCourses.length > 0 ? (
                isGridView ? (
                    <div className={mcClasses("mc-modern-course-grid")} id={courseListId}>
                        {visibleCourses.map((course) => (
                            <CourseCard course={course} labels={labels} key={course.id} />
                        ))}
                    </div>
                ) : (
                    <div className={mcClasses("mc-learner-course-rows")} id={courseListId} role="list">
                        {visibleCourses.map((course) => (
                            <LearnerCourseRow course={course} labels={labels} key={course.id} />
                        ))}
                    </div>
                )
            ) : (
                <EmptyDashboard labels={labels} catalogUrl={learnerAppHashUrl("library")} />
            )}
        </section>
    );
}

function ModernLearnerDashboard({
    data,
    labels,
    layout,
}: {
    data: DashboardData;
    labels: Labels;
    layout?: LearnerLayoutContext;
}) {
    return (
        <ModernLearnerLayout
            activeNav="dashboard"
            title={welcomeTitle(data.user, labels)}
            subtitle={label(labels, "learnerdashboardsubtitle")}
            profile={data.user}
            stats={{
                courses: data.stats.courses,
                completedcourses: data.stats.completedcourses,
                certificates: data.stats.certificates,
                activeaccess: activeAccessCount(data.stats),
            }}
            labels={labels}
            layout={layout}
        >
            <StatGrid data={data} labels={labels} />
            <ModernCommerceTools data={data} labels={labels} />
            <RecentInvoices invoices={data.recentinvoices} stats={data.stats} labels={labels} />
            <ContinueLearning
                courses={data.access.courses}
                labels={labels}
                coursesUrl={learnerAppHref(data.urls.courses, "courses")}
            />
        </ModernLearnerLayout>
    );
}

export default function LearnerDashboard({
    methodName,
    labels,
    layout,
}: LearnerDashboardProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<DashboardData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");

        callMoodleService<DashboardData>(methodName, {})
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                }
                return undefined;
            })
            .catch((caught: Error) => {
                if (!cancelled) {
                    setError(caught.message);
                }
                return undefined;
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

    const catalogUrl = learnerAppHref(data?.urls.catalog, "library");

    const hasAccess = useMemo(() => {
        if (!data) {
            return false;
        }
        return data.access.courses.length > 0 || data.access.products.length > 0;
    }, [data]);

    if (loading) {
        return (
            <DashboardFrame labels={labels} layout={layout}>
                <LoadingState labels={labels} />
            </DashboardFrame>
        );
    }

    if (error || !data || !data.success) {
        return (
            <DashboardFrame labels={labels} layout={layout}>
                <ErrorState
                    message={error || data?.message || label(labels, "dashboardempty")}
                    labels={labels}
                    catalogUrl={catalogUrl}
                />
            </DashboardFrame>
        );
    }

    if (!hasAccess) {
        return (
            <ModernLearnerLayout
                activeNav="dashboard"
                title={welcomeTitle(data.user, labels)}
                subtitle={label(labels, "learnerdashboardsubtitle")}
                profile={data.user}
                stats={{
                    courses: data.stats.courses,
                    completedcourses: data.stats.completedcourses,
                    certificates: data.stats.certificates,
                    activeaccess: activeAccessCount(data.stats),
                }}
                labels={labels}
                layout={layout}
            >
                <StatGrid data={data} labels={labels} />
                <ModernCommerceTools data={data} labels={labels} />
                <EmptyDashboard labels={labels} catalogUrl={learnerAppHref(data.urls.catalog, "library")} />
            </ModernLearnerLayout>
        );
    }

    return <ModernLearnerDashboard data={data} labels={labels} layout={layout} />;
}

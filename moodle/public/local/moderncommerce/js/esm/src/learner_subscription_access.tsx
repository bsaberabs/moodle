// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React learner subscription access page for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_subscription_access
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import type {ReactNode} from "react";
import {badgeClass, callMoodleService, formatCount, Labels} from "./learner_common";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";
import {LearnerStatStrip, LearnerStatTile} from "./learner_stat_tiles";

type Subscription = {
    id: number;
    planid: number;
    status: string;
    statuslabel: string;
    statusclass: string;
};

type Plan = {
    id: number;
    name: string;
};

type Course = {
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

type AccessGroup = {
    id: number;
    name: string;
    producttype: string;
    typelabel: string;
    coursecount: number;
    courses: Course[];
};

type Counts = {
    courses: number;
    categories: number;
    bundles: number;
    totalcourses: number;
};

type AccessResponse = {
    success: boolean;
    available: boolean;
    hassubscription: boolean;
    message: string;
    subscription: Subscription;
    plan: Plan;
    courses: Course[];
    categories: AccessGroup[];
    bundles: AccessGroup[];
    counts: Counts;
    urls: {
        plan: string;
        plans: string;
        catalog: string;
        courses: string;
    };
};

type LearnerSubscriptionAccessProps = {
    methodName: string;
    subscriptionId: number;
    planId: number;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-list-check" aria-hidden="true" /></span>
            <p className={mcClasses("mc-empty__title")}>{labels.loading}</p>
        </div>
    );
}

function EmptyState({
    title,
    message,
    labels,
    catalogUrl,
    plansUrl,
}: {
    title: string;
    message: string;
    labels: Labels;
    catalogUrl: string;
    plansUrl: string;
}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-list-check" aria-hidden="true" /></span>
            <p className={mcClasses("mc-empty__title")}>{title}</p>
            {message && <p className={mcClasses("mc-empty__desc")}>{message}</p>}
            <div className="d-flex gap-2 justify-content-center flex-wrap">
                <a className={mcClasses("mc-button btn-mc-primary")} href={catalogUrl}>
                    <i className="bi bi-grid me-1" aria-hidden="true" />
                    {labels.browsecatalog}
                </a>
                <a className={mcClasses("mc-button btn-mc-secondary")} href={plansUrl}>
                    <i className="bi bi-credit-card me-1" aria-hidden="true" />
                    {labels.browseplans}
                </a>
            </div>
        </div>
    );
}

function CourseRow({
    course,
    labels,
}: {
    course: Course;
    labels: Labels;
}) {
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

function CourseSection({
    title,
    courses,
    labels,
}: {
    title: string;
    courses: Course[];
    labels: Labels;
}) {
    if (courses.length === 0) {
        return null;
    }

    return (
        <div className={mcClasses("mc-card mb-3")}>
            <div className={mcClasses("mc-card-header")}>
                <h2 className={mcClasses("mc-card-title")}>{title}</h2>
                <span className={mcClasses("mc-badge mc-badge--neutral")}>{formatCount(courses.length)}</span>
            </div>
            <div className={mcClasses("mc-card-body")}>
                {courses.map((course) => (
                    <CourseRow key={course.id} course={course} labels={labels} />
                ))}
            </div>
        </div>
    );
}

function GroupSection({
    title,
    groups,
    labels,
}: {
    title: string;
    groups: AccessGroup[];
    labels: Labels;
}) {
    if (groups.length === 0) {
        return null;
    }

    return (
        <div className={mcClasses("mc-card mb-3")}>
            <div className={mcClasses("mc-card-header")}>
                <h2 className={mcClasses("mc-card-title")}>{title}</h2>
                <span className={mcClasses("mc-badge mc-badge--neutral")}>{formatCount(groups.length)}</span>
            </div>
            <div className={mcClasses("mc-card-body")}>
                {groups.map((group) => (
                    <section className="mb-3" key={`${group.producttype}-${group.id}`}>
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
                ))}
            </div>
        </div>
    );
}

export default function LearnerSubscriptionAccess({
    methodName,
    subscriptionId,
    planId,
    labels,
    layout,
}: LearnerSubscriptionAccessProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<AccessResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");

        callMoodleService<AccessResponse>(methodName, {id: subscriptionId, planid: planId})
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
    }, [methodName, planId, subscriptionId]);

    const renderLayout = (children: ReactNode, subtitle?: string, actions?: ReactNode) => (
        <ModernLearnerLayout
            activeNav="access"
            title={labels.mysubscriptionaccess}
            subtitle={subtitle}
            labels={labels}
            layout={layout}
            actions={actions}
        >
            {children}
        </ModernLearnerLayout>
    );

    if (loading) {
        return renderLayout(<LoadingState labels={labels} />);
    }

    if (error || !data || !data.success) {
        return renderLayout(
            <div className={mcClasses("mc-alert mc-alert--warning")} role="alert">
                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                <div className={mcClasses("mc-alert__body")}>{error || data?.message}</div>
            </div>
        );
    }

    if (!data.available) {
        return renderLayout(
            <EmptyState
                title={labels.subscriptionsunavailable}
                message={data.message}
                labels={labels}
                catalogUrl={data.urls.catalog}
                plansUrl={data.urls.plans}
            />
        );
    }

    if (!data.hassubscription) {
        return renderLayout(
            <EmptyState
                title={labels.noactivesubscription}
                message={data.message}
                labels={labels}
                catalogUrl={data.urls.catalog}
                plansUrl={data.urls.plans}
            />
        );
    }

    const hasAccess = data.courses.length > 0 || data.categories.length > 0 || data.bundles.length > 0;
    const headerActions = (
        <span className={mcClasses(`mc-badge mc-badge--${badgeClass(data.subscription.statusclass)}`)}>
            {data.subscription.statuslabel}
        </span>
    );

    return renderLayout(
        <div className={mcClasses("mc-learner-subscription-access")}>
            <a className={mcClasses("mc-modern-back-link")} href={data.urls.plan}>
                <i className="bi bi-arrow-left" aria-hidden="true" />
                {labels.viewplan}
            </a>

            <LearnerStatStrip>
                <LearnerStatTile
                    label={labels.totalcourses}
                    value={data.counts.totalcourses}
                    icon="bi-play-circle"
                    variant="primary"
                />
                <LearnerStatTile label={labels.directcourses} value={data.counts.courses} icon="bi-book" variant="success" />
                <LearnerStatTile label={labels.categoryaccess} value={data.counts.categories} icon="bi-folder2" variant="warning" />
                <LearnerStatTile label={labels.bundleaccess} value={data.counts.bundles} icon="bi-layers" variant="info" />
            </LearnerStatStrip>

            {!hasAccess && (
                <div className={mcClasses("mc-empty mc-empty--centered")}>
                    <span className={mcClasses("mc-empty__icon")}><i className="bi bi-list-check" aria-hidden="true" /></span>
                    <p className={mcClasses("mc-empty__title")}>{labels.noactivesubscription}</p>
                    <a className={mcClasses("mc-button btn-mc-primary")} href={data.urls.catalog}>
                        <i className="bi bi-grid me-1" aria-hidden="true" />
                        {labels.browsecatalog}
                    </a>
                </div>
            )}

            {hasAccess && (
                <>
                    <CourseSection title={labels.directcourses} courses={data.courses} labels={labels} />
                    <GroupSection title={labels.bundleaccess} groups={data.bundles} labels={labels} />
                    <GroupSection title={labels.categoryaccess} groups={data.categories} labels={labels} />
                </>
            )}
        </div>,
        `${labels.plan}: ${data.plan.name}`,
        headerActions
    );
}

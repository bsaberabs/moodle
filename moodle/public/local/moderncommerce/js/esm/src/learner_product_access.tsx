// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React learner product access page for Modern Commerce bundles/programs.
 *
 * @module     local_moderncommerce/learner_product_access
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {callMoodleService, clampProgress, formatCount, Labels} from "./learner_common";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import ModernLearnerLayout, {learnerAppHashUrl, type LearnerLayoutContext, type LearnerNavKey} from "./learner_layout";
import {LearnerStatStrip, LearnerStatTile} from "./learner_stat_tiles";

type Product = {
    id: number;
    name: string;
    description: string;
    producttype: string;
    typelabel: string;
    coursecount: number;
    imageurl: string;
    hasimage: boolean;
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
    progress: number;
    progresslabel: string;
    completed: boolean;
    status: string;
    statuslabel: string;
    courseurl: string;
    locked: boolean;
    unlockreason: string;
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

type Counts = {
    courses: number;
    completed: number;
    inprogress: number;
    notstarted: number;
};

type ProductAccessResponse = {
    success: boolean;
    message: string;
    product: Product;
    courses: Course[];
    counts: Counts;
    programprogress: {
        programid: number;
        status: string;
        completed: boolean;
        progresspercentage: number;
        coursescompleted: number;
        totalcourses: number;
        certificateissued: boolean;
        prerequisitesmet: boolean;
    };
    urls: {
        library: string;
        dashboard: string;
        details: string;
    };
};

type LearnerProductAccessProps = {
    methodName: string;
    productId: number;
    labels: Labels;
    layout?: LearnerLayoutContext;
    activeNav?: LearnerNavKey;
};

const label = (labels: Labels, key: string, fallback = ""): string => labels[key] || fallback || key;

const courseActionLabel = (course: Course, labels: Labels): string => {
    if (course.locked) {
        return label(labels, "locked");
    }
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
            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-layers" aria-hidden="true" /></span>
            <p className={mcClasses("mc-empty__title")}>{label(labels, "loading")}</p>
        </div>
    );
}

function ProductSummary({
    product,
    counts,
    labels,
}: {
    product: Product;
    counts: Counts;
    labels: Labels;
}) {
    return (
        <section className={mcClasses("mc-card mb-3")}>
            <div className={mcClasses("mc-card-body d-flex gap-3 align-items-start flex-wrap flex-lg-nowrap")}>
                {product.hasimage ? (
                    <img
                        src={product.imageurl}
                        alt=""
                        width={168}
                        height={96}
                        className="rounded object-fit-cover flex-shrink-0"
                        loading="lazy"
                    />
                ) : (
                    <span className="rounded bg-light d-flex align-items-center justify-content-center flex-shrink-0"
                        style={{width: 168, height: 96}} aria-hidden="true">
                        <i className="bi bi-layers text-muted" />
                    </span>
                )}
                <div className="flex-grow-1 min-w-0">
                    <span className={mcClasses("mc-badge mc-badge--primary mb-2")}>{product.typelabel}</span>
                    {product.description && <p className={mcClasses("mc-cell-muted mb-2")}>{product.description}</p>}
                    <div className="d-flex flex-wrap gap-2">
                        <span className={mcClasses("mc-badge mc-badge--neutral")}>
                            {formatCount(counts.courses)} {label(labels, "includedcourses")}
                        </span>
                        <span className={mcClasses("mc-badge mc-badge--success")}>
                            {formatCount(counts.completed)} {label(labels, "completed")}
                        </span>
                    </div>
                </div>
            </div>
        </section>
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

    return (
        <article className={mcClasses("mc-learner-course-card")}>
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
                    <span>
                        {course.locked ? (
                            <span className={mcClasses("mc-learner-course-card__title")}>{course.name}</span>
                        ) : (
                            <a className={mcClasses("mc-learner-course-card__title text-decoration-none")} href={course.courseurl}>
                                {course.name}
                            </a>
                        )}
                        <span className={mcClasses("mc-learner-course-card__meta d-flex flex-wrap gap-2")}>
                            {course.categoryname && <span>{course.categoryname}</span>}
                            {course.modulecount > 0 && (
                                <span>{formatCount(course.modulecount)} {label(labels, "activities")}</span>
                            )}
                            {course.lastaccesslabel && <span>{course.lastaccesslabel}</span>}
                        </span>
                    </span>
                    <span className={mcClasses(`mc-badge mc-badge--${course.completed ? "success" : "neutral"}`)}>
                        {course.statuslabel}
                    </span>
                </span>
                <span className="d-flex align-items-center justify-content-between gap-2 mt-2">
                    <span className={mcClasses("mc-progress-bar-wrap flex-grow-1")}>
                        <span
                            className={mcClasses("mc-progress-bar-fill", course.completed && "mc-progress-bar-fill--complete")}
                            style={{width: `${progress}%`}}
                        />
                    </span>
                    <span className={mcClasses("mc-cell-muted small")}>{course.progresslabel}</span>
                </span>
            </span>
            {course.locked ? (
                <span className={mcClasses("mc-cell-muted small ms-md-auto")} title={course.unlockreason}>
                    <i className="bi bi-lock me-1" aria-hidden="true" />{courseActionLabel(course, labels)}
                </span>
            ) : (
                <a className={mcClasses("mc-button btn-mc-primary ms-md-auto")} href={course.courseurl}>
                    <i className="bi bi-play-circle" aria-hidden="true" />
                    {courseActionLabel(course, labels)}
                </a>
            )}
        </article>
    );
}

export default function LearnerProductAccess({
    methodName,
    productId,
    labels,
    layout,
    activeNav = "access",
}: LearnerProductAccessProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<ProductAccessResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");

        callMoodleService<ProductAccessResponse>(methodName, {id: productId})
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
    }, [methodName, productId]);

    const product = data?.product;
    const title = product?.name || label(labels, "accesslibrary");
    const subtitle = product?.typelabel
        ? `${product.typelabel} · ${formatCount(product.coursecount)} ${label(labels, "includedcourses")}`
        : label(labels, "accountaccesssubtitle");
    const backUrl = activeNav === "bundles" ? learnerAppHashUrl("bundles") : (data?.urls.library || "#/library");
    const backLabel = activeNav === "bundles"
        ? label(labels, "mybundles")
        : label(labels, "courselibrary");

    return (
        <ModernLearnerLayout
            activeNav={activeNav}
            title={title}
            subtitle={subtitle}
            labels={labels}
            layout={layout}
            actions={(
                <a className={mcClasses("mc-button btn-mc-secondary")} href={backUrl}>
                    <i className="bi bi-arrow-left" aria-hidden="true" />
                    {backLabel}
                </a>
            )}
        >
            {loading && <LoadingState labels={labels} />}

            {!loading && (error || !data || !data.success) && (
                <div className={mcClasses("mc-alert mc-alert--warning")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>
                        {error || data?.message || label(
                            labels,
                            "productaccessunavailable",
                            "This learning access is not available for your account."
                        )}
                    </div>
                </div>
            )}

            {!loading && data?.success && (
                <div className={mcClasses("mc-learner-product-access")}>
                    <ProductSummary product={data.product} counts={data.counts} labels={labels} />

                    {data.product.producttype === "program" && (
                        <section className={mcClasses("mc-card mb-3")} aria-label={label(labels, "programprogress")}>
                            <div className={mcClasses("mc-card-body")}>
                                <div className="d-flex justify-content-between gap-3 mb-2">
                                    <strong>{data.programprogress.completed
                                        ? label(labels, "programcomplete")
                                        : label(labels, "programprogress")}</strong>
                                    <span>{Math.round(data.programprogress.progresspercentage)}%</span>
                                </div>
                                <div className={mcClasses("mc-progress-bar-wrap")}>
                                    <span className={mcClasses("mc-progress-bar-fill")}
                                        style={{width: `${clampProgress(data.programprogress.progresspercentage)}%`}} />
                                </div>
                                {!data.programprogress.prerequisitesmet && (
                                    <p className={mcClasses("mc-cell-muted small mt-2 mb-0")}>
                                        {label(labels, "programprerequisitespending")}
                                    </p>
                                )}
                            </div>
                        </section>
                    )}

                    <LearnerStatStrip>
                        <LearnerStatTile
                            label={label(labels, "totalcourses")}
                            value={data.counts.courses}
                            icon="bi-play-circle"
                            variant="primary"
                        />
                        <LearnerStatTile
                            label={label(labels, "completed")}
                            value={data.counts.completed}
                            icon="bi-check-circle"
                            variant="success"
                        />
                        <LearnerStatTile
                            label={label(labels, "inprogress")}
                            value={data.counts.inprogress}
                            icon="bi-graph-up"
                            variant="info"
                        />
                        <LearnerStatTile
                            label={label(labels, "notstarted")}
                            value={data.counts.notstarted}
                            icon="bi-circle"
                            variant="warning"
                        />
                    </LearnerStatStrip>

                    <section className={mcClasses("mc-card")}>
                        <div className={mcClasses("mc-card-header")}>
                            <h2 className={mcClasses("mc-card-title")}>
                                {label(labels, "includedcourses")}
                            </h2>
                            <span className={mcClasses("mc-badge mc-badge--neutral")}>
                                {formatCount(data.courses.length)}
                            </span>
                        </div>
                        <div className={mcClasses("mc-card-body")}>
                            {data.courses.length > 0 ? (
                                data.courses.map((course) => (
                                    <CourseCard key={course.id} course={course} labels={labels} />
                                ))
                            ) : (
                                <div className={mcClasses("mc-empty mc-empty--centered")}>
                                    <span className={mcClasses("mc-empty__icon")}>
                                        <i className="bi bi-layers" aria-hidden="true" />
                                    </span>
                                    <p className={mcClasses("mc-empty__title")}>
                                        {label(labels, "nocourses")}
                                    </p>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            )}
        </ModernLearnerLayout>
    );
}

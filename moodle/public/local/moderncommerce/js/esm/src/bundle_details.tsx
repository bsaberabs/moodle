// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React bundle/program details storefront page for Modern Commerce.
 *
 * @module     local_moderncommerce/bundle_details
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {callMoodleService, formatCount, Labels, wwwroot} from "./learner_common";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";

type Bundle = {
    id: number;
    name: string;
    description: string;
    shortdescription: string;
    type: string;
    isprogram: boolean;
    coursecount: number;
    imageurl: string;
    hasimage: boolean;
};

type Price = {
    current: string;
    original: string;
    hassale: boolean;
    saleprice: string;
    savings: string;
};

type State = {
    purchased: boolean;
    accessallcourses: boolean;
    hasownedcourses: boolean;
    ownedcoursescount: number;
    totalcoursescount: number;
    hascourses: boolean;
    isavailable: boolean;
    showfeatured: boolean;
    showbestseller: boolean;
    isloggedin: boolean;
    productid: number;
    inwishlist: boolean;
};

type Course = {
    id: number;
    courseid: number;
    courseurl: string;
    courseviewurl: string;
    imageurl: string;
    hasimage: boolean;
    name: string;
    summary: string;
    categoryname: string;
    duration: string;
    activitycount: number;
    level: string;
    quizzescount: number;
    isfree: boolean;
    isenrolled: boolean;
    hassale: boolean;
    regularprice: string;
    saleprice: string;
    enrolledtext: string;
};

type Urls = {
    addtocart: string;
    checkout: string;
    catalog: string;
    launch: string;
};

type BundleDetailsResponse = {
    success: boolean;
    message: string;
    bundle: Bundle;
    price: Price;
    state: State;
    courses: Course[];
    urls: Urls;
};

type BundleDetailsProps = {
    methodName: string;
    wishlistUpdateMethodName?: string;
    bundleId: number;
    labels: Labels;
};

const label = (labels: Labels, key: string): string => labels[key] || key;

const purchaseLabel = (labels: Labels): string => {
    return !labels.addtocart || labels.addtocart === "Add" ? "Add to cart" : labels.addtocart;
};

const typeLabel = (bundle: Bundle, labels: Labels): string => {
    return bundle.isprogram ? labels.program : labels.bundle;
};

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}>
                <i className="bi bi-layers" aria-hidden="true" />
            </span>
            <p className={mcClasses("mc-empty__title")}>{labels.loading}</p>
        </div>
    );
}

function ErrorState({
    data,
    error,
    labels,
}: {
    data: BundleDetailsResponse | null;
    error: string;
    labels: Labels;
}) {
    const message = error || data?.message || labels.bundlenotfound;
    const catalogUrl = data?.urls.catalog || `${wwwroot()}/local/moderncommerce/index.php`;

    return (
        <div className={mcClasses("mc-bundle-details")}>
            <div className={mcClasses("mc-bundle-container")}>
                <div className={mcClasses("mc-alert mc-alert--warning mb-3")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{message}</div>
                </div>
                <a className={mcClasses("mc-button mc-btn-soft")} href={catalogUrl}>
                    <i className="bi bi-arrow-left me-1" aria-hidden="true" />
                    {labels.browsecatalog}
                </a>
            </div>
        </div>
    );
}

function HeroStat({
    icon,
    labelText,
    value,
}: {
    icon: string;
    labelText: string;
    value: string;
}) {
    return (
        <div className={mcClasses("mc-bundle-stat")}>
            <span className={mcClasses("mc-bundle-stat__icon")}>
                <i className={`bi ${icon}`} aria-hidden="true" />
            </span>
            <span>
                <span className={mcClasses("mc-bundle-stat__label")}>{labelText}</span>
                <strong>{value}</strong>
            </span>
        </div>
    );
}

function BundleMedia({bundle}: {bundle: Bundle}) {
    const [imageFailed, setImageFailed] = useState(false);
    const hasImage = bundle.hasimage && bundle.imageurl && !imageFailed;

    return (
        <div className={mcClasses("mc-bundle-hero__media")}>
            {hasImage ? (
                <img
                    src={bundle.imageurl}
                    alt={bundle.name}
                    width={760}
                    height={428}
                    loading="lazy"
                    onError={() => setImageFailed(true)}
                />
            ) : (
                <div className={mcClasses("mc-bundle-media-fallback")} aria-hidden="true">
                    <i className="bi bi-layers" />
                </div>
            )}
        </div>
    );
}

function CourseThumb({course}: {course: Course}) {
    const [imageFailed, setImageFailed] = useState(false);
    const hasImage = course.hasimage && course.imageurl && !imageFailed;

    if (hasImage) {
        return (
            <img
                src={course.imageurl}
                alt=""
                className={mcClasses("mc-bundle-course__thumb")}
                width={88}
                height={58}
                loading="lazy"
                onError={() => setImageFailed(true)}
            />
        );
    }

    return (
        <span className={mcClasses("mc-bundle-course__thumb")} aria-hidden="true">
            <i className="bi bi-play-circle" />
        </span>
    );
}

function BundleHero({
    bundle,
    price,
    state,
    labels,
}: {
    bundle: Bundle;
    price: Price;
    state: State;
    labels: Labels;
}) {
    return (
        <section className={mcClasses("mc-bundle-hero")} aria-labelledby="mc-bundle-title">
            <div className={mcClasses("mc-bundle-hero__copy")}>
                <div className={mcClasses("mc-bundle-badges")}>
                    <span className={mcClasses(`mc-badge ${bundle.isprogram ? "mc-badge-program" : "mc-badge-bundle"}`)}>
                        {typeLabel(bundle, labels)}
                    </span>
                    {state.showfeatured && (
                        <span className={mcClasses("mc-badge mc-badge--success")}>{labels.featured}</span>
                    )}
                    {state.showbestseller && (
                        <span className={mcClasses("mc-badge mc-badge--warning")}>{labels.bestseller}</span>
                    )}
                </div>

                <h1 id="mc-bundle-title">{bundle.name}</h1>
                {(bundle.description || bundle.shortdescription) && (
                    <p>{bundle.description || bundle.shortdescription}</p>
                )}

                <div className={mcClasses("mc-bundle-stats")} aria-label={labels.coursesinbundle}>
                    <HeroStat
                        icon="bi-collection-play"
                        labelText={labels.coursesinbundle}
                        value={formatCount(bundle.coursecount)}
                    />
                    <HeroStat
                        icon="bi-box-seam"
                        labelText={label(labels, "producttype")}
                        value={typeLabel(bundle, labels)}
                    />
                    {price.savings && (
                        <HeroStat
                            icon="bi-tag"
                            labelText={labels.bundlesavings}
                            value={price.savings}
                        />
                    )}
                    <HeroStat
                        icon="bi-shield-check"
                        labelText={label(labels, "securecheckout")}
                        value={label(labels, "instantaccess")}
                    />
                </div>
            </div>
            <BundleMedia bundle={bundle} />
        </section>
    );
}

function CourseMeta({
    course,
    labels,
}: {
    course: Course;
    labels: Labels;
}) {
    return (
        <span className={mcClasses("mc-bundle-course__meta")}>
            {course.categoryname && <span>{course.categoryname}</span>}
            {course.duration && (
                <span>
                    <i className="bi bi-clock" aria-hidden="true" />
                    {course.duration}
                </span>
            )}
            {course.level && <span>{course.level}</span>}
            {course.activitycount > 0 && (
                <span>
                    {formatCount(course.activitycount)} {labels.activities || "activities"}
                </span>
            )}
        </span>
    );
}

function IncludedCourseRow({
    course,
    labels,
}: {
    course: Course;
    labels: Labels;
}) {
    const href = course.isenrolled ? course.courseviewurl : course.courseurl;
    const price = course.hassale ? course.saleprice : course.regularprice;

    return (
        <a className={mcClasses("mc-bundle-course")} href={href}>
            <CourseThumb course={course} />
            <span className={mcClasses("mc-bundle-course__body")}>
                <span className={mcClasses("mc-bundle-course__title")}>{course.name}</span>
                {course.summary && <span className={mcClasses("mc-bundle-course__summary")}>{course.summary}</span>}
                <CourseMeta course={course} labels={labels} />
            </span>
            <span className={mcClasses("mc-bundle-course__side")}>
                {course.isenrolled ? (
                    <span className={mcClasses("mc-badge mc-badge--success")}>{course.enrolledtext}</span>
                ) : (
                    <span className={mcClasses("mc-bundle-course__price")}>{price}</span>
                )}
                <i className="bi bi-arrow-right" aria-hidden="true" />
            </span>
        </a>
    );
}

function IncludedCourses({
    bundle,
    courses,
    labels,
}: {
    bundle: Bundle;
    courses: Course[];
    labels: Labels;
}) {
    return (
        <section className={mcClasses("mc-bundle-panel")} aria-labelledby="mc-bundle-courses-title">
            <div className={mcClasses("mc-bundle-panel__header")}>
                <span>
                    <span className={mcClasses("mc-bundle-panel__eyebrow")}>
                        {label(labels, "bundlecurriculum")}
                    </span>
                    <h2 id="mc-bundle-courses-title">{labels.coursesinbundle}</h2>
                </span>
                <span className={mcClasses("mc-bundle-course-count")}>{formatCount(bundle.coursecount)}</span>
            </div>

            {courses.length > 0 ? (
                <div className={mcClasses("mc-bundle-course-list")}>
                    {courses.map((course) => (
                        <IncludedCourseRow course={course} labels={labels} key={course.id} />
                    ))}
                </div>
            ) : (
                <div className={mcClasses("mc-empty mc-empty--compact")}>
                    <span className={mcClasses("mc-empty__icon")}>
                        <i className="bi bi-collection-play" aria-hidden="true" />
                    </span>
                    <p className={mcClasses("mc-empty__title")}>{labels.nocourses}</p>
                </div>
            )}
        </section>
    );
}

function PurchasePanel({
    bundle,
    price,
    state,
    urls,
    labels,
    wishlistBusy,
    onToggleWishlist,
}: {
    bundle: Bundle;
    price: Price;
    state: State;
    urls: Urls;
    labels: Labels;
    wishlistBusy: boolean;
    onToggleWishlist?: (saved: boolean) => void;
}) {
    const canBuy = state.isavailable && !state.accessallcourses && !state.purchased;
    const showLaunch = Boolean(urls.launch && (state.accessallcourses || state.purchased));
    const canSave = Boolean(onToggleWishlist) && state.isloggedin
        && !state.purchased && !state.accessallcourses && state.productid > 0;
    const wishlistTitle = state.inwishlist
        ? label(labels, "removefromwishlist")
        : label(labels, "savetowishlist");

    return (
        <aside className={mcClasses("mc-bundle-purchase")} aria-label={label(labels, "courseprice")}>
            <div className={mcClasses("mc-bundle-purchase__header")}>
                <span className={mcClasses("mc-bundle-purchase__type")}>{typeLabel(bundle, labels)}</span>
                <h2>{bundle.name}</h2>
            </div>

            <div className={mcClasses("mc-bundle-purchase__body")}>
                <div className={mcClasses("mc-bundle-price")}>
                    {price.hassale ? (
                        <>
                            <span className={mcClasses("mc-bundle-price__current")}>{price.saleprice}</span>
                            <span className={mcClasses("mc-bundle-price__original")}>{price.original}</span>
                            <span className={mcClasses("mc-price__sale")}>{labels.onsale}</span>
                        </>
                    ) : (
                        <span className={mcClasses("mc-bundle-price__current")}>{price.current}</span>
                    )}
                </div>

                {price.savings && (
                    <div className={mcClasses("mc-order-row mc-order-row--discount")}>
                        <span>{labels.bundlesavings}</span>
                        <span className={mcClasses("mc-order-row__value")}>{price.savings}</span>
                    </div>
                )}

                <div className={mcClasses("mc-order-row")}>
                    <span>{labels.coursesinbundle}</span>
                    <span className={mcClasses("mc-order-row__value")}>{formatCount(bundle.coursecount)}</span>
                </div>

                {state.hasownedcourses && !state.accessallcourses && (
                    <div className={mcClasses("mc-order-row")}>
                        <span>{labels.coursesowned}</span>
                        <span className={mcClasses("mc-order-row__value")}>
                            {formatCount(state.ownedcoursescount)} / {formatCount(state.totalcoursescount)}
                        </span>
                    </div>
                )}

                {state.accessallcourses && (
                    <div className={mcClasses("mc-alert mc-alert--success mt-3")} role="status">
                        <i className="bi bi-check-circle mc-alert__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{labels.accessallcourses}</div>
                    </div>
                )}

                {state.purchased && (
                    <div className={mcClasses("mc-alert mc-alert--info mt-3")} role="status">
                        <i className="bi bi-info-circle mc-alert__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{labels.bundlepurchased}</div>
                    </div>
                )}

                {showLaunch && (
                    <a href={urls.launch} className={mcClasses("mc-button btn-mc-primary w-100 d-flex justify-content-center mt-3")}>
                        <i className="bi bi-layers me-1" aria-hidden="true" />
                        {labels.viewincludedcourses || "View included courses"}
                    </a>
                )}

                {canBuy && (
                    <>
                        <a href={urls.addtocart} className={mcClasses("mc-button btn-mc-primary w-100 d-flex justify-content-center mt-3")}>
                            <i className="bi bi-cart-plus me-1" aria-hidden="true" />
                            {purchaseLabel(labels)}
                        </a>
                        {urls.checkout && (
                            <a href={urls.checkout} className={mcClasses("mc-button mc-btn-soft w-100 d-flex justify-content-center mt-2")}>
                                <i className="bi bi-bag-check me-1" aria-hidden="true" />
                                {label(labels, "buynow")}
                            </a>
                        )}
                    </>
                )}

                {canSave && (
                    <button
                        type="button"
                        className={mcClasses("mc-button mc-btn-soft w-100 d-flex justify-content-center mt-2")}
                        aria-pressed={state.inwishlist}
                        disabled={wishlistBusy}
                        onClick={() => onToggleWishlist?.(state.inwishlist)}
                        title={wishlistTitle}
                    >
                        <i
                            className={`bi ${state.inwishlist ? "bi-heart-fill" : "bi-heart"} me-1`}
                            aria-hidden="true"
                        />
                        {wishlistTitle}
                    </button>
                )}

                {!state.isavailable && (
                    <button className={mcClasses("mc-button btn-mc-secondary w-100 mt-3")} disabled type="button">
                        {labels.bundlenotavailable}
                    </button>
                )}

                <div className={mcClasses("mc-bundle-trust")}>
                    <span>
                        <i className="bi bi-shield-check" aria-hidden="true" />
                        {labels.securepayment}
                    </span>
                    <span>
                        <i className="bi bi-unlock" aria-hidden="true" />
                        {label(labels, "instantaccess")}
                    </span>
                </div>
            </div>
        </aside>
    );
}

export default function BundleDetails({
    methodName,
    wishlistUpdateMethodName,
    bundleId,
    labels,
}: BundleDetailsProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<BundleDetailsResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [wishlistBusy, setWishlistBusy] = useState(false);

    const toggleWishlist = async(saved: boolean) => {
        const productid = data?.state.productid ?? 0;
        if (!wishlistUpdateMethodName || productid <= 0 || !data?.state.isloggedin) {
            return;
        }

        setWishlistBusy(true);
        setError("");

        try {
            const result = await callMoodleService<{message: string}>(wishlistUpdateMethodName, {
                action: saved ? "remove" : "add",
                productid,
            });
            setData((current) => current
                ? {...current, state: {...current.state, inwishlist: !saved}}
                : current);
            toast.success(result.message || label(labels, saved ? "wishlistremoved" : "wishlistadded"));
        } catch (caught) {
            setError((caught as Error).message);
        } finally {
            setWishlistBusy(false);
        }
    };

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");

        callMoodleService<BundleDetailsResponse>(methodName, {id: bundleId})
            .then((result) => {
                if (cancelled) {
                    return result;
                }

                if (result.urls.launch) {
                    window.location.assign(result.urls.launch);
                    return result;
                }

                setData(result);
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
    }, [bundleId, methodName]);

    if (loading) {
        return <LoadingState labels={labels} />;
    }

    if (error || !data || !data.success) {
        return <ErrorState data={data} error={error} labels={labels} />;
    }

    const {bundle, price, state, urls, courses} = data;

    return (
        <div className={mcClasses("mc-bundle-details")}>
            <div className={mcClasses("mc-bundle-container")}>
                <nav aria-label={labels.catalog} className={mcClasses("mc-bundle-breadcrumb")}>
                    <a href={urls.catalog} className={mcClasses("mc-button mc-btn-soft")}>
                        <i className="bi bi-arrow-left me-1" aria-hidden="true" />
                        {labels.browsecatalog}
                    </a>
                </nav>

                <BundleHero bundle={bundle} price={price} state={state} labels={labels} />

                <div className={mcClasses("mc-bundle-layout")}>
                    <main className={mcClasses("mc-bundle-main")}>
                        <IncludedCourses bundle={bundle} courses={courses} labels={labels} />
                    </main>
                    <PurchasePanel
                        bundle={bundle}
                        price={price}
                        state={state}
                        urls={urls}
                        labels={labels}
                        wishlistBusy={wishlistBusy}
                        onToggleWishlist={
                            wishlistUpdateMethodName ? (saved) => void toggleWishlist(saved) : undefined
                        }
                    />
                </div>
            </div>
        </div>
    );
}

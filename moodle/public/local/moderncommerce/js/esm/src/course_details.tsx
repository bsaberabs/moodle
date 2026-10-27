// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React public course details page for Modern Commerce.
 *
 * @module     local_moderncommerce/course_details
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useState} from "react";
import {callMoodleService, formatCount, Labels, refreshNavbarCart} from "./learner_common";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";

type Course = {
    id: number;
    fullname: string;
    shortname: string;
    summary: string;
    imageurl: string;
    hasimage: boolean;
    categoryid: number;
    categoryname: string;
    courseurl: string;
};

type Price = {
    hasprice: boolean;
    isfree: boolean;
    hassale: boolean;
    current: string;
    original: string;
    discountpercentage: number;
    rawcurrent: number;
    raworiginal: number;
};

type Meta = {
    duration: string;
    hasduration: boolean;
    skilllevel: string;
    hasskilllevel: boolean;
    language: string;
    haslanguage: boolean;
    quizzescount: number;
    certificateenabled: boolean;
    featured: boolean;
    bestseller: boolean;
    trending: boolean;
};

type CourseState = {
    isloggedin: boolean;
    hasaccess: boolean;
    isavailable: boolean;
    canpurchase: boolean;
    isinsubscriptionplan: boolean;
    productid: number;
    inwishlist: boolean;
};

type Objective = {
    id: number;
    text: string;
};

type OutlineItem = {
    id: number;
    title: string;
    estimatedtime: string;
    hasestimatedtime: boolean;
    activitycount: number;
    hasactivitycount: boolean;
    imageurl: string;
    hasimage: boolean;
    icon: string;
};

type CourseDetailsResponse = {
    success: boolean;
    message: string;
    course: Course;
    price: Price;
    meta: Meta;
    state: CourseState;
    overview: {
        html: string;
        hasoverview: boolean;
    };
    objectives: Objective[];
    outline: OutlineItem[];
    urls: {
        catalog: string;
        cart: string;
        checkout: string;
        login: string;
        register: string;
        launch: string;
    };
};

type CartResponse = {
    success: boolean;
    message: string;
};

type Review = {
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
    timeformatted: string;
    hidden: boolean;
    likes: number;
    dislikes: number;
    loves: number;
    userreaction: number;
};

type RatingDistribution = {
    stars: number;
    count: number;
    percent: number;
};

type CourseReviewsResponse = {
    enabled: boolean;
    summary: {
        reviewcount: number;
        avgrating: number;
        displayavgrating: string;
    };
    ratingdist: RatingDistribution[];
    reviews: Review[];
    total: number;
    page: number;
    perpage: number;
    userhasreviewed: boolean;
    canreview: boolean;
};

type ReviewSummary = CourseReviewsResponse["summary"];

type ReviewSubmitResponse = {
    success: boolean;
    message: string;
};

type ReviewReactionResponse = {
    success: boolean;
    message: string;
    reactions: {
        likes: number;
        dislikes: number;
        loves: number;
    };
    userreaction: number;
};

type SidebarPosition = "left" | "right";

type CourseDetailsProps = {
    methodName: string;
    cartMethodName: string;
    wishlistUpdateMethodName?: string;
    reviewsMethodName: string;
    submitReviewMethodName: string;
    reactionMethodName: string;
    courseId: number;
    sidebarPosition?: SidebarPosition;
    labels: Labels;
};

type Feature = {
    icon: string;
    label: string;
    value: string;
};

const label = (labels: Labels, key: string): string => labels[key] || key;

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-course-details mc-course-details--loading")}>
            <div className={mcClasses("mc-course-container")}>
                <div className={mcClasses("mc-empty mc-empty--centered")}>
                    <span className={mcClasses("mc-empty__icon")}>
                        <i className="bi bi-mortarboard" aria-hidden="true" />
                    </span>
                    <p className={mcClasses("mc-empty__title")}>{labels.loading}</p>
                </div>
            </div>
        </div>
    );
}

function ErrorState({
    data,
    error,
    labels,
}: {
    data: CourseDetailsResponse | null;
    error: string;
    labels: Labels;
}) {
    const message = error || data?.message || labels.coursenotavailable;
    const catalogUrl = data?.urls.catalog || "#";

    return (
        <div className={mcClasses("mc-course-details")}>
            <div className={mcClasses("mc-course-container")}>
                <div className={mcClasses("mc-alert mc-alert--warning")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{message}</div>
                </div>
                <a className={mcClasses("mc-button mc-btn-soft mc-course-backlink")} href={catalogUrl}>
                    <i className="bi bi-arrow-left" aria-hidden="true" />
                    {labels.browsecatalog}
                </a>
            </div>
        </div>
    );
}

function CourseMedia({course}: {course: Course}) {
    const [imageFailed, setImageFailed] = useState(false);
    const hasImage = course.hasimage && course.imageurl && !imageFailed;

    return (
        <div className={mcClasses("mc-course-hero__media")}>
            {hasImage ? (
                <img
                    src={course.imageurl}
                    alt={course.fullname}
                    width={760}
                    height={428}
                    loading="eager"
                    onError={() => setImageFailed(true)}
                />
            ) : (
                <div className={mcClasses("mc-course-media-fallback")} aria-hidden="true">
                    <i className="bi bi-mortarboard" />
                </div>
            )}
        </div>
    );
}

function BadgeList({course, meta, labels}: {course: Course; meta: Meta; labels: Labels}) {
    return (
        <div className={mcClasses("mc-course-badges")}>
            {course.categoryname && (
                <span className={mcClasses("mc-badge mc-badge--neutral")}>{course.categoryname}</span>
            )}
            {meta.featured && (
                <span className={mcClasses("mc-badge mc-badge--success")}>{labels.featured}</span>
            )}
            {meta.bestseller && (
                <span className={mcClasses("mc-badge mc-badge--warning")}>{labels.bestseller}</span>
            )}
            {meta.trending && (
                <span className={mcClasses("mc-badge mc-badge--primary")}>{labels.trending}</span>
            )}
        </div>
    );
}

function HeroMetric({
    icon,
    labelText,
    value,
}: {
    icon: string;
    labelText: string;
    value: string;
}) {
    return (
        <span className={mcClasses("mc-course-hero-metric")}>
            <i className={`bi ${icon}`} aria-hidden="true" />
            <span>
                <span className={mcClasses("mc-course-hero-metric__label")}>{labelText}</span>
                <strong>{value}</strong>
            </span>
        </span>
    );
}

function CourseHero({data, labels}: {data: CourseDetailsResponse; labels: Labels}) {
    const {course, meta, outline, overview} = data;
    const heroSummary = course.summary || (overview.hasoverview ? overview.html : "");
    const activityCount = outline.reduce((total, item) => total + item.activitycount, 0);

    const tertiaryMetric = meta.hasduration
        ? {icon: "bi-clock", labelText: labels.duration, value: meta.duration}
        : meta.hasskilllevel
            ? {icon: "bi-bar-chart", labelText: labels.level, value: meta.skilllevel}
            : {icon: "bi-box-seam", labelText: labels.producttype, value: labels.course};

    return (
        <section className={mcClasses("mc-course-hero")} aria-labelledby="mc-course-title">
            <div className={mcClasses("mc-course-hero__content")}>
                <BadgeList course={course} meta={meta} labels={labels} />
                <h1 id="mc-course-title">{course.fullname}</h1>
                {heroSummary && (
                    <div
                        className={mcClasses("mc-course-hero__summary")}
                        dangerouslySetInnerHTML={{__html: heroSummary}}
                    />
                )}
                <div className={mcClasses("mc-course-hero__metrics")} aria-label={labels.courseinformation}>
                    <HeroMetric icon="bi-list-check" labelText={labels.courseoutline} value={formatCount(outline.length)} />
                    <HeroMetric icon="bi-collection-play" labelText={labels.activities} value={formatCount(activityCount)} />
                    <HeroMetric icon={tertiaryMetric.icon} labelText={tertiaryMetric.labelText} value={tertiaryMetric.value} />
                </div>
            </div>
            <CourseMedia course={course} />
        </section>
    );
}

function buildFeatures(data: CourseDetailsResponse, labels: Labels): Feature[] {
    const {meta} = data;
    const features: Feature[] = [
        {
            icon: "bi-box-seam",
            label: labels.producttype,
            value: labels.course,
        },
    ];

    if (meta.hasduration) {
        features.push({icon: "bi-clock", label: labels.duration, value: meta.duration});
    }

    if (meta.hasskilllevel) {
        features.push({icon: "bi-bar-chart", label: labels.level, value: meta.skilllevel});
    }

    if (meta.haslanguage) {
        features.push({icon: "bi-translate", label: labels.language, value: meta.language});
    }

    if (meta.quizzescount > 0) {
        features.push({icon: "bi-question-circle", label: labels.quizzes, value: formatCount(meta.quizzescount)});
    }

    if (meta.certificateenabled) {
        features.push({icon: "bi-award", label: labels.certificate, value: labels.certificate});
    }

    return features;
}

function PriceBlock({price, labels}: {price: Price; labels: Labels}) {
    if (!price.hasprice) {
        return null;
    }

    return (
        <div className={mcClasses("mc-course-price")} aria-label={labels.courseprice}>
            <span className={mcClasses(`mc-course-price__current ${price.isfree ? "mc-course-price__current--free" : ""}`)}>
                {price.current}
            </span>
            {price.hassale && (
                <span className={mcClasses("mc-course-price__sale")}>
                    <span className={mcClasses("mc-course-price__original")}>{price.original}</span>
                    <span className={mcClasses("mc-price__sale")}>{labels.onsale}</span>
                </span>
            )}
        </div>
    );
}

function FeatureItem({feature}: {feature: Feature}) {
    return (
        <div className={mcClasses("mc-course-feature")}>
            <span className={mcClasses("mc-course-feature__icon")}>
                <i className={`bi ${feature.icon}`} aria-hidden="true" />
            </span>
            <span className={mcClasses("mc-course-feature__body")}>
                <span className={mcClasses("mc-course-feature__label")}>{feature.label}</span>
                <strong>{feature.value}</strong>
            </span>
        </div>
    );
}

function PurchasePanel({
    data,
    labels,
    busy,
    showCartLink,
    onAddToCart,
    onLoginRequired,
    wishlistBusy,
    onToggleWishlist,
}: {
    data: CourseDetailsResponse;
    labels: Labels;
    busy: boolean;
    showCartLink: boolean;
    onAddToCart: () => void;
    onLoginRequired: () => void;
    wishlistBusy: boolean;
    onToggleWishlist?: (saved: boolean) => void;
}) {
    const {price, state, urls} = data;
    const canBuy = state.canpurchase && state.isavailable && !state.hasaccess;
    const features = buildFeatures(data, labels);
    const canSave = Boolean(onToggleWishlist) && state.isloggedin && !state.hasaccess && state.productid > 0;
    const wishlistTitle = state.inwishlist
        ? label(labels, "removefromwishlist")
        : label(labels, "savetowishlist");

    return (
        <aside className={mcClasses("mc-course-sidebar-card")} aria-label={labels.courseinformation}>
            <div className={mcClasses("mc-course-sidebar-card__header")}>
                <span className={mcClasses("mc-course-sidebar-card__label")}>{labels.courseprice}</span>
                <PriceBlock price={price} labels={labels} />
            </div>

            <div className={mcClasses("mc-course-actions")}>
                {state.hasaccess && urls.launch && (
                    <a href={urls.launch} className={mcClasses("mc-button btn-mc-primary mc-course-action")}>
                        <i className="bi bi-play-circle" aria-hidden="true" />
                        {labels.takecourse}
                    </a>
                )}

                {canBuy && (
                    <>
                        <button
                            type="button"
                            className={mcClasses("mc-button btn-mc-primary mc-course-action")}
                            disabled={busy}
                            onClick={state.isloggedin ? onAddToCart : onLoginRequired}
                        >
                            <i className="bi bi-cart-plus" aria-hidden="true" />
                            {labels.addtocart}
                        </button>
                        <a
                            href={state.isloggedin ? urls.checkout : urls.login}
                            className={mcClasses("mc-button mc-btn-soft mc-course-action")}
                        >
                            <i className="bi bi-bag-check" aria-hidden="true" />
                            {labels.buynow}
                        </a>
                    </>
                )}

                {canSave && (
                    <button
                        type="button"
                        className={mcClasses("mc-button mc-btn-soft mc-course-action")}
                        aria-pressed={state.inwishlist}
                        disabled={wishlistBusy}
                        onClick={() => onToggleWishlist?.(state.inwishlist)}
                        title={wishlistTitle}
                    >
                        <i
                            className={`bi ${state.inwishlist ? "bi-heart-fill" : "bi-heart"}`}
                            aria-hidden="true"
                        />
                        {wishlistTitle}
                    </button>
                )}

                {showCartLink && (
                    <a href={urls.cart} className={mcClasses("mc-button mc-btn-soft mc-course-action")}>
                        <i className="bi bi-cart" aria-hidden="true" />
                        {labels.cart}
                    </a>
                )}
            </div>

            {!state.isloggedin && (
                <div className={mcClasses("mc-alert mc-alert--info mc-course-sidebar-alert")} role="status">
                    <i className="bi bi-info-circle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>
                        <strong>{labels.loginrequired}</strong>
                        <span>{labels.loginrequiredmessage}</span>
                        <span className={mcClasses("mc-course-sidebar-alert__actions")}>
                            <a href={urls.login} className={mcClasses("mc-button mc-btn-soft")}>
                                {labels.login}
                            </a>
                            <a href={urls.register} className={mcClasses("mc-button btn-mc-primary")}>
                                {labels.startsignup}
                            </a>
                        </span>
                    </div>
                </div>
            )}

            {!state.hasaccess && !canBuy && (
                <div className={mcClasses("mc-alert mc-alert--neutral mc-course-sidebar-alert")} role="status">
                    <i className="bi bi-info-circle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{labels.coursenotavailable}</div>
                </div>
            )}

            <section className={mcClasses("mc-course-feature-list")} aria-labelledby="mc-course-features-title">
                <h2 id="mc-course-features-title">{labels.productfeatures}</h2>
                <div>
                    {features.map((feature) => (
                        <FeatureItem feature={feature} key={`${feature.label}-${feature.value}`} />
                    ))}
                </div>
            </section>

            <div className={mcClasses("mc-course-trust")}>
                <span>
                    <i className="bi bi-shield-check" aria-hidden="true" />
                    {labels.securepayment}
                </span>
                <span>
                    <i className="bi bi-unlock" aria-hidden="true" />
                    {labels.instantaccess}
                </span>
            </div>
        </aside>
    );
}

function SectionHeader({
    id,
    title,
    count,
}: {
    id: string;
    title: string;
    count?: string;
}) {
    return (
        <div className={mcClasses("mc-course-section__header")}>
            <h2 id={id}>{title}</h2>
            {count && <span className={mcClasses("mc-course-section__count")}>{count}</span>}
        </div>
    );
}

function Overview({data, labels}: {data: CourseDetailsResponse; labels: Labels}) {
    if (!data.overview.hasoverview && data.objectives.length === 0) {
        return null;
    }

    return (
        <section className={mcClasses("mc-course-section mc-course-overview")} aria-labelledby="mc-course-overview-title">
            <SectionHeader id="mc-course-overview-title" title={labels.overview} />
            <div className={mcClasses("mc-course-overview__body")}>
                {data.overview.hasoverview && (
                    <div dangerouslySetInnerHTML={{__html: data.overview.html}} />
                )}

                {data.objectives.length > 0 && (
                    <div className={mcClasses("mc-course-objectives")}>
                        <h3>{labels.whatyoulllearn}</h3>
                        <ul>
                            {data.objectives.map((objective) => (
                                <li key={objective.id}>
                                    <i className="bi bi-check-circle" aria-hidden="true" />
                                    <span>{objective.text}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </section>
    );
}

function OutlineThumb({item}: {item: OutlineItem}) {
    const [imageFailed, setImageFailed] = useState(false);
    const hasImage = item.hasimage && item.imageurl && !imageFailed;

    if (hasImage) {
        return (
            <img
                src={item.imageurl}
                alt=""
                className={mcClasses("mc-course-outline__thumb")}
                width={88}
                height={58}
                loading="lazy"
                onError={() => setImageFailed(true)}
            />
        );
    }

    return (
        <span className={mcClasses("mc-course-outline__thumb")} aria-hidden="true">
            <i className={`bi ${item.icon || "bi-journal-text"}`} />
        </span>
    );
}

function Outline({items, labels}: {items: OutlineItem[]; labels: Labels}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <section className={mcClasses("mc-course-section mc-course-outline")} aria-labelledby="mc-course-outline-title">
            <SectionHeader id="mc-course-outline-title" title={labels.courseoutline} count={formatCount(items.length)} />
            <div className={mcClasses("mc-course-outline__list")}>
                {items.map((item) => (
                    <article className={mcClasses("mc-course-outline-row")} key={item.id}>
                        <OutlineThumb item={item} />
                        <div className={mcClasses("mc-course-outline-row__body")}>
                            <h3>{item.title}</h3>
                            <div className={mcClasses("mc-course-outline-row__meta")}>
                                {item.hasestimatedtime && (
                                    <span>
                                        <i className="bi bi-clock" aria-hidden="true" />
                                        {item.estimatedtime}
                                    </span>
                                )}
                                {item.hasactivitycount && (
                                    <span>
                                        <i className="bi bi-collection-play" aria-hidden="true" />
                                        {formatCount(item.activitycount)} {labels.activities}
                                    </span>
                                )}
                            </div>
                        </div>
                    </article>
                ))}
            </div>
        </section>
    );
}

function RatingStars({rating}: {rating: number}) {
    const rounded = Math.round(rating);

    return (
        <span className={mcClasses("mc-course-rating-stars")} aria-hidden="true">
            {[1, 2, 3, 4, 5].map((star) => (
                <i className={`bi ${star <= rounded ? "bi-star-fill" : "bi-star"}`} key={star} />
            ))}
        </span>
    );
}

function RatingInput({
    labels,
    rating,
    onRatingChange,
}: {
    labels: Labels;
    rating: number;
    onRatingChange: (rating: number) => void;
}) {
    return (
        <div className={mcClasses("mc-course-rating-input")} role="group" aria-label={labels.rating}>
            {[1, 2, 3, 4, 5].map((star) => (
                <button
                    type="button"
                    className={mcClasses(star <= rating ? "is-active" : "")}
                    data-mc-button={star <= rating ? "primary" : "soft"}
                    key={star}
                    onClick={() => onRatingChange(star)}
                    aria-label={`${labels.rating}: ${star}`}
                    aria-pressed={star <= rating}
                >
                    <i className="bi bi-star-fill" aria-hidden="true" />
                    <span>{star}</span>
                </button>
            ))}
        </div>
    );
}

function RatingDistributionRows({
    rows,
    labels,
}: {
    rows: RatingDistribution[];
    labels: Labels;
}) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <div className={mcClasses("mc-course-rating-distribution")} aria-label={labels.ratingdistribution}>
            {rows.map((row) => (
                <div className={mcClasses("mc-course-rating-row")} key={row.stars}>
                    <span className={mcClasses("mc-course-rating-row__label")}>
                        {row.stars}
                        <i className="bi bi-star-fill" aria-hidden="true" />
                    </span>
                    <progress max={100} value={row.percent} />
                    <span className={mcClasses("mc-course-rating-row__count")}>{formatCount(row.count)}</span>
                </div>
            ))}
        </div>
    );
}

function ReviewSummaryTiles({summary, labels}: {summary: ReviewSummary; labels: Labels}) {
    const ratingValue = `${summary.displayavgrating} ${label(labels, "outof5")}`;

    return (
        <div
            className={mcClasses("mc-stat-strip mc-stat-strip--grid mc-learner-stat-strip mc-course-review-summary-tiles")}
            role="list"
        >
            <article
                className={mcClasses("mc-stat-tile mc-learner-stat-tile mc-stat-tile--warning mc-stat-tile--span6")}
                role="listitem"
            >
                <i className="bi bi-star-fill mc-stat-tile__icon" aria-hidden="true" />
                <div className={mcClasses("mc-stat-tile__body")}>
                    <span className={mcClasses("mc-stat-tile__label")}>{labels.averagerating}</span>
                    <strong className={mcClasses("mc-stat-tile__value")}>{ratingValue}</strong>
                </div>
                <i className="bi bi-star-fill mc-stat-tile__watermark" aria-hidden="true" />
            </article>
            <article
                className={mcClasses("mc-stat-tile mc-learner-stat-tile mc-stat-tile--info mc-stat-tile--span6")}
                role="listitem"
            >
                <i className="bi bi-chat-square-text mc-stat-tile__icon" aria-hidden="true" />
                <div className={mcClasses("mc-stat-tile__body")}>
                    <span className={mcClasses("mc-stat-tile__label")}>{labels.reviews}</span>
                    <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(summary.reviewcount)}</strong>
                </div>
                <i className="bi bi-chat-square-text mc-stat-tile__watermark" aria-hidden="true" />
            </article>
        </div>
    );
}

function ReactionButton({
    icon,
    labelText,
    active,
    count,
    disabled,
    onClick,
}: {
    icon: string;
    labelText: string;
    active: boolean;
    count: number;
    disabled: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            className={mcClasses(`mc-course-reaction ${active ? "is-active" : ""}`)}
            data-mc-button={active ? "primary" : "soft"}
            disabled={disabled}
            onClick={onClick}
            title={labelText}
            aria-label={`${labelText} ${formatCount(count)}`}
        >
            <i className={`bi ${icon}`} aria-hidden="true" />
            <span>{formatCount(count)}</span>
        </button>
    );
}

function ReviewsPanel({
    data,
    labels,
    isLoggedIn,
    loading,
    busy,
    rating,
    comment,
    message,
    error,
    onRatingChange,
    onCommentChange,
    onSubmit,
    onReaction,
    onLoadMore,
}: {
    data: CourseReviewsResponse | null;
    labels: Labels;
    isLoggedIn: boolean;
    loading: boolean;
    busy: boolean;
    rating: number;
    comment: string;
    message: string;
    error: string;
    onRatingChange: (rating: number) => void;
    onCommentChange: (comment: string) => void;
    onSubmit: () => void;
    onReaction: (reviewid: number, reaction: number) => void;
    onLoadMore: () => void;
}) {
    if (!data?.enabled && !loading && !error) {
        return null;
    }

    const summary = data?.summary ?? {reviewcount: 0, avgrating: 0, displayavgrating: "0.0"};
    const reviews = data?.reviews ?? [];
    const hasMore = Boolean(data && data.reviews.length < data.total);
    const canShowForm = Boolean(data?.canreview && !data.userhasreviewed);

    return (
        <section className={mcClasses("mc-course-section mc-course-reviews")} aria-labelledby="mc-course-reviews-title">
            <SectionHeader id="mc-course-reviews-title" title={labels.reviews} count={formatCount(summary.reviewcount)} />

            {loading && (
                <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
            )}

            {!loading && (
                <>
                    <div className={mcClasses("mc-course-rating-summary")} aria-label={labels.reviewsummary}>
                        <div className={mcClasses("mc-course-rating-summary__row mc-course-rating-summary__row--metrics")}>
                            <ReviewSummaryTiles summary={summary} labels={labels} />
                        </div>
                        <div className={mcClasses("mc-course-rating-summary__row mc-course-rating-summary__row--distribution")}>
                            <RatingDistributionRows rows={data?.ratingdist ?? []} labels={labels} />
                        </div>
                    </div>

                    {(message || error) && (
                        <div className={mcClasses(`mc-alert ${error ? "mc-alert--danger" : "mc-alert--success"}`)} role="alert">
                            <i className={`bi ${error ? "bi-exclamation-triangle" : "bi-check-circle"} mc-alert__icon`} aria-hidden="true" />
                            <div className={mcClasses("mc-alert__body")}>{error || message}</div>
                        </div>
                    )}

                    {canShowForm && (
                        <div className={mcClasses("mc-course-review-form")}>
                            <h3>{label(labels, "writeareview")}</h3>
                            <RatingInput labels={labels} rating={rating} onRatingChange={onRatingChange} />
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-course-review-comment">
                                {labels.comment}
                            </label>
                            <textarea
                                id="mc-course-review-comment"
                                className="form-control"
                                rows={4}
                                value={comment}
                                placeholder={label(labels, "reviewcommentplaceholder")}
                                onChange={(event) => onCommentChange(event.currentTarget.value)}
                            />
                            <McButton
                                type="button"
                                className={mcClasses("btn-mc-primary")}
                                loading={busy}
                                loadingLabel={label(labels, "loading")}
                                onClick={onSubmit}
                            >
                                <i className="bi bi-send" aria-hidden="true" />
                                {label(labels, "submitreview")}
                            </McButton>
                        </div>
                    )}

                    {data?.userhasreviewed && (
                        <div className={mcClasses("mc-alert mc-alert--neutral")} role="status">
                            <i className="bi bi-check-circle mc-alert__icon" aria-hidden="true" />
                            <div className={mcClasses("mc-alert__body")}>{label(labels, "alreadyreviewed")}</div>
                        </div>
                    )}

                    {!canShowForm && isLoggedIn && !data?.userhasreviewed && (
                        <div className={mcClasses("mc-alert mc-alert--neutral")} role="status">
                            <i className="bi bi-info-circle mc-alert__icon" aria-hidden="true" />
                            <div className={mcClasses("mc-alert__body")}>{label(labels, "cannotreviewcourse")}</div>
                        </div>
                    )}

                    {reviews.length === 0 ? (
                        <div className={mcClasses("mc-empty mc-empty--centered")}>
                            <span className={mcClasses("mc-empty__icon")}>
                                <i className="bi bi-star" aria-hidden="true" />
                            </span>
                            <p className={mcClasses("mc-empty__title")}>{labels.noreviews}</p>
                        </div>
                    ) : (
                        <div className={mcClasses("mc-course-review-list")}>
                            {reviews.map((review) => (
                                <article className={mcClasses("mc-course-review")} key={review.id}>
                                    {review.userimage ? (
                                        <img
                                            src={review.userimage}
                                            alt=""
                                            className={mcClasses("mc-course-review__avatar")}
                                            width={48}
                                            height={48}
                                            loading="lazy"
                                        />
                                    ) : (
                                        <span className={mcClasses("mc-course-review__avatar")} aria-hidden="true">
                                            <i className="bi bi-person" />
                                        </span>
                                    )}
                                    <div className={mcClasses("mc-course-review__body")}>
                                        <div className={mcClasses("mc-course-review__head")}>
                                            <strong>{review.username}</strong>
                                            <span>{review.timeformatted}</span>
                                        </div>
                                        <RatingStars rating={review.rating} />
                                        <p>{review.comment}</p>
                                        <div className={mcClasses("mc-course-review__reactions")}>
                                            <ReactionButton
                                                icon="bi-hand-thumbs-up"
                                                labelText={labels.like}
                                                active={review.userreaction === 1}
                                                count={review.likes}
                                                disabled={!isLoggedIn || busy}
                                                onClick={() => onReaction(review.id, 1)}
                                            />
                                            <ReactionButton
                                                icon="bi-hand-thumbs-down"
                                                labelText={labels.dislike}
                                                active={review.userreaction === 2}
                                                count={review.dislikes}
                                                disabled={!isLoggedIn || busy}
                                                onClick={() => onReaction(review.id, 2)}
                                            />
                                            <ReactionButton
                                                icon="bi-heart"
                                                labelText={labels.love}
                                                active={review.userreaction === 3}
                                                count={review.loves}
                                                disabled={!isLoggedIn || busy}
                                                onClick={() => onReaction(review.id, 3)}
                                            />
                                        </div>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}

                    {hasMore && (
                        <button type="button" className={mcClasses("mc-button mc-btn-soft")} disabled={busy} onClick={onLoadMore}>
                            <i className="bi bi-chevron-down" aria-hidden="true" />
                            {label(labels, "loadmore")}
                        </button>
                    )}
                </>
            )}
        </section>
    );
}

export default function CourseDetails({
    methodName,
    cartMethodName,
    wishlistUpdateMethodName,
    reviewsMethodName,
    submitReviewMethodName,
    reactionMethodName,
    courseId,
    sidebarPosition = "right",
    labels,
}: CourseDetailsProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<CourseDetailsResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");
    const [busy, setBusy] = useState(false);
    const [wishlistBusy, setWishlistBusy] = useState(false);
    const [reviewsData, setReviewsData] = useState<CourseReviewsResponse | null>(null);
    const [reviewsLoading, setReviewsLoading] = useState(true);
    const [reviewBusy, setReviewBusy] = useState(false);
    const [reviewRating, setReviewRating] = useState(0);
    const [reviewComment, setReviewComment] = useState("");
    const [reviewMessage, setReviewMessage] = useState("");
    const [reviewError, setReviewError] = useState("");
    const layoutPosition = sidebarPosition === "left" ? "left" : "right";

    const loadReviews = useCallback(async(page = 0, append = false) => {
        if (append) {
            setReviewBusy(true);
        } else {
            setReviewsLoading(true);
        }
        setReviewError("");

        try {
            const result = await callMoodleService<CourseReviewsResponse>(reviewsMethodName, {
                courseid: courseId,
                page,
                perpage: 10,
            });
            setReviewsData((current) => {
                if (!append || !current) {
                    return result;
                }

                return {
                    ...result,
                    reviews: [...current.reviews, ...result.reviews],
                };
            });
        } catch (caught) {
            setReviewError((caught as Error).message);
        } finally {
            if (append) {
                setReviewBusy(false);
            } else {
                setReviewsLoading(false);
            }
        }
    }, [courseId, reviewsMethodName]);

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");

        callMoodleService<CourseDetailsResponse>(methodName, {id: courseId})
            .then((result) => {
                if (cancelled) {
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
    }, [courseId, methodName]);

    useEffect(() => {
        void loadReviews();
    }, [loadReviews]);

    const addToCart = async() => {
        if (!data?.state.isloggedin) {
            setMessage(labels.loginrequiredmessage);
            return;
        }

        setBusy(true);
        setError("");
        setMessage("");

        try {
            const result = await callMoodleService<CartResponse>(cartMethodName, {
                action: "addcourse",
                courseid: courseId,
            });
            void refreshNavbarCart(result);
            setMessage(result.message);
        } catch (caught) {
            setError((caught as Error).message);
        } finally {
            setBusy(false);
        }
    };

    const toggleWishlist = async(saved: boolean) => {
        const productid = data?.state.productid ?? 0;
        if (!wishlistUpdateMethodName || productid <= 0) {
            return;
        }

        if (!data?.state.isloggedin) {
            setMessage(labels.loginrequiredmessage);
            return;
        }

        setWishlistBusy(true);
        setError("");
        setMessage("");

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

    const submitReview = async() => {
        if (reviewRating < 1 || reviewRating > 5) {
            setReviewError(label(labels, "error:invalidrating"));
            return;
        }

        if (reviewComment.trim() === "") {
            setReviewError(label(labels, "commentrequired"));
            return;
        }

        // A one-character review is noise; the server caps the upper bound too.
        if (reviewComment.trim().length < 10) {
            setReviewError(label(labels, "error:minlength").replace("{$a}", "10"));
            return;
        }

        if (reviewComment.trim().length > 2000) {
            setReviewError(label(labels, "error:maxlength").replace("{$a}", "2000"));
            return;
        }

        setReviewBusy(true);
        setReviewError("");
        setReviewMessage("");

        try {
            const result = await callMoodleService<ReviewSubmitResponse>(submitReviewMethodName, {
                courseid: courseId,
                rating: reviewRating,
                comment: reviewComment,
            });
            if (!result.success) {
                setReviewError(result.message);
                return;
            }

            setReviewMessage(result.message);
            setReviewRating(0);
            setReviewComment("");
            await loadReviews(0, false);
        } catch (caught) {
            setReviewError((caught as Error).message);
        } finally {
            setReviewBusy(false);
        }
    };

    const setReaction = async(reviewid: number, reaction: number) => {
        if (!data?.state.isloggedin) {
            setReviewError(labels.loginrequiredmessage);
            return;
        }

        setReviewBusy(true);
        setReviewError("");

        try {
            const result = await callMoodleService<ReviewReactionResponse>(reactionMethodName, {
                reviewid,
                reaction,
            });
            setReviewMessage(result.message);
            setReviewsData((current) => {
                if (!current) {
                    return current;
                }

                return {
                    ...current,
                    reviews: current.reviews.map((review) => review.id === reviewid ? {
                        ...review,
                        likes: result.reactions.likes,
                        dislikes: result.reactions.dislikes,
                        loves: result.reactions.loves,
                        userreaction: result.userreaction,
                    } : review),
                };
            });
        } catch (caught) {
            setReviewError((caught as Error).message);
        } finally {
            setReviewBusy(false);
        }
    };

    if (loading) {
        return <LoadingState labels={labels} />;
    }

    if (error || !data || !data.success) {
        return <ErrorState data={data} error={error} labels={labels} />;
    }

    return (
        <div className={mcClasses(`mc-course-details mc-course-details--sidebar-${layoutPosition}`)}>
            <div className={mcClasses("mc-course-container")}>
                <nav aria-label={labels.catalog} className={mcClasses("mc-course-breadcrumb")}>
                    <a href={data.urls.catalog} className={mcClasses("mc-button mc-btn-soft mc-course-backlink")}>
                        <i className="bi bi-arrow-left" aria-hidden="true" />
                        {labels.browsecatalog}
                    </a>
                </nav>

                {(message || error) && (
                    <div className={mcClasses(`mc-alert ${error ? "mc-alert--danger" : "mc-alert--success"}`)} role="alert">
                        <i className={`bi ${error ? "bi-exclamation-triangle" : "bi-check-circle"} mc-alert__icon`} aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{error || message}</div>
                    </div>
                )}

                <CourseHero data={data} labels={labels} />

                <div className={mcClasses("mc-course-layout")}>
                    <aside className={mcClasses("mc-course-layout__sidebar")}>
                        <PurchasePanel
                            data={data}
                            labels={labels}
                            busy={busy}
                            showCartLink={Boolean(message)}
                            onAddToCart={() => void addToCart()}
                            onLoginRequired={() => setMessage(labels.loginrequiredmessage)}
                            wishlistBusy={wishlistBusy}
                            onToggleWishlist={
                                wishlistUpdateMethodName ? (saved) => void toggleWishlist(saved) : undefined
                            }
                        />
                    </aside>

                    <main className={mcClasses("mc-course-layout__main")} aria-label={labels.coursedetailsoverview}>
                        <Overview data={data} labels={labels} />
                        <Outline items={data.outline} labels={labels} />
                        <ReviewsPanel
                            data={reviewsData}
                            labels={labels}
                            isLoggedIn={data.state.isloggedin}
                            loading={reviewsLoading}
                            busy={reviewBusy}
                            rating={reviewRating}
                            comment={reviewComment}
                            message={reviewMessage}
                            error={reviewError}
                            onRatingChange={setReviewRating}
                            onCommentChange={setReviewComment}
                            onSubmit={() => void submitReview()}
                            onReaction={(reviewid, reaction) => void setReaction(reviewid, reaction)}
                            onLoadMore={() => void loadReviews((reviewsData?.page ?? 0) + 1, true)}
                        />
                    </main>
                </div>
            </div>
        </div>
    );
}

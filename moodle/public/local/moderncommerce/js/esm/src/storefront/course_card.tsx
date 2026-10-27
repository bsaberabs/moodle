// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Shared course image card for storefront product widgets.
 *
 * @module     local_moderncommerce/storefront/course_card
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties} from "react";
import {McButton} from "../button";

export type CourseCardItem = {
    id: number;
    productid: number;
    inwishlist: boolean;
    itemtype: string;
    title: string;
    thumbnail: string;
    alt: string;
    category: string;
    coursetype: string;
    level: string;
    duration: string;
    rating: number;
    reviewcount: number;
    displayprice: string;
    displayoriginalprice: string;
    hasoriginalprice: boolean;
    isbundle: boolean;
    isprogram: boolean;
    bestseller: boolean;
    hasaccess: boolean;
    accessurl: string;
    detailsurl: string;
};

type Labels = Record<string, string>;

const badgeClass = (item: CourseCardItem): string => {
    if (item.isprogram) {
        return "mc-badge-program";
    }
    if (item.isbundle) {
        return "mc-badge-bundle";
    }
    return "mc-badge--neutral";
};

function RatingStars({rating}: {rating: number}) {
    const rounded = Math.round(rating);

    return (
        <span className="mc-rating-stars d-inline-flex align-items-center gap-1 text-warning" aria-hidden="true">
            {[1, 2, 3, 4, 5].map((star) => (
                <i className={`bi ${star <= rounded ? "bi-star-fill" : "bi-star"}`} key={star} />
            ))}
        </span>
    );
}

export default function CourseImageCard({
    item,
    labels,
    busy,
    onAdd,
    style,
    wishlistBusy = false,
    wishlistSaved = false,
    onToggleWishlist,
}: {
    item: CourseCardItem;
    labels: Labels;
    busy: boolean;
    onAdd: (item: CourseCardItem) => void;
    style?: CSSProperties;
    wishlistBusy?: boolean;
    wishlistSaved?: boolean;
    onToggleWishlist?: (item: CourseCardItem, saved: boolean) => void;
}) {
    const wishlistTitle = wishlistSaved
        ? (labels.removefromwishlist || "Remove from wishlist")
        : (labels.savetowishlist || "Save to wishlist");

    return (
        <article className={`mc-course-card${item.hasaccess ? " mc-course-card--owned" : ""}`} style={style}>
            <div className="mc-course-card-image">
                {item.thumbnail ? (
                    <img src={item.thumbnail} alt={item.alt || item.title} width={480} height={270} loading="lazy" />
                ) : (
                    <div className="w-100 h-100 bg-light" aria-hidden="true" />
                )}
                <div className="mc-course-card-badges">
                    <span className={`mc-badge mc-course-card-image-badge ${badgeClass(item)}`}>
                        {item.coursetype}
                    </span>
                    {item.bestseller && (
                        <span className="mc-badge mc-course-card-image-badge mc-badge-bestseller">
                            {labels.bestseller}
                        </span>
                    )}
                </div>
                {onToggleWishlist && !item.hasaccess && (
                    <div className="mc-course-card-actions">
                        <button
                            type="button"
                            className="mc-button mc-course-card-wishlist"
                            data-mc-button="ghost"
                            data-mc-button-size="icon"
                            aria-pressed={wishlistSaved}
                            disabled={wishlistBusy}
                            onClick={() => onToggleWishlist(item, wishlistSaved)}
                            aria-label={wishlistTitle}
                            title={wishlistTitle}
                        >
                            <i className={`bi ${wishlistSaved ? "bi-heart-fill" : "bi-heart"}`} aria-hidden="true" />
                        </button>
                    </div>
                )}
            </div>

            <div className="mc-course-card-body">
                <div className="mc-course-card-meta">
                    {item.category && <span className="mc-course-card-category">{item.category}</span>}
                    {item.duration && (
                        <span className="mc-course-card-duration">
                            <i className="bi bi-clock" aria-hidden="true" />
                            {item.duration}
                        </span>
                    )}
                </div>
                <h3 className="mc-course-card-title">
                    <a href={item.detailsurl} className="text-decoration-none text-reset">{item.title}</a>
                </h3>
                {item.level && <p className="mc-course-card-instructor">{item.level}</p>}
                <div className="mc-course-card-detailsrow">
                    <a href={item.detailsurl} className="mc-course-card-details">
                        {labels.viewdetails}
                        <i className="bi bi-arrow-right" aria-hidden="true" />
                    </a>
                    {item.reviewcount > 0 && (
                        <div className="mc-course-card-rating">
                            <RatingStars rating={item.rating} />
                            <span className="mc-rating-value">{item.rating.toFixed(1)}</span>
                            <span className="mc-rating-count">({item.reviewcount})</span>
                        </div>
                    )}
                </div>
            </div>

            <div className="mc-course-card-footer">
                <div className="mc-course-card-price">
                    <span className="fw-bold">{item.displayprice}</span>
                    {item.hasoriginalprice && (
                        <span className="mc-course-card-original-price">{item.displayoriginalprice}</span>
                    )}
                </div>
                {item.hasaccess ? (
                    <a className="mc-button btn-mc-secondary mc-course-card-btn" href={item.accessurl || "#"}
                        data-mc-button="secondary">
                        <i className="bi bi-play-circle" aria-hidden="true" />
                        {labels.owned}
                    </a>
                ) : (
                    <McButton
                        type="button"
                        className="btn-mc-primary mc-course-card-btn"
                        loading={busy}
                        loadingLabel={labels.loading || "Adding..."}
                        onClick={() => onAdd(item)}
                    >
                        <i className="bi bi-cart-plus" aria-hidden="true" />
                        {labels.addtocart}
                    </McButton>
                )}
            </div>
        </article>
    );
}

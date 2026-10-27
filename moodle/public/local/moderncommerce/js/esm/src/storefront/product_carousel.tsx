// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Featured / related products carousel for the Modern Commerce storefront.
 *
 * Fetches items from Modern Commerce's catalog web service and renders them with
 * the shared mc-course-card styling, including add-to-cart. The web-service
 * method names are supplied via props so the same component can drive featured
 * and related zones.
 *
 * @module     local_moderncommerce/storefront/product_carousel
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties, useEffect, useRef, useState} from "react";
import {refreshNavbarCart} from "../learner_common";
import CourseImageCard, {CourseCardItem} from "./course_card";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type CatalogItem = CourseCardItem;

type CatalogResponse = {
    items: CatalogItem[];
    state: {isloggedin: boolean};
};

type CartResponse = {
    success: boolean;
    message: string;
};

type Labels = Record<string, string>;

type Filters = {
    coursetype: string;
    categoryid: number;
    sort: string;
    perpage: number;
};

type ProductCarouselProps = {
    title?: string;
    subtitle?: string;
    methodName: string;
    cartMethodName: string;
    wishlistMethodName?: string;
    filters: Filters;
    layout: string;
    align?: string;
    navposition?: string;
    columns: number;
    buttoncolor?: string;
    buttontextcolor?: string;
    cardbgcolor?: string;
    cardbordercolor?: string;
    cardborderwidth?: number | string;
    labels: Labels;
};

const callService = async <T, >(methodName: string, args: unknown, serviceErrorMessage: string): Promise<T> => {
    const query = new URLSearchParams({sesskey: M.cfg.sesskey, info: methodName});
    const response = await fetch(`${M.cfg.wwwroot}/lib/ajax/service.php?${query.toString()}`, {
        method: "POST",
        credentials: "same-origin",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify([{index: 0, methodname: methodName, args}]),
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
        throw new Error(first.exception?.message ?? serviceErrorMessage);
    }
    return (first.data ?? first) as T;
};

export default function ProductCarousel({
    title,
    subtitle,
    methodName,
    cartMethodName,
    wishlistMethodName,
    filters,
    layout,
    align = "left",
    navposition = "topright",
    columns,
    buttoncolor = "",
    buttontextcolor = "",
    cardbgcolor = "",
    cardbordercolor = "",
    cardborderwidth = 0,
    labels,
}: ProductCarouselProps) {
    const [items, setItems] = useState<CatalogItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [loggedIn, setLoggedIn] = useState(true);
    const [busyItem, setBusyItem] = useState(0);
    const [busyWishlistItem, setBusyWishlistItem] = useState(0);
    const [message, setMessage] = useState("");
    const trackRef = useRef<HTMLDivElement>(null);

    const filterKey = JSON.stringify(filters);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        callService<CatalogResponse>(methodName, {
            coursetype: filters.coursetype,
            categoryid: filters.categoryid,
            sort: filters.sort,
            page: 0,
            perpage: filters.perpage,
        }, labels.p1_storefront_servicerequestfailed)
            .then((result) => {
                if (!cancelled) {
                    setItems(result.items ?? []);
                    setLoggedIn(Boolean(result.state?.isloggedin));
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setItems([]);
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
    }, [methodName, filterKey, filters.coursetype, filters.categoryid, filters.sort, filters.perpage]);

    const addToCart = async(item: CatalogItem) => {
        if (!loggedIn) {
            setMessage(labels.loginrequired);
            return;
        }
        setBusyItem(item.id);
        setMessage("");
        try {
            const args = item.isbundle || item.isprogram
                ? {action: "addbundle", bundleid: item.id}
                : {action: "addcourse", courseid: item.id};
            const result = await callService<CartResponse>(cartMethodName, args, labels.p1_storefront_servicerequestfailed);
            void refreshNavbarCart(result);
            setMessage(result.message);
        } catch (caught) {
            setMessage((caught as Error).message);
        } finally {
            setBusyItem(0);
        }
    };

    const toggleWishlist = async(item: CatalogItem, saved: boolean) => {
        if (!wishlistMethodName || item.productid <= 0) {
            return;
        }
        if (!loggedIn) {
            setMessage(labels.loginrequired);
            return;
        }
        setBusyWishlistItem(item.productid);
        setMessage("");
        try {
            await callService<{message: string}>(
                wishlistMethodName,
                {action: saved ? "remove" : "add", productid: item.productid},
                labels.p1_storefront_servicerequestfailed
            );
            setItems((current) => current.map((currentItem) => currentItem.productid === item.productid
                ? {...currentItem, inwishlist: !saved}
                : currentItem));
        } catch (caught) {
            setMessage((caught as Error).message);
        } finally {
            setBusyWishlistItem(0);
        }
    };

    const scrollByPage = (direction: number) => {
        const track = trackRef.current;
        if (track) {
            track.scrollBy({left: direction * track.clientWidth * 0.85, behavior: "smooth"});
        }
    };

    if (loading) {
        return (
            <section className="mw-featured mw-featured--loading">
                <div className="mw-featured__track" aria-hidden="true">
                    {[0, 1, 2, 3].map((i) => <div className="mw-featured__card-skeleton" key={i} />)}
                </div>
            </section>
        );
    }

    if (items.length === 0) {
        return null;
    }

    const showArrows = layout === "carousel" && items.length > columns;
    const listStyle = {"--mw-cols": String(columns)} as CSSProperties;
    const sectionStyle = {} as CSSProperties & Record<string, string>;
    const cardStyle = {} as CSSProperties & Record<string, string>;
    if (buttoncolor) {
        sectionStyle["--mc-featured-button-bg"] = buttoncolor;
    }
    if (buttontextcolor) {
        sectionStyle["--mc-featured-button-text"] = buttontextcolor;
    }
    if (cardbgcolor) {
        sectionStyle["--mc-product-card-bg"] = cardbgcolor;
        cardStyle["--mc-product-card-bg"] = cardbgcolor;
        cardStyle.background = cardbgcolor;
        cardStyle.backgroundColor = cardbgcolor;
    }
    if (cardbordercolor) {
        sectionStyle["--mc-product-card-border"] = cardbordercolor;
        cardStyle["--mc-product-card-border"] = cardbordercolor;
        cardStyle.borderColor = cardbordercolor;
        cardStyle.borderStyle = "solid";
    }
    const borderWidth = typeof cardborderwidth === "string"
        ? Number(cardborderwidth.trim().replace(/px$/i, ""))
        : Number(cardborderwidth);
    if (Number.isFinite(borderWidth) && borderWidth > 0) {
        const borderWidthPx = `${Math.min(24, Math.max(0, borderWidth))}px`;
        sectionStyle["--mc-product-card-border-width"] = borderWidthPx;
        cardStyle["--mc-product-card-border-width"] = borderWidthPx;
        cardStyle.borderWidth = borderWidthPx;
        cardStyle.borderStyle = "solid";
    }
    const appliedCardStyle = Object.keys(cardStyle).length ? cardStyle : undefined;
    const navAtTop = !navposition.startsWith("bottom");

    const navButtons = showArrows ? (
        <div className="mw-featured__nav">
            <button
                type="button"
                className="mc-button mw-featured__arrow"
                data-mc-button="light"
                data-mc-button-size="icon"
                aria-label={labels.p1_storefront_scrollleft}
                onClick={() => scrollByPage(-1)}
            >
                <i className="bi bi-chevron-left" aria-hidden="true" />
            </button>
            <button
                type="button"
                className="mc-button mw-featured__arrow"
                data-mc-button="light"
                data-mc-button-size="icon"
                aria-label={labels.p1_storefront_scrollright}
                onClick={() => scrollByPage(1)}
            >
                <i className="bi bi-chevron-right" aria-hidden="true" />
            </button>
        </div>
    ) : null;

    return (
        <section
            className={`mw-featured mw-featured--${layout} mw-featured--align-${align} mw-featured--nav-${navposition}`}
            style={sectionStyle}
        >
            <header className="mw-featured__head">
                <div className="mw-featured__heading">
                    {title && <h2 className="mw-featured__title">{title}</h2>}
                    {subtitle && <p className="mw-featured__subtitle">{subtitle}</p>}
                </div>
                {navAtTop && navButtons}
            </header>

            {message && (
                <div className="mc-alert mc-alert--info" role="status">
                    <div className="mc-alert__body">{message}</div>
                </div>
            )}

            <div
                className={layout === "grid" ? "mw-featured__grid" : "mw-featured__track"}
                style={listStyle}
                ref={trackRef}
            >
                {items.map((item) => (
                    <CourseImageCard
                        item={item}
                        labels={labels}
                        busy={busyItem === item.id}
                        onAdd={addToCart}
                        style={appliedCardStyle}
                        wishlistBusy={busyWishlistItem === item.productid}
                        wishlistSaved={item.inwishlist}
                        onToggleWishlist={wishlistMethodName && loggedIn ? toggleWishlist : undefined}
                        key={`${item.itemtype}-${item.id}`}
                    />
                ))}
            </div>

            {!navAtTop && navButtons && (
                <div className="mw-featured__foot">{navButtons}</div>
            )}
        </section>
    );
}

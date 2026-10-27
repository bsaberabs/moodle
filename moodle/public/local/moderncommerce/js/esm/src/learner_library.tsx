// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Learner course library for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_library
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {FormEvent, useEffect, useState} from "react";
import type {Dispatch, ReactNode} from "react";
import {callMoodleService, formatCount, Labels, refreshNavbarCart} from "./learner_common";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";
import LearnerListRow from "./learner_list_row";
import {LearnerStatStrip, LearnerStatTile} from "./learner_stat_tiles";
import {McTablePagination} from "./table_components";

type CatalogItem = {
    id: number;
    productid: number;
    itemtype: string;
    title: string;
    thumbnail: string;
    alt: string;
    category: string;
    categoryid: number;
    coursetype: string;
    level: string;
    duration: string;
    rating: number;
    reviewcount: number;
    price: number;
    originalprice: number;
    displayprice: string;
    displayoriginalprice: string;
    hasoriginalprice: boolean;
    isbundle: boolean;
    isprogram: boolean;
    bestseller: boolean;
    hasaccess: boolean;
    inwishlist: boolean;
    accessurl: string;
    detailsurl: string;
};

type CatalogFilters = {
    search: string;
    coursetype: string;
    categoryid: number;
    level: string;
    minprice: number;
    maxprice: number;
    sort: string;
    page: number;
    perpage: number;
};

type Option = {
    value: string;
    label: string;
};

type CategoryOption = {
    id: number;
    name: string;
};

type CatalogResponse = {
    items: CatalogItem[];
    total: number;
    page: number;
    perpage: number;
    totalpages: number;
    hasmore: boolean;
    filters: Omit<CatalogFilters, "sort" | "page" | "perpage">;
    filteroptions: {
        categories: CategoryOption[];
        coursetypes: Option[];
        levels: Option[];
        maxprice: number;
    };
    state: {
        isloggedin: boolean;
    };
};

type CartResponse = {
    success: boolean;
    message: string;
};

type LearnerLibraryProps = {
    methodName: string;
    cartMethodName: string;
    wishlistUpdateMethodName?: string;
    initialFilters: CatalogFilters;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

type ViewMode = "grid" | "list";

const label = (labels: Labels, key: string, fallback = ""): string => labels[key] || fallback || key;
const formatMoodleLabel = (template: string, replacements: Record<string, string | number>): string => (
    Object.entries(replacements).reduce(
        (text, [key, value]) => text.split(`{$a->${key}}`).join(String(value)),
        template
    )
);

const defaultFilters = (initialFilters: CatalogFilters): CatalogFilters => ({
    search: initialFilters.search ?? "",
    coursetype: initialFilters.coursetype ?? "",
    categoryid: Number(initialFilters.categoryid ?? 0),
    level: initialFilters.level ?? "",
    minprice: Number(initialFilters.minprice ?? 0),
    maxprice: Number(initialFilters.maxprice ?? 0),
    sort: initialFilters.sort || "popular",
    page: Math.max(0, Number(initialFilters.page ?? 0)),
    perpage: Math.max(1, Number(initialFilters.perpage ?? 12)),
});

const filtersForService = (filters: CatalogFilters) => ({
    search: filters.search,
    coursetype: filters.coursetype,
    categoryid: filters.categoryid,
    level: filters.level,
    minprice: filters.minprice,
    maxprice: filters.maxprice,
    sort: filters.sort,
    page: filters.page,
    perpage: filters.perpage,
});

const syncLearnerLibraryUrl = (filters: CatalogFilters) => {
    const params = new URLSearchParams();

    Object.entries(filtersForService(filters)).forEach(([key, value]) => {
        if (value === "" || value === 0 || (key === "sort" && value === "popular")) {
            return;
        }
        if (key === "perpage" && Number(value) === 12) {
            return;
        }
        params.set(key, String(value));
    });

    const query = params.toString();
    const hash = query ? `#/library?${query}` : "#/library";
    const nextUrl = location.pathname.endsWith("/learner/index.php")
        ? `${location.pathname}${hash}`
        : `${location.pathname}${query ? `?${query}` : ""}`;

    window.history.replaceState({}, "", nextUrl);
};

const itemTypeLabel = (item: CatalogItem, labels: Labels): string => {
    if (item.isprogram) {
        return label(labels, "program");
    }
    if (item.isbundle) {
        return label(labels, "bundle");
    }
    return label(labels, "course");
};

const itemBadgeClass = (item: CatalogItem): string => {
    if (item.isprogram) {
        return "mc-badge-program";
    }
    if (item.isbundle) {
        return "mc-badge-bundle";
    }
    return "mc-badge--neutral";
};

const accessAction = (item: CatalogItem, labels: Labels): {label: string; icon: string} => {
    const itemType = item.itemtype.toLowerCase();

    if (itemType === "course") {
        return {
            label: label(labels, "viewcourse"),
            icon: "bi-play-circle",
        };
    }

    if (item.isprogram) {
        return {
            label: label(labels, "viewincludedcourses"),
            icon: "bi-list-check",
        };
    }

    if (item.isbundle) {
        return {
            label: label(labels, "viewbundle"),
            icon: "bi-layers",
        };
    }

    return {
        label: label(labels, "viewaccesslibrary"),
        icon: "bi-unlock",
    };
};

const sortOptions = (labels: Labels): Option[] => [
    {value: "popular", label: label(labels, "catalog_sort_popular")},
    {value: "newest", label: label(labels, "catalog_sort_newest")},
    {value: "title", label: label(labels, "catalog_sort_title")},
    {value: "pricelow", label: label(labels, "catalog_sort_pricelow")},
    {value: "pricehigh", label: label(labels, "catalog_sort_pricehigh")},
];

function Field({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <label className={mcClasses("mc-filter-label d-flex flex-column gap-1")}>
            <span>{title}</span>
            {children}
        </label>
    );
}

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

function LibraryItem({
    item,
    labels,
    viewMode,
    busy,
    wishlistBusy,
    wishlistSaved,
    onAddToCart,
    onToggleWishlist,
}: {
    item: CatalogItem;
    labels: Labels;
    viewMode: ViewMode;
    busy: boolean;
    wishlistBusy: boolean;
    wishlistSaved: boolean;
    onAddToCart: Dispatch<CatalogItem>;
    onToggleWishlist?: (item: CatalogItem, saved: boolean) => void;
}) {
    const typeLabel = itemTypeLabel(item, labels);
    const accessUrl = item.accessurl || "#/dashboard";
    const ownedAction = accessAction(item, labels);
    const wishlistLabel = wishlistSaved ? label(labels, "savedtowishlist") : label(labels, "savetowishlist");
    const wishlistTitle = wishlistSaved ? label(labels, "removefromwishlist") : label(labels, "savetowishlist");
    const wishlistIcon = wishlistSaved ? "bi-heart-fill" : "bi-heart";
    const action = item.hasaccess ? (
        <a className={mcClasses("mc-button btn-mc-secondary")} href={accessUrl}>
            <i className={`bi ${ownedAction.icon}`} aria-hidden="true" />
            {ownedAction.label}
        </a>
    ) : (
        <button
            type="button"
            className={mcClasses("mc-button btn-mc-primary")}
            disabled={busy}
            onClick={() => onAddToCart(item)}
        >
            <i className="bi bi-cart-plus" aria-hidden="true" />
            {label(labels, "catalog_add_to_cart")}
        </button>
    );

    if (viewMode === "list") {
        return (
            <LearnerListRow
                thumbnail={item.thumbnail || undefined}
                title={item.title}
                titleHref={item.detailsurl}
                meta={(
                    <>
                        <span className={mcClasses(`mc-badge ${itemBadgeClass(item)}`)}>{typeLabel}</span>
                        {item.category && <span className={mcClasses("mc-cell-muted small")}>{item.category}</span>}
                        {item.duration && <span className={mcClasses("mc-cell-muted small")}>{item.duration}</span>}
                    </>
                )}
                subtitle={item.level || undefined}
                actions={(
                    <>
                        <strong>{item.displayprice}</strong>
                        {action}
                        {onToggleWishlist && !item.hasaccess && (
                            <button
                                type="button"
                                className={mcClasses("mc-button btn-mc-secondary py-1 px-2")}
                                aria-pressed={wishlistSaved}
                                disabled={wishlistBusy}
                                title={wishlistTitle}
                                onClick={() => onToggleWishlist(item, wishlistSaved)}
                            >
                                <i className={`bi ${wishlistIcon}`} aria-hidden="true" />
                                {wishlistLabel}
                            </button>
                        )}
                    </>
                )}
            />
        );
    }

    return (
        <article className={mcClasses("mc-course-card")}>
            <div className={mcClasses("mc-course-card-image")}>
                {item.thumbnail ? (
                    <img
                        src={item.thumbnail}
                        alt={item.alt || item.title}
                        width={480}
                        height={270}
                        loading="lazy"
                    />
                ) : (
                    <div className="w-100 h-100 bg-light" aria-hidden="true" />
                )}
                <div className={mcClasses("mc-course-card-badges")}>
                    <span className={mcClasses(`mc-badge ${itemBadgeClass(item)}`)}>{typeLabel}</span>
                    {item.bestseller && (
                        <span className={mcClasses("mc-badge mc-badge-bestseller")}>
                            {label(labels, "bestseller")}
                        </span>
                    )}
                </div>
                {onToggleWishlist && !item.hasaccess && (
                    <div className={mcClasses("mc-course-card-actions")}>
                        <button
                            type="button"
                            className={mcClasses("mc-button mc-course-card-wishlist")}
                            data-mc-button="ghost"
                            data-mc-button-size="icon"
                            aria-pressed={wishlistSaved}
                            disabled={wishlistBusy}
                            onClick={() => onToggleWishlist(item, wishlistSaved)}
                            aria-label={wishlistTitle}
                            title={wishlistTitle}
                        >
                            <i className={`bi ${wishlistIcon}`} aria-hidden="true" />
                        </button>
                    </div>
                )}
            </div>

            <div className={mcClasses("mc-course-card-body")}>
                <div className={mcClasses("mc-course-card-meta")}>
                    {item.category && <span className={mcClasses("mc-course-card-category")}>{item.category}</span>}
                    {item.duration && (
                        <span className={mcClasses("mc-course-card-duration")}>
                            <i className="bi bi-clock" aria-hidden="true" />
                            {item.duration}
                        </span>
                    )}
                </div>
                <h2 className={mcClasses("mc-course-card-title")}>
                    <a href={item.detailsurl} className="text-decoration-none text-reset">
                        {item.title}
                    </a>
                </h2>
                {item.level && <p className={mcClasses("mc-course-card-instructor")}>{item.level}</p>}
                {item.reviewcount > 0 && (
                    <div className={mcClasses("mc-course-card-rating")}>
                        <RatingStars rating={item.rating} />
                        <span className={mcClasses("mc-rating-value")}>{item.rating.toFixed(1)}</span>
                        <span className={mcClasses("mc-rating-count")}>({formatCount(item.reviewcount)})</span>
                    </div>
                )}
            </div>

            <div className={mcClasses("mc-course-card-footer")}>
                <div className={mcClasses("mc-course-card-price")}>
                    <span className="fw-bold">{item.displayprice}</span>
                    {item.hasoriginalprice && (
                        <span className={mcClasses("mc-course-card-original-price")}>
                            {item.displayoriginalprice}
                        </span>
                    )}
                </div>
                <div className="d-flex flex-column gap-2 align-items-stretch">
                    {item.hasaccess ? (
                        <a className={mcClasses("mc-button btn-mc-secondary mc-course-card-btn")} href={accessUrl}>
                            <i className={`bi ${ownedAction.icon}`} aria-hidden="true" />
                            {ownedAction.label}
                        </a>
                    ) : (
                        <McButton
                            type="button"
                            className={mcClasses("btn-mc-primary mc-course-card-btn")}
                            loading={busy}
                            loadingLabel={label(labels, "loading")}
                            onClick={() => onAddToCart(item)}
                        >
                            <i className="bi bi-cart-plus" aria-hidden="true" />
                            {label(labels, "catalog_add_to_cart")}
                        </McButton>
                    )}
                </div>
            </div>
        </article>
    );
}

export default function LearnerLibrary({
    methodName,
    cartMethodName,
    wishlistUpdateMethodName,
    initialFilters,
    labels,
    layout,
}: LearnerLibraryProps) {
    useModernCommerceClassSync();
    const initial = defaultFilters(initialFilters);
    const [filters, setFilters] = useState<CatalogFilters>(initial);
    const [draftSearch, setDraftSearch] = useState(initial.search);
    const [data, setData] = useState<CatalogResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");
    const [busyItem, setBusyItem] = useState(0);
    const [busyWishlistItem, setBusyWishlistItem] = useState(0);
    const [savedWishlistProductIds, setSavedWishlistProductIds] = useState<Set<number>>(new Set());
    const [viewMode, setViewMode] = useState<ViewMode>("grid");

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");
        syncLearnerLibraryUrl(filters);

        callMoodleService<CatalogResponse>(methodName, filtersForService(filters))
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                    setDraftSearch(result.filters.search);
                    setSavedWishlistProductIds((current) => {
                        const next = new Set(current);
                        result.items.forEach((item) => {
                            if (item.inwishlist) {
                                next.add(item.productid);
                                return;
                            }
                            next.delete(item.productid);
                        });
                        return next;
                    });
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

    const applyFilters = (changes: Partial<CatalogFilters>) => {
        setFilters((current) => ({
            ...current,
            ...changes,
            page: changes.page ?? 0,
        }));
        setMessage("");
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        applyFilters({search: draftSearch});
    };

    const clearFilters = () => {
        const next = defaultFilters({
            ...initialFilters,
            search: "",
            coursetype: "",
            categoryid: 0,
            level: "",
            minprice: 0,
            maxprice: 0,
            page: 0,
        });
        setDraftSearch("");
        setFilters(next);
        setMessage("");
    };

    const addToCart = async(item: CatalogItem) => {
        if (item.hasaccess) {
            window.location.assign(item.accessurl || "#/dashboard");
            return;
        }

        setBusyItem(item.id);
        setError("");
        setMessage("");

        try {
            const args = item.isbundle || item.isprogram
                ? {action: "addbundle", bundleid: item.id}
                : {action: "addcourse", courseid: item.id};
            const result = await callMoodleService<CartResponse>(cartMethodName, args);
            void refreshNavbarCart(result);
            toast.success(result.message);
        } catch (caught) {
            setError((caught as Error).message);
        } finally {
            setBusyItem(0);
        }
    };

    const toggleWishlist = async(item: CatalogItem, saved: boolean) => {
        if (!wishlistUpdateMethodName || item.productid <= 0) {
            return;
        }

        setBusyWishlistItem(item.productid);
        setError("");
        setMessage("");

        try {
            const result = await callMoodleService<CartResponse & {message: string}>(wishlistUpdateMethodName, {
                action: saved ? "remove" : "add",
                productid: item.productid,
            });
            setSavedWishlistProductIds((current) => {
                const next = new Set(current);
                if (saved) {
                    next.delete(item.productid);
                } else {
                    next.add(item.productid);
                }
                return next;
            });
            setData((current) => current ? {
                ...current,
                items: current.items.map((currentItem) => currentItem.productid === item.productid
                    ? {...currentItem, inwishlist: !saved}
                    : currentItem
                ),
            } : current);
            toast.success(result.message || label(labels, saved ? "wishlistremoved" : "wishlistadded"));
        } catch (caught) {
            setError((caught as Error).message);
        } finally {
            setBusyWishlistItem(0);
        }
    };

    const categories = data?.filteroptions.categories ?? [];
    const courseTypes = data?.filteroptions.coursetypes ?? [];
    const levels = data?.filteroptions.levels ?? [];
    const items = data?.items ?? [];
    const typeCount = Math.max(0, courseTypes.filter((option) => option.value !== "").length);
    const canPrevious = data ? data.page > 0 : false;
    const canNext = data ? data.page + 1 < data.totalpages : false;
    const noticeClassName = mcClasses("mc-alert", error ? "mc-alert--danger" : "mc-alert--success");
    const noticeIconClassName = mcClasses(
        "bi",
        error ? "bi-exclamation-triangle" : "bi-check-circle",
        "mc-alert__icon"
    );

    return (
        <ModernLearnerLayout
            activeNav="catalog"
            title={label(labels, "courselibrary")}
            subtitle={label(labels, "learnerlibrarydesc")}
            labels={labels}
            layout={layout}
        >
            <div className={mcClasses("mc-learner-library")}>
                <LearnerStatStrip>
                    <LearnerStatTile
                        label={label(labels, "catalog_results")}
                        value={data?.total ?? 0}
                        icon="bi-grid"
                        variant="primary"
                    />
                    <LearnerStatTile
                        label={label(labels, "catalog_topic_title")}
                        value={categories.length}
                        icon="bi-folder2-open"
                        variant="success"
                    />
                    <LearnerStatTile
                        label={label(labels, "catalog_coursetype_title")}
                        value={typeCount}
                        icon="bi-layers"
                        variant="warning"
                    />
                    <LearnerStatTile
                        label={label(labels, "catalog_show")}
                        value={filters.perpage}
                        icon="bi-list-ol"
                        variant="info"
                    />
                </LearnerStatStrip>

                {(message || error) && (
                    <div className={noticeClassName} role="alert" aria-live="polite">
                        <i className={noticeIconClassName} aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{error || message}</div>
                    </div>
                )}

                <div className={mcClasses("mc-card mb-3")}>
                    <div className={mcClasses("mc-card-body")}>
                        <form className="row g-3 align-items-end" onSubmit={submitSearch}>
                            <div className="col-12 col-xl-4">
                                <Field title={label(labels, "searchcourses")}>
                                    <div className={mcClasses("mc-catalog-search max-w-100")}>
                                        <i className="bi bi-search mc-catalog-search-icon" aria-hidden="true" />
                                        <input
                                            type="search"
                                            className={mcClasses("mc-catalog-search-input")}
                                            value={draftSearch}
                                            placeholder={label(labels, "catalog_search_placeholder")}
                                            onChange={(event) => setDraftSearch(event.currentTarget.value)}
                                        />
                                    </div>
                                </Field>
                            </div>
                            <div className="col-sm-6 col-xl-2">
                                <Field title={label(labels, "catalog_coursetype_title")}>
                                    <select
                                        className={mcClasses("mc-select")}
                                        value={filters.coursetype}
                                        onChange={(event) => applyFilters({coursetype: event.currentTarget.value})}
                                    >
                                        {courseTypes.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>
                            <div className="col-sm-6 col-xl-2">
                                <Field title={label(labels, "catalog_topic_title")}>
                                    <select
                                        className={mcClasses("mc-select")}
                                        value={filters.categoryid}
                                        onChange={(event) => applyFilters({
                                            categoryid: Number(event.currentTarget.value),
                                        })}
                                    >
                                        <option value={0}>{label(labels, "allcategories")}</option>
                                        {categories.map((category) => (
                                            <option key={category.id} value={category.id}>
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>
                            <div className="col-sm-6 col-xl-2">
                                <Field title={label(labels, "catalog_level_title")}>
                                    <select
                                        className={mcClasses("mc-select")}
                                        value={filters.level}
                                        onChange={(event) => applyFilters({level: event.currentTarget.value})}
                                    >
                                        {levels.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>
                            <div className="col-sm-6 col-xl-2">
                                <Field title={label(labels, "catalog_sortby")}>
                                    <select
                                        className={mcClasses("mc-select")}
                                        value={filters.sort}
                                        onChange={(event) => applyFilters({sort: event.currentTarget.value})}
                                    >
                                        {sortOptions(labels).map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>
                            <div className="col-12 d-flex flex-wrap gap-2 justify-content-between">
                                <div className="d-flex flex-wrap gap-2">
                                    <button type="submit" className={mcClasses("mc-button btn-mc-primary")}>
                                        <i className="bi bi-search" aria-hidden="true" />
                                        {label(labels, "catalog_show_results")}
                                    </button>
                                    <button
                                        type="button"
                                        className={mcClasses("mc-button btn-mc-secondary")}
                                        onClick={clearFilters}
                                    >
                                        {label(labels, "catalog_clear_filters")}
                                    </button>
                                </div>
                                {/* Same control as the dashboard's course view switch: the
                                    inactive side stays light so the selected view reads at
                                    a glance, rather than two identical dark buttons. */}
                                <div
                                    className={mcClasses("mc-modern-view-toggle")}
                                    role="group"
                                    aria-label={label(labels, "courseviewtoggle", "Course view")}
                                >
                                    <button
                                        className={mcClasses("mc-button", viewMode === "grid" && "active")}
                                        data-mc-button={viewMode === "grid" ? "primary" : "light"}
                                        data-mc-button-size="icon"
                                        type="button"
                                        aria-label={label(labels, "viewgrid")}
                                        aria-pressed={viewMode === "grid"}
                                        title={label(labels, "viewgrid")}
                                        onClick={() => setViewMode("grid")}
                                    >
                                        <i className="bi bi-grid-3x3-gap-fill" aria-hidden="true" />
                                    </button>
                                    <button
                                        className={mcClasses("mc-button", viewMode === "list" && "active")}
                                        data-mc-button={viewMode === "list" ? "primary" : "light"}
                                        data-mc-button-size="icon"
                                        type="button"
                                        aria-label={label(labels, "viewlist")}
                                        aria-pressed={viewMode === "list"}
                                        title={label(labels, "viewlist")}
                                        onClick={() => setViewMode("list")}
                                    >
                                        <i className="bi bi-list-ul" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div className="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                    <span className={mcClasses("mc-cell-muted")}>
                        {loading ? label(labels, "loading") : (
                            `${formatCount(data?.total ?? 0)} ${label(labels, "catalog_results")}`
                        )}
                    </span>
                </div>

                {loading && !data && (
                    <div className={mcClasses("mc-empty mc-empty--centered")}>
                        <span className={mcClasses("mc-empty__icon")}>
                            <i className="bi bi-grid" aria-hidden="true" />
                        </span>
                        <p className={mcClasses("mc-empty__title")}>{label(labels, "loading")}</p>
                    </div>
                )}

                {!loading && !error && items.length === 0 && (
                    <div className={mcClasses("mc-catalog-no-results")}>
                        <div className={mcClasses("mc-no-results-icon")}>
                            <i className="bi bi-search" aria-hidden="true" />
                        </div>
                        <h2>{label(labels, "catalog_no_courses")}</h2>
                        <button type="button" className={mcClasses("mc-button mc-btn-soft")} onClick={clearFilters}>
                            {label(labels, "catalog_clear_filters")}
                        </button>
                    </div>
                )}

                {items.length > 0 && (
                    <div className={viewMode === "grid" ? mcClasses("mc-catalog-grid") : ""}>
                        {items.map((item) => (
                            <LibraryItem
                                key={`${item.itemtype}-${item.id}`}
                                item={item}
                                labels={labels}
                                viewMode={viewMode}
                                busy={busyItem === item.id}
                                wishlistBusy={busyWishlistItem === item.productid}
                                wishlistSaved={item.inwishlist || savedWishlistProductIds.has(item.productid)}
                                onAddToCart={addToCart}
                                onToggleWishlist={wishlistUpdateMethodName ? toggleWishlist : undefined}
                            />
                        ))}
                    </div>
                )}

                {data && data.totalpages > 1 && (
                    <McTablePagination
                        className="mt-3"
                        previousLabel={label(labels, "catalog_previous")}
                        nextLabel={label(labels, "catalog_next")}
                        pageLabel={formatMoodleLabel(label(labels, "catalog_page_x_of_y"), {
                            page: formatCount(data.page + 1),
                            total: formatCount(data.totalpages),
                        })}
                        previousDisabled={!canPrevious || loading}
                        nextDisabled={!canNext || loading}
                        onPrevious={() => applyFilters({page: data.page - 1})}
                        onNext={() => applyFilters({page: data.page + 1})}
                    />
                )}
            </div>
        </ModernLearnerLayout>
    );
}

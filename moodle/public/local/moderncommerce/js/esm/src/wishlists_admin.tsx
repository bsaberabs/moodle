// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin wishlist analytics for Modern Commerce.
 *
 * @module     local_moderncommerce/wishlists_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {McBadge} from "./badge";
import {mcClasses, sortIconClass, useModernCommerceClassSync} from "./design_system";
import {McTableActionMenu, McTableCard, McTableFooter, McTablePagination} from "./table_components";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type SelectOption = {
    value: string;
    label: string;
};

type Filters = {
    productsearch: string;
    activitysearch: string;
    producttype: string;
    activitytype: string;
    productpage: number;
    activitypage: number;
    productperpage: number;
    activityperpage: number;
    productsort: string;
    productdirection: "ASC" | "DESC";
    activitysort: string;
    activitydirection: "ASC" | "DESC";
};

type InitialFilters = Partial<Filters> & {
    search?: string;
    perpage?: number;
};

type Labels = Record<string, string>;

type TopProduct = {
    id: number;
    name: string;
    sku: string;
    producttype: string;
    typelabel: string;
    savedcount: number;
    customercount: number;
    amount: number;
    hasprice: boolean;
    displayprice: string;
    lastsaved: number;
    displaylastsaved: string;
    producturl: string;
};

type WishlistActivity = {
    id: number;
    customerid: number;
    customername: string;
    customeremail: string;
    customerurl: string;
    productid: number;
    productname: string;
    sku: string;
    producttype: string;
    typelabel: string;
    timecreated: number;
    displaydate: string;
    producturl: string;
};

type Stats = {
    saveditems: number;
    productcount: number;
    customercount: number;
    lastsaved: string;
};

type WishlistsResponse = {
    topproducts: TopProduct[];
    topproductstotal: number;
    productpage: number;
    activity: WishlistActivity[];
    activitytotal: number;
    activitypage: number;
    productperpage: number;
    activityperpage: number;
    productsort: string;
    productdirection: "ASC" | "DESC";
    activitysort: string;
    activitydirection: "ASC" | "DESC";
    stats: Stats;
};

type WishlistsAdminProps = {
    methodName: string;
    initialFilters: InitialFilters;
    productTypes: SelectOption[];
    perPageOptions: number[];
    labels: Labels;
};

const defaultFilters: Filters = {
    productsearch: "",
    activitysearch: "",
    producttype: "",
    activitytype: "",
    productpage: 0,
    activitypage: 0,
    productperpage: 10,
    activityperpage: 10,
    productsort: "savedcount",
    productdirection: "DESC",
    activitysort: "timecreated",
    activitydirection: "DESC",
};

const normaliseFilters = (filters: InitialFilters): Filters => {
    const legacySearch = String(filters.search ?? "");
    const legacyPerPage = Number(filters.perpage ?? defaultFilters.productperpage) || defaultFilters.productperpage;

    return {
        ...defaultFilters,
        ...filters,
        productsearch: String(filters.productsearch ?? legacySearch),
        activitysearch: String(filters.activitysearch ?? legacySearch),
        producttype: String(filters.producttype ?? ""),
        activitytype: String(filters.activitytype ?? filters.producttype ?? ""),
        productpage: Math.max(0, Number(filters.productpage ?? defaultFilters.productpage) || 0),
        activitypage: Math.max(0, Number(filters.activitypage ?? defaultFilters.activitypage) || 0),
        productperpage: Number(filters.productperpage ?? legacyPerPage) || defaultFilters.productperpage,
        activityperpage: Number(filters.activityperpage ?? legacyPerPage) || defaultFilters.activityperpage,
        productdirection: filters.productdirection === "ASC" ? "ASC" : "DESC",
        activitysort: String(filters.activitysort ?? defaultFilters.activitysort),
        activitydirection: filters.activitydirection === "ASC" ? "ASC" : "DESC",
    };
};

const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php`
        + `?sesskey=${encodeURIComponent(M.cfg.sesskey)}`
        + `&info=${encodeURIComponent(methodName)}`;
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

const formatCount = (value: number): string => {
    return new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);
};

const getVisibleRange = (total: number, page: number, perpage: number): {from: number; to: number} => {
    if (total <= 0) {
        return {from: 0, to: 0};
    }

    return {
        from: page * perpage + 1,
        to: Math.min((page + 1) * perpage, total),
    };
};

export default function WishlistsAdmin({
    methodName,
    initialFilters,
    productTypes,
    perPageOptions,
    labels,
}: WishlistsAdminProps) {
    useModernCommerceClassSync();

    const [filters, setFilters] = useState<Filters>(() => normaliseFilters(initialFilters));
    const [productSearchInput, setProductSearchInput] = useState(filters.productsearch);
    const [activitySearchInput, setActivitySearchInput] = useState(filters.activitysearch);
    const [data, setData] = useState<WishlistsResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [reloadToken, setReloadToken] = useState(0);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setFilters((current) => {
                if (current.productsearch === productSearchInput) {
                    return current;
                }

                return {
                    ...current,
                    productsearch: productSearchInput,
                    productpage: 0,
                };
            });
        }, 350);

        return () => window.clearTimeout(timer);
    }, [productSearchInput]);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setFilters((current) => {
                if (current.activitysearch === activitySearchInput) {
                    return current;
                }

                return {
                    ...current,
                    activitysearch: activitySearchInput,
                    activitypage: 0,
                };
            });
        }, 350);

        return () => window.clearTimeout(timer);
    }, [activitySearchInput]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<WishlistsResponse>(methodName, filters)
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                }
            })
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
    }, [filters, methodName, reloadToken]);

    useEffect(() => {
        const refreshButton = document.getElementById("moderncommerce-wishlists-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    const topTotal = data?.topproductstotal ?? 0;
    const activityTotal = data?.activitytotal ?? 0;
    const stats = data?.stats;
    const topPages = Math.max(1, Math.ceil(topTotal / filters.productperpage));
    const activityPages = Math.max(1, Math.ceil(activityTotal / filters.activityperpage));
    const topRange = getVisibleRange(topTotal, filters.productpage, filters.productperpage);
    const activityRange = getVisibleRange(activityTotal, filters.activitypage, filters.activityperpage);

    const updateProductFilters = (changes: Partial<Filters>) => {
        const shouldResetPage = "productsearch" in changes
            || "producttype" in changes
            || "productperpage" in changes;

        setFilters((current) => ({
            ...current,
            ...changes,
            productpage: changes.productpage ?? (shouldResetPage ? 0 : current.productpage),
        }));
    };

    const updateActivityFilters = (changes: Partial<Filters>) => {
        const shouldResetPage = "activitysearch" in changes
            || "activitytype" in changes
            || "activityperpage" in changes;

        setFilters((current) => ({
            ...current,
            ...changes,
            activitypage: changes.activitypage ?? (shouldResetPage ? 0 : current.activitypage),
        }));
    };

    const changeProductSort = (sort: string) => {
        setFilters((current) => {
            if (current.productsort === sort) {
                return {
                    ...current,
                    productdirection: current.productdirection === "ASC" ? "DESC" : "ASC",
                    productpage: 0,
                };
            }

            return {
                ...current,
                productsort: sort,
                productdirection: sort === "name" || sort === "producttype" ? "ASC" : "DESC",
                productpage: 0,
            };
        });
    };

    const changeActivitySort = (sort: string) => {
        setFilters((current) => {
            if (current.activitysort === sort) {
                return {
                    ...current,
                    activitydirection: current.activitydirection === "ASC" ? "DESC" : "ASC",
                    activitypage: 0,
                };
            }

            return {
                ...current,
                activitysort: sort,
                activitydirection: sort === "customer" || sort === "product" || sort === "producttype" ? "ASC" : "DESC",
                activitypage: 0,
            };
        });
    };

    const renderProductSortButton = (sort: string, label: string, align = "text-start") => {
        const active = filters.productsort === sort;
        return (
            <button
                className={mcClasses("mc-table-sort", align)}
                onClick={() => changeProductSort(sort)}
                type="button"
            >
                <span>{label}</span>
                <i
                    className={mcClasses(
                        "mc-table-sort__indicator",
                        active && "mc-table-sort__indicator--active",
                        sortIconClass(active, filters.productdirection),
                    )}
                    aria-hidden="true"
                />
            </button>
        );
    };

    const renderActivitySortButton = (sort: string, label: string, align = "text-start") => {
        const active = filters.activitysort === sort;
        return (
            <button
                className={mcClasses("mc-table-sort", align)}
                onClick={() => changeActivitySort(sort)}
                type="button"
            >
                <span>{label}</span>
                <i
                    className={mcClasses(
                        "mc-table-sort__indicator",
                        active && "mc-table-sort__indicator--active",
                        sortIconClass(active, filters.activitydirection),
                    )}
                    aria-hidden="true"
                />
            </button>
        );
    };

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            {stats && (
                <div className={mcClasses("mc-stat-strip")} aria-label={labels.title}>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--primary")}>
                        <i className="bi bi-heart mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.saveditems}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>
                                {formatCount(stats.saveditems)}
                            </strong>
                        </div>
                        <i className="bi bi-heart mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                        <i className="bi bi-box-seam mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.wishlistedproducts}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>
                                {formatCount(stats.productcount)}
                            </strong>
                        </div>
                        <i className="bi bi-box-seam mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--info")}>
                        <i className="bi bi-people mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.wishlistcustomers}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>
                                {formatCount(stats.customercount)}
                            </strong>
                        </div>
                        <i className="bi bi-people mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--warning")}>
                        <i className="bi bi-clock-history mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.lastsaved}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{stats.lastsaved || "-"}</strong>
                        </div>
                        <i className="bi bi-clock-history mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                </div>
            )}

            <div className={mcClasses("d-flex flex-column gap-3")}>
                <McTableCard
                    className={mcClasses("mc-wishlist-table-card")}
                    title={<h2 className={mcClasses("mc-card-title mb-0")}>{labels.mostwishlistedproducts}</h2>}
                    toolbar={(
                        <div className={mcClasses("mc-wishlist-table-controls")}>
                            <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar mc-wishlist-toolbar")}>
                                <div className={mcClasses("mc-product-toolbar__search mc-wishlist-toolbar__search")}>
                                    <label className={mcClasses("mc-filter-label")} htmlFor="mc-wishlists-product-search">
                                        {labels.search}
                                    </label>
                                    <input
                                        className={mcClasses("mc-form-control")}
                                        id="mc-wishlists-product-search"
                                        onChange={(event) => setProductSearchInput(event.target.value)}
                                        placeholder={labels.productsearchplaceholder ?? labels.searchplaceholder}
                                        type="search"
                                        value={productSearchInput}
                                    />
                                </div>
                                <label className={mcClasses("mc-product-toolbar__field")}>
                                    <span className={mcClasses("mc-filter-label")}>{labels.type}</span>
                                    <select
                                        className={mcClasses("mc-select")}
                                        onChange={(event) => updateProductFilters({producttype: event.target.value})}
                                        value={filters.producttype}
                                    >
                                        {productTypes.map((option) => (
                                            <option key={option.value || "all"} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className={mcClasses("mc-table-design-page-size")}>
                                    <span className={mcClasses("mc-filter-label")}>{labels.perpage}</span>
                                    <select
                                        className={mcClasses("mc-select")}
                                        onChange={(event) => updateProductFilters({
                                            productperpage: Number(event.target.value) || 10,
                                        })}
                                        value={filters.productperpage}
                                    >
                                        {perPageOptions.map((option) => (
                                            <option key={option} value={option}>{option}</option>
                                        ))}
                                    </select>
                                </label>
                            </div>
                        </div>
                    )}
                    footer={(
                        <McTableFooter
                            summary={(
                                <span>
                                    {labels.showing} {formatCount(topRange.from)}-{formatCount(topRange.to)}
                                    {" / "}
                                    {formatCount(topTotal)}
                                </span>
                            )}
                            pagination={(
                                <McTablePagination
                                    previousLabel={labels.previous}
                                    nextLabel={labels.next}
                                    pageLabel={labels.page}
                                    page={Math.min(filters.productpage + 1, topPages)}
                                    totalPages={topPages}
                                    previousDisabled={loading || filters.productpage <= 0}
                                    nextDisabled={loading || filters.productpage + 1 >= topPages}
                                    onPrevious={() => updateProductFilters({productpage: Math.max(0, filters.productpage - 1)})}
                                    onNext={() => updateProductFilters({productpage: filters.productpage + 1})}
                                />
                            )}
                        />
                    )}
                >
                            <table
                                className={mcClasses("table mc-table mc-product-table mb-0")}
                                aria-label={labels.mostwishlistedproducts}
                            >
                                <thead>
                                    <tr>
                                        <th scope="col">{renderProductSortButton("name", labels.product)}</th>
                                        <th scope="col">{renderProductSortButton("producttype", labels.type)}</th>
                                        <th scope="col" className="text-end">
                                            {renderProductSortButton("savedcount", labels.saveditems, "text-end")}
                                        </th>
                                        <th scope="col" className="text-end">
                                            {renderProductSortButton("customercount", labels.customers, "text-end")}
                                        </th>
                                        <th scope="col" className="text-end">
                                            {renderProductSortButton("amount", labels.price, "text-end")}
                                        </th>
                                        <th scope="col">{renderProductSortButton("lastsaved", labels.lastsaved)}</th>
                                        <th scope="col" className="text-end">{labels.actions}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {!loading && data?.topproducts.length === 0 && (
                                        <tr>
                                            <td colSpan={7}>
                                                <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                    <span className={mcClasses("mc-empty__icon")}>
                                                        <i className="bi bi-heart" aria-hidden="true" />
                                                    </span>
                                                    <p className={mcClasses("mc-empty__title")}>
                                                        {topTotal === 0 ? labels.nowishlistitems : labels.noresults}
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {data?.topproducts.map((product) => (
                                        <tr key={product.id}>
                                            <td>
                                                <a className="fw-semibold" href={product.producturl}>
                                                    {product.name}
                                                </a>
                                                {product.sku && (
                                                    <div className={mcClasses("mc-cell-muted small")}>{product.sku}</div>
                                                )}
                                            </td>
                                            <td>
                                                <McBadge variant="neutral" tone="soft">{product.typelabel}</McBadge>
                                            </td>
                                            <td className="text-end">{formatCount(product.savedcount)}</td>
                                            <td className="text-end">{formatCount(product.customercount)}</td>
                                            <td className="text-end fw-semibold">{product.displayprice}</td>
                                            <td className={mcClasses("mc-cell-nowrap mc-cell-muted")}>
                                                {product.displaylastsaved || "-"}
                                            </td>
                                            <td className="text-end">
                                                <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                                    <McTableActionMenu
                                                        label={`${labels.actions}: ${product.name}`}
                                                        items={[
                                                            {
                                                                key: "view",
                                                                label: labels.viewdetails,
                                                                icon: "bi bi-eye",
                                                                href: product.producturl,
                                                            },
                                                        ]}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {loading && (
                                        <tr>
                                            <td colSpan={7}>
                                                <div className={mcClasses("mc-product-admin__loading")}>
                                                    {labels.loading}
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                </McTableCard>

                <McTableCard
                    className={mcClasses("mc-wishlist-table-card")}
                    title={<h2 className={mcClasses("mc-card-title mb-0")}>{labels.recentwishlistactivity}</h2>}
                    toolbar={(
                        <div className={mcClasses("mc-wishlist-table-controls")}>
                            <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar mc-wishlist-toolbar")}>
                                <div className={mcClasses("mc-product-toolbar__search mc-wishlist-toolbar__search")}>
                                    <label className={mcClasses("mc-filter-label")} htmlFor="mc-wishlists-activity-search">
                                        {labels.search}
                                    </label>
                                    <input
                                        className={mcClasses("mc-form-control")}
                                        id="mc-wishlists-activity-search"
                                        onChange={(event) => setActivitySearchInput(event.target.value)}
                                        placeholder={labels.activitysearchplaceholder ?? labels.searchplaceholder}
                                        type="search"
                                        value={activitySearchInput}
                                    />
                                </div>
                                <label className={mcClasses("mc-product-toolbar__field")}>
                                    <span className={mcClasses("mc-filter-label")}>{labels.type}</span>
                                    <select
                                        className={mcClasses("mc-select")}
                                        onChange={(event) => updateActivityFilters({activitytype: event.target.value})}
                                        value={filters.activitytype}
                                    >
                                        {productTypes.map((option) => (
                                            <option key={option.value || "all"} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className={mcClasses("mc-table-design-page-size")}>
                                    <span className={mcClasses("mc-filter-label")}>{labels.perpage}</span>
                                    <select
                                        className={mcClasses("mc-select")}
                                        onChange={(event) => updateActivityFilters({
                                            activityperpage: Number(event.target.value) || 10,
                                        })}
                                        value={filters.activityperpage}
                                    >
                                        {perPageOptions.map((option) => (
                                            <option key={option} value={option}>{option}</option>
                                        ))}
                                    </select>
                                </label>
                            </div>
                        </div>
                    )}
                    footer={(
                        <McTableFooter
                            summary={(
                                <span>
                                    {labels.showing} {formatCount(activityRange.from)}-{formatCount(activityRange.to)}
                                    {" / "}
                                    {formatCount(activityTotal)}
                                </span>
                            )}
                            pagination={(
                                <McTablePagination
                                    previousLabel={labels.previous}
                                    nextLabel={labels.next}
                                    pageLabel={labels.page}
                                    page={Math.min(filters.activitypage + 1, activityPages)}
                                    totalPages={activityPages}
                                    previousDisabled={loading || filters.activitypage <= 0}
                                    nextDisabled={loading || filters.activitypage + 1 >= activityPages}
                                    onPrevious={() => updateActivityFilters({activitypage: Math.max(0, filters.activitypage - 1)})}
                                    onNext={() => updateActivityFilters({activitypage: filters.activitypage + 1})}
                                />
                            )}
                        />
                    )}
                >
                            <table
                                className={mcClasses("table mc-table mc-product-table mb-0")}
                                aria-label={labels.recentwishlistactivity}
                            >
                                <thead>
                                    <tr>
                                        <th scope="col">{renderActivitySortButton("customer", labels.customer)}</th>
                                        <th scope="col">{renderActivitySortButton("product", labels.product)}</th>
                                        <th scope="col">{renderActivitySortButton("producttype", labels.type)}</th>
                                        <th scope="col">{renderActivitySortButton("timecreated", labels.date)}</th>
                                        <th scope="col" className="text-end">{labels.actions}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {!loading && data?.activity.length === 0 && (
                                        <tr>
                                            <td colSpan={5}>
                                                <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                    <span className={mcClasses("mc-empty__icon")}>
                                                        <i className="bi bi-clock-history" aria-hidden="true" />
                                                    </span>
                                                    <p className={mcClasses("mc-empty__title")}>
                                                        {activityTotal === 0 ? labels.nowishlistitems : labels.noresults}
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {data?.activity.map((row) => (
                                        <tr key={row.id}>
                                            <td>
                                                <a className="fw-semibold" href={row.customerurl}>
                                                    {row.customername}
                                                </a>
                                                <div className={mcClasses("mc-cell-muted small")}>{row.customeremail}</div>
                                            </td>
                                            <td>
                                                <a className="fw-semibold" href={row.producturl}>
                                                    {row.productname}
                                                </a>
                                                {row.sku && (
                                                    <div className={mcClasses("mc-cell-muted small")}>{row.sku}</div>
                                                )}
                                            </td>
                                            <td>
                                                <McBadge variant="neutral" tone="soft">{row.typelabel}</McBadge>
                                            </td>
                                            <td className={mcClasses("mc-cell-nowrap mc-cell-muted")}>
                                                {row.displaydate || "-"}
                                            </td>
                                            <td className="text-end">
                                                <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                                    <McTableActionMenu
                                                        label={`${labels.actions}: ${row.customername}`}
                                                        items={[
                                                            {
                                                                key: "customer",
                                                                label: labels.customer,
                                                                icon: "bi bi-person",
                                                                href: row.customerurl,
                                                            },
                                                            {
                                                                key: "product",
                                                                label: labels.product,
                                                                icon: "bi bi-box",
                                                                href: row.producturl,
                                                            },
                                                        ]}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {loading && (
                                        <tr>
                                            <td colSpan={5}>
                                                <div className={mcClasses("mc-product-admin__loading")}>
                                                    {labels.loading}
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                </McTableCard>
            </div>
        </section>
    );
}

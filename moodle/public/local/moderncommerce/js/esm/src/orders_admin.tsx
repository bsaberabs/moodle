// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin orders list for Modern Commerce.
 *
 * @module     local_moderncommerce/orders_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {mcClasses, sortIconClass, toast, useModernCommerceClassSync} from "./design_system";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
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
    search: string;
    status: string;
    page: number;
    perpage: number;
    sort: string;
    direction: "ASC" | "DESC";
};

type Labels = Record<string, string>;

type Order = {
    id: number;
    ordernumber: string;
    ordertype: string;
    status: string;
    statuslabel: string;
    statusclass: string;
    customerid: number;
    customername: string;
    customeremail: string;
    customerurl: string;
    itemcount: number;
    rawtotal: number;
    displaytotal: string;
    paymentmethod: string;
    timecreated: number;
    displaydate: string;
    viewurl: string;
};

type Stats = {
    totalorders: number;
    paidorders: number;
    pendingorders: number;
    refundedorders: number;
    displayrevenue: string;
};

type OrdersResponse = {
    items: Order[];
    total: number;
    page: number;
    perpage: number;
    sort: string;
    direction: "ASC" | "DESC";
    canmanage: boolean;
    stats: Stats;
};

type UpdateStatusResponse = {
    success: boolean;
    orderid: number;
    status: string;
    statuslabel: string;
    statusclass: string;
    message: string;
};

type OrdersAdminProps = {
    methodName: string;
    updateStatusMethodName: string;
    initialFilters: Partial<Filters>;
    statusOptions: SelectOption[];
    perPageOptions: number[];
    labels: Labels;
};

const defaultFilters: Filters = {
    search: "",
    status: "",
    page: 0,
    perpage: 10,
    sort: "timecreated",
    direction: "DESC",
};

const normaliseFilters = (filters: Partial<Filters>): Filters => ({
    ...defaultFilters,
    ...filters,
    page: Math.max(0, Number(filters.page ?? defaultFilters.page) || 0),
    perpage: Number(filters.perpage ?? defaultFilters.perpage) || defaultFilters.perpage,
    direction: filters.direction === "ASC" ? "ASC" : "DESC",
});

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

const badgeVariant = (variant: string): McBadgeVariant => {
    const variants: McBadgeVariant[] = ["primary", "secondary", "accent", "success", "warning", "danger", "info", "neutral"];
    return variants.includes(variant as McBadgeVariant) ? variant as McBadgeVariant : "neutral";
};

export default function OrdersAdmin({
    methodName,
    updateStatusMethodName,
    initialFilters,
    statusOptions,
    perPageOptions,
    labels,
}: OrdersAdminProps) {
    useModernCommerceClassSync();
    const [filters, setFilters] = useState<Filters>(() => normaliseFilters(initialFilters));
    const [searchInput, setSearchInput] = useState(filters.search);
    const [data, setData] = useState<OrdersResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [busyId, setBusyId] = useState(0);
    const [reloadToken, setReloadToken] = useState(0);

    const editableStatuses = statusOptions.filter((option) => option.value !== "");

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setFilters((current) => {
                if (current.search === searchInput) {
                    return current;
                }

                return {...current, search: searchInput, page: 0};
            });
        }, 350);

        return () => window.clearTimeout(timer);
    }, [searchInput]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<OrdersResponse>(methodName, filters)
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                }

                return result;
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
        const refreshButton = document.getElementById("moderncommerce-orders-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    const total = data?.total ?? 0;
    const stats = data?.stats;
    const canmanage = data?.canmanage ?? false;
    const totalPages = Math.max(1, Math.ceil(total / filters.perpage));
    const range = getVisibleRange(total, filters.page, filters.perpage);

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({
            ...current,
            ...changes,
            page: changes.page ?? 0,
        }));
    };

    const changeSort = (sort: string) => {
        setFilters((current) => {
            if (current.sort === sort) {
                return {
                    ...current,
                    direction: current.direction === "ASC" ? "DESC" : "ASC",
                    page: 0,
                };
            }

            return {
                ...current,
                sort,
                direction: sort === "ordernumber" ? "ASC" : "DESC",
                page: 0,
            };
        });
    };

    const changeStatus = async(order: Order, status: string) => {
        if (status === order.status) {
            return;
        }

        setBusyId(order.id);
        setError("");

        try {
            const result = await callMoodleService<UpdateStatusResponse>(updateStatusMethodName, {
                orderid: order.id,
                status,
            });

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

    const sortHeaderProps = (sort: string): {"aria-sort"?: "ascending" | "descending"} =>
        filters.sort === sort
            ? {"aria-sort": filters.direction === "ASC" ? "ascending" : "descending"}
            : {};

    const renderSortButton = (sort: string, label: string, align = "text-start") => {
        const active = filters.sort === sort;
        return (
            <button className={mcClasses("mc-table-sort", align)} onClick={() => changeSort(sort)} type="button">
                <span>{label}</span>
                <i
                    className={mcClasses(
                        "mc-table-sort__indicator",
                        active && "mc-table-sort__indicator--active",
                        sortIconClass(active, filters.direction),
                    )}
                    aria-hidden="true"
                />
            </button>
        );
    };

    const renderOrderActions = (order: Order) => (
        <div className={mcClasses("mc-table-design__actions justify-content-end")}>
            <McTableActionMenu
                label={`${labels.actions}: ${order.ordernumber}`}
                items={[
                    {
                        key: "view",
                        label: labels.view,
                        icon: "bi bi-eye",
                        href: order.viewurl,
                    },
                    ...(
                        canmanage
                            ? editableStatuses.map((option) => ({
                                key: `status-${option.value}`,
                                label: option.label,
                                icon: "bi bi-pencil",
                                disabled: busyId === order.id || option.value === order.status,
                                current: option.value === order.status,
                                onClick: () => void changeStatus(order, option.value),
                            }))
                            : []
                    ),
                ]}
            />
        </div>
    );

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
                        <i className="bi bi-receipt mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.totalorders}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.totalorders)}</strong>
                        </div>
                        <i className="bi bi-receipt mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                        <i className="bi bi-cash-stack mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.revenue}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{stats.displayrevenue}</strong>
                        </div>
                        <i className="bi bi-cash-stack mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--warning")}>
                        <i className="bi bi-hourglass-split mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.pendingorders}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.pendingorders)}</strong>
                        </div>
                        <i className="bi bi-hourglass-split mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--info")}>
                        <i className="bi bi-arrow-counterclockwise mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.refundedorders}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.refundedorders)}</strong>
                        </div>
                        <i className="bi bi-arrow-counterclockwise mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                </div>
            )}

            <McTableCard
                title={<h2 className={mcClasses("mc-card-title")}>{labels.title}</h2>}
                toolbar={(
                    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                        <div className={mcClasses("mc-product-toolbar__search")}>
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-orders-search">
                                {labels.search}
                            </label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-orders-search"
                                onChange={(event) => setSearchInput(event.target.value)}
                                placeholder={labels.searchplaceholder}
                                type="search"
                                value={searchInput}
                            />
                        </div>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.status}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({status: event.target.value})}
                                value={filters.status}
                            >
                                {statusOptions.map((option) => (
                                    <option key={option.value || "all"} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                        </label>
                        <label className={mcClasses("mc-table-design-page-size")}>
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
                    </div>
                )}
                footer={(
                    <McTableFooter
                        summary={(
                            <>
                                <span>
                                    {labels.showing} {formatCount(range.from)}-{formatCount(range.to)} / {formatCount(total)}
                                </span>
                                {loading && <span>{labels.loading}</span>}
                            </>
                        )}
                        pagination={(
                            <McTablePagination
                                previousLabel={labels.previous}
                                nextLabel={labels.next}
                                pageLabel={labels.page}
                                page={Math.min(filters.page + 1, totalPages)}
                                totalPages={totalPages}
                                previousDisabled={loading || filters.page <= 0}
                                nextDisabled={loading || filters.page + 1 >= totalPages}
                                onPrevious={() => updateFilters({page: Math.max(0, filters.page - 1)})}
                                onNext={() => updateFilters({page: filters.page + 1})}
                            />
                        )}
                    />
                )}
            >
                <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.title}>
                            <thead>
                                <tr>
                                    <th scope="col" {...sortHeaderProps("ordernumber")}>{renderSortButton("ordernumber", labels.ordernumber)}</th>
                                    <th scope="col">{labels.customer}</th>
                                    <th scope="col" {...sortHeaderProps("timecreated")}>{renderSortButton("timecreated", labels.date)}</th>
                                    <th scope="col" className="text-end">{labels.items}</th>
                                    <th scope="col" className="text-end" {...sortHeaderProps("total")}>
                                        {renderSortButton("total", labels.total, "text-end")}
                                    </th>
                                    <th scope="col">{labels.paymentmethod}</th>
                                    <th scope="col" {...sortHeaderProps("status")}>{renderSortButton("status", labels.status)}</th>
                                    <th scope="col" className="text-end">{labels.actions}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {!loading && data?.items.length === 0 && (
                                    <tr>
                                        <td colSpan={8}>
                                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                <span className={mcClasses("mc-empty__icon")}>
                                                    <i className="bi bi-receipt" aria-hidden="true" />
                                                </span>
                                                <p className={mcClasses("mc-empty__title")}>
                                                    {total === 0 ? labels.noorders : labels.noresults}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {data?.items.map((order) => (
                                    <tr key={order.id}>
                                        <td>
                                            <a className="fw-semibold" href={order.viewurl}>{order.ordernumber}</a>
                                            <div className={mcClasses("mc-cell-muted")}>{order.ordertype}</div>
                                        </td>
                                        <td>
                                            <div className="fw-semibold">{order.customername}</div>
                                            <div className={mcClasses("mc-cell-muted")}>{order.customeremail}</div>
                                        </td>
                                        <td className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{order.displaydate}</td>
                                        <td className="text-end">{formatCount(order.itemcount)}</td>
                                        <td className="text-end fw-semibold">{order.displaytotal}</td>
                                        <td className={mcClasses("mc-cell-muted")}>{order.paymentmethod}</td>
                                        <td>
                                            <McBadge variant={badgeVariant(order.statusclass)} tone="soft" dot>
                                                {order.statuslabel}
                                            </McBadge>
                                        </td>
                                        <td className="text-end">{renderOrderActions(order)}</td>
                                    </tr>
                                ))}
                                {loading && (
                                    <tr>
                                        <td colSpan={8}>
                                            <div className={mcClasses("mc-product-admin__loading")}>
                                                {labels.loading}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                </table>
            </McTableCard>
        </section>
    );
}

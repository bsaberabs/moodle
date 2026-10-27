// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Generic React admin ledger (read-only event/audit tables) for Modern Commerce.
 *
 * @module     local_moderncommerce/ledger_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Fragment, useEffect, useState} from "react";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {McTableCard, McTableFooter, McTablePagination} from "./table_components";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type SelectOption = {
    value: string;
    label: string;
};

type Column = {
    label: string;
    align: string;
};

type Cell = {
    value: string;
    badge: string;
};

type Detail = {
    label: string;
    value: string;
    badge: string;
};

type Row = {
    id: number;
    cells: Cell[];
    details?: Detail[];
};

type LedgerResponse = {
    columns: Column[];
    items: Row[];
    total: number;
    page: number;
    perpage: number;
    gateways: SelectOption[];
};

type Filters = {
    search: string;
    gateway: string;
    status: string;
    page: number;
    perpage: number;
};

type LedgerAdminProps = {
    methodName: string;
    statusOptions: SelectOption[];
    perPageOptions: number[];
    labels: Labels;
};

const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php?sesskey=${encodeURIComponent(M.cfg.sesskey)}&info=${encodeURIComponent(methodName)}`;
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

const formatCount = (value: number): string => new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);

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

export default function LedgerAdmin({methodName, statusOptions, perPageOptions, labels}: LedgerAdminProps) {
    useModernCommerceClassSync();
    const [filters, setFilters] = useState<Filters>({search: "", gateway: "", status: "", page: 0, perpage: 10});
    const [searchInput, setSearchInput] = useState("");
    const [data, setData] = useState<LedgerResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [reloadToken, setReloadToken] = useState(0);
    const [expandedId, setExpandedId] = useState(0);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setFilters((current) => current.search === searchInput ? current : {...current, search: searchInput, page: 0});
        }, 350);

        return () => window.clearTimeout(timer);
    }, [searchInput]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<LedgerResponse>(methodName, filters)
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
        const refreshButton = document.getElementById("moderncommerce-ledger-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    const total = data?.total ?? 0;
    const columns = data?.columns ?? [];
    const totalPages = Math.max(1, Math.ceil(total / filters.perpage));
    const gateways = data?.gateways ?? [];
    const range = getVisibleRange(total, filters.page, filters.perpage);

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({...current, ...changes, page: changes.page ?? 0}));
    };

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            <McTableCard
                title={<h2 className={mcClasses("mc-card-title")}>{labels.title}</h2>}
                toolbar={(
                    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                        <div className={mcClasses("mc-product-toolbar__search")}>
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-ledger-search">{labels.search}</label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-ledger-search"
                                onChange={(event) => setSearchInput(event.target.value)}
                                type="search"
                                value={searchInput}
                            />
                        </div>
                        {gateways.length > 0 && (
                            <label className={mcClasses("mc-product-toolbar__field")}>
                                <span className={mcClasses("mc-filter-label")}>{labels.gateway}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    onChange={(event) => updateFilters({gateway: event.target.value})}
                                    value={filters.gateway}
                                >
                                    <option value="">{labels.allgateways}</option>
                                    {gateways.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </label>
                        )}
                        {statusOptions.length > 0 && (
                            <label className={mcClasses("mc-product-toolbar__field")}>
                                <span className={mcClasses("mc-filter-label")}>{labels.status}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    onChange={(event) => updateFilters({status: event.target.value})}
                                    value={filters.status}
                                >
                                    <option value="">{labels.allstatuses}</option>
                                    {statusOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </label>
                        )}
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
                        summary={<span>{labels.showing} {formatCount(range.from)}-{formatCount(range.to)} / {formatCount(total)}</span>}
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
                                    {columns.map((column, index) => (
                                        <th key={index} scope="col" className={column.align === "end" ? "text-end" : ""}>
                                            {column.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {!loading && data?.items.length === 0 && (
                                    <tr>
                                        <td colSpan={Math.max(1, columns.length)}>
                                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                <span className={mcClasses("mc-empty__icon")}><i className="bi bi-list-columns" aria-hidden="true" /></span>
                                                <p className={mcClasses("mc-empty__title")}>{labels.noevents}</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {data?.items.map((row) => {
                                    const details = row.details ?? [];
                                    const hasDetails = details.length > 0;
                                    const expanded = expandedId === row.id;

                                    return (
                                        <Fragment key={row.id}>
                                            <tr>
                                                {row.cells.map((cell, index) => {
                                                    const align = columns[index]?.align === "end" ? "text-end" : "";
                                                    const content = cell.badge
                                                        ? <McBadge variant={badgeVariant(cell.badge)} tone="soft" dot>{cell.value}</McBadge>
                                                        : <span className={mcClasses(index === 0 ? "fw-semibold" : "mc-cell-muted")}>{cell.value}</span>;

                                                    return (
                                                        <td key={index} className={align}>
                                                            {index === 0 && hasDetails ? (
                                                                <div className="d-flex align-items-center gap-2">
                                                                    <button
                                                                        aria-expanded={expanded}
                                                                        aria-label={expanded ? labels.hidedetails : labels.showdetails}
                                                                        className={mcClasses("mc-table-design__action mc-table-design__action--view")}
                                                                        onClick={() => setExpandedId(expanded ? 0 : row.id)}
                                                                        title={expanded ? labels.hidedetails : labels.showdetails}
                                                                        type="button"
                                                                    >
                                                                        <i className={`bi ${expanded ? "bi-chevron-down" : "bi-chevron-right"}`} aria-hidden="true" />
                                                                    </button>
                                                                    {content}
                                                                </div>
                                                            ) : content}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                            {expanded && hasDetails && (
                                                <tr>
                                                    <td colSpan={Math.max(1, columns.length)}>
                                                        <dl className={mcClasses("mc-ledger-details row mb-0")}>
                                                            {details.map((detail, index) => (
                                                                <Fragment key={`${detail.label}-${index}`}>
                                                                    <dt className={mcClasses("col-md-3 mc-cell-muted")}>{detail.label}</dt>
                                                                    <dd className="col-md-9">
                                                                        {detail.badge
                                                                            ? <McBadge variant={badgeVariant(detail.badge)} tone="soft" dot>{detail.value}</McBadge>
                                                                            : <pre className={mcClasses("mc-ledger-details__value mb-0")}>{detail.value}</pre>}
                                                                    </dd>
                                                                </Fragment>
                                                            ))}
                                                        </dl>
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    );
                                })}
                                {loading && (
                                    <tr>
                                        <td colSpan={Math.max(1, columns.length)}>
                                            <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                </table>
            </McTableCard>
        </section>
    );
}

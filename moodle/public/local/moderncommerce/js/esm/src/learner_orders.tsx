// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React learner orders page for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_orders
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import type {Dispatch} from "react";
import {badgeClass, callMoodleService, formatCount, Labels} from "./learner_common";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";
import {LearnerStatStrip, LearnerStatTile} from "./learner_stat_tiles";
import {McTablePagination} from "./table_components";

type Order = {
    id: number;
    ordernumber: string;
    date: string;
    datetime: string;
    relativedate: string;
    itemcount: number;
    itemstext: string;
    firstitemname: string;
    total: string;
    status: string;
    statuslabel: string;
    statusclass: string;
    ispaid: boolean;
    ispending: boolean;
    viewurl: string;
    continueurl: string;
    invoiceurl: string;
};

type ManualInvoice = {
    id: number;
    invoicenumber: string;
    date: string;
    datetime: string;
    duedate: string;
    total: string;
    status: string;
    statuslabel: string;
    statusclass: string;
    downloadurl: string;
};

type Stats = {
    total: number;
    paid: number;
    pending: number;
    cancelled: number;
    totalspent: string;
};

type InvoiceStats = {
    total: number;
    paid: number;
    outstanding: number;
    displayoutstanding: string;
};

type Filters = {
    status: string;
    page: number;
    perpage: number;
};

type OrdersResponse = {
    success: boolean;
    message: string;
    orders: Order[];
    manualinvoices: ManualInvoice[];
    manualinvoicestotal: number;
    invoicestats: InvoiceStats;
    stats: Stats;
    total: number;
    page: number;
    perpage: number;
    totalpages: number;
    hasprevious: boolean;
    hasnext: boolean;
    filters: {
        status: string;
    };
    urls: {
        catalog: string;
        orders: string;
    };
};

type LearnerOrdersProps = {
    methodName: string;
    initialFilters: Filters;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

const statusOptions = (labels: Labels) => [
    {value: "", label: labels.allorders},
    {value: "paid", label: labels.status_paid},
    {value: "pending", label: labels.status_pending},
    {value: "cancelled", label: labels.status_cancelled},
    {value: "refunded", label: labels.status_refunded},
];

const syncUrl = (filters: Filters) => {
    const url = new URL(window.location.href);

    if (filters.status) {
        url.searchParams.set("status", filters.status);
    } else {
        url.searchParams.delete("status");
    }

    if (filters.page > 0) {
        url.searchParams.set("page", String(filters.page));
    } else {
        url.searchParams.delete("page");
    }

    window.history.replaceState(null, "", url.toString());
};

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-receipt" aria-hidden="true" /></span>
            <p className={mcClasses("mc-empty__title")}>{labels.loading}</p>
        </div>
    );
}

function Pagination({
    data,
    labels,
    onPage,
}: {
    data: OrdersResponse;
    labels: Labels;
    onPage: Dispatch<number>;
}) {
    if (data.totalpages <= 1) {
        return null;
    }

    return (
        <McTablePagination
            className="mt-3"
            previousLabel={labels.previous}
            nextLabel={labels.next}
            pageLabel={`${formatCount(data.page + 1)} ${labels.of} ${formatCount(data.totalpages)}`}
            previousDisabled={!data.hasprevious}
            nextDisabled={!data.hasnext}
            onPrevious={() => onPage(Math.max(0, data.page - 1))}
            onNext={() => onPage(data.page + 1)}
        />
    );
}

function OrderActions({
    order,
    labels,
}: {
    order: Order;
    labels: Labels;
}) {
    return (
        <div className={mcClasses("mc-learner-order-actions")}>
            {order.ispending && (
                <a className={mcClasses("mc-button btn-mc-primary")} href={order.continueurl}>
                    <i className="bi bi-credit-card" aria-hidden="true" />
                    {labels.continuepayment}
                </a>
            )}
            <a className={mcClasses("mc-button btn-mc-secondary")} href={order.viewurl}>
                <i className="bi bi-eye" aria-hidden="true" />
                {labels.view}
            </a>
            {order.ispaid && (
                <a className={mcClasses("mc-button btn-mc-secondary")} href={order.invoiceurl}>
                    <i className="bi bi-download" aria-hidden="true" />
                    {labels.downloadinvoice}
                </a>
            )}
        </div>
    );
}

function OrderCard({
    order,
    labels,
}: {
    order: Order;
    labels: Labels;
}) {
    return (
        <article className={mcClasses("mc-learner-order-card", order.ispending && "mc-learner-order-card--pending")}>
            <div className={mcClasses("mc-learner-order-card__header")}>
                <div>
                    <a className={mcClasses("mc-learner-order-card__number")} href={order.viewurl}>
                        {order.ordernumber}
                    </a>
                    <time className={mcClasses("mc-cell-muted small d-block")} dateTime={order.datetime}>
                        {order.date}
                    </time>
                </div>
                <span className={mcClasses(`mc-badge mc-badge--${badgeClass(order.statusclass)}`)}>
                    {order.statuslabel}
                </span>
            </div>
            <div className={mcClasses("mc-learner-order-card__body")}>
                <div>
                    <span className={mcClasses("mc-learner-order-card__label")}>{labels.items}</span>
                    <strong>{order.firstitemname}</strong>
                    <span className={mcClasses("mc-cell-muted small d-block")}>
                        {formatCount(order.itemcount)} {order.itemstext}
                    </span>
                </div>
                <div>
                    <span className={mcClasses("mc-learner-order-card__label")}>{labels.total}</span>
                    <strong>{order.total}</strong>
                </div>
            </div>
            <OrderActions order={order} labels={labels} />
        </article>
    );
}

function ManualInvoicesTable({
    invoices,
    labels,
}: {
    invoices: ManualInvoice[];
    labels: Labels;
}) {
    if (invoices.length === 0) {
        return null;
    }

    return (
        <div className={mcClasses("mc-card mt-4")}>
            <div className={mcClasses("mc-card-header")}>
                <div>
                    <h2 className={mcClasses("mc-card-title mb-1")}>
                        {labels.manualinvoices || labels.invoices}
                    </h2>
                    <div className={mcClasses("mc-cell-muted")}>
                        {labels.manualinvoicesdesc || labels.viewandmanageorders}
                    </div>
                </div>
            </div>
            <div className={mcClasses("mc-card-body p-0")}>
                <div className="table-responsive">
                    <table className="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{labels.invoice}</th>
                                <th scope="col">{labels.status}</th>
                                <th scope="col">{labels.duedate}</th>
                                <th scope="col" className="text-end">{labels.total}</th>
                                <th scope="col" className="text-end">{labels.actions}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.map((invoice) => (
                                <tr key={invoice.id}>
                                    <td>
                                        <div className="fw-semibold">{invoice.invoicenumber}</div>
                                        <div className={mcClasses("mc-cell-muted small")}>{invoice.date}</div>
                                    </td>
                                    <td>
                                        <span className={mcClasses(`mc-badge mc-badge--${badgeClass(invoice.statusclass)}`)}>
                                            {invoice.statuslabel}
                                        </span>
                                    </td>
                                    <td className={mcClasses("mc-cell-muted")}>{invoice.duedate}</td>
                                    <td className="text-end fw-semibold">{invoice.total}</td>
                                    <td className="text-end">
                                        <a
                                            className={mcClasses("mc-button btn-mc-secondary py-1 px-2")}
                                            href={invoice.downloadurl}
                                        >
                                            <i className="bi bi-download me-1" aria-hidden="true" />
                                            {labels.downloadinvoice}
                                        </a>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

export default function LearnerOrders({
    methodName,
    initialFilters,
    labels,
    layout,
}: LearnerOrdersProps) {
    useModernCommerceClassSync();
    const [filters, setFilters] = useState<Filters>({
        ...initialFilters,
        page: Math.max(0, initialFilters.page || 0),
        perpage: Math.max(1, initialFilters.perpage || 10),
    });
    const [data, setData] = useState<OrdersResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");
        syncUrl(filters);

        callMoodleService<OrdersResponse>(methodName, filters)
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
    }, [filters, methodName]);

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({
            ...current,
            ...changes,
            page: changes.page ?? 0,
        }));
    };

    const stats = data?.stats ?? {
        total: 0,
        paid: 0,
        pending: 0,
        cancelled: 0,
        totalspent: "",
    };
    const invoiceStats = data?.invoicestats ?? {
        total: 0,
        paid: 0,
        outstanding: 0,
        displayoutstanding: "",
    };
    const orders = data?.orders ?? [];
    const manualInvoices = data?.manualinvoices ?? [];
    const catalogUrl = data?.urls.catalog ?? "#";
    const actions = (
        <a className={mcClasses("mc-button btn-mc-secondary")} href={catalogUrl}>
            <i className="bi bi-grid" aria-hidden="true" />
            {labels.browsecatalog}
        </a>
    );

    return (
        <ModernLearnerLayout
            activeNav="orders"
            title={labels.myorders}
            subtitle={labels.viewandmanageorders}
            labels={labels}
            layout={layout}
            actions={actions}
        >
            <div className={mcClasses("mc-learner-orders")}>
            <LearnerStatStrip>
                <LearnerStatTile label={labels.allorders} value={stats.total} icon="bi-receipt" variant="primary" />
                <LearnerStatTile label={labels.paid} value={stats.paid} icon="bi-check2-circle" variant="success" />
                <LearnerStatTile label={labels.pending} value={stats.pending} icon="bi-clock-history" variant="warning" />
                <LearnerStatTile label={labels.totalspent} value={stats.totalspent} icon="bi-cash-stack" variant="info" />
                <LearnerStatTile
                    label={labels.manualinvoices || labels.invoices}
                    value={invoiceStats.total}
                    icon="bi-file-earmark-text"
                    variant="neutral"
                />
                <LearnerStatTile
                    label={labels.outstanding}
                    value={invoiceStats.displayoutstanding}
                    icon="bi-exclamation-circle"
                    variant="warning"
                />
            </LearnerStatStrip>

            <div className={mcClasses("mc-toolbar mb-3")}>
                <div className="row g-2 align-items-center">
                    <div className="col-sm-6 col-lg-3">
                        <select
                            className="form-select"
                            value={filters.status}
                            aria-label={labels.status}
                            onChange={(event) => updateFilters({status: event.target.value})}
                        >
                            {statusOptions(labels).map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {loading && <LoadingState labels={labels} />}

            {!loading && (error || (data && !data.success)) && (
                <div className={mcClasses("mc-alert mc-alert--warning")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error || data?.message}</div>
                </div>
            )}

            {!loading && !error && data?.success && orders.length === 0 && manualInvoices.length === 0 && (
                <div className={mcClasses("mc-empty mc-empty--centered")}>
                    <span className={mcClasses("mc-empty__icon")}><i className="bi bi-receipt" aria-hidden="true" /></span>
                    <p className={mcClasses("mc-empty__title")}>{labels.noorders}</p>
                    <p className={mcClasses("mc-empty__desc")}>{labels.noordersyet}</p>
                    <a className={mcClasses("mc-button btn-mc-primary")} href={catalogUrl}>
                        <i className="bi bi-grid me-1" aria-hidden="true" />
                        {labels.browsecatalog}
                    </a>
                </div>
            )}

            {!loading && !error && orders.length > 0 && data && (
                <div className={mcClasses("mc-card")}>
                    <div className={mcClasses("mc-card-body p-0")}>
                        <div className={mcClasses("mc-learner-orders__cards")}>
                            {orders.map((order) => (
                                <OrderCard key={order.id} order={order} labels={labels} />
                            ))}
                        </div>
                        <div className={mcClasses("table-responsive mc-learner-orders__table")}>
                            <table className="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{labels.order}</th>
                                        <th scope="col">{labels.items}</th>
                                        <th scope="col">{labels.status}</th>
                                        <th scope="col" className="text-end">{labels.total}</th>
                                        <th scope="col" className="text-end">{labels.actions}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {orders.map((order) => (
                                        <tr key={order.id}>
                                            <td>
                                                <a className="fw-semibold text-decoration-none" href={order.viewurl}>
                                                    {order.ordernumber}
                                                </a>
                                                <div className={mcClasses("mc-cell-muted small")}>{order.date}</div>
                                            </td>
                                            <td>
                                                <div className="text-truncate">{order.firstitemname}</div>
                                                <div className={mcClasses("mc-cell-muted small")}>
                                                    {formatCount(order.itemcount)} {order.itemstext}
                                                </div>
                                            </td>
                                            <td>
                                                <span className={mcClasses(`mc-badge mc-badge--${badgeClass(order.statusclass)}`)}>
                                                    {order.statuslabel}
                                                </span>
                                            </td>
                                            <td className="text-end fw-semibold">{order.total}</td>
                                            <td className="text-end">
                                                <OrderActions order={order} labels={labels} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div className={mcClasses("mc-card-footer")}>
                        <Pagination data={data} labels={labels} onPage={(page) => updateFilters({page})} />
                    </div>
                </div>
            )}
            {!loading && !error && data?.success && (
                <ManualInvoicesTable invoices={manualInvoices} labels={labels} />
            )}
            </div>
        </ModernLearnerLayout>
    );
}

// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin customer detail for Modern Commerce.
 *
 * @module     local_moderncommerce/customer_detail
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import type {ReactNode} from "react";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {McTableActionMenu, McTableCard, McTableFooter, McTablePagination} from "./table_components";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type Customer = {
    id: number;
    fullname: string;
    email: string;
    phone: string;
    city: string;
    country: string;
    accountcreated: string;
    statuslabel: string;
    statusclass: string;
};

type Stats = {
    ordercount: number;
    paidorders: number;
    pendingorders: number;
    refundedorders: number;
    wishlistcount: number;
    totalspent: number;
    displaytotalspent: string;
    refundedtotal: number;
    displayrefundedtotal: string;
    firstorder: string;
    lastorder: string;
};

type Billing = {
    hasdetails: boolean;
    name: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    state: string;
    country: string;
    zipcode: string;
};

type OrderItem = {
    name: string;
    typelabel: string;
    quantity: number;
};

type CustomerOrder = {
    id: number;
    ordernumber: string;
    ordertype: string;
    status: string;
    statuslabel: string;
    statusclass: string;
    itemcount: number;
    items: OrderItem[];
    paymentmethod: string;
    displaytotal: string;
    displaydate: string;
    viewurl: string;
};

type CustomerDetailResponse = {
    customer: Customer;
    stats: Stats;
    billing: Billing;
    orders: CustomerOrder[];
    total: number;
    page: number;
    perpage: number;
    totalpages: number;
};

type CustomerDetailProps = {
    customerId: number;
    methodName: string;
    initialPage: number;
    initialPerPage: number;
    customersUrl: string;
    labels: Labels;
};

const fallback = "-";

const getLabel = (labels: Labels, key: string, defaultValue?: string): string => labels[key] ?? defaultValue ?? key;

const displayValue = (value: string | number | undefined): string => {
    if (value === undefined || value === null || value === "") {
        return fallback;
    }
    return String(value);
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

    return first.data ?? first;
};

const StatTile = ({
    label,
    value,
    icon,
    variant,
}: {
    label: string;
    value: string | number;
    icon: string;
    variant: string;
}) => (
    <article className={mcClasses(`mc-stat-tile mc-stat-tile--${variant}`)}>
        <i className={`bi ${icon} mc-stat-tile__icon`} aria-hidden="true" />
        <div className={mcClasses("mc-stat-tile__body")}>
            <span className={mcClasses("mc-stat-tile__label")}>{label}</span>
            <strong className={mcClasses("mc-stat-tile__value")}>{value}</strong>
        </div>
        <i className={`bi ${icon} mc-stat-tile__watermark`} aria-hidden="true" />
    </article>
);

const DetailRows = ({rows}: {rows: Array<[string, ReactNode]>}) => (
    <dl className="mb-0">
        {rows.map(([label, value]) => (
            <div className="d-flex justify-content-between gap-3 py-1 border-bottom" key={label}>
                <dt className={mcClasses("fw-normal text-muted")}>{label}</dt>
                <dd className="mb-0 text-end">{typeof value === "string" || typeof value === "number" ? displayValue(value) : value}</dd>
            </div>
        ))}
    </dl>
);

const OrderItems = ({items, itemcount, labels}: {items: OrderItem[]; itemcount: number; labels: Labels}) => {
    if (items.length === 0) {
        return <span className={mcClasses("mc-cell-muted")}>{itemcount} {getLabel(labels, "items")}</span>;
    }

    const visible = items.slice(0, 2);
    const remaining = Math.max(0, items.length - visible.length);

    return (
        <div className="d-flex flex-column gap-1">
            {visible.map((item, index) => (
                <span key={`${item.name}-${index}`}>
                    {item.name}
                    {item.quantity > 1 && <span className={mcClasses("mc-cell-muted")}> x{item.quantity}</span>}
                </span>
            ))}
            {remaining > 0 && (
                <span className={mcClasses("mc-cell-muted small")}>+{remaining} {getLabel(labels, "items")}</span>
            )}
        </div>
    );
};

export default function CustomerDetail({
    customerId,
    methodName,
    initialPage,
    initialPerPage,
    customersUrl,
    labels,
}: CustomerDetailProps) {
    useModernCommerceClassSync();

    const [data, setData] = useState<CustomerDetailResponse | null>(null);
    const [page, setPage] = useState(Math.max(0, Number(initialPage) || 0));
    const [perpage] = useState(Math.max(10, Number(initialPerPage) || 10));
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        callMoodleService<CustomerDetailResponse>(methodName, {
            id: customerId,
            page,
            perpage,
        }).then((response) => {
            if (!cancelled) {
                setData(response);
            }
        }).catch((exception) => {
            if (!cancelled) {
                setError(exception instanceof Error ? exception.message : String(exception));
            }
        }).finally(() => {
            if (!cancelled) {
                setLoading(false);
            }
        });

        return () => {
            cancelled = true;
        };
    }, [customerId, methodName, page, perpage]);

    if (loading && !data) {
        return <div className={mcClasses("mc-product-admin__loading")}>{getLabel(labels, "loading")}</div>;
    }

    if (error && !data) {
        return (
            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                <div className={mcClasses("mc-alert__body")}>{error}</div>
            </div>
        );
    }

    if (!data) {
        return null;
    }

    const {customer, stats, billing, orders} = data;
    const canPrev = data.page > 0;
    const canNext = data.page + 1 < data.totalpages;
    const range = getVisibleRange(data.total, data.page, data.perpage);
    const statusBadge = (
        <McBadge variant={badgeVariant(customer.statusclass)} tone="soft" dot>
            {customer.statuslabel}
        </McBadge>
    );
    const renderOrderActions = (order: CustomerOrder) => (
        <div className={mcClasses("mc-table-design__actions justify-content-end")}>
            <McTableActionMenu
                label={`${getLabel(labels, "actions")}: ${order.ordernumber}`}
                items={[
                    {
                        key: "view",
                        label: getLabel(labels, "viewdetails"),
                        icon: "bi bi-eye",
                        href: order.viewurl,
                    },
                ]}
            />
        </div>
    );

    return (
        <section className={mcClasses("mc-customer-detail")} aria-label={getLabel(labels, "customerdetails")}>
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            <div className={mcClasses("mc-stat-strip")} aria-label={getLabel(labels, "customerdetails")}>
                <StatTile
                    label={getLabel(labels, "totalorders")}
                    value={formatCount(stats.ordercount)}
                    icon="bi-bag"
                    variant="primary"
                />
                <StatTile
                    label={getLabel(labels, "totalspent")}
                    value={stats.displaytotalspent}
                    icon="bi-cash-stack"
                    variant="success"
                />
                <StatTile
                    label={getLabel(labels, "pendingorders")}
                    value={formatCount(stats.pendingorders)}
                    icon="bi-hourglass-split"
                    variant="warning"
                />
                <StatTile
                    label={getLabel(labels, "saveditems")}
                    value={formatCount(stats.wishlistcount)}
                    icon="bi-heart"
                    variant="info"
                />
            </div>

            <div className="row g-3 mb-3">
                <div className="col-xl-6">
                    <div className={mcClasses("mc-card h-100")}>
                        <div className={mcClasses("mc-card-header")}>
                            <h2 className={mcClasses("mc-card-title mb-0")}>{customer.fullname}</h2>
                            <div className={mcClasses("mc-card-sub")}>{getLabel(labels, "customerdetails_desc")}</div>
                        </div>
                        <div className={mcClasses("mc-card-body")}>
                            <DetailRows rows={[
                                [getLabel(labels, "email"), customer.email],
                                [getLabel(labels, "phone"), customer.phone],
                                [getLabel(labels, "city"), customer.city],
                                [getLabel(labels, "country"), customer.country],
                                [getLabel(labels, "accountcreated"), customer.accountcreated],
                                [getLabel(labels, "firstorder"), stats.firstorder],
                                [getLabel(labels, "lastorder"), stats.lastorder],
                                [getLabel(labels, "status"), statusBadge],
                            ]} />
                        </div>
                    </div>
                </div>

                <div className="col-xl-6">
                    <div className={mcClasses("mc-card h-100")}>
                        <div className={mcClasses("mc-card-header")}>
                            <h2 className={mcClasses("mc-card-title mb-0")}>{getLabel(labels, "latestbillingdetails")}</h2>
                        </div>
                        <div className={mcClasses("mc-card-body")}>
                            {billing.hasdetails ? (
                                <DetailRows rows={[
                                    [getLabel(labels, "customer"), billing.name],
                                    [getLabel(labels, "email"), billing.email],
                                    [getLabel(labels, "phone"), billing.phone],
                                    [getLabel(labels, "address"), billing.address],
                                    [getLabel(labels, "city"), billing.city],
                                    [getLabel(labels, "state"), billing.state],
                                    [getLabel(labels, "country"), billing.country],
                                    [getLabel(labels, "zipcode"), billing.zipcode],
                                ]} />
                            ) : (
                                <div className={mcClasses("text-muted")}>{getLabel(labels, "nothingtodisplay")}</div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <McTableCard
                title={<h2 className={mcClasses("mc-card-title mb-0")}>{getLabel(labels, "orderhistory")}</h2>}
                actions={(
                    <a className={mcClasses("mc-button btn-mc-secondary")} href={customersUrl}>
                        <i className="bi bi-arrow-left" aria-hidden="true" />
                        {getLabel(labels, "backtocustomers")}
                    </a>
                )}
                footer={(
                    <McTableFooter
                        summary={(
                            <span>
                                {getLabel(labels, "showing", "Showing")} {formatCount(range.from)}-{formatCount(range.to)}
                                {" / "}
                                {formatCount(data.total)}
                            </span>
                        )}
                        pagination={(
                            <McTablePagination
                                previousLabel={getLabel(labels, "previous")}
                                nextLabel={getLabel(labels, "next")}
                                pageLabel={getLabel(labels, "page")}
                                page={data.page + 1}
                                totalPages={data.totalpages}
                                previousDisabled={!canPrev || loading}
                                nextDisabled={!canNext || loading}
                                onPrevious={() => setPage((current) => Math.max(0, current - 1))}
                                onNext={() => setPage((current) => current + 1)}
                            />
                        )}
                    />
                )}
            >
                <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={getLabel(labels, "orderhistory")}>
                            <thead>
                                <tr>
                                    <th scope="col">{getLabel(labels, "ordernumber")}</th>
                                    <th scope="col">{getLabel(labels, "date")}</th>
                                    <th scope="col">{getLabel(labels, "items")}</th>
                                    <th scope="col">{getLabel(labels, "status")}</th>
                                    <th scope="col">{getLabel(labels, "paymentmethod")}</th>
                                    <th scope="col" className="text-end">{getLabel(labels, "total")}</th>
                                    <th scope="col" className="text-end">{getLabel(labels, "actions")}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.length === 0 && (
                                    <tr>
                                        <td colSpan={7}>
                                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                <span className={mcClasses("mc-empty__icon")}>
                                                    <i className="bi bi-receipt" aria-hidden="true" />
                                                </span>
                                                <p className={mcClasses("mc-empty__title")}>{getLabel(labels, "noordersfound")}</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {orders.map((order) => (
                                    <tr key={order.id}>
                                        <td><a className="fw-semibold" href={order.viewurl}>{order.ordernumber}</a></td>
                                        <td className={mcClasses("mc-cell-nowrap")}>{order.displaydate}</td>
                                        <td><OrderItems items={order.items} itemcount={order.itemcount} labels={labels} /></td>
                                        <td>
                                            <McBadge variant={badgeVariant(order.statusclass)} tone="soft" dot>
                                                {order.statuslabel}
                                            </McBadge>
                                        </td>
                                        <td>{displayValue(order.paymentmethod)}</td>
                                        <td className="text-end fw-semibold">{order.displaytotal}</td>
                                        <td className="text-end">
                                            {renderOrderActions(order)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                </table>
            </McTableCard>
        </section>
    );
}

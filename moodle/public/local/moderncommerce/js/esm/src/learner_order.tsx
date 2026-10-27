// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React learner order detail page for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_order
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import type {ReactNode} from "react";
import {badgeClass, callMoodleService, formatCount, Labels} from "./learner_common";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";
import {confirmDialog} from "./modal";

type Order = {
    id: number;
    ordernumber: string;
    date: string;
    status: string;
    statuslabel: string;
    statusclass: string;
    ispaid: boolean;
    ispending: boolean;
    isfailed: boolean;
    isrefunded: boolean;
    subtotal: string;
    hasdiscount: boolean;
    discount: string;
    hastax: boolean;
    tax: string;
    total: string;
    paymentmethod: string;
    transactionid: string;
    couponcode: string;
};

type Item = {
    id: number;
    name: string;
    producttype: string;
    typelabel: string;
    quantity: number;
    quantitylabel: string;
    unitprice: string;
    total: string;
    url: string;
    hasurl: boolean;
    iscourse: boolean;
    isbundle: boolean;
    issubscription: boolean;
};

type Billing = {
    name: string;
    email: string;
    phone: string;
    address: string;
    hasaddress: boolean;
};

type OrderResponse = {
    success: boolean;
    message: string;
    order: Order;
    items: Item[];
    billing: Billing;
    urls: {
        orders: string;
        catalog: string;
        receipt: string;
        invoice: string;
        continuepayment: string;
    };
    sesskey: string;
};

type CancelResponse = {
    success: boolean;
    message?: string;
    error?: string;
};

type LearnerOrderProps = {
    methodName: string;
    orderId: number;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-receipt" aria-hidden="true" /></span>
            <p className={mcClasses("mc-empty__title")}>{labels.loading}</p>
        </div>
    );
}

function KeyValue({
    label,
    value,
    emphasis = false,
    preline = false,
}: {
    label: string;
    value?: string;
    emphasis?: boolean;
    preline?: boolean;
}) {
    const displayValue = (value ?? "").trim();

    if (!displayValue) {
        return null;
    }

    return (
        <div className={mcClasses("mc-detail-list__row", emphasis && "mc-detail-list__row--total")}>
            <dt className={mcClasses("mc-detail-list__label")}>{label}</dt>
            <dd className={mcClasses("mc-detail-list__value", preline && "mc-detail-list__value--preline")}>
                {displayValue}
            </dd>
        </div>
    );
}

function DetailList({children}: {children: ReactNode}) {
    return <dl className={mcClasses("mc-detail-list mb-0")}>{children}</dl>;
}

function BillingBlock({
    billing,
    labels,
}: {
    billing: Billing;
    labels: Labels;
}) {
    return (
        <section className={mcClasses("mc-card h-100")} aria-labelledby="mc-learner-order-billing-title">
            <div className={mcClasses("mc-card-header")}>
                <h2 className={mcClasses("mc-card-title")} id="mc-learner-order-billing-title">
                    {labels.billingdetails}
                </h2>
            </div>
            <div className={mcClasses("mc-card-body")}>
                <DetailList>
                    <KeyValue label={labels.customer} value={billing.name} />
                    <KeyValue label={labels.email} value={billing.email} />
                    <KeyValue label={labels.phone} value={billing.phone} />
                    {billing.hasaddress && (
                        <KeyValue label={labels.address} value={billing.address} preline />
                    )}
                </DetailList>
            </div>
        </section>
    );
}

function TotalsBlock({
    order,
    labels,
}: {
    order: Order;
    labels: Labels;
}) {
    return (
        <section className={mcClasses("mc-card h-100")} aria-labelledby="mc-learner-order-payment-title">
            <div className={mcClasses("mc-card-header")}>
                <h2 className={mcClasses("mc-card-title")} id="mc-learner-order-payment-title">
                    {labels.paymentdetails}
                </h2>
            </div>
            <div className={mcClasses("mc-card-body")}>
                <DetailList>
                    <KeyValue label={labels.subtotal} value={order.subtotal} />
                    {order.hasdiscount && <KeyValue label={labels.discount} value={order.discount} />}
                    {order.hastax && <KeyValue label={labels.tax} value={order.tax} />}
                    <KeyValue label={labels.total} value={order.total} emphasis />
                    <KeyValue label={labels.paymentmethod} value={order.paymentmethod} />
                    <KeyValue label={labels.transactionid} value={order.transactionid} />
                    <KeyValue label={labels.couponcode} value={order.couponcode} />
                </DetailList>
            </div>
        </section>
    );
}

function ReceiptActions({
    order,
    urls,
    labels,
    cancelling,
    onCancel,
}: {
    order: Order;
    urls: OrderResponse["urls"];
    labels: Labels;
    cancelling: boolean;
    onCancel: () => void;
}) {
    return (
        <div className={mcClasses("mc-learner-order-receipt__actions")}>
            {order.ispending && (
                <>
                    <a className={mcClasses("mc-button btn-mc-primary")} href={urls.continuepayment}>
                        <i className="bi bi-credit-card" aria-hidden="true" />
                        {labels.continuepayment}
                    </a>
                    <button
                        type="button"
                        className={mcClasses("mc-button btn-mc-danger")}
                        disabled={cancelling}
                        onClick={onCancel}
                    >
                        <i className="bi bi-x-circle" aria-hidden="true" />
                        {cancelling ? labels.processing : labels.cancelorder}
                    </button>
                </>
            )}
            {order.ispaid && (
                <>
                    <a className={mcClasses("mc-button btn-mc-secondary")} href={urls.receipt}>
                        <i className="bi bi-download" aria-hidden="true" />
                        {labels.downloadreceipt}
                    </a>
                    <a className={mcClasses("mc-button btn-mc-secondary")} href={urls.invoice}>
                        <i className="bi bi-file-earmark-text" aria-hidden="true" />
                        {labels.downloadinvoice}
                    </a>
                </>
            )}
        </div>
    );
}

function ReceiptHeader({
    order,
    urls,
    labels,
    cancelling,
    onCancel,
}: {
    order: Order;
    urls: OrderResponse["urls"];
    labels: Labels;
    cancelling: boolean;
    onCancel: () => void;
}) {
    return (
        <section
            className={mcClasses(
                "mc-learner-order-receipt",
                order.ispending && "mc-learner-order-receipt--pending"
            )}
            aria-labelledby="mc-learner-order-receipt-title"
        >
            <div className={mcClasses("mc-learner-order-receipt__header")}>
                <div className={mcClasses("mc-learner-order-receipt__identity")}>
                    <span className={mcClasses("mc-learner-order-receipt__label")}>{labels.receipt}</span>
                    <h2 id="mc-learner-order-receipt-title">{order.ordernumber}</h2>
                    <div className={mcClasses("mc-learner-order-receipt__meta")}>
                        <span>
                            <i className="bi bi-calendar3" aria-hidden="true" />
                            {labels.orderdate}: {order.date}
                        </span>
                        {order.paymentmethod && (
                            <span>
                                <i className="bi bi-credit-card-2-front" aria-hidden="true" />
                                {order.paymentmethod}
                            </span>
                        )}
                    </div>
                </div>
                <span className={mcClasses(`mc-badge mc-badge--${badgeClass(order.statusclass)}`)}>
                    {order.statuslabel}
                </span>
            </div>
            <div className={mcClasses("mc-learner-order-receipt__summary")}>
                <div className={mcClasses("mc-learner-order-receipt__total")}>
                    <span>{labels.total}</span>
                    <strong>{order.total}</strong>
                </div>
                <ReceiptActions
                    order={order}
                    urls={urls}
                    labels={labels}
                    cancelling={cancelling}
                    onCancel={onCancel}
                />
            </div>
            {order.ispending && (
                <div className={mcClasses("mc-alert mc-alert--warning mc-learner-order-receipt__notice")} role="status">
                    <i className="bi bi-clock-history mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{labels.orderpending}</div>
                </div>
            )}
        </section>
    );
}

function OrderItemName({
    item,
}: {
    item: Item;
}) {
    return item.hasurl ? (
        <a className={mcClasses("mc-learner-order-item-card__title")} href={item.url}>
            {item.name}
        </a>
    ) : (
        <span className={mcClasses("mc-learner-order-item-card__title")}>{item.name}</span>
    );
}

function OrderItemCard({
    item,
    labels,
}: {
    item: Item;
    labels: Labels;
}) {
    return (
        <article className={mcClasses("mc-learner-order-item-card")}>
            <div className={mcClasses("mc-learner-order-item-card__header")}>
                <div>
                    <OrderItemName item={item} />
                    <span className={mcClasses("mc-cell-muted small d-block")}>{item.typelabel}</span>
                </div>
                <strong>{item.total}</strong>
            </div>
            <div className={mcClasses("mc-learner-order-item-card__body")}>
                <div>
                    <span>{labels.quantity}</span>
                    <strong>{item.quantitylabel}</strong>
                </div>
                <div>
                    <span>{labels.price}</span>
                    <strong>{item.unitprice}</strong>
                </div>
            </div>
        </article>
    );
}

function OrderItemsBlock({
    items,
    labels,
}: {
    items: Item[];
    labels: Labels;
}) {
    return (
        <section className={mcClasses("mc-card")} aria-labelledby="mc-learner-order-items-title">
            <div className={mcClasses("mc-card-header d-flex justify-content-between align-items-center gap-2")}>
                <h2 className={mcClasses("mc-card-title")} id="mc-learner-order-items-title">
                    {labels.orderitems}
                </h2>
                <span className={mcClasses("mc-badge mc-badge--neutral")}>{formatCount(items.length)}</span>
            </div>
            {items.length === 0 ? (
                <div className={mcClasses("mc-card-body")}>
                    <div className={mcClasses("mc-empty mc-empty--centered")}>
                        <span className={mcClasses("mc-empty__icon")}><i className="bi bi-receipt" aria-hidden="true" /></span>
                        <p className={mcClasses("mc-empty__title")}>{labels.noorderitems}</p>
                    </div>
                </div>
            ) : (
                <div className={mcClasses("mc-card-body p-0")}>
                    <div className={mcClasses("mc-learner-order-items__cards")}>
                        {items.map((item) => (
                            <OrderItemCard key={item.id} item={item} labels={labels} />
                        ))}
                    </div>
                    <div className={mcClasses("table-responsive mc-learner-order-items__table")}>
                        <table className="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{labels.item}</th>
                                    <th scope="col">{labels.quantity}</th>
                                    <th scope="col" className="text-end">{labels.price}</th>
                                    <th scope="col" className="text-end">{labels.linetotal}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr key={item.id}>
                                        <td>
                                            <OrderItemName item={item} />
                                            <div className={mcClasses("mc-cell-muted small")}>{item.typelabel}</div>
                                        </td>
                                        <td>{item.quantitylabel}</td>
                                        <td className="text-end">{item.unitprice}</td>
                                        <td className="text-end fw-semibold">{item.total}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </section>
    );
}

export default function LearnerOrder({
    methodName,
    orderId,
    labels,
    layout,
}: LearnerOrderProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<OrderResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [notice, setNotice] = useState<{type: "success" | "warning"; message: string} | null>(null);
    const [refreshKey, setRefreshKey] = useState(0);
    const [cancelling, setCancelling] = useState(false);

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");

        callMoodleService<OrderResponse>(methodName, {id: orderId})
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
    }, [methodName, orderId, refreshKey]);

    const cancelOrder = async() => {
        if (!data?.order.ispending || cancelling) {
            return;
        }

        if (!await confirmDialog({
            title: labels.cancelorder,
            message: labels.cancelorderconfirmmessage,
            confirmLabel: labels.cancelorder,
            cancelLabel: labels.cancel,
            danger: true,
        })) {
            return;
        }

        setCancelling(true);
        setNotice(null);

        try {
            const result = await callMoodleService<CancelResponse>(
                "local_moderncommerce_cancel_learner_order",
                {id: orderId}
            );

            if (!result.success) {
                throw new Error(result.error || result.message || labels.cancelordererror);
            }

            setNotice({
                type: "success",
                message: result.message || labels.ordercancelledsuccess,
            });
            setRefreshKey((current) => current + 1);
        } catch (caught) {
            setNotice({
                type: "warning",
                message: caught instanceof Error ? caught.message : labels.cancelordererror,
            });
        } finally {
            setCancelling(false);
        }
    };

    const renderLayout = (children: ReactNode, subtitle?: string, actions?: ReactNode) => (
        <ModernLearnerLayout
            activeNav="orders"
            title={labels.orderdetails}
            subtitle={subtitle}
            labels={labels}
            layout={layout}
            actions={actions}
        >
            {children}
        </ModernLearnerLayout>
    );

    if (loading) {
        return renderLayout(<LoadingState labels={labels} />);
    }

    if (error || !data || !data.success) {
        return renderLayout(
            <div className={mcClasses("mc-alert mc-alert--warning")} role="alert">
                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                <div className={mcClasses("mc-alert__body")}>
                    <div className="fw-semibold mb-2">{error || data?.message}</div>
                    <a className={mcClasses("mc-button btn-mc-secondary")} href={data?.urls.orders ?? "#"}>
                        <i className="bi bi-arrow-left me-1" aria-hidden="true" />
                        {labels.backtoorders}
                    </a>
                </div>
            </div>
        );
    }

    const {order, items, billing, urls} = data;
    const pageActions = (
        <a className={mcClasses("mc-button btn-mc-secondary")} href={urls.orders}>
            <i className="bi bi-arrow-left" aria-hidden="true" />
            {labels.backtoorders}
        </a>
    );

    return renderLayout(
        <div className={mcClasses("mc-learner-order")}>
            {notice && (
                <div
                    className={mcClasses(`mc-alert mc-alert--${notice.type}`)}
                    role={notice.type === "success" ? "status" : "alert"}
                >
                    <i
                        className={mcClasses(
                            notice.type === "success" ? "bi bi-check-circle mc-alert__icon" :
                                "bi bi-exclamation-triangle mc-alert__icon"
                        )}
                        aria-hidden="true"
                    />
                    <div className={mcClasses("mc-alert__body")}>{notice.message}</div>
                </div>
            )}

            <ReceiptHeader
                order={order}
                urls={urls}
                labels={labels}
                cancelling={cancelling}
                onCancel={() => void cancelOrder()}
            />

            <OrderItemsBlock items={items} labels={labels} />

            <div className={mcClasses("mc-learner-order__details-grid")}>
                <TotalsBlock order={order} labels={labels} />
                <BillingBlock billing={billing} labels={labels} />
            </div>
        </div>,
        `${order.ordernumber} - ${order.date}`,
        pageActions
    );
}

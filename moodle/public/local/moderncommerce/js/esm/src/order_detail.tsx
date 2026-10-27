// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin order detail for Modern Commerce.
 *
 * @module     local_moderncommerce/order_detail
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {confirmDialog} from "./modal";
import {FieldError} from "./form";
import {focusFirstError, fmt, min, minLength, maxLength, number, required, runRules} from "./validation";
import type {FieldErrorMap} from "./validation";
import {McTableFrame} from "./table_components";

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

type Labels = Record<string, string>;

type Billing = {
    name: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    state: string;
    country: string;
    zipcode: string;
    hasdetails: boolean;
};

type OrderItem = {
    name: string;
    typelabel: string;
    sku: string;
    url: string;
    hasurl: boolean;
    unitprice: string;
    quantity: number;
    total: string;
};

type Transaction = {
    reference: string;
    gateway: string;
    amount: string;
    statuslabel: string;
    statusclass: string;
    date: string;
};

type Refund = {
    amount: string;
    reason: string;
    statuslabel: string;
    statusclass: string;
    date: string;
};

type OrderDetail = {
    id: number;
    ordernumber: string;
    ordertype: string;
    status: string;
    statuslabel: string;
    statusclass: string;
    displaydate: string;
    timepaid: string;
    timecompleted: string;
    timerefunded: string;
    customerid: number;
    customername: string;
    customeremail: string;
    customerurl: string;
    paymentmethod: string;
    couponcode: string;
    billing: Billing;
    subtotal: string;
    discount: string;
    tax: string;
    fees: string;
    total: string;
    refundedtotal: string;
    rawrefundedtotal: number;
    rawtotal: number;
    items: OrderItem[];
    transactions: Transaction[];
    refunds: Refund[];
    customernotes: string;
    adminnotes: string;
    canmanage: boolean;
    canrefund: boolean;
};

type UpdateStatusResponse = {
    success: boolean;
    status: string;
    statuslabel: string;
    statusclass: string;
    message: string;
};

type RefundResponse = {
    success: boolean;
    refundid: number;
    message: string;
};

type RefundForm = {
    open: boolean;
    type: "full" | "partial";
    amount: string;
    reason: string;
    unenrol: boolean;
};

type OrderDetailProps = {
    orderId: number;
    getMethodName: string;
    updateStatusMethodName: string;
    createRefundMethodName: string;
    ordersUrl: string;
    statusOptions: SelectOption[];
    labels: Labels;
};

const BADGE_VARIANTS: McBadgeVariant[] = ["primary", "secondary", "accent", "success", "warning", "danger", "info", "neutral"];
const badgeVariant = (variant?: string): McBadgeVariant => (
    BADGE_VARIANTS.includes(variant as McBadgeVariant) ? variant as McBadgeVariant : "neutral"
);

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

const emptyRefundForm = (): RefundForm => ({
    open: false,
    type: "full",
    amount: "",
    reason: "",
    unenrol: false,
});

export default function OrderDetail({
    orderId,
    getMethodName,
    updateStatusMethodName,
    createRefundMethodName,
    ordersUrl,
    statusOptions,
    labels,
}: OrderDetailProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<OrderDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    const [statusDraft, setStatusDraft] = useState("");
    const [refund, setRefund] = useState<RefundForm>(emptyRefundForm);
    const [refundFieldErrors, setRefundFieldErrors] = useState<FieldErrorMap>({});
    const [reloadToken, setReloadToken] = useState(0);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<OrderDetail>(getMethodName, {id: orderId})
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                    setStatusDraft(result.status);
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
    }, [getMethodName, orderId, reloadToken]);

    const reload = () => setReloadToken((current) => current + 1);

    const submitStatus = async() => {
        if (!data || statusDraft === data.status) {
            return;
        }

        setBusy(true);
        setError("");

        try {
            const result = await callMoodleService<UpdateStatusResponse>(updateStatusMethodName, {
                orderid: orderId,
                status: statusDraft,
            });

            if (!result.success) {
                setError(result.message);
                return;
            }

            toast.success(result.message);
            reload();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setBusy(false);
        }
    };

    const submitRefund = async() => {
        if (!data) {
            return;
        }

        const t = (key: string) => labels[key] ?? key;
        const remaining = Math.max(0, (data.rawtotal || 0) - (data.rawrefundedtotal || 0));
        const found = runRules(refund, {
            amount: refund.type === "partial"
                ? [required(t("error:required")), number(t("error:invalidnumber")), min(0.01, t("error:minvalue"))]
                : [],
            reason: [
                required(t("error:required")),
                minLength(3, t("error:minlength")),
                maxLength(500, t("error:maxlength")),
            ],
        });
        // Refunding more than is left on the order would credit money that was
        // never taken. The server rejects it too; this explains it in place.
        if (!found.amount && refund.type === "partial" && Number(refund.amount) > remaining) {
            found.amount = fmt(t("error:refundabovetotal"), remaining.toFixed(2));
        }

        setRefundFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setError("");
            focusFirstError(found, ["amount", "reason"]);
            return;
        }

        if (!await confirmDialog({message: labels.confirmrefund, danger: true})) {
            return;
        }

        setBusy(true);
        setError("");

        try {
            const result = await callMoodleService<RefundResponse>(createRefundMethodName, {
                orderid: orderId,
                refundtype: refund.type,
                amount: refund.type === "partial" ? Number(refund.amount) || 0 : 0,
                reason: refund.reason,
                unenrol: refund.unenrol,
            });

            if (!result.success) {
                setError(result.message);
                return;
            }

            toast.success(result.message);
            setRefund(emptyRefundForm());
            reload();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setBusy(false);
        }
    };

    if (loading && !data) {
        return <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>;
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

    const billing = data.billing;

    return (
        <section className={mcClasses("mc-order-detail")} aria-label={labels.orderdetails}>

            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            <div className={mcClasses("mc-card mb-3")}>
                <div className={mcClasses("mc-card-body d-flex flex-wrap justify-content-between align-items-center gap-3")}>
                    <div>
                        <div className={mcClasses("mc-card-title mb-1")}>{data.ordernumber}</div>
                        <div className={mcClasses("mc-cell-muted")}>{data.displaydate}</div>
                    </div>
                    <div className="d-flex align-items-center gap-2 flex-wrap">
                        <McBadge variant={badgeVariant(data.statusclass)} tone="soft" dot>{data.statuslabel}</McBadge>
                        {data.canmanage && (
                            <>
                                <select
                                    aria-label={labels.changestatus}
                                    className={mcClasses("mc-select mc-status-select")}
                                    disabled={busy}
                                    onChange={(event) => setStatusDraft(event.target.value)}
                                    value={statusDraft}
                                >
                                    {statusOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                                <McButton
                                    className={mcClasses("btn-mc-primary")}
                                    disabled={statusDraft === data.status}
                                    loading={busy}
                                    loadingLabel={labels.processing || "Updating..."}
                                    onClick={submitStatus}
                                    type="button"
                                >
                                    {labels.updatestatus}
                                </McButton>
                            </>
                        )}
                    </div>
                </div>
            </div>

            <div className="row g-3">
                <div className="col-12 col-lg-8">
                    <div className={mcClasses("mc-card mb-3")}>
                        <div className={mcClasses("mc-card-header")}>
                            <span className={mcClasses("mc-card-title")}>{labels.items}</span>
                        </div>
                        <McTableFrame>
                            <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.items}>
                                <thead>
                                    <tr>
                                        <th scope="col">{labels.name}</th>
                                        <th scope="col">{labels.type}</th>
                                        <th scope="col" className="text-end">{labels.unitprice}</th>
                                        <th scope="col" className="text-end">{labels.quantity}</th>
                                        <th scope="col" className="text-end">{labels.total}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.items.length === 0 && (
                                        <tr>
                                            <td colSpan={5}>
                                                <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                    <p className={mcClasses("mc-empty__title")}>{labels.noitems}</p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {data.items.map((item, index) => (
                                        <tr key={index}>
                                            <td>
                                                {item.hasurl ? (
                                                    <a className="fw-semibold" href={item.url}>{item.name}</a>
                                                ) : (
                                                    <span className="fw-semibold">{item.name}</span>
                                                )}
                                                {item.sku && <div className={mcClasses("mc-cell-muted")}>{labels.sku}: {item.sku}</div>}
                                            </td>
                                            <td><McBadge variant="neutral" tone="soft">{item.typelabel}</McBadge></td>
                                            <td className="text-end">{item.unitprice}</td>
                                            <td className="text-end">{item.quantity}</td>
                                            <td className="text-end fw-semibold">{item.total}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </McTableFrame>
                        <div className={mcClasses("mc-card-body")}>
                            <dl className={mcClasses("mc-order-summary__body mb-0")}>
                                <div className="d-flex justify-content-between py-1">
                                    <dt className="fw-normal">{labels.subtotal}</dt>
                                    <dd className="mb-0">{data.subtotal}</dd>
                                </div>
                                <div className="d-flex justify-content-between py-1">
                                    <dt className="fw-normal">{labels.discount}</dt>
                                    <dd className="mb-0">{data.discount}</dd>
                                </div>
                                <div className="d-flex justify-content-between py-1">
                                    <dt className="fw-normal">{labels.tax}</dt>
                                    <dd className="mb-0">{data.tax}</dd>
                                </div>
                                <div className="d-flex justify-content-between py-1">
                                    <dt className="fw-normal">{labels.fees}</dt>
                                    <dd className="mb-0">{data.fees}</dd>
                                </div>
                                <div className="d-flex justify-content-between py-2 border-top fw-semibold">
                                    <dt>{labels.total}</dt>
                                    <dd className="mb-0">{data.total}</dd>
                                </div>
                                {data.rawrefundedtotal > 0 && (
                                    <div className="d-flex justify-content-between py-1 text-danger">
                                        <dt className="fw-normal">{labels.refundedtotal}</dt>
                                        <dd className="mb-0">-{data.refundedtotal}</dd>
                                    </div>
                                )}
                                {data.couponcode && (
                                    <div className="d-flex justify-content-between py-1">
                                        <dt className="fw-normal">{labels.coupon}</dt>
                                        <dd className="mb-0">{data.couponcode}</dd>
                                    </div>
                                )}
                            </dl>
                        </div>
                    </div>

                    <div className={mcClasses("mc-card mb-3")}>
                        <div className={mcClasses("mc-card-header")}>
                            <span className={mcClasses("mc-card-title")}>{labels.transactions}</span>
                        </div>
                        <McTableFrame>
                            <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.transactions}>
                                <thead>
                                    <tr>
                                        <th scope="col">{labels.reference}</th>
                                        <th scope="col">{labels.gateway}</th>
                                        <th scope="col" className="text-end">{labels.amount}</th>
                                        <th scope="col">{labels.status}</th>
                                        <th scope="col">{labels.date}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.transactions.length === 0 && (
                                        <tr>
                                            <td colSpan={5}>
                                                <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                    <p className={mcClasses("mc-empty__title")}>{labels.notransactions}</p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {data.transactions.map((txn, index) => (
                                        <tr key={index}>
                                            <td className={mcClasses("mc-cell-mono")}>{txn.reference || "-"}</td>
                                            <td>{txn.gateway}</td>
                                            <td className="text-end">{txn.amount}</td>
                                            <td>
                                                <McBadge variant={badgeVariant(txn.statusclass)} tone="soft" dot>
                                                    {txn.statuslabel}
                                                </McBadge>
                                            </td>
                                            <td className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{txn.date}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </McTableFrame>
                    </div>

                    <div className={mcClasses("mc-card mb-3")}>
                        <div className={mcClasses("mc-card-header d-flex justify-content-between align-items-center")}>
                            <span className={mcClasses("mc-card-title")}>{labels.refunds}</span>
                            {data.canrefund && (
                                <button
                                    className={mcClasses("mc-button btn-mc-danger")}
                                    disabled={busy}
                                    onClick={() => setRefund((current) => ({...emptyRefundForm(), open: !current.open}))}
                                    type="button"
                                >
                                    {labels.refund}
                                </button>
                            )}
                        </div>

                        {refund.open && data.canrefund && (
                            <div className={mcClasses("mc-card-body border-bottom")}>
                                <div className={mcClasses("mc-product-form__grid")}>
                                    <label>
                                        <span>{labels.refundtype}</span>
                                        <select
                                            className={mcClasses("mc-select")}
                                            onChange={(event) => setRefund((current) => ({
                                                ...current,
                                                type: event.target.value === "partial" ? "partial" : "full",
                                            }))}
                                            value={refund.type}
                                        >
                                            <option value="full">{labels.fullrefund}</option>
                                            <option value="partial">{labels.partialrefund}</option>
                                        </select>
                                    </label>
                                    {refund.type === "partial" && (
                                        <label htmlFor="mc-refund-amount">
                                            <span>
                                                {labels.refundamount}
                                                <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                            </span>
                                            <input
                                                aria-describedby={refundFieldErrors.amount
                                                    ? "mc-refund-amount-error" : undefined}
                                                aria-invalid={refundFieldErrors.amount ? true : undefined}
                                                aria-required="true"
                                                className={mcClasses("mc-form-control",
                                                    refundFieldErrors.amount ? "mc-field-invalid" : "")}
                                                id="mc-refund-amount"
                                                min="0"
                                                name="amount"
                                                onChange={(event) => setRefund((current) => ({...current, amount: event.target.value}))}
                                                step="0.01"
                                                type="number"
                                                value={refund.amount}
                                            />
                                            <FieldError
                                                id="mc-refund-amount-error"
                                                message={refundFieldErrors.amount}
                                            />
                                        </label>
                                    )}
                                    <label className={mcClasses("mc-product-form__wide")} htmlFor="mc-refund-reason">
                                        <span>
                                            {labels.refundreason}
                                            <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                        </span>
                                        <input
                                            aria-describedby={refundFieldErrors.reason
                                                ? "mc-refund-reason-error" : undefined}
                                            aria-invalid={refundFieldErrors.reason ? true : undefined}
                                            aria-required="true"
                                            className={mcClasses("mc-form-control",
                                                refundFieldErrors.reason ? "mc-field-invalid" : "")}
                                            id="mc-refund-reason"
                                            maxLength={500}
                                            name="reason"
                                            onChange={(event) => setRefund((current) => ({...current, reason: event.target.value}))}
                                            type="text"
                                            value={refund.reason}
                                        />
                                        <FieldError id="mc-refund-reason-error" message={refundFieldErrors.reason} />
                                    </label>
                                </div>
                                <div className={mcClasses("mc-product-form__checks")}>
                                    <label>
                                        <input
                                            checked={refund.unenrol}
                                            onChange={(event) => setRefund((current) => ({...current, unenrol: event.target.checked}))}
                                            type="checkbox"
                                        />
                                        <span>{labels.unenrolonrefund}</span>
                                    </label>
                                </div>
                                <McButton
                                    className={mcClasses("btn-mc-danger")}
                                    loading={busy}
                                    loadingLabel={labels.processing || "Processing..."}
                                    onClick={submitRefund}
                                    type="button"
                                >
                                    {labels.processrefund}
                                </McButton>
                            </div>
                        )}

                        <McTableFrame>
                            <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.refunds}>
                                <thead>
                                    <tr>
                                        <th scope="col" className="text-end">{labels.amount}</th>
                                        <th scope="col">{labels.refundreason}</th>
                                        <th scope="col">{labels.status}</th>
                                        <th scope="col">{labels.date}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.refunds.length === 0 && (
                                        <tr>
                                            <td colSpan={4}>
                                                <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                    <p className={mcClasses("mc-empty__title")}>{labels.norefunds}</p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    {data.refunds.map((row, index) => (
                                        <tr key={index}>
                                            <td className="text-end fw-semibold">{row.amount}</td>
                                            <td>{row.reason}</td>
                                            <td>
                                                <McBadge variant={badgeVariant(row.statusclass)} tone="soft" dot>
                                                    {row.statuslabel}
                                                </McBadge>
                                            </td>
                                            <td className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{row.date}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </McTableFrame>
                    </div>
                </div>

                <div className="col-12 col-lg-4">
                    <div className={mcClasses("mc-card mb-3")}>
                        <div className={mcClasses("mc-card-header")}>
                            <span className={mcClasses("mc-card-title")}>{labels.customer}</span>
                        </div>
                        <div className={mcClasses("mc-card-body")}>
                            <div className="fw-semibold">
                                {data.customerid > 0 ? (
                                    <a href={data.customerurl}>{data.customername}</a>
                                ) : data.customername}
                            </div>
                            {data.customeremail && <div className={mcClasses("mc-cell-muted")}>{data.customeremail}</div>}
                            <dl className="mt-2 mb-0">
                                <div className="d-flex justify-content-between py-1">
                                    <dt className={mcClasses("fw-normal mc-cell-muted")}>{labels.paymentmethod}</dt>
                                    <dd className="mb-0">{data.paymentmethod}</dd>
                                </div>
                                {data.timepaid && (
                                    <div className="d-flex justify-content-between py-1">
                                        <dt className={mcClasses("fw-normal mc-cell-muted")}>{labels.paidon}</dt>
                                        <dd className="mb-0">{data.timepaid}</dd>
                                    </div>
                                )}
                                {data.timecompleted && (
                                    <div className="d-flex justify-content-between py-1">
                                        <dt className={mcClasses("fw-normal mc-cell-muted")}>{labels.completedon}</dt>
                                        <dd className="mb-0">{data.timecompleted}</dd>
                                    </div>
                                )}
                                {data.timerefunded && (
                                    <div className="d-flex justify-content-between py-1">
                                        <dt className={mcClasses("fw-normal mc-cell-muted")}>{labels.refundedon}</dt>
                                        <dd className="mb-0">{data.timerefunded}</dd>
                                    </div>
                                )}
                            </dl>
                        </div>
                    </div>

                    {billing.hasdetails && (
                        <div className={mcClasses("mc-card mb-3")}>
                            <div className={mcClasses("mc-card-header")}>
                                <span className={mcClasses("mc-card-title")}>{labels.billingdetails}</span>
                            </div>
                            <div className={mcClasses("mc-card-body")}>
                                {billing.name && <div className="fw-semibold">{billing.name}</div>}
                                {billing.phone && <div className={mcClasses("mc-cell-muted")}>{labels.phone}: {billing.phone}</div>}
                                {billing.address && <div className={mcClasses("mc-cell-muted")}>{billing.address}</div>}
                                {(billing.city || billing.state || billing.zipcode) && (
                                    <div className={mcClasses("mc-cell-muted")}>
                                        {[billing.city, billing.state, billing.zipcode].filter(Boolean).join(", ")}
                                    </div>
                                )}
                                {billing.country && <div className={mcClasses("mc-cell-muted")}>{billing.country}</div>}
                            </div>
                        </div>
                    )}

                    {(data.customernotes || data.adminnotes) && (
                        <div className={mcClasses("mc-card mb-3")}>
                            <div className={mcClasses("mc-card-header")}>
                                <span className={mcClasses("mc-card-title")}>{labels.notes}</span>
                            </div>
                            <div className={mcClasses("mc-card-body")}>
                                {data.customernotes && (
                                    <div className="mb-2">
                                        <div className={mcClasses("mc-cell-muted")}>{labels.customernotes}</div>
                                        <div className={mcClasses("mc-note-text")}>{data.customernotes}</div>
                                    </div>
                                )}
                                {data.adminnotes && (
                                    <div>
                                        <div className={mcClasses("mc-cell-muted")}>{labels.adminnotes}</div>
                                        <div className={mcClasses("mc-note-text")}>{data.adminnotes}</div>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    <a className={mcClasses("mc-button btn-mc-secondary w-100")} href={ordersUrl}>
                        {labels.backtoorders}
                    </a>
                </div>
            </div>
        </section>
    );
}

// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React buyer checkout page for Modern Commerce.
 *
 * @module     local_moderncommerce/checkout
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Dispatch, FormEvent, useEffect, useState} from "react";
import type {ReactNode} from "react";
import {callMoodleService, Labels} from "./learner_common";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";
import {FieldError} from "./form";
import {email as emailRule, focusFirstError, required, runRules} from "./validation";
import type {FieldErrorMap, RuleMap} from "./validation";

type CheckoutItem = {
    id: number;
    productid: number;
    courseid: number;
    itemtype: string;
    typelabel: string;
    name: string;
    shortname: string;
    imageurl: string;
    hasimage: boolean;
    quantity: number;
    quantitylabel: string;
    price: string;
    linetotal: string;
    detailsurl: string;
    iscourse: boolean;
    isbundle: boolean;
    isprogram: boolean;
    issubscription: boolean;
};

type Totals = {
    subtotal: string;
    subtotalraw: number;
    hasdiscount: boolean;
    discount: string;
    discountraw: number;
    hastax: boolean;
    tax: string;
    taxraw: number;
    total: string;
    totalraw: number;
};

type CheckoutUser = {
    firstname: string;
    lastname: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    state: string;
    country: string;
    zipcode: string;
};

type BillingField = {
    field: string;
    label: string;
    required: boolean;
    type: string;
};

type Country = {
    code: string;
    name: string;
    selected: boolean;
};

type PaymentMethod = {
    id: string;
    name: string;
    description: string;
    icon: string;
    methodtype: string;
};

const paymentIconClassName = (value: string): string => {
    const clean = value.trim().replace(/^bi\s+/, "").replace(/^bi-/, "").replace(/^fa\s+/, "");
    const legacyMap: Record<string, string> = {
        "fa-credit-card": "credit-card-2-front",
        "fa-cc-stripe": "stripe",
        "fa-cc-paypal": "paypal",
        "fa-university": "bank",
        "fa-key": "key",
    };
    const icon = (legacyMap[clean] ?? clean) || "credit-card-2-front";

    return `bi bi-${icon}`;
};

type CheckoutCoupon = {
    hascoupon: boolean;
    code: string;
    discount: string;
    editable: boolean;
};

export type PublicLink = {
    key: string;
    label: string;
    url: string;
};

type CheckoutResponse = {
    success: boolean;
    message: string;
    cancheckout: boolean;
    iscontinuepayment: boolean;
    existingorderid: number;
    existingordernumber: string;
    user: CheckoutUser;
    billingfields: BillingField[];
    countries: Country[];
    paymentmethods: PaymentMethod[];
    haspaymentmethods: boolean;
    nopaymentmethods: boolean;
    items: CheckoutItem[];
    itemcount: number;
    totals: Totals;
    coupon: CheckoutCoupon;
    urls: {
        cart: string;
        catalog: string;
        checkout: string;
        orders: string;
    };
    sesskey: string;
};

type PlaceOrderResponse = {
    success: boolean;
    message: string;
    orderid: number;
    ordernumber: string;
    redirecturl: string;
};

type CheckoutProps = {
    startMethodName: string;
    placeOrderMethodName: string;
    cartUpdateMethodName: string;
    orderId: number;
    courseId: number;
    bundleId: number;
    labels: Labels;
    publicLinks?: PublicLink[];
    layout?: LearnerLayoutContext;
};

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}>
                <i className="bi bi-credit-card" aria-hidden="true" />
            </span>
            <p className={mcClasses("mc-empty__title")}>{labels.loading}</p>
        </div>
    );
}

function EmptyCheckout({
    data,
    labels,
}: {
    data: CheckoutResponse | null;
    labels: Labels;
}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}>
                <i className="bi bi-cart" aria-hidden="true" />
            </span>
            <p className={mcClasses("mc-empty__title")}>{data?.message || labels.emptycart}</p>
            <p className={mcClasses("mc-empty__desc")}>{labels.emptycartmessage}</p>
            <a href={data?.urls.catalog ?? "#"} className={mcClasses("mc-button btn-mc-primary")}>
                {labels.browsecatalog}
            </a>
        </div>
    );
}

function PaymentMethods({
    methods,
    selected,
    labels,
    onSelect,
}: {
    methods: PaymentMethod[];
    selected: string;
    labels: Labels;
    onSelect: Dispatch<string>;
}) {
    if (methods.length === 0) {
        return (
            <div className={mcClasses("mc-alert mc-alert--danger mb-0")} role="alert">
                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                <div className={mcClasses("mc-alert__body")}>{labels.nopaymentmethodsenabled}</div>
            </div>
        );
    }

    return (
        <div role="radiogroup" aria-label={labels.paymentmethod}>
            {methods.map((method) => (
                <label
                    className={mcClasses(`mc-payment-option ${
                            selected === method.id ? "mc-payment-option--selected" : ""
                        }`)}
                    htmlFor={`payment-${method.id}`}
                    key={method.id}
                >
                    <input
                        className="visually-hidden"
                        type="radio"
                        name="paymentmethod"
                        id={`payment-${method.id}`}
                        value={method.id}
                        checked={selected === method.id}
                        onChange={() => onSelect(method.id)}
                    />
                    <div className={mcClasses("mc-payment-option__icon")} aria-hidden="true">
                        <i className={paymentIconClassName(method.icon)} />
                    </div>
                    <span>
                        <span className={mcClasses("mc-payment-option__name")}>{method.name}</span>
                        <span className={mcClasses("mc-payment-option__desc")}>{method.description}</span>
                    </span>
                </label>
            ))}
        </div>
    );
}

function BillingFields({
    fields,
    countries,
    billing,
    required,
    labels,
    onChange,
    errors = {},
}: {
    fields: BillingField[];
    countries: Country[];
    billing: CheckoutUser;
    required: boolean;
    labels: Labels;
    onChange: CallableFunction;
    errors?: FieldErrorMap;
}) {
    if (!required || fields.length === 0) {
        return null;
    }

    return (
        <div className={mcClasses("mc-form-section mc-billing-card mb-3")}>
            <div className={mcClasses("mc-form-section__header")}>
                <h2 className={mcClasses("mc-form-section__title")}>
                    <i className="bi bi-person me-2" aria-hidden="true" />
                    {labels.billinginfo}
                </h2>
            </div>
            <div className={mcClasses("mc-form-section__body")}>
                <div className="row g-3">
                    {fields.map((field) => (
                        <div className={field.field === "address" ? "col-12" : "col-md-6"} key={field.field}>
                            <label htmlFor={field.field} className={mcClasses("mc-field-label")}>
                                {field.label}
                                {field.required && <span className={mcClasses("mc-required")} aria-hidden="true">*</span>}
                            </label>
                            {field.field === "country" ? (
                                <select
                                    aria-describedby={errors[field.field] ? `${field.field}-error` : undefined}
                                    aria-invalid={errors[field.field] ? true : undefined}
                                    aria-required={field.required ? true : undefined}
                                    className={mcClasses("mc-select", errors[field.field] ? "mc-field-invalid" : "")}
                                    id={field.field}
                                    name={field.field}
                                    value={billing.country}
                                    required={field.required}
                                    onChange={(event) => onChange("country", event.currentTarget.value)}
                                >
                                    {countries.map((country) => (
                                        <option value={country.code} key={country.code}>
                                            {country.name}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <input
                                    aria-describedby={errors[field.field] ? `${field.field}-error` : undefined}
                                    aria-invalid={errors[field.field] ? true : undefined}
                                    aria-required={field.required ? true : undefined}
                                    type={field.type}
                                    className={mcClasses("mc-form-control",
                                        errors[field.field] ? "mc-field-invalid" : "")}
                                    id={field.field}
                                    name={field.field}
                                    value={String(billing[field.field as keyof CheckoutUser] ?? "")}
                                    required={field.required}
                                    onChange={(event) => (
                                        onChange(
                                            field.field as keyof CheckoutUser,
                                            event.currentTarget.value
                                        )
                                    )}
                                />
                            )}
                            <FieldError id={`${field.field}-error`} message={errors[field.field]} />
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function Summary({
    data,
    labels,
    couponInput,
    busy,
    onCouponInput,
    onApplyCoupon,
    onRemoveCoupon,
}: {
    data: CheckoutResponse;
    labels: Labels;
    couponInput: string;
    busy: boolean;
    onCouponInput: Dispatch<string>;
    onApplyCoupon: () => void;
    onRemoveCoupon: () => void;
}) {
    return (
        <div className={mcClasses("mc-order-summary")}>
            <div className={mcClasses("mc-order-summary__header")}>{labels.ordersummary}</div>
            <div className={mcClasses("mc-order-summary__body")}>
                {data.items.map((item) => (
                    <div className={mcClasses("mc-order-row")} key={item.id}>
                        <span className={mcClasses("mc-flex-truncate")}>
                            <span className={mcClasses("mc-badge mc-badge--neutral me-1")}>
                                {item.typelabel}
                            </span>
                            {item.name}
                        </span>
                        <span className={mcClasses("mc-order-row__value")}>{item.linetotal}</span>
                    </div>
                ))}

                <hr className="my-2" />

                <div className={mcClasses("mc-order-row")}>
                    <span>{labels.subtotal}</span>
                    <span className={mcClasses("mc-order-row__value")}>{data.totals.subtotal}</span>
                </div>
                {data.totals.hasdiscount && (
                    <div className={mcClasses("mc-order-row mc-order-row--discount")}>
                        <span>{labels.discount}</span>
                        <span className={mcClasses("mc-order-row__value")}>-{data.totals.discount}</span>
                    </div>
                )}
                {data.totals.hastax && (
                    <div className={mcClasses("mc-order-row")}>
                        <span>{labels.tax}</span>
                        <span className={mcClasses("mc-order-row__value")}>{data.totals.tax}</span>
                    </div>
                )}
                <div className={mcClasses("mc-order-row mc-order-row--total")}>
                    <span>{labels.carttotal}</span>
                    <span>{data.totals.total}</span>
                </div>

                {data.coupon.editable && (
                    <div className={mcClasses("mc-coupon-section mt-3")}>
                        <label htmlFor="couponcode-input" className={mcClasses("mc-filter-label")}>
                            {labels.couponcode}
                        </label>
                        {data.coupon.hascoupon ? (
                            <div className="d-flex gap-2">
                                <input
                                    type="text"
                                    className={mcClasses("mc-form-control")}
                                    id="couponcode-input"
                                    value={data.coupon.code}
                                    disabled
                                    readOnly
                                />
                                <button
                                    className={mcClasses("mc-button mc-btn-soft mc-btn-soft--danger")}
                                    type="button"
                                    disabled={busy}
                                    onClick={onRemoveCoupon}
                                >
                                    {labels.removecoupon}
                                </button>
                            </div>
                        ) : (
                            <div className="d-flex gap-2">
                                <input
                                    type="text"
                                    className={mcClasses("mc-form-control")}
                                    id="couponcode-input"
                                    value={couponInput}
                                    placeholder={labels.entercoupon}
                                    onChange={(event) => onCouponInput(event.currentTarget.value)}
                                />
                                <McButton
                                    className={mcClasses("mc-btn-soft")}
                                    type="button"
                                    loading={busy}
                                    loadingLabel={labels.processing || "Applying..."}
                                    onClick={onApplyCoupon}
                                >
                                    {labels.applycoupon}
                                </McButton>
                            </div>
                        )}
                    </div>
                )}
            </div>
            <div className={mcClasses("mc-order-summary__footer")}>
                <div className={mcClasses("mc-trust-note justify-content-center")}>
                    <i className="bi bi-shield-check" aria-hidden="true" />
                    {labels.securecheckout}
                </div>
            </div>
        </div>
    );
}

export default function Checkout({
    startMethodName,
    placeOrderMethodName,
    cartUpdateMethodName,
    orderId,
    courseId,
    bundleId,
    labels,
    publicLinks = [],
    layout,
}: CheckoutProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<CheckoutResponse | null>(null);
    const [billing, setBilling] = useState<CheckoutUser | null>(null);
    const [selectedPayment, setSelectedPayment] = useState("");
    const [couponInput, setCouponInput] = useState("");
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<FieldErrorMap>({});
    const [couponBusy, setCouponBusy] = useState(false);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");

    const loadCheckout = async() => {
        const result = await callMoodleService<CheckoutResponse>(startMethodName, {
            orderid: orderId,
            courseid: courseId,
            bundleid: bundleId,
        });

        setData(result);
        setBilling(result.user);
        setCouponInput(result.coupon.code);
        setMessage(result.message);

        if (!selectedPayment && result.paymentmethods.length > 0) {
            setSelectedPayment(result.paymentmethods[0].id);
        }

        return result;
    };

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");

        loadCheckout()
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
    }, [startMethodName, orderId, courseId, bundleId]);

    const handleBillingChange = (field: keyof CheckoutUser, value: string) => {
        setBilling((current) => {
            if (!current) {
                return current;
            }

            return {...current, [field]: value};
        });
    };

    const handleCouponAction = async(action: "applycoupon" | "removecoupon") => {
        setCouponBusy(true);
        setError("");
        setMessage("");

        try {
            await callMoodleService(cartUpdateMethodName, {
                action,
                couponcode: couponInput,
            });
            const result = await loadCheckout();
            setMessage(result.message);
        } catch (caught) {
            setError((caught as Error).message);
        } finally {
            setCouponBusy(false);
        }
    };

    const handleSubmit = async(event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!data || !billing || !selectedPayment) {
            setError(labels.nopaymentmethodsenabled);
            return;
        }

        // The server decides which billing fields apply, so the rules are built
        // from that same list rather than hard-coded here.
        const t = (key: string) => labels[key] ?? key;
        const rules: RuleMap<Record<string, unknown>> = {};
        if (!["manual", "enrollkey"].includes(selectedPayment)) {
            (data.billingfields || []).forEach((field) => {
                const list = [];
                if (field.required) {
                    list.push(required(t("error:required")));
                }
                if (field.type === "email" || field.field === "email") {
                    list.push(emailRule(t("error:invalidemail")));
                }
                if (list.length) {
                    rules[field.field] = list;
                }
            });
        }
        const found = runRules(billing as unknown as Record<string, unknown>, rules);
        setFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setError("");
            focusFirstError(found, (data.billingfields || []).map((field) => field.field));
            return;
        }

        setSubmitting(true);
        setError("");

        try {
            const result = await callMoodleService<PlaceOrderResponse>(placeOrderMethodName, {
                orderid: data.existingorderid,
                paymentmethod: selectedPayment,
                firstname: billing.firstname,
                lastname: billing.lastname,
                email: billing.email,
                phone: billing.phone,
                address: billing.address,
                city: billing.city,
                state: billing.state,
                country: billing.country,
                zipcode: billing.zipcode,
            });

            window.location.assign(result.redirecturl);
        } catch (caught) {
            setError((caught as Error).message);
            setSubmitting(false);
        }
    };

    const renderLayout = (children: ReactNode, actions?: ReactNode) => (
        <ModernLearnerLayout
            activeNav="checkout"
            title={labels.checkout}
            subtitle={labels.checkoutdesc}
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

    if (error && !data) {
        return renderLayout(
            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                <div className={mcClasses("mc-alert__body")}>
                    <div className="fw-semibold mb-2">{error}</div>
                    <button type="button" className={mcClasses("mc-button mc-btn-soft")} onClick={() => location.reload()}>
                        {labels.retry}
                    </button>
                </div>
            </div>
        );
    }

    if (!data || !billing || !data.cancheckout) {
        return renderLayout(<EmptyCheckout data={data} labels={labels} />);
    }

    const requiresBilling = !["manual", "enrollkey"].includes(selectedPayment);
    const busy = submitting || couponBusy;
    const alertClassName = mcClasses(`mc-alert ${error ? "mc-alert--danger" : "mc-alert--info"}`);
    const alertIconClassName = `bi ${error ? "bi-exclamation-triangle" : "bi-info-circle"} mc-alert__icon`;

    const actions = (
        <a href={data.urls.cart} className={mcClasses("mc-button btn-mc-secondary")}>
            <i className="bi bi-arrow-left" aria-hidden="true" />
            {labels.backtocart}
        </a>
    );

    return renderLayout(
        <div className={mcClasses("mc-checkout-page")}>
                {(message || error) && (
                    <div
                        className={alertClassName}
                        role="alert"
                        aria-live="polite"
                    >
                        <i className={alertIconClassName} aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{error || message}</div>
                    </div>
                )}

                <form onSubmit={handleSubmit}>
                    <div className={mcClasses("mc-checkout-layout")}>
                        <div>
                            <div className={mcClasses("mc-form-section mb-3")}>
                                <div className={mcClasses("mc-form-section__header")}>
                                    <h2 className={mcClasses("mc-form-section__title")}>
                                        <i className="bi bi-credit-card me-2" aria-hidden="true" />
                                        {labels.paymentmethod}
                                    </h2>
                                </div>
                                <div className={mcClasses("mc-form-section__body")}>
                                    <PaymentMethods
                                        methods={data.paymentmethods}
                                        selected={selectedPayment}
                                        labels={labels}
                                        onSelect={setSelectedPayment}
                                    />
                                </div>
                            </div>

                            <BillingFields
                                fields={data.billingfields}
                                countries={data.countries}
                                billing={billing}
                                required={requiresBilling}
                                labels={labels}
                                onChange={handleBillingChange}
                                errors={fieldErrors}
                            />

                            {data.haspaymentmethods && (
                                <>
                                    <McButton
                                        type="submit"
                                        className={mcClasses("btn-mc-primary d-flex w-100 justify-content-center")}
                                        disabled={couponBusy}
                                        loading={submitting}
                                        loadingLabel={labels.processing || "Processing..."}
                                    >
                                        <i className="bi bi-lock me-2" aria-hidden="true" />
                                        {labels.placeorder}
                                    </McButton>
                                    <div className={mcClasses("mc-trust-note mt-2")}>
                                        <i className="bi bi-shield-check" aria-hidden="true" />
                                        {labels.securepayment}
                                    </div>
                                    {publicLinks.length > 0 && (
                                        <div className={mcClasses("mc-cell-muted small mt-2 d-flex flex-wrap gap-2")}>
                                            {publicLinks.map((link, index) => (
                                                <span className="d-inline-flex gap-2" key={link.key}>
                                                    {index > 0 && <span aria-hidden="true">·</span>}
                                                    <a href={link.url} target="_blank" rel="noreferrer">{link.label}</a>
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </>
                            )}
                        </div>

                        <div>
                            <Summary
                                data={data}
                                labels={labels}
                                couponInput={couponInput}
                                busy={busy}
                                onCouponInput={setCouponInput}
                                onApplyCoupon={() => void handleCouponAction("applycoupon")}
                                onRemoveCoupon={() => void handleCouponAction("removecoupon")}
                            />
                        </div>
                    </div>
                </form>
        </div>,
        actions
    );
}

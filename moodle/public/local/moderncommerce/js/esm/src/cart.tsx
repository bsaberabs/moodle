// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React buyer cart page for Modern Commerce.
 *
 * @module     local_moderncommerce/cart
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Dispatch, FormEvent, FormEventHandler, ReactNode, useEffect, useState} from "react";
import {callMoodleService, Labels, refreshNavbarCart} from "./learner_common";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {confirmDialog} from "./modal";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";
import {minLength, required, runRules} from "./validation";

type CartItem = {
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

type Coupon = {
    hascoupon: boolean;
    code: string;
    discount: string;
};

type CartResponse = {
    success: boolean;
    message: string;
    isempty: boolean;
    itemcount: number;
    items: CartItem[];
    totals: Totals;
    coupon: Coupon;
    urls: {
        catalog: string;
        cart: string;
        checkout: string;
    };
    sesskey: string;
};

type CartProps = {
    methodName: string;
    updateMethodName: string;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}>
                <i className="bi bi-cart" aria-hidden="true" />
            </span>
            <p className={mcClasses("mc-empty__title")}>{labels.loading}</p>
        </div>
    );
}

function EmptyCart({
    data,
    labels,
}: {
    data: CartResponse | null;
    labels: Labels;
}) {
    const catalogUrl = data?.urls.catalog ?? "#";

    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}>
                <i className="bi bi-cart" aria-hidden="true" />
            </span>
            <p className={mcClasses("mc-empty__title")}>{labels.emptycart}</p>
            <p className={mcClasses("mc-empty__desc")}>{labels.emptycartmessage}</p>
            <a href={catalogUrl} className={mcClasses("mc-button btn-mc-primary")}>
                {labels.browsecatalog}
            </a>
        </div>
    );
}

function CartItemRow({
    item,
    labels,
    busy,
    onRemove,
}: {
    item: CartItem;
    labels: Labels;
    busy: boolean;
    onRemove: Dispatch<CartItem>;
}) {
    return (
        <div className={mcClasses("mc-cart-item")}>
            {item.hasimage ? (
                <img
                    src={item.imageurl}
                    alt=""
                    className={mcClasses("mc-cart-item__thumb")}
                    width={64}
                    height={48}
                    loading="lazy"
                />
            ) : (
                <div className={mcClasses("mc-cart-item__thumb")} aria-hidden="true" />
            )}
            <div className={mcClasses("mc-cart-item__body")}>
                <a className={mcClasses("mc-cart-item__title text-decoration-none")} href={item.detailsurl}>
                    {item.name}
                </a>
                <div className={mcClasses("mc-cart-item__meta d-flex align-items-center gap-2 flex-wrap")}>
                    <span className={mcClasses("mc-badge mc-badge--neutral")}>{item.typelabel}</span>
                    {item.shortname && <span className={mcClasses("mc-cell-muted")}>{item.shortname}</span>}
                    <span className={mcClasses("mc-cell-muted")}>x{item.quantitylabel}</span>
                </div>
            </div>
            <div className={mcClasses("mc-cart-item__price")}>{item.linetotal}</div>
            <button
                type="button"
                className={mcClasses("mc-button mc-cart-item__remove")}
                data-mc-button="ghost"
                data-mc-button-size="icon"
                title={labels.removefromcart}
                aria-label={`${labels.removefromcart} ${item.name}`}
                disabled={busy}
                onClick={() => onRemove(item)}
            >
                <i className="bi bi-x-lg" aria-hidden="true" />
            </button>
        </div>
    );
}

function OrderSummary({
    data,
    labels,
    couponInput,
    couponError,
    busy,
    onCouponInput,
    onApplyCoupon,
    onRemoveCoupon,
}: {
    data: CartResponse;
    labels: Labels;
    couponInput: string;
    couponError: string;
    busy: boolean;
    onCouponInput: Dispatch<string>;
    onApplyCoupon: FormEventHandler<HTMLFormElement>;
    onRemoveCoupon: () => void;
}) {
    return (
        <div className={mcClasses("mc-order-summary")}>
            <div className={mcClasses("mc-order-summary__header")}>{labels.ordersummary}</div>
            <div className={mcClasses("mc-order-summary__body")}>
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

                {data.coupon.hascoupon ? (
                    <div className="d-flex align-items-center gap-2 mt-2 pt-2 border-top">
                        <span className={mcClasses("mc-badge mc-badge--success flex-grow-1")}>
                            <i className="bi bi-ticket-perforated me-1" aria-hidden="true" />
                            {data.coupon.code}
                        </span>
                        <button
                            type="button"
                            className={mcClasses("mc-button mc-btn-soft mc-btn-soft--danger")}
                            disabled={busy}
                            onClick={onRemoveCoupon}
                        >
                            {labels.removecoupon}
                        </button>
                    </div>
                ) : (
                    <form className={mcClasses("mc-coupon-input mt-3")} noValidate onSubmit={onApplyCoupon}>
                        <input
                            type="text"
                            aria-describedby={couponError ? "mc-cart-coupon-error" : undefined}
                            aria-invalid={couponError ? true : undefined}
                            className={mcClasses("mc-form-control", couponError ? "mc-field-invalid" : "")}
                            value={couponInput}
                            placeholder={labels.couponcode}
                            aria-label={labels.couponcode}
                            onChange={(event) => onCouponInput(event.currentTarget.value)}
                        />
                        <McButton
                            type="submit"
                            className={mcClasses("mc-btn-soft flex-shrink-0")}
                            loading={busy}
                            loadingLabel={labels.loading || "Applying..."}
                        >
                            {labels.applycoupon}
                        </McButton>
                    </form>
                )}
                {couponError && !data.coupon && (
                    <div className={mcClasses("mc-field-error")} id="mc-cart-coupon-error" role="alert">
                        <i className={mcClasses("bi bi-exclamation-circle mc-field-error__icon")} aria-hidden="true" />
                        <span>{couponError}</span>
                    </div>
                )}

                <a
                    href={data.urls.checkout}
                    className={mcClasses("mc-button btn-mc-primary w-100 d-flex justify-content-center mt-3")}
                >
                    <i className="bi bi-lock me-1" aria-hidden="true" />
                    {labels.proceedtocheckout}
                </a>

                <div className={mcClasses("mc-trust-note mt-2")}>
                    <i className="bi bi-shield-check" aria-hidden="true" />
                    {labels.securepayment}
                </div>
            </div>
        </div>
    );
}

export default function Cart({
    methodName,
    updateMethodName,
    labels,
    layout,
}: CartProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<CartResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");
    const [couponInput, setCouponInput] = useState("");
    const [couponError, setCouponError] = useState("");
    const [busyAction, setBusyAction] = useState("");

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");

        callMoodleService<CartResponse>(methodName, {})
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                    setCouponInput(result.coupon.code);
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
    }, [methodName]);

    const updateCart = async(args: Record<string, string | number>) => {
        setBusyAction(String(args.action));
        setError("");
        setMessage("");

        try {
            const result = await callMoodleService<CartResponse>(updateMethodName, args);
            setData(result);
            setCouponInput(result.coupon.code);
            void refreshNavbarCart(result);
            toast.success(result.message);
        } catch (caught) {
            setError((caught as Error).message);
        } finally {
            setBusyAction("");
        }
    };

    const handleRemove = async(item: CartItem) => {
        if (!await confirmDialog({
            title: labels.removetitle,
            message: labels.removeconfirm,
            confirmLabel: labels.removeconfirmlabel,
            cancelLabel: labels.cancel,
            danger: true,
        })) {
            return;
        }

        void updateCart({action: "remove", itemid: item.id});
    };

    const handleClear = async() => {
        if (!await confirmDialog({
            title: labels.clearcarttitle,
            message: labels.clearcartconfirm,
            confirmLabel: labels.clearcartconfirmlabel,
            cancelLabel: labels.cancel,
            danger: true,
        })) {
            return;
        }

        void updateCart({action: "clear"});
    };

    const handleApplyCoupon = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        // Reported on the field rather than the page-level banner, so the
        // message sits next to the control the user has to correct.
        const found = runRules({couponcode: couponInput}, {
            couponcode: [
                required(labels["error:required"] ?? labels.entercouponcode),
                minLength(3, labels["error:minlength"] ?? labels.entercouponcode),
            ],
        });
        setCouponError(found.couponcode ?? "");
        if (found.couponcode) {
            return;
        }

        void updateCart({action: "applycoupon", couponcode: couponInput});
    };

    const handleRemoveCoupon = () => {
        void updateCart({action: "removecoupon"});
    };

    const wrap = (children: ReactNode) => (
        <ModernLearnerLayout
            activeNav="cart"
            title={labels.shoppingcart}
            subtitle={labels.checkoutdesc || labels.cartandcheckout || labels.securepayment}
            labels={labels}
            layout={layout}
        >
            {children}
        </ModernLearnerLayout>
    );

    if (loading) {
        return wrap(<LoadingState labels={labels} />);
    }

    if (error && !data) {
        return wrap(
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

    if (!data || data.isempty) {
        return wrap(<EmptyCart data={data} labels={labels} />);
    }

    const busy = busyAction !== "";
    const alertClassName = mcClasses(`mc-alert ${error ? "mc-alert--danger" : "mc-alert--success"}`);
    const alertIconClassName = `bi ${error ? "bi-exclamation-triangle" : "bi-check-circle"} mc-alert__icon`;

    return (
        <ModernLearnerLayout
            activeNav="cart"
            title={labels.shoppingcart}
            subtitle={labels.checkoutdesc || labels.cartandcheckout || labels.securepayment}
            labels={labels}
            layout={layout}
            cartCount={data.itemcount}
            actions={(
                <a href={data.urls.catalog} className={mcClasses("mc-button btn-mc-secondary")}>
                    <i className="bi bi-arrow-left" aria-hidden="true" />
                    {labels.continueshopping}
                </a>
            )}
        >
            <div className={mcClasses("mc-cart-page")}>
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

                <div className={mcClasses("mc-cart-layout")}>
                    <div>
                        <div className="d-flex align-items-center justify-content-between gap-3 mb-3">
                            <h2 className={mcClasses("mc-page-title mb-0")}>
                                {labels.shoppingcart}
                                <span className={mcClasses("mc-badge mc-badge--neutral ms-2")}>
                                    {data.itemcount}
                                </span>
                            </h2>
                            <button
                                type="button"
                                className={mcClasses("mc-button mc-btn-soft")}
                                disabled={busy}
                                onClick={handleClear}
                            >
                                {labels.clearcart}
                            </button>
                        </div>

                        <div className={mcClasses("mc-cart-items")} aria-busy={busy}>
                            {data.items.map((item) => (
                                <CartItemRow
                                    key={item.id}
                                    item={item}
                                    labels={labels}
                                    busy={busy}
                                    onRemove={handleRemove}
                                />
                            ))}
                        </div>

                        <div className="mt-3">
                            <a href={data.urls.catalog} className={mcClasses("mc-button mc-btn-soft")}>
                                <i className="bi bi-arrow-left me-1" aria-hidden="true" />
                                {labels.continueshopping}
                            </a>
                        </div>
                    </div>

                    <div>
                        <OrderSummary
                            data={data}
                            labels={labels}
                            couponInput={couponInput}
                            couponError={couponError}
                            busy={busy}
                            onCouponInput={(value) => {
                                setCouponInput(value);
                                setCouponError("");
                            }}
                            onApplyCoupon={handleApplyCoupon}
                            onRemoveCoupon={handleRemoveCoupon}
                        />
                    </div>
                </div>
            </div>
        </ModernLearnerLayout>
    );
}

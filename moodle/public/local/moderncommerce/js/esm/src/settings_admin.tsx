// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin commerce settings for Modern Commerce.
 *
 * Unified, tabbed settings console: core plugin config groups (store identity,
 * currency, tax, documents, checkout fields, navigation, and product form settings) are
 * editable here with a single global save. Catalog display is configured
 * per-widget from the storefront side panel, not here.
 *
 * @module     local_moderncommerce/settings_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useMemo, useState} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import ContactEmailsAdmin from "./contact_emails_admin";
import {
    email as emailRule,
    integer,
    max,
    maxLength,
    min,
    normaliseServerErrors,
    percent,
    required,
    runRules,
    url as urlRule,
} from "./validation";
import type {FieldErrorMap, RuleMap} from "./validation";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;
type Option = {value: string; label: string};
type Metric = {label: string; value: string; icon: string; variant: string};

type Values = {
    primary_currency: string;
    currency_position: string;
    decimal_places: number;
    thousand_separator: string;
    decimal_separator: string;
    business_name: string;
    support_email: string;
    support_url: string;
    invoice_prefix: string;
    receipt_prefix: string;
    tax_mode: string;
    default_tax_rate: number;
    contact_info_enabled: number;
    phone_field: string;
    address_field: string;
    city_field: string;
    state_field: string;
    country_field: string;
    zipcode_field: string;
    adminnavlabel: string;
    learnernavlabel: string;
    hideprimarynavitems: string[];
    navbar_cart_position: string;
    notification_position: string;
    notification_autodismiss: number;
    reviews_enabled: number;
    product_show_sku: number;
    product_show_slug: number;
    course_detail_sidebar_position: string;
    enable_webhook_ip_whitelist: number;
    payment_max_retries: number;
    default_program_certificate_template_id: number;
};

type SettingsResponse = {
    values: Values;
    currencyoptions: Option[];
    positionoptions: Option[];
    taxmodes: Option[];
    fieldvisibilityoptions: Option[];
    notificationpositionoptions: Option[];
    navitemoptions: Option[];
    navbarcartpositionoptions: Option[];
    coursedetailsidebarpositionoptions: Option[];
    coursecertificateavailable: boolean;
    certificatetemplateoptions: Option[];
    metrics: Metric[];
};

type SaveResponse = {
    success: boolean;
    message: string;
    errors: Array<{field: string; message: string}>;
};

type Form = {
    primary_currency: string;
    currency_position: string;
    decimal_places: string;
    thousand_separator: string;
    decimal_separator: string;
    business_name: string;
    support_email: string;
    support_url: string;
    invoice_prefix: string;
    receipt_prefix: string;
    tax_mode: string;
    default_tax_rate: string;
    contact_info_enabled: boolean;
    phone_field: string;
    address_field: string;
    city_field: string;
    state_field: string;
    country_field: string;
    zipcode_field: string;
    adminnavlabel: string;
    learnernavlabel: string;
    hideprimarynavitems: string[];
    navbar_cart_position: string;
    notification_position: string;
    notification_autodismiss: string;
    reviews_enabled: boolean;
    product_show_sku: boolean;
    product_show_slug: boolean;
    course_detail_sidebar_position: string;
    enable_webhook_ip_whitelist: boolean;
    payment_max_retries: string;
    default_program_certificate_template_id: string;
};

type Props = {
    getMethodName: string;
    saveMethodName: string;
    initialTab?: string;
    certificateTemplatesUrl?: string;
    contactEmails?: ContactEmailsConfig;
    labels: Labels;
};

type ContactEmailsConfig = {
    available: boolean;
    getMethodName: string;
    saveMethodName: string;
    labels: Labels;
};

type TabConfig = {id: string; labelKey: string; icon: string};

const CONTACT_EMAILS_TAB_ID = "contact_autoreply";

const TABS: TabConfig[] = [
    {id: "store", labelKey: "tabStore", icon: "bi-shop"},
    {id: "currency", labelKey: "tabCurrency", icon: "bi-currency-exchange"},
    {id: "tax", labelKey: "tabTax", icon: "bi-receipt"},
    {id: "documents", labelKey: "tabDocuments", icon: "bi-file-earmark-text"},
    {id: "checkout", labelKey: "tabCheckout", icon: "bi-bag-check"},
    {id: "navigation", labelKey: "tabNavigation", icon: "bi-compass"},
    {id: "notifications", labelKey: "tabNotifications", icon: "bi-bell"},
    {id: "reviews", labelKey: "tabReviews", icon: "bi-star"},
    {id: "products", labelKey: "tabProducts", icon: "bi-box-seam"},
    {id: "certificates", labelKey: "tabCertificates", icon: "bi-award"},
];

const CONTACT_EMAILS_TAB: TabConfig = {
    id: CONTACT_EMAILS_TAB_ID,
    labelKey: "tabContactAutoreply",
    icon: "bi-reply",
};

// Maps a field key to the tab it lives on, so a validation error reveals its panel.
const FIELD_TAB: Record<string, string> = {
    business_name: "store", support_email: "store", support_url: "store",
    primary_currency: "currency", currency_position: "currency", decimal_places: "currency",
    thousand_separator: "currency", decimal_separator: "currency",
    tax_mode: "tax", default_tax_rate: "tax",
    invoice_prefix: "documents", receipt_prefix: "documents",
    contact_info_enabled: "checkout",
    phone_field: "checkout", address_field: "checkout", city_field: "checkout",
    state_field: "checkout", country_field: "checkout", zipcode_field: "checkout",
    adminnavlabel: "navigation", learnernavlabel: "navigation", hideprimarynavitems: "navigation",
    navbar_cart_position: "navigation",
    notification_position: "notifications", notification_autodismiss: "notifications",
    reviews_enabled: "reviews",
    product_show_sku: "products", product_show_slug: "products",
    course_detail_sidebar_position: "products",
    default_program_certificate_template_id: "certificates",
    // Owned by the webhooks screen, which saves through this same endpoint.
    // Without an entry here their errors fall through to a toast instead of
    // revealing the panel that holds them.
    enable_webhook_ip_whitelist: "store", payment_max_retries: "store",
};

const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php?sesskey=${encodeURIComponent(M.cfg.sesskey)}&info=${encodeURIComponent(methodName)}`;
    const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify([{index: 0, methodname: methodName, args}]),
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

const valuesToForm = (v: Values): Form => ({
    primary_currency: v.primary_currency,
    currency_position: v.currency_position,
    decimal_places: String(v.decimal_places),
    thousand_separator: v.thousand_separator,
    decimal_separator: v.decimal_separator,
    business_name: v.business_name,
    support_email: v.support_email,
    support_url: v.support_url,
    invoice_prefix: v.invoice_prefix,
    receipt_prefix: v.receipt_prefix,
    tax_mode: v.tax_mode,
    default_tax_rate: String(v.default_tax_rate),
    contact_info_enabled: Boolean(v.contact_info_enabled),
    phone_field: v.phone_field,
    address_field: v.address_field,
    city_field: v.city_field,
    state_field: v.state_field,
    country_field: v.country_field,
    zipcode_field: v.zipcode_field,
    adminnavlabel: v.adminnavlabel,
    learnernavlabel: v.learnernavlabel,
    hideprimarynavitems: [...v.hideprimarynavitems],
    navbar_cart_position: v.navbar_cart_position,
    notification_position: v.notification_position,
    notification_autodismiss: String(v.notification_autodismiss),
    reviews_enabled: Boolean(v.reviews_enabled),
    product_show_sku: Boolean(v.product_show_sku),
    product_show_slug: Boolean(v.product_show_slug),
    course_detail_sidebar_position: v.course_detail_sidebar_position,
    enable_webhook_ip_whitelist: Boolean(v.enable_webhook_ip_whitelist),
    payment_max_retries: String(v.payment_max_retries),
    default_program_certificate_template_id: String(v.default_program_certificate_template_id),
});

const formToArgs = (form: Form) => ({
    primary_currency: form.primary_currency,
    currency_position: form.currency_position,
    decimal_places: Number(form.decimal_places) || 0,
    thousand_separator: form.thousand_separator,
    decimal_separator: form.decimal_separator,
    business_name: form.business_name,
    support_email: form.support_email,
    support_url: form.support_url,
    invoice_prefix: form.invoice_prefix,
    receipt_prefix: form.receipt_prefix,
    tax_mode: form.tax_mode,
    default_tax_rate: Number(form.default_tax_rate) || 0,
    contact_info_enabled: form.contact_info_enabled ? 1 : 0,
    phone_field: form.phone_field,
    address_field: form.address_field,
    city_field: form.city_field,
    state_field: form.state_field,
    country_field: form.country_field,
    zipcode_field: form.zipcode_field,
    adminnavlabel: form.adminnavlabel,
    learnernavlabel: form.learnernavlabel,
    hideprimarynavitems: form.hideprimarynavitems,
    navbar_cart_position: form.navbar_cart_position,
    notification_position: form.notification_position,
    notification_autodismiss: Number(form.notification_autodismiss) || 0,
    reviews_enabled: form.reviews_enabled ? 1 : 0,
    product_show_sku: form.product_show_sku ? 1 : 0,
    product_show_slug: form.product_show_slug ? 1 : 0,
    course_detail_sidebar_position: form.course_detail_sidebar_position,
    enable_webhook_ip_whitelist: form.enable_webhook_ip_whitelist ? 1 : 0,
    payment_max_retries: Number(form.payment_max_retries) || 0,
    default_program_certificate_template_id: Number(form.default_program_certificate_template_id) || 0,
});

export default function SettingsAdmin({
    getMethodName,
    saveMethodName,
    initialTab = "store",
    certificateTemplatesUrl = "",
    contactEmails,
    labels,
}: Props) {
    useModernCommerceClassSync();
    const tabs = useMemo(() => (contactEmails ? [...TABS, CONTACT_EMAILS_TAB] : TABS), [contactEmails]);
    const initialActive = tabs.some((tab) => tab.id === initialTab) ? initialTab : "store";
    const [data, setData] = useState<SettingsResponse | null>(null);
    const [form, setForm] = useState<Form | null>(null);
    const [saved, setSaved] = useState<Form | null>(null);
    const [active, setActive] = useState(initialActive);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");
        void callMoodleService<SettingsResponse>(getMethodName, {})
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                    setForm(valuesToForm(result.values));
                    setSaved(valuesToForm(result.values));
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
    }, [getMethodName]);

    const dirty = useMemo(
        () => Boolean(form && saved) && JSON.stringify(form) !== JSON.stringify(saved),
        [form, saved]
    );
    const onContactEmailsTab = active === CONTACT_EMAILS_TAB_ID;

    const update = (changes: Partial<Form>) => {
        setForm((current) => current ? {...current, ...changes} : current);
        setFieldErrors((current) => {
            const next = {...current};
            Object.keys(changes).forEach((field) => delete next[field]);
            return next;
        });
    };

    const discard = () => {
        if (saved) {
            setForm({...saved, hideprimarynavitems: [...saved.hideprimarynavitems]});
        }
        setFieldErrors({});
        setError("");
    };

    // Mirrors commerce_settings_service::validate_admin_settings so an obvious
    // mistake is caught before the round trip. The server stays authoritative.
    const rules = useMemo<RuleMap<Form>>(() => {
        const t = (key: string) => labels[key] ?? key;
        return {
            business_name: [required(t("error:required")), maxLength(255, t("error:maxlength"))],
            support_email: emailRule(t("error:invalidemail")),
            support_url: urlRule(t("error:invalidurl"), {requireHttp: true}),
            primary_currency: required(t("error:selectoption")),
            decimal_places: [integer(t("error:invalidinteger")), min(0, t("error:minvalue")), max(6, t("error:maxvalue"))],
            thousand_separator: maxLength(1, t("error:maxlength")),
            decimal_separator: [required(t("error:required")), maxLength(1, t("error:maxlength"))],
            default_tax_rate: percent(t("error:invalidpercent")),
            invoice_prefix: maxLength(20, t("error:maxlength")),
            receipt_prefix: maxLength(20, t("error:maxlength")),
            adminnavlabel: maxLength(50, t("error:maxlength")),
            learnernavlabel: maxLength(50, t("error:maxlength")),
            notification_autodismiss: [integer(t("error:invalidinteger")), min(0, t("error:minvalue"))],
            payment_max_retries: [
                integer(t("error:invalidinteger")), min(0, t("error:minvalue")), max(10, t("error:maxvalue")),
            ],
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [labels]);

    /** Show the errors and reveal the tab holding the first one. */
    const applyFieldErrors = (map: FieldErrorMap, fallbackMessage = ""): void => {
        setFieldErrors(map);
        const order = [...Object.keys(rules), ...Object.keys(FIELD_TAB)];
        const first = order.find((field) => map[field]) ?? Object.keys(map)[0];
        const tab = first ? FIELD_TAB[first] : undefined;
        if (tab) {
            setActive(tab);
        } else if (fallbackMessage) {
            toast.error(fallbackMessage);
        }

        if (first) {
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    const control = document.getElementById(`mc-setting-${first}`);
                    control?.scrollIntoView({block: "center", behavior: "smooth"});
                    control?.focus({preventScroll: true});
                });
            });
        }
    };

    const submit = async() => {
        if (!form) {
            return;
        }

        const clientErrors = runRules(form, rules);
        // The thousands and decimal separators must differ or prices become
        // unparseable; neither field alone is wrong, so it is checked here.
        if (!clientErrors.decimal_separator
            && String(form.decimal_separator) === String(form.thousand_separator)) {
            clientErrors.decimal_separator = labels.separatorsmustdiffer
                ?? labels["error:required"] ?? "separatorsmustdiffer";
        }
        if (Object.keys(clientErrors).length > 0) {
            applyFieldErrors(clientErrors);
            return;
        }

        setSaving(true);
        setError("");
        setFieldErrors({});
        try {
            const result = await callMoodleService<SaveResponse>(saveMethodName, formToArgs(form));
            if (!result.success) {
                applyFieldErrors(normaliseServerErrors(result.errors), result.message);
                return;
            }
            // Refresh server-computed data (metric tiles, price preview, option lists)
            // and reset the saved baseline so the summary reflects the new saved state.
            try {
                const fresh = await callMoodleService<SettingsResponse>(getMethodName, {});
                const freshForm = valuesToForm(fresh.values);
                setData(fresh);
                setForm(freshForm);
                setSaved(freshForm);
            } catch {
                setSaved({...form, hideprimarynavitems: [...form.hideprimarynavitems]});
            }
            toast.success(result.message);
        } catch (caught) {
            toast.error(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    if (loading && !form) {
        return <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>;
    }
    if (error && !form) {
        return (
            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                <div className={mcClasses("mc-alert__body")}>{error}</div>
            </div>
        );
    }
    if (!form || !data) {
        return null;
    }

    // Required flags come from the rule map so the asterisk cannot drift away
    // from what is actually enforced.
    const isRequired = (key: keyof Form): boolean => {
        const entry = rules[key as Extract<keyof Form, string>];
        const list = Array.isArray(entry) ? entry : (entry ? [entry] : []);
        return list.some((rule) => rule("", form) !== undefined);
    };

    const fieldError = (key: keyof Form) => fieldErrors[key as string];

    const controlProps = (key: keyof Form) => {
        const message = fieldError(key);
        const id = `mc-setting-${String(key)}`;
        return {
            id,
            "aria-describedby": message ? `${id}-error` : undefined,
            "aria-invalid": message ? true : undefined,
            "aria-required": isRequired(key) ? true : undefined,
        };
    };

    const fieldMessage = (key: keyof Form) => {
        const message = fieldError(key);
        if (!message) {
            return null;
        }
        return (
            <div className={mcClasses("mc-field-error")} id={`mc-setting-${String(key)}-error`} role="alert">
                <i className={mcClasses("bi bi-exclamation-circle mc-field-error__icon")} aria-hidden="true" />
                <span>{message}</span>
            </div>
        );
    };

    const fieldLabel = (key: keyof Form, label: string) => (
        <span>
            {label}
            {isRequired(key) && <span className={mcClasses("mc-required")} aria-hidden="true">*</span>}
        </span>
    );

    const textField = (key: keyof Form, label: string, type = "text", attrs: Record<string, string> = {}) => (
        <label htmlFor={`mc-setting-${String(key)}`} key={key}>
            {fieldLabel(key, label)}
            <input
                {...controlProps(key)}
                className={mcClasses("mc-form-control", fieldError(key) ? "mc-field-invalid" : "")}
                onChange={(e) => update({[key]: e.target.value} as Partial<Form>)}
                type={type}
                value={String(form[key])}
                {...attrs}
            />
            {fieldMessage(key)}
        </label>
    );

    const selectField = (key: keyof Form, label: string, options: Option[]) => (
        <label htmlFor={`mc-setting-${String(key)}`} key={key}>
            {fieldLabel(key, label)}
            <select
                {...controlProps(key)}
                className={mcClasses("mc-select", fieldError(key) ? "mc-field-invalid" : "")}
                onChange={(e) => update({[key]: e.target.value} as Partial<Form>)}
                value={String(form[key])}
            >
                {options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
            {fieldMessage(key)}
        </label>
    );

    const switchField = (key: keyof Form, label: string) => (
        <label className={mcClasses("mc-switch")} key={key}>
            <input
                checked={Boolean(form[key])}
                onChange={(e) => update({[key]: e.target.checked} as Partial<Form>)}
                type="checkbox"
            />
            <span className={mcClasses("mc-switch__track")} />
            <span className={mcClasses("mc-switch__thumb")} />
            <span className={mcClasses("mc-switch__label")}>{label}</span>
        </label>
    );

    const checkboxGroupField = (key: keyof Form, label: string, options: Option[]) => {
        const selected = (form[key] as string[]) || [];
        const toggle = (value: string) => {
            const next = selected.includes(value)
                ? selected.filter((v) => v !== value)
                : [...selected, value];
            update({[key]: next} as Partial<Form>);
        };
        return (
            <div className={mcClasses("mc-settings-checkgroup")} key={key}>
                <span className={mcClasses("mc-settings-checkgroup__label")}>{label}</span>
                <div className={mcClasses("mc-settings-checkgroup__items")}>
                    {options.map((o) => (
                        <label className={mcClasses("mc-checkbox")} key={o.value}>
                            <input checked={selected.includes(o.value)} onChange={() => toggle(o.value)} type="checkbox" />
                            {" "}{o.label}
                        </label>
                    ))}
                </div>
            </div>
        );
    };

    const decimalOptions: Option[] = Array.from({length: 7}, (_, i) => ({value: String(i), label: String(i)}));

    const section = (title: string, desc: string, children: React.ReactNode) => (
        <div className={mcClasses("mc-product-form__section")}>
            <h4>{title}</h4>
            {desc && <p className={mcClasses("mc-cell-muted small")}>{desc}</p>}
            <div className={mcClasses("mc-product-form__grid")}>{children}</div>
        </div>
    );

    const renderPanel = () => {
        switch (active) {
            case "store":
                return section(labels.storeidentity, labels.storeidentitydesc, [
                    textField("business_name", labels.businessname),
                    textField("support_email", labels.supportemail, "email"),
                    textField("support_url", labels.supporturl, "url"),
                ]);
            case "currency":
                return section(labels.currencysettings, labels.currencysettingsdesc, [
                    selectField("primary_currency", labels.primarycurrency, data.currencyoptions),
                    selectField("currency_position", labels.currencyposition, data.positionoptions),
                    selectField("decimal_places", labels.decimalplaces, decimalOptions),
                    textField("thousand_separator", labels.thousandseparator),
                    textField("decimal_separator", labels.decimalseparator),
                ]);
            case "tax":
                return section(labels.taxsettings, labels.taxsettingsdesc, [
                    selectField("tax_mode", labels.taxmode, data.taxmodes),
                    textField("default_tax_rate", labels.defaulttaxrate, "number", {step: "0.01", min: "0", max: "100"}),
                ]);
            case "documents":
                return section(labels.documentsettings, labels.documentsettingsdesc, [
                    textField("invoice_prefix", labels.invoiceprefix),
                    textField("receipt_prefix", labels.receiptprefix),
                ]);
            case "checkout":
                return (
                    <div className={mcClasses("mc-product-form__section")}>
                        <h4>{labels.checkoutfields}</h4>
                        <p className={mcClasses("mc-cell-muted small")}>{labels.checkoutfieldsdesc}</p>
                        <div className={mcClasses("mc-settings-switchrow")}>
                            {switchField("contact_info_enabled", labels.contactinfoenabled)}
                        </div>
                        {form.contact_info_enabled && (
                            <div className={mcClasses("mc-product-form__grid")}>
                                {selectField("phone_field", labels.phonefield, data.fieldvisibilityoptions)}
                                {selectField("address_field", labels.addressfield, data.fieldvisibilityoptions)}
                                {selectField("city_field", labels.cityfield, data.fieldvisibilityoptions)}
                                {selectField("state_field", labels.statefield, data.fieldvisibilityoptions)}
                                {selectField("country_field", labels.countryfield, data.fieldvisibilityoptions)}
                                {selectField("zipcode_field", labels.zipcodefield, data.fieldvisibilityoptions)}
                            </div>
                        )}
                    </div>
                );
            case "navigation":
                return (
                    <div className={mcClasses("mc-product-form__section")}>
                        <h4>{labels.navigationsettings}</h4>
                        <p className={mcClasses("mc-cell-muted small")}>{labels.navigationsettingsdesc}</p>
                        <div className={mcClasses("mc-product-form__grid")}>
                            {textField("adminnavlabel", labels.adminnavlabel)}
                            {textField("learnernavlabel", labels.learnernavlabel)}
                            {selectField("navbar_cart_position", labels.navbarcartposition, data.navbarcartpositionoptions)}
                        </div>
                        {checkboxGroupField("hideprimarynavitems", labels.hideprimarynavitems, data.navitemoptions)}
                    </div>
                );
            case "notifications":
                return section(labels.notificationsettings, labels.notificationsettingsdesc, [
                    selectField("notification_position", labels.notificationposition, data.notificationpositionoptions),
                    textField("notification_autodismiss", labels.notificationautodismiss, "number", {min: "0", step: "500"}),
                ]);
            case "reviews":
                return (
                    <div className={mcClasses("mc-product-form__section")}>
                        <h4>{labels.reviewsettings}</h4>
                        <p className={mcClasses("mc-cell-muted small")}>{labels.reviewsettingsdesc}</p>
                        <div className={mcClasses("mc-settings-switchrow")}>
                            {switchField("reviews_enabled", labels.reviewsenabled)}
                        </div>
                    </div>
                );
            case "products":
                return (
                    <div className={mcClasses("mc-product-form__section")}>
                        <h4>{labels.productformsettings}</h4>
                        <p className={mcClasses("mc-cell-muted small")}>{labels.productformsettingsdesc}</p>
                        <div className={mcClasses("mc-settings-switchrow")}>
                            {switchField("product_show_sku", labels.showskufield)}
                        </div>
                        <div className={mcClasses("mc-settings-switchrow")}>
                            {switchField("product_show_slug", labels.showslugfield)}
                        </div>
                        <div className={mcClasses("mc-product-form__grid mt-3")}>
                            {selectField(
                                "course_detail_sidebar_position",
                                labels.coursedetailsidebarposition,
                                data.coursedetailsidebarpositionoptions
                            )}
                        </div>
                    </div>
                );
            case "certificates":
                return (
                    <div className={mcClasses("mc-product-form__section")}>
                        <h4>{labels.programcertificatesettings}</h4>
                        <p className={mcClasses("mc-cell-muted small")}>{labels.programcertificatesettingsdesc}</p>
                        {data.coursecertificateavailable ? (
                            <>
                                <div className={mcClasses("mc-product-form__grid mt-3")}>
                                    {selectField(
                                        "default_program_certificate_template_id",
                                        labels.defaultprogramcertificatetemplate,
                                        data.certificatetemplateoptions
                                    )}
                                    {certificateTemplatesUrl && (
                                        <div className="d-flex align-items-end">
                                            <a
                                                className={mcClasses("mc-button mc-btn-soft text-nowrap")}
                                                href={certificateTemplatesUrl}
                                                rel="noopener noreferrer"
                                                target="_blank"
                                            >
                                                <i className="bi bi-award me-1" aria-hidden="true" />
                                                {labels.managecertificatetemplates}
                                                <i className="bi bi-box-arrow-up-right ms-2" aria-hidden="true" />
                                            </a>
                                        </div>
                                    )}
                                </div>
                            </>
                        ) : (
                            <div className={mcClasses("mc-alert mc-alert--warning mt-3")} role="status">
                                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                                <div className={mcClasses("mc-alert__body")}>
                                    {labels.coursecertificateunavailableprogram}
                                    <div className="mt-1">{labels.coursecertificateoptionaldesc}</div>
                                </div>
                            </div>
                        )}
                    </div>
                );
            case CONTACT_EMAILS_TAB_ID:
                if (!contactEmails?.available) {
                    return (
                        <div className={mcClasses("mc-card")}>
                            <div className={mcClasses("mc-card-body")}>
                                <div className={mcClasses("mc-empty mc-empty--centered")}>
                                    <span className={mcClasses("mc-empty__icon")}>
                                        <i className="bi bi-reply" aria-hidden="true" />
                                    </span>
                                    <p className={mcClasses("mc-empty__title")}>{contactEmails?.labels.unavailable}</p>
                                    <p className={mcClasses("mc-empty__text")}>{contactEmails?.labels.unavailable_desc}</p>
                                </div>
                            </div>
                        </div>
                    );
                }
                return (
                    <ContactEmailsAdmin
                        getMethodName={contactEmails.getMethodName}
                        labels={contactEmails.labels}
                        saveMethodName={contactEmails.saveMethodName}
                    />
                );
            default:
                return null;
        }
    };

    return (
        <section className={mcClasses("mc-product-form")} aria-label={labels.storeidentity}>
            <div className={mcClasses("mc-stat-strip")} aria-label={labels.storeidentity}>
                {data.metrics.map((metric, index) => (
                    <article className={mcClasses(`mc-stat-tile mc-stat-tile--${metric.variant}`)} key={`${metric.label}-${index}`}>
                        <i className={`bi ${metric.icon} mc-stat-tile__icon`} aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{metric.label}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{metric.value}</strong>
                        </div>
                        <i className={`bi ${metric.icon} mc-stat-tile__watermark`} aria-hidden="true" />
                    </article>
                ))}
            </div>

            <div className={mcClasses("mc-settings-shell")}>
                <nav className={mcClasses("mc-settings-nav")} aria-label={labels.tabStore} role="tablist">
                    {tabs.map((tab) => (
                        <button
                            aria-selected={active === tab.id ? "true" : "false"}
                            className={mcClasses("mc-button mc-settings-nav__item", active === tab.id ? "is-active" : "")}
                            data-mc-button={active === tab.id ? "primary" : "ghost"}
                            key={tab.id}
                            onClick={() => setActive(tab.id)}
                            role="tab"
                            type="button"
                        >
                            <i className={`bi ${tab.icon}`} aria-hidden="true" />
                            <span>{labels[tab.labelKey]}</span>
                        </button>
                    ))}
                </nav>
                <div className={mcClasses("mc-settings-content")}>
                    <div className={mcClasses("mc-settings-panel")} key={active} role="tabpanel">
                        {renderPanel()}
                    </div>
                </div>
            </div>

            {!onContactEmailsTab && <div className={mcClasses("mc-settings-footer")}>
                <span className={mcClasses("mc-settings-footer__status")}>
                    {dirty && (
                        <>
                            <span className={mcClasses("mc-settings-dirty-dot")} aria-hidden="true" />
                            {labels.unsaved}
                        </>
                    )}
                </span>
                <button
                    className={mcClasses("mc-button mc-btn-soft")}
                    disabled={saving || !dirty}
                    onClick={discard}
                    type="button"
                >
                    {labels.discard}
                </button>
                <McButton
                    className={mcClasses("btn-mc-primary")}
                    disabled={!dirty}
                    loading={saving}
                    loadingLabel={labels.saving}
                    onClick={submit}
                    type="button"
                >
                    {labels.save}
                </McButton>
            </div>}
        </section>
    );
}

// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin payment gateways for Modern Commerce.
 *
 * @module     local_moderncommerce/gateways_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useId, useMemo, useRef, useState} from "react";
import type {FormEvent} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {McButton} from "./button";
import {McDrawer} from "./drawer";
import {FieldError} from "./form";
import {focusFirstError, integer, maxLength, min, required, runRules, url as urlRule, when} from "./validation";
import type {FieldErrorMap, RuleMap} from "./validation";
import {McTableActionMenu, McTableCard, McTableFooter} from "./table_components";
import {formatCount} from "./learner_common";

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

type IconOption = SelectOption & {
    domain?: string;
    keywords?: string;
};

type EventSummary = {
    hasevent: boolean;
    status: string;
    statusclass: string;
    date: string;
};

type Gateway = {
    gateway: string;
    displayname: string;
    displayorder: number;
    methodtype: string;
    methodlabel: string;
    component: string;
    classname: string;
    icon: string;
    enabled: boolean;
    testmode: boolean;
    publickey: string;
    merchantid: string;
    supportedcurrencies: string;
    ipwhitelist: string;
    secretconfigured: boolean;
    webhooksecretconfigured: boolean;
    supportswebhooks: boolean;
    supportsrefunds: boolean;
    supportsrecurring: boolean;
    secretok: boolean;
    webhookok: boolean;
    ready: boolean;
    hosted: boolean;
    currencysupported: boolean;
    readinessmessage: string;
    supportedcurrencylist: string;
    lastpaymentevent: EventSummary;
    lastwebhookevent: EventSummary;
};

type ListResponse = {
    gateways: Gateway[];
    activecurrency: string;
    hostedready: number;
    methodtypes: SelectOption[];
};

type SaveResponse = {
    success: boolean;
    gateway: string;
    message: string;
};

type GatewayForm = {
    gateway: string;
    displayname: string;
    displayorder: string;
    methodtype: string;
    component: string;
    classname: string;
    icon: string;
    publickey: string;
    merchantid: string;
    secretkey: string;
    webhooksecret: string;
    supportedcurrencies: string;
    ipwhitelist: string;
    enabled: boolean;
    testmode: boolean;
    supportswebhooks: boolean;
    supportsrefunds: boolean;
    supportsrecurring: boolean;
    secretconfigured: boolean;
    webhooksecretconfigured: boolean;
};

type GatewaysAdminProps = {
    listMethodName: string;
    saveMethodName: string;
    webhooksUrl: string;
    paymentEventsUrl: string;
    webhookEventsUrl: string;
    iconOptions?: IconOption[];
    labels: Labels;
};

type Metric = {
    label: string;
    value: number;
    icon: string;
    variant: string;
};

const ICON_OPTION_LIMIT = 50;

const normalizeGatewayIconName = (value: string): string => {
    const clean = value.trim().replace(/^bi\s+/, "").replace(/^bi-/, "").replace(/^fa\s+/, "");
    const legacyMap: Record<string, string> = {
        "fa-credit-card": "credit-card-2-front",
        "fa-cc-stripe": "stripe",
        "fa-cc-paypal": "paypal",
        "fa-university": "bank",
        "fa-key": "key",
    };

    return legacyMap[clean] ?? clean;
};

const gatewayIconClassName = (value: string): string => {
    const clean = value.trim();

    return `bi bi-${normalizeGatewayIconName(clean) || "credit-card-2-front"}`;
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

function GatewayIconPicker({
    value,
    label,
    options,
    labels,
    onChange,
}: {
    value: string;
    label: string;
    options: IconOption[];
    labels: Labels;
    onChange(icon: string): void;
}) {
    const inputId = useId();
    const normalizedValue = normalizeGatewayIconName(value);
    const [query, setQuery] = useState(normalizedValue);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        setQuery(normalizedValue);
    }, [normalizedValue]);

    const filteredOptions = useMemo(() => {
        const rawNeedle = query.trim().toLowerCase();
        const iconNeedle = normalizeGatewayIconName(query).toLowerCase();
        const matches = rawNeedle === "" ? options : options.filter((option) => {
            const optionIcon = normalizeGatewayIconName(option.value);
            const haystack = [
                option.label,
                optionIcon,
                option.domain ?? "",
                option.keywords ?? "",
            ].join(" ").toLowerCase();

            return haystack.includes(rawNeedle) || haystack.includes(iconNeedle);
        });
        const selected = options.find((option) =>
            normalizeGatewayIconName(option.value) === normalizedValue
        );
        const ordered = selected && !matches.includes(selected) ? [selected, ...matches] : matches;

        return ordered.slice(0, ICON_OPTION_LIMIT);
    }, [normalizedValue, options, query]);

    const commitQuery = () => {
        const rawQuery = query.trim();
        const normalizedQuery = normalizeGatewayIconName(rawQuery);
        const exactMatch = options.find((option) => {
            const optionIcon = normalizeGatewayIconName(option.value);

            return optionIcon.toLowerCase() === normalizedQuery.toLowerCase()
                || option.label.toLowerCase() === rawQuery.toLowerCase();
        });
        const nextIcon = normalizeGatewayIconName(exactMatch?.value ?? filteredOptions[0]?.value ?? normalizedQuery)
            || "credit-card-2-front";

        onChange(nextIcon);
        setQuery(nextIcon);
    };

    const selectIcon = (option: IconOption) => {
        const nextIcon = normalizeGatewayIconName(option.value) || "credit-card-2-front";

        onChange(nextIcon);
        setQuery(nextIcon);
        setOpen(false);
    };

    return (
        <div className={mcClasses("mc-gateway-field mc-gateway-iconpick")}>
            <label htmlFor={inputId}>{label}</label>
            <div className={mcClasses("mc-gateway-iconpick__control")}>
                <span className={mcClasses("mc-gateway-iconpick__preview")} aria-hidden="true">
                    <i className={gatewayIconClassName(normalizedValue)} />
                </span>
                <input
                    aria-autocomplete="list"
                    aria-expanded={open ? "true" : "false"}
                    aria-label={labels.selecticon ?? label}
                    autoComplete="off"
                    className={mcClasses("mc-form-control mc-gateway-iconpick__input")}
                    id={inputId}
                    onBlur={() => {
                        window.setTimeout(() => {
                            commitQuery();
                            setOpen(false);
                        }, 150);
                    }}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                    placeholder="credit-card-2-front"
                    role="combobox"
                    type="search"
                    value={query}
                />
            </div>
            {open && (
                <div className={mcClasses("mc-gateway-iconpick__menu")} role="listbox">
                    {filteredOptions.length === 0 && (
                        <div className={mcClasses("mc-gateway-iconpick__empty")}>{labels.noresults}</div>
                    )}
                    {filteredOptions.map((option) => {
                        const optionIcon = normalizeGatewayIconName(option.value);

                        return (
                            <button
                                className={mcClasses("mc-button mc-gateway-iconpick__item")}
                                data-mc-button="ghost"
                                key={optionIcon}
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    selectIcon(option);
                                }}
                                role="option"
                                type="button"
                            >
                                <i className={gatewayIconClassName(optionIcon)} aria-hidden="true" />
                                <span>{option.label}</span>
                                <code>{optionIcon}</code>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

const gatewayToForm = (gateway: Gateway): GatewayForm => ({
    gateway: gateway.gateway,
    displayname: gateway.displayname,
    displayorder: String(gateway.displayorder),
    methodtype: gateway.methodtype,
    component: gateway.component,
    classname: gateway.classname,
    icon: gateway.icon,
    publickey: gateway.publickey,
    merchantid: gateway.merchantid,
    secretkey: "",
    webhooksecret: "",
    supportedcurrencies: gateway.supportedcurrencies,
    ipwhitelist: gateway.ipwhitelist,
    enabled: gateway.enabled,
    testmode: gateway.testmode,
    supportswebhooks: gateway.supportswebhooks,
    supportsrefunds: gateway.supportsrefunds,
    supportsrecurring: gateway.supportsrecurring,
    secretconfigured: gateway.secretconfigured,
    webhooksecretconfigured: gateway.webhooksecretconfigured,
});

const emptyForm = (): GatewayForm => ({
    gateway: "",
    displayname: "",
    displayorder: "0",
    methodtype: "redirect",
    component: "local_moderncommerce",
    classname: "",
    icon: "credit-card-2-front",
    publickey: "",
    merchantid: "",
    secretkey: "",
    webhooksecret: "",
    supportedcurrencies: "",
    ipwhitelist: "",
    enabled: false,
    testmode: false,
    supportswebhooks: false,
    supportsrefunds: false,
    supportsrecurring: false,
    secretconfigured: false,
    webhooksecretconfigured: false,
});

const gatewayNeedsAttention = (gateway: Gateway): boolean => (
    !gateway.ready ||
    !gateway.currencysupported ||
    (gateway.hosted && !gateway.secretok) ||
    (gateway.supportswebhooks && !gateway.webhookok)
);

const badgeVariant = (variant: string): McBadgeVariant => {
    const variants: McBadgeVariant[] = ["primary", "secondary", "accent", "success", "warning", "danger", "info", "neutral"];
    return variants.includes(variant as McBadgeVariant) ? variant as McBadgeVariant : "neutral";
};

export default function GatewaysAdmin({
    listMethodName,
    saveMethodName,
    webhooksUrl,
    paymentEventsUrl,
    webhookEventsUrl,
    iconOptions = [],
    labels,
}: GatewaysAdminProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<ListResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [fieldErrors, setFieldErrors] = useState<FieldErrorMap>({});
    const [saving, setSaving] = useState(false);
    const [editing, setEditing] = useState("");
    const [form, setForm] = useState<GatewayForm | null>(null);
    const [reloadToken, setReloadToken] = useState(0);
    const drawerBodyRef = useRef<HTMLDivElement | null>(null);
    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<ListResponse>(listMethodName, {})
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
    }, [listMethodName, reloadToken]);

    useEffect(() => {
        const refreshButton = document.getElementById("moderncommerce-gateways-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    useEffect(() => {
        if (error && drawerBodyRef.current) {
            drawerBodyRef.current.scrollTo({top: 0, behavior: "smooth"});
        }
    }, [error]);

    const methodTypes = data?.methodtypes ?? [];
    const gateways = data?.gateways ?? [];
    const enabledCount = gateways.filter((gateway) => gateway.enabled).length;
    const readyCount = gateways.filter((gateway) => gateway.ready).length;
    const attentionCount = gateways.filter(gatewayNeedsAttention).length;
    const testModeCount = gateways.filter((gateway) => gateway.testmode).length;
    const metrics: Metric[] = [
        {label: labels.enabled, value: enabledCount, icon: "bi-toggle-on", variant: "primary"},
        {label: labels.ready, value: readyCount, icon: "bi-check2-circle", variant: "success"},
        {label: labels.attention, value: attentionCount, icon: "bi-exclamation-triangle", variant: attentionCount > 0 ? "warning" : "neutral"},
        {label: labels.testmode, value: testModeCount, icon: "bi-sliders", variant: testModeCount > 0 ? "info" : "neutral"},
    ];

    const openEdit = (gateway: Gateway) => {
        setEditing(gateway.gateway);
        setForm(gatewayToForm(gateway));
        setError("");
    };

    const openNew = () => {
        setEditing("__new__");
        setForm(emptyForm());
        setError("");
    };

    const closeForm = () => {
        setEditing("");
        setForm(null);
    };

    const updateForm = (changes: Partial<GatewayForm>) => {
        setForm((current) => current ? {...current, ...changes} : current);
    };

    const submitForm = async(event?: FormEvent<HTMLFormElement>) => {
        event?.preventDefault();
        if (!form) {
            return;
        }

        const t = (key: string) => labels[key] ?? key;
        // Credentials only matter once the gateway is switched on, so those
        // checks are conditional on `enabled`.
        const rules: RuleMap<GatewayForm> = {
            gateway: [required(t("error:required")), maxLength(64, t("error:maxlength"))],
            displayname: [required(t("error:required")), maxLength(255, t("error:maxlength"))],
            displayorder: integer(t("error:invalidinteger")),
            publickey: when((values) => Boolean(values.enabled), required(t("error:required"))),
            secretkey: when((values) => Boolean(values.enabled), required(t("error:required"))),
            webhooksecret: when(
                (values) => Boolean(values.enabled) && Boolean(values.supportswebhooks),
                required(t("error:required"))
            ),
        };
        const found = runRules(form, rules);
        setFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setError("");
            focusFirstError(found, ["gateway", "displayname", "publickey", "secretkey", "webhooksecret"]);
            return;
        }

        setSaving(true);
        setError("");

        try {
            const result = await callMoodleService<SaveResponse>(saveMethodName, {
                gateway: form.gateway,
                displayname: form.displayname,
                displayorder: Number(form.displayorder) || 0,
                methodtype: form.methodtype,
                component: form.component || "local_moderncommerce",
                classname: form.classname,
                icon: form.icon,
                publickey: form.publickey,
                merchantid: form.merchantid,
                secretkey: form.secretkey,
                webhooksecret: form.webhooksecret,
                supportedcurrencies: form.supportedcurrencies,
                ipwhitelist: form.ipwhitelist,
                enabled: form.enabled,
                testmode: form.testmode,
                supportswebhooks: form.supportswebhooks,
                supportsrefunds: form.supportsrefunds,
                supportsrecurring: form.supportsrecurring,
            });

            if (!result.success) {
                setError(result.message);
                return;
            }

            toast.success(result.message);
            closeForm();
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const renderMetric = (metric: Metric) => (
        <article className={mcClasses(`mc-stat-tile mc-stat-tile--${metric.variant}`)} key={metric.label}>
            <i className={`bi ${metric.icon} mc-stat-tile__icon`} aria-hidden="true" />
            <div className={mcClasses("mc-stat-tile__body")}>
                <span className={mcClasses("mc-stat-tile__label")}>{metric.label}</span>
                <strong className={mcClasses("mc-stat-tile__value")}>{metric.value}</strong>
            </div>
            <i className={`bi ${metric.icon} mc-stat-tile__watermark`} aria-hidden="true" />
        </article>
    );

    const renderInput = (
        key: keyof GatewayForm,
        label: string,
        type = "text",
        help = "",
        placeholder = "",
        isRequired = false
    ) => {
        const message = fieldErrors[key as string];
        const id = `mc-gateway-${String(key)}`;
        return (
            <label className={mcClasses("mc-gateway-field")} htmlFor={id}>
                <span>
                    {label}
                    {isRequired && <span className={mcClasses("mc-required")} aria-hidden="true">*</span>}
                </span>
                <input
                    aria-describedby={message ? `${id}-error` : undefined}
                    aria-invalid={message ? true : undefined}
                    aria-required={isRequired ? true : undefined}
                    autoComplete="off"
                    className={mcClasses("mc-form-control", message ? "mc-field-invalid" : "")}
                    id={id}
                    name={String(key)}
                    onChange={(event) => updateForm({[key]: event.target.value} as Partial<GatewayForm>)}
                    placeholder={placeholder}
                    type={type}
                    value={String(form?.[key] ?? "")}
                />
                {help && <small className={mcClasses("mc-cell-muted")}>{help}</small>}
                <FieldError id={`${id}-error`} message={message} />
            </label>
        );
    };

    const renderCheck = (key: keyof GatewayForm, label: string) => (
        <label className={mcClasses("mc-gateway-check mc-switch")}>
            <input
                checked={Boolean(form?.[key])}
                onChange={(event) => updateForm({[key]: event.target.checked} as Partial<GatewayForm>)}
                type="checkbox"
            />
            <span className={mcClasses("mc-switch__track")} aria-hidden="true" />
            <span className={mcClasses("mc-switch__thumb")} aria-hidden="true" />
            <span className={mcClasses("mc-switch__label")}>{label}</span>
        </label>
    );

    const renderFormContent = (isnew: boolean) => {
        if (!form) {
            return null;
        }

        return (
            <>
                <div className={mcClasses("mc-gateway-form__head")}>
                    <p>{labels.gatewayconfigurationdesc}</p>
                </div>
                {isnew && renderInput("gateway", labels.gatewayid, "text", "", "", true)}
                <div className={mcClasses("mc-gateway-section")}>
                    <h5>{labels.gatewayidentity}</h5>
                    <div className={mcClasses("mc-gateway-grid")}>
                        {renderInput("displayname", labels.displayname, "text", "", "", true)}
                        {renderInput("displayorder", labels.displayorder, "number")}
                        <label className={mcClasses("mc-gateway-field")}>
                            <span>{labels.methodtype}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateForm({methodtype: event.target.value})}
                                value={form.methodtype}
                            >
                                {methodTypes.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                        </label>
                        <GatewayIconPicker
                            value={form.icon}
                            label={labels.icon}
                            options={iconOptions}
                            labels={labels}
                            onChange={(icon) => updateForm({icon})}
                        />
                        {renderInput("component", labels.component)}
                        {renderInput("classname", labels.classname)}
                    </div>
                </div>
                <div className={mcClasses("mc-gateway-section")}>
                    <h5>{labels.apikeys}</h5>
                    <div className={mcClasses("mc-gateway-grid")}>
                        {renderInput("publickey", labels.publickey, "text", "", "", Boolean(form?.enabled))}
                        {renderInput("merchantid", labels.merchantid)}
                        {renderInput(
                            "secretkey",
                            labels.secretkey,
                            "password",
                            labels.keepblanksecret,
                            form.secretconfigured ? labels.configured : ""
                        )}
                        {renderInput("supportedcurrencies", labels.supportedcurrencies)}
                    </div>
                </div>
                <div className={mcClasses("mc-gateway-section")}>
                    <h5>{labels.capabilities}</h5>
                    <div className={mcClasses("mc-gateway-checks")}>
                        {renderCheck("enabled", labels.enabled)}
                        {renderCheck("testmode", labels.testmode)}
                        {renderCheck("supportswebhooks", labels.supportswebhooks)}
                        {renderCheck("supportsrefunds", labels.supportsrefunds)}
                        {renderCheck("supportsrecurring", labels.supportsrecurring)}
                    </div>
                </div>
                <div className={mcClasses("mc-gateway-section")}>
                    <h5>{labels.webhookconfiguration}</h5>
                    <div className={mcClasses("mc-gateway-grid")}>
                        {renderInput(
                            "webhooksecret",
                            labels.webhooksecret,
                            "password",
                            labels.keepblanksecret,
                            form.webhooksecretconfigured ? labels.configured : ""
                        )}
                        {renderInput("ipwhitelist", labels.ipwhitelist)}
                    </div>
                </div>
            </>
        );
    };

    const renderEvent = (event: EventSummary, viewurl: string) => {
        if (!event.hasevent) {
            return (
                <div className={mcClasses("mc-gateway-event mc-gateway-event--compact")}>
                    <span className={mcClasses("mc-cell-muted")}>{labels.noactivity}</span>
                </div>
            );
        }

        return (
            <div className={mcClasses("mc-gateway-event mc-gateway-event--compact")}>
                <McBadge variant={badgeVariant(event.statusclass)} tone="soft" dot>{event.status}</McBadge>
                <div className={mcClasses("mc-cell-muted small")}>{event.date}</div>
                <a className="small" href={viewurl}>{labels.viewdetails}</a>
            </div>
        );
    };

    const renderWebhookStatus = (gateway: Gateway) => {
        if (!gateway.supportswebhooks) {
            return <McBadge variant="neutral" tone="soft">{labels.disabled}</McBadge>;
        }

        return (
            <McBadge variant={gateway.webhookok ? "success" : "warning"} tone="soft" dot>
                {gateway.webhookok ? labels.configured : labels.notconfigured}
            </McBadge>
        );
    };

    const renderGatewayDrawer = () => {
        if (!form) {
            return null;
        }

        const isnew = editing === "__new__";
        const title = isnew ? labels.addcustomgateway : labels.gatewayconfiguration;
        const subtitle = form.displayname || form.gateway;

        return (
            <McDrawer
                title={title}
                subtitle={subtitle}
                onClose={closeForm}
                closeLabel={labels.close}
                disableClose={saving}
                className="mc-gateway-drawer"
                bodyClassName="mc-gateway-drawer__body"
                bodyRef={drawerBodyRef}
                footer={(
                    <>
                        <McButton
                            className={mcClasses("btn-mc-primary")}
                            loading={saving}
                            loadingLabel={labels.saving || "Saving..."}
                            form="mc-gateway-drawer-form"
                            type="submit"
                        >
                            {labels.save}
                        </McButton>
                        <button className={mcClasses("mc-button btn-mc-secondary")} disabled={saving} onClick={closeForm} type="button">
                            {labels.cancel}
                        </button>
                    </>
                )}
            >
                {error && (
                    <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                        <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{error}</div>
                    </div>
                )}
                <form id="mc-gateway-drawer-form" noValidate onSubmit={submitForm}>
                    {renderFormContent(isnew)}
                </form>
            </McDrawer>
        );
    };

    if (loading && !data) {
        return <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>;
    }

    return (
        <section className={mcClasses("mc-gateways-admin mc-gateway-console")} aria-label={labels.title}>
            {error && !form && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            {data && data.hostedready === 0 && (
                <div className={mcClasses("mc-alert mc-alert--warning")} role="status">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>
                        <strong>{labels.attention}</strong>: {labels.activecurrency} {data.activecurrency}
                    </div>
                </div>
            )}

            <div className={mcClasses("mc-gateway-console__hero")}>
                <div className={mcClasses("mc-gateway-console__intro")}>
                    <span className={mcClasses("mc-gateway-console__eyebrow")}>{labels.gatewayhealth}</span>
                    <h3>{labels.title}</h3>
                    <p>{labels.paymentgatewayssubtitle}</p>
                    <div className={mcClasses("mc-gateway-console__meta")}>
                        <span className={mcClasses("mc-badge mc-badge--primary")}>
                            {labels.activecurrency}: {data?.activecurrency}
                        </span>
                        <span className={mcClasses(`mc-badge mc-badge--${attentionCount > 0 ? "warning" : "success"}`)}>
                            {attentionCount > 0 ? `${attentionCount} ${labels.attention}` : labels.ready}
                        </span>
                    </div>
                </div>
                <div className={mcClasses("mc-gateway-console__actions")}>
                    <a className={mcClasses("mc-button mc-btn-soft")} href={paymentEventsUrl}>
                        <i className="bi bi-clock-history" aria-hidden="true" />
                        {labels.paymentevents}
                    </a>
                    <a className={mcClasses("mc-button mc-btn-soft")} href={webhookEventsUrl}>
                        <i className="bi bi-hdd-network" aria-hidden="true" />
                        {labels.webhookeventslog}
                    </a>
                    <button
                        className={mcClasses("mc-button btn-mc-primary")}
                        disabled={saving}
                        onClick={() => editing === "__new__" ? closeForm() : openNew()}
                        type="button"
                    >
                        <i className="bi bi-plus-lg" aria-hidden="true" />
                        {labels.addcustomgateway}
                    </button>
                </div>
            </div>

            <div className={mcClasses("mc-stat-strip mc-gateway-health")}>
                {metrics.map(renderMetric)}
            </div>

            <McTableCard
                className={mcClasses("mc-gateway-table-card")}
                title={<h3 className={mcClasses("mc-card-title")}>{labels.title}</h3>}
                footer={(
                    <McTableFooter
                        summary={<span>{labels.showing} {formatCount(gateways.length)} / {formatCount(gateways.length)}</span>}
                    />
                )}
            >
                <table className={mcClasses("table mc-table mc-product-table mc-gateway-table mb-0")} aria-label={labels.title}>
                    <thead>
                        <tr>
                            <th scope="col">{labels.displayname}</th>
                            <th scope="col">{labels.status}</th>
                            <th scope="col">{labels.mode}</th>
                            <th scope="col">{labels.methodtype}</th>
                            <th scope="col">{labels.activecurrencysupport}</th>
                            <th scope="col">{labels.webhookstatus}</th>
                            <th scope="col">{labels.lastpaymentevent}</th>
                            <th className="text-end" scope="col">{labels.settings}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {gateways.length === 0 && !loading && (
                            <tr>
                                <td colSpan={8}>
                                    <div className={mcClasses("mc-empty mc-empty--centered")}>
                                        <span className={mcClasses("mc-empty__icon")}>
                                            <i className="bi bi-credit-card-2-front" aria-hidden="true" />
                                        </span>
                                        <p className={mcClasses("mc-empty__title")}>{labels.nogateways}</p>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {gateways.map((gateway) => (
                            <tr
                                className={mcClasses(
                                    editing === gateway.gateway ? "is-selected" : "",
                                    gatewayNeedsAttention(gateway) ? "is-attention" : ""
                                )}
                                key={gateway.gateway}
                            >
                                <td>
                                    <div className={mcClasses("mc-gateway-table__gateway")}>
                                        <span className={mcClasses("mc-gateway-table__icon")}>
                                            <i className={gatewayIconClassName(gateway.icon)} aria-hidden="true" />
                                        </span>
                                        <span>
                                            <strong>{gateway.displayname}</strong>
                                            <small>{gateway.gateway}</small>
                                            {!gateway.ready && gateway.readinessmessage && (
                                                <em>{gateway.readinessmessage}</em>
                                            )}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <McBadge variant={gateway.enabled ? "success" : "neutral"} tone="soft" dot>
                                        {gateway.enabled ? labels.enabled : labels.disabled}
                                    </McBadge>
                                </td>
                                <td>
                                    <McBadge variant={gateway.testmode ? "warning" : "success"} tone="soft" dot>
                                        {gateway.testmode ? labels.testmode : labels.live}
                                    </McBadge>
                                </td>
                                <td>
                                    <span className={mcClasses("mc-cell-muted")}>{gateway.methodlabel}</span>
                                </td>
                                <td>
                                    <McBadge variant={gateway.currencysupported ? "success" : "warning"} tone="soft" dot>
                                        {gateway.currencysupported ? labels.ready : labels.attention}
                                    </McBadge>
                                    <div className={mcClasses("mc-cell-muted small")}>{data?.activecurrency}</div>
                                </td>
                                <td>{renderWebhookStatus(gateway)}</td>
                                <td>{renderEvent(gateway.lastpaymentevent, `${paymentEventsUrl}?gateway=${gateway.gateway}`)}</td>
                                <td className="text-end">
                                    <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                        <McTableActionMenu
                                            disabled={saving}
                                            label={`${labels.settings}: ${gateway.displayname}`}
                                            items={[
                                                {
                                                    key: "settings",
                                                    label: labels.settings,
                                                    icon: "bi bi-gear",
                                                    onClick: () => openEdit(gateway),
                                                },
                                            ]}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </McTableCard>

            {renderGatewayDrawer()}
        </section>
    );
}

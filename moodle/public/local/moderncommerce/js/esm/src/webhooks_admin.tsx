// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin webhooks for Modern Commerce.
 *
 * @module     local_moderncommerce/webhooks_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McDrawer} from "./drawer";
import {McTableActionMenu, McTableCard, McTableFooter, McTablePagination} from "./table_components";
import {integer, max, min, runRules} from "./validation";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type WebhookGateway = {
    gateway: string;
    name: string;
    webhookurl: string;
    enabled: boolean;
    secretconfigured: boolean;
    testmode: boolean;
    ipwhitelist: boolean;
    events: string[];
};

type WebhooksResponse = {
    gateways: WebhookGateway[];
    hasunconfigured: boolean;
    settingsurl: string;
};

type WebhookEvent = {
    id: number;
    gateway: string;
    eventtype: string;
    reference: string;
    status: string;
    statusclass: string;
    signatureverified: boolean;
    attemptcount: number;
    lasterror: string;
    date: string;
};

type EventsResponse = {
    events: WebhookEvent[];
    total: number;
    page: number;
    perpage: number;
};

type SettingsValues = Record<string, unknown> & {
    enable_webhook_ip_whitelist?: number | boolean;
    payment_max_retries?: number | string;
};

type SettingsResponse = {
    values: SettingsValues;
};

type SaveResponse = {
    success: boolean;
    message: string;
    errors: Array<{field: string; message: string}>;
};

type SecurityForm = {
    enable_webhook_ip_whitelist: boolean;
    payment_max_retries: string;
};

type WebhooksAdminProps = {
    getWebhooksMethodName: string;
    listEventsMethodName: string;
    getSettingsMethodName?: string;
    saveSettingsMethodName?: string;
    gatewaysUrl: string;
    settingsUrl: string;
    labels: Labels;
    showEvents?: boolean;
};

const PER_PAGE = 10;
const BADGE_VARIANTS: McBadgeVariant[] = ["primary", "secondary", "accent", "success", "warning", "danger", "info", "neutral"];
const badgeVariant = (variant?: string): McBadgeVariant => (
    BADGE_VARIANTS.includes(variant as McBadgeVariant) ? variant as McBadgeVariant : "neutral"
);

const securityFormFromValues = (values: SettingsValues): SecurityForm => ({
    enable_webhook_ip_whitelist: Boolean(Number(values.enable_webhook_ip_whitelist ?? 1)),
    payment_max_retries: String(values.payment_max_retries ?? 3),
});

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

export default function WebhooksAdmin({
    getWebhooksMethodName,
    listEventsMethodName,
    getSettingsMethodName,
    saveSettingsMethodName,
    gatewaysUrl,
    settingsUrl,
    labels,
    showEvents = true,
}: WebhooksAdminProps) {
    useModernCommerceClassSync();
    const [webhooks, setWebhooks] = useState<WebhooksResponse | null>(null);
    const [events, setEvents] = useState<EventsResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [eventsLoading, setEventsLoading] = useState(true);
    const [error, setError] = useState("");
    const [page, setPage] = useState(0);
    const [copied, setCopied] = useState("");
    const [reloadToken, setReloadToken] = useState(0);
    const [selectedGatewayId, setSelectedGatewayId] = useState("");
    const [settingsValues, setSettingsValues] = useState<SettingsValues | null>(null);
    const [securityForm, setSecurityForm] = useState<SecurityForm | null>(null);
    const [securitySaved, setSecuritySaved] = useState<SecurityForm | null>(null);
    const [securityLoading, setSecurityLoading] = useState(false);
    const [securitySaving, setSecuritySaving] = useState(false);
    const [securityError, setSecurityError] = useState("");
    const [securityFieldErrors, setSecurityFieldErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<WebhooksResponse>(getWebhooksMethodName, {})
            .then((result) => {
                if (!cancelled) {
                    setWebhooks(result);
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
    }, [getWebhooksMethodName, reloadToken]);

    useEffect(() => {
        if (!showEvents) {
            setEventsLoading(false);
            return undefined;
        }
        let cancelled = false;
        setEventsLoading(true);

        void callMoodleService<EventsResponse>(listEventsMethodName, {gateway: "", page, perpage: PER_PAGE})
            .then((result) => {
                if (!cancelled) {
                    setEvents(result);
                }
            })
            .catch((caught: unknown) => {
                if (!cancelled) {
                    setError(caught instanceof Error ? caught.message : String(caught));
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setEventsLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [listEventsMethodName, page, reloadToken, showEvents]);

    useEffect(() => {
        if (!getSettingsMethodName || !saveSettingsMethodName) {
            setSecurityLoading(false);
            return undefined;
        }

        let cancelled = false;
        setSecurityLoading(true);
        setSecurityError("");

        void callMoodleService<SettingsResponse>(getSettingsMethodName, {})
            .then((result) => {
                if (!cancelled) {
                    const nextForm = securityFormFromValues(result.values);
                    setSettingsValues(result.values);
                    setSecurityForm(nextForm);
                    setSecuritySaved(nextForm);
                    setSecurityFieldErrors({});
                }
            })
            .catch((caught: unknown) => {
                if (!cancelled) {
                    setSecurityError(caught instanceof Error ? caught.message : String(caught));
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setSecurityLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [getSettingsMethodName, saveSettingsMethodName, reloadToken]);

    useEffect(() => {
        const refreshButton = document.getElementById("moderncommerce-webhooks-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    const copyUrl = (gateway: string, url: string) => {
        void navigator.clipboard?.writeText(url).then(() => {
            setCopied(gateway);
            window.setTimeout(() => setCopied(""), 2000);
        });
    };

    const total = events?.total ?? 0;
    const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
    const visibleEventCount = events?.events.length ?? 0;
    const visibleEventFrom = total === 0 || visibleEventCount === 0 ? 0 : (page * PER_PAGE) + 1;
    const visibleEventTo = visibleEventCount === 0 ? 0 : Math.min(total, (page * PER_PAGE) + visibleEventCount);
    const gateways = webhooks?.gateways ?? [];
    const selectedGateway = selectedGatewayId
        ? gateways.find((gateway) => gateway.gateway === selectedGatewayId) ?? null
        : null;
    const viewLabel = labels.view || labels.viewdetails || "View";
    const closeLabel = labels.close || "Close";
    const actionsLabel = labels.actions || "";
    const securityDirty = Boolean(securityForm && securitySaved)
        && JSON.stringify(securityForm) !== JSON.stringify(securitySaved);

    useEffect(() => {
        if (selectedGatewayId && webhooks && !gateways.some((gateway) => gateway.gateway === selectedGatewayId)) {
            setSelectedGatewayId("");
        }
    }, [gateways, selectedGatewayId, webhooks]);

    const renderStatusPill = (gateway: WebhookGateway) => (
        <McBadge variant={gateway.enabled ? "success" : "neutral"} tone="soft" dot>
            {gateway.enabled ? labels.enabled : labels.disabled}
        </McBadge>
    );

    const renderSecretPill = (gateway: WebhookGateway) => (
        <McBadge variant={gateway.secretconfigured ? "success" : "danger"} tone="soft" dot>
            {gateway.secretconfigured ? labels.configured : labels.notconfigured}
        </McBadge>
    );

    const updateSecurityForm = (changes: Partial<SecurityForm>) => {
        setSecurityForm((current) => current ? {...current, ...changes} : current);
    };

    const discardSecuritySettings = () => {
        if (securitySaved) {
            setSecurityForm({...securitySaved});
        }
        setSecurityError("");
        setSecurityFieldErrors({});
    };

    const saveSecuritySettings = async() => {
        if (!securityForm || !settingsValues || !saveSettingsMethodName || !getSettingsMethodName) {
            return;
        }

        const clientErrors = runRules({payment_max_retries: securityForm.payment_max_retries}, {
            payment_max_retries: [
                integer(labels["error:invalidinteger"] ?? "error:invalidinteger"),
                min(0, labels["error:minvalue"] ?? "error:minvalue"),
                max(10, labels["error:maxvalue"] ?? "error:maxvalue"),
            ],
        });
        if (Object.keys(clientErrors).length > 0) {
            setSecurityFieldErrors(clientErrors);
            return;
        }

        setSecuritySaving(true);
        setSecurityError("");
        setSecurityFieldErrors({});

        const nextValues: SettingsValues = {
            ...settingsValues,
            enable_webhook_ip_whitelist: securityForm.enable_webhook_ip_whitelist ? 1 : 0,
            payment_max_retries: Number(securityForm.payment_max_retries) || 0,
        };

        try {
            const result = await callMoodleService<SaveResponse>(saveSettingsMethodName, nextValues);
            if (!result.success) {
                const map: Record<string, string> = {};
                result.errors.forEach((fieldError) => {
                    map[fieldError.field] = fieldError.message;
                });
                setSecurityFieldErrors(map);
                const firstExternalError = result.errors.find((fieldError) =>
                    fieldError.field !== "enable_webhook_ip_whitelist" && fieldError.field !== "payment_max_retries"
                );
                if (firstExternalError || result.message) {
                    setSecurityError(firstExternalError?.message || result.message);
                }
                return;
            }

            try {
                const fresh = await callMoodleService<SettingsResponse>(getSettingsMethodName, {});
                const nextForm = securityFormFromValues(fresh.values);
                setSettingsValues(fresh.values);
                setSecurityForm(nextForm);
                setSecuritySaved(nextForm);
            } catch {
                const nextForm = securityFormFromValues(nextValues);
                setSettingsValues(nextValues);
                setSecurityForm(nextForm);
                setSecuritySaved(nextForm);
            }

            setReloadToken((current) => current + 1);
            toast.success(result.message);
        } catch (caught) {
            setSecurityError(caught instanceof Error ? caught.message : String(caught));
            toast.error(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSecuritySaving(false);
        }
    };

    const renderSecuritySettings = () => {
        if (!getSettingsMethodName || !saveSettingsMethodName) {
            return null;
        }

        return (
            <div className={mcClasses("mc-card mc-webhook-security-card")}>
                <div className={mcClasses("mc-card-header mc-webhook-security-card__header")}>
                    <div>
                        <h3 className={mcClasses("mc-card-title")}>{labels.paymentsecurity}</h3>
                        <p className={mcClasses("mc-cell-muted")}>{labels.paymentsecuritydesc}</p>
                    </div>
                </div>
                <div className={mcClasses("mc-card-body")}>
                    {securityError && (
                        <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                            <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                            <div className={mcClasses("mc-alert__body")}>{securityError}</div>
                        </div>
                    )}
                    {securityLoading && !securityForm && (
                        <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                    )}
                    {securityForm && (
                        <>
                            <div className={mcClasses("mc-settings-switchrow")}>
                                <label className={mcClasses("mc-switch")}>
                                    <input
                                        checked={securityForm.enable_webhook_ip_whitelist}
                                        disabled={securitySaving || securityLoading}
                                        onChange={(event) => updateSecurityForm({
                                            enable_webhook_ip_whitelist: event.target.checked,
                                        })}
                                        type="checkbox"
                                    />
                                    <span className={mcClasses("mc-switch__track")} />
                                    <span className={mcClasses("mc-switch__thumb")} />
                                    <span className={mcClasses("mc-switch__label")}>
                                        {labels.enablewebhookipwhitelist}
                                    </span>
                                </label>
                                {securityFieldErrors.enable_webhook_ip_whitelist && (
                                    <div className={mcClasses("mc-field-error")} role="alert">
                                        <i
                                            className={mcClasses("bi bi-exclamation-circle mc-field-error__icon")}
                                            aria-hidden="true"
                                        />
                                        <span>{securityFieldErrors.enable_webhook_ip_whitelist}</span>
                                    </div>
                                )}
                            </div>
                            <div className={mcClasses("mc-product-form__grid mc-webhook-security-card__grid")}>
                                <label htmlFor="mc-webhook-payment-max-retries">
                                    <span>{labels.paymentmaxretries}</span>
                                    <input
                                        aria-describedby={securityFieldErrors.payment_max_retries
                                            ? "mc-webhook-payment-max-retries-error" : undefined}
                                        aria-invalid={securityFieldErrors.payment_max_retries ? true : undefined}
                                        className={mcClasses(
                                            "mc-form-control",
                                            securityFieldErrors.payment_max_retries ? "mc-field-invalid" : ""
                                        )}
                                        disabled={securitySaving || securityLoading}
                                        id="mc-webhook-payment-max-retries"
                                        max="10"
                                        min="0"
                                        onChange={(event) => updateSecurityForm({
                                            payment_max_retries: event.target.value,
                                        })}
                                        step="1"
                                        type="number"
                                        value={securityForm.payment_max_retries}
                                    />
                                    {securityFieldErrors.payment_max_retries && (
                                        <div
                                            className={mcClasses("mc-field-error")}
                                            id="mc-webhook-payment-max-retries-error"
                                            role="alert"
                                        >
                                            <i
                                                className={mcClasses("bi bi-exclamation-circle mc-field-error__icon")}
                                                aria-hidden="true"
                                            />
                                            <span>{securityFieldErrors.payment_max_retries}</span>
                                        </div>
                                    )}
                                </label>
                            </div>
                        </>
                    )}
                </div>
                <div className={mcClasses("mc-card-footer mc-webhook-security-card__footer")}>
                    <span className={mcClasses("mc-settings-footer__status")}>
                        {securityDirty && (
                            <>
                                <span className={mcClasses("mc-settings-dirty-dot")} aria-hidden="true" />
                                {labels.unsaved}
                            </>
                        )}
                    </span>
                    <McButton
                        variant="soft"
                        className={mcClasses("mc-btn-soft")}
                        disabled={securitySaving || !securityDirty}
                        onClick={discardSecuritySettings}
                        type="button"
                    >
                        {labels.discard}
                    </McButton>
                    <McButton
                        variant="primary"
                        className={mcClasses("btn-mc-primary")}
                        disabled={!securityDirty}
                        loading={securitySaving}
                        loadingLabel={labels.saving || "Saving..."}
                        onClick={saveSecuritySettings}
                        type="button"
                    >
                        {labels.save}
                    </McButton>
                </div>
            </div>
        );
    };

    const renderGatewayDrawer = () => {
        if (!selectedGateway) {
            return null;
        }

        return (
            <McDrawer
                title={selectedGateway.name}
                subtitle={selectedGateway.gateway}
                onClose={() => setSelectedGatewayId("")}
                closeLabel={closeLabel}
                className="mc-webhook-drawer"
                bodyClassName="mc-webhook-drawer__body"
                footer={(
                    <McButton
                        variant="secondary"
                        className={mcClasses("btn-mc-secondary")}
                        onClick={() => setSelectedGatewayId("")}
                        type="button"
                    >
                        {closeLabel}
                    </McButton>
                )}
            >
                        <div className={mcClasses("mc-webhook-drawer__section")}>
                            <label
                                className={mcClasses("mc-filter-label")}
                                htmlFor={`drawer-url-${selectedGateway.gateway}`}
                            >
                                {labels.webhookurl}
                            </label>
                            <div className={mcClasses("mc-webhook-url-line")}>
                                <input
                                    className={mcClasses("mc-form-control mc-cell-mono")}
                                    id={`drawer-url-${selectedGateway.gateway}`}
                                    readOnly
                                    type="text"
                                    value={selectedGateway.webhookurl}
                                />
                                <McButton
                                    variant="soft"
                                    className={mcClasses("mc-btn-soft text-nowrap")}
                                    onClick={() => copyUrl(selectedGateway.gateway, selectedGateway.webhookurl)}
                                    type="button"
                                >
                                    {copied === selectedGateway.gateway ? labels.copied : labels.copy}
                                </McButton>
                            </div>
                        </div>

                        <div className={mcClasses("mc-webhook-status-grid")}>
                            <div>
                                <span>{labels.status}</span>
                                <strong>{selectedGateway.enabled ? labels.enabled : labels.disabled}</strong>
                            </div>
                            <div>
                                <span>{labels.secretconfigured}</span>
                                <strong>{selectedGateway.secretconfigured ? labels.configured : labels.notconfigured}</strong>
                            </div>
                            <div>
                                <span>{labels.testmode}</span>
                                <strong>{selectedGateway.testmode ? labels.enabled : labels.disabled}</strong>
                            </div>
                            <div>
                                <span>{labels.ipwhitelistactive}</span>
                                <strong>{selectedGateway.ipwhitelist ? labels.enabled : labels.disabled}</strong>
                            </div>
                        </div>

                        <div className={mcClasses("mc-webhook-drawer__section")}>
                            <div className={mcClasses("mc-filter-label")}>{labels.webhookevents}</div>
                            <div className={mcClasses("mc-webhook-event-list")}>
                                {selectedGateway.events.length > 0 ? (
                                    selectedGateway.events.map((event) => (
                                        <McBadge variant="neutral" tone="soft" className={mcClasses("mc-cell-mono")} key={event}>
                                            {event}
                                        </McBadge>
                                    ))
                                ) : (
                                    <span className={mcClasses("mc-cell-muted")}>-</span>
                                )}
                            </div>
                        </div>
            </McDrawer>
        );
    };

    return (
        <section className={mcClasses("mc-webhooks-admin")} aria-label={labels.title}>
            {renderSecuritySettings()}

            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            {webhooks?.hasunconfigured && (
                <div className={mcClasses("mc-alert mc-alert--warning")} role="status">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{labels.securitywarning}</div>
                </div>
            )}

            {loading && !webhooks && (
                <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
            )}

            <McTableCard
                className={mcClasses("mc-webhook-table-card")}
                title={(
                    <div className={mcClasses("mc-webhook-table-card__heading")}>
                        <span className={mcClasses("mc-card-title")}>{labels.setupinstructions}</span>
                        <span className={mcClasses("mc-cell-muted")}>{labels.title}</span>
                    </div>
                )}
                actions={(
                    <a className={mcClasses("mc-button mc-btn-soft")} href={gatewaysUrl}>
                        <i className="bi bi-credit-card-2-front me-1" aria-hidden="true" />
                        {labels.paymentgateways}
                    </a>
                )}
                footer={(
                    <McTableFooter
                        summary={(
                            <span>
                                {labels.showing} {gateways.length === 0 ? "0" : `1-${gateways.length}`} / {gateways.length}
                            </span>
                        )}
                    />
                )}
            >
                        <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.title}>
                            <thead>
                                <tr>
                                    <th scope="col">{labels.gateway}</th>
                                    <th scope="col">{labels.status}</th>
                                    <th scope="col">{labels.secretconfigured}</th>
                                    <th scope="col">{labels.testmode}</th>
                                    <th scope="col">{labels.ipwhitelistactive}</th>
                                    <th scope="col">{labels.webhookevents}</th>
                                    <th scope="col" className="text-end">{actionsLabel}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {!loading && gateways.length === 0 && (
                                    <tr>
                                        <td colSpan={7}>
                                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                <span className={mcClasses("mc-empty__icon")}>
                                                    <i className="bi bi-hdd-network" aria-hidden="true" />
                                                </span>
                                                <p className={mcClasses("mc-empty__title")}>{labels.noevents}</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {gateways.map((gateway) => (
                                    <tr key={gateway.gateway}>
                                        <td>
                                            <div className={mcClasses("mc-webhook-table__gateway")}>
                                                <strong>{gateway.name}</strong>
                                                <span className={mcClasses("mc-cell-muted mc-cell-mono")}>{gateway.gateway}</span>
                                            </div>
                                        </td>
                                        <td>{renderStatusPill(gateway)}</td>
                                        <td>{renderSecretPill(gateway)}</td>
                                        <td>
                                            <McBadge variant={gateway.testmode ? "warning" : "neutral"} tone="soft" dot>
                                                {gateway.testmode ? labels.enabled : labels.disabled}
                                            </McBadge>
                                        </td>
                                        <td>
                                            <McBadge variant={gateway.ipwhitelist ? "info" : "neutral"} tone="soft" dot>
                                                {gateway.ipwhitelist ? labels.enabled : labels.disabled}
                                            </McBadge>
                                        </td>
                                        <td>
                                            <div className={mcClasses("mc-webhook-table__events")}>
                                                {gateway.events.slice(0, 2).map((event) => (
                                                    <McBadge variant="neutral" tone="soft" className={mcClasses("mc-cell-mono")} key={event}>
                                                        {event}
                                                    </McBadge>
                                                ))}
                                                {gateway.events.length > 2 && (
                                                    <McBadge variant="neutral" tone="soft">
                                                        +{gateway.events.length - 2}
                                                    </McBadge>
                                                )}
                                                {gateway.events.length === 0 && (
                                                    <span className={mcClasses("mc-cell-muted")}>-</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="text-end">
                                            <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                                <McTableActionMenu
                                                    label={`${actionsLabel || viewLabel}: ${gateway.name}`}
                                                    items={[
                                                        {
                                                            key: "view",
                                                            label: viewLabel,
                                                            icon: "bi bi-eye",
                                                            onClick: () => setSelectedGatewayId(gateway.gateway),
                                                        },
                                                    ]}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {loading && (
                                    <tr>
                                        <td colSpan={7}>
                                            <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
            </McTableCard>

            {renderGatewayDrawer()}

            {showEvents && (
            <McTableCard
                className={mcClasses("mt-4")}
                title={<span className={mcClasses("mc-card-title")}>{labels.recentwebhookevents}</span>}
                actions={(
                    <a className={mcClasses("mc-button btn-mc-primary")} href={settingsUrl}>
                        <i className="bi bi-gear me-1" aria-hidden="true" />
                        {labels.commercesettings}
                    </a>
                )}
                footer={(
                    <McTableFooter
                        summary={(
                            <span>
                                {labels.showing} {visibleEventFrom}-{visibleEventTo} / {total}
                            </span>
                        )}
                        pagination={totalPages > 1 ? (
                            <McTablePagination
                                previousLabel={labels.previous}
                                nextLabel={labels.next}
                                pageLabel={labels.page}
                                page={page + 1}
                                totalPages={totalPages}
                                previousDisabled={eventsLoading || page <= 0}
                                nextDisabled={eventsLoading || page + 1 >= totalPages}
                                onPrevious={() => setPage((current) => Math.max(0, current - 1))}
                                onNext={() => setPage((current) => current + 1)}
                            />
                        ) : undefined}
                    />
                )}
            >
                    <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.recentwebhookevents}>
                        <thead>
                            <tr>
                                <th scope="col">{labels.gateway}</th>
                                <th scope="col">{labels.eventtype}</th>
                                <th scope="col">{labels.reference}</th>
                                <th scope="col">{labels.status}</th>
                                <th scope="col" className="text-end">{labels.attempts}</th>
                                <th scope="col">{labels.date}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {!eventsLoading && events?.events.length === 0 && (
                                <tr>
                                    <td colSpan={6}>
                                        <div className={mcClasses("mc-empty mc-empty--centered")}>
                                            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-hdd-network" aria-hidden="true" /></span>
                                            <p className={mcClasses("mc-empty__title")}>{labels.noevents}</p>
                                        </div>
                                    </td>
                                </tr>
                            )}
                            {events?.events.map((event) => (
                                <tr key={event.id}>
                                    <td>{event.gateway}</td>
                                    <td className={mcClasses("mc-cell-mono")}>{event.eventtype || "-"}</td>
                                    <td className={mcClasses("mc-cell-mono")}>{event.reference || "-"}</td>
                                    <td>
                                        <McBadge variant={badgeVariant(event.statusclass)} tone="soft" dot>
                                            {event.status}
                                        </McBadge>
                                        {!event.signatureverified && (
                                            <div className={mcClasses("mc-cell-muted small")}>{labels.signatureverified}: -</div>
                                        )}
                                        {event.lasterror && (
                                            <div className="text-danger small">{labels.lasterror}: {event.lasterror}</div>
                                        )}
                                    </td>
                                    <td className="text-end">{event.attemptcount}</td>
                                    <td className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{event.date}</td>
                                </tr>
                            ))}
                            {eventsLoading && (
                                <tr>
                                    <td colSpan={6}>
                                        <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
            </McTableCard>
            )}
        </section>
    );
}

// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin dashboard for Modern Notify inside the Modern Commerce shell.
 *
 * @module     local_moderncommerce/notifications_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import type {FormEvent} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {McButton} from "./button";
import {FieldError} from "./form";
import {focusFirstError, integer, min, required, runRules, url as urlRule, when} from "./validation";
import type {FieldErrorMap} from "./validation";
import {McTableCard, McTableFooter, McTablePagination} from "./table_components";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type Methods = {
    get: string;
    save: string;
};

type Stats = {
    pending: number;
    processing: number;
    sent: number;
    failed: number;
    suppressed: number;
    cancelled: number;
    logtotal: number;
};

type Settings = {
    batchsize: number;
    slack_enabled: boolean;
    slack_url: string;
    slack_secret_set: boolean;
    teams_enabled: boolean;
    teams_url: string;
    teams_secret_set: boolean;
};

type NotificationLog = {
    id: number;
    timecreated: number;
    displaytime: string;
    event: string;
    component: string;
    eventkey: string;
    channel: string;
    recipient: string;
    result: string;
    resultclass: string;
    error: string;
};

type NotificationsResponse = {
    installed: boolean;
    stats: Stats;
    settings: Settings;
    logs: NotificationLog[];
    logtotal: number;
    page: number;
    perpage: number;
    channels: string[];
    results: string[];
};

type SaveResponse = {
    success: boolean;
    message: string;
    settings: Settings;
};

type FormState = {
    batchsize: string;
    slack_enabled: boolean;
    slack_url: string;
    slack_secret: string;
    teams_enabled: boolean;
    teams_url: string;
    teams_secret: string;
    slack_secret_set: boolean;
    teams_secret_set: boolean;
};

type Props = {
    methods: Methods;
    settingsUrl: string;
    labels: Labels;
};

type Filters = {
    search: string;
    channel: string;
    result: string;
    page: number;
    perpage: number;
};

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

const emptyStats: Stats = {
    pending: 0,
    processing: 0,
    sent: 0,
    failed: 0,
    suppressed: 0,
    cancelled: 0,
    logtotal: 0,
};

const emptySettings: Settings = {
    batchsize: 100,
    slack_enabled: false,
    slack_url: "",
    slack_secret_set: false,
    teams_enabled: false,
    teams_url: "",
    teams_secret_set: false,
};

const settingsToForm = (settings: Settings): FormState => ({
    batchsize: String(settings.batchsize || 100),
    slack_enabled: Boolean(settings.slack_enabled),
    slack_url: settings.slack_url || "",
    slack_secret: "",
    teams_enabled: Boolean(settings.teams_enabled),
    teams_url: settings.teams_url || "",
    teams_secret: "",
    slack_secret_set: Boolean(settings.slack_secret_set),
    teams_secret_set: Boolean(settings.teams_secret_set),
});

const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php`
        + `?sesskey=${encodeURIComponent(M.cfg.sesskey)}`
        + `&info=${encodeURIComponent(methodName)}`;
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

const resultClass = (resultclass: string): McBadgeVariant => {
    if (["success", "danger", "warning", "neutral"].includes(resultclass)) {
        return resultclass as McBadgeVariant;
    }

    return "neutral";
};

const optionLabel = (value: string): string => {
    const label = value.replace(/_/g, " ").trim();
    if (label === "") {
        return value;
    }

    return label.charAt(0).toUpperCase() + label.slice(1);
};

type ChannelCardProps = {
    icon: string;
    name: string;
    desc?: string;
    enabled: boolean;
    toggleLabel: string;
    onToggle: (checked: boolean) => void;
    urlLabel: string;
    urlDesc?: string;
    url: string;
    onUrl: (value: string) => void;
    secretLabel: string;
    secretDesc?: string;
    secret: string;
    onSecret: (value: string) => void;
    secretSet: boolean;
    secretPlaceholder: string;
    disabled: boolean;
    urlError?: string;
    urlId: string;
};

// One operational webhook channel (Slack / Teams). Header carries the brand and a
// toggle; the webhook URL + signing secret stack below with helper text. Shared
// markup so both channels — and any future channel — stay visually identical.
function ChannelCard({
    icon,
    name,
    desc,
    enabled,
    toggleLabel,
    onToggle,
    urlLabel,
    urlDesc,
    url,
    onUrl,
    secretLabel,
    secretDesc,
    secret,
    onSecret,
    secretSet,
    secretPlaceholder,
    disabled,
    urlError,
    urlId,
}: ChannelCardProps) {
    return (
        <section className={mcClasses("mc-channel-card", enabled && "mc-channel-card--active")}>
            <div className={mcClasses("mc-channel-card__head")}>
                <span className={mcClasses("mc-channel-card__brand")}>
                    <i className={`bi ${icon} mc-channel-card__icon`} aria-hidden="true" />
                    <span className={mcClasses("mc-channel-card__name")}>{name}</span>
                </span>
                <label className={mcClasses("mc-switch")}>
                    <input
                        checked={enabled}
                        disabled={disabled}
                        onChange={(event) => onToggle(event.target.checked)}
                        type="checkbox"
                    />
                    <span className={mcClasses("mc-switch__track")} aria-hidden="true" />
                    <span className={mcClasses("mc-switch__thumb")} aria-hidden="true" />
                    <span className="visually-hidden">{toggleLabel}</span>
                </label>
            </div>
            {desc && <p className={mcClasses("mc-channel-card__desc")}>{desc}</p>}
            <div className={mcClasses("mc-channel-card__fields")}>
                <label htmlFor={urlId}>
                    <span className={mcClasses("mc-field-label")}>
                        {urlLabel}
                        {enabled && <span className={mcClasses("mc-required")} aria-hidden="true">*</span>}
                    </span>
                    <input
                        aria-describedby={urlError ? `${urlId}-error` : undefined}
                        aria-invalid={urlError ? true : undefined}
                        aria-required={enabled ? true : undefined}
                        className={mcClasses("mc-form-control", urlError ? "mc-field-invalid" : "")}
                        disabled={disabled}
                        id={urlId}
                        name={urlId}
                        onChange={(event) => onUrl(event.target.value)}
                        type="url"
                        value={url}
                    />
                    {urlDesc && <small className={mcClasses("mc-field-hint")}>{urlDesc}</small>}
                    <FieldError id={`${urlId}-error`} message={urlError} />
                </label>
                <label>
                    <span className={mcClasses("mc-field-label")}>{secretLabel}</span>
                    <input
                        autoComplete="new-password"
                        className={mcClasses("mc-form-control")}
                        disabled={disabled}
                        onChange={(event) => onSecret(event.target.value)}
                        placeholder={secretSet ? secretPlaceholder : ""}
                        type="password"
                        value={secret}
                    />
                    {secretDesc && <small className={mcClasses("mc-field-hint")}>{secretDesc}</small>}
                </label>
            </div>
        </section>
    );
}

export default function NotificationsAdmin({methods, settingsUrl, labels}: Props) {
    useModernCommerceClassSync();

    const [data, setData] = useState<NotificationsResponse | null>(null);
    const [form, setForm] = useState<FormState>(() => settingsToForm(emptySettings));
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const [fieldErrors, setFieldErrors] = useState<FieldErrorMap>({});
    const [reloadToken, setReloadToken] = useState(0);
    const [filters, setFilters] = useState<Filters>({
        search: "",
        channel: "",
        result: "",
        page: 0,
        perpage: 10,
    });
    const [searchInput, setSearchInput] = useState("");

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setFilters((current) => current.search === searchInput
                ? current
                : {...current, search: searchInput, page: 0});
        }, 350);

        return () => window.clearTimeout(timer);
    }, [searchInput]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<NotificationsResponse>(methods.get, {
            search: filters.search,
            channel: filters.channel,
            result: filters.result,
            page: filters.page,
            perpage: filters.perpage,
        })
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                    setForm(settingsToForm(result.settings));
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
    }, [methods.get, filters, reloadToken]);

    useEffect(() => {
        const refreshButton = document.getElementById("moderncommerce-notifications-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    const updateForm = (changes: Partial<FormState>) => {
        setForm((current) => ({...current, ...changes}));
    };

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({...current, ...changes, page: changes.page ?? 0}));
    };

    const submit = async(event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const t = (key: string) => labels[key] ?? key;
        // An enabled channel with no endpoint fails silently at delivery time,
        // so the URL is only required once its toggle is on.
        const found = runRules(form, {
            batchsize: [integer(t("error:invalidinteger")), min(1, t("error:minvalue"))],
            slack_url: [
                when((values) => Boolean(values.slack_enabled), required(t("error:required"))),
                urlRule(t("error:invalidurl"), {requireHttp: true}),
            ],
            teams_url: [
                when((values) => Boolean(values.teams_enabled), required(t("error:required"))),
                urlRule(t("error:invalidurl"), {requireHttp: true}),
            ],
        });
        setFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setError("");
            focusFirstError(found, ["batchsize", "slack_url", "teams_url"]);
            return;
        }

        setSaving(true);
        setError("");

        try {
            const result = await callMoodleService<SaveResponse>(methods.save, {
                batchsize: Number(form.batchsize) || 100,
                slack_enabled: form.slack_enabled,
                slack_url: form.slack_url,
                slack_secret: form.slack_secret,
                teams_enabled: form.teams_enabled,
                teams_url: form.teams_url,
                teams_secret: form.teams_secret,
            });
            if (!result.success) {
                setError(result.message);
                return;
            }

            toast.success(result.message);
            setForm(settingsToForm(result.settings));
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const stats = data?.stats ?? emptyStats;
    const logs = data?.logs ?? [];
    const total = data?.logtotal ?? 0;
    const currentPage = data?.page ?? filters.page;
    const currentPerPage = data?.perpage ?? filters.perpage;
    const totalPages = Math.max(1, Math.ceil(total / currentPerPage));
    const range = getVisibleRange(total, currentPage, currentPerPage);
    const channels = data?.channels ?? [];
    const results = data?.results ?? [];

    if (!loading && data && !data.installed) {
        return (
            <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
                <div className={mcClasses("mc-card")}>
                    <div className={mcClasses("mc-card-body")}>
                        <div className={mcClasses("mc-empty mc-empty--centered")}>
                            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-bell" aria-hidden="true" /></span>
                            <p className={mcClasses("mc-empty__title")}>{labels.addonmissing}</p>
                            <p className={mcClasses("mc-empty__text")}>{labels.addonmissingdesc}</p>
                        </div>
                    </div>
                </div>
            </section>
        );
    }

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            <div className={mcClasses("mc-stat-strip")} aria-label={labels.title}>
                <article className={mcClasses("mc-stat-tile mc-stat-tile--primary")}>
                    <i className="bi bi-hourglass-split mc-stat-tile__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-stat-tile__body")}>
                        <span className={mcClasses("mc-stat-tile__label")}>{labels.pending}</span>
                        <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.pending)}</strong>
                    </div>
                    <i className="bi bi-hourglass-split mc-stat-tile__watermark" aria-hidden="true" />
                </article>
                <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                    <i className="bi bi-send-check mc-stat-tile__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-stat-tile__body")}>
                        <span className={mcClasses("mc-stat-tile__label")}>{labels.sent}</span>
                        <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.sent)}</strong>
                    </div>
                    <i className="bi bi-send-check mc-stat-tile__watermark" aria-hidden="true" />
                </article>
                <article className={mcClasses("mc-stat-tile mc-stat-tile--danger")}>
                    <i className="bi bi-exclamation-triangle mc-stat-tile__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-stat-tile__body")}>
                        <span className={mcClasses("mc-stat-tile__label")}>{labels.failed}</span>
                        <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.failed)}</strong>
                    </div>
                    <i className="bi bi-exclamation-triangle mc-stat-tile__watermark" aria-hidden="true" />
                </article>
                <article className={mcClasses("mc-stat-tile mc-stat-tile--warning")}>
                    <i className="bi bi-shield-x mc-stat-tile__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-stat-tile__body")}>
                        <span className={mcClasses("mc-stat-tile__label")}>{labels.suppressed}</span>
                        <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.suppressed)}</strong>
                    </div>
                    <i className="bi bi-shield-x mc-stat-tile__watermark" aria-hidden="true" />
                </article>
            </div>

            <form className={mcClasses("mc-card")} onSubmit={submit}>
                <div className={mcClasses("mc-card-header")}>
                    <div>
                        <h3 className={mcClasses("mc-card-title")}>{labels.channelsettings}</h3>
                        {labels.channelsettingsdesc && (
                            <p className={mcClasses("mc-cell-muted small mb-0")}>{labels.channelsettingsdesc}</p>
                        )}
                    </div>
                    {settingsUrl !== "" && (
                        <a className={mcClasses("mc-button mc-btn-soft")} href={settingsUrl}>
                            <i className="bi bi-sliders me-1" aria-hidden="true" />
                            <span>{labels.settings}</span>
                        </a>
                    )}
                </div>
                <div className={mcClasses("mc-card-body")}>
                    <div className={mcClasses("mc-channel-settings")}>
                        <div>
                            <h4 className={mcClasses("mc-settings-block__title")}>{labels.deliveryheading || labels.batchsize}</h4>
                            {labels.deliveryheadingdesc && (
                                <p className={mcClasses("mc-settings-block__desc")}>{labels.deliveryheadingdesc}</p>
                            )}
                            <label className={mcClasses("mc-settings-block__field")}>
                                <span className={mcClasses("mc-field-label")}>{labels.batchsize}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    min="1"
                                    onChange={(event) => updateForm({batchsize: event.target.value})}
                                    type="number"
                                    value={form.batchsize}
                                />
                                {labels.batchsizedesc && (
                                    <small className={mcClasses("mc-field-hint")}>{labels.batchsizedesc}</small>
                                )}
                            </label>
                        </div>

                        <div className={mcClasses("mc-channel-grid")}>
                            <ChannelCard
                                desc={labels.slackheadingdesc}
                                disabled={loading}
                                enabled={form.slack_enabled}
                                icon="bi-slack"
                                name={labels.slackheading || "Slack"}
                                onSecret={(value) => updateForm({slack_secret: value})}
                                onToggle={(checked) => updateForm({slack_enabled: checked})}
                                onUrl={(value) => updateForm({slack_url: value})}
                                secret={form.slack_secret}
                                secretDesc={labels.signingsecretdesc}
                                secretLabel={labels.signingsecret}
                                secretPlaceholder={labels.secretconfigured}
                                secretSet={form.slack_secret_set}
                                toggleLabel={labels.slackenabled}
                                url={form.slack_url}
                                urlDesc={labels.slackurldesc}
                                urlError={fieldErrors.slack_url}
                                urlId="slack_url"
                                urlLabel={labels.webhookurl}
                            />
                            <ChannelCard
                                desc={labels.teamsheadingdesc}
                                disabled={loading}
                                enabled={form.teams_enabled}
                                icon="bi-microsoft-teams"
                                name={labels.teamsheading || "Microsoft Teams"}
                                onSecret={(value) => updateForm({teams_secret: value})}
                                onToggle={(checked) => updateForm({teams_enabled: checked})}
                                onUrl={(value) => updateForm({teams_url: value})}
                                secret={form.teams_secret}
                                secretDesc={labels.signingsecretdesc}
                                secretLabel={labels.signingsecret}
                                secretPlaceholder={labels.secretconfigured}
                                secretSet={form.teams_secret_set}
                                toggleLabel={labels.teamsenabled}
                                url={form.teams_url}
                                urlDesc={labels.teamsurldesc}
                                urlError={fieldErrors.teams_url}
                                urlId="teams_url"
                                urlLabel={labels.webhookurl}
                            />
                        </div>
                    </div>
                </div>
                <div className={mcClasses("mc-card-footer")}>
                    <McButton
                        variant="primary"
                        className={mcClasses("btn-mc-primary")}
                        disabled={loading}
                        loading={saving}
                        loadingLabel={labels.saving || "Saving..."}
                        type="submit"
                    >
                        {labels.savechanges}
                    </McButton>
                </div>
            </form>

            <McTableCard
                title={<h3 className={mcClasses("mc-card-title")}>{labels.recentactivity}</h3>}
                toolbar={(
                    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                        <div className={mcClasses("mc-product-toolbar__search")}>
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-notifications-search">{labels.search}</label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-notifications-search"
                                onChange={(event) => setSearchInput(event.target.value)}
                                placeholder={labels.search}
                                type="search"
                                value={searchInput}
                            />
                        </div>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.channel}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({channel: event.target.value})}
                                value={filters.channel}
                            >
                                <option value="">{labels.allchannels}</option>
                                {channels.map((channel) => (
                                    <option key={channel} value={channel}>{optionLabel(channel)}</option>
                                ))}
                            </select>
                        </label>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.result}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({result: event.target.value})}
                                value={filters.result}
                            >
                                <option value="">{labels.allstatuses}</option>
                                {results.map((result) => (
                                    <option key={result} value={result}>{optionLabel(result)}</option>
                                ))}
                            </select>
                        </label>
                        <label className={mcClasses("mc-table-design-page-size")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.perpage}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({perpage: Number(event.target.value) || 10})}
                                value={filters.perpage}
                            >
                                {PER_PAGE_OPTIONS.map((option) => (
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
                                page={Math.min(currentPage + 1, totalPages)}
                                totalPages={totalPages}
                                previousDisabled={loading || currentPage <= 0}
                                nextDisabled={loading || currentPage + 1 >= totalPages}
                                onPrevious={() => updateFilters({page: Math.max(0, currentPage - 1)})}
                                onNext={() => updateFilters({page: currentPage + 1})}
                            />
                        )}
                    />
                )}
            >
                <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.recentactivity}>
                    <thead>
                        <tr>
                            <th scope="col">{labels.time}</th>
                            <th scope="col">{labels.event}</th>
                            <th scope="col">{labels.channel}</th>
                            <th scope="col">{labels.recipient}</th>
                            <th scope="col">{labels.result}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading && (
                            <tr>
                                <td colSpan={5}>
                                    <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                                </td>
                            </tr>
                        )}
                        {!loading && logs.length === 0 && (
                            <tr>
                                <td colSpan={5}>
                                    <div className={mcClasses("mc-empty mc-empty--centered")}>
                                        <span className={mcClasses("mc-empty__icon")}><i className="bi bi-bell" aria-hidden="true" /></span>
                                        <p className={mcClasses("mc-empty__title")}>{labels.nodeliveries}</p>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {logs.map((log) => (
                            <tr key={log.id}>
                                <td className={mcClasses("mc-cell-nowrap mc-cell-muted")}>{log.displaytime}</td>
                                <td>
                                    <div>{log.event}</div>
                                    {log.error !== "" && <div className={mcClasses("mc-cell-muted small")}>{log.error}</div>}
                                </td>
                                <td><McBadge variant="neutral" tone="soft">{log.channel}</McBadge></td>
                                <td>{log.recipient || "-"}</td>
                                <td>
                                    <McBadge variant={resultClass(log.resultclass)} tone="soft" dot>
                                        {log.result}
                                    </McBadge>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </McTableCard>
        </section>
    );
}

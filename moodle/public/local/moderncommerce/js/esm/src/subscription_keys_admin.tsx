// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin manager for subscription enrolment keys (Modern Commerce shell,
 * Modern Commerce core webservice endpoints).
 *
 * @module     local_moderncommerce/subscription_keys_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import type {FormEvent} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {McDrawer} from "./drawer";
import {McTableActionMenu, McTableCard, McTableFooter, McTablePagination} from "./table_components";
import {confirmDialog} from "./modal";
import {FieldError} from "./form";
import {afterField, focusFirstError, integer, max, min, requiredId, runRules} from "./validation";
import type {FieldErrorMap} from "./validation";

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

type Plan = {
    id: number;
    name: string;
    billingcycle: string;
    displayprice: string;
};

type Filters = {
    search: string;
    status: string;
    planid: number;
    page: number;
    perpage: number;
};

type Key = {
    id: number;
    keycode: string;
    planid: number;
    planname: string;
    value: number;
    displayvalue: string;
    currency: string;
    durationdays: number;
    maxuses: number;
    usedcount: number;
    maxusesperuser: number;
    batchid: string;
    batchname: string;
    status: string;
    startdate: number;
    expirydate: number;
    notes: string;
    timecreated: number;
    timemodified: number;
};

type Stats = {
    total: number;
    active: number;
    disabled: number;
    used: number;
};

type ListResponse = {
    items: Key[];
    total: number;
    page: number;
    perpage: number;
    stats: Stats;
    plans: Plan[];
};

type GenerateResponse = {
    success: boolean;
    generated: number;
    batchid: string;
    keycodes: string[];
    message: string;
};

type ActionResponse = {
    success: boolean;
    message: string;
};

type GenerateForm = {
    planid: number;
    quantity: string;
    value: string;
    durationdays: string;
    maxuses: string;
    maxusesperuser: string;
    validfrom: string;
    validuntil: string;
    batchname: string;
    notes: string;
};

type SubscriptionKeysAdminProps = {
    listMethodName: string;
    generateMethodName: string;
    actionMethodName: string;
    statusOptions: SelectOption[];
    perPageOptions: number[];
    labels: Labels;
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

const formatCount = (value: number): string => new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);

const getVisibleRange = (total: number, page: number, perpage: number): {from: number; to: number} => {
    if (total <= 0) {
        return {from: 0, to: 0};
    }

    const from = page * perpage + 1;
    const to = Math.min(total, from + perpage - 1);

    return {from, to};
};

const dateToTimestamp = (value: string): number => {
    if (!value) {
        return 0;
    }
    const parsed = Date.parse(`${value}T00:00:00`);
    return Number.isNaN(parsed) ? 0 : Math.floor(parsed / 1000);
};

const formatDisplayDate = (value: number, fallback: string): string =>
    value > 0 ? new Date(value * 1000).toLocaleDateString(document.documentElement.lang || undefined) : fallback;

const csvCell = (value: string | number): string => `"${String(value).replace(/"/g, "\"\"")}"`;

const downloadCsv = (filename: string, rows: Array<Array<string | number>>): void => {
    const csv = rows.map((row) => row.map(csvCell).join(",")).join("\r\n");
    const blob = new Blob([`\uFEFF${csv}\r\n`], {type: "text/csv;charset=utf-8"});
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");

    link.href = url;
    link.download = filename;
    link.style.display = "none";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};

const emptyGenerateForm = (): GenerateForm => ({
    planid: 0,
    quantity: "1",
    value: "0",
    durationdays: "0",
    maxuses: "1",
    maxusesperuser: "1",
    validfrom: "",
    validuntil: "",
    batchname: "",
    notes: "",
});

export default function SubscriptionKeysAdmin({
    listMethodName,
    generateMethodName,
    actionMethodName,
    statusOptions,
    perPageOptions,
    labels,
}: SubscriptionKeysAdminProps) {
    useModernCommerceClassSync();

    const [filters, setFilters] = useState<Filters>({
        search: "",
        status: "",
        planid: 0,
        page: 0,
        perpage: 10,
    });
    const [searchInput, setSearchInput] = useState("");
    const [data, setData] = useState<ListResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [busyId, setBusyId] = useState(0);
    const [reloadToken, setReloadToken] = useState(0);
    const [copied, setCopied] = useState("");

    const [genOpen, setGenOpen] = useState(false);
    const [genForm, setGenForm] = useState<GenerateForm>(emptyGenerateForm());
    const [genFieldErrors, setGenFieldErrors] = useState<FieldErrorMap>({});
    const [genGenerated, setGenGenerated] = useState<string[]>([]);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setFilters((current) => current.search === searchInput ? current : {...current, search: searchInput, page: 0});
        }, 350);

        return () => window.clearTimeout(timer);
    }, [searchInput]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<ListResponse>(listMethodName, filters)
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
    }, [filters, listMethodName, reloadToken]);

    useEffect(() => {
        const refreshButton = document.getElementById("moderncommerce-subscription-keys-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    const total = data?.total ?? 0;
    const stats = data?.stats;
    const plans = data?.plans ?? [];
    const totalPages = Math.max(1, Math.ceil(total / filters.perpage));
    const range = getVisibleRange(total, filters.page, filters.perpage);

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({...current, ...changes, page: changes.page ?? 0}));
    };

    const updateGen = (changes: Partial<GenerateForm>) => {
        setGenForm((current) => ({...current, ...changes}));
    };

    const openGenerateDrawer = () => {
        setGenOpen(true);
        setGenGenerated([]);
    };

    const copyText = (id: string, text: string) => {
        const fallbackCopy = () => {
            const field = document.createElement("textarea");
            field.value = text;
            field.setAttribute("readonly", "readonly");
            field.style.position = "fixed";
            field.style.left = "-9999px";
            document.body.appendChild(field);
            field.select();
            document.execCommand("copy");
            document.body.removeChild(field);
        };

        const copy = navigator.clipboard?.writeText(text) ?? new Promise<void>((resolve) => {
            fallbackCopy();
            resolve();
        });

        void copy.then(() => {
            setCopied(id);
            window.setTimeout(() => setCopied(""), 2000);
        }).catch(() => {
            fallbackCopy();
            setCopied(id);
            window.setTimeout(() => setCopied(""), 2000);
        });
    };

    const exportGeneratedKeys = () => {
        downloadCsv(`subscription-keys-generated-${new Date().toISOString().slice(0, 10)}.csv`, [
            [labels.keycode],
            ...genGenerated.map((code) => [code]),
        ]);
    };

    const statusBadgeClass = (status: string): McBadgeVariant => {
        if (status === "active") {
            return "success";
        }
        if (status === "disabled") {
            return "neutral";
        }
        return "warning";
    };

    const statusLabel = (status: string): string => {
        const match = statusOptions.find((option) => option.value === status);
        return match ? match.label : status;
    };

    const exportVisibleKeys = () => {
        const rows = data?.items ?? [];

        downloadCsv(`subscription-keys-${new Date().toISOString().slice(0, 10)}.csv`, [
            [labels.keycode, labels.plan, labels.status, labels.usage, labels.expires, labels.batch, labels.notes],
            ...rows.map((key) => [
                key.keycode,
                key.planname,
                statusLabel(key.status),
                key.maxuses > 0 ? `${key.usedcount} / ${key.maxuses}` : key.usedcount,
                formatDisplayDate(key.expirydate, labels.neverexpires),
                key.batchname,
                key.notes,
            ]),
        ]);
    };

    const changeStatus = async(key: Key, action: "activate" | "disable" | "delete") => {
        if (action === "delete" && !await confirmDialog({message: labels.deleteconfirm, danger: true})) {
            return;
        }

        setBusyId(key.id);
        setError("");

        try {
            const result = await callMoodleService<ActionResponse>(actionMethodName, {id: key.id, action});
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setBusyId(0);
        }
    };

    const renderKeyActions = (key: Key) => (
        <div className={mcClasses("mc-table-design__actions justify-content-end")}>
            <McTableActionMenu
                label={`${labels.actions}: ${key.keycode}`}
                items={[
                    key.status === "active"
                        ? {
                            key: "disable",
                            label: labels.deactivate,
                            icon: "bi bi-pause-circle",
                            disabled: busyId === key.id,
                            onClick: () => void changeStatus(key, "disable"),
                        }
                        : {
                            key: "activate",
                            label: labels.activate,
                            icon: "bi bi-play-circle",
                            disabled: busyId === key.id,
                            onClick: () => void changeStatus(key, "activate"),
                        },
                    {
                        key: "delete",
                        label: labels.delete,
                        icon: "bi bi-trash",
                        danger: true,
                        disabled: busyId === key.id || key.usedcount > 0,
                        title: key.usedcount > 0 ? labels.cannotdeletekeyinuse : labels.delete,
                        onClick: () => void changeStatus(key, "delete"),
                    },
                ]}
            />
        </div>
    );

    const submitGenerate = async(event?: FormEvent<HTMLFormElement>) => {
        event?.preventDefault();

        const t = (key: string) => labels[key] ?? key;
        const found = runRules(genForm, {
            planid: requiredId(labels.selectplanfirst ?? t("error:required")),
            quantity: [
                integer(t("error:invalidinteger")), min(1, t("error:minvalue")), max(500, t("error:maxvalue")),
            ],
            maxuses: [integer(t("error:invalidinteger")), min(0, t("error:minvalue"))],
            maxusesperuser: [integer(t("error:invalidinteger")), min(0, t("error:minvalue"))],
            durationdays: [integer(t("error:invalidinteger")), min(0, t("error:minvalue"))],
            validuntil: afterField("validfrom", t("error:enddatebeforestart")),
        });

        setGenFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setError("");
            focusFirstError(found, ["planid", "quantity", "maxuses", "maxusesperuser", "durationdays", "validuntil"]);
            return;
        }

        setBusyId(-1);
        setError("");
        setGenGenerated([]);

        try {
            const result = await callMoodleService<GenerateResponse>(generateMethodName, {
                planid: genForm.planid,
                quantity: Number(genForm.quantity) || 1,
                value: Number(genForm.value) || 0,
                durationdays: Number(genForm.durationdays) || 0,
                maxuses: Number(genForm.maxuses) || 0,
                maxusesperuser: Number(genForm.maxusesperuser) || 1,
                validfrom: dateToTimestamp(genForm.validfrom),
                validuntil: dateToTimestamp(genForm.validuntil),
                batchname: genForm.batchname,
                notes: genForm.notes,
            });

            if (!result.success) {
                setError(result.message);
                return;
            }

            toast.success(result.message);
            setGenGenerated(result.keycodes);
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setBusyId(0);
        }
    };

    const visibleKeycodes = (data?.items ?? []).map((key) => key.keycode).join("\n");

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            {genOpen && (
                <McDrawer
                    title={labels.generatekeys}
                    onClose={() => setGenOpen(false)}
                    closeLabel={labels.cancel}
                    disableClose={busyId === -1}
                    footer={(
                        <>
                            <McButton
                                className={mcClasses("btn-mc-primary")}
                                loading={busyId === -1}
                                loadingLabel={labels.loading || "Generating..."}
                                form="mc-subkey-generate-form"
                                type="submit"
                            >
                                {labels.generatekeys}
                            </McButton>
                            <button
                                className={mcClasses("mc-button btn-mc-secondary")}
                                disabled={busyId === -1}
                                onClick={() => setGenOpen(false)}
                                type="button"
                            >
                                {labels.cancel}
                            </button>
                        </>
                    )}
                >
                    <form id="mc-subkey-generate-form" noValidate onSubmit={submitGenerate}>
                    <div className={mcClasses("mc-product-form__section")}>
                        <div className={mcClasses("mc-product-form__grid")}>
                            <label className={mcClasses("mc-product-form__wide")} htmlFor="mc-subkey-planid">
                                <span>
                                    {labels.selectplan}
                                    <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                </span>
                                <select
                                    aria-describedby={genFieldErrors.planid ? "mc-subkey-planid-error" : undefined}
                                    aria-invalid={genFieldErrors.planid ? true : undefined}
                                    aria-required="true"
                                    className={mcClasses("mc-select", genFieldErrors.planid ? "mc-field-invalid" : "")}
                                    id="mc-subkey-planid"
                                    name="planid"
                                    onChange={(event) => updateGen({planid: Number(event.target.value) || 0})}
                                    value={genForm.planid}
                                >
                                    <option value="0">{labels.selectplan}</option>
                                    {plans.map((plan) => (
                                        <option key={plan.id} value={plan.id}>{plan.name} ({plan.displayprice})</option>
                                    ))}
                                </select>
                                <FieldError id="mc-subkey-planid-error" message={genFieldErrors.planid} />
                            </label>
                            <label>
                                <span>{labels.numberofkeys}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    max="1000"
                                    min="1"
                                    onChange={(event) => updateGen({quantity: event.target.value})}
                                    type="number"
                                    value={genForm.quantity}
                                />
                            </label>
                            <label>
                                <span>{labels.maxusesperkey}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    min="0"
                                    onChange={(event) => updateGen({maxuses: event.target.value})}
                                    placeholder={labels.zerounlimited}
                                    type="number"
                                    value={genForm.maxuses}
                                />
                            </label>
                            <label>
                                <span>{labels.maxusesperuser}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    min="1"
                                    onChange={(event) => updateGen({maxusesperuser: event.target.value})}
                                    type="number"
                                    value={genForm.maxusesperuser}
                                />
                            </label>
                            <label>
                                <span>{labels.subscriptiondurationdays}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    min="0"
                                    onChange={(event) => updateGen({durationdays: event.target.value})}
                                    placeholder={labels.zerouseplandefault}
                                    type="number"
                                    value={genForm.durationdays}
                                />
                            </label>
                            <label>
                                <span>{labels.validfrom}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    onChange={(event) => updateGen({validfrom: event.target.value})}
                                    type="date"
                                    value={genForm.validfrom}
                                />
                            </label>
                            <label>
                                <span>{labels.validuntil}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    onChange={(event) => updateGen({validuntil: event.target.value})}
                                    type="date"
                                    value={genForm.validuntil}
                                />
                            </label>
                            <label>
                                <span>{labels.batchname}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    onChange={(event) => updateGen({batchname: event.target.value})}
                                    placeholder={labels.optionalbatchname}
                                    type="text"
                                    value={genForm.batchname}
                                />
                            </label>
                            <label className={mcClasses("mc-product-form__wide")}>
                                <span>{labels.notes}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    onChange={(event) => updateGen({notes: event.target.value})}
                                    placeholder={labels.optionalnotes}
                                    type="text"
                                    value={genForm.notes}
                                />
                            </label>
                        </div>
                    </div>
                    </form>

                    {genGenerated.length > 0 && (
                        <div className={mcClasses("mc-product-form__section")}>
                            <div className="d-flex justify-content-between align-items-center">
                                <h4>{labels.generatedkeys} ({genGenerated.length})</h4>
                                <button
                                    className={mcClasses("mc-button mc-btn-soft")}
                                    onClick={exportGeneratedKeys}
                                    type="button"
                                >
                                    {labels.exportkeys}
                                </button>
                            </div>
                            <div className="d-flex flex-wrap gap-1 mt-2">
                                {genGenerated.map((code) => (
                                    <McBadge variant="neutral" tone="soft" className="mc-cell-mono" key={code}>{code}</McBadge>
                                ))}
                            </div>
                        </div>
                    )}
                </McDrawer>
            )}

            {stats && (
                <div className={mcClasses("mc-stat-strip")} aria-label={labels.title}>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--primary")}>
                        <i className="bi bi-key mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.totalkeys}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.total)}</strong>
                        </div>
                        <i className="bi bi-key mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                        <i className="bi bi-check-circle mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.activekeys}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.active)}</strong>
                        </div>
                        <i className="bi bi-check-circle mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--info")}>
                        <i className="bi bi-receipt mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.usedkeys}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.used)}</strong>
                        </div>
                        <i className="bi bi-receipt mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--warning")}>
                        <i className="bi bi-slash-circle mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.disabledkeys}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.disabled)}</strong>
                        </div>
                        <i className="bi bi-slash-circle mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                </div>
            )}

            <McTableCard
                title={<h2 className={mcClasses("mc-card-title")}>{labels.title}</h2>}
                actions={(
                    <McButton
                        className={mcClasses("btn-mc-primary")}
                        onClick={openGenerateDrawer}
                    >
                        <i className="bi bi-key" aria-hidden="true" />
                        <span>{labels.generatekeys}</span>
                    </McButton>
                )}
                toolbar={(
                    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                        <div className={mcClasses("mc-product-toolbar__search")}>
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-subkeys-search">{labels.search}</label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-subkeys-search"
                                onChange={(event) => setSearchInput(event.target.value)}
                                placeholder={labels.searchkeys}
                                type="search"
                                value={searchInput}
                            />
                        </div>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.plan}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({planid: Number(event.target.value) || 0})}
                                value={filters.planid}
                            >
                                <option value="0">{labels.allplans}</option>
                                {plans.map((plan) => (
                                    <option key={plan.id} value={plan.id}>{plan.name}</option>
                                ))}
                            </select>
                        </label>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.status}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({status: event.target.value})}
                                value={filters.status}
                            >
                                <option value="">{labels.allstatuses}</option>
                                {statusOptions.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                        </label>
                        <label className={mcClasses("mc-product-toolbar__field mc-product-toolbar__field--small mc-table-design-page-size")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.perpage}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({perpage: Number(event.target.value) || 10})}
                                value={filters.perpage}
                            >
                                {perPageOptions.map((option) => (
                                    <option key={option} value={option}>{option}</option>
                                ))}
                            </select>
                        </label>
                    </div>
                )}
                footer={(
                    <McTableFooter>
                        <div className={mcClasses("mc-product-admin__summary mc-table-design-footer-summary")}>
                            <span>{labels.showing} {formatCount(range.from)}-{formatCount(range.to)} / {formatCount(total)}</span>
                            {visibleKeycodes !== "" && (
                                <button
                                    className={mcClasses("mc-button mc-btn-soft")}
                                    onClick={exportVisibleKeys}
                                    type="button"
                                >
                                    {labels.exportkeys}
                                </button>
                            )}
                        </div>
                        <McTablePagination
                            previousLabel={labels.previous}
                            nextLabel={labels.next}
                            pageLabel={labels.page}
                            page={filters.page + 1}
                            totalPages={totalPages}
                            previousDisabled={loading || filters.page <= 0}
                            nextDisabled={loading || filters.page + 1 >= totalPages}
                            onPrevious={() => updateFilters({page: Math.max(0, filters.page - 1)})}
                            onNext={() => updateFilters({page: filters.page + 1})}
                        />
                    </McTableFooter>
                )}
            >
                <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.title}>
                            <thead>
                                <tr>
                                    <th scope="col">{labels.keycode}</th>
                                    <th scope="col">{labels.plan}</th>
                                    <th scope="col" className="text-end">{labels.usage}</th>
                                    <th scope="col">{labels.status}</th>
                                    <th scope="col">{labels.expires}</th>
                                    <th scope="col" className="text-end">{labels.actions}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {!loading && data?.items.length === 0 && (
                                    <tr>
                                        <td colSpan={6}>
                                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                <span className={mcClasses("mc-empty__icon")}><i className="bi bi-key" aria-hidden="true" /></span>
                                                <p className={mcClasses("mc-empty__title")}>{total === 0 ? labels.nokeys : labels.noresults}</p>
                                                {total === 0 && <p className={mcClasses("mc-empty__text")}>{labels.nokeysdesc}</p>}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {data?.items.map((key) => (
                                    <tr key={key.id}>
                                        <td>
                                            <button
                                                className={mcClasses("mc-button mc-cell-mono mc-btn-ghost p-0 text-start")}
                                                onClick={() => copyText(`key-${key.id}`, key.keycode)}
                                                title={labels.copy}
                                                type="button"
                                            >
                                                {key.keycode}
                                            </button>
                                            {copied === `key-${key.id}` && <span className={mcClasses("mc-cell-muted small ms-1")}>{labels.copied}</span>}
                                            {key.batchname !== "" && <div className={mcClasses("mc-cell-muted small")}>{labels.batch}: {key.batchname}</div>}
                                        </td>
                                        <td>
                                            <div>{key.planname}</div>
                                            {key.displayvalue !== "" && <div className={mcClasses("mc-cell-muted small")}>{key.displayvalue}</div>}
                                        </td>
                                        <td className="text-end">{key.usedcount}{key.maxuses > 0 ? ` / ${key.maxuses}` : ""}</td>
                                        <td>
                                            <McBadge variant={statusBadgeClass(key.status)} tone="soft" dot>
                                                {statusLabel(key.status)}
                                            </McBadge>
                                        </td>
                                        <td className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{formatDisplayDate(key.expirydate, labels.neverexpires)}</td>
                                        <td className="text-end">
                                            {renderKeyActions(key)}
                                        </td>
                                    </tr>
                                ))}
                                {loading && (
                                    <tr>
                                        <td colSpan={6}>
                                            <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                </table>
            </McTableCard>
        </section>
    );
}

// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin manager for subscription email templates (Modern Commerce shell,
 * Modern Commerce core webservice endpoints).
 *
 * @module     local_moderncommerce/subscription_emails_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McBadge} from "./badge";
import {McDrawer} from "./drawer";
import {McTableActionMenu, McTableCard, McTableFooter, McTablePagination} from "./table_components";
import {BodyEditorMode, EmailBodyEditor} from "./email_body_editor";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type Placeholder = {
    token: string;
    description: string;
};

type EmailTemplate = {
    type: string;
    key: string;
    name: string;
    description: string;
    enabled: boolean;
    templatekey: string;
    usecustommessage: boolean;
    customsubject: string;
    custommessage: string;
    timecreated: number;
    timemodified: number;
};

type TemplateOption = {
    key: string;
    name: string;
    subject: string;
    body: string;
};

type ListResponse = {
    items: EmailTemplate[];
    placeholders: Placeholder[];
    templateoptions: TemplateOption[];
};

type SaveResponse = {
    success: boolean;
    message: string;
};

type SubscriptionEmailsAdminProps = {
    listMethodName: string;
    saveMethodName: string;
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

const iconForType = (type: string): string => {
    if (type.includes("payment")) {
        return "bi-credit-card";
    }
    if (type.includes("expir") || type.includes("grace")) {
        return "bi-hourglass-split";
    }
    if (type.includes("cancel")) {
        return "bi-x-circle";
    }
    if (type.includes("renew")) {
        return "bi-arrow-repeat";
    }
    if (type.includes("activation")) {
        return "bi-check2-circle";
    }

    return "bi-envelope";
};

const formatDate = (timestamp: number): string => {
    if (!timestamp) {
        return "-";
    }

    return new Date(timestamp * 1000).toLocaleDateString(document.documentElement.lang || undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

export default function SubscriptionEmailsAdmin({
    listMethodName,
    saveMethodName,
    labels,
}: SubscriptionEmailsAdminProps) {
    useModernCommerceClassSync();

    const [data, setData] = useState<ListResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [form, setForm] = useState<EmailTemplate | null>(null);
    const [saving, setSaving] = useState(false);
    const [copied, setCopied] = useState("");
    const [reloadToken, setReloadToken] = useState(0);
    const [page, setPage] = useState(1);
    const [searchTerm, setSearchTerm] = useState("");
    const [statusFilter, setStatusFilter] = useState("");
    const [perPage, setPerPage] = useState(10);
    const [bodyMode, setBodyMode] = useState<BodyEditorMode>("visual");

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<ListResponse>(listMethodName, {})
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                    setPage(1);
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
        const refreshButton = document.getElementById("moderncommerce-subscription-emails-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    useEffect(() => {
        setPage(1);
    }, [searchTerm, statusFilter, perPage]);

    const updateForm = (changes: Partial<EmailTemplate>) => {
        setForm((current) => current ? {...current, ...changes} : current);
    };

    const openForm = (item: EmailTemplate) => {
        setError("");
        setBodyMode("visual");
        setForm({...item});
    };

    const closeForm = () => {
        setForm(null);
        setError("");
    };

    const copyPlaceholder = (token: string) => {
        void navigator.clipboard?.writeText(token).then(() => {
            setCopied(token);
            window.setTimeout(() => setCopied(""), 1500);
        });
    };

    const selectedTemplate = (templatekey: string): TemplateOption | undefined => (
        data?.templateoptions ?? []
    ).find((option) => option.key === templatekey);

    const selectedTemplateName = (templatekey: string): string => (
        selectedTemplate(templatekey)?.name || templatekey || labels.defaulttemplate || "Default template"
    );

    const applyTemplate = (templatekey: string) => {
        const option = selectedTemplate(templatekey);
        updateForm({
            templatekey,
            customsubject: option?.subject || "",
            custommessage: option?.body || "",
            usecustommessage: true,
        });
        setBodyMode("visual");
    };

    const submitForm = async() => {
        if (!form) {
            return;
        }
        setSaving(true);
        setError("");

        try {
            const result = await callMoodleService<SaveResponse>(saveMethodName, {
                type: form.type,
                enabled: form.enabled,
                templatekey: form.templatekey,
                usecustommessage: true,
                customsubject: form.customsubject,
                custommessage: form.custommessage,
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

    const placeholders = data?.placeholders ?? [];
    const normalizedSearch = searchTerm.trim().toLowerCase();
    const filteredItems = (data?.items ?? []).filter((item) => {
        const matchesSearch = normalizedSearch === ""
            || item.name.toLowerCase().includes(normalizedSearch)
            || item.description.toLowerCase().includes(normalizedSearch)
            || item.type.toLowerCase().includes(normalizedSearch)
            || item.templatekey.toLowerCase().includes(normalizedSearch)
            || selectedTemplateName(item.templatekey).toLowerCase().includes(normalizedSearch);
        const matchesStatus = statusFilter === ""
            || (statusFilter === "enabled" && item.enabled)
            || (statusFilter === "disabled" && !item.enabled);

        return matchesSearch && matchesStatus;
    });
    const totalItems = filteredItems.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
    const currentPage = Math.min(page, totalPages);
    const startIndex = totalItems === 0 ? 0 : (currentPage - 1) * perPage;
    const endIndex = totalItems === 0 ? 0 : Math.min(startIndex + perPage, totalItems);
    const visibleItems = filteredItems.slice(startIndex, endIndex);

    return (
        <section className={mcClasses("mc-emails-admin")} aria-label={labels.title}>
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}
            {loading && !data && (
                <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
            )}

            <McTableCard
                title={(
                    <div>
                        <h3 className={mcClasses("mc-card-title mb-1")}>{labels.title}</h3>
                        {labels.description && (
                            <p className={mcClasses("mc-cell-muted small mb-0")}>{labels.description}</p>
                        )}
                    </div>
                )}
                toolbar={(
                    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                        <div className={mcClasses("mc-product-toolbar__search")}>
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-subscription-emails-search">
                                {labels.search || "Search"}
                            </label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-subscription-emails-search"
                                onChange={(event) => setSearchTerm(event.target.value)}
                                placeholder={labels.searchplaceholder || "Search subscription emails"}
                                type="search"
                                value={searchTerm}
                            />
                        </div>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.status}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => setStatusFilter(event.target.value)}
                                value={statusFilter}
                            >
                                <option value="">{labels.allstatuses || "All statuses"}</option>
                                <option value="enabled">{labels.enabled}</option>
                                <option value="disabled">{labels.disabled}</option>
                            </select>
                        </label>
                        <label className={mcClasses("mc-product-toolbar__field mc-product-toolbar__field--small")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.perpage || "Per page"}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => setPerPage(Number(event.target.value) || 10)}
                                value={perPage}
                            >
                                {[10, 25, 50].map((option) => (
                                    <option key={option} value={option}>{option}</option>
                                ))}
                            </select>
                        </label>
                    </div>
                )}
                footer={(
                    <McTableFooter
                        summary={(
                            <span>
                                {labels.showing || "Showing"} {totalItems === 0 ? 0 : startIndex + 1}-{endIndex} / {totalItems}
                            </span>
                        )}
                        pagination={(
                            <McTablePagination
                                previousDisabled={currentPage <= 1}
                                nextDisabled={currentPage >= totalPages}
                                onPrevious={() => setPage((value) => Math.max(1, value - 1))}
                                onNext={() => setPage((value) => Math.min(totalPages, value + 1))}
                                previousLabel={labels.previous || "Previous"}
                                nextLabel={labels.next || "Next"}
                                pageLabel={labels.page || "Page"}
                                page={currentPage}
                                totalPages={totalPages}
                            />
                        )}
                    />
                )}
            >
                <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.title}>
                    <thead>
                        <tr>
                            <th scope="col">{labels.email || "Email"}</th>
                            <th scope="col">{labels.purpose || "Purpose"}</th>
                            <th scope="col">{labels.status}</th>
                            <th scope="col">{labels.template || "Email Template"}</th>
                            <th scope="col">{labels.modified || "Modified"}</th>
                            <th scope="col" className="text-end">{labels.actions || "Actions"}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visibleItems.map((item) => (
                            <tr key={item.type}>
                                <td>
                                    <div className="d-flex align-items-center gap-3">
                                        <span className={mcClasses("mc-icon", "mc-email-icon", "text-primary")}>
                                            <i className={`bi ${iconForType(item.type)}`} aria-hidden="true" />
                                        </span>
                                        <div>
                                            <div className="fw-semibold">{item.name}</div>
                                            <div className={mcClasses("mc-cell-muted small mc-cell-mono")}>{item.type}</div>
                                        </div>
                                    </div>
                                </td>
                                <td className={mcClasses("mc-cell-muted")}>{item.description}</td>
                                <td>
                                    <McBadge variant={item.enabled ? "success" : "neutral"} tone="soft" dot>
                                        {item.enabled ? labels.enabled : labels.disabled}
                                    </McBadge>
                                </td>
                                <td>
                                    <McBadge variant="neutral" tone="soft">
                                        {selectedTemplateName(item.templatekey)}
                                    </McBadge>
                                </td>
                                <td>{formatDate(item.timemodified || item.timecreated)}</td>
                                <td className="text-end">
                                    <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                        <McTableActionMenu
                                            label={`${labels.actions || "Actions"}: ${item.name}`}
                                            items={[
                                                {
                                                    key: "edit",
                                                    label: labels.edit,
                                                    icon: "bi bi-pencil",
                                                    onClick: () => openForm(item),
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

            {form && (
                <McDrawer
                    title={form.name}
                    subtitle={labels.emailsettings || labels.title}
                    onClose={closeForm}
                    closeLabel={labels.cancel}
                    className="mc-drawer--email-form"
                    bodyClassName="mc-drawer__body--email-form"
                    disableClose={saving}
                    footer={(
                        <>
                            <McButton
                                className={mcClasses("btn-mc-primary")}
                                loading={saving}
                                loadingLabel={labels.saving || "Saving..."}
                                onClick={submitForm}
                                type="button"
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

                    <div className={mcClasses("mc-product-form__section")}>
                        <div className={mcClasses("mc-product-form__checks")}>
                            <label className={mcClasses("mc-switch")}>
                                <input
                                    checked={form.enabled}
                                    onChange={(event) => updateForm({enabled: event.target.checked})}
                                    type="checkbox"
                                />
                                <span className={mcClasses("mc-switch__track")} aria-hidden="true" />
                                <span className={mcClasses("mc-switch__thumb")} aria-hidden="true" />
                                <span className={mcClasses("mc-switch__label")}>{labels.enabled}</span>
                            </label>
                        </div>

                        <div className={mcClasses("mc-product-form__grid")}>
                            {(data?.templateoptions ?? []).length > 0 && (
                                <label className={mcClasses("mc-product-form__wide")}>
                                    <span>{labels.template || "Email Template"}</span>
                                    <select
                                        className={mcClasses("mc-select")}
                                        onChange={(event) => applyTemplate(event.target.value)}
                                        value={form.templatekey}
                                    >
                                        {(data?.templateoptions ?? []).map((option) => (
                                            <option key={option.key} value={option.key}>{option.name}</option>
                                        ))}
                                    </select>
                                    <small className={mcClasses("mc-cell-muted d-block mt-1")}>
                                        {labels.templatehint}
                                    </small>
                                </label>
                            )}
                            <label className={mcClasses("mc-product-form__wide")}>
                                <span>{labels.customsubject}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    onChange={(event) => updateForm({customsubject: event.target.value, usecustommessage: true})}
                                    type="text"
                                    value={form.customsubject}
                                />
                            </label>
                            <div className={mcClasses("mc-product-form__wide")}>
                                <span>{labels.custommessage}</span>
                                <EmailBodyEditor
                                    idPrefix={`mc-subscription-email-body-${form.type}`}
                                    labels={{...labels, body: labels.custommessage}}
                                    mode={bodyMode}
                                    onChange={(custommessage) => updateForm({custommessage, usecustommessage: true})}
                                    onModeChange={setBodyMode}
                                    value={form.custommessage}
                                />
                            </div>
                        </div>
                    </div>

                    {placeholders.length > 0 && (
                        <div className={mcClasses("mc-product-form__section")}>
                            <h4>{labels.placeholders}</h4>
                            <p className={mcClasses("mc-cell-muted small")}>{labels.insertplaceholder}</p>
                            <div className="d-flex flex-wrap gap-1">
                                {placeholders.map((placeholder) => (
                                    <button
                                        className={mcClasses("mc-button mc-badge mc-badge--neutral mc-cell-mono mc-placeholder-chip")}
                                        data-mc-button="light"
                                        key={placeholder.token}
                                        onClick={() => copyPlaceholder(placeholder.token)}
                                        title={placeholder.description}
                                        type="button"
                                    >
                                        {copied === placeholder.token ? labels.copied : placeholder.token}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </McDrawer>
            )}
        </section>
    );
}

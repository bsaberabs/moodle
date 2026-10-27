// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin email notifications for Modern Commerce.
 *
 * @module     local_moderncommerce/emails_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import type {FormEvent} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McBadge} from "./badge";
import {McDrawer} from "./drawer";
import {FieldError} from "./form";
import {focusFirstError, maxLength, required, runRules} from "./validation";
import type {FieldErrorMap} from "./validation";
import {McTableActionMenu, McTableCard, McTableFooter, McTablePagination} from "./table_components";
import {BodyEditorMode, EmailBodyEditor} from "./email_body_editor";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type EmailType = {
    key: string;
    name: string;
    description: string;
    icon: string;
    color: string;
    groupkey: string;
    grouplabel: string;
    enabled: boolean;
    timemodified: number;
};

type ListResponse = {
    items: EmailType[];
};

type TemplateOption = {
    id: number;
    name: string;
};

type TemplatePreview = {
    subject: string;
    body: string;
};

type EmailDetail = {
    type: string;
    name: string;
    enabled: boolean;
    subject: string;
    body: string;
    templateid: number;
    placeholders: string[];
    templateoptions: TemplateOption[];
};

type SaveResponse = {
    success: boolean;
    message: string;
};

type EmailForm = {
    type: string;
    name: string;
    enabled: boolean;
    subject: string;
    body: string;
    templateid: number;
    placeholders: string[];
    templateoptions: TemplateOption[];
};

type EmailsAdminProps = {
    listMethodName: string;
    getMethodName: string;
    saveMethodName: string;
    openType: string;
    labels: Labels;
    onEditingChange?: (editing: boolean) => void;
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

export default function EmailsAdmin({
    listMethodName,
    getMethodName,
    saveMethodName,
    openType,
    labels,
    onEditingChange,
}: EmailsAdminProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<ListResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [fieldErrors, setFieldErrors] = useState<FieldErrorMap>({});
    const [form, setForm] = useState<EmailForm | null>(null);
    const [saving, setSaving] = useState(false);
    const [copied, setCopied] = useState("");
    const [reloadToken, setReloadToken] = useState(0);
    const [bootstrapped, setBootstrapped] = useState(false);
    const [page, setPage] = useState(1);
    const [searchTerm, setSearchTerm] = useState("");
    const [groupFilter, setGroupFilter] = useState("");
    const [statusFilter, setStatusFilter] = useState("");
    const [perPage, setPerPage] = useState(10);
    const [bodyMode, setBodyMode] = useState<BodyEditorMode>("visual");

    useEffect(() => {
        onEditingChange?.(Boolean(form));
    }, [form, onEditingChange]);

    const loadEmail = (type: string) => {
        setError("");
        void callMoodleService<EmailDetail>(getMethodName, {type})
            .then((detail) => {
                setBodyMode("visual");
                setForm({...detail});
            })
            .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : String(caught)));
    };

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
        if (bootstrapped) {
            return;
        }
        setBootstrapped(true);
        if (openType) {
            loadEmail(openType);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [openType, bootstrapped]);

    useEffect(() => {
        const refreshButton = document.getElementById("moderncommerce-emails-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    useEffect(() => {
        setPage(1);
    }, [searchTerm, groupFilter, statusFilter, perPage]);

    const updateForm = (changes: Partial<EmailForm>) => {
        setForm((current) => current ? {...current, ...changes} : current);
    };

    const applyTemplate = (templateid: number) => {
        if (templateid <= 0) {
            updateForm({templateid: 0});
            return;
        }

        updateForm({templateid});
        void callMoodleService<{template: TemplatePreview}>(
            "local_moderncommerce_email_get_template",
            {id: templateid}
        )
            .then((response) => {
                const template = response.template;
                updateForm({
                    templateid,
                    subject: template.subject || "",
                    body: template.body || "",
                });
            })
            .catch((caught: unknown) => {
                setError(caught instanceof Error
                    ? caught.message
                    : labels.templateloaderror || "Could not load the selected template content.");
            });
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

    const submitForm = async(event?: FormEvent<HTMLFormElement>) => {
        event?.preventDefault();
        if (!form) {
            return;
        }

        const t = (key: string) => labels[key] ?? key;
        const found = runRules(form, {
            subject: [required(t("error:required")), maxLength(255, t("error:maxlength"))],
            body: required(t("error:required")),
        });
        setFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setError("");
            focusFirstError(found, ["subject", "body"]);
            return;
        }

        setSaving(true);
        setError("");

        try {
            const result = await callMoodleService<SaveResponse>(saveMethodName, {
                type: form.type,
                enabled: form.enabled,
                subject: form.subject,
                body: form.body,
                templateid: form.templateid,
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

    const groupOptions = (data?.items ?? []).reduce<Array<{key: string; label: string}>>((options, item) => {
        if (item.groupkey && !options.some((option) => option.key === item.groupkey)) {
            options.push({key: item.groupkey, label: item.grouplabel});
        }
        return options;
    }, []);
    const normalizedSearch = searchTerm.trim().toLowerCase();
    const filteredItems = (data?.items ?? []).filter((item) => {
        const matchesSearch = normalizedSearch === ""
            || item.name.toLowerCase().includes(normalizedSearch)
            || item.description.toLowerCase().includes(normalizedSearch)
            || item.grouplabel.toLowerCase().includes(normalizedSearch);
        const matchesGroup = groupFilter === "" || item.groupkey === groupFilter;
        const matchesStatus = statusFilter === ""
            || (statusFilter === "enabled" && item.enabled)
            || (statusFilter === "disabled" && !item.enabled);

        return matchesSearch && matchesGroup && matchesStatus;
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
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-emails-search">
                                {labels.search || "Search"}
                            </label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-emails-search"
                                onChange={(event) => setSearchTerm(event.target.value)}
                                placeholder={labels.searchplaceholder || "Search emails"}
                                type="search"
                                value={searchTerm}
                            />
                        </div>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.group || "Email group"}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => setGroupFilter(event.target.value)}
                                value={groupFilter}
                            >
                                <option value="">{labels.allgroups || "All email groups"}</option>
                                {groupOptions.map((option) => (
                                    <option key={option.key} value={option.key}>{option.label}</option>
                                ))}
                            </select>
                        </label>
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
                                {labels.showing} {totalItems === 0 ? 0 : startIndex + 1}-{endIndex} / {totalItems}
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
                            <th scope="col">{labels.email}</th>
                            <th scope="col">{labels.purpose}</th>
                            <th scope="col">{labels.status}</th>
                            <th scope="col">{labels.modified}</th>
                            <th scope="col" className="text-end">{labels.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visibleItems.map((item) => (
                            <tr key={item.key}>
                                <td>
                                    <div className="d-flex align-items-center gap-3">
                                        <span className={mcClasses("mc-icon", "mc-email-icon", `text-${item.color}`)}>
                                            <i className={`bi ${item.icon}`} aria-hidden="true" />
                                        </span>
                                        <div className="fw-semibold">{item.name}</div>
                                    </div>
                                </td>
                                <td className={mcClasses("mc-cell-muted")}>{item.description}</td>
                                <td>
                                    <McBadge variant={item.enabled ? "success" : "neutral"} tone="soft" dot>
                                        {item.enabled ? labels.enabled : labels.disabled}
                                    </McBadge>
                                </td>
                                <td>{formatDate(item.timemodified)}</td>
                                <td className="text-end">
                                    <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                        <McTableActionMenu
                                            label={`${labels.actions}: ${item.name}`}
                                            items={[
                                                {
                                                    key: "edit",
                                                    label: labels.edit,
                                                    icon: "bi bi-pencil",
                                                    onClick: () => loadEmail(item.key),
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
                    subtitle={labels.settings}
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
                                form="mc-email-drawer-form"
                                loadingLabel={labels.saving || "Saving..."}
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

                    <form id="mc-email-drawer-form" noValidate onSubmit={submitForm}>
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
                            {form.templateoptions.length > 0 && (
                                <label>
                                    <span>{labels.template}</span>
                                    <select
                                        className={mcClasses("mc-select")}
                                        onChange={(event) => applyTemplate(Number(event.target.value) || 0)}
                                        value={form.templateid}
                                    >
                                        <option value="0">{labels.templatebuiltin || labels.none}</option>
                                        {form.templateoptions.map((option) => (
                                            <option key={option.id} value={option.id}>{option.name}</option>
                                        ))}
                                    </select>
                                    <small className={mcClasses("mc-cell-muted d-block mt-1")}>
                                        {labels.templatehint}
                                    </small>
                                </label>
                            )}
                            <label className={mcClasses("mc-product-form__wide")} htmlFor="mc-email-subject">
                                <span>
                                    {labels.subject}
                                    <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                </span>
                                <input
                                    aria-describedby={fieldErrors.subject ? "mc-email-subject-error" : undefined}
                                    aria-invalid={fieldErrors.subject ? true : undefined}
                                    aria-required="true"
                                    className={mcClasses("mc-form-control",
                                        fieldErrors.subject ? "mc-field-invalid" : "")}
                                    id="mc-email-subject"
                                    maxLength={255}
                                    name="subject"
                                    onChange={(event) => updateForm({subject: event.target.value})}
                                    type="text"
                                    value={form.subject}
                                />
                                <FieldError id="mc-email-subject-error" message={fieldErrors.subject} />
                            </label>
                            <div className={mcClasses("mc-product-form__wide")}>
                                <span>
                                    {labels.body}
                                    <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                </span>
                                <FieldError id="mc-email-body-error" message={fieldErrors.body} />
                                <EmailBodyEditor
                                    labels={labels}
                                    mode={bodyMode}
                                    onChange={(body) => updateForm({body})}
                                    onModeChange={setBodyMode}
                                    value={form.body}
                                />
                            </div>
                        </div>
                    </div>

                    <div className={mcClasses("mc-product-form__section")}>
                        <h4>{labels.placeholders}</h4>
                        <p className={mcClasses("mc-cell-muted small")}>{labels.insertplaceholder}</p>
                        <div className="d-flex flex-wrap gap-1">
                            {form.placeholders.map((token) => (
                                <button
                                    className={mcClasses("mc-button mc-badge mc-badge--neutral mc-cell-mono mc-placeholder-chip")}
                                    data-mc-button="light"
                                    key={token}
                                    onClick={() => copyPlaceholder(token)}
                                    type="button"
                                >
                                    {copied === token ? labels.copied : token}
                                </button>
                            ))}
                        </div>
                    </div>
                    </form>
                </McDrawer>
            )}
        </section>
    );
}

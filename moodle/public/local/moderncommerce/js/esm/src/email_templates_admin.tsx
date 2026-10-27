// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin for Modern Commerce outgoing emails and the template content library.
 *
 * @module     local_moderncommerce/email_templates_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef, useState} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McBadge} from "./badge";
import {McTableActionMenu, McTableCard, McTableFooter, McTablePagination} from "./table_components";
import {confirmDialog} from "./modal";
import EmailsAdmin from "./emails_admin";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type Option = {
    value: string;
    label: string;
    installed?: boolean;
};

type PlaceholderDef = {
    category: string;
    label: string;
    token: string;
    description: string;
};

type Metadata = {
    placeholders: PlaceholderDef[];
    components: Option[];
    types: Option[];
    statuses: Option[];
};

type Row = {
    id: number;
    template_key: string;
    component: string;
    name: string;
    template_type: string;
    status: string;
    locked: boolean;
    timecreated: number;
    timemodified: number;
};

type ListResponse = {
    items: Row[];
    total: number;
    page: number;
    perpage: number;
    stats: {
        total: number;
        active: number;
        inactive: number;
    };
};

type Template = {
    id: number;
    template_key: string;
    component: string;
    name: string;
    template_type: string;
    description: string;
    subject: string;
    body: string;
    format: string;
    status: string;
    locked: boolean;
    placeholders: string[];
};

type SaveResponse = {
    success: boolean;
    message: string;
    id: number;
};

type EmailTemplatesAdminProps = {
    metadataMethodName: string;
    listMethodName: string;
    getMethodName: string;
    saveMethodName: string;
    deleteMethodName: string;
    cloneMethodName: string;
    previewMethodName: string;
    notificationListMethodName: string;
    notificationGetMethodName: string;
    notificationSaveMethodName: string;
    notificationOpenType?: string;
    notificationLabels: Labels;
    labels: Labels;
    addonsUrl?: string;
};

type Filters = {
    search: string;
    component: string;
    type: string;
    status: string;
    sort: string;
    page: number;
    perpage: number;
};

const PER_PAGE = 10;
const PER_PAGE_OPTIONS = [10, 25, 50, 100];

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

const errorText = (caught: unknown): string => caught instanceof Error ? caught.message : String(caught);

const formatDate = (timestamp: number): string => {
    if (!timestamp) {
        return "-";
    }
    return new Date(timestamp * 1000).toLocaleDateString(document.documentElement.lang || undefined, {year: "numeric", month: "short", day: "numeric"});
};

const formatCount = (value: number): string => new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);

const emptyTemplate = (): Template => ({
    id: 0,
    template_key: "",
    component: "local_moderncommerce",
    name: "",
    template_type: "transactional",
    description: "",
    subject: "",
    body: "",
    format: "html",
    status: "active",
    locked: false,
    placeholders: [],
});

export default function EmailTemplatesAdmin({
    metadataMethodName,
    listMethodName,
    getMethodName,
    saveMethodName,
    deleteMethodName,
    cloneMethodName,
    previewMethodName,
    notificationListMethodName,
    notificationGetMethodName,
    notificationSaveMethodName,
    notificationOpenType = "",
    notificationLabels,
    labels,
    addonsUrl = "",
}: EmailTemplatesAdminProps) {
    useModernCommerceClassSync();

    const [metadata, setMetadata] = useState<Metadata | null>(null);
    const [data, setData] = useState<ListResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [searchInput, setSearchInput] = useState("");
    const [filters, setFilters] = useState<Filters>({
        search: "",
        component: "",
        type: "",
        status: "",
        sort: "name_asc",
        page: 0,
        perpage: PER_PAGE,
    });

    const [mode, setMode] = useState<"list" | "edit">("list");
    const [form, setForm] = useState<Template | null>(null);
    const [saving, setSaving] = useState(false);
    const [preview, setPreview] = useState<{subject: string; body: string} | null>(null);
    const [previewing, setPreviewing] = useState(false);
    const [copied, setCopied] = useState("");

    const [notificationsVersion, setNotificationsVersion] = useState(0);

    const reloadRef = useRef<() => void>(() => {});

    useEffect(() => {
        void callMoodleService<Metadata>(metadataMethodName, {})
            .then((result) => setMetadata(result))
            .catch((caught: unknown) => setError(errorText(caught)));
    }, [metadataMethodName]);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            setFilters((current) => current.search === searchInput ? current : {...current, search: searchInput, page: 0});
        }, 300);
        return () => window.clearTimeout(handle);
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
                    setError(errorText(caught));
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
    }, [listMethodName, filters]);

    reloadRef.current = () => {
        setNotificationsVersion((current) => current + 1);
        setFilters((current) => ({...current}));
    };

    useEffect(() => {
        const button = document.getElementById("moderncommerce-email-templates-refresh");
        if (!button) {
            return undefined;
        }
        const handler = () => reloadRef.current();
        button.addEventListener("click", handler);
        return () => button.removeEventListener("click", handler);
    }, []);

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({...current, ...changes}));
    };

    const openNew = () => {
        setForm(emptyTemplate());
        setPreview(null);
        setError("");
        setMode("edit");
    };

    const openEdit = async(id: number) => {
        setError("");
        try {
            const result = await callMoodleService<{template: Template}>(getMethodName, {id});
            setForm(result.template);
            setPreview(null);
            setMode("edit");
        } catch (caught) {
            toast.error(errorText(caught));
        }
    };

    const cloneRow = async(id: number) => {
        setSaving(true);
        try {
            const result = await callMoodleService<SaveResponse>(cloneMethodName, {id});
            if (!result.success) {
                toast.error(result.message);
                return;
            }
            toast.success(result.message);
            reloadRef.current();
        } catch (caught) {
            toast.error(errorText(caught));
        } finally {
            setSaving(false);
        }
    };

    const componentInstalled = (component: string): boolean => {
        if (!component || component === "local_moderncommerce") {
            return true;
        }
        const opt = metadata?.components.find((item) => item.value === component);
        return opt ? opt.installed !== false : true;
    };

    const deleteRow = async(row: Row) => {
        if (!componentInstalled(row.component)) {
            toast.error(labels.addonnotinstalled);
            return;
        }
        if (row.locked) {
            toast.error(labels.locked);
            return;
        }
        if (!await confirmDialog({message: labels.confirmdelete, danger: true})) {
            return;
        }
        setSaving(true);
        try {
            const result = await callMoodleService<SaveResponse>(deleteMethodName, {id: row.id});
            if (!result.success) {
                toast.error(result.message);
                return;
            }
            toast.success(result.message);
            reloadRef.current();
        } catch (caught) {
            toast.error(errorText(caught));
        } finally {
            setSaving(false);
        }
    };

    const saveForm = async() => {
        if (!form) {
            return;
        }
        if (form.name.trim() === "" || form.subject.trim() === "" || form.body.trim() === "") {
            setError(labels.requiredfields ?? "Name, subject and body are required.");
            return;
        }
        setSaving(true);
        setError("");
        try {
            const result = await callMoodleService<SaveResponse>(saveMethodName, {
                id: form.id,
                template_key: form.template_key,
                component: form.component,
                name: form.name,
                template_type: form.template_type,
                description: form.description,
                subject: form.subject,
                body: form.body,
                format: form.format,
                status: form.status,
                placeholders: form.placeholders,
            });
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            setMode("list");
            setForm(null);
            reloadRef.current();
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setSaving(false);
        }
    };

    const runPreview = async() => {
        if (!form) {
            return;
        }
        setPreviewing(true);
        try {
            const result = await callMoodleService<{subject: string; body: string}>(previewMethodName, {
                subject: form.subject,
                body: form.body,
            });
            setPreview(result);
        } catch (caught) {
            toast.error(errorText(caught));
        } finally {
            setPreviewing(false);
        }
    };

    const copyToken = (token: string) => {
        const copiedToken = navigator.clipboard?.writeText(token);
        if (!copiedToken) {
            return;
        }
        void copiedToken.then(() => {
            setCopied(token);
            window.setTimeout(() => setCopied(""), 1500);
        });
    };

    const updateForm = (changes: Partial<Template>) => {
        setForm((current) => current ? {...current, ...changes} : current);
    };

    const renderTemplateActions = (row: Row, missing: boolean) => (
        <div className={mcClasses("mc-table-design__actions justify-content-end")}>
            <McTableActionMenu
                label={`${labels.actions}: ${row.name}`}
                items={[
                    {
                        key: "edit",
                        label: labels.edit,
                        icon: "bi bi-pencil",
                        disabled: saving,
                        onClick: () => void openEdit(row.id),
                    },
                    {
                        key: "clone",
                        label: labels.clone,
                        icon: "bi bi-copy",
                        disabled: saving || missing,
                        title: missing ? labels.addonnotinstalled : labels.clone,
                        onClick: () => void cloneRow(row.id),
                    },
                    {
                        key: "delete",
                        label: labels.delete,
                        icon: "bi bi-trash",
                        disabled: saving || row.locked || missing,
                        danger: true,
                        title: missing ? labels.addonnotinstalled : (row.locked ? labels.locked : labels.delete),
                        onClick: () => void deleteRow(row),
                    },
                ]}
            />
        </div>
    );

    if (error && !data && mode === "list") {
        return (
            <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            </section>
        );
    }

    if (mode === "edit" && form) {
        const isNew = form.id === 0;
        const lockedCore = !isNew && form.locked;
        const addonMissing = !componentInstalled(form.component);
        const readOnly = lockedCore || addonMissing;
        return (
            <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
                {error && (
                    <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                        <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{error}</div>
                    </div>
                )}

                {addonMissing && (
                    <div className={mcClasses("mc-alert mc-alert--warning")} role="status">
                        <i className="bi bi-box-seam mc-alert__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>
                            <div className="fw-semibold">{labels.addonnotinstalled}</div>
                            <div>{labels.addonnotinstalleddesc}</div>
                            {addonsUrl && (
                                <a className={mcClasses("mc-button mc-btn-soft mt-2")} href={addonsUrl}>
                                    <i className="bi bi-puzzle me-1" aria-hidden="true" />
                                    {labels.manageaddons}
                                </a>
                            )}
                        </div>
                    </div>
                )}

                <div className={mcClasses("mc-card mb-3")}>
                    <div className={mcClasses("mc-card-body")}>
                        {addonMissing && (
                            <McBadge variant="warning" tone="soft" className="mb-3">{labels.addonnotinstalled}</McBadge>
                        )}
                        {lockedCore && !addonMissing && (
                            <McBadge variant="info" tone="soft" className="mb-3">{labels.locked}</McBadge>
                        )}
                        <div className="row g-3">
                            <label className="col-12 col-lg-6">
                                <span className={mcClasses("mc-field-label")}>{labels.templatename}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    disabled={readOnly}
                                    onChange={(event) => updateForm({name: event.target.value})}
                                    type="text"
                                    value={form.name}
                                />
                            </label>
                            <label className="col-12 col-lg-6">
                                <span className={mcClasses("mc-field-label")}>{labels.templatekey}</span>
                                <input
                                    className={mcClasses("mc-form-control mc-cell-mono")}
                                    disabled={!isNew}
                                    onChange={(event) => updateForm({template_key: event.target.value})}
                                    placeholder={isNew ? labels.keyautohelp : ""}
                                    type="text"
                                    value={form.template_key}
                                />
                                <span className={mcClasses("mc-cell-muted small")}>{isNew ? labels.keyautohelp : labels.keyhelp}</span>
                            </label>
                            <label className="col-12 col-lg-4">
                                <span className={mcClasses("mc-field-label")}>{labels.component}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    disabled={readOnly}
                                    list="mc-et-components"
                                    onChange={(event) => updateForm({component: event.target.value})}
                                    type="text"
                                    value={form.component}
                                />
                                <datalist id="mc-et-components">
                                    {metadata?.components.map((option) => (
                                        <option key={option.value} value={option.value} />
                                    ))}
                                </datalist>
                            </label>
                            <label className="col-12 col-lg-4">
                                <span className={mcClasses("mc-field-label")}>{labels.templatetype}</span>
                                <select
                                    className={mcClasses("mc-form-control")}
                                    disabled={readOnly}
                                    onChange={(event) => updateForm({template_type: event.target.value})}
                                    value={form.template_type}
                                >
                                    {metadata?.types.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </label>
                            <label className="col-12 col-lg-4">
                                <span className={mcClasses("mc-field-label")}>{labels.status}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    disabled={addonMissing}
                                    onChange={(event) => updateForm({status: event.target.value})}
                                    value={form.status}
                                >
                                    <option value="active">{labels.active}</option>
                                    <option value="inactive">{labels.inactive}</option>
                                </select>
                            </label>
                            <label className="col-12">
                                <span className={mcClasses("mc-field-label")}>{labels.description}</span>
                                <textarea
                                    className={mcClasses("mc-form-control")}
                                    disabled={addonMissing}
                                    onChange={(event) => updateForm({description: event.target.value})}
                                    rows={2}
                                    value={form.description}
                                />
                            </label>
                            <label className="col-12">
                                <span className={mcClasses("mc-field-label")}>{labels.subject}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    disabled={addonMissing}
                                    onChange={(event) => updateForm({subject: event.target.value})}
                                    type="text"
                                    value={form.subject}
                                />
                            </label>
                            <label className="col-12">
                                <span className={mcClasses("mc-field-label")}>{labels.body}</span>
                                <textarea
                                    className={mcClasses("form-control form-control-sm mc-code-textarea")}
                                    disabled={addonMissing}
                                    onChange={(event) => updateForm({body: event.target.value})}
                                    rows={16}
                                    value={form.body}
                                />
                            </label>
                        </div>
                    </div>
                </div>

                {metadata && metadata.placeholders.length > 0 && (
                    <div className={mcClasses("mc-card mb-3")}>
                        <div className={mcClasses("mc-card-header")}>
                            <h3 className={mcClasses("mc-card-title mb-1")}>{labels.placeholders}</h3>
                            <p className={mcClasses("mc-cell-muted small mb-0")}>{labels.insertplaceholder}</p>
                        </div>
                        <div className={mcClasses("mc-card-body d-flex flex-wrap gap-1")}>
                            {metadata.placeholders.map((placeholder) => (
                                <button
                                    className={mcClasses("mc-button mc-badge mc-badge--neutral mc-cell-mono mc-placeholder-chip")}
                                    data-mc-button="light"
                                    key={placeholder.token}
                                    onClick={() => copyToken(placeholder.token)}
                                    title={placeholder.description}
                                    type="button"
                                >
                                    {copied === placeholder.token ? labels.copied : placeholder.token}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                <div className={mcClasses("mc-card mb-3")}>
                    <div className={mcClasses("mc-card-header d-flex justify-content-between align-items-center")}>
                        <h3 className={mcClasses("mc-card-title mb-0")}>{labels.preview}</h3>
                        <button className={mcClasses("mc-button mc-btn-soft")} disabled={previewing} onClick={runPreview} type="button">
                            {labels.refreshpreview}
                        </button>
                    </div>
                    {preview && (
                        <div className={mcClasses("mc-card-body")}>
                            <div className="fw-semibold mb-2">{preview.subject}</div>
                            <iframe
                                className="mc-email-preview-frame"
                                sandbox=""
                                srcDoc={preview.body}
                                style={{width: "100%", minHeight: "420px", border: "1px solid #e5e7eb", borderRadius: "6px"}}
                                title={labels.preview}
                            />
                        </div>
                    )}
                </div>

                <div className="d-flex gap-2">
                    <McButton
                        className={mcClasses("btn-mc-primary")}
                        disabled={addonMissing}
                        loading={saving}
                        loadingLabel={labels.saving || "Saving..."}
                        onClick={saveForm}
                        type="button"
                    >
                        {labels.save}
                    </McButton>
                    <button
                        className={mcClasses("mc-button mc-btn-soft")}
                        disabled={saving}
                        onClick={() => {
                            setMode("list");
                            setForm(null);
                            setError("");
                        }}
                        type="button"
                    >
                        {labels.cancel}
                    </button>
                </div>
            </section>
        );
    }

    const total = data?.total ?? 0;
    const totalPages = Math.max(1, Math.ceil(total / filters.perpage));
    const stats = data?.stats;
    const visibleCount = data?.items.length ?? 0;
    const visibleFrom = total === 0 || visibleCount === 0 ? 0 : filters.page * filters.perpage + 1;
    const visibleTo = visibleCount === 0 ? 0 : Math.min(total, visibleFrom + visibleCount - 1);
    const componentCount = metadata?.components.length ?? 0;

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
            {stats && (
                <div className={mcClasses("mc-stat-strip")} aria-label={labels.title}>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--primary")}>
                        <i className="bi bi-envelope mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.total}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.total)}</strong>
                        </div>
                        <i className="bi bi-envelope mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                        <i className="bi bi-check-circle mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.active}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.active)}</strong>
                        </div>
                        <i className="bi bi-check-circle mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--neutral")}>
                        <i className="bi bi-slash-circle mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.inactive}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.inactive)}</strong>
                        </div>
                        <i className="bi bi-slash-circle mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--info")}>
                        <i className="bi bi-grid-3x3-gap mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.components}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(componentCount)}</strong>
                        </div>
                        <i className="bi bi-grid-3x3-gap mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                </div>
            )}

            <EmailsAdmin
                getMethodName={notificationGetMethodName}
                key={notificationsVersion}
                labels={notificationLabels}
                listMethodName={notificationListMethodName}
                openType={notificationOpenType}
                saveMethodName={notificationSaveMethodName}
            />

            <McTableCard
                title={(
                    <div>
                        <h3 className={mcClasses("mc-card-title mb-1")}>{labels.contentlibrary ?? labels.templates}</h3>
                        {labels.contentlibrarydesc && (
                            <p className={mcClasses("mc-cell-muted small mb-0")}>{labels.contentlibrarydesc}</p>
                        )}
                    </div>
                )}
                actions={(
                    <button className={mcClasses("mc-button btn-mc-primary")} onClick={openNew} type="button">
                        <i className="bi bi-plus-lg me-1" aria-hidden="true" />
                        {labels.newtemplate}
                    </button>
                )}
                toolbar={(
                    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                        <div className={mcClasses("mc-product-toolbar__search")}>
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-et-search">{labels.search}</label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-et-search"
                                onChange={(event) => setSearchInput(event.target.value)}
                                placeholder={labels.search}
                                type="search"
                                value={searchInput}
                            />
                        </div>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.component}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({component: event.target.value, page: 0})}
                                value={filters.component}
                            >
                                <option value="">{labels.allcomponents}</option>
                                {metadata?.components.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                        </label>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.type}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({type: event.target.value, page: 0})}
                                value={filters.type}
                            >
                                <option value="">{labels.alltypes}</option>
                                {metadata?.types.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                        </label>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.status}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({status: event.target.value, page: 0})}
                                value={filters.status}
                            >
                                <option value="">{labels.allstatuses}</option>
                                <option value="active">{labels.active}</option>
                                <option value="inactive">{labels.inactive}</option>
                            </select>
                        </label>
                        <label className={mcClasses("mc-product-toolbar__field mc-product-toolbar__field--small")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.perpage}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({perpage: Number(event.target.value) || PER_PAGE, page: 0})}
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
                        summary={(
                            <span>
                                {labels.showing} {formatCount(visibleFrom)}-{formatCount(visibleTo)} / {formatCount(total)}
                            </span>
                        )}
                        pagination={(
                            <McTablePagination
                                previousLabel={labels.previous}
                                nextLabel={labels.next}
                                pageLabel={labels.page}
                                page={Math.min(filters.page + 1, totalPages)}
                                totalPages={totalPages}
                                previousDisabled={loading || filters.page <= 0}
                                nextDisabled={loading || filters.page + 1 >= totalPages}
                                onPrevious={() => updateFilters({page: Math.max(0, filters.page - 1)})}
                                onNext={() => updateFilters({page: filters.page + 1})}
                            />
                        )}
                    />
                )}
            >
                <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.title}>
                            <thead>
                                <tr>
                                    <th scope="col">{labels.name}</th>
                                    <th scope="col">{labels.component}</th>
                                    <th scope="col">{labels.type}</th>
                                    <th scope="col">{labels.status}</th>
                                    <th scope="col" className="text-end">{labels.modified}</th>
                                    <th scope="col" className="text-end">{labels.actions}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {!loading && data && data.items.length === 0 && (
                                    <tr>
                                        <td colSpan={6}>
                                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                <span className={mcClasses("mc-empty__icon")}>
                                                    <i className="bi bi-envelope" aria-hidden="true" />
                                                </span>
                                                <p className={mcClasses("mc-empty__title")}>{labels.notemplates}</p>
                                                <button className={mcClasses("mc-button btn-mc-primary")} onClick={openNew} type="button">
                                                    <i className="bi bi-plus-lg me-1" aria-hidden="true" />
                                                    {labels.newtemplate}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {data?.items.map((row) => {
                                    const missing = !componentInstalled(row.component);
                                    return (
                                    <tr key={row.id}>
                                        <td>
                                            <div className="fw-semibold">
                                                {row.name}
                                                {row.locked && !missing && (
                                                    <McBadge variant="info" tone="soft" className="ms-2">{labels.locked}</McBadge>
                                                )}
                                                {missing && (
                                                    <McBadge variant="warning" tone="soft" className="ms-2">{labels.addonnotinstalled}</McBadge>
                                                )}
                                            </div>
                                            <div className={mcClasses("mc-cell-muted mc-cell-mono small")}>{row.template_key}</div>
                                        </td>
                                        <td className={mcClasses("mc-cell-muted")}>{row.component}</td>
                                        <td>
                                            {row.template_type
                                                ? <McBadge variant="neutral" tone="soft">{row.template_type}</McBadge>
                                                : <span className={mcClasses("mc-cell-muted")}>-</span>}
                                        </td>
                                        <td>
                                            <McBadge variant={row.status === "active" ? "success" : "neutral"} tone="soft" dot>
                                                {row.status === "active" ? labels.active : labels.inactive}
                                            </McBadge>
                                        </td>
                                        <td className={mcClasses("text-end mc-cell-muted")}>{formatDate(row.timemodified)}</td>
                                        <td className="text-end">
                                            {renderTemplateActions(row, missing)}
                                        </td>
                                    </tr>
                                    );
                                })}
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

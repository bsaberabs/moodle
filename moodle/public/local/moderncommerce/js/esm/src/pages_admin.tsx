// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin manager for Modern Commerce public store pages.
 *
 * @module     local_moderncommerce/pages_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McBadge} from "./badge";
import {McButton} from "./button";
import {McDrawer} from "./drawer";
import {IconPicker, normIcon} from "./icon_picker";
import type {IconOption} from "./icon_picker";
import {confirmDialog} from "./modal";
import {McTableActionMenu, McTableCard, McTableFooter} from "./table_components";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type Methods = {
    getLayout: string;
    saveLayout: string;
    togglePage: string;
    listPages: string;
    savePage: string;
    deletePage: string;
    checkSlug: string;
};

/** A page whose sections and widgets can seed a new page. */
type CopySource = {
    key: string;
    title: string;
    template: string;
    widgets: number;
};

type PageConfig = {
    id: number;
    key: string;
    title: string;
    summary: string;
    icon: string;
    url: string;
    enabled: boolean;
    required: boolean;
    custom: boolean;
    template: string;
};

type TemplateOption = {
    value: string;
    label: string;
    description: string;
};

/** Draft state backing the create/edit page form. */
type PageDraft = {
    id: number;
    slug: string;
    title: string;
    summary: string;
    icon: string;
    template: string;
    enabled: boolean;
    /** Page key to clone widgets from, or "" for a blank page. */
    copyfrom: string;
    /** True once the admin edits the slug by hand, which stops title-tracking. */
    slugtouched: boolean;
};

/** Live result of the slug availability check. */
type SlugState = {
    status: "idle" | "checking" | "ok" | "taken";
    message: string;
    url: string;
};

const DEFAULT_ICON = "file-text";

const emptyDraft = (template: string): PageDraft => ({
    id: 0,
    slug: "",
    title: "",
    summary: "",
    icon: DEFAULT_ICON,
    template,
    enabled: true,
    copyfrom: "",
    slugtouched: false,
});

/**
 * Convert free text into a URL slug matching the server's rules: lowercase, single
 * hyphens, no underscores. The server re-normalises and validates on save; this only
 * keeps the field honest while typing.
 *
 * @param value Raw input.
 * @returns The normalised slug.
 */
const slugify = (value: string): string => value
    .toLowerCase()
    .replace(/[\s_]+/g, "-")
    .replace(/[^a-z0-9-]/g, "")
    .replace(/-+/g, "-")
    .replace(/^-|-$/g, "")
    .slice(0, 30);

type Zone = {
    slug: string;
    label: string;
};

type Widget = {
    id: number;
    type: string;
    typelabel: string;
    zone: string;
    enabled: boolean;
    sortorder: number;
    title: string;
};

type LayoutResponse = {
    widgets: Widget[];
    zones: Zone[];
};

type SaveResponse = {
    success: boolean;
    message: string;
};

type TogglePageResponse = {
    success: boolean;
    message: string;
    page: {
        key: string;
        enabled: boolean;
    };
};

type ListPagesResponse = {
    pages: PageConfig[];
};

type SavePageResponse = {
    success: boolean;
    message: string;
    page: PageConfig;
};

type DeletePageResponse = {
    success: boolean;
    message: string;
};

type SlugCheckResponse = {
    slug: string;
    available: boolean;
    message: string;
    url: string;
};

type Props = {
    pages: PageConfig[];
    methods: Methods;
    globalUrl: string;
    templates: TemplateOption[];
    copySources: CopySource[];
    iconOptions: IconOption[];
    labels: Labels;
};

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

export default function PagesAdmin(
    {pages, methods, globalUrl, templates, copySources, iconOptions, labels}: Props
) {
    useModernCommerceClassSync();

    const defaultTemplate = templates[0]?.value ?? "content";

    const [pageRows, setPageRows] = useState<PageConfig[]>(pages);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [selectedPage, setSelectedPage] = useState<PageConfig | null>(null);
    const [layout, setLayout] = useState<LayoutResponse | null>(null);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [busyPage, setBusyPage] = useState("");
    const [error, setError] = useState("");

    const [formOpen, setFormOpen] = useState(false);
    const [draft, setDraft] = useState<PageDraft>(() => emptyDraft(defaultTemplate));
    const [formError, setFormError] = useState("");
    const [formSaving, setFormSaving] = useState(false);
    const [slugState, setSlugState] = useState<SlugState>({status: "idle", message: "", url: ""});

    useEffect(() => {
        setPageRows(pages);
    }, [pages]);

    // Debounced availability check. Only creates need it: the slug is fixed after that,
    // and the effect is skipped entirely once the form is closed.
    const checkSlug = draft.id === 0 && formOpen ? draft.slug : "";
    useEffect(() => {
        if (checkSlug === "") {
            setSlugState({status: "idle", message: "", url: ""});
            return undefined;
        }

        let cancelled = false;
        setSlugState((current) => ({...current, status: "checking"}));
        const timer = window.setTimeout(() => {
            void callMoodleService<SlugCheckResponse>(methods.checkSlug, {slug: checkSlug, excludeid: 0})
                .then((result) => {
                    if (cancelled) {
                        return;
                    }
                    setSlugState({
                        status: result.available ? "ok" : "taken",
                        message: result.message,
                        url: result.url,
                    });
                })
                .catch(() => {
                    if (!cancelled) {
                        // A failed check must not block saving; the server validates again.
                        setSlugState({status: "idle", message: "", url: ""});
                    }
                });
        }, 400);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [checkSlug, methods.checkSlug]);

    const refreshPages = async() => {
        const result = await callMoodleService<ListPagesResponse>(methods.listPages, {});
        setPageRows(result.pages ?? []);
    };

    const openDrawer = (page: PageConfig) => {
        setSelectedPage(page);
        setDrawerOpen(true);
        setLayout(null);
        setError("");
        setLoading(true);

        void callMoodleService<LayoutResponse>(methods.getLayout, {page: page.key})
            .then((result) => setLayout({
                zones: result.zones ?? [],
                widgets: result.widgets ?? [],
            }))
            .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : String(caught)))
            .finally(() => setLoading(false));
    };

    const closeDrawer = () => {
        if (saving) {
            return;
        }
        setDrawerOpen(false);
    };

    const zoneWidgets = (zone: string): Widget[] => {
        return (layout?.widgets ?? []).filter((widget) => widget.zone === zone);
    };

    const swapWithinZone = (widgetId: number, direction: number) => {
        if (!layout) {
            return;
        }

        const widget = layout.widgets.find((row) => row.id === widgetId);
        if (!widget) {
            return;
        }

        const zoneIndexes = layout.widgets
            .map((row, index) => row.zone === widget.zone ? index : -1)
            .filter((index) => index >= 0);
        const currentPosition = zoneIndexes.findIndex((index) => layout.widgets[index].id === widgetId);
        const targetPosition = currentPosition + direction;
        if (currentPosition < 0 || targetPosition < 0 || targetPosition >= zoneIndexes.length) {
            return;
        }

        const nextWidgets = [...layout.widgets];
        const currentIndex = zoneIndexes[currentPosition];
        const targetIndex = zoneIndexes[targetPosition];
        [nextWidgets[currentIndex], nextWidgets[targetIndex]] = [nextWidgets[targetIndex], nextWidgets[currentIndex]];
        setLayout({...layout, widgets: nextWidgets});
    };

    const toggleWidget = (widgetId: number, enabled: boolean) => {
        if (!layout) {
            return;
        }
        setLayout({
            ...layout,
            widgets: layout.widgets.map((widget) => widget.id === widgetId ? {...widget, enabled} : widget),
        });
    };

    const saveLayout = async() => {
        if (!layout || !selectedPage) {
            return;
        }

        const zoneCounters: Record<string, number> = {};
        const items = layout.widgets.map((widget) => {
            const sortorder = zoneCounters[widget.zone] ?? 0;
            zoneCounters[widget.zone] = sortorder + 1;

            return {
                id: widget.id,
                zone: widget.zone,
                enabled: widget.enabled,
                sortorder,
            };
        });

        setSaving(true);
        setError("");

        try {
            const result = await callMoodleService<SaveResponse>(methods.saveLayout, {
                page: selectedPage.key,
                items,
            });
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            const refreshed = await callMoodleService<LayoutResponse>(methods.getLayout, {page: selectedPage.key});
            setLayout({
                zones: refreshed.zones ?? [],
                widgets: refreshed.widgets ?? [],
            });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const togglePage = async(page: PageConfig) => {
        if (page.required) {
            return;
        }

        setBusyPage(page.key);
        setError("");

        try {
            const result = await callMoodleService<TogglePageResponse>(methods.togglePage, {
                page: page.key,
                enabled: !page.enabled,
            });
            if (!result.success) {
                setError(result.message);
                return;
            }

            setPageRows((current) => current.map((row) => row.key === result.page.key
                ? {...row, enabled: result.page.enabled}
                : row));
            toast.success(result.message);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setBusyPage("");
        }
    };

    const openCreateForm = () => {
        setDraft(emptyDraft(defaultTemplate));
        setSlugState({status: "idle", message: "", url: ""});
        setFormError("");
        setFormOpen(true);
    };

    const openEditForm = (page: PageConfig) => {
        setDraft({
            id: page.id,
            slug: page.key,
            title: page.title,
            summary: page.summary,
            icon: normIcon(page.icon) || DEFAULT_ICON,
            template: page.template || defaultTemplate,
            enabled: page.enabled,
            copyfrom: "",
            slugtouched: true,
        });
        setSlugState({status: "idle", message: "", url: ""});
        setFormError("");
        setFormOpen(true);
    };

    /**
     * Update the title, and mirror it into the slug until the admin edits the slug
     * directly. Editing an existing page never re-slugs, since the slug is immutable.
     *
     * @param title New title text.
     */
    const updateTitle = (title: string) => {
        setDraft((current) => ({
            ...current,
            title,
            slug: current.id === 0 && !current.slugtouched ? slugify(title) : current.slug,
        }));
    };

    const closeForm = () => {
        if (formSaving) {
            return;
        }
        setFormOpen(false);
    };

    const updateDraft = (patch: Partial<PageDraft>) => setDraft((current) => ({...current, ...patch}));

    const savePage = async() => {
        const isCreate = draft.id === 0;
        const slug = slugify(draft.slug || draft.title);

        if (draft.title.trim() === "") {
            setFormError(labels.storepage_error_titlerequired);
            return;
        }
        if (isCreate && slug === "") {
            setFormError(labels.storepage_error_slugrequired);
            return;
        }
        if (isCreate && slugState.status === "taken") {
            setFormError(slugState.message);
            return;
        }

        setFormSaving(true);
        setFormError("");

        try {
            const result = await callMoodleService<SavePageResponse>(methods.savePage, {
                id: draft.id,
                slug,
                title: draft.title.trim(),
                summary: draft.summary.trim(),
                icon: `bi-${normIcon(draft.icon) || DEFAULT_ICON}`,
                template: draft.template,
                enabled: draft.enabled,
                copyfrom: isCreate ? draft.copyfrom : "",
            });
            if (!result.success) {
                setFormError(result.message);
                return;
            }
            await refreshPages();
            toast.success(result.message);
            setFormOpen(false);
        } catch (caught) {
            setFormError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setFormSaving(false);
        }
    };

    const deletePage = async(page: PageConfig) => {
        const confirmed = await confirmDialog({
            title: labels.storepage_deletepage,
            message: (labels.storepage_deleteconfirm || "").replace("{$a}", page.title),
            confirmLabel: labels.storepage_deletepage,
            cancelLabel: labels.cancel,
            danger: true,
        });
        if (!confirmed) {
            return;
        }

        setBusyPage(page.key);
        setError("");

        try {
            const result = await callMoodleService<DeletePageResponse>(methods.deletePage, {id: page.id});
            if (!result.success) {
                setError(result.message);
                return;
            }
            await refreshPages();
            toast.success(result.message);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setBusyPage("");
        }
    };

    const renderForm = () => {
        if (!formOpen) {
            return null;
        }

        const isCreate = draft.id === 0;

        return (
            <McDrawer
                title={isCreate ? labels.storepage_newpage : labels.storepage_editpage}
                subtitle={isCreate ? "" : draft.slug}
                onClose={closeForm}
                closeLabel={labels.close}
                disableClose={formSaving}
                backdropClassName="mc-drawer-backdrop--open"
                footer={(
                    <>
                        <McButton
                            className={mcClasses("btn-mc-primary")}
                            loading={formSaving}
                            loadingLabel={labels.saving || "Saving..."}
                            onClick={savePage}
                            type="button"
                        >
                            {labels.savechanges}
                        </McButton>
                        <button
                            className={mcClasses("mc-button btn-mc-secondary")}
                            disabled={formSaving}
                            onClick={closeForm}
                            type="button"
                        >
                            {labels.cancel}
                        </button>
                    </>
                )}
            >
                {formError !== "" && (
                    <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                        <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{formError}</div>
                    </div>
                )}

                <div className={mcClasses("mc-product-form__grid")}>
                    <label className={mcClasses("mc-product-form__wide")} htmlFor="mc-storepage-title">
                        <span>
                            {labels.storepage_title}
                            <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                        </span>
                        <input
                            autoFocus
                            aria-required="true"
                            className={mcClasses("mc-form-control")}
                            disabled={formSaving}
                            id="mc-storepage-title"
                            maxLength={255}
                            onChange={(event) => updateTitle(event.target.value)}
                            required
                            type="text"
                            value={draft.title}
                        />
                    </label>

                    <label className={mcClasses("mc-product-form__wide")} htmlFor="mc-storepage-slug">
                        <span>{labels.storepage_slug}</span>
                        <input
                            aria-invalid={slugState.status === "taken" ? true : undefined}
                            className={mcClasses("mc-form-control", slugState.status === "taken" ? "mc-field-invalid" : "")}
                            disabled={formSaving || !isCreate}
                            id="mc-storepage-slug"
                            maxLength={30}
                            onBlur={(event) => updateDraft({slug: slugify(event.target.value)})}
                            onChange={(event) => updateDraft({slug: event.target.value, slugtouched: true})}
                            type="text"
                            value={draft.slug}
                        />
                        {isCreate && slugState.status === "checking" && (
                            <span className={mcClasses("mc-field-help")}>{labels.storepage_slug_checking}</span>
                        )}
                        {isCreate && slugState.status === "ok" && (
                            <span className={mcClasses("mc-field-help mc-storepage-slug--ok")}>
                                <i className="bi bi-check-circle" aria-hidden="true" /> {slugState.url}
                            </span>
                        )}
                        {isCreate && slugState.status === "taken" && (
                            <span className={mcClasses("mc-field-help mc-storepage-slug--taken")} role="alert">
                                <i className="bi bi-exclamation-circle" aria-hidden="true" /> {slugState.message}
                            </span>
                        )}
                        {(!isCreate || slugState.status === "idle") && (
                            <span className={mcClasses("mc-field-help")}>
                                {isCreate ? labels.storepage_slug_help : labels.storepage_sluglocked}
                            </span>
                        )}
                    </label>

                    <label className={mcClasses("mc-product-form__wide")} htmlFor="mc-storepage-summary">
                        <span>{labels.storepage_summary}</span>
                        <textarea
                            className={mcClasses("mc-form-control")}
                            disabled={formSaving}
                            id="mc-storepage-summary"
                            maxLength={255}
                            onChange={(event) => updateDraft({summary: event.target.value})}
                            rows={3}
                            value={draft.summary}
                        />
                    </label>

                    <div className={mcClasses("mc-product-form__wide mc-storepage-iconfield")}>
                        <IconPicker
                            disabled={formSaving}
                            fallback={DEFAULT_ICON}
                            help={labels.storepage_icon_help}
                            label={labels.storepage_icon}
                            onChange={(value) => updateDraft({icon: value})}
                            options={iconOptions}
                            value={draft.icon}
                        />
                    </div>

                    <fieldset
                        className={mcClasses("mc-product-form__wide mc-storepage-templates")}
                        disabled={formSaving || !isCreate}
                    >
                        <legend className={mcClasses("mc-form-section__title")}>
                            {isCreate ? labels.storepage_copyfrom : labels.storepage_template}
                        </legend>

                        {isCreate && templates.map((option) => (
                            <label className={mcClasses("mc-storepage-template-option")} key={option.value}>
                                <input
                                    checked={draft.copyfrom === "" && draft.template === option.value}
                                    name="mc-storepage-start"
                                    onChange={() => updateDraft({template: option.value, copyfrom: ""})}
                                    type="radio"
                                />
                                <span>
                                    <span className={mcClasses("mc-storepage-template-option__label")}>{option.label}</span>
                                    <span className={mcClasses("mc-cell-muted small d-block")}>{option.description}</span>
                                </span>
                            </label>
                        ))}

                        {isCreate && copySources.length > 0 && (
                            <label className={mcClasses("mc-storepage-template-option")}>
                                <input
                                    checked={draft.copyfrom !== ""}
                                    name="mc-storepage-start"
                                    onChange={() => updateDraft({copyfrom: copySources[0].key})}
                                    type="radio"
                                />
                                <span>
                                    <span className={mcClasses("mc-storepage-template-option__label")}>
                                        {labels.storepage_copyfrom_existing}
                                    </span>
                                    <select
                                        aria-label={labels.storepage_copyfrom_existing}
                                        className={mcClasses("mc-select mc-storepage-copysource")}
                                        disabled={formSaving || draft.copyfrom === ""}
                                        onChange={(event) => updateDraft({copyfrom: event.target.value})}
                                        value={draft.copyfrom || copySources[0].key}
                                    >
                                        {copySources.map((source) => (
                                            <option key={source.key} value={source.key}>
                                                {source.title} ({source.widgets})
                                            </option>
                                        ))}
                                    </select>
                                    <span className={mcClasses("mc-cell-muted small d-block")}>
                                        {labels.storepage_copyfrom_help}
                                    </span>
                                </span>
                            </label>
                        )}

                        {!isCreate && (
                            <>
                                <p className={mcClasses("mc-storepage-template-option__label mb-1")}>
                                    {templates.find((t) => t.value === draft.template)?.label ?? draft.template}
                                </p>
                                <span className={mcClasses("mc-field-help")}>{labels.storepage_templatelocked}</span>
                            </>
                        )}
                    </fieldset>

                    <label className={mcClasses("mc-product-form__wide")} htmlFor="mc-storepage-published">
                        <span>{labels.storepage_published}</span>
                        <select
                            className={mcClasses("mc-select")}
                            disabled={formSaving}
                            id="mc-storepage-published"
                            onChange={(event) => updateDraft({enabled: event.target.value === "1"})}
                            value={draft.enabled ? "1" : "0"}
                        >
                            <option value="1">{labels.storepage_published_yes}</option>
                            <option value="0">{labels.storepage_published_no}</option>
                        </select>
                        <span className={mcClasses("mc-field-help")}>{labels.storepage_published_help}</span>
                    </label>
                </div>
            </McDrawer>
        );
    };

    const renderDrawer = () => {
        if (!drawerOpen || !selectedPage) {
            return null;
        }

        return (
            <McDrawer
                title={selectedPage.title}
                subtitle={labels.pagewidgets}
                onClose={closeDrawer}
                closeLabel={labels.close}
                disableClose={saving}
                backdropClassName="mc-drawer-backdrop--open"
                footer={(
                    <>
                        <McButton
                            className={mcClasses("btn-mc-primary")}
                            disabled={loading || !layout}
                            loading={saving}
                            loadingLabel={labels.saving || "Saving..."}
                            onClick={saveLayout}
                            type="button"
                        >
                            {labels.savechanges}
                        </McButton>
                        <button
                            className={mcClasses("mc-button btn-mc-secondary")}
                            disabled={saving}
                            onClick={closeDrawer}
                            type="button"
                        >
                            {labels.cancel}
                        </button>
                    </>
                )}
            >
                        {error !== "" && (
                            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                                <div className={mcClasses("mc-alert__body")}>{error}</div>
                            </div>
                        )}

                        {loading && <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>}

                        {!loading && layout && layout.widgets.length === 0 && (
                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                <span className={mcClasses("mc-empty__icon")}><i className="bi bi-layout-sidebar" aria-hidden="true" /></span>
                                <p className={mcClasses("mc-empty__title")}>{labels.nowidgetsassigned}</p>
                            </div>
                        )}

                        {!loading && layout && layout.zones.map((zone) => {
                            const widgets = zoneWidgets(zone.slug);
                            if (widgets.length === 0) {
                                return null;
                            }

                            return (
                                <section className={mcClasses("mc-page-layout-zone")} key={zone.slug}>
                                    <h3 className={mcClasses("mc-sf-section__title")}>{zone.label}</h3>
                                    {widgets.map((widget, index) => (
                                        <div
                                            className={mcClasses(widget.enabled
                                                ? "mc-sf-row mc-page-layout-row"
                                                : "mc-sf-row mc-page-layout-row mc-sf-row--off")}
                                            key={widget.id}
                                        >
                                            <div className={mcClasses("mc-sf-row__reorder")}>
                                                <button
                                                    aria-label={labels.moveup}
                                                    className={mcClasses("mc-button mc-btn-soft")}
                                                    data-mc-button="soft"
                                                    data-mc-button-size="icon"
                                                    disabled={index === 0 || saving}
                                                    onClick={() => swapWithinZone(widget.id, -1)}
                                                    title={labels.moveup}
                                                    type="button"
                                                >
                                                    <i className="bi bi-chevron-up" aria-hidden="true" />
                                                </button>
                                                <button
                                                    aria-label={labels.movedown}
                                                    className={mcClasses("mc-button mc-btn-soft")}
                                                    data-mc-button="soft"
                                                    data-mc-button-size="icon"
                                                    disabled={index === widgets.length - 1 || saving}
                                                    onClick={() => swapWithinZone(widget.id, 1)}
                                                    title={labels.movedown}
                                                    type="button"
                                                >
                                                    <i className="bi bi-chevron-down" aria-hidden="true" />
                                                </button>
                                            </div>
                                            <label className={mcClasses("mc-page-layout-toggle")} title={labels.show}>
                                                <input
                                                    checked={widget.enabled}
                                                    disabled={saving}
                                                    onChange={(event) => toggleWidget(widget.id, event.target.checked)}
                                                    type="checkbox"
                                                />
                                                {labels.show}
                                            </label>
                                            <span className={mcClasses("mc-sf-row__title")}>
                                                {widget.title || widget.typelabel || widget.type}
                                                <span className={mcClasses("mc-sf-row__type")}> / {widget.typelabel || widget.type}</span>
                                            </span>
                                        </div>
                                    ))}
                                </section>
                            );
                        })}
            </McDrawer>
        );
    };

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
            {renderDrawer()}
            {renderForm()}

            {error !== "" && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            <McTableCard
                title={(
                    <div>
                        <h3 className={mcClasses("mc-card-title mb-1")}>{labels.storepages}</h3>
                        <p className={mcClasses("mc-cell-muted small mb-0")}>
                            <i className="bi bi-globe2 me-1" aria-hidden="true" />
                            {labels.storeglobalintro}
                        </p>
                    </div>
                )}
                actions={(
                    <>
                        <a className={mcClasses("mc-button btn-mc-secondary")} href={globalUrl}>
                            <i className="bi bi-sliders" aria-hidden="true" />
                            <span>{labels.storeglobalmanage}</span>
                        </a>
                        <button
                            className={mcClasses("mc-button btn-mc-primary")}
                            onClick={openCreateForm}
                            type="button"
                        >
                            <i className="bi bi-plus-lg" aria-hidden="true" />
                            <span>{labels.storepage_addpage}</span>
                        </button>
                    </>
                )}
                footer={(
                    <McTableFooter
                        summary={<span>{labels.showing} {formatCount(pageRows.length)} / {formatCount(pageRows.length)}</span>}
                    />
                )}
            >
                <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.storepages}>
                    <thead>
                        <tr>
                            <th scope="col">{labels.page}</th>
                            <th scope="col">{labels.publicurl}</th>
                            <th scope="col">{labels.status}</th>
                            <th scope="col" className="text-end">{labels.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {pageRows.map((page) => (
                            <tr key={page.key}>
                                <td>
                                    <div className="d-flex align-items-center gap-2">
                                        <i className={`bi ${page.icon}`} aria-hidden="true" />
                                        <div>
                                            <div className="d-flex align-items-center gap-2">
                                                <span>{page.title}</span>
                                                {page.custom && (
                                                    <McBadge variant="info" tone="soft">
                                                        {templates.find((option) => option.value === page.template)?.label
                                                            ?? page.template}
                                                    </McBadge>
                                                )}
                                            </div>
                                            {page.summary !== "" && <div className={mcClasses("mc-cell-muted small")}>{page.summary}</div>}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a className={mcClasses("mc-storepage-url")} href={page.url} title={page.url}>
                                        {page.url}
                                    </a>
                                </td>
                                <td>
                                    <McBadge variant={page.enabled ? "success" : "neutral"} tone="soft" dot>
                                        {page.required ? labels.requiredpage : (page.enabled ? labels.enabled : labels.disabled)}
                                    </McBadge>
                                </td>
                                <td className="text-end">
                                    <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                        {!page.required && (
                                            <button
                                                aria-label={`${page.enabled ? labels.disablepage : labels.enablepage}: ${page.title}`}
                                                aria-pressed={page.enabled}
                                                className={mcClasses("mc-button", page.enabled
                                                    ? "mc-page-visibility-switch mc-page-visibility-switch--on"
                                                    : "mc-page-visibility-switch")}
                                                data-mc-button="ghost"
                                                disabled={busyPage === page.key}
                                                onClick={() => togglePage(page)}
                                                title={page.enabled ? labels.disablepage : labels.enablepage}
                                                type="button"
                                            >
                                                <span className={mcClasses("mc-page-visibility-switch__track")} aria-hidden="true">
                                                    <span className={mcClasses("mc-page-visibility-switch__knob")} />
                                                </span>
                                            </button>
                                        )}
                                        <McTableActionMenu
                                            label={`${labels.actions}: ${page.title}`}
                                            items={[
                                                {
                                                    key: "manage",
                                                    label: labels.managewidgets,
                                                    icon: "bi bi-gear",
                                                    onClick: () => openDrawer(page),
                                                },
                                                {
                                                    key: "preview",
                                                    label: labels.preview,
                                                    icon: "bi bi-eye",
                                                    href: page.url,
                                                },
                                                ...(page.custom ? [
                                                    {
                                                        key: "edit",
                                                        label: labels.storepage_editpage,
                                                        icon: "bi bi-pencil",
                                                        onClick: () => openEditForm(page),
                                                    },
                                                    {
                                                        key: "delete",
                                                        label: labels.storepage_deletepage,
                                                        icon: "bi bi-trash",
                                                        danger: true,
                                                        onClick: () => {
                                                            void deletePage(page);
                                                        },
                                                    },
                                                ] : []),
                                            ]}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </McTableCard>
        </section>
    );
}

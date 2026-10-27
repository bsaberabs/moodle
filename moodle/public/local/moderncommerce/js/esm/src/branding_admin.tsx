// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React branding editor for Modern Commerce.
 *
 * Recolours the whole design-system palette (every --mc-* colour token) for
 * both the storefront and the admin console, plus corner radius and an advanced
 * raw-CSS escape hatch. Fields are data-driven from the branding registry
 * (\local_moderncommerce\branding) via web services, so adding a token server
 * side surfaces it here automatically. Includes a live preview.
 *
 * @module     local_moderncommerce/branding_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useMemo, useState} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import EmailBrandingAdmin from "./email_branding_admin";
import {FieldError} from "./form";
import {focusFirstError} from "./validation";
import type {FieldErrorMap} from "./validation";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type Derived = {var: string; expr: string};

type Field = {
    key: string;
    group: string;
    grouplabel: string;
    label: string;
    type: "colour" | "text" | "length" | "css";
    var: string;
    value: string;
    default: string;
    derived: Derived[];
};

type GetResponse = {fields: Field[]};
type SaveResponse = {success: boolean; message: string};

type ColorsProps = {
    getMethodName: string;
    saveMethodName: string;
    labels: Labels;
};

type BrandingTab = "colours" | "email";

type Props = {
    getMethodName: string;
    saveMethodName: string;
    getShellMethodName: string;
    saveShellMethodName: string;
    previewShellMethodName: string;
    resetShellMethodName: string;
    brandPrimary?: string;
    brandSecondary?: string;
    tabLabels: Record<BrandingTab, string>;
    labels: Labels;
    emailLabels: Labels;
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

const isHex6 = (value: string): boolean => /^#[0-9a-fA-F]{6}$/.test(value);

// A valid 6-digit hex for the native colour input, which cannot show "empty".
const swatchValue = (value: string, fallback: string): string => {
    if (isHex6(value)) {
        return value;
    }
    if (isHex6(fallback)) {
        return fallback;
    }
    return "#000000";
};

function BrandColorsEditor({getMethodName, saveMethodName, labels}: ColorsProps) {
    useModernCommerceClassSync();
    const [fields, setFields] = useState<Field[]>([]);
    const [form, setForm] = useState<Record<string, string> | null>(null);
    const [saved, setSaved] = useState<Record<string, string> | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<FieldErrorMap>({});
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");
        void callMoodleService<GetResponse>(getMethodName, {})
            .then((result) => {
                if (cancelled) {
                    return;
                }
                const values: Record<string, string> = {};
                result.fields.forEach((f) => {
                    values[f.key] = f.value;
                });
                setFields(result.fields);
                setForm(values);
                setSaved(values);
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

    const groups = useMemo(() => {
        const order: string[] = [];
        const map: Record<string, {label: string; items: Field[]}> = {};
        fields.forEach((f) => {
            if (!map[f.group]) {
                map[f.group] = {label: f.grouplabel, items: []};
                order.push(f.group);
            }
            map[f.group].items.push(f);
        });
        return order.map((key) => ({key, label: map[key].label, items: map[key].items}));
    }, [fields]);

    // --mc-* variables for the live preview. Mirrors branding::build_css(): only
    // a seed the admin has set emits its variable plus its derived color-mix()
    // tokens; untouched seeds inherit the design-system defaults via the cascade.
    const previewStyle = useMemo(() => {
        const style: Record<string, string> = {};
        if (form) {
            fields.forEach((f) => {
                if (f.type === "css" || !f.var) {
                    return;
                }
                const value = form[f.key];
                if (!value) {
                    return;
                }
                style[f.var] = value;
                f.derived.forEach((d) => {
                    style[d.var] = d.expr;
                });
            });
        }
        return style as React.CSSProperties;
    }, [fields, form]);

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
    if (!form) {
        return null;
    }

    const update = (key: string, value: string) => setForm((c) => c ? {...c, [key]: value} : c);
    const reset = (key: string) => update(key, "");

    const resetAll = () => setForm((c) => {
        if (!c) {
            return c;
        }
        const next: Record<string, string> = {};
        Object.keys(c).forEach((k) => {
            next[k] = "";
        });
        return next;
    });

    const discard = () => {
        if (saved) {
            setForm({...saved});
        }
        setError("");
    };

    const submit = async() => {
        // isHex6 previously only drove the preview swatch: an invalid value
        // rendered as black locally and was still sent to the server.
        const found: FieldErrorMap = {};
        fields.forEach((field) => {
            if (field.type === "colour" && String(form[field.key] ?? "").trim() !== ""
                && !isHex6(String(form[field.key]))) {
                found[field.key] = labels["error:invalidcolour"] ?? "";
            }
        });
        setFieldErrors(found);
        if (Object.keys(found).length > 0) {
            focusFirstError(found, fields.map((field) => field.key));
            return;
        }

        setSaving(true);
        try {
            const payload = Object.keys(form).map((key) => ({key, value: form[key]}));
            const result = await callMoodleService<SaveResponse>(saveMethodName, {fields: payload});
            if (!result.success) {
                toast.error(result.message);
                return;
            }
            try {
                const fresh = await callMoodleService<GetResponse>(getMethodName, {});
                const values: Record<string, string> = {};
                fresh.fields.forEach((f) => {
                    values[f.key] = f.value;
                });
                setFields(fresh.fields);
                setForm(values);
                setSaved(values);
            } catch {
                setSaved({...form});
            }
            toast.success(result.message);
        } catch (caught) {
            toast.error(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const resetButton = (key: string) => (
        <button
            aria-label={labels.reset}
            className={mcClasses("mc-button mc-btn-icon", "mc-button mc-btn-icon--ghost", "mc-brand-field__reset")}
            onClick={() => reset(key)}
            title={labels.reset}
            type="button"
        >
            <i className="bi bi-arrow-counterclockwise" aria-hidden="true" />
        </button>
    );

    const renderField = (field: Field) => {
        const value = form[field.key] ?? "";
        const isset = value !== "";

        if (field.type === "css") {
            return (
                <div className={mcClasses("mc-brand-field", "mc-brand-field--full")} key={field.key}>
                    <div className={mcClasses("mc-brand-field__head")}>
                        <span className={mcClasses("mc-brand-field__label")}>{field.label}</span>
                        {isset && resetButton(field.key)}
                    </div>
                    <textarea
                        className={mcClasses("mc-form-control", "mc-code-textarea")}
                        onChange={(e) => update(field.key, e.target.value)}
                        rows={6}
                        spellCheck={false}
                        value={value}
                    />
                    <small className={mcClasses("mc-cell-muted")}>{labels.customcssdesc}</small>
                </div>
            );
        }

        return (
            <div className={mcClasses("mc-brand-field")} key={field.key}>
                <div className={mcClasses("mc-brand-field__head")}>
                    <span className={mcClasses("mc-brand-field__label")}>{field.label}</span>
                    {isset
                        ? resetButton(field.key)
                        : <span className={mcClasses("mc-brand-field__badge")}>{labels.usingdefault}</span>}
                </div>
                <div className={mcClasses("mc-brand-field__control")}>
                    {field.type === "colour" && (
                        <input
                            aria-label={field.label}
                            className={mcClasses("mc-brand-swatch")}
                            onChange={(e) => update(field.key, e.target.value)}
                            type="color"
                            value={swatchValue(value, field.default)}
                        />
                    )}
                    <input
                        aria-describedby={fieldErrors[field.key] ? `mc-brand-${field.key}-error` : undefined}
                        aria-invalid={fieldErrors[field.key] ? true : undefined}
                        className={mcClasses("mc-form-control", "mc-brand-field__text",
                            fieldErrors[field.key] ? "mc-field-invalid" : "")}
                        id={`mc-brand-${field.key}`}
                        name={field.key}
                        onChange={(e) => update(field.key, e.target.value)}
                        placeholder={field.default}
                        type="text"
                        value={value}
                    />
                </div>
                <FieldError id={`mc-brand-${field.key}-error`} message={fieldErrors[field.key]} />
            </div>
        );
    };

    return (
        <section className={mcClasses("mc-brand-layout")} aria-label={labels.intro}>
            <div className={mcClasses("mc-brand-editor")}>
                <p className={mcClasses("mc-cell-muted")}>{labels.intro}</p>
                {groups.map((group) => (
                    <div className={mcClasses("mc-brand-group")} key={group.key}>
                        <h4 className={mcClasses("mc-brand-group__title")}>{group.label}</h4>
                        <div className={mcClasses("mc-brand-grid")}>
                            {group.items.map((field) => renderField(field))}
                        </div>
                    </div>
                ))}
            </div>

            <aside className={mcClasses("mc-brand-preview")} aria-label={labels.preview}>
                <div className={mcClasses("mc-brand-preview__header")}>
                    <h4 className={mcClasses("mc-brand-preview__title")}>{labels.preview}</h4>
                    <span className={mcClasses(dirty
                        ? "mc-brand-preview__state mc-brand-preview__state--dirty"
                        : "mc-brand-preview__state")}>
                        {dirty ? labels.unsaved : labels.previewbadge}
                    </span>
                </div>
                <div className={mcClasses("mc-brand-preview__stage")} style={previewStyle}>
                    <div className={mcClasses("mc-brand-preview__chrome")} aria-hidden="true">
                        <span className={mcClasses("mc-brand-preview__dot")} />
                        <span className={mcClasses("mc-brand-preview__dot")} />
                        <span className={mcClasses("mc-brand-preview__dot")} />
                        <span className={mcClasses("mc-brand-preview__chrome-title")}>{labels.previewstore}</span>
                    </div>
                    <div className={mcClasses("mc-brand-preview__canvas")}>
                        <div className={mcClasses("mc-brand-preview__nav")}>
                            <span className={mcClasses("mc-brand-preview__mark")} aria-hidden="true" />
                            <span className={mcClasses("mc-brand-preview__nav-title")}>{labels.previewstore}</span>
                            <span className={mcClasses("mc-brand-preview__nav-item", "mc-brand-preview__nav-item--active")}>
                                {labels.previewcatalog}
                            </span>
                            <span className={mcClasses("mc-brand-preview__nav-item")}>{labels.previeworders}</span>
                        </div>
                        <div className={mcClasses("mc-brand-preview__content")}>
                            <div className={mcClasses("mc-brand-preview__panel")}>
                                <div>
                                    <span className={mcClasses("mc-badge", "mc-badge--primary")}>{labels.previewbadge}</span>
                                    <h5>{labels.previewcard}</h5>
                                    <p>{labels.previewmuted}</p>
                                </div>
                                <div className={mcClasses("mc-brand-preview__metric")} aria-label={labels.previewmetric}>
                                    <strong>124</strong>
                                    <span>{labels.previewmetric}</span>
                                </div>
                            </div>
                            <div className={mcClasses("mc-brand-preview__row")}>
                                <button className={mcClasses("mc-button btn-mc-primary")} type="button">{labels.previewbutton}</button>
                                <button className={mcClasses("mc-button mc-btn-soft")} type="button">{labels.discard}</button>
                            </div>
                            <div className={mcClasses("mc-brand-preview__swatches")} aria-hidden="true">
                                <span className={mcClasses("mc-brand-preview__swatch", "mc-brand-preview__swatch--primary")} />
                                <span className={mcClasses("mc-brand-preview__swatch", "mc-brand-preview__swatch--accent")} />
                                <span className={mcClasses("mc-brand-preview__swatch", "mc-brand-preview__swatch--surface")} />
                            </div>
                            <div className={mcClasses("mc-brand-preview__notice")} role="status">
                                <span className={mcClasses("mc-settings-dirty-dot")} aria-hidden="true" />
                                <span>{labels.unsaved}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <div className={mcClasses("mc-settings-footer", "mc-brand-footer")}>
                <button
                    className={mcClasses("mc-button mc-btn-soft")}
                    disabled={saving}
                    onClick={resetAll}
                    type="button"
                >
                    {labels.resetall}
                </button>
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
                    loadingLabel={labels.saving || "Saving..."}
                    onClick={submit}
                    type="button"
                >
                    {labels.save}
                </McButton>
            </div>
        </section>
    );
}

/**
 * Branding console host. Two tabs: the storefront/admin colour palette and the
 * global email shell ("Email branding"), which seeds its colours from the brand
 * primary/secondary so emails follow the configured palette by default.
 */
export default function BrandingAdmin({
    getMethodName,
    saveMethodName,
    getShellMethodName,
    saveShellMethodName,
    previewShellMethodName,
    resetShellMethodName,
    brandPrimary,
    brandSecondary,
    tabLabels,
    labels,
    emailLabels,
}: Props) {
    useModernCommerceClassSync();
    const [tab, setTab] = useState<BrandingTab>("colours");

    const tabButton = (value: BrandingTab, icon: string, label: string) => (
        <button
            className={mcClasses(tab === value ? "mc-button btn-mc-primary" : "mc-button mc-btn-soft")}
            onClick={() => setTab(value)}
            type="button"
        >
            <i className={`bi ${icon} me-1`} aria-hidden="true" />
            {label}
        </button>
    );

    return (
        <div className={mcClasses("mc-branding-console")}>
            <div className={mcClasses("mc-card mb-3")}>
                <div className={mcClasses("mc-card-body d-flex gap-2 flex-wrap")} aria-label={labels.intro}>
                    {tabButton("colours", "bi-palette", tabLabels.colours)}
                    {tabButton("email", "bi-envelope-paper", tabLabels.email)}
                </div>
            </div>

            {tab === "colours" && (
                <BrandColorsEditor getMethodName={getMethodName} saveMethodName={saveMethodName} labels={labels} />
            )}

            {tab === "email" && (
                <EmailBrandingAdmin
                    brandPrimary={brandPrimary}
                    brandSecondary={brandSecondary}
                    getShellMethodName={getShellMethodName}
                    labels={emailLabels}
                    previewShellMethodName={previewShellMethodName}
                    resetShellMethodName={resetShellMethodName}
                    saveShellMethodName={saveShellMethodName}
                />
            )}
        </div>
    );
}

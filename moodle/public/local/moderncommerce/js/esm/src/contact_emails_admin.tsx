// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin for contact email configuration (autoreply + admin notification),
 * Modern Commerce core contact webservice endpoints.
 *
 * @module     local_moderncommerce/contact_emails_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {BodyEditorMode, EmailBodyEditor} from "./email_body_editor";
import {FieldError} from "./form";
import {emailList, required, runRules} from "./validation";
import type {FieldErrorMap} from "./validation";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type Option = {
    id: number;
    name: string;
    value: string;
};

type TemplatePreview = {
    subject: string;
    body: string;
};

type EmailBlock = {
    enabled: boolean;
    templateid: number;
    subject: string;
    body: string;
};

type Settings = {
    recipientemails: string;
    templates: Option[];
    placeholders: string[];
    autoreply: EmailBlock;
    adminnotify: EmailBlock;
};

type SaveResponse = {
    success: boolean;
    message: string;
};

type ContactEmailsAdminProps = {
    getMethodName: string;
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

const errorText = (caught: unknown): string => caught instanceof Error ? caught.message : String(caught);

function EmailBlockEditor({
    id,
    title,
    description,
    block,
    templates,
    onChange,
    labels,
}: {
    id: string;
    title: string;
    description: string;
    block: EmailBlock;
    templates: Option[];
    onChange: (changes: Partial<EmailBlock>) => void;
    labels: Labels;
}) {
    const [bodyMode, setBodyMode] = useState<BodyEditorMode>("visual");
    const hasTemplate = Number(block.templateid) > 0;
    const hasCustomContent = block.subject.trim() !== "" || block.body.trim() !== "";
    const usingTemplate = hasTemplate && !hasCustomContent;
    const [templatePreview, setTemplatePreview] = useState<TemplatePreview | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState("");
    const modeTitle = hasCustomContent
        ? labels.customcontentactive
        : hasTemplate
            ? labels.templatecontentactive
            : labels.nocontentselected;
    const modeText = hasCustomContent
        ? labels.customcontentactive_desc
        : hasTemplate
            ? labels.templatecontentactive_desc
            : labels.nocontentselected_desc;
    const clearOverrides = () => onChange({subject: "", body: ""});
    const customizeFromTemplate = () => onChange({
        subject: templatePreview?.subject ?? "",
        body: templatePreview?.body ?? "",
    });

    useEffect(() => {
        if (!hasTemplate) {
            setTemplatePreview(null);
            setPreviewError("");
            setPreviewLoading(false);
            return;
        }

        let cancelled = false;
        setPreviewLoading(true);
        setPreviewError("");
        void callMoodleService<{template: TemplatePreview}>(
            "local_moderncommerce_email_get_template",
            {id: block.templateid}
        )
            .then((response) => {
                if (!cancelled) {
                    const result = response.template;
                    setTemplatePreview({
                        subject: result.subject || "",
                        body: result.body || "",
                    });
                }
            })
            .catch((caught: unknown) => {
                if (!cancelled) {
                    setTemplatePreview(null);
                    setPreviewError(errorText(caught));
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setPreviewLoading(false);
                }
            });
        return () => {
            cancelled = true;
        };
    }, [block.templateid, hasTemplate]);

    return (
        <div className={mcClasses("mc-card mb-3")}>
            <div className={mcClasses("mc-card-header")}>
                <h3 className={mcClasses("mc-card-title mb-1")}>{title}</h3>
                <p className={mcClasses("mc-cell-muted small mb-0")}>{description}</p>
            </div>
            <div className={mcClasses("mc-card-body")}>
                <label className={mcClasses("mc-switch mb-3")}>
                    <input checked={block.enabled} onChange={(event) => onChange({enabled: event.target.checked})} type="checkbox" />
                    <span className={mcClasses("mc-switch__track")} aria-hidden="true" />
                    <span className={mcClasses("mc-switch__thumb")} aria-hidden="true" />
                    <span className={mcClasses("mc-switch__label")}>{labels.enabled}</span>
                </label>

                {templates.length > 0 && (
                    <label className="d-block mb-3">
                        <span className={mcClasses("mc-field-label")}>{labels.template}</span>
                        <select
                            className={mcClasses("mc-select")}
                            onChange={(event) => onChange({templateid: Number(event.target.value) || 0})}
                            value={block.templateid}
                        >
                            <option value="0">{labels.none}</option>
                            {templates.map((option) => (
                                <option key={option.id} value={option.id}>{option.name}</option>
                            ))}
                        </select>
                        <small className={mcClasses("mc-cell-muted d-block mt-1")}>
                            {labels.templatefallbackhint}
                        </small>
                    </label>
                )}

                <div className={mcClasses("mc-alert mc-alert--info mb-3")} role="status">
                    <i className="bi bi-info-circle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body d-flex align-items-start justify-content-between gap-3 flex-wrap")}>
                        <div>
                            <strong className="d-block">{modeTitle}</strong>
                            <span>{modeText}</span>
                        </div>
                        {hasTemplate && hasCustomContent && (
                            <button
                                className={mcClasses("mc-button mc-btn-soft")}
                                onClick={clearOverrides}
                                type="button"
                            >
                                {labels.usetemplatecontent}
                            </button>
                        )}
                    </div>
                </div>

                {usingTemplate && (
                    <div className={mcClasses("mc-product-form__section mc-template-preview mb-0")}>
                        <div className={mcClasses("d-flex align-items-start justify-content-between gap-3 flex-wrap")}>
                            <div>
                                <h4>{labels.templatepreview}</h4>
                                <p className={mcClasses("mc-cell-muted small")}>
                                    {labels.templatepreview_desc}
                                </p>
                            </div>
                            <button
                                className={mcClasses("mc-button mc-btn-soft")}
                                disabled={previewLoading || Boolean(previewError) || !templatePreview}
                                onClick={customizeFromTemplate}
                                type="button"
                            >
                                {labels.customizefromtemplate}
                            </button>
                        </div>
                        {previewLoading && (
                            <div className={mcClasses("mc-product-admin__loading")}>
                                {labels.loadingtemplate}
                            </div>
                        )}
                        {previewError && (
                            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                                <div className={mcClasses("mc-alert__body")}>{previewError}</div>
                            </div>
                        )}
                        {!previewLoading && !previewError && templatePreview && (
                            <div className={mcClasses("mc-template-preview__content")}>
                                <div>
                                    <span className={mcClasses("mc-field-label")}>{labels.subject}</span>
                                    <div className={mcClasses("mc-template-preview__subject")}>
                                        {templatePreview.subject || labels.emptytemplatefield}
                                    </div>
                                </div>
                                <div>
                                    <span className={mcClasses("mc-field-label")}>{labels.body}</span>
                                    <div
                                        className={mcClasses("mc-template-preview__body")}
                                        dangerouslySetInnerHTML={{
                                            __html: templatePreview.body || labels.emptytemplatefield,
                                        }}
                                    />
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {!usingTemplate && (
                    <div className={mcClasses("mc-product-form__section mb-0")}>
                        <h4>{labels.customcontent}</h4>
                        <p className={mcClasses("mc-cell-muted small")}>
                            {hasTemplate
                                ? labels.customcontent_desc
                                : labels.customcontentwithouttemplate_desc}
                        </p>
                        <label className="d-block mb-3">
                            <span className={mcClasses("mc-field-label")}>{labels.subject}</span>
                            <input
                                className={mcClasses("mc-form-control")}
                                onChange={(event) => onChange({subject: event.target.value})}
                                type="text"
                                value={block.subject}
                            />
                        </label>

                        <div>
                            <span className={mcClasses("mc-field-label")}>{labels.body}</span>
                            <EmailBodyEditor
                                idPrefix={`mc-contact-email-${id}-body`}
                                labels={labels}
                                mode={bodyMode}
                                onChange={(value) => onChange({body: value})}
                                onModeChange={setBodyMode}
                                value={block.body}
                            />
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function ContactEmailsAdmin({getMethodName, saveMethodName, labels}: ContactEmailsAdminProps) {
    useModernCommerceClassSync();

    const [data, setData] = useState<Settings | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [saving, setSaving] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<FieldErrorMap>({});
    const [copied, setCopied] = useState("");

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");
        void callMoodleService<Settings>(getMethodName, {})
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
    }, [getMethodName]);

    const updateBlock = (key: "autoreply" | "adminnotify", changes: Partial<EmailBlock>) => {
        setData((current) => current ? {...current, [key]: {...current[key], ...changes}} : current);
    };

    const copyPlaceholder = (token: string) => {
        void navigator.clipboard?.writeText(token).then(() => {
            setCopied(token);
            window.setTimeout(() => setCopied(""), 1500);
        });
    };

    const save = async() => {
        const t = (key: string) => labels[key] ?? key;
        // This is a comma-separated list and previously had no validation at
        // all, so one typo silently broke every admin notification.
        const found = runRules(data ?? {}, {
            recipientemails: [required(t("error:required")), emailList(t("error:invalidemaillist"))],
        });
        setFieldErrors(found);
        if (Object.keys(found).length > 0) {
            return;
        }

        if (!data) {
            return;
        }
        setSaving(true);
        setError("");
        try {
            const result = await callMoodleService<SaveResponse>(saveMethodName, {
                recipientemails: data.recipientemails,
                autoreply: data.autoreply,
                adminnotify: data.adminnotify,
            });
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setSaving(false);
        }
    };

    if (loading && !data) {
        return <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>;
    }

    if (error && !data) {
        return (
            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                <div className={mcClasses("mc-alert__body")}>{error}</div>
            </div>
        );
    }

    if (!data) {
        return null;
    }

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            <div className={mcClasses("mc-card mb-3")}>
                <div className={mcClasses("mc-card-header")}>
                    <h3 className={mcClasses("mc-card-title mb-1")}>{labels.recipients}</h3>
                    <p className={mcClasses("mc-cell-muted small mb-0")}>{labels.recipients_desc}</p>
                </div>
                <div className={mcClasses("mc-card-body")}>
                    <input
                        aria-describedby={fieldErrors.recipientemails
                            ? "mc-contact-recipients-error" : undefined}
                        aria-invalid={fieldErrors.recipientemails ? true : undefined}
                        aria-label={labels.recipients}
                        aria-required="true"
                        className={mcClasses("mc-form-control",
                            fieldErrors.recipientemails ? "mc-field-invalid" : "")}
                        id="mc-contact-recipients"
                        name="recipientemails"
                        onChange={(event) => {
                            setData({...data, recipientemails: event.target.value});
                            setFieldErrors({});
                        }}
                        placeholder="admin@example.com, support@example.com"
                        type="text"
                        value={data.recipientemails}
                    />
                    <FieldError
                        id="mc-contact-recipients-error"
                        message={fieldErrors.recipientemails}
                    />
                </div>
            </div>

            <EmailBlockEditor
                id="autoreply"
                title={labels.autoreply}
                description={labels.autoreply_desc}
                block={data.autoreply}
                templates={data.templates}
                onChange={(changes) => updateBlock("autoreply", changes)}
                labels={labels}
            />

            <EmailBlockEditor
                id="adminnotify"
                title={labels.adminnotify}
                description={labels.adminnotify_desc}
                block={data.adminnotify}
                templates={data.templates}
                onChange={(changes) => updateBlock("adminnotify", changes)}
                labels={labels}
            />

            {data.placeholders.length > 0 && (
                <div className={mcClasses("mc-card mb-3")}>
                    <div className={mcClasses("mc-card-header")}>
                        <h3 className={mcClasses("mc-card-title mb-1")}>{labels.placeholders}</h3>
                        <p className={mcClasses("mc-cell-muted small mb-0")}>{labels.insertplaceholder}</p>
                    </div>
                    <div className={mcClasses("mc-card-body d-flex flex-wrap gap-1")}>
                        {data.placeholders.map((token) => (
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
            )}

            <div>
                <McButton
                    className={mcClasses("btn-mc-primary")}
                    loading={saving}
                    loadingLabel={labels.saving}
                    onClick={save}
                    type="button"
                >
                    {labels.savechanges}
                </McButton>
            </div>
        </section>
    );
}

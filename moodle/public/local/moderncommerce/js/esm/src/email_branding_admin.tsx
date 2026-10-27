// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React editor for the global Modern Commerce email shell ("Email branding").
 *
 * The shell wraps every outgoing Modern Commerce email. This editor lives under
 * the Branding admin so the email look-and-feel sits next to the storefront/admin
 * palette. By default it seeds the email primary colour from the brand primary and
 * the header band from the brand secondary, so emails match the configured brand
 * out of the box; admins can still override any colour here.
 *
 * @module     local_moderncommerce/email_branding_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {confirmDialog} from "./modal";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type ShellResponse = {
    shell: string;
    defaultshell: string;
};

type ShellSaveResponse = {
    success: boolean;
    message: string;
    shell: string;
};

type ShellPreviewResponse = {
    body: string;
};

type ShellEditorTab = "design" | "footer" | "preview" | "advanced";
type ShellHeaderStyle = "split" | "centered" | "compact";

type ShellDesigner = {
    logo: string;
    sitename: string;
    siteurl: string;
    supportemail: string;
    supporturl: string;
    emailbg: string;
    headerbg: string;
    contentbg: string;
    footerbg: string;
    primarycolor: string;
    textcolor: string;
    containerwidth: string;
    buttonradius: string;
    headerstyle: ShellHeaderStyle;
    footertext: string;
    showsupport: boolean;
    showunsubscribe: boolean;
};

type ShellColorKey = "emailbg" | "headerbg" | "contentbg" | "footerbg" | "primarycolor" | "textcolor";
type ShellTextKey = "logo" | "sitename" | "siteurl" | "supportemail" | "supporturl" | "containerwidth" | "buttonradius" | "footertext";
type ShellSwitchKey = "showsupport" | "showunsubscribe";

type Props = {
    getShellMethodName: string;
    saveShellMethodName: string;
    previewShellMethodName: string;
    resetShellMethodName: string;
    brandPrimary?: string;
    brandSecondary?: string;
    labels: Labels;
};

const CONTENT_TOKEN = "{content_html}";
const UNSUBSCRIBE_TOKEN = "{unsubscribe_html}";
const DEFAULT_PRIMARY = "#7c3aed";
const DEFAULT_SECONDARY = "#1e1b4b";
const LOGO_PREVIEW_DATA_URI = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='168' height='48' viewBox='0 0 168 48'%3E%3Crect width='168' height='48' rx='8' fill='%23ffffff'/%3E%3Ccircle cx='26' cy='24' r='12' fill='%230f766e'/%3E%3Cpath d='M21 25.5l3.2 3.2L31.5 19' fill='none' stroke='%23ffffff' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ctext x='48' y='30' font-family='Arial,sans-serif' font-size='16' font-weight='700' fill='%230f172a'%3ELogo%3C/text%3E%3C/svg%3E";
const SHELL_SAMPLE_CONTENT = `<h2 style="margin:0 0 12px; font-size:24px; line-height:1.25;">Advance your team with a new programme</h2>
<p style="margin:0 0 16px;">Hello Jane, your course package is ready. Secure your place today and start learning with guided modules, practical resources, and certificate-ready outcomes.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0;">
    <tr>
        <td><a class="mc-button mc-email-button" data-mc-button="primary" href="{siteurl}">Explore programme</a></td>
    </tr>
</table>
<p style="margin:0;">Featured programme: Digital Commerce Leadership</p>`;

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

const normaliseColor = (value: string, fallback: string): string => {
    const trimmed = value.trim();
    return /^#[0-9a-f]{6}$/i.test(trimmed) ? trimmed : fallback;
};

// Seed the shell designer. The email primary colour defaults to the configured
// brand primary and the header band to the brand secondary, so untouched emails
// follow the storefront/admin palette.
const defaultShellDesigner = (brandPrimary: string, brandSecondary: string): ShellDesigner => ({
    logo: "{logo}",
    sitename: "{sitename}",
    siteurl: "{siteurl}",
    supportemail: "{supportemail}",
    supporturl: "{siteurl}",
    emailbg: "#f4f7fb",
    headerbg: normaliseColor(brandSecondary, DEFAULT_SECONDARY),
    contentbg: "#ffffff",
    footerbg: "#edf2f7",
    primarycolor: normaliseColor(brandPrimary, DEFAULT_PRIMARY),
    textcolor: "#172033",
    containerwidth: "640",
    buttonradius: "6",
    headerstyle: "split",
    footertext: "You are receiving this email because you purchased, enrolled in, or showed interest in a course or programme.",
    showsupport: true,
    showunsubscribe: true,
});

const escapeHtml = (value: string): string => value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");

const clampNumber = (value: string, min: number, max: number, fallback: number): number => {
    const parsed = Number.parseInt(value, 10);
    if (Number.isNaN(parsed)) {
        return fallback;
    }
    return Math.min(max, Math.max(min, parsed));
};

const replaceToken = (source: string, token: string, value: string): string => source.split(token).join(value);

const buildShellHeader = (
    designer: ShellDesigner,
    headerBg: string,
    primaryColor: string,
): string => {
    const logo = designer.logo.trim() || "{logo}";
    const sitename = designer.sitename.trim() || "{sitename}";
    const siteurl = designer.siteurl.trim() || "{siteurl}";
    const logoMarkup = `<img src="${escapeHtml(logo)}" width="152" alt="${escapeHtml(sitename)}" style="display:block; max-width:152px; width:152px; height:auto;">`;
    const nameMarkup = `<a href="${escapeHtml(siteurl)}" style="color:#ffffff; font-size:18px; font-weight:700; line-height:1.2; text-decoration:none;">${escapeHtml(sitename)}</a>`;

    if (designer.headerstyle === "centered") {
        return `<tr>
    <td align="center" style="background:${headerBg}; padding:30px 28px;">
        ${logoMarkup}
        <div style="height:12px; line-height:12px;">&nbsp;</div>
        ${nameMarkup}
    </td>
</tr>`;
    }

    if (designer.headerstyle === "compact") {
        return `<tr>
    <td style="background:${headerBg}; padding:22px 28px;">
        <div style="color:#ffffff; font-size:20px; font-weight:800; line-height:1.25;">${escapeHtml(sitename)}</div>
        <div style="height:4px; line-height:4px;">&nbsp;</div>
        <a href="${escapeHtml(siteurl)}" style="color:#d7f8f2; font-size:13px; text-decoration:none;">${escapeHtml(siteurl)}</a>
    </td>
</tr>`;
    }

    return `<tr>
    <td style="background:${headerBg}; border-bottom:4px solid ${primaryColor}; padding:24px 28px;">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
            <tr>
                <td align="left" style="vertical-align:middle;">${logoMarkup}</td>
                <td align="right" style="vertical-align:middle;">${nameMarkup}</td>
            </tr>
        </table>
    </td>
</tr>`;
};

const shellDesignerToHtml = (designer: ShellDesigner): string => {
    const width = clampNumber(designer.containerwidth, 480, 760, 640);
    const radius = clampNumber(designer.buttonradius, 0, 24, 6);
    const emailBg = normaliseColor(designer.emailbg, "#f4f7fb");
    const headerBg = normaliseColor(designer.headerbg, DEFAULT_SECONDARY);
    const contentBg = normaliseColor(designer.contentbg, "#ffffff");
    const footerBg = normaliseColor(designer.footerbg, "#edf2f7");
    const primaryColor = normaliseColor(designer.primarycolor, DEFAULT_PRIMARY);
    const textColor = normaliseColor(designer.textcolor, "#172033");
    const supportEmail = designer.supportemail.trim() || "{supportemail}";
    const supportUrl = designer.supporturl.trim() || designer.siteurl.trim() || "{siteurl}";
    const supportMarkup = designer.showsupport
        ? `<p style="margin:8px 0 0; font-size:13px; line-height:1.5;">
            <a href="${escapeHtml(supportUrl)}" style="color:${primaryColor}; text-decoration:none;">Support</a>
            <span style="color:#94a3b8;"> | </span>
            <a href="mailto:${escapeHtml(supportEmail)}" style="color:${primaryColor}; text-decoration:none;">${escapeHtml(supportEmail)}</a>
        </p>`
        : "";
    const unsubscribeMarkup = designer.showunsubscribe
        ? `<div style="margin-top:14px; font-size:12px; line-height:1.5; color:#64748b;">${UNSUBSCRIBE_TOKEN}</div>`
        : "";

    return `<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>${escapeHtml(designer.sitename.trim() || "{sitename}")}</title>
    <style>
        body, table, td, a { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
        table, td { mso-table-lspace:0pt; mso-table-rspace:0pt; }
        img { -ms-interpolation-mode:bicubic; border:0; height:auto; line-height:100%; outline:none; text-decoration:none; }
        a { color:${primaryColor}; }
        .mc-email-button { background:${primaryColor}; border-radius:${radius}px; color:#ffffff !important; display:inline-block; font-weight:700; padding:12px 18px; text-decoration:none; }
        @media screen and (max-width: 640px) {
            .mc-email-container { width:100% !important; max-width:100% !important; }
            .mc-email-padding { padding-left:20px !important; padding-right:20px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:${emailBg}; color:${textColor}; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:${emailBg}; border-collapse:collapse; margin:0; padding:0;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="${width}" class="mc-email-container" style="border-collapse:collapse; max-width:${width}px; width:${width}px;">
                    ${buildShellHeader(designer, headerBg, primaryColor)}
                    <tr>
                        <td class="mc-email-padding" style="background:${contentBg}; color:${textColor}; font-size:16px; line-height:1.6; padding:32px;">
                            ${CONTENT_TOKEN}
                        </td>
                    </tr>
                    <tr>
                        <td class="mc-email-padding" style="background:${footerBg}; color:#64748b; font-size:13px; line-height:1.5; padding:22px 32px;">
                            <p style="margin:0;">${escapeHtml(designer.footertext.trim())}</p>
                            ${supportMarkup}
                            ${unsubscribeMarkup}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>`;
};

const renderShellPreviewHtml = (shellHtml: string, contentHtml: string, designer: ShellDesigner): string => {
    const sitename = designer.sitename.replace(/[{}]/g, "").trim() || "Modern Commerce";
    const siteurl = designer.siteurl.includes("{") ? "#" : designer.siteurl;
    const supportemail = designer.supportemail.includes("{") ? "support@example.com" : designer.supportemail;
    const logo = designer.logo.includes("{") ? LOGO_PREVIEW_DATA_URI : designer.logo;
    let rendered = shellHtml;

    rendered = replaceToken(rendered, CONTENT_TOKEN, contentHtml.trim() || SHELL_SAMPLE_CONTENT);
    rendered = replaceToken(rendered, UNSUBSCRIBE_TOKEN, `<a href="#" style="color:${normaliseColor(designer.primarycolor, DEFAULT_PRIMARY)}; text-decoration:none;">Manage email preferences</a>`);
    rendered = replaceToken(rendered, "{sitename}", escapeHtml(sitename));
    rendered = replaceToken(rendered, "{siteurl}", escapeHtml(siteurl || "#"));
    rendered = replaceToken(rendered, "{supportemail}", escapeHtml(supportemail || "support@example.com"));
    rendered = replaceToken(rendered, "{logo}", escapeHtml(logo));
    rendered = replaceToken(rendered, "{logo_compact}", escapeHtml(logo));

    return rendered;
};

export default function EmailBrandingAdmin({
    getShellMethodName,
    saveShellMethodName,
    previewShellMethodName,
    resetShellMethodName,
    brandPrimary = DEFAULT_PRIMARY,
    brandSecondary = DEFAULT_SECONDARY,
    labels,
}: Props) {
    useModernCommerceClassSync();

    const [copied, setCopied] = useState("");
    const [shell, setShell] = useState("");
    const [defaultShell, setDefaultShell] = useState("");
    const [shellPreview, setShellPreview] = useState("");
    const [shellContent, setShellContent] = useState(SHELL_SAMPLE_CONTENT);
    const [shellBuilder, setShellBuilder] = useState<ShellDesigner>(() => defaultShellDesigner(brandPrimary, brandSecondary));
    const [shellEditorTab, setShellEditorTab] = useState<ShellEditorTab>("design");
    const [shellLoading, setShellLoading] = useState(true);
    const [shellSaving, setShellSaving] = useState(false);

    const builderShell = shellDesignerToHtml(shellBuilder);
    const activeShell = shellEditorTab === "advanced" ? shell : builderShell;
    const activeShellHasContentToken = activeShell.includes(CONTENT_TOKEN);
    const activeShellPreview = renderShellPreviewHtml(activeShell, shellContent, shellBuilder);

    useEffect(() => {
        let cancelled = false;
        setShellLoading(true);
        void callMoodleService<ShellResponse>(getShellMethodName, {})
            .then((result) => {
                if (!cancelled) {
                    setShell(result.shell);
                    setDefaultShell(result.defaultshell);
                    if (result.shell !== "" && result.defaultshell !== "" && result.shell !== result.defaultshell) {
                        setShellEditorTab("advanced");
                    }
                }
            })
            .catch((caught: unknown) => {
                if (!cancelled) {
                    toast.error(errorText(caught));
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setShellLoading(false);
                }
            });
        return () => {
            cancelled = true;
        };
    }, [getShellMethodName]);

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

    const updateShellBuilder = (changes: Partial<ShellDesigner>) => {
        setShellBuilder((current) => ({...current, ...changes}));
        setShellPreview("");
    };

    const updateShellHtml = (html: string) => {
        setShell(html);
        setShellPreview("");
    };

    const saveShell = async() => {
        if (!activeShellHasContentToken) {
            toast.error(labels.requiredtokenmissing ?? "The email shell must include {content_html}.");
            return;
        }
        setShellSaving(true);
        try {
            const result = await callMoodleService<ShellSaveResponse>(saveShellMethodName, {shell: activeShell});
            if (!result.success) {
                toast.error(result.message);
                setShell(result.shell);
                return;
            }
            setShell(result.shell);
            toast.success(result.message);
        } catch (caught) {
            toast.error(errorText(caught));
        } finally {
            setShellSaving(false);
        }
    };

    const previewShell = async() => {
        if (!activeShellHasContentToken) {
            toast.error(labels.requiredtokenmissing ?? "The email shell must include {content_html}.");
            return;
        }
        setShellSaving(true);
        try {
            const result = await callMoodleService<ShellPreviewResponse>(previewShellMethodName, {
                shell: activeShell,
                content: shellContent || SHELL_SAMPLE_CONTENT,
            });
            setShellPreview(result.body);
            setShellEditorTab("preview");
        } catch (caught) {
            toast.error(errorText(caught));
        } finally {
            setShellSaving(false);
        }
    };

    const resetShell = async() => {
        if (!await confirmDialog({message: labels.resetconfirm, danger: true})) {
            return;
        }
        setShellSaving(true);
        try {
            const result = await callMoodleService<ShellSaveResponse>(resetShellMethodName, {});
            if (!result.success) {
                toast.error(result.message);
                return;
            }
            setShell(result.shell);
            setShellBuilder(defaultShellDesigner(brandPrimary, brandSecondary));
            setShellEditorTab("design");
            setShellPreview("");
            toast.success(result.message);
        } catch (caught) {
            toast.error(errorText(caught));
        } finally {
            setShellSaving(false);
        }
    };

    const colorFallbacks: Record<ShellColorKey, string> = {
        emailbg: "#f4f7fb",
        headerbg: normaliseColor(brandSecondary, DEFAULT_SECONDARY),
        contentbg: "#ffffff",
        footerbg: "#edf2f7",
        primarycolor: normaliseColor(brandPrimary, DEFAULT_PRIMARY),
        textcolor: "#172033",
    };

    const shellTabButton = (tab: ShellEditorTab, icon: string, label: string) => (
        <button
            className={mcClasses(shellEditorTab === tab ? "mc-button btn-mc-primary" : "mc-button mc-btn-soft")}
            disabled={shellLoading || shellSaving}
            key={tab}
            onClick={() => {
                setShellEditorTab(tab);
                setShellPreview("");
            }}
            type="button"
        >
            <i className={`bi ${icon} me-1`} aria-hidden="true" />
            {label}
        </button>
    );

    const shellTextField = (key: ShellTextKey, label: string, type = "text") => (
        <label key={key}>
            <span className={mcClasses("mc-field-label")}>{label}</span>
            <input
                className={mcClasses("mc-form-control")}
                disabled={shellLoading || shellSaving}
                onChange={(event) => updateShellBuilder({[key]: event.target.value} as Partial<ShellDesigner>)}
                type={type}
                value={shellBuilder[key]}
            />
        </label>
    );

    const shellColorField = (key: ShellColorKey, label: string) => (
        <label key={key}>
            <span className={mcClasses("mc-field-label")}>{label}</span>
            <div className={mcClasses("mc-settings-colorfield")}>
                <input
                    aria-label={label}
                    disabled={shellLoading || shellSaving}
                    onChange={(event) => updateShellBuilder({[key]: event.target.value} as Partial<ShellDesigner>)}
                    type="color"
                    value={normaliseColor(shellBuilder[key], colorFallbacks[key])}
                />
                <input
                    className={mcClasses("mc-form-control")}
                    disabled={shellLoading || shellSaving}
                    onChange={(event) => updateShellBuilder({[key]: event.target.value} as Partial<ShellDesigner>)}
                    placeholder="#rrggbb"
                    type="text"
                    value={shellBuilder[key]}
                />
            </div>
        </label>
    );

    const shellSwitchField = (key: ShellSwitchKey, label: string) => (
        <label className={mcClasses("mc-switch")} key={key}>
            <input
                checked={shellBuilder[key]}
                disabled={shellLoading || shellSaving}
                onChange={(event) => updateShellBuilder({[key]: event.target.checked} as Partial<ShellDesigner>)}
                type="checkbox"
            />
            <span className={mcClasses("mc-switch__track")} aria-hidden="true" />
            <span className={mcClasses("mc-switch__thumb")} aria-hidden="true" />
            <span className={mcClasses("mc-switch__label")}>{label}</span>
        </label>
    );

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.shellbuilder ?? labels.shell}>
            <div className={mcClasses("mc-card mb-3")}>
                <div className={mcClasses("mc-card-header d-flex flex-wrap gap-2 align-items-start justify-content-between")}>
                    <div>
                        <h3 className={mcClasses("mc-card-title mb-1")}>{labels.shellbuilder ?? labels.shell}</h3>
                        <p className={mcClasses("mc-cell-muted small mb-0")}>{labels.shellhelp}</p>
                    </div>
                    <span className={mcClasses(activeShellHasContentToken ? "mc-badge mc-badge--success" : "mc-badge mc-badge--danger")}>
                        {CONTENT_TOKEN}
                    </span>
                </div>
                <div className={mcClasses("mc-card-body")}>
                    <div className="d-flex gap-2 flex-wrap mb-3" role="tablist" aria-label={labels.shellbuilder ?? labels.shell}>
                        {shellTabButton("design", "bi-palette", labels.shelldesign ?? "Design")}
                        {shellTabButton("footer", "bi-shield-check", labels.shellfooter ?? "Footer & compliance")}
                        {shellTabButton("preview", "bi-window", labels.shellpreview ?? labels.preview)}
                        {shellTabButton("advanced", "bi-code-square", labels.shelladvanced ?? labels.shellhtml)}
                    </div>

                    {!activeShellHasContentToken && (
                        <div className={mcClasses("mc-alert mc-alert--danger mb-3")} role="alert">
                            <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                            <div className={mcClasses("mc-alert__body")}>
                                {labels.requiredtokenmissing ?? "The email shell must include {content_html}."}
                            </div>
                        </div>
                    )}

                    {shellEditorTab === "design" && (
                        <div className={mcClasses("mc-product-form__grid")}>
                            {shellTextField("logo", labels.logourl ?? "Logo URL")}
                            {shellTextField("sitename", labels.brandname ?? "Brand name")}
                            {shellTextField("siteurl", labels.siteurl ?? "Site URL")}
                            <label>
                                <span className={mcClasses("mc-field-label")}>{labels.headerstyle ?? "Header style"}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    disabled={shellLoading || shellSaving}
                                    onChange={(event) => {
                                        updateShellBuilder({headerstyle: event.target.value as ShellHeaderStyle});
                                    }}
                                    value={shellBuilder.headerstyle}
                                >
                                    <option value="split">{labels.headerstylesplit ?? "Logo left, name right"}</option>
                                    <option value="centered">{labels.headerstylecentered ?? "Centered"}</option>
                                    <option value="compact">{labels.headerstylecompact ?? "Compact text"}</option>
                                </select>
                            </label>
                            {shellColorField("headerbg", labels.headerbg ?? "Header background")}
                            {shellColorField("primarycolor", labels.primarycolor ?? "Primary color")}
                            {shellColorField("emailbg", labels.emailbg ?? "Email background")}
                            {shellColorField("contentbg", labels.contentbg ?? "Content background")}
                            {shellColorField("textcolor", labels.textcolor ?? "Text color")}
                            {shellTextField("containerwidth", labels.containerwidth ?? "Container width", "number")}
                            {shellTextField("buttonradius", labels.buttonradius ?? "Button radius", "number")}
                        </div>
                    )}

                    {shellEditorTab === "footer" && (
                        <>
                            <div className={mcClasses("mc-product-form__grid")}>
                                {shellTextField("supportemail", labels.supportemail ?? "Support email")}
                                {shellTextField("supporturl", labels.supporturl ?? "Support URL")}
                                {shellColorField("footerbg", labels.footerbg ?? "Footer background")}
                            </div>
                            <div className="d-flex align-items-center gap-4 flex-wrap mt-3">
                                {shellSwitchField("showsupport", labels.showsupport ?? "Show support link")}
                                {shellSwitchField("showunsubscribe", labels.showunsubscribe ?? "Show unsubscribe")}
                            </div>
                            <label className="d-block mt-3">
                                <span className={mcClasses("mc-field-label")}>{labels.footertext ?? "Footer text"}</span>
                                <textarea
                                    className={mcClasses("mc-form-control")}
                                    disabled={shellLoading || shellSaving}
                                    onChange={(event) => updateShellBuilder({footertext: event.target.value})}
                                    rows={3}
                                    value={shellBuilder.footertext}
                                />
                            </label>
                        </>
                    )}

                    {shellEditorTab === "preview" && (
                        <div className="row g-3">
                            <div className="col-12 col-xl-5">
                                <label className="d-block">
                                    <span className={mcClasses("mc-field-label")}>{labels.previewcontent}</span>
                                    <textarea
                                        className={mcClasses("form-control form-control-sm mc-code-textarea")}
                                        disabled={shellLoading || shellSaving}
                                        onChange={(event) => {
                                            setShellContent(event.target.value);
                                            setShellPreview("");
                                        }}
                                        rows={14}
                                        value={shellContent}
                                    />
                                </label>
                            </div>
                            <div className="col-12 col-xl-7">
                                <iframe
                                    className="mc-email-preview-frame"
                                    sandbox=""
                                    srcDoc={shellPreview || activeShellPreview}
                                    style={{width: "100%", minHeight: "560px", border: "1px solid #e5e7eb", borderRadius: "6px"}}
                                    title={labels.preview}
                                />
                            </div>
                        </div>
                    )}

                    {shellEditorTab === "advanced" && (
                        <>
                            <div className="d-flex gap-2 flex-wrap mb-3">
                                {[CONTENT_TOKEN, UNSUBSCRIBE_TOKEN, "{sitename}", "{siteurl}", "{supportemail}", "{logo}", "{logo_compact}"].map((token) => (
                                    <button
                                        className={mcClasses("mc-button mc-badge mc-badge--neutral mc-cell-mono mc-placeholder-chip")}
                                        data-mc-button="light"
                                        key={token}
                                        onClick={() => copyToken(token)}
                                        type="button"
                                    >
                                        {copied === token ? labels.copied : token}
                                    </button>
                                ))}
                            </div>
                            <textarea
                                className={mcClasses("form-control form-control-sm mc-code-textarea")}
                                disabled={shellLoading || shellSaving}
                                onChange={(event) => updateShellHtml(event.target.value)}
                                rows={26}
                                value={shell}
                            />
                        </>
                    )}

                    <div className="d-flex gap-2 flex-wrap mt-4">
                        <McButton
                            className={mcClasses("btn-mc-primary")}
                            disabled={shellLoading || !activeShellHasContentToken}
                            loading={shellSaving}
                            loadingLabel={labels.saving || "Saving..."}
                            onClick={saveShell}
                            type="button"
                        >
                            {labels.saveshell}
                        </McButton>
                        <button className={mcClasses("mc-button mc-btn-soft")} disabled={shellLoading || shellSaving || !activeShellHasContentToken} onClick={previewShell} type="button">
                            {labels.previewshell}
                        </button>
                        <button className={mcClasses("mc-button mc-btn-soft")} disabled={shellLoading || shellSaving} onClick={resetShell} type="button">
                            {labels.resetshell}
                        </button>
                    </div>
                </div>
            </div>

            {defaultShell !== "" && (
                <input type="hidden" value={defaultShell} readOnly />
            )}
        </section>
    );
}

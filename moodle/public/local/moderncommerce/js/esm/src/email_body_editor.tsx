// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Shared Modern Commerce email body editor.
 *
 * Stores and returns HTML, while giving non-technical admins a visual editing
 * surface for common formatting tasks.
 *
 * @module     local_moderncommerce/email_body_editor
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef} from "react";
import {mcClasses} from "./design_system";
import {McTabs, tabPanelProps} from "./tabs";

type Labels = Record<string, string>;

export type BodyEditorMode = "visual" | "html";

export function EmailBodyEditor({
    value,
    onChange,
    mode,
    onModeChange,
    labels,
    idPrefix = "mc-email-body-editor",
}: {
    value: string;
    onChange: (value: string) => void;
    mode: BodyEditorMode;
    onModeChange: (mode: BodyEditorMode) => void;
    labels: Labels;
    idPrefix?: string;
}) {
    const visualEditorRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        const editor = visualEditorRef.current;
        if (!editor || document.activeElement === editor) {
            return;
        }
        if (editor.innerHTML !== value) {
            editor.innerHTML = value || "";
        }
    }, [value, mode]);

    const runCommand = (command: string, commandValue?: string) => {
        visualEditorRef.current?.focus();
        document.execCommand(command, false, commandValue);
        onChange(visualEditorRef.current?.innerHTML || "");
    };

    const insertLink = () => {
        const href = window.prompt(labels.linkurl || "Link URL");
        if (!href) {
            return;
        }
        runCommand("createLink", href);
    };

    const toolbarActions = [
        {
            key: "bold",
            icon: "bi-type-bold",
            label: labels.formatbold || "Bold",
            onClick: () => runCommand("bold"),
        },
        {
            key: "italic",
            icon: "bi-type-italic",
            label: labels.formatitalic || "Italic",
            onClick: () => runCommand("italic"),
        },
        {
            key: "bullet",
            icon: "bi-list-ul",
            label: labels.formatbulletlist || "Bulleted list",
            onClick: () => runCommand("insertUnorderedList"),
        },
        {
            key: "numbered",
            icon: "bi-list-ol",
            label: labels.formatnumberedlist || "Numbered list",
            onClick: () => runCommand("insertOrderedList"),
        },
        {
            key: "link",
            icon: "bi-link-45deg",
            label: labels.formatlink || "Link",
            onClick: insertLink,
        },
        {
            key: "unlink",
            icon: "bi-link",
            label: labels.formatunlink || "Remove link",
            onClick: () => runCommand("unlink"),
        },
        {
            key: "clear",
            icon: "bi-eraser",
            label: labels.formatclear || "Clear formatting",
            onClick: () => runCommand("removeFormat"),
        },
    ];

    return (
        <div className={mcClasses("mc-email-body-editor")}>
            <McTabs<BodyEditorMode>
                activeKey={mode}
                ariaLabel={labels.bodyeditormode || "Body editor mode"}
                className="mc-email-body-editor__tabs"
                idPrefix={idPrefix}
                onChange={onModeChange}
                tabs={[
                    {key: "visual", label: labels.bodyvisual || "Visual editor", icon: "bi-window"},
                    {key: "html", label: labels.bodyhtml || "HTML", icon: "bi-code-slash"},
                ]}
            />
            <div className={mcClasses("mc-email-body-editor__panel")} {...tabPanelProps(idPrefix, mode)}>
                {mode === "visual" ? (
                    <>
                        <div className={mcClasses("mc-email-body-editor__toolbar")} aria-label={labels.formattoolbar || "Formatting toolbar"}>
                            {toolbarActions.map((action) => (
                                <button
                                    aria-label={action.label}
                                    className={mcClasses("mc-button mc-email-body-editor__tool")}
                                    key={action.key}
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={action.onClick}
                                    title={action.label}
                                    type="button"
                                >
                                    <i className={`bi ${action.icon}`} aria-hidden="true" />
                                </button>
                            ))}
                        </div>
                        <div
                            className={mcClasses("mc-form-control mc-email-body-editor__visual")}
                            contentEditable
                            onInput={(event) => onChange(event.currentTarget.innerHTML)}
                            ref={visualEditorRef}
                            role="textbox"
                            aria-label={labels.body}
                            suppressContentEditableWarning
                        />
                    </>
                ) : (
                    <textarea
                        className={mcClasses("form-control form-control-sm mc-code-textarea mc-email-body-editor__html")}
                        onChange={(event) => onChange(event.target.value)}
                        rows={14}
                        value={value}
                    />
                )}
            </div>
        </div>
    );
}

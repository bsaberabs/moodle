// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Reusable modal dialog for Modern Commerce React surfaces.
 *
 * Exposes two things:
 *  - {@link Modal}: a presentational, portalled modal component (header/body/footer)
 *    for declarative use inside any component.
 *  - {@link confirmDialog}: an imperative `Promise<boolean>` confirmation helper that
 *    replaces blocking `window.confirm()` calls. Resolves true on confirm, false on
 *    cancel/dismiss.
 *
 * @module     local_moderncommerce/modal
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef} from "react";
import type {ReactNode} from "react";
import {createPortal} from "react-dom";
import {createRoot} from "react-dom/client";
import {mcClasses} from "./design_system";

type ModalSize = "sm" | "md" | "lg";

type ModalProps = {
    title?: ReactNode;
    onClose: () => void;
    children: ReactNode;
    footer?: ReactNode;
    size?: ModalSize;
    closeLabel?: string;
};

/**
 * Portalled modal shell. Renders into document.body so it escapes any
 * overflow/transform ancestor, locks body scroll, and closes on Escape or
 * backdrop click.
 *
 * @param props Modal props.
 * @returns The portalled modal element.
 */
export function Modal({title, onClose, children, footer, size = "md", closeLabel = "Close"}: ModalProps) {
    useEffect(() => {
        document.body.classList.add("mc-modal-open");
        const handleKey = (event: KeyboardEvent) => {
            if (event.key === "Escape") {
                onClose();
            }
        };
        document.addEventListener("keydown", handleKey);
        return () => {
            document.body.classList.remove("mc-modal-open");
            document.removeEventListener("keydown", handleKey);
        };
    }, [onClose]);

    return createPortal(
        <div className={mcClasses("mc-modal-backdrop")} onClick={onClose}>
            <div
                className={mcClasses("mc-modal", `mc-modal--${size}`)}
                role="dialog"
                aria-modal="true"
                onClick={(event) => event.stopPropagation()}
            >
                <div className={mcClasses("mc-modal__header")}>
                    {title ? <h2 className={mcClasses("mc-modal__title")}>{title}</h2> : <span />}
                    <button
                        type="button"
                        className={mcClasses("mc-button mc-modal__close")}
                        onClick={onClose}
                        aria-label={closeLabel}
                    >
                        <i className="bi bi-x-lg" aria-hidden="true" />
                    </button>
                </div>
                <div className={mcClasses("mc-modal__body")}>{children}</div>
                {footer && <div className={mcClasses("mc-modal__footer")}>{footer}</div>}
            </div>
        </div>,
        document.body
    );
}

export type ConfirmOptions = {
    title?: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    danger?: boolean;
};

/**
 * Internal confirmation dialog rendered by {@link confirmDialog}.
 *
 * @param props Confirm options plus the resolver callback.
 * @returns The confirmation modal element.
 */
function ConfirmDialog(
    {title, message, confirmLabel = "Confirm", cancelLabel = "Cancel", danger = false, onResolve}:
    ConfirmOptions & {onResolve: (value: boolean) => void}
) {
    const confirmRef = useRef<HTMLButtonElement>(null);
    useEffect(() => {
        confirmRef.current?.focus();
    }, []);

    const footer = (
        <>
            <button
                ref={confirmRef}
                type="button"
                className={mcClasses(danger ? "mc-button btn-mc-danger" : "mc-button btn-mc-primary")}
                onClick={() => onResolve(true)}
            >
                {confirmLabel}
            </button>
            <button
                type="button"
                className={mcClasses("mc-button btn-mc-secondary")}
                onClick={() => onResolve(false)}
            >
                {cancelLabel}
            </button>
        </>
    );

    return (
        <Modal title={title} onClose={() => onResolve(false)} size="sm" footer={footer} closeLabel={cancelLabel}>
            <div className={mcClasses("mc-modal__confirm")}>
                {danger && (
                    <span className={mcClasses("mc-modal__icon mc-modal__icon--danger")} aria-hidden="true">
                        <i className="bi bi-exclamation-triangle" />
                    </span>
                )}
                <p className={mcClasses("mc-modal__message")}>{message}</p>
            </div>
        </Modal>
    );
}

/**
 * Show a confirmation modal and resolve with the user's choice. Drop-in async
 * replacement for `window.confirm()`:
 *
 *   if (!await confirmDialog({message: labels.removeconfirm, danger: true})) {
 *       return;
 *   }
 *
 * @param options Confirmation options.
 * @returns Resolves true if confirmed, false if cancelled/dismissed.
 */
export function confirmDialog(options: ConfirmOptions): Promise<boolean> {
    return new Promise((resolve) => {
        const host = document.createElement("div");
        document.body.appendChild(host);
        const root = createRoot(host);
        const finish = (value: boolean) => {
            root.unmount();
            host.remove();
            resolve(value);
        };
        root.render(<ConfirmDialog {...options} onResolve={finish} />);
    });
}

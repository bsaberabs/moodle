// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Reusable portalled drawer for Modern Commerce React admin screens.
 *
 * @module     local_moderncommerce/drawer
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useId, useRef} from "react";
import type {ReactNode, Ref} from "react";
import {createPortal} from "react-dom";
import {mcClasses} from "./design_system";

type McDrawerProps = {
    title: ReactNode;
    onClose: () => void;
    children: ReactNode;
    subtitle?: ReactNode;
    footer?: ReactNode;
    closeLabel?: string;
    ariaLabel?: string;
    className?: string;
    bodyClassName?: string;
    backdropClassName?: string;
    bodyRef?: Ref<HTMLDivElement>;
    nested?: boolean;
    disableClose?: boolean;
    closeOnBackdrop?: boolean;
    closeOnEscape?: boolean;
};

let nextDrawerId = 1;
let openDrawerCount = 0;
const drawerStack: number[] = [];

const lockBody = () => {
    openDrawerCount += 1;
    document.body.classList.add("mc-drawer-open");

    return () => {
        openDrawerCount = Math.max(0, openDrawerCount - 1);
        if (openDrawerCount === 0) {
            document.body.classList.remove("mc-drawer-open");
        }
    };
};

const pushDrawer = (id: number) => {
    drawerStack.push(id);

    return () => {
        const index = drawerStack.lastIndexOf(id);
        if (index !== -1) {
            drawerStack.splice(index, 1);
        }
    };
};

/**
 * Portalled drawer shell. Renders into `document.body` so it escapes table/card
 * overflow and local stacking contexts. Modals intentionally remain above it.
 *
 * @param props Drawer props.
 * @returns The portalled drawer element.
 */
export function McDrawer({
    title,
    subtitle,
    onClose,
    children,
    footer,
    closeLabel = "Close",
    ariaLabel,
    className,
    bodyClassName,
    backdropClassName,
    bodyRef,
    nested = false,
    disableClose = false,
    closeOnBackdrop = true,
    closeOnEscape = true,
}: McDrawerProps) {
    const titleId = useId();
    const drawerIdRef = useRef(0);
    if (drawerIdRef.current === 0) {
        drawerIdRef.current = nextDrawerId;
        nextDrawerId += 1;
    }

    useEffect(() => {
        const unlockBody = lockBody();
        const popDrawer = pushDrawer(drawerIdRef.current);

        return () => {
            popDrawer();
            unlockBody();
        };
    }, []);

    useEffect(() => {
        const handleKey = (event: KeyboardEvent) => {
            const isTopDrawer = drawerStack[drawerStack.length - 1] === drawerIdRef.current;
            if (event.key === "Escape" && closeOnEscape && !disableClose && isTopDrawer) {
                onClose();
            }
        };
        document.addEventListener("keydown", handleKey);

        return () => {
            document.removeEventListener("keydown", handleKey);
        };
    }, [closeOnEscape, disableClose, onClose]);

    const closeFromBackdrop = () => {
        if (closeOnBackdrop && !disableClose) {
            onClose();
        }
    };

    return createPortal(
        <>
            <div
                className={mcClasses("mc-drawer-backdrop", nested && "mc-drawer-backdrop--nested", backdropClassName)}
                onClick={closeFromBackdrop}
                aria-hidden="true"
            />
            <aside
                className={mcClasses("mc-drawer mc-drawer--open", nested && "mc-drawer--nested", className)}
                role="dialog"
                aria-modal="true"
                aria-label={ariaLabel}
                aria-labelledby={ariaLabel ? undefined : titleId}
                onClick={(event) => event.stopPropagation()}
            >
                <div className={mcClasses("mc-drawer__header")}>
                    <div>
                        <h2 className={mcClasses("mc-drawer__title")} id={titleId}>{title}</h2>
                        {subtitle && <div className={mcClasses("mc-drawer__subtitle")}>{subtitle}</div>}
                    </div>
                    <button
                        className={mcClasses("mc-button mc-drawer__close")}
                        disabled={disableClose}
                        onClick={onClose}
                        type="button"
                        aria-label={closeLabel}
                    >
                        <i className="bi bi-x-lg" aria-hidden="true" />
                    </button>
                </div>

                <div className={mcClasses("mc-drawer__body", bodyClassName)} ref={bodyRef}>
                    {children}
                </div>

                {footer && <div className={mcClasses("mc-drawer__footer")}>{footer}</div>}
            </aside>
        </>,
        document.body
    );
}

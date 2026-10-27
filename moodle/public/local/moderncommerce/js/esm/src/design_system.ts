// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Runtime design-system helpers for Modern Commerce React surfaces.
 *
 * @module     local_moderncommerce/design_system
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect} from "react";

type ClassNameValue = string | false | null | undefined;

export const syncModernCommerceClasses = (): void => {
    // Retained for stable imports; ModernCommerce now emits mc-* classes directly.
};

export const mcClasses = (...classNames: ClassNameValue[]): string => {
    const classes = new Set<string>();

    classNames.forEach((className) => {
        if (!className) {
            return;
        }

        className.split(/\s+/).filter(Boolean).forEach((token) => {
            classes.add(token);
        });
    });

    return Array.from(classes).join(" ");
};

export type McSortDirection = "ASC" | "DESC";

/**
 * Bootstrap-icon class for a sortable column-header indicator. Idle columns show
 * a faint dual chevron so they read as sortable; the active column shows a
 * directional caret. Pair with `mc-table-sort__indicator` for colour/opacity.
 */
export const sortIconClass = (active: boolean, direction: McSortDirection): string => {
    if (!active) {
        return "bi bi-chevron-expand";
    }

    return direction === "ASC" ? "bi bi-caret-up-fill" : "bi bi-caret-down-fill";
};

export const useModernCommerceClassSync = (): void => {
    useEffect(() => {
        syncModernCommerceClasses();
        return undefined;
    }, []);
};

/**
 * Bridge from the ESM React surfaces to the design-system toast manager
 * (the local_moderncommerce/floating_notifications AMD module). Use this for
 * transient action feedback — "Changes saved", "Coupon applied" — instead of an
 * inline mc-alert, so it appears as a floating toast at the admin-configured
 * position. Keep inline mc-alert for persistent, in-flow states (load failures,
 * field validation).
 */
type ToastOptions = {title?: string; delay?: number; dismissible?: boolean; icon?: string};

type ToastApi = {
    success: (message: string, options?: ToastOptions) => void;
    error: (message: string, options?: ToastOptions) => void;
    warning: (message: string, options?: ToastOptions) => void;
    info: (message: string, options?: ToastOptions) => void;
};

type AmdRequire = (deps: string[], callback: (module: ToastApi) => void) => void;

const withToastApi = (run: (api: ToastApi) => void): void => {
    const amdRequire = (window as unknown as {require?: AmdRequire}).require;
    if (typeof amdRequire !== "function") {
        return;
    }
    amdRequire(["local_moderncommerce/floating_notifications"], (api) => run(api));
};

export const toast = {
    success: (message: string, options?: ToastOptions): void =>
        withToastApi((api) => api.success(message, options)),
    error: (message: string, options?: ToastOptions): void =>
        withToastApi((api) => api.error(message, options)),
    warning: (message: string, options?: ToastOptions): void =>
        withToastApi((api) => api.warning(message, options)),
    info: (message: string, options?: ToastOptions): void =>
        withToastApi((api) => api.info(message, options)),
};

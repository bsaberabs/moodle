// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Shared learner React helpers for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_common
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

export type Labels = Record<string, string>;

export const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const query = new URLSearchParams({
        sesskey: M.cfg.sesskey,
        info: methodName,
    });
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php?${query.toString()}`;
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

export const formatCount = (value: number): string => {
    return new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);
};

type NavbarCartResponse = {
    itemcount?: number;
    count?: number;
    cartcount?: number;
    items?: unknown[];
};

const isRecord = (value: unknown): value is Record<string, unknown> => {
    return typeof value === "object" && value !== null;
};

const normaliseCartCount = (value: unknown): number | null => {
    const count = typeof value === "number" ? value : typeof value === "string" ? Number(value) : NaN;
    return Number.isFinite(count) && count >= 0 ? Math.floor(count) : null;
};

const countFromCartResponse = (cart?: unknown): number | null => {
    if (!isRecord(cart)) {
        return null;
    }

    const response = cart as NavbarCartResponse;
    const explicitCount = normaliseCartCount(response.itemcount ?? response.count ?? response.cartcount);
    if (explicitCount !== null) {
        return explicitCount;
    }

    return Array.isArray(response.items) ? response.items.length : null;
};

const navbarCartRoots = (): HTMLElement[] => {
    if (typeof document === "undefined") {
        return [];
    }

    return Array.from(document.querySelectorAll<HTMLElement>(".local-moderncommerce-navbar-cart"));
};

const setNavbarCartBadge = (count: number): void => {
    navbarCartRoots().forEach((root) => {
        const trigger = root.querySelector<HTMLElement>(".mui-cart__trigger");
        if (!trigger) {
            return;
        }

        const existingBadge = root.querySelector<HTMLElement>(".mui-cart__badge");
        if (count <= 0) {
            if (existingBadge) {
                existingBadge.textContent = "0";
                existingBadge.style.display = "none";
            }
            return;
        }

        let badge = existingBadge;
        if (!badge) {
            badge = document.createElement("span");
            trigger.insertBefore(badge, trigger.querySelector(".visually-hidden"));
        }

        badge.classList.add("count-container", "mui-cart__badge", "mc-cart-count", "cart-count");
        badge.setAttribute("data-region", "cart-count");
        badge.setAttribute("aria-hidden", "true");
        badge.style.removeProperty("display");
        badge.textContent = formatCount(count);
    });

    if (typeof window !== "undefined" && typeof CustomEvent === "function") {
        window.dispatchEvent(new CustomEvent("local_moderncommerce:cartupdated", {detail: {count}}));
    }
};

/**
 * Refresh the server-rendered cart widget shown in Moodle's top-right navbar.
 *
 * React cart mutations happen outside the navbar renderer, so this keeps the
 * standalone navbar icon and dropdown in sync without requiring a page reload.
 *
 * @param cart Optional cart service response for immediate badge update.
 */
export const refreshNavbarCart = async(cart?: unknown): Promise<void> => {
    const optimisticCount = countFromCartResponse(cart);
    if (optimisticCount !== null) {
        setNavbarCartBadge(optimisticCount);
    }

    if (navbarCartRoots().length === 0) {
        return;
    }

    try {
        const payload = await callMoodleService<{success?: boolean; html?: string; count?: number}>(
            "local_moderncommerce_get_cart_dropdown",
            {}
        );
        if (!payload.success) {
            return;
        }

        const refreshedCount = normaliseCartCount(payload.count);
        if (refreshedCount !== null) {
            setNavbarCartBadge(refreshedCount);
        }

        if (typeof payload.html === "string") {
            const html = payload.html;
            navbarCartRoots().forEach((root) => {
                const panel = root.querySelector<HTMLElement>(".mui-cart__panel");
                if (panel) {
                    panel.innerHTML = html;
                }
            });
        }
    } catch {
        // The badge has already been updated from the mutation response.
    }
};

export const badgeClass = (statusclass: string): string => {
    const allowed = ["success", "warning", "danger", "info", "primary", "neutral"];
    return allowed.includes(statusclass) ? statusclass : "neutral";
};

export const clampProgress = (value: number): number => {
    return Math.min(100, Math.max(0, value));
};

export const wwwroot = (): string => M.cfg.wwwroot;

// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Reusable Modern Commerce tab navigation for React screens. Emits proper
 * Bootstrap `nav nav-tabs` markup with full WAI-ARIA tab semantics (roving
 * tabindex + arrow-key navigation), reskinned to the mc-* design system.
 *
 * @module     local_moderncommerce/tabs
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Dispatch, KeyboardEvent, ReactNode} from "react";
import {mcClasses} from "./design_system";

export type McTabItem<Key extends string = string> = {
    /** Stable identifier returned through onChange and used for ARIA wiring. */
    key: Key;
    /** Visible tab label. */
    label: ReactNode;
    /** Optional Bootstrap-icon class, e.g. "bi-diagram-3". */
    icon?: string;
    /** Optional trailing count badge. */
    badge?: ReactNode;
    /** When true the tab renders disabled and is skipped by keyboard nav. */
    disabled?: boolean;
};

export type McTabsProps<Key extends string = string> = {
    tabs: McTabItem<Key>[];
    activeKey: Key;
    onChange: Dispatch<Key>;
    /** Accessible name for the tablist (maps to aria-label). */
    ariaLabel?: string;
    /**
     * Prefix for generated tab/panel ids so `aria-controls` can point at the
     * matching tabpanel. Pair with {@link tabPanelProps} on the panel element.
     */
    idPrefix?: string;
    /** Stretch tabs to fill the available width (Bootstrap `nav-fill`). */
    fill?: boolean;
    className?: string;
};

export type McTabCardProps<Key extends string = string> = {
    tabs: McTabItem<Key>[];
    activeKey: Key;
    onChange: Dispatch<Key>;
    title: ReactNode;
    subtitle?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    ariaLabel?: string;
    idPrefix?: string;
    fill?: boolean;
    className?: string;
    headerClassName?: string;
    bodyClassName?: string;
    tabsClassName?: string;
};

/**
 * Build the id used for a tab trigger. Shared with {@link tabPanelProps} so the
 * panel's `aria-labelledby` resolves to its trigger.
 *
 * @param idPrefix Component id prefix.
 * @param key Tab key.
 * @returns The DOM id for the tab button.
 */
const tabId = (idPrefix: string, key: string): string => `${idPrefix}-${key}-tab`;

/**
 * Build the id used for a tab panel.
 *
 * @param idPrefix Component id prefix.
 * @param key Tab key.
 * @returns The DOM id for the tab panel.
 */
const panelId = (idPrefix: string, key: string): string => `${idPrefix}-${key}-panel`;

/**
 * ARIA props for the content region governed by the active tab. Spread onto the
 * panel wrapper so screen readers tie the panel back to its trigger.
 *
 * @param idPrefix Same prefix passed to {@link McTabs}.
 * @param activeKey Currently active tab key.
 * @returns Role/id/aria-labelledby attributes for the panel.
 */
export const tabPanelProps = (idPrefix: string, activeKey: string) => ({
    role: "tabpanel",
    id: panelId(idPrefix, activeKey),
    "aria-labelledby": tabId(idPrefix, activeKey),
    tabIndex: 0,
});

/**
 * Tab bar built on Bootstrap nav-tabs with mc-* theming.
 *
 * @param props Tab configuration.
 * @returns A keyboard-accessible tablist element.
 */
export function McTabs<Key extends string = string>({
    tabs,
    activeKey,
    onChange,
    ariaLabel,
    idPrefix = "mc-tab",
    fill = false,
    className,
}: McTabsProps<Key>) {
    const handleKeyDown = (event: KeyboardEvent<HTMLUListElement>) => {
        const selectable = tabs.filter((tab) => !tab.disabled);
        if (selectable.length === 0) {
            return;
        }

        const currentIndex = Math.max(0, selectable.findIndex((tab) => tab.key === activeKey));
        let nextIndex = currentIndex;

        switch (event.key) {
            case "ArrowRight":
            case "ArrowDown":
                nextIndex = (currentIndex + 1) % selectable.length;
                break;
            case "ArrowLeft":
            case "ArrowUp":
                nextIndex = (currentIndex - 1 + selectable.length) % selectable.length;
                break;
            case "Home":
                nextIndex = 0;
                break;
            case "End":
                nextIndex = selectable.length - 1;
                break;
            default:
                return;
        }

        event.preventDefault();
        const nextKey = selectable[nextIndex].key;
        onChange(nextKey);
        document.getElementById(tabId(idPrefix, nextKey))?.focus();
    };

    return (
        <ul
            className={mcClasses("nav nav-tabs mc-tabs", fill && "nav-fill", className)}
            role="tablist"
            aria-label={ariaLabel}
            onKeyDown={handleKeyDown}
        >
            {tabs.map((tab) => {
                const active = tab.key === activeKey;

                return (
                    <li className="nav-item" role="presentation" key={tab.key}>
                        <button
                            id={tabId(idPrefix, tab.key)}
                            className={mcClasses("nav-link mc-tabs__link", active && "active")}
                            type="button"
                            role="tab"
                            aria-selected={active}
                            aria-controls={panelId(idPrefix, tab.key)}
                            tabIndex={active ? 0 : -1}
                            disabled={tab.disabled}
                            onClick={() => onChange(tab.key)}
                        >
                            {tab.icon && <i className={`bi ${tab.icon} mc-tabs__icon`} aria-hidden="true" />}
                            <span className="mc-tabs__label">{tab.label}</span>
                            {tab.badge !== undefined && tab.badge !== null && tab.badge !== "" && (
                                <span className="mc-tabs__badge">{tab.badge}</span>
                            )}
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}

/**
 * Card shell for tabbed admin sections. The header carries the section title
 * and actions; the Bootstrap tablist stays inside the card body above the
 * active tab panel.
 *
 * @param props Tab card configuration.
 * @returns A card with Bootstrap tab navigation and an ARIA-linked panel.
 */
export function McTabCard<Key extends string = string>({
    tabs,
    activeKey,
    onChange,
    title,
    subtitle,
    actions,
    children,
    ariaLabel,
    idPrefix = "mc-tab",
    fill = false,
    className,
    headerClassName,
    bodyClassName,
    tabsClassName,
}: McTabCardProps<Key>) {
    const titleId = `${idPrefix}-title`;

    return (
        <section className={mcClasses("mc-card mc-tab-card", className)} aria-labelledby={titleId}>
            <div className={mcClasses("mc-card-header mc-tab-card__header", headerClassName)}>
                <div className={mcClasses("mc-tab-card__heading")}>
                    <h2 className={mcClasses("mc-card-title mc-tab-card__title")} id={titleId}>{title}</h2>
                    {subtitle && <p className={mcClasses("mc-card-sub mc-tab-card__subtitle")}>{subtitle}</p>}
                </div>
                {actions && <div className={mcClasses("mc-tab-card__actions")}>{actions}</div>}
            </div>
            <div className={mcClasses("mc-card-body mc-tab-card__body", bodyClassName)}>
                <McTabs
                    tabs={tabs}
                    activeKey={activeKey}
                    onChange={onChange}
                    ariaLabel={ariaLabel}
                    idPrefix={idPrefix}
                    fill={fill}
                    className={mcClasses("mc-tab-card__tabs", tabsClassName)}
                />
                <div className={mcClasses("mc-tab-card__panel")} {...tabPanelProps(idPrefix, activeKey)}>
                    {children}
                </div>
            </div>
        </section>
    );
}

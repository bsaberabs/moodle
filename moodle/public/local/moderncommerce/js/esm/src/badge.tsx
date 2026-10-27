// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Shared Modern Commerce badge primitives.
 *
 * @module     local_moderncommerce/badge
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactNode} from "react";
import {mcClasses} from "./design_system";

export type McBadgeVariant = "primary" | "secondary" | "accent" | "success" | "warning" | "danger" | "info" | "neutral";
export type McBadgeTone = "subtle" | "soft" | "medium" | "strong";
export type McBadgeSize = "sm" | "md" | "lg";

type McBadgeProps = {
    children: ReactNode;
    variant?: McBadgeVariant;
    tone?: McBadgeTone;
    size?: McBadgeSize;
    icon?: string;
    dot?: boolean;
    className?: string;
    title?: string;
};

type McBadgeGroupProps = {
    children: ReactNode;
    inline?: boolean;
    stacked?: boolean;
    className?: string;
};

/**
 * Brand-aware badge for status labels, metadata, and small feature markers.
 *
 * @param props Component props.
 * @returns Badge element.
 */
export function McBadge({
    children,
    variant = "neutral",
    tone = "soft",
    size = "md",
    icon,
    dot = false,
    className,
    title,
}: McBadgeProps) {
    return (
        <span
            className={mcClasses(
                "mc-badge",
                `mc-badge--${variant}`,
                `mc-badge--tone-${tone}`,
                size !== "md" && `mc-badge--${size}`,
                className,
            )}
            title={title}
        >
            {dot && <span className={mcClasses("mc-badge__dot")} aria-hidden="true" />}
            {icon && <i className={mcClasses("bi", icon, "mc-badge__icon")} aria-hidden="true" />}
            <span className={mcClasses("mc-badge__label")}>{children}</span>
        </span>
    );
}

/**
 * Flexible wrapper for adjacent badges.
 *
 * @param props Component props.
 * @returns Badge group.
 */
export function McBadgeGroup({children, inline = false, stacked = false, className}: McBadgeGroupProps) {
    return (
        <span
            className={mcClasses(
                "mc-badge-group",
                inline && "mc-badge-group--inline",
                stacked && "mc-badge-group--stacked",
                className,
            )}
        >
            {children}
        </span>
    );
}

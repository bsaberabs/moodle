// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Trust badges strip for the Modern Commerce storefront.
 *
 * @module     local_moderncommerce/storefront/trustbadges
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

type Badge = {
    icon: string;
    label: string;
    sublabel: string;
};

import {CSSProperties} from "react";

type TrustStripProps = {
    title?: string;
    badges: Badge[];
    bgcolor?: string;
    titlecolor?: string;
    titlefontsize?: number | string;
    cardbgcolor?: string;
    cardbordercolor?: string;
    cardborderwidth?: number | string;
    cardradius?: number | string;
    iconbgcolor?: string;
    iconcolor?: string;
    iconsize?: number | string;
    labelcolor?: string;
    labelfontsize?: number | string;
    sublabelcolor?: string;
    sublabelfontsize?: number | string;
    paddingTop?: number; // px; 0 keeps the CSS default.
    paddingBottom?: number; // px; 0 keeps the CSS default.
    labels?: {trust?: string};
};

// Bootstrap-icon value may arrive bare ("shield-check") or prefixed ("bi-shield-check" / "bi shield-check");
// strip any prefix so we can render a single, correct "bi bi-<name>".
const normIcon = (v: string): string => (v || "").trim().replace(/^bi\s+/, "").replace(/^bi-/, "");

const pxValue = (value: number | string | undefined, max = 240): number => {
    const parsed = typeof value === "string" ? Number(value.trim().replace(/px$/i, "")) : Number(value);
    return Number.isFinite(parsed) ? Math.min(max, Math.max(0, parsed)) : 0;
};

export default function TrustStrip({
    title,
    badges,
    bgcolor = "",
    titlecolor = "",
    titlefontsize = 0,
    cardbgcolor = "",
    cardbordercolor = "",
    cardborderwidth = 1,
    cardradius = 8,
    iconbgcolor = "",
    iconcolor = "",
    iconsize = 26,
    labelcolor = "",
    labelfontsize = 16,
    sublabelcolor = "",
    sublabelfontsize = 14,
    paddingTop,
    paddingBottom,
    labels = {},
}: TrustStripProps) {
    if (!badges || badges.length === 0) {
        return null;
    }

    const style = {} as CSSProperties & Record<string, string | number>;
    if (bgcolor) {
        style.backgroundColor = bgcolor;
        style["--mc-trust-bg"] = bgcolor;
    }
    if (titlecolor) {
        style["--mc-trust-title-color"] = titlecolor;
    }
    const titleSize = pxValue(titlefontsize, 96);
    if (titleSize > 0) {
        style["--mc-trust-title-font-size"] = `${titleSize}px`;
    }
    if (cardbgcolor) {
        style["--mc-trust-card-bg"] = cardbgcolor;
    }
    if (cardbordercolor) {
        style["--mc-trust-card-border"] = cardbordercolor;
    }
    const cardBorder = pxValue(cardborderwidth, 24);
    if (cardBorder >= 0) {
        style["--mc-trust-card-border-width"] = `${cardBorder}px`;
    }
    const radius = pxValue(cardradius, 96);
    if (radius >= 0) {
        style["--mc-trust-card-radius"] = `${radius}px`;
    }
    if (iconbgcolor) {
        style["--mc-trust-icon-bg"] = iconbgcolor;
    }
    if (iconcolor) {
        style["--mc-trust-icon-color"] = iconcolor;
    }
    const iconSize = pxValue(iconsize, 96);
    if (iconSize > 0) {
        style["--mc-trust-icon-size"] = `${iconSize}px`;
        style["--mc-trust-icon-box-size"] = `${Math.max(36, iconSize + 18)}px`;
    }
    if (labelcolor) {
        style["--mc-trust-label-color"] = labelcolor;
    }
    const labelSize = pxValue(labelfontsize, 96);
    if (labelSize > 0) {
        style["--mc-trust-label-font-size"] = `${labelSize}px`;
    }
    if (sublabelcolor) {
        style["--mc-trust-sublabel-color"] = sublabelcolor;
    }
    const sublabelSize = pxValue(sublabelfontsize, 96);
    if (sublabelSize > 0) {
        style["--mc-trust-sublabel-font-size"] = `${sublabelSize}px`;
    }
    if (paddingTop && paddingTop > 0) {
        style.paddingTop = paddingTop;
    }
    if (paddingBottom && paddingBottom > 0) {
        style.paddingBottom = paddingBottom;
    }
    const sectionStyle = Object.keys(style).length > 0 ? style : undefined;

    return (
        <section className="mw-trust" style={sectionStyle} aria-label={title || labels.trust}>
            <div className="mw-trust__container">
                {title && <h2 className="mw-trust__title">{title}</h2>}
                <ul className="mw-trust__list">
                    {badges.map((badge, index) => (
                        <li className="mw-trust__item" key={index}>
                            <i className={`bi bi-${normIcon(badge.icon) || "check-circle"} mw-trust__icon`}
                                aria-hidden="true" />
                            <span className="mw-trust__text">
                                <span className="mw-trust__label">{badge.label}</span>
                                {badge.sublabel && <span className="mw-trust__sublabel">{badge.sublabel}</span>}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}

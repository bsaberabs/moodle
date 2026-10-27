// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Feature card grid for the Modern Commerce storefront.
 *
 * Up to twelve icon cards laid out on the Bootstrap grid, 3 or 4 per row on
 * desktop as the admin chooses, collapsing to 2-up on tablet and 1-up on phones.
 * Each card carries its own icon and accent colour so a site can run one hue per
 * card without any extra markup.
 *
 * @module     local_moderncommerce/storefront/featuregrid
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties} from "react";

export type FeatureCard = {
    icon: string;
    title: string;
    text: string;
    iconcolor?: string;
    accentcolor?: string;
    linklabel?: string;
    linkurl?: string;
};

export type FeatureGridProps = {
    style?: string;
    columns?: number;
    cards: FeatureCard[];

    title?: string;
    subtitle?: string;
    showheader?: boolean;
    centercards?: boolean;

    cssvars?: CSSProperties;
};

const STYLES = ["topbar", "plain", "tile", "bordered"];

const normStyle = (value?: string): string => (
    value && STYLES.includes(value) ? value : "topbar"
);

/**
 * Bootstrap column classes for the chosen desktop density.
 *
 * Both options stay 1-up on phones and 2-up from the small breakpoint, so the
 * only thing the admin's choice changes is the widest layout.
 */
const columnClass = (columns: number): string => (
    columns === 4
        ? "col-12 col-sm-6 col-lg-3"
        : "col-12 col-sm-6 col-lg-4"
);

/**
 * Strip any "bi-"/"bi " prefix so a bare icon name is left; the markup adds
 * "bi bi-" itself. Mirrors how the category tiles normalise their icons.
 */
const normIcon = (icon: string): string => (
    (icon || "star").replace(/^bi\s+/, "").replace(/^bi-/, "")
);

function Card({card, treatment, centred}: {card: FeatureCard; treatment: string; centred: boolean}) {
    // Per-card colours win; blank falls through to the widget-level CSS vars.
    const vars: CSSProperties & Record<string, string> = {};
    if (card.accentcolor) {
        vars["--mc-fg-card-accent"] = card.accentcolor;
    }
    if (card.iconcolor) {
        vars["--mc-fg-card-icon"] = card.iconcolor;
    }

    const classes = [
        "mc-fg__card",
        `mc-fg__card--${treatment}`,
        centred ? "mc-fg__card--centred" : "",
        "h-100",
    ].filter(Boolean).join(" ");

    return (
        <div className={classes} style={vars}>
            {treatment === "topbar" && <span className="mc-fg__topbar" aria-hidden="true" />}

            <span className="mc-fg__icon-wrap">
                <i className={`mc-fg__icon bi bi-${normIcon(card.icon)}`} aria-hidden="true" />
            </span>

            {card.title && <h3 className="mc-fg__card-title">{card.title}</h3>}
            {card.text && <p className="mc-fg__card-text">{card.text}</p>}

            {card.linklabel && card.linkurl && (
                <a className="mc-fg__card-link" href={card.linkurl}>
                    {card.linklabel}
                </a>
            )}
        </div>
    );
}

export default function FeatureGrid(props: FeatureGridProps) {
    const treatment = normStyle(props.style);
    const columns = props.columns === 4 ? 4 : 3;
    const centred = props.centercards !== false;
    const cards = (props.cards || []).slice(0, 12);

    if (!cards.length) {
        return null;
    }

    const showHeader = props.showheader !== false && Boolean(props.title || props.subtitle);

    return (
        <section className={`mc-fg mc-fg--${treatment}`} style={props.cssvars}>
            <div className="mc-fg__inner container">
                {showHeader && (
                    <header className="mc-fg__header">
                        {props.title && <h2 className="mc-fg__title">{props.title}</h2>}
                        {props.subtitle && <p className="mc-fg__subtitle">{props.subtitle}</p>}
                    </header>
                )}

                <div className="row g-4">
                    {cards.map((card, index) => (
                        <div className={columnClass(columns)} key={index}>
                            <Card card={card} treatment={treatment} centred={centred} />
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

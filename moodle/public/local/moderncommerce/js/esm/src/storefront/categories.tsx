// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Category tiles for the Modern Commerce storefront.
 *
 * @module     local_moderncommerce/storefront/categories
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties, useRef} from "react";

type Category = {
    id: number;
    name: string;
    count: number;
    url: string;
    icon: string;
    color?: string; // optional admin-chosen accent (hex); '' falls back to the palette.
};

// Bootstrap-icon value may arrive bare ("code-slash") or prefixed; strip any prefix so we
// render a single correct "bi bi-<name>".
const normIcon = (v: string): string => (v || "").trim().replace(/^bi\s+/, "").replace(/^bi-/, "");

// Recognised render styles. To add a new one: add a key here, a field_schema choice + resolver
// whitelist entry, and a `.mw-cats--<key>` block in styles/scss/surfaces/_storefront.scss.
const STYLES = ["minimal", "colourful", "carousel"] as const;
const resolveStyle = (s?: string): string => (STYLES as readonly string[]).includes(s ?? "") ? s! : "minimal";

// Brand-derived bg/accent pairs for the colourful style, cycled by tile index when a tile has
// no admin-chosen colour. Per-category colour overrides this.
const PALETTE: ReadonlyArray<{bg: string; accent: string}> = [
    {bg: "var(--mc-primary-light)", accent: "var(--mc-primary)"},
    {bg: "color-mix(in srgb, var(--mc-accent), white 88%)", accent: "var(--mc-accent)"},
    {bg: "color-mix(in srgb, var(--mc-secondary), white 90%)", accent: "var(--mc-secondary)"},
    {bg: "color-mix(in srgb, var(--mc-link), white 88%)", accent: "var(--mc-link)"},
    {bg: "color-mix(in srgb, var(--mc-primary-hover), white 90%)", accent: "var(--mc-primary-hover)"},
    {bg: "color-mix(in srgb, var(--mc-accent-hover), white 90%)", accent: "var(--mc-accent-hover)"},
    {bg: "color-mix(in srgb, var(--mc-text-muted), white 88%)", accent: "var(--mc-text-muted)"},
    {bg: "color-mix(in srgb, var(--mc-primary-active), white 90%)", accent: "var(--mc-primary-active)"},
    {bg: "var(--mc-surface-alt)", accent: "var(--mc-primary)"},
];

// Mix a custom hex colour with white to derive a pastel tile background. Accepts #rgb or #rrggbb;
// returns an rgb() string. Brand fallback pairs above can use CSS variables directly.
const tint = (hex: string, amount = 0.12): string => {
    let h = (hex || "").trim().replace(/^#/, "");
    if (h.length === 3) {
        h = h.split("").map((c) => c + c).join("");
    }
    if (!/^[0-9a-f]{6}$/i.test(h)) {
        return "var(--mc-surface-alt)";
    }
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    const mix = (c: number): number => Math.round(c * amount + 255 * (1 - amount));
    return `rgb(${mix(r)}, ${mix(g)}, ${mix(b)})`;
};

type CategoryTilesProps = {
    title?: string;
    subtitle?: string;
    categories: Category[];
    showcount: boolean;
    style?: string; // "minimal" (default) | "colourful" | "carousel" | future keys.
    iconColor?: string;
    visibleCards?: number; // carousel only: how many tiles are visible per view (card-width divisor).
    bgColor?: string; // optional section background colour ('' keeps the CSS default).
    titleColor?: string;
    titleFontSize?: number;
    subtitleColor?: string;
    subtitleFontSize?: number;
    cardBgColor?: string;
    cardTextColor?: string;
    cardTextFontSize?: number;
    cardRadius?: number;
    iconBgColor?: string;
    iconSize?: number;
    countColor?: string;
    countFontSize?: number;
    paddingTop?: number; // px; 0 keeps the CSS default.
    paddingBottom?: number; // px; 0 keeps the CSS default.
    labels: {courses: string; scrollleft: string; scrollright: string};
};

export default function CategoryTiles(
    {
        title, subtitle, categories, showcount, style, iconColor, visibleCards, bgColor,
        titleColor, titleFontSize, subtitleColor, subtitleFontSize, cardBgColor, cardTextColor, cardTextFontSize,
        cardRadius, iconBgColor, iconSize, countColor, countFontSize, paddingTop, paddingBottom, labels,
    }: CategoryTilesProps
) {
    const trackRef = useRef<HTMLDivElement>(null);

    if (!categories || categories.length === 0) {
        return null;
    }

    const variant = resolveStyle(style);
    const isCarousel = variant === "carousel";

    // Per-view card count for the carousel (clamped); minimal is up to 4-up centring an incomplete
    // final row; colourful is a fixed 3-up.
    const perView = Math.max(1, Math.min(6, Math.round(visibleCards || 4)));
    const cols = isCarousel ? perView : (variant === "colourful" ? 3 : Math.min(categories.length, 4));
    const listStyle = {"--mw-cols": String(cols)} as CSSProperties;

    const sectionStyle: CSSProperties & Record<string, string | number> = {};
    if (paddingTop && paddingTop > 0) {
        sectionStyle.paddingTop = paddingTop;
    }
    if (paddingBottom && paddingBottom > 0) {
        sectionStyle.paddingBottom = paddingBottom;
    }
    if (bgColor) {
        sectionStyle.backgroundColor = bgColor;
        sectionStyle["--mc-cats-section-bg"] = bgColor;
    }
    if (titleColor) {
        sectionStyle["--mc-cats-title-color"] = titleColor;
    }
    if (titleFontSize && titleFontSize > 0) {
        sectionStyle["--mc-cats-title-font-size"] = `${titleFontSize}px`;
    }
    if (subtitleColor) {
        sectionStyle["--mc-cats-subtitle-color"] = subtitleColor;
    }
    if (subtitleFontSize && subtitleFontSize > 0) {
        sectionStyle["--mc-cats-subtitle-font-size"] = `${subtitleFontSize}px`;
    }
    if (cardBgColor) {
        sectionStyle["--mc-cats-card-bg"] = cardBgColor;
    }
    if (cardTextColor) {
        sectionStyle["--mc-cats-card-text"] = cardTextColor;
    }
    if (cardTextFontSize && cardTextFontSize > 0) {
        sectionStyle["--mc-cats-card-text-font-size"] = `${cardTextFontSize}px`;
    }
    if (cardRadius && cardRadius > 0) {
        sectionStyle["--mc-cats-card-radius"] = `${cardRadius}px`;
    }
    if (iconBgColor) {
        sectionStyle["--mc-cats-icon-bg"] = iconBgColor;
    }
    if (iconColor) {
        sectionStyle["--mc-cats-icon-color"] = iconColor;
    }
    if (iconSize && iconSize > 0) {
        sectionStyle["--mc-cats-icon-size"] = `${iconSize}px`;
    }
    if (countColor) {
        sectionStyle["--mc-cats-count-color"] = countColor;
    }
    if (countFontSize && countFontSize > 0) {
        sectionStyle["--mc-cats-count-font-size"] = `${countFontSize}px`;
    }
    const secStyle = Object.keys(sectionStyle).length > 0 ? sectionStyle : undefined;

    const tiles = categories.map((category, i) => {
        const pair = PALETTE[i % PALETTE.length];
        const accent = category.color || pair.accent;
        const bg = category.color ? tint(category.color) : pair.bg;
        // Colour vars are always emitted; only .mw-cats--colourful / --carousel read them.
        const tileStyle = {"--mw-cat-bg": bg, "--mw-cat-accent": accent} as CSSProperties;
        return (
            <a className="mw-cats__tile" href={category.url} key={category.id} style={tileStyle}>
                <span className="mw-cats__icon" aria-hidden="true">
                    <i className={`bi bi-${normIcon(category.icon) || "collection"}`} />
                </span>
                <span className="mw-cats__body">
                    <span className="mw-cats__name">{category.name}</span>
                    {showcount && category.count > 0 && (
                        <span className="mw-cats__count">
                            <i className="bi bi-grid mw-cats__count-icon" aria-hidden="true" />
                            {category.count} {labels.courses}
                        </span>
                    )}
                </span>
            </a>
        );
    });

    // Carousel: a horizontally scrolling track with prev/next arrows (arrows only when there is
    // more to reveal than fits in one view). Mirrors the product carousel's scroll-by-page UX.
    if (isCarousel) {
        const showArrows = categories.length > cols;
        const scrollByPage = (direction: number) => {
            const track = trackRef.current;
            if (track) {
                track.scrollBy({left: direction * track.clientWidth * 0.85, behavior: "smooth"});
            }
        };
        return (
            <section className="mw-cats mw-cats--carousel" style={secStyle}>
                <header className="mw-cats__head">
                    <div className="mw-cats__heading">
                        {title && <h2 className="mw-cats__title">{title}</h2>}
                        {subtitle && <p className="mw-cats__subtitle">{subtitle}</p>}
                    </div>
                    {showArrows && (
                        <div className="mw-cats__nav">
                            <button type="button" className="mc-button mw-cats__arrow" data-mc-button="light"
                                data-mc-button-size="icon" aria-label={labels.scrollleft}
                                onClick={() => scrollByPage(-1)}>
                                <i className="bi bi-chevron-left" aria-hidden="true" />
                            </button>
                            <button type="button" className="mc-button mw-cats__arrow" data-mc-button="light"
                                data-mc-button-size="icon" aria-label={labels.scrollright}
                                onClick={() => scrollByPage(1)}>
                                <i className="bi bi-chevron-right" aria-hidden="true" />
                            </button>
                        </div>
                    )}
                </header>
                <div className="mw-cats__track" style={listStyle} ref={trackRef}>
                    {tiles}
                </div>
            </section>
        );
    }

    return (
        <section className={`mw-cats mw-cats--${variant}`} style={secStyle}>
            {(title || subtitle) && (
                <header className="mw-cats__head">
                    {title && <h2 className="mw-cats__title">{title}</h2>}
                    {subtitle && <p className="mw-cats__subtitle">{subtitle}</p>}
                </header>
            )}
            <div className="mw-cats__grid" style={listStyle}>
                {tiles}
            </div>
        </section>
    );
}

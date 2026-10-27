// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Partner logo band for the Modern Commerce storefront.
 *
 * @module     local_moderncommerce/storefront/partners
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties, useRef} from "react";

type PartnerLogo = {
    image: string;
    name: string;
    linkurl: string;
};

type PartnersProps = {
    heading?: string;
    logos: PartnerLogo[];
    variant?: string; // grid | carousel | marquee
    visibleLogos?: number;
    speed?: number; // Marquee: seconds for one full pass of the track.
    pauseOnHover?: boolean;
    greyscale?: boolean;
    logoHeight?: number;
    logoGap?: number;
    bgColor?: string;
    headingColor?: string;
    headingFontSize?: number;
    paddingTop?: number;
    paddingBottom?: number;
    labels?: {scrollleft?: string; scrollright?: string};
};

const VARIANTS = ["grid", "carousel", "marquee"];

const pxValue = (value: number | undefined, fallback: number, max: number): number => {
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? Math.min(max, parsed) : fallback;
};

export default function PartnerLogos({
    heading = "",
    logos,
    variant = "grid",
    visibleLogos = 5,
    speed = 30,
    pauseOnHover = true,
    greyscale = false,
    logoHeight = 48,
    logoGap = 48,
    bgColor = "",
    headingColor = "",
    headingFontSize = 0,
    paddingTop,
    paddingBottom,
    labels = {},
}: PartnersProps) {
    const trackRef = useRef<HTMLDivElement | null>(null);

    if (!logos || logos.length === 0) {
        return null;
    }

    const mode = VARIANTS.indexOf(variant) >= 0 ? variant : "grid";
    const cols = Math.max(1, Math.min(12, Math.round(Number(visibleLogos) || 5)));
    const gap = pxValue(logoGap, 48, 160);

    const style = {} as CSSProperties & Record<string, string | number>;
    if (bgColor) {
        style.backgroundColor = bgColor;
        style["--mc-partners-bg"] = bgColor;
    }
    if (headingColor) {
        style["--mc-partners-heading-color"] = headingColor;
    }
    const headingSize = pxValue(headingFontSize, 0, 96);
    if (headingSize > 0) {
        style["--mc-partners-heading-font-size"] = `${headingSize}px`;
    }
    style["--mc-partners-logo-height"] = `${pxValue(logoHeight, 48, 200)}px`;
    style["--mc-partners-gap"] = `${gap}px`;
    if (paddingTop && paddingTop > 0) {
        style["--mc-partners-padding-top"] = `${paddingTop}px`;
    }
    if (paddingBottom && paddingBottom > 0) {
        style["--mc-partners-padding-bottom"] = `${paddingBottom}px`;
    }

    const sectionClass = `mw-partners mw-partners--${mode}${greyscale ? " mw-partners--greyscale" : ""}`;

    // The marquee duplicates the track for a seamless loop; clones are hidden from assistive
    // tech and skipped by the keyboard so each partner is announced and tabbed to exactly once.
    const logoNode = (logo: PartnerLogo, key: string, cloned = false) => {
        const img = (
            <img className="mw-partners__img" src={logo.image} alt={cloned ? "" : logo.name}
                loading="lazy" decoding="async" />
        );
        if (logo.linkurl) {
            return (
                <a className="mw-partners__item" href={logo.linkurl} key={key}
                    rel="noopener noreferrer" title={logo.name || undefined}
                    aria-hidden={cloned || undefined} tabIndex={cloned ? -1 : undefined}>
                    {img}
                </a>
            );
        }
        return (
            <span className="mw-partners__item" key={key} aria-hidden={cloned || undefined}>
                {img}
            </span>
        );
    };

    const headingNode = heading ? <h2 className="mw-partners__heading">{heading}</h2> : null;

    if (mode === "marquee") {
        const trackStyle = {
            "--mc-partners-speed": `${Math.max(5, Math.min(240, Number(speed) || 30))}s`,
        } as CSSProperties & Record<string, string>;
        const marqueeClass = `mw-partners__marquee${pauseOnHover ? " mw-partners__marquee--pausable" : ""}`;
        return (
            <section className={sectionClass} style={style}>
                {headingNode}
                <div className={marqueeClass}>
                    <div className="mw-partners__marquee-track" style={trackStyle}>
                        {logos.map((logo, i) => logoNode(logo, `a-${i}`))}
                        {logos.map((logo, i) => logoNode(logo, `b-${i}`, true))}
                    </div>
                </div>
            </section>
        );
    }

    // Carousel: a horizontally scrolling track with prev/next arrows, shown only when there
    // are more logos than fit in one view. Mirrors the category carousel's scroll-by-page UX.
    if (mode === "carousel") {
        const showArrows = logos.length > cols;
        const scrollByPage = (direction: number) => {
            const track = trackRef.current;
            if (track) {
                track.scrollBy({left: direction * track.clientWidth * 0.85, behavior: "smooth"});
            }
        };
        return (
            <section className={sectionClass} style={style}>
                <header className="mw-partners__head">
                    {headingNode}
                    {showArrows && (
                        <div className="mw-partners__nav">
                            <button type="button" className="mc-button mw-partners__arrow" data-mc-button="light"
                                data-mc-button-size="icon" aria-label={labels.scrollleft}
                                onClick={() => scrollByPage(-1)}>
                                <i className="bi bi-chevron-left" aria-hidden="true" />
                            </button>
                            <button type="button" className="mc-button mw-partners__arrow" data-mc-button="light"
                                data-mc-button-size="icon" aria-label={labels.scrollright}
                                onClick={() => scrollByPage(1)}>
                                <i className="bi bi-chevron-right" aria-hidden="true" />
                            </button>
                        </div>
                    )}
                </header>
                <div className="mw-partners__track" style={{"--mw-cols": String(cols)} as CSSProperties}
                    ref={trackRef}>
                    {logos.map((logo, i) => logoNode(logo, `c-${i}`))}
                </div>
            </section>
        );
    }

    return (
        <section className={sectionClass} style={style}>
            {headingNode}
            <div className="mw-partners__grid">
                {logos.map((logo, i) => logoNode(logo, `g-${i}`))}
            </div>
        </section>
    );
}

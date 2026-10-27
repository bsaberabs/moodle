// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Shared, presentational hero slider for the Modern Commerce storefront.
 * Pure UI — no web-service calls. Layout is driven by the `design` variant
 * (overlay | split | card).
 *
 * @module     local_moderncommerce/storefront/slider
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties, useEffect, useState} from "react";

export type Slide = {
    id: number;
    image: string;
    heading: string;
    subheading: string;
    ctalabel: string;
    ctaurl: string;
    ctastyle: string;
    bgcolor: string;
};

export type SliderData = {
    slides: Slide[];
    autoplay: boolean;
    interval: number;
    showarrows: boolean;
    showdots: boolean;
    design: string;
    buttoncolor: string;
    buttontextcolor: string;
    buttonfontsize: number;
    buttonradius: number;
    labels: {
        featured: string;
        previousslide: string;
        nextslide: string;
        chooseslide: string;
        gotoslide: string;
    };
};

export const SLIDER_DESIGNS = ["overlay", "split", "card"] as const;

export const sliderDataDefaults = (): SliderData => ({
    slides: [],
    autoplay: true,
    interval: 6000,
    showarrows: true,
    showdots: true,
    design: "overlay",
    buttoncolor: "",
    buttontextcolor: "",
    buttonfontsize: 0,
    buttonradius: 0,
    labels: {featured: "", previousslide: "", nextslide: "", chooseslide: "", gotoslide: ""},
});

const ctaClass = (style: string): string => {
    switch (style) {
        case "light":
            return "mc-button mw-hero__cta mw-hero__cta--light";
        case "outline":
            return "mc-button mw-hero__cta mw-hero__cta--outline";
        case "primary":
        default:
            return "mc-button mw-hero__cta mw-hero__cta--primary";
    }
};

const ctaVariant = (style: string): string => {
    switch (style) {
        case "light":
            return "light";
        case "outline":
            return "soft";
        case "primary":
        default:
            return "primary";
    }
};

function HeroSlide({slide, active}: {slide: Slide; active: boolean}) {
    const slideBg = slide.bgcolor || "var(--mc-primary)";
    const mediaStyle: CSSProperties = slide.image
        ? {backgroundImage: `url("${slide.image}")`}
        : {backgroundColor: slideBg};
    const slideStyle = {"--mw-slide-bg": slideBg} as CSSProperties;

    return (
        <div
            className={`mw-hero__slide${active ? " mw-hero__slide--active" : ""}`}
            style={slideStyle}
            aria-hidden={!active}
            role="group"
            aria-roledescription="slide"
        >
            <div className="mw-hero__media" style={mediaStyle} aria-hidden="true" />
            <div className="mw-hero__inner">
                {slide.heading && <h2 className="mw-hero__heading">{slide.heading}</h2>}
                {slide.subheading && <p className="mw-hero__subheading">{slide.subheading}</p>}
                {slide.ctalabel && slide.ctaurl && (
                    <a className={ctaClass(slide.ctastyle)} data-mc-button={ctaVariant(slide.ctastyle)}
                        href={slide.ctaurl}>
                        {slide.ctalabel}
                    </a>
                )}
            </div>
        </div>
    );
}

export function HeroSlider({data, title}: {data: SliderData; title?: string}) {
    const slides = data.slides ?? [];
    const count = slides.length;
    const design = data.design || "overlay";
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);

    // Keep the active index valid if the slide set shrinks (live preview edits).
    useEffect(() => {
        setIndex((current) => (current >= count ? 0 : current));
    }, [count]);

    useEffect(() => {
        if (!data.autoplay || paused || count <= 1) {
            return undefined;
        }
        const timer = window.setInterval(() => {
            setIndex((current) => (current + 1) % count);
        }, data.interval);
        return () => window.clearInterval(timer);
    }, [data.autoplay, data.interval, paused, count]);

    if (count === 0) {
        return null;
    }

    const go = (next: number) => setIndex(((next % count) + count) % count);
    const sectionStyle: CSSProperties & Record<string, string> = {};
    if (data.buttoncolor) {
        sectionStyle["--mc-slider-button-bg"] = data.buttoncolor;
    }
    if (data.buttontextcolor) {
        sectionStyle["--mc-slider-button-text"] = data.buttontextcolor;
    }
    if (Number(data.buttonfontsize) > 0) {
        sectionStyle["--mc-slider-button-font-size"] = `${Number(data.buttonfontsize)}px`;
    }
    if (Number(data.buttonradius) > 0) {
        sectionStyle["--mc-slider-button-radius"] = `${Number(data.buttonradius)}px`;
    }

    return (
        <section
            className={`mw-hero mw-hero--${design}`}
            style={sectionStyle}
            aria-roledescription="carousel"
            aria-label={title || data.labels.featured}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
        >
            <div className="mw-hero__track">
                {slides.map((slide, i) => (
                    <HeroSlide slide={slide} active={i === index} key={slide.id} />
                ))}
            </div>

            {data.showarrows && count > 1 && (
                <>
                    <button
                        type="button"
                        className="mc-button mw-hero__arrow mw-hero__arrow--prev"
                        data-mc-button="light"
                        data-mc-button-size="icon"
                        aria-label={data.labels.previousslide}
                        onClick={() => go(index - 1)}
                    >
                        <i className="bi bi-chevron-left" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        className="mc-button mw-hero__arrow mw-hero__arrow--next"
                        data-mc-button="light"
                        data-mc-button-size="icon"
                        aria-label={data.labels.nextslide}
                        onClick={() => go(index + 1)}
                    >
                        <i className="bi bi-chevron-right" aria-hidden="true" />
                    </button>
                </>
            )}

            {data.showdots && count > 1 && (
                <div className="mw-hero__dots" role="tablist" aria-label={data.labels.chooseslide}>
                    {slides.map((slide, i) => (
                        <button
                            type="button"
                            className={`mc-button mw-hero__dot${i === index ? " mw-hero__dot--active" : ""}`}
                            data-mc-button="ghost"
                            data-mc-button-size="icon"
                            aria-label={data.labels.gotoslide.replace('{$a}', String(i + 1))}
                            aria-selected={i === index}
                            role="tab"
                            onClick={() => go(i)}
                            key={slide.id}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

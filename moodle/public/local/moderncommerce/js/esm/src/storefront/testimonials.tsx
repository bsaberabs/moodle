// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Testimonials grid for the Modern Commerce storefront.
 *
 * @module     local_moderncommerce/storefront/testimonials
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

type Testimonial = {
    quote: string;
    author: string;
    role: string;
    rating: number;
};

import {CSSProperties} from "react";

type TestimonialGridProps = {
    title?: string;
    subtitle?: string;
    testimonials: Testimonial[];
    style?: CSSProperties;
};

const initials = (name: string): string => {
    const parts = name.split(/\s+/).filter(Boolean).slice(0, 2);
    const text = parts.map((part) => part.charAt(0).toUpperCase()).join("");
    return text || "★";
};

function Stars({rating}: {rating: number}) {
    return (
        <span className="mw-tm__stars" aria-label={`${rating} / 5`}>
            {[1, 2, 3, 4, 5].map((n) => (
                <i className={`bi ${n <= rating ? "bi-star-fill" : "bi-star"}`} aria-hidden="true" key={n} />
            ))}
        </span>
    );
}

export default function TestimonialGrid({title, subtitle, testimonials, style}: TestimonialGridProps) {
    if (!testimonials || testimonials.length === 0) {
        return null;
    }

    return (
        <section className="mw-tm" style={style}>
            {(title || subtitle) && (
                <header className="mw-tm__head">
                    {title && <h2 className="mw-tm__title">{title}</h2>}
                    {subtitle && <p className="mw-tm__subtitle">{subtitle}</p>}
                </header>
            )}
            <div className="mw-tm__grid">
                {testimonials.map((item, index) => (
                    <figure className="mw-tm__card" key={index}>
                        {item.rating > 0 && <Stars rating={item.rating} />}
                        <blockquote className="mw-tm__quote">{item.quote}</blockquote>
                        <figcaption className="mw-tm__author">
                            <span className="mw-tm__avatar" aria-hidden="true">{initials(item.author)}</span>
                            <span className="mw-tm__meta">
                                {item.author && <span className="mw-tm__name">{item.author}</span>}
                                {item.role && <span className="mw-tm__role">{item.role}</span>}
                            </span>
                        </figcaption>
                    </figure>
                ))}
            </div>
        </section>
    );
}

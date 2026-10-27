// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

import {CSSProperties} from "react";

/**
 * Instructor spotlight grid for the Modern Commerce storefront.
 *
 * @module     local_moderncommerce/storefront/instructors
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

type Instructor = {
    name: string;
    role: string;
    bio: string;
    photo: string;
};

type InstructorSpotlightProps = {
    title?: string;
    subtitle?: string;
    instructors: Instructor[];
    style?: CSSProperties;
};

const initials = (name: string): string => {
    const parts = name.split(/\s+/).filter(Boolean).slice(0, 2);
    return parts.map((part) => part.charAt(0).toUpperCase()).join("") || "★";
};

export default function InstructorSpotlight({title, subtitle, instructors, style}: InstructorSpotlightProps) {
    if (!instructors || instructors.length === 0) {
        return null;
    }

    return (
        <section className="mw-inst" style={style}>
            {(title || subtitle) && (
                <header className="mw-inst__head">
                    {title && <h2 className="mw-inst__title">{title}</h2>}
                    {subtitle && <p className="mw-inst__subtitle">{subtitle}</p>}
                </header>
            )}
            <div className="mw-inst__grid">
                {instructors.map((person, index) => (
                    <figure className="mw-inst__card" key={index}>
                        <div className="mw-inst__avatar">
                            {person.photo
                                ? <img src={person.photo} alt={person.name} loading="lazy" />
                                : <span aria-hidden="true">{initials(person.name)}</span>}
                        </div>
                        <figcaption className="mw-inst__meta">
                            <span className="mw-inst__name">{person.name}</span>
                            {person.role && <span className="mw-inst__role">{person.role}</span>}
                            {person.bio && <p className="mw-inst__bio">{person.bio}</p>}
                        </figcaption>
                    </figure>
                ))}
            </div>
        </section>
    );
}

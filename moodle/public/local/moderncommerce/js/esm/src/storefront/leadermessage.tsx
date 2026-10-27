// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Leader message block for the Modern Commerce storefront.
 *
 * A portrait beside a signed message from a named person - an ED/CEO welcome, a
 * Vice-Chancellor's address, a director's statement. Four design treatments share
 * one markup contract; the portrait can sit on either side; every element except
 * the message body can be switched off by the admin.
 *
 * @module     local_moderncommerce/storefront/leadermessage
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties} from "react";

export type LeaderMessageProps = {
    style?: string;
    mediaposition?: string;

    eyebrow?: string;
    showeyebrow?: boolean;
    title?: string;
    showtitle?: boolean;
    message?: string;
    justifytext?: boolean;

    photo?: string;
    showphoto?: boolean;
    photoalt?: string;

    leadername?: string;
    leaderrole?: string;
    showattribution?: boolean;

    signature?: string;
    showsignature?: boolean;

    ctalabel?: string;
    ctaurl?: string;
    showcta?: boolean;

    showaccent?: boolean;

    cssvars?: CSSProperties;
};

const STYLES = ["elevated", "overlay", "quote", "flat"];

const normStyle = (value?: string): string => (
    value && STYLES.includes(value) ? value : "elevated"
);

/**
 * The portrait, wrapped in whatever frame the active treatment calls for.
 *
 * The decorative accent shape is only drawn for treatments that use it, and is
 * always aria-hidden - it carries no meaning for screen readers.
 */
function Portrait({
    photo,
    alt,
    treatment,
    showaccent,
}: {
    photo: string;
    alt: string;
    treatment: string;
    showaccent: boolean;
}) {
    const drawAccent = showaccent && (treatment === "elevated" || treatment === "overlay");

    return (
        <div className={`mc-leader__media mc-leader__media--${treatment}`}>
            {drawAccent && <span className="mc-leader__accent-shape" aria-hidden="true" />}
            <img className="mc-leader__photo" src={photo} alt={alt} loading="lazy" />
        </div>
    );
}

/**
 * Name + role, plus the optional signature that sits above them.
 */
function Attribution({
    name,
    role,
    signature,
    showsignature,
    showattribution,
    treatment,
}: {
    name: string;
    role: string;
    signature: string;
    showsignature: boolean;
    showattribution: boolean;
    treatment: string;
}) {
    if (!showsignature && !showattribution) {
        return null;
    }

    return (
        <div className={`mc-leader__attribution mc-leader__attribution--${treatment}`}>
            {showsignature && (
                <img className="mc-leader__signature" src={signature} alt="" loading="lazy" />
            )}
            {showattribution && (
                <p className="mc-leader__byline">
                    {name && <span className="mc-leader__name">{name}</span>}
                    {role && <span className="mc-leader__role">{role}</span>}
                </p>
            )}
        </div>
    );
}

export default function LeaderMessage(props: LeaderMessageProps) {
    const treatment = normStyle(props.style);
    const mediaLeft = props.mediaposition === "left";
    const hasPhoto = Boolean(props.showphoto && props.photo);

    const showTitle = Boolean(props.showtitle && props.title);
    const showEyebrow = Boolean(props.showeyebrow && props.eyebrow);
    const showCta = Boolean(props.showcta && props.ctalabel && props.ctaurl);
    const showSignature = Boolean(props.showsignature && props.signature);
    const showAttribution = Boolean(props.showattribution && (props.leadername || props.leaderrole));

    const sectionClasses = [
        "mc-leader",
        `mc-leader--${treatment}`,
        mediaLeft ? "mc-leader--media-left" : "mc-leader--media-right",
        hasPhoto ? "mc-leader--has-media" : "mc-leader--no-media",
    ].join(" ");

    const copy = (
        <div className="mc-leader__copy">
            {showEyebrow && <p className="mc-leader__eyebrow">{props.eyebrow}</p>}

            {showTitle && (
                <h2 className="mc-leader__title">
                    {props.title}
                </h2>
            )}

            {props.showaccent && <span className="mc-leader__accent-rule" aria-hidden="true" />}

            {props.message && (
                <div
                    className={`mc-leader__message${props.justifytext ? " mc-leader__message--justify" : ""}`}
                    // Body copy is admin-authored rich text, already cleaned server-side
                    // by format_text() before it reaches the browser.
                    dangerouslySetInnerHTML={{__html: props.message}}
                />
            )}

            <Attribution
                name={props.leadername || ""}
                role={props.leaderrole || ""}
                signature={props.signature || ""}
                showsignature={showSignature}
                showattribution={showAttribution}
                treatment={treatment}
            />

            {showCta && (
                <a className="mc-leader__cta" href={props.ctaurl}>
                    {props.ctalabel}
                </a>
            )}
        </div>
    );

    const media = hasPhoto ? (
        <Portrait
            photo={props.photo || ""}
            alt={props.photoalt || ""}
            treatment={treatment}
            showaccent={Boolean(props.showaccent)}
        />
    ) : null;

    return (
        <section className={sectionClasses} style={props.cssvars}>
            <div className="mc-leader__inner container">
                <div className="mc-leader__grid">
                    {/* Source order puts the copy first so it always leads for
                        screen readers and on mobile; CSS order flips the columns
                        when the admin asks for the portrait on the left. */}
                    {copy}
                    {media}
                </div>
            </div>
        </section>
    );
}

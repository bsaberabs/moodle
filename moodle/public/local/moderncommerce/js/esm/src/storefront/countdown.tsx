// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Countdown bar for the Modern Commerce storefront.
 *
 * @module     local_moderncommerce/storefront/countdown
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties, useEffect, useState} from "react";

type CountdownLabels = {
    days: string;
    hours: string;
    minutes: string;
    seconds: string;
};

type CountdownBarProps = {
    heading: string;
    endtime: number; // Unix seconds.
    expiredmessage: string;
    ctalabel: string;
    ctaurl: string;
    bgcolor?: string;
    textcolor?: string;
    headingcolor?: string;
    headingfontsize?: number;
    timerbgcolor?: string;
    timernumbercolor?: string;
    timernumberfontsize?: number;
    timerlabelcolor?: string;
    timerlabelfontsize?: number;
    buttoncolor?: string;
    buttontextcolor?: string;
    expiredbgcolor?: string;
    expiredtextcolor?: string;
    paddingTop?: number; // px; 0 keeps the CSS default.
    paddingBottom?: number; // px; 0 keeps the CSS default.
    labels: CountdownLabels;
};

const pad = (value: number): string => String(value).padStart(2, "0");

export default function CountdownBar(
    {
        heading,
        endtime,
        expiredmessage,
        ctalabel,
        ctaurl,
        bgcolor,
        textcolor,
        headingcolor,
        headingfontsize,
        timerbgcolor,
        timernumbercolor,
        timernumberfontsize,
        timerlabelcolor,
        timerlabelfontsize,
        buttoncolor,
        buttontextcolor,
        expiredbgcolor,
        expiredtextcolor,
        paddingTop,
        paddingBottom,
        labels,
    }: CountdownBarProps
) {
    const style: CSSProperties & Record<string, string | number> = {};
    if (bgcolor) {
        style["--mc-countdown-bg"] = bgcolor;
    }
    if (textcolor) {
        style.color = textcolor;
        style["--mc-countdown-text"] = textcolor;
    }
    if (headingcolor) {
        style["--mc-countdown-heading-color"] = headingcolor;
    }
    if (headingfontsize && headingfontsize > 0) {
        style["--mc-countdown-heading-font-size"] = `${headingfontsize}px`;
    }
    if (timerbgcolor) {
        style["--mc-countdown-unit-bg"] = timerbgcolor;
    }
    if (timernumbercolor) {
        style["--mc-countdown-number-color"] = timernumbercolor;
    }
    if (timernumberfontsize && timernumberfontsize > 0) {
        style["--mc-countdown-number-font-size"] = `${timernumberfontsize}px`;
    }
    if (timerlabelcolor) {
        style["--mc-countdown-label-color"] = timerlabelcolor;
    }
    if (timerlabelfontsize && timerlabelfontsize > 0) {
        style["--mc-countdown-label-font-size"] = `${timerlabelfontsize}px`;
    }
    if (buttoncolor) {
        style["--mc-countdown-button-bg"] = buttoncolor;
    }
    if (buttontextcolor) {
        style["--mc-countdown-button-text"] = buttontextcolor;
    }
    if (expiredbgcolor) {
        style["--mc-countdown-expired-bg"] = expiredbgcolor;
    }
    if (expiredtextcolor) {
        style["--mc-countdown-expired-text"] = expiredtextcolor;
    }
    if (paddingTop && paddingTop > 0) {
        style.paddingTop = paddingTop;
    }
    if (paddingBottom && paddingBottom > 0) {
        style.paddingBottom = paddingBottom;
    }
    const sectionStyle = Object.keys(style).length > 0 ? style : undefined;
    const endMs = endtime * 1000;
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(timer);
    }, []);

    if (!endtime) {
        return null;
    }

    const diff = Math.max(0, endMs - now);
    const expired = diff <= 0;

    if (expired) {
        if (!expiredmessage) {
            return null;
        }
        return (
            <section className="mw-countdown mw-countdown--expired" style={sectionStyle}>
                <span className="mw-countdown__heading">{expiredmessage}</span>
            </section>
        );
    }

    const total = Math.floor(diff / 1000);
    const units: Array<[number, string]> = [
        [Math.floor(total / 86400), labels.days],
        [Math.floor((total % 86400) / 3600), labels.hours],
        [Math.floor((total % 3600) / 60), labels.minutes],
        [total % 60, labels.seconds],
    ];

    return (
        <section className="mw-countdown" style={sectionStyle}>
            {heading && <span className="mw-countdown__heading">{heading}</span>}
            <span className="mw-countdown__timer">
                {units.map(([value, label]) => (
                    <span className="mw-countdown__unit" key={label}>
                        <span className="mw-countdown__num">{pad(value)}</span>
                        <span className="mw-countdown__label">{label}</span>
                    </span>
                ))}
            </span>
            {ctalabel && ctaurl && (
                <a className="mc-button mw-countdown__cta" data-mc-button="light" href={ctaurl}>{ctalabel}</a>
            )}
        </section>
    );
}

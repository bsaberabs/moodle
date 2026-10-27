// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Split video-hero widget: copy + click-to-play video panel + floating stat card.
 *
 * @module     local_moderncommerce/storefront/videohero
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties, Fragment, useState} from "react";

type Button = {label: string; url: string};
type InfoItem = {iconclass: string; hasicon: boolean; title: string; text: string};
type Video = {
    mode: string; haspanelvideo: boolean; embedurl: string; fileurl: string; mimetype: string;
    posterurl: string; hasposter: boolean; title: string; hastitle: boolean;
};
export type VideoHeroData = {
    headinglines: string[]; subtext: string;
    primary: Button; secondary: Button | null; hassecondary: boolean;
    bgcolor: string; accent: string; accentcolor?: string;
    primarybuttoncolor?: string; primarybuttontextcolor?: string;
    secondarybuttoncolor?: string; secondarybuttontextcolor?: string;
    infocardbgcolor?: string; infoiconbgcolor?: string; infoiconcolor?: string;
    infoheadingcolor?: string; infoheadingfontsize?: string | number; infotextcolor?: string;
    video: Video; infoitems: InfoItem[]; quote: {text: string; author: string; hasauthor: boolean};
    showquote?: boolean | number | string;
    labels: {playvideo: string};
};

// The gallery patches settings client-side as 1/0, so accept both booleans and numeric flags.
const isOff = (value: unknown): boolean =>
    value === false || value === 0 || value === "0" || value === "false";

const ArrowRight = () => (
    <svg className="bi" width="1em" height="1em" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"
        style={{marginLeft: ".5rem"}}>
        <path fillRule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0
            1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8" />
    </svg>
);
const PlayIcon = ({cls}: {cls: string}) => (
    <svg className={cls} width="1em" height="1em" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
        <path d="m11.596 8.697-6.363 3.692c-.54.313-1.233-.066-1.233-.697V4.308c0-.63.692-1.01
            1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393" />
    </svg>
);

export default function VideoHero({data, title}: {data: VideoHeroData; title?: string}) {
    const [playing, setPlaying] = useState(false);
    const v = data.video;
    const infoHeadingFontSize = Number(data.infoheadingfontsize || 0);
    const rootStyle = {
        ["--mc-vh-bg" as string]: data.bgcolor,
        ["--mc-vh-accent" as string]: data.accentcolor || data.accent,
        ["--mc-vh-primary-button-bg" as string]: data.primarybuttoncolor || undefined,
        ["--mc-vh-primary-button-text" as string]: data.primarybuttontextcolor || undefined,
        ["--mc-vh-secondary-button-bg" as string]: data.secondarybuttoncolor || undefined,
        ["--mc-vh-secondary-button-text" as string]: data.secondarybuttontextcolor || undefined,
        ["--mc-vh-info-card-bg" as string]: data.infocardbgcolor || undefined,
        ["--mc-vh-info-icon-bg" as string]: data.infoiconbgcolor || undefined,
        ["--mc-vh-info-icon-color" as string]: data.infoiconcolor || undefined,
        ["--mc-vh-info-heading-color" as string]: data.infoheadingcolor || undefined,
        ["--mc-vh-info-heading-font-size" as string]: infoHeadingFontSize > 0 ? `${infoHeadingFontSize}px` : undefined,
        ["--mc-vh-info-text-color" as string]: data.infotextcolor || undefined,
    } as CSSProperties;

    const showQuote = !isOff(data.showquote) && data.quote.text !== "";
    // With the quote hidden the info boxes own the whole card, so split it into equal
    // Bootstrap-style columns: 3 boxes => 4+4+4, 4 boxes => 3+3+3+3.
    const infoCols = data.infoitems.length;
    const infoCardClass = "mc-vh__infocard"
        + (!showQuote && infoCols >= 2 && infoCols <= 4 ? ` mc-vh__infocard--cols-${infoCols}` : "");

    const play = () => { if (v.haspanelvideo) { setPlaying(true); } };
    const boxStyle: CSSProperties = v.hasposter ? {backgroundImage: `url('${v.posterurl}')`} : {};
    const caption = v.hastitle ? v.title : title || "";

    return (
        <div className="mc-vh" style={rootStyle}>
            <section className="mc-vh__hero">
                <div className="mc-vh__inner">
                    <div className="mc-vh__copy">
                        <h1 className="mc-vh__heading">
                            {data.headinglines.map((line, i) => (
                                <Fragment key={i}>{i > 0 && <br />}{line}</Fragment>
                            ))}
                        </h1>
                        <p className="mc-vh__lead">{data.subtext}</p>
                        <div className="mc-vh__actions">
                            <a className="mc-button mc-vh__cta" data-mc-button="light" role="button"
                                href={data.primary.url}>
                                {data.primary.label}<ArrowRight />
                            </a>
                            {data.hassecondary && data.secondary && (
                                <a className="mc-button mc-vh__cta-secondary" data-mc-button="ghost" role="button"
                                    href={data.secondary.url}>
                                    {data.secondary.label}
                                </a>
                            )}
                        </div>
                    </div>
                    <div className="mc-vh__panel">
                        <div className={"mc-vh__videobox" + (v.haspanelvideo ? " mc-vh__videobox--playable" : "")
                            + (playing ? " is-playing" : "")} style={boxStyle}
                            onClick={v.haspanelvideo && !playing ? play : undefined}>
                            {playing && v.mode === "embed" && (
                                <iframe className="mc-vh__embedframe" src={v.embedurl} title={caption}
                                    frameBorder={0} allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                                    allowFullScreen />
                            )}
                            {playing && v.mode === "file" && (
                                <video className="mc-vh__filevideo" controls autoPlay playsInline>
                                    <source src={v.fileurl} type={v.mimetype || undefined} />
                                </video>
                            )}
                            {!playing && (<>
                                <div className="mc-vh__playwrap">
                                    <button type="button" className="mc-button mc-vh__playbtn"
                                        data-mc-button="light" data-mc-button-size="icon"
                                        aria-label={data.labels.playvideo} aria-hidden={v.haspanelvideo ? undefined : true}
                                        tabIndex={v.haspanelvideo ? undefined : -1}
                                        onClick={v.haspanelvideo ? (e) => { e.stopPropagation(); play(); } : undefined}>
                                        <PlayIcon cls="mc-vh__playicon" />
                                    </button>
                                </div>
                                {caption && (
                                    <div className="mc-vh__videocaption">
                                        <PlayIcon cls="mc-vh__accent mc-vh__capicon" />
                                        <span className="mc-vh__videotitle">{caption}</span>
                                    </div>
                                )}
                            </>)}
                        </div>
                    </div>
                </div>
            </section>

            {(data.infoitems.length > 0 || showQuote) && (
                <div className="mc-vh__infowrap">
                    <div className={infoCardClass}>
                        {data.infoitems.map((item, i) => (
                            <div className="mc-vh__infocol" key={i}>
                                {item.hasicon && (
                                    <div className="mc-vh__infoicon">
                                        <i className={"bi bi-" + item.iconclass} aria-hidden="true" />
                                    </div>
                                )}
                                <div>
                                    <h6 className="mc-vh__infotitle">{item.title}</h6>
                                    <p className="mc-vh__infotext">{item.text}</p>
                                </div>
                            </div>
                        ))}
                        {showQuote && (
                            <div className="mc-vh__quote">
                                <p className="mc-vh__quotetext">
                                    <i className="bi bi-quote mc-vh__accent mc-vh__quoteicon" aria-hidden="true" />
                                    {data.quote.text}
                                </p>
                                {data.quote.hasauthor && (
                                    <p className="mc-vh__quoteauthor">- {data.quote.author}</p>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

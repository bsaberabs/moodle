// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Shared learner "list row" card for Modern Commerce.
 *
 * One central, editable component for the horizontal media-row layout used by
 * learner list views (catalogue/library list mode, dashboard "Continue learning"
 * list mode, and any future learner list). Layout: thumbnail on the left, a
 * content column in the middle (meta line, title, subtitle, body), and a
 * right-aligned actions column. Every slot is a ReactNode, so each page composes
 * its own content while sharing one layout/markup that can be restyled here.
 *
 * @module     local_moderncommerce/learner_list_row
 * @copyright  2026 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactNode} from "react";
import {mcClasses} from "./design_system";

export type LearnerListRowProps = {
    /** Thumbnail image URL. When empty a neutral placeholder block is rendered. */
    thumbnail?: string;
    /** Optional link to wrap the thumbnail (e.g. the course/product URL). */
    thumbnailHref?: string;
    /** Alt text for the thumbnail image. */
    thumbnailAlt?: string;
    /** Top meta line (badges, category, duration, status…). */
    meta?: ReactNode;
    /** Row title. */
    title: ReactNode;
    /** Optional link for the title. */
    titleHref?: string;
    /** Heading element used for the title (defaults to h2, matching the catalogue). */
    titleAs?: "h2" | "h3";
    /** Muted subtitle line under the title (e.g. level). */
    subtitle?: ReactNode;
    /** Extra content under the subtitle (e.g. a progress bar). */
    body?: ReactNode;
    /** Right-aligned actions column (price, buttons…). */
    actions?: ReactNode;
};

const THUMB_WIDTH = 120;
const THUMB_HEIGHT = 68;

function Thumbnail({thumbnail, thumbnailHref, thumbnailAlt}: Pick<LearnerListRowProps, "thumbnail" | "thumbnailHref" | "thumbnailAlt">) {
    const image = thumbnail ? (
        <img
            src={thumbnail}
            alt={thumbnailAlt ?? ""}
            width={THUMB_WIDTH}
            height={THUMB_HEIGHT}
            className="rounded object-fit-cover flex-shrink-0"
            loading="lazy"
        />
    ) : (
        <span className="rounded bg-light flex-shrink-0" style={{width: THUMB_WIDTH, height: THUMB_HEIGHT}} />
    );

    if (thumbnailHref) {
        return (
            <a href={thumbnailHref} className="flex-shrink-0 d-block">
                {image}
            </a>
        );
    }

    return image;
}

export default function LearnerListRow({
    thumbnail,
    thumbnailHref,
    thumbnailAlt,
    meta,
    title,
    titleHref,
    titleAs = "h2",
    subtitle,
    body,
    actions,
}: LearnerListRowProps) {
    const TitleTag = titleAs;
    const titleContent = titleHref ? (
        <a className="text-decoration-none text-reset" href={titleHref}>{title}</a>
    ) : title;

    return (
        <article className={mcClasses("mc-card mb-2 mc-learner-list-row")}>
            <div className={mcClasses("mc-card-body d-flex gap-3 align-items-start flex-wrap flex-md-nowrap")}>
                <Thumbnail thumbnail={thumbnail} thumbnailHref={thumbnailHref} thumbnailAlt={thumbnailAlt} />
                <div className="flex-grow-1 min-w-0">
                    {meta && <div className="d-flex flex-wrap align-items-center gap-2 mb-1">{meta}</div>}
                    <TitleTag className={mcClasses("mc-card-title mb-1")}>{titleContent}</TitleTag>
                    {subtitle && <p className={mcClasses("mc-cell-muted mb-0")}>{subtitle}</p>}
                    {body}
                </div>
                {actions && <div className="d-flex flex-column gap-2 align-items-md-end">{actions}</div>}
            </div>
        </article>
    );
}

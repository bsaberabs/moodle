// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React learner calendar page for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_calendar
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef} from "react";
import {Labels} from "./learner_common";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";

type LearnerCalendarProps = {
    labels: Labels;
    layout?: LearnerLayoutContext;
    monthHtml: string;
    monthFooterHtml: string;
    upcomingHtml: string;
    calendarUrl: string;
    upcomingUrl: string;
};

const label = (labels: Labels, key: string): string => labels[key] || key;

type CoreCalendar = {
    init: (root: HTMLElement, isCalendarBlock?: boolean) => void;
};

type AmdRequire = (deps: string[], callback: (calendar: CoreCalendar) => void) => void;

export default function LearnerCalendar({
    labels,
    layout,
    monthHtml,
    monthFooterHtml,
    upcomingHtml,
    calendarUrl,
    upcomingUrl,
}: LearnerCalendarProps) {
    useModernCommerceClassSync();
    const monthRootRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const root = monthRootRef.current?.querySelector<HTMLElement>(
            '[data-template="core_calendar/month_detailed"]'
        );
        if (!root || root.dataset.mcCalendarInitialised === "true") {
            return;
        }

        const amdRequire = (window as unknown as {require?: AmdRequire}).require;
        if (typeof amdRequire !== "function") {
            return;
        }

        root.dataset.mcCalendarInitialised = "true";
        amdRequire(["core_calendar/calendar"], (calendar) => {
            if (root.isConnected) {
                calendar.init(root, true);
            }
        });
    }, [monthHtml]);

    return (
        <ModernLearnerLayout
            activeNav="calendar"
            title={label(labels, "calendar")}
            subtitle={label(labels, "calendarintro")}
            labels={labels}
            layout={layout}
            actions={(
                <a className={mcClasses("mc-button btn-mc-secondary")} href={calendarUrl}>
                    <i className="bi bi-calendar" aria-hidden="true" />
                    {label(labels, "viewfullcalendar")}
                </a>
            )}
        >
            <div className={mcClasses("mc-learner-calendar")}>
                <section className={mcClasses("mc-card mc-learner-calendar__month")} aria-labelledby="mc-calendar-month-heading">
                    <div className={mcClasses("mc-card-header")}>
                        <div>
                            <h2 id="mc-calendar-month-heading">{label(labels, "calendar")}</h2>
                            <p>{label(labels, "calendarintro")}</p>
                        </div>
                    </div>
                    <div className={mcClasses("mc-card-body")}>
                        <div
                            ref={monthRootRef}
                            className={mcClasses("mc-learner-calendar__core")}
                            dangerouslySetInnerHTML={{__html: monthHtml}}
                        />
                    </div>
                    {monthFooterHtml && (
                        <div
                            className={mcClasses("mc-card-footer mc-learner-calendar__footer")}
                            dangerouslySetInnerHTML={{__html: monthFooterHtml}}
                        />
                    )}
                </section>

                <section
                    className={mcClasses("mc-card mc-learner-calendar__upcoming")}
                    aria-labelledby="mc-calendar-upcoming-heading"
                >
                    <div className={mcClasses("mc-card-header")}>
                        <div>
                            <h2 id="mc-calendar-upcoming-heading">{label(labels, "upcomingevents")}</h2>
                            <p>{label(labels, "calendarupcomingdesc")}</p>
                        </div>
                    </div>
                    <div className={mcClasses("mc-card-body")}>
                        <div
                            className={mcClasses("mc-learner-calendar__core")}
                            dangerouslySetInnerHTML={{__html: upcomingHtml}}
                        />
                    </div>
                    <div className={mcClasses("mc-card-footer mc-learner-calendar__footer")}>
                        <a href={upcomingUrl}>{label(labels, "viewupcomingevents")}</a>
                    </div>
                </section>
            </div>
        </ModernLearnerLayout>
    );
}

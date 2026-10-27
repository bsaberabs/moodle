// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Checkout success / thank-you screen, rendered inside the learner sidebar layout.
 *
 * The order confirmation content (purchased items + order summary) is built
 * server-side and passed through as trusted HTML so this view only supplies the
 * shared ModernLearnerLayout chrome (sidebar + hero band) around it.
 *
 * @module     local_moderncommerce/learner_success
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Labels} from "./learner_common";
import {useModernCommerceClassSync} from "./design_system";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";

type LearnerSuccessProps = {
    title: string;
    eyebrow?: string;
    subtitle?: string;
    contentHtml: string;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

export default function LearnerSuccess({
    title,
    eyebrow,
    subtitle,
    contentHtml,
    labels,
    layout,
}: LearnerSuccessProps) {
    useModernCommerceClassSync();

    return (
        <ModernLearnerLayout
            activeNav="orders"
            title={title}
            eyebrow={eyebrow}
            subtitle={subtitle}
            labels={labels}
            layout={layout}
        >
            <div className="mc-success-content" dangerouslySetInnerHTML={{__html: contentHtml}} />
        </ModernLearnerLayout>
    );
}

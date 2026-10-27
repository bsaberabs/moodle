// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Subscription plan pricing table for the Modern Commerce storefront.
 *
 * Plans are resolved server-side (the subscription web services all require a
 * login, and this renders on public pages), so this component is presentational
 * only: it lays the resolved plan cards out on the Bootstrap grid, 2, 3, or 4 per
 * row on desktop as the admin chooses, collapsing to 1-up on phones.
 *
 * @module     local_moderncommerce/storefront/subscription_plans
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties, useState} from "react";

export type PlanFeature = {
    name: string;
    icon: string;
};

export type SubscriptionPlan = {
    id: number;
    name: string;
    code: string;
    description?: string;
    billingcycle: string;
    isfree?: boolean;
    displayprice: string;
    haspromo?: boolean;
    displayregularprice?: string;
    trialdays?: number;
    featured?: boolean;
    features?: PlanFeature[];
};

export type PlanCycle = {
    key: string;
    label: string;
    plans: SubscriptionPlan[];
};

export type SubscriptionPlansProps = {
    style?: string;
    columns?: number;
    plans: SubscriptionPlan[];
    cycles?: PlanCycle[];
    defaultcycle?: string;
    showcycleswitch?: boolean;
    savingslabel?: string;
    cyclegrouplabel?: string;

    title?: string;
    subtitle?: string;
    showheader?: boolean;
    showfeatures?: boolean;
    ctalabel?: string;
    ctaurl?: string;
    featuredlabel?: string;

    cssvars?: CSSProperties;
};

const STYLES = ["elevated", "bordered", "minimal"];

const normStyle = (value?: string): string => (
    value && STYLES.includes(value) ? value : "elevated"
);

/**
 * Bootstrap column classes for the chosen desktop density.
 *
 * All options stay 1-up on phones; 3 and 4 per row go 2-up at the small
 * breakpoint so the cards never get too narrow to read.
 */
const columnClass = (columns: number): string => {
    if (columns === 2) {
        return "col-12 col-md-6";
    }
    if (columns === 4) {
        return "col-12 col-sm-6 col-lg-3";
    }
    return "col-12 col-sm-6 col-lg-4";
};

/**
 * Strip any "bi-"/"bi " prefix so a bare icon name is left; the markup adds
 * "bi bi-" itself. Mirrors how the feature grid normalises its icons.
 */
const normIcon = (icon: string): string => (
    (icon || "check-circle").replace(/^bi\s+/, "").replace(/^bi-/, "")
);

/** Per-cycle suffix shown next to the price. */
const cycleLabel = (cycle: string): string => {
    if (cycle === "yearly") {
        return "/year";
    }
    if (cycle === "monthly") {
        return "/month";
    }
    return "";
};

function PlanCard(
    {plan, treatment, showFeatures, ctaLabel, ctaUrl, featuredLabel}:
    {
        plan: SubscriptionPlan; treatment: string; showFeatures: boolean;
        ctaLabel: string; ctaUrl: string; featuredLabel: string;
    }
) {
    const highlighted = Boolean(plan.featured);
    const classes = [
        "mc-sp__card",
        `mc-sp__card--${treatment}`,
        highlighted ? "mc-sp__card--featured" : "",
        "h-100",
    ].filter(Boolean).join(" ");

    const features = showFeatures ? (plan.features || []) : [];
    const suffix = cycleLabel(plan.billingcycle);
    // One plan page serves every plan, so the code identifies which was chosen.
    const href = ctaUrl
        ? `${ctaUrl}${ctaUrl.includes("?") ? "&" : "?"}plan=${encodeURIComponent(plan.code)}`
        : "";

    return (
        <div className={classes}>
            {highlighted && featuredLabel && (
                <span className="mc-sp__badge">{featuredLabel}</span>
            )}

            <h3 className="mc-sp__plan-name">{plan.name}</h3>

            <p className="mc-sp__price-row">
                {plan.haspromo && plan.displayregularprice && (
                    <span className="mc-sp__price-was">{plan.displayregularprice}</span>
                )}
                <span className="mc-sp__price">{plan.displayprice}</span>
                {!plan.isfree && suffix && <span className="mc-sp__cycle">{suffix}</span>}
            </p>

            {Boolean(plan.trialdays) && (
                <p className="mc-sp__trial">{plan.trialdays} day free trial</p>
            )}

            {plan.description && <p className="mc-sp__description">{plan.description}</p>}

            {features.length > 0 && (
                <ul className="mc-sp__features">
                    {features.map((feature, index) => (
                        <li className="mc-sp__feature" key={index}>
                            <i
                                className={`mc-sp__feature-icon bi bi-${normIcon(feature.icon)}`}
                                aria-hidden="true"
                            />
                            <span>{feature.name}</span>
                        </li>
                    ))}
                </ul>
            )}

            {ctaLabel && href && (
                // .mc-button opts this link out of the storefront-wide anchor colour
                // rule, which would otherwise repaint the label with the link colour.
                <a className="mc-sp__cta mc-button" href={href}>
                    {ctaLabel}
                </a>
            )}
        </div>
    );
}

export default function SubscriptionPlans(props: SubscriptionPlansProps) {
    const treatment = normStyle(props.style);
    const columns = props.columns === 2 || props.columns === 4 ? props.columns : 3;

    // Cycles carry the per-cycle plan groups. Fall back to the flat `plans`
    // list so a payload resolved before the switch existed still renders.
    const cycles: PlanCycle[] = (props.cycles || []).filter((c) => c && c.plans && c.plans.length > 0);
    const fallback = (props.plans || []).slice(0, 12);

    const initial = cycles.some((c) => c.key === props.defaultcycle)
        ? (props.defaultcycle as string)
        : (cycles[0]?.key || "");
    const [active, setActive] = useState(initial);

    const current = cycles.find((c) => c.key === active) || cycles[0];
    const plans = (current ? current.plans : fallback).slice(0, 12);

    // No active plans configured yet: render nothing rather than an empty band.
    if (!plans.length) {
        return null;
    }

    const showHeader = props.showheader !== false && Boolean(props.title || props.subtitle);
    const showSwitch = props.showcycleswitch !== false && cycles.length > 1;

    return (
        <section className={`mc-sp mc-sp--${treatment}`} style={props.cssvars}>
            <div className="mc-sp__inner container">
                {showHeader && (
                    <header className="mc-sp__header">
                        {props.title && <h2 className="mc-sp__title">{props.title}</h2>}
                        {props.subtitle && <p className="mc-sp__subtitle">{props.subtitle}</p>}
                    </header>
                )}

                {showSwitch && (
                    <div className="mc-sp__switch-wrap">
                        <div className="mc-sp__switch" role="tablist" aria-label={props.cyclegrouplabel}>
                            {cycles.map((cycle) => {
                                const selected = cycle.key === (current ? current.key : "");
                                return (
                                    <button
                                        type="button"
                                        role="tab"
                                        key={cycle.key}
                                        aria-selected={selected}
                                        className={`mc-sp__switch-btn${selected ? " mc-sp__switch-btn--on" : ""}`}
                                        onClick={() => setActive(cycle.key)}
                                    >
                                        {cycle.label}
                                        {cycle.key === "yearly" && props.savingslabel && (
                                            <span className="mc-sp__savings">{props.savingslabel}</span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div className="row g-4 justify-content-center">
                    {plans.map((plan) => (
                        <div className={columnClass(columns)} key={plan.id}>
                            <PlanCard
                                plan={plan}
                                treatment={treatment}
                                showFeatures={props.showfeatures !== false}
                                ctaLabel={props.ctalabel || ""}
                                ctaUrl={props.ctaurl || ""}
                                featuredLabel={props.featuredlabel || ""}
                            />
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Public subscription plans / pricing page: browse plans, start a trial,
 * subscribe (free or via checkout), or redeem a subscription key.
 *
 * @module     local_moderncommerce/subscription_plans
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useMemo, useState} from "react";
import type {ReactNode} from "react";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type Feature = {
    name: string;
    icon: string;
};

type Plan = {
    id: number;
    name: string;
    code: string;
    description: string;
    billingcycle: string;
    price: number;
    displayprice: string;
    regularprice: number;
    displayregularprice: string;
    haspromo: boolean;
    isfree: boolean;
    trialdays: number;
    // Oriskill addition: trial amount (0 = free trial) and number of payments (0 = until cancelled).
    trialprice: number;
    displaytrialprice: string;
    payments: number;
    maxseats: number;
    featured: boolean;
    sortorder: number;
    features: Feature[];
};

type Current = {
    hassubscription: boolean;
    planid: number;
    planname: string;
    billingcycle: string;
    status: string;
    startdate: number;
    enddate: number;
};

type PlansResponse = {
    plans: Plan[];
    currentsubscription: Current;
    currency: {code: string; symbol: string; position: string; decimals: number};
};

type SubscribeResponse = {
    activated: boolean;
    checkouturl: string;
    message: string;
    subscriptionid: number;
};

type RedeemResponse = {
    activated: boolean;
    planname: string;
    message: string;
};

type Methods = {
    getPlans: string;
    subscribe: string;
    redeemKey: string;
};

type SubscriptionPlansProps = {
    methods: Methods;
    manageUrl: string;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php?sesskey=${encodeURIComponent(M.cfg.sesskey)}&info=${encodeURIComponent(methodName)}`;
    const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify([
            {
                index: 0,
                methodname: methodName,
                args,
            },
        ]),
    });

    if (!response.ok) {
        throw new Error(`${response.status} ${response.statusText}`);
    }

    const payload = await response.json();
    const first = Array.isArray(payload) ? payload[0] : payload;

    if (!first) {
        throw new Error("Empty Moodle service response.");
    }

    if (first.error) {
        const exception = first.exception ?? {};
        throw new Error(exception.message ?? first.message ?? "Moodle service request failed.");
    }

    return (first.data ?? first) as T;
};

const formatDate = (value: number): string => value > 0 ? new Date(value * 1000).toLocaleDateString(document.documentElement.lang || undefined) : "";

const errorText = (caught: unknown): string => caught instanceof Error ? caught.message : String(caught);

type Tier = {
    name: string;
    sortorder: number;
    featured: boolean;
    byCycle: Record<string, Plan>;
};

export default function SubscriptionPlans({methods, manageUrl, labels, layout}: SubscriptionPlansProps) {
    useModernCommerceClassSync();

    const renderLayout = (children: ReactNode): ReactNode => (
        <ModernLearnerLayout
            activeNav="subscriptions"
            title={labels.chooseplan}
            subtitle={labels.chooseplan_desc}
            labels={labels}
            layout={layout}
        >
            {children}
        </ModernLearnerLayout>
    );

    const [data, setData] = useState<PlansResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [notice, setNotice] = useState("");
    const [cycle, setCycle] = useState("monthly");
    const [busyPlanId, setBusyPlanId] = useState(0);
    const [reloadToken, setReloadToken] = useState(0);

    const [keyInput, setKeyInput] = useState("");
    const [redeeming, setRedeeming] = useState(false);
    const [keyError, setKeyError] = useState("");

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");
        void callMoodleService<PlansResponse>(methods.getPlans, {})
            .then((result) => {
                if (cancelled) {
                    return;
                }
                setData(result);
                const cycles = Array.from(new Set(result.plans.map((plan) => plan.billingcycle)));
                if (cycles.length > 0 && !cycles.includes(cycle)) {
                    setCycle(cycles.includes("monthly") ? "monthly" : cycles[0]);
                }
            })
            .catch((caught: unknown) => {
                if (!cancelled) {
                    setError(errorText(caught));
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });
        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [methods.getPlans, reloadToken]);

    const tiers = useMemo<Tier[]>(() => {
        const map = new Map<string, Tier>();
        (data?.plans ?? []).forEach((plan) => {
            const tier = map.get(plan.name) ?? {name: plan.name, sortorder: plan.sortorder, featured: false, byCycle: {}};
            tier.byCycle[plan.billingcycle] = plan;
            tier.sortorder = Math.min(tier.sortorder, plan.sortorder);
            tier.featured = tier.featured || plan.featured;
            map.set(plan.name, tier);
        });
        return Array.from(map.values()).sort((a, b) => a.sortorder - b.sortorder);
    }, [data]);

    const availableCycles = useMemo<string[]>(
        () => Array.from(new Set((data?.plans ?? []).map((plan) => plan.billingcycle))),
        [data]
    );

    const current = data?.currentsubscription;

    const planForTier = (tier: Tier): Plan | null => tier.byCycle[cycle]
        ?? tier.byCycle[cycle === "monthly" ? "yearly" : "monthly"]
        ?? null;

    const runSubscribe = async(plan: Plan, starttrial: boolean) => {
        setBusyPlanId(plan.id);
        setError("");
        setNotice("");
        try {
            const result = await callMoodleService<SubscribeResponse>(methods.subscribe, {planid: plan.id, starttrial});
            if (!result.activated && result.checkouturl) {
                window.location.assign(result.checkouturl);
                return;
            }
            setNotice(result.message);
            setReloadToken((value) => value + 1);
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setBusyPlanId(0);
        }
    };

    const redeem = async() => {
        // Was a silent return: pressing redeem with an empty box did nothing
        // and explained nothing, even though keyError was already available.
        if (keyInput.trim() === "") {
            setKeyError(labels["error:required"] ?? "");
            return;
        }
        setRedeeming(true);
        setKeyError("");
        setNotice("");
        try {
            const result = await callMoodleService<RedeemResponse>(methods.redeemKey, {keycode: keyInput.trim()});
            setNotice(result.message);
            setKeyInput("");
            setReloadToken((value) => value + 1);
        } catch (caught) {
            setKeyError(errorText(caught));
        } finally {
            setRedeeming(false);
        }
    };

    if (loading && !data) {
        return renderLayout(<div className={mcClasses("mc-loading mc-loading--centered")}>{labels.loading}</div>);
    }

    if (error && !data) {
        return renderLayout(
            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                <div className={mcClasses("mc-alert__body")}>{error}</div>
            </div>
        );
    }

    if (!data) {
        return null;
    }

    const cycleLabel = (value: string): string => value === "yearly" ? labels.yearly_short : labels.monthly_short;
    const cyclePer = (value: string): string => value === "yearly" ? labels.peryear : labels.permonth;

    return renderLayout(
        <section className={mcClasses("mc-subscribe")} aria-label={labels.chooseplan}>
            {current?.hassubscription && (
                <div className={mcClasses("mc-alert mc-alert--info mc-subscribe__current")} role="status">
                    <i className="bi bi-info-circle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body d-flex justify-content-between align-items-center flex-wrap gap-2")}>
                        <span>
                            {labels.currentplanlabel}: <strong>{current.planname}</strong>
                            {current.enddate > 0 && <span className="mc-cell-muted"> · {formatDate(current.enddate)}</span>}
                        </span>
                        <a className={mcClasses("mc-button btn-mc-secondary")} href={manageUrl}>{labels.manageplanbtn}</a>
                    </div>
                </div>
            )}

            {notice && (
                <div className={mcClasses("mc-alert mc-alert--success")} role="status">
                    <i className="bi bi-check-circle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{notice}</div>
                </div>
            )}
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            {availableCycles.length > 1 && (
                <div className={mcClasses("mc-subscribe__toggle")} role="tablist" aria-label={labels.choosebillingcycle}>
                    {availableCycles.map((value) => (
                        <button
                            aria-selected={cycle === value ? "true" : "false"}
                            className={mcClasses(cycle === value ? "mc-button btn-mc-primary" : "mc-button mc-btn-soft")}
                            key={value}
                            onClick={() => setCycle(value)}
                            role="tab"
                            type="button"
                        >
                            {cycleLabel(value)}
                        </button>
                    ))}
                </div>
            )}

            {tiers.length === 0 ? (
                <div className={mcClasses("mc-empty mc-empty--centered")}>
                    <span className={mcClasses("mc-empty__icon")}><i className="bi bi-collection" aria-hidden="true" /></span>
                    <p className={mcClasses("mc-empty__title")}>{labels.noplansavailable}</p>
                </div>
            ) : (
                <div className={mcClasses("mc-subscribe__grid")}>
                    {tiers.map((tier) => {
                        const plan = planForTier(tier);
                        if (!plan) {
                            return null;
                        }
                        const isCurrent = Boolean(current?.hassubscription && current.planid === plan.id);
                        const canTrial = plan.trialdays > 0 && !current?.hassubscription;
                        const busy = busyPlanId === plan.id;

                        return (
                            <article
                                className={mcClasses("mc-plan-card", tier.featured ? "mc-plan-card--featured" : "", isCurrent ? "mc-plan-card--current" : "")}
                                key={tier.name}
                            >
                                {tier.featured && <div className={mcClasses("mc-plan-card__ribbon")}>{labels.mostpopular}</div>}
                                <div className={mcClasses("mc-plan-card__name")}>{tier.name}</div>
                                {plan.description !== "" && <p className={mcClasses("mc-plan-card__desc")}>{plan.description}</p>}

                                <div className={mcClasses("mc-plan-card__price")}>
                                    {plan.isfree ? (
                                        <span className={mcClasses("mc-plan-card__amount")}>{labels.freeforever}</span>
                                    ) : (
                                        <>
                                            <span className={mcClasses("mc-plan-card__amount")}>{plan.displayprice}</span>
                                            <span className={mcClasses("mc-plan-card__per")}>{cyclePer(plan.billingcycle)}</span>
                                        </>
                                    )}
                                    {plan.haspromo && (
                                        <div className={mcClasses("mc-plan-card__regular")}>{plan.displayregularprice}</div>
                                    )}
                                    {!plan.isfree && plan.payments > 0 && (
                                        <div className={mcClasses("mc-cell-muted small mt-1")}>
                                            {(plan.billingcycle === "yearly" ? labels.payments_yearly : labels.payments_monthly)
                                                .replace("{$a}", String(plan.payments))}
                                        </div>
                                    )}
                                </div>

                                {canTrial && (
                                    <div className={mcClasses("mc-badge mc-badge--success mc-plan-card__trial")}>
                                        {plan.trialprice > 0
                                            ? labels.paidtrialbadge.replace("{$a->days}", String(plan.trialdays))
                                                .replace("{$a->price}", plan.displaytrialprice)
                                            : labels.daytrialbadge.replace("{$a}", String(plan.trialdays))}
                                    </div>
                                )}

                                {isCurrent ? (
                                    <button className={mcClasses("mc-button btn-mc-secondary w-100 mc-plan-card__cta")} disabled type="button">
                                        {labels.currentplanlabel}
                                    </button>
                                ) : canTrial ? (
                                    <McButton
                                        className={mcClasses("btn-mc-primary w-100 mc-plan-card__cta")}
                                        loading={busy}
                                        loadingLabel={labels.loading || "Starting..."}
                                        onClick={() => runSubscribe(plan, true)}
                                        type="button"
                                    >
                                        {plan.trialprice > 0 ? labels.starttrial_paid : labels.startfreetrial}
                                    </McButton>
                                ) : (
                                    <McButton
                                        className={mcClasses("btn-mc-primary w-100 mc-plan-card__cta")}
                                        loading={busy}
                                        loadingLabel={labels.loading || "Processing..."}
                                        onClick={() => runSubscribe(plan, false)}
                                        type="button"
                                    >
                                        {labels.subscribenow}
                                    </McButton>
                                )}

                                {plan.features.length > 0 && (
                                    <>
                                        <div className={mcClasses("mc-plan-card__included")}>{labels.planincludes}</div>
                                        <ul className={mcClasses("mc-plan-card__features list-unstyled")}>
                                            {plan.features.map((feature, index) => (
                                                <li className={mcClasses("mc-plan-card__feature")} key={index}>
                                                    <i className={`bi bi-${feature.icon || "check-circle"} mc-plan-card__feature-icon`} aria-hidden="true" />
                                                    <span>{feature.name}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </>
                                )}
                            </article>
                        );
                    })}
                </div>
            )}

            <div className={mcClasses("mc-subscribe__redeem")}>
                <div>
                    <h3 className={mcClasses("mc-card-title mb-1")}>{labels.haveakey}</h3>
                    <p className={mcClasses("mc-cell-muted small mb-0")}>{labels.haveakey_desc}</p>
                </div>
                <div className={mcClasses("mc-subscribe__redeem-form")}>
                    <input
                        aria-label={labels.enterkey}
                        className={mcClasses("mc-form-control mc-cell-mono")}
                        onChange={(event) => setKeyInput(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === "Enter") {
                                void redeem();
                            }
                        }}
                        placeholder={labels.enterkey}
                        type="text"
                        value={keyInput}
                    />
                    <McButton
                        className={mcClasses("btn-mc-primary")}
                        disabled={keyInput.trim() === ""}
                        loading={redeeming}
                        loadingLabel={labels.loading || "Redeeming..."}
                        onClick={redeem}
                        type="button"
                    >
                        {labels.redeem}
                    </McButton>
                </div>
                {keyError && (
                    <div className={mcClasses("mc-alert mc-alert--danger mt-2")} role="alert">
                        <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{keyError}</div>
                    </div>
                )}
            </div>
        </section>
    );
}

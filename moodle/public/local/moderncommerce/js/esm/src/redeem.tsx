// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React buyer enrollment key redemption for Modern Commerce.
 *
 * @module     local_moderncommerce/redeem
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useState} from "react";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McInput} from "./form";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";
import {pattern, required, runRules} from "./validation";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type Course = {
    id: number;
    fullname: string;
    alreadyenrolled: boolean;
    url: string;
};

type KeyType = "enrollment" | "subscription" | "unknown";

type ResolveResponse = {
    type: KeyType;
    valid: boolean;
    message: string;
    courses: Course[];
    planname: string;
};

type RedeemResponse = {
    success: boolean;
    type: KeyType;
    message: string;
    courses: Course[];
    planname: string;
};

type RedeemResult = {
    type: KeyType;
    courses: Course[];
    planname: string;
    message: string;
};

type RedeemProps = {
    resolveMethodName: string;
    redeemMethodName: string;
    orderId: number;
    catalogUrl: string;
    coursesUrl: string;
    subscriptionUrl: string;
    labels: Labels;
    layout?: LearnerLayoutContext;
    activeNav?: "redeem";
};

const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const query = new URLSearchParams({
        sesskey: M.cfg.sesskey,
        info: methodName,
    });
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php?${query.toString()}`;
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

export default function Redeem({
    resolveMethodName,
    redeemMethodName,
    orderId,
    catalogUrl,
    coursesUrl,
    subscriptionUrl,
    labels,
    layout,
    activeNav = "redeem",
}: RedeemProps) {
    useModernCommerceClassSync();
    const [keycode, setKeycode] = useState("");
    const [busy, setBusy] = useState<"idle" | "validating" | "redeeming">("idle");
    const [error, setError] = useState("");
    const [keyError, setKeyError] = useState("");
    const [preview, setPreview] = useState<ResolveResponse | null>(null);
    const [result, setResult] = useState<RedeemResult | null>(null);

    const normalised = keycode.trim().toUpperCase();

    // Previously both actions returned silently on an empty or malformed key,
    // leaving the user with no idea why nothing happened.
    const checkKey = (): boolean => {
        const found = runRules({keycode: normalised}, {
            keycode: [
                required(labels["error:required"] ?? labels.enterkeycode),
                pattern(/^[A-Z0-9-]{4,64}$/, labels["error:invalidkeyformat"] ?? labels.invalidkey
                    ?? labels["error:required"]),
            ],
        });
        setKeyError(found.keycode ?? "");
        return !found.keycode;
    };

    const keyTypeLabel = (type: KeyType): string => (
        type === "subscription"
            ? labels.redeemkeytype_subscription || labels.subscriptionkey || "Subscription key"
            : labels.redeemkeytype_enrollment || labels.enrollmentkey || "Course access key"
    );

    const validate = async() => {
        if (!checkKey()) {
            return;
        }
        setBusy("validating");
        setError("");
        setPreview(null);

        try {
            const response = await callMoodleService<ResolveResponse>(resolveMethodName, {keycode: normalised});
            if (!response.valid) {
                setError(response.message);
                return;
            }
            setPreview(response);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setBusy("idle");
        }
    };

    const redeem = async() => {
        if (!checkKey()) {
            return;
        }
        setBusy("redeeming");
        setError("");

        try {
            const response = await callMoodleService<RedeemResponse>(redeemMethodName, {
                keycode: normalised,
                orderid: orderId,
            });
            if (!response.success) {
                setError(response.message);
                return;
            }
            setResult({
                type: response.type,
                courses: response.courses,
                planname: response.planname,
                message: response.message,
            });
            setPreview(null);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setBusy("idle");
        }
    };

    const reset = () => {
        setKeycode("");
        setError("");
        setKeyError("");
        setPreview(null);
        setResult(null);
    };

    if (result) {
        return (
            <ModernLearnerLayout
                activeNav={activeNav}
                title={labels.title}
                subtitle={labels.intro}
                labels={labels}
                layout={layout}
            >
                <section className={mcClasses("mc-redeem mc-redeem--success")} aria-label={labels.redeemsuccess}>
                    <div className={mcClasses("mc-card")}>
                        <div className={mcClasses("mc-card-body text-center")}>
                            <span className={mcClasses("mc-redeem__icon text-success")}>
                                <i className="bi bi-check-circle-fill" aria-hidden="true" />
                            </span>
                            <h2 className={mcClasses("mc-card-title mt-2")}>{labels.redeemsuccess}</h2>
                            {result.type === "subscription" ? (
                                <div className={mcClasses("mc-redeem__plan mt-3")}>
                                    <p className="fw-semibold mb-1">{result.planname}</p>
                                    <p className={mcClasses("mc-cell-muted mb-0")}>
                                        {result.message || labels.redeemplanactivated}
                                    </p>
                                </div>
                            ) : (
                                <div className={mcClasses("mc-redeem__courses mt-3")}>
                                    {result.courses.map((course) => (
                                        <div className={mcClasses("mc-redeem__course")} key={course.id}>
                                            <span className="fw-semibold">{course.fullname}</span>
                                            <a className={mcClasses("mc-button btn-mc-primary")} href={course.url}>{labels.accesscourse}</a>
                                        </div>
                                    ))}
                                </div>
                            )}
                            <div className="d-flex flex-wrap justify-content-center gap-2 mt-4">
                                <button className={mcClasses("mc-button btn-mc-secondary")} onClick={reset} type="button">
                                    {labels.redeemanother}
                                </button>
                                {result.type === "subscription" ? (
                                    <a className={mcClasses("mc-button btn-mc-secondary")} href={subscriptionUrl}>
                                        {labels.redeemviewsubscription || labels.mysubscriptionplan}
                                    </a>
                                ) : (
                                    <a className={mcClasses("mc-button btn-mc-secondary")} href={coursesUrl}>{labels.mycourses}</a>
                                )}
                                <a className={mcClasses("mc-button mc-btn-ghost")} href={catalogUrl}>{labels.browsecatalog}</a>
                            </div>
                        </div>
                    </div>
                </section>
            </ModernLearnerLayout>
        );
    }

    return (
        <ModernLearnerLayout
            activeNav={activeNav}
            title={labels.title}
            subtitle={labels.intro}
            labels={labels}
            layout={layout}
            actions={(
                <a className={mcClasses("mc-button btn-mc-secondary")} href={catalogUrl}>
                    <i className="bi bi-grid" aria-hidden="true" />
                    {labels.browsecatalog}
                </a>
            )}
        >
            <section className={mcClasses("mc-redeem")} aria-label={labels.title}>
                <div className={mcClasses("mc-card")}>
                    <div className={mcClasses("mc-card-body")}>
                        <h2 className={mcClasses("mc-card-title")}>{labels.title}</h2>
                        <p className={mcClasses("mc-cell-muted")}>{labels.intro}</p>

                        {error && (
                            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                                <div className={mcClasses("mc-alert__body")}>{error}</div>
                            </div>
                        )}

                        <div className={mcClasses("mc-redeem__form")}>
                            <McInput
                                autoComplete="off"
                                error={keyError}
                                label={labels.keycode}
                                maxLength={64}
                                name="keycode"
                                onKeyDown={(event) => {
                                    if (event.key === "Enter") {
                                        void redeem();
                                    }
                                }}
                                onValueChange={(value) => {
                                    setKeycode(value);
                                    setPreview(null);
                                    setError("");
                                    setKeyError("");
                                }}
                                placeholder={labels.enterkeycode}
                                required
                                type="text"
                                value={keycode}
                            />
                        </div>

                        {preview && (
                            <div className={mcClasses("mc-redeem__preview mt-3")}>
                                <div className="d-flex align-items-center gap-2">
                                    <span className={mcClasses("mc-filter-label mb-0")}>{labels.grantsaccess}</span>
                                    <span className={mcClasses("mc-badge mc-badge--info")}>
                                        {keyTypeLabel(preview.type)}
                                    </span>
                                </div>
                                {preview.type === "subscription" ? (
                                    <p className={mcClasses("mc-cell-muted mb-0 mt-2")}>{preview.planname}</p>
                                ) : (
                                    <ul className={mcClasses("mc-redeem__preview-list")}>
                                        {preview.courses.map((course) => (
                                            <li key={course.id}>
                                                <span>{course.fullname}</span>
                                                {course.alreadyenrolled && (
                                                    <span className={mcClasses("mc-badge mc-badge--neutral ms-2")}>
                                                        {labels.alreadyenrolled}
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}

                        <div className="d-flex flex-wrap gap-2 mt-3">
                            <McButton
                                className={mcClasses("btn-mc-primary")}
                                disabled={busy !== "idle" || normalised === ""}
                                loading={busy === "redeeming"}
                                loadingLabel={labels.loading || "Redeeming..."}
                                onClick={redeem}
                                type="button"
                            >
                                {labels.redeemkey}
                            </McButton>
                            <button
                                className={mcClasses("mc-button btn-mc-secondary")}
                                disabled={busy !== "idle" || normalised === ""}
                                onClick={validate}
                                type="button"
                            >
                                {busy === "validating" ? labels.loading : labels.validatekey}
                            </button>
                        </div>

                        <p className={mcClasses("mc-cell-muted small mt-3 mb-0")}>{labels.help}</p>
                    </div>
                </div>
            </section>
        </ModernLearnerLayout>
    );
}

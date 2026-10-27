// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin screen for a subscription plan's features and access rules
 * (the courses, categories, and bundles it grants).
 *
 * @module     local_moderncommerce/subscription_plan_access
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef, useState} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {confirmDialog} from "./modal";
import {FieldError} from "./form";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type AccessType = "course" | "category" | "bundle";

type Plan = {
    id: number;
    name: string;
    code: string;
    billingcycle: string;
    displayprice: string;
    status: string;
};

type Rule = {
    id: number;
    accesstype: AccessType;
    targetid: number;
    targetname: string;
    coursecount: number;
};

type Feature = {
    id: number;
    name: string;
    icon: string;
    enabled: boolean;
};

type Option = {
    id: number;
    name: string;
    coursecount: number;
};

type AccessData = {
    plan: Plan;
    rules: Rule[];
    totalcourses: number;
    features: Feature[];
    categories: Option[];
    bundles: Option[];
};

type AccessMutation = {
    success: boolean;
    message: string;
    rules: Rule[];
    totalcourses: number;
};

type FeatureMutation = {
    success: boolean;
    message: string;
    features: Feature[];
};

type Methods = {
    get: string;
    add: string;
    remove: string;
    saveFeatures: string;
};

type PlanAccessProps = {
    planid: number;
    methods: Methods;
    searchCoursesMethodName: string;
    featureMatrixUrl: string;
    labels: Labels;
};

type PickerItem = {
    id: number;
    label: string;
    sub?: string;
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

const formatCount = (value: number): string => new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);

const accessTypeClass = (type: AccessType): string => {
    switch (type) {
        case "course":
            return "info";
        case "category":
            return "primary";
        case "bundle":
            return "success";
        default:
            return "neutral";
    }
};

const errorText = (caught: unknown): string => caught instanceof Error ? caught.message : String(caught);

/**
 * A searchable typeahead. The `search` prop may resolve synchronously (local
 * filtering) or asynchronously (server search); it is held in a ref so changing
 * it between renders does not retrigger the debounce effect.
 */
function SearchablePicker({
    placeholder,
    search,
    onSelect,
    noResults,
    error,
    id = "mc-plan-access-target",
}: {
    placeholder: string;
    search: (query: string) => Promise<PickerItem[]>;
    onSelect: (id: number) => void;
    noResults: string;
    error?: string;
    id?: string;
}) {
    const [query, setQuery] = useState("");
    const [options, setOptions] = useState<PickerItem[]>([]);
    const [chosen, setChosen] = useState<PickerItem | null>(null);
    const [focused, setFocused] = useState(false);
    const searchRef = useRef(search);
    searchRef.current = search;

    useEffect(() => {
        if (chosen) {
            return;
        }
        let cancelled = false;
        const timer = window.setTimeout(() => {
            void Promise.resolve(searchRef.current(query.trim())).then((items) => {
                if (!cancelled) {
                    setOptions(items);
                }
            });
        }, 200);
        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [query, chosen]);

    const showResults = focused && !chosen;

    return (
        <div className={mcClasses("mc-course-picker")}>
            <input
                aria-describedby={error ? `${id}-error` : undefined}
                aria-invalid={error ? true : undefined}
                aria-label={placeholder}
                aria-required="true"
                autoComplete="off"
                className={mcClasses("mc-form-control", error ? "mc-field-invalid" : "")}
                id={id}
                name="targetid"
                onBlur={() => window.setTimeout(() => setFocused(false), 150)}
                onChange={(event) => {
                    setChosen(null);
                    onSelect(0);
                    setQuery(event.target.value);
                }}
                onFocus={() => setFocused(true)}
                placeholder={placeholder}
                type="search"
                value={chosen ? chosen.label : query}
            />
            <FieldError id={`${id}-error`} message={error} />
            {showResults && (
                <div className={mcClasses("mc-course-picker__results")} role="listbox">
                    {options.length === 0 && (
                        <div className={mcClasses("mc-course-picker__empty mc-cell-muted small")}>{noResults}</div>
                    )}
                    {options.map((option) => (
                        <button
                            className={mcClasses("mc-button mc-course-picker__option")}
                            data-mc-button="ghost"
                            key={option.id}
                            onMouseDown={(event) => {
                                event.preventDefault();
                                setChosen(option);
                                onSelect(option.id);
                                setFocused(false);
                            }}
                            role="option"
                            type="button"
                        >
                            <span className={mcClasses("mc-course-picker__option-main")}><strong>{option.label}</strong></span>
                            {option.sub && <span className={mcClasses("mc-course-picker__option-sub")}>{option.sub}</span>}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function SubscriptionPlanAccess({
    planid,
    methods,
    searchCoursesMethodName,
    featureMatrixUrl,
    labels,
}: PlanAccessProps) {
    useModernCommerceClassSync();

    const [data, setData] = useState<AccessData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [busyKey, setBusyKey] = useState("");

    const [featureState, setFeatureState] = useState<Record<number, boolean>>({});
    const [featuresDirty, setFeaturesDirty] = useState(false);
    const [savingFeatures, setSavingFeatures] = useState(false);

    const [addType, setAddType] = useState<AccessType>("course");
    const [targetId, setTargetId] = useState(0);
    const [targetError, setTargetError] = useState("");
    const [adding, setAdding] = useState(false);
    const [pickerNonce, setPickerNonce] = useState(0);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");
        void callMoodleService<AccessData>(methods.get, {planid})
            .then((result) => {
                if (cancelled) {
                    return;
                }
                setData(result);
                const next: Record<number, boolean> = {};
                result.features.forEach((feature) => {
                    next[feature.id] = feature.enabled;
                });
                setFeatureState(next);
                setFeaturesDirty(false);
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
    }, [methods.get, planid]);

    // Reset the target when the access type changes.
    useEffect(() => {
        setTargetId(0);
    }, [addType]);

    const searchFor = (type: AccessType) => async(query: string): Promise<PickerItem[]> => {
        if (type === "course") {
            if (query.length < 2) {
                return [];
            }
            const result = await callMoodleService<{items: Array<{id: number; fullname: string}>}>(
                searchCoursesMethodName,
                {query, limit: 20}
            );
            return result.items.map((item) => ({id: item.id, label: item.fullname}));
        }
        const list = type === "category" ? (data?.categories ?? []) : (data?.bundles ?? []);
        const needle = query.toLowerCase();
        return list
            .filter((option) => option.name.toLowerCase().includes(needle))
            .map((option) => ({
                id: option.id,
                label: option.name,
                sub: `${formatCount(option.coursecount)} ${labels.coursesgranted.toLowerCase()}`,
            }));
    };

    const applyAccess = (result: AccessMutation) => {
        setData((current) => current ? {...current, rules: result.rules, totalcourses: result.totalcourses} : current);
    };

    const addRule = async() => {
        // The picker is the only field, so the message lands on it rather than
        // in the panel banner where it used to sit.
        if (targetId <= 0) {
            setTargetError(labels.selecttargetfirst ?? labels["error:required"] ?? "");
            return;
        }
        setTargetError("");
        setAdding(true);
        setError("");
        try {
            const result = await callMoodleService<AccessMutation>(methods.add, {planid, accesstype: addType, targetid: targetId});
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            applyAccess(result);
            setTargetId(0);
            setPickerNonce((value) => value + 1); // Remount the picker to clear its selection.
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setAdding(false);
        }
    };

    const removeRule = async(rule: Rule) => {
        if (!await confirmDialog({message: labels.confirmremove, danger: true})) {
            return;
        }
        const key = `${rule.accesstype}-${rule.targetid}`;
        setBusyKey(key);
        setError("");
        try {
            const result = await callMoodleService<AccessMutation>(methods.remove, {
                planid,
                accesstype: rule.accesstype,
                targetid: rule.targetid,
            });
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            applyAccess(result);
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setBusyKey("");
        }
    };

    const toggleFeature = (id: number) => {
        setFeatureState((current) => ({...current, [id]: !current[id]}));
        setFeaturesDirty(true);
    };

    const saveFeatures = async() => {
        if (!data) {
            return;
        }
        setSavingFeatures(true);
        setError("");
        const featureids = data.features.filter((feature) => featureState[feature.id]).map((feature) => feature.id);
        try {
            const result = await callMoodleService<FeatureMutation>(methods.saveFeatures, {planid, featureids});
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            setData((current) => current ? {...current, features: result.features} : current);
            const next: Record<number, boolean> = {};
            result.features.forEach((feature) => {
                next[feature.id] = feature.enabled;
            });
            setFeatureState(next);
            setFeaturesDirty(false);
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setSavingFeatures(false);
        }
    };

    if (loading && !data) {
        return <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>;
    }

    if (error && !data) {
        return (
            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                <div className={mcClasses("mc-alert__body")}>{error}</div>
            </div>
        );
    }

    if (!data) {
        return null;
    }

    const typeLabel = (type: AccessType): string =>
        type === "course" ? labels.accesstype_course : type === "category" ? labels.accesstype_category : labels.accesstype_bundle;

    const pickerPlaceholder = addType === "course"
        ? labels.searchcoursesplaceholder
        : addType === "category" ? labels.searchcategories : labels.searchbundles;

    const enabledFeatures = data.features.filter((feature) => featureState[feature.id]).length;
    const totalFeatures = data.features.length;

    return (
        <section className={mcClasses("mc-product-admin mc-plan-access")} aria-label={labels.title}>
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            {/* Plan header + summary tiles. */}
            <div className={mcClasses("mc-card mb-4")}>
                <div className={mcClasses("mc-card-body d-flex justify-content-between align-items-center gap-3 flex-wrap")}>
                    <div>
                        <div className="d-flex align-items-center gap-2 flex-wrap">
                            <h2 className="mb-0">{data.plan.name}</h2>
                            {data.plan.code !== "" && <span className={mcClasses("mc-badge mc-badge--neutral mc-cell-mono")}>{data.plan.code}</span>}
                            <span className={mcClasses(`mc-badge mc-badge--${data.plan.status === "active" ? "success" : "neutral"}`)}>{data.plan.status}</span>
                        </div>
                        <div className={mcClasses("mc-cell-muted mt-1")}>
                            {data.plan.billingcycle === "yearly" ? labels.billingcycle_yearly : labels.billingcycle_monthly} · {data.plan.displayprice}
                        </div>
                    </div>
                    <div className="d-flex gap-3 flex-wrap">
                        <div className={mcClasses("mc-metric-tile mc-metric-tile--neutral mc-plan-access__tile")}>
                            <div className={mcClasses("mc-metric-tile__body")}>
                                <div className={mcClasses("mc-metric-tile__label")}>{labels.planfeatures}</div>
                                <div className={mcClasses("mc-metric-tile__value")}>{enabledFeatures} / {totalFeatures}</div>
                            </div>
                        </div>
                        <div className={mcClasses("mc-metric-tile mc-metric-tile--neutral mc-plan-access__tile")}>
                            <div className={mcClasses("mc-metric-tile__body")}>
                                <div className={mcClasses("mc-metric-tile__label")}>{labels.accessrules}</div>
                                <div className={mcClasses("mc-metric-tile__value")}>{formatCount(data.rules.length)}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="row g-4">
                {/* Plan features (left). */}
                <div className="col-12 col-lg-6">
                    <div className={mcClasses("mc-card h-100")}>
                        <div className={mcClasses("mc-card-header d-flex justify-content-between align-items-start gap-2")}>
                            <div>
                                <h3 className={mcClasses("mc-card-title mb-1")}>{labels.planfeatures}</h3>
                                <p className={mcClasses("mc-cell-muted small mb-0")}>{labels.planfeatures_desc}</p>
                            </div>
                            <a className={mcClasses("mc-button mc-btn-soft text-nowrap")} href={featureMatrixUrl}>{labels.featurematrix}</a>
                        </div>
                        <div className={mcClasses("mc-card-body")}>
                            {data.features.length === 0 && <p className={mcClasses("mc-cell-muted small")}>{labels.nofeaturesmatrix}</p>}
                            <div className={mcClasses("mc-plan-access__features")}>
                                {data.features.map((feature) => (
                                    <label className={mcClasses("mc-checkbox mc-plan-access__feature")} key={feature.id}>
                                        <input
                                            checked={Boolean(featureState[feature.id])}
                                            onChange={() => toggleFeature(feature.id)}
                                            type="checkbox"
                                        />
                                        <i className={`bi bi-${feature.icon} mc-plan-access__feature-icon`} aria-hidden="true" />
                                        <span>{feature.name}</span>
                                    </label>
                                ))}
                            </div>
                            {data.features.length > 0 && (
                                <McButton
                                    className={mcClasses("btn-mc-primary w-100 mt-3")}
                                    disabled={!featuresDirty}
                                    loading={savingFeatures}
                                    loadingLabel={labels.saving || "Saving..."}
                                    onClick={saveFeatures}
                                    type="button"
                                >
                                    {labels.savechanges}
                                </McButton>
                            )}
                        </div>
                    </div>
                </div>

                {/* Access rules (right). */}
                <div className="col-12 col-lg-6">
                    <div className={mcClasses("mc-card h-100")}>
                        <div className={mcClasses("mc-card-header")}>
                            <h3 className={mcClasses("mc-card-title mb-1")}>{labels.accessrules}</h3>
                            <p className={mcClasses("mc-cell-muted small mb-0")}>{labels.accessrules_desc}</p>
                        </div>
                        <div className={mcClasses("mc-card-body")}>
                            {data.rules.length === 0 ? (
                                <div className={mcClasses("mc-empty mc-empty--centered")}>
                                    <span className={mcClasses("mc-empty__icon")}><i className="bi bi-shield-lock" aria-hidden="true" /></span>
                                    <p className={mcClasses("mc-empty__title")}>{labels.noaccessrules}</p>
                                    <p className={mcClasses("mc-empty__text")}>{labels.noaccessrulesdesc}</p>
                                </div>
                            ) : (
                                <ul className={mcClasses("mc-plan-access__rules list-unstyled mb-0")}>
                                    {data.rules.map((rule) => (
                                        <li className={mcClasses("mc-plan-access__rule")} key={`${rule.accesstype}-${rule.targetid}`}>
                                            <div className={mcClasses("mc-plan-access__rule-main")}>
                                                <span className={mcClasses(`mc-badge mc-badge--${accessTypeClass(rule.accesstype)}`)}>{typeLabel(rule.accesstype)}</span>
                                                <span className={mcClasses("mc-plan-access__rule-name")}>{rule.targetname}</span>
                                                <span className={mcClasses("mc-cell-muted small")}>{formatCount(rule.coursecount)} {labels.coursesgranted.toLowerCase()}</span>
                                            </div>
                                            <button
                                                aria-label={labels.remove}
                                                className={mcClasses("mc-button mc-btn-icon mc-btn-icon--danger")}
                                                disabled={busyKey === `${rule.accesstype}-${rule.targetid}`}
                                                onClick={() => removeRule(rule)}
                                                title={labels.remove}
                                                type="button"
                                            >
                                                <i className="bi bi-x-lg" aria-hidden="true" />
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <div className={mcClasses("mc-plan-access__add mt-4")}>
                                <label className={mcClasses("mc-field-label")} htmlFor="mc-access-type">{labels.accesstype}</label>
                                <select
                                    className={mcClasses("mc-select mb-3")}
                                    id="mc-access-type"
                                    onChange={(event) => setAddType(event.target.value as AccessType)}
                                    value={addType}
                                >
                                    <option value="course">{labels.accesstype_course}</option>
                                    <option value="category">{labels.accesstype_category}</option>
                                    <option value="bundle">{labels.accesstype_bundle}</option>
                                </select>

                                <label className={mcClasses("mc-field-label")}>
                                    {addType === "course" ? labels.selectcourse : addType === "category" ? labels.selectcategory : labels.selectbundle}
                                </label>
                                <div className="mb-3">
                                    <SearchablePicker
                                        error={targetError}
                                        key={`${addType}-${pickerNonce}`}
                                        noResults={labels.noresults}
                                        onSelect={(id) => {
                                            setTargetId(id);
                                            setTargetError("");
                                        }}
                                        placeholder={pickerPlaceholder}
                                        search={searchFor(addType)}
                                    />
                                </div>

                                <button
                                    className={mcClasses("mc-button btn-mc-primary w-100")}
                                    disabled={adding || targetId <= 0}
                                    onClick={addRule}
                                    type="button"
                                >
                                    <i className="bi bi-plus-lg" aria-hidden="true" /> {labels.addaccessrule}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}

// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin course advanced/merchandising features editor for Modern Commerce.
 *
 * @module     local_moderncommerce/course_features_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {FieldError} from "./form";
import {focusFirstError, lteField, max, min, number, runRules} from "./validation";
import type {FieldErrorMap} from "./validation";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type Option = {
    value: string;
    label: string;
};

type OutlineRow = {
    title: string;
    time: string;
};

type Detail = {
    courseid: number;
    coursename: string;
    durationhours: number;
    durationminutes: number;
    skilllevel: string;
    language: string;
    passgrade: number;
    certenabled: boolean;
    overviewauto: boolean;
    overviewtext: string;
    featured: boolean;
    bestseller: boolean;
    trending: boolean;
    price: number;
    saleprice: number;
    quizcount: number;
    sectioncount: number;
    objectives: Array<{text: string}>;
    outline: OutlineRow[];
    levels: Option[];
    languages: Option[];
    currency: {code: string; symbol: string};
};

type Form = {
    skilllevel: string;
    language: string;
    durationhours: string;
    durationminutes: string;
    passgrade: string;
    certenabled: boolean;
    overviewauto: boolean;
    overviewtext: string;
    featured: boolean;
    bestseller: boolean;
    trending: boolean;
    price: string;
    saleprice: string;
    objectives: string[];
    outline: OutlineRow[];
};

type SaveResponse = {
    success: boolean;
    message: string;
};

type Props = {
    courseId: number;
    getMethodName: string;
    saveMethodName: string;
    labels: Labels;
};

const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php?sesskey=${encodeURIComponent(M.cfg.sesskey)}&info=${encodeURIComponent(methodName)}`;
    const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify([{index: 0, methodname: methodName, args}]),
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

const detailToForm = (detail: Detail): Form => ({
    skilllevel: detail.skilllevel,
    language: detail.language,
    durationhours: String(detail.durationhours),
    durationminutes: String(detail.durationminutes),
    passgrade: detail.passgrade > 0 ? String(detail.passgrade) : "",
    certenabled: detail.certenabled,
    overviewauto: detail.overviewauto,
    overviewtext: detail.overviewtext,
    featured: detail.featured,
    bestseller: detail.bestseller,
    trending: detail.trending,
    price: detail.price > 0 ? String(detail.price) : "",
    saleprice: detail.saleprice > 0 ? String(detail.saleprice) : "",
    objectives: detail.objectives.length > 0 ? detail.objectives.map((o) => o.text) : [""],
    outline: detail.outline.length > 0 ? detail.outline : [{title: "", time: ""}],
});

export default function CourseFeaturesAdmin({courseId, getMethodName, saveMethodName, labels}: Props) {
    useModernCommerceClassSync();
    const [detail, setDetail] = useState<Detail | null>(null);
    const [form, setForm] = useState<Form | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<FieldErrorMap>({});
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<Detail>(getMethodName, {courseid: courseId})
            .then((result) => {
                if (!cancelled) {
                    setDetail(result);
                    setForm(detailToForm(result));
                }
            })
            .catch((caught: unknown) => {
                if (!cancelled) {
                    setError(caught instanceof Error ? caught.message : String(caught));
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
    }, [getMethodName, courseId]);

    const update = (changes: Partial<Form>) => setForm((current) => current ? {...current, ...changes} : current);

    const updateObjective = (index: number, value: string) => {
        setForm((current) => {
            if (!current) {
                return current;
            }
            const objectives = current.objectives.map((o, i) => i === index ? value : o);
            return {...current, objectives};
        });
    };

    const updateOutline = (index: number, changes: Partial<OutlineRow>) => {
        setForm((current) => {
            if (!current) {
                return current;
            }
            const outline = current.outline.map((row, i) => i === index ? {...row, ...changes} : row);
            return {...current, outline};
        });
    };

    const submit = async() => {
        if (!form) {
            return;
        }

        const t = (key: string) => labels[key] ?? key;
        const found = runRules(form, {
            durationhours: [number(t("error:invalidnumber")), min(0, t("error:minvalue"))],
            durationminutes: [
                number(t("error:invalidnumber")), min(0, t("error:minvalue")), max(59, t("error:maxvalue")),
            ],
            passgrade: [
                number(t("error:invalidnumber")), min(0, t("error:minvalue")), max(100, t("error:maxvalue")),
            ],
            price: [number(t("error:invalidnumber")), min(0, t("error:minvalue"))],
            saleprice: [
                number(t("error:invalidnumber")), min(0, t("error:minvalue")),
                lteField("price", t("error:salepriceabovebase")),
            ],
        });
        setFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setError("");
            focusFirstError(found, ["durationhours", "durationminutes", "passgrade", "price", "saleprice"]);
            return;
        }

        setSaving(true);
        setError("");

        try {
            const result = await callMoodleService<SaveResponse>(saveMethodName, {
                courseid: courseId,
                durationhours: Number(form.durationhours) || 0,
                durationminutes: Number(form.durationminutes) || 0,
                skilllevel: form.skilllevel,
                language: form.language,
                passgrade: form.passgrade === "" ? -1 : Number(form.passgrade),
                certenabled: form.certenabled,
                overviewauto: form.overviewauto,
                overviewtext: form.overviewtext,
                featured: form.featured,
                bestseller: form.bestseller,
                trending: form.trending,
                price: form.price === "" ? -1 : Number(form.price),
                saleprice: Number(form.saleprice) || 0,
                objectives: form.objectives.map((o) => o.trim()).filter((o) => o !== ""),
                outline: form.outline.filter((row) => row.title.trim() !== "" || row.time.trim() !== ""),
            });

            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    if (loading && !form) {
        return <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>;
    }
    if (error && !form) {
        return (
            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                <div className={mcClasses("mc-alert__body")}>{error}</div>
            </div>
        );
    }
    if (!form || !detail) {
        return null;
    }

    return (
        <section className={mcClasses("mc-product-form")} aria-label={detail.coursename}>
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            <div className={mcClasses("mc-product-form__section")}>
                <h4>{labels.coursebasics}</h4>
                <div className={mcClasses("mc-product-form__grid")}>
                    <label>
                        <span>{labels.skilllevel}</span>
                        <select className={mcClasses("mc-select")} onChange={(e) => update({skilllevel: e.target.value})} value={form.skilllevel}>
                            <option value="">{labels.none}</option>
                            {detail.levels.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </select>
                    </label>
                    <label>
                        <span>{labels.language}</span>
                        <select className={mcClasses("mc-select")} onChange={(e) => update({language: e.target.value})} value={form.language}>
                            <option value="">{labels.none}</option>
                            {detail.languages.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </select>
                    </label>
                    <label>
                        <span>{labels.hours}</span>
                        <input className={mcClasses("mc-form-control")} min="0" onChange={(e) => update({durationhours: e.target.value})} type="number" value={form.durationhours} />
                    </label>
                    <label>
                        <span>{labels.minutes}</span>
                        <input className={mcClasses("mc-form-control")} min="0" max="59" onChange={(e) => update({durationminutes: e.target.value})} type="number" value={form.durationminutes} />
                    </label>
                    <label>
                        <span>{labels.passgrade}</span>
                        <input className={mcClasses("mc-form-control")} min="0" max="100" onChange={(e) => update({passgrade: e.target.value})} step="0.01" type="number" value={form.passgrade} />
                    </label>
                </div>
                <div className={mcClasses("mc-product-form__checks")}>
                    <label>
                        <input checked={form.certenabled} onChange={(e) => update({certenabled: e.target.checked})} type="checkbox" />
                        <span>{labels.enablecertificate}</span>
                    </label>
                </div>
            </div>

            <div className={mcClasses("mc-product-form__section")}>
                <h4>{labels.overview}</h4>
                <div className={mcClasses("mc-product-form__checks")}>
                    <label>
                        <input checked={form.overviewauto} onChange={(e) => update({overviewauto: e.target.checked})} type="checkbox" />
                        <span>{labels.autogenerate}</span>
                    </label>
                </div>
                {!form.overviewauto && (
                    <textarea className={mcClasses("mc-form-control")} onChange={(e) => update({overviewtext: e.target.value})} rows={4} value={form.overviewtext} />
                )}
            </div>

            <div className={mcClasses("mc-product-form__section")}>
                <div className="d-flex justify-content-between align-items-center">
                    <h4>{labels.learningobjectives}</h4>
                    <button className={mcClasses("mc-button mc-btn-soft")} onClick={() => update({objectives: [...form.objectives, ""]})} type="button">
                        {labels.addobjective}
                    </button>
                </div>
                {form.objectives.map((objective, index) => (
                    <div className="d-flex gap-2 mb-2" key={index}>
                        <input className={mcClasses("mc-form-control")} onChange={(e) => updateObjective(index, e.target.value)} type="text" value={objective} />
                        <button className={mcClasses("mc-button btn-mc-danger")} onClick={() => update({objectives: form.objectives.filter((_, i) => i !== index)})} type="button">&times;</button>
                    </div>
                ))}
            </div>

            <div className={mcClasses("mc-product-form__section")}>
                <div className="d-flex justify-content-between align-items-center">
                    <h4>{labels.courseoutline}</h4>
                    <button className={mcClasses("mc-button mc-btn-soft")} onClick={() => update({outline: [...form.outline, {title: "", time: ""}]})} type="button">
                        {labels.addsection}
                    </button>
                </div>
                {form.outline.map((row, index) => (
                    <div className="d-flex gap-2 mb-2" key={index}>
                        <input className={mcClasses("mc-form-control")} onChange={(e) => updateOutline(index, {title: e.target.value})} placeholder={labels.sectiontitle} type="text" value={row.title} />
                        <input className={mcClasses("mc-form-control mc-input-narrow")} onChange={(e) => updateOutline(index, {time: e.target.value})} placeholder={labels.estimatedtime} type="text" value={row.time} />
                        <button className={mcClasses("mc-button btn-mc-danger")} onClick={() => update({outline: form.outline.filter((_, i) => i !== index)})} type="button">&times;</button>
                    </div>
                ))}
            </div>

            <div className={mcClasses("mc-product-form__section")}>
                <h4>{labels.merchandising} ({detail.currency.code})</h4>
                <div className={mcClasses("mc-product-form__grid")}>
                    <label>
                        <span>{labels.regularprice}</span>
                        <input className={mcClasses("mc-form-control")} min="0" onChange={(e) => update({price: e.target.value})} step="0.01" type="number" value={form.price} />
                    </label>
                    <label>
                        <span>{labels.saleprice}</span>
                        <input className={mcClasses("mc-form-control")} min="0" onChange={(e) => update({saleprice: e.target.value})} step="0.01" type="number" value={form.saleprice} />
                    </label>
                </div>
                <div className={mcClasses("mc-product-form__checks")}>
                    <label><input checked={form.featured} onChange={(e) => update({featured: e.target.checked})} type="checkbox" /><span>{labels.featured}</span></label>
                    <label><input checked={form.bestseller} onChange={(e) => update({bestseller: e.target.checked})} type="checkbox" /><span>{labels.bestseller}</span></label>
                    <label><input checked={form.trending} onChange={(e) => update({trending: e.target.checked})} type="checkbox" /><span>{labels.trending}</span></label>
                </div>
            </div>

            <div className={mcClasses("mc-product-form__footer")}>
                <McButton className={mcClasses("btn-mc-primary")} loading={saving} loadingLabel={labels.saving || "Saving..."} onClick={submit} type="button">{labels.save}</McButton>
            </div>
        </section>
    );
}

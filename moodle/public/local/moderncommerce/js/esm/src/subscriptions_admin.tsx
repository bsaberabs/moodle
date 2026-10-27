// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin console for subscription plans, feature matrix, and subscribers
 * (Modern Commerce shell, Modern Commerce core webservice endpoints).
 *
 * @module     local_moderncommerce/subscriptions_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useId, useMemo, useRef, useState} from "react";
import type {FormEvent, ReactNode} from "react";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McDrawer} from "./drawer";
import {confirmDialog} from "./modal";
import {FieldError} from "./form";
import {focusFirstError, integer, max, maxLength, min, number, required, runRules} from "./validation";
import type {FieldErrorMap} from "./validation";
import {McTableActionMenu, McTableCard, McTableFooter, McTableFrame, McTablePagination} from "./table_components";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type SelectOption = {
    value: string;
    label: string;
};

type IconOption = SelectOption & {
    domain?: string;
    keywords?: string;
};

type Plan = {
    id: number;
    name: string;
    code: string;
    description: string;
    billingcycle: string;
    price: number;
    displayprice: string;
    promoprice: number;
    displaypromoprice: string;
    promoenddate: number;
    currency: string;
    trialdays: number;
    graceperioddays: number;
    maxseats: number;
    sortorder: number;
    status: string;
    featured: boolean;
    subscribercount: number;
    timecreated: number;
    timemodified: number;
};

type PlanStats = {
    totalplans: number;
    activeplans: number;
    inactiveplans: number;
    totalsubscribers: number;
    mrr: number;
    displaymrr: string;
};

type OverviewResponse = {
    plans: Plan[];
    total: number;
    page: number;
    perpage: number;
    stats: PlanStats;
    currency: {code: string; symbol: string; position: string; decimals: number};
};

type Feature = {
    id: number;
    name: string;
    description: string;
    icon: string;
    sortorder: number;
    status: string;
};

type Mapping = {
    featureid: number;
    planid: number;
    enabled: boolean;
};

type MatrixResponse = {
    features: Feature[];
    plans: Plan[];
    mappings: Mapping[];
};

type Subscription = {
    id: number;
    userid: number;
    userfullname: string;
    useremail: string;
    planid: number;
    planname: string;
    billingcycle: string;
    status: string;
    startdate: number;
    enddate: number;
    trialenddate: number;
    graceenddate: number;
    autorenew: boolean;
    renewalcount: number;
    cancelledat: number;
    cancelatperiodend: boolean;
    pendingplanid: number;
    pendingchangedate: number;
    accountcredit: number;
    timecreated: number;
    timemodified: number;
};

type SubscriptionStats = {
    total: number;
    active: number;
    trial: number;
    grace: number;
    cancelled: number;
    expired: number;
};

type SubscriptionListResponse = {
    items: Subscription[];
    total: number;
    page: number;
    perpage: number;
    stats: SubscriptionStats;
    plans: Plan[];
};

type SubscriberColumnKey =
    "subscriber"
    | "email"
    | "plan"
    | "billingcycle"
    | "status"
    | "startdate"
    | "enddate"
    | "trialenddate"
    | "graceenddate"
    | "autorenew"
    | "renewalcount"
    | "accountcredit"
    | "created"
    | "updated";

type SubscriberColumn = {
    key: SubscriberColumnKey;
    label: string;
    align?: "right";
    render: (subscription: Subscription) => ReactNode;
    exportValue: (subscription: Subscription) => string;
};

type HistoryEntry = {
    id: number;
    action: string;
    oldplanid: number;
    newplanid: number;
    amountpaid: number;
    notes: string;
    timecreated: number;
};

type AccessEntry = {
    id: number;
    courseid: number;
    coursename: string;
    courseshortname: string;
    grantedat: number;
    expiresat: number;
};

type SubscriptionDetail = {
    subscription: Subscription;
    history: HistoryEntry[];
    access: AccessEntry[];
};

type SimpleResult = {
    success: boolean;
    message: string;
};

type Methods = {
    overview: string;
    savePlan: string;
    deletePlan: string;
    featureMatrix: string;
    saveFeature: string;
    deleteFeature: string;
    saveFeatureMatrix: string;
    listSubscriptions: string;
    getSubscription: string;
    subscriptionAction: string;
};

type TabKey = "plans" | "features" | "subscribers";

type SubscriptionsAdminProps = {
    view?: string;
    methods: Methods;
    statusOptions: SelectOption[];
    billingOptions: SelectOption[];
    subscriptionStatusOptions: SelectOption[];
    iconOptions: IconOption[];
    perPageOptions: number[];
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
const BADGE_VARIANTS: McBadgeVariant[] = ["primary", "secondary", "accent", "success", "warning", "danger", "info", "neutral"];
const badgeVariant = (variant?: string): McBadgeVariant => (
    BADGE_VARIANTS.includes(variant as McBadgeVariant) ? variant as McBadgeVariant : "neutral"
);
const isTabKey = (value?: string): value is TabKey => value === "plans" || value === "features" || value === "subscribers";

const DASH = "—";
const DEFAULT_SUBSCRIBER_COLUMNS: SubscriberColumnKey[] = [
    "subscriber",
    "email",
    "plan",
    "billingcycle",
    "status",
    "enddate",
    "autorenew",
];

const formatDate = (value: number): string =>
    value > 0 ? new Date(value * 1000).toLocaleDateString(document.documentElement.lang || undefined) : DASH;

const formatCsvDate = (value: number): string =>
    value > 0 ? new Date(value * 1000).toISOString().slice(0, 10) : "";

const csvCell = (value: string | number | boolean): string => {
    const text = String(value).replace(/\r?\n|\r/g, " ");
    return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
};

const dateToTimestamp = (value: string): number => {
    if (!value) {
        return 0;
    }
    const parsed = Date.parse(`${value}T00:00:00`);
    return Number.isNaN(parsed) ? 0 : Math.floor(parsed / 1000);
};

const timestampToDateInput = (value: number): string => {
    if (!value) {
        return "";
    }
    const date = new Date(value * 1000);
    const month = `${date.getMonth() + 1}`.padStart(2, "0");
    const day = `${date.getDate()}`.padStart(2, "0");
    return `${date.getFullYear()}-${month}-${day}`;
};

const errorText = (caught: unknown): string => caught instanceof Error ? caught.message : String(caught);

const normalizeIconName = (value: string): string =>
    value.trim().replace(/^bi\s+/, "").replace(/^bi-/, "");

const iconClassName = (value: string): string =>
    `bi bi-${normalizeIconName(value) || "check-circle"}`;

const subscriptionStatusClass = (status: string): McBadgeVariant => {
    switch (status) {
        case "active":
            return "success";
        case "trial":
            return "info";
        case "grace":
            return "warning";
        case "suspended":
            return "warning";
        case "cancelled":
            return "neutral";
        case "expired":
            return "danger";
        default:
            return "neutral";
    }
};

const ErrorAlert = ({message}: {message: string}) => (
    <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
        <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
        <div className={mcClasses("mc-alert__body")}>{message}</div>
    </div>
);

// --- Plans tab ------------------------------------------------------------

type PlanForm = {
    id: number;
    name: string;
    code: string;
    description: string;
    billingcycle: string;
    price: string;
    promoprice: string;
    promoenddate: string;
    trialdays: string;
    graceperioddays: string;
    maxseats: string;
    status: string;
    sortorder: string;
    featured: boolean;
};

const planToForm = (plan: Plan): PlanForm => ({
    id: plan.id,
    name: plan.name,
    code: plan.code,
    description: plan.description,
    billingcycle: plan.billingcycle || "monthly",
    price: String(plan.price ?? 0),
    promoprice: plan.promoprice > 0 ? String(plan.promoprice) : "",
    promoenddate: timestampToDateInput(plan.promoenddate),
    trialdays: String(plan.trialdays ?? 0),
    graceperioddays: String(plan.graceperioddays ?? 7),
    maxseats: String(plan.maxseats ?? 0),
    status: plan.status || "active",
    sortorder: String(plan.sortorder ?? 0),
    featured: plan.featured,
});

const emptyPlanForm = (): PlanForm => ({
    id: 0,
    name: "",
    code: "",
    description: "",
    billingcycle: "monthly",
    price: "0",
    promoprice: "",
    promoenddate: "",
    trialdays: "0",
    graceperioddays: "7",
    maxseats: "0",
    status: "active",
    sortorder: "0",
    featured: false,
});

function PlansTab({
    methods,
    statusOptions,
    billingOptions,
    perPageOptions,
    labels,
    globalReload,
}: {
    methods: Methods;
    statusOptions: SelectOption[];
    billingOptions: SelectOption[];
    perPageOptions: number[];
    labels: Labels;
    globalReload: number;
}) {
    const [filters, setFilters] = useState({search: "", status: "", billingcycle: "", page: 0, perpage: 10});
    const [searchInput, setSearchInput] = useState("");
    const [data, setData] = useState<OverviewResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [reloadToken, setReloadToken] = useState(0);
    const [busyId, setBusyId] = useState(0);
    const [form, setForm] = useState<PlanForm | null>(null);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState("");
    const [fieldErrors, setFieldErrors] = useState<FieldErrorMap>({});
    const drawerBodyRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setFilters((current) => current.search === searchInput ? current : {...current, search: searchInput, page: 0});
        }, 350);
        return () => window.clearTimeout(timer);
    }, [searchInput]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");
        void callMoodleService<OverviewResponse>(methods.overview, filters)
            .then((result) => {
                if (!cancelled) {
                    setData(result);
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
    }, [filters, methods.overview, reloadToken, globalReload]);

    function closeForm(): void {
        setForm(null);
        setFormError("");
    }

    useEffect(() => {
        if (formError && drawerBodyRef.current) {
            drawerBodyRef.current.scrollTo({top: 0, behavior: "smooth"});
        }
    }, [formError]);

    const stats = data?.stats;
    const total = data?.total ?? 0;
    const totalPages = Math.max(1, Math.ceil(total / filters.perpage));
    const visiblePlanCount = data?.plans.length ?? 0;
    const visibleFrom = total === 0 || visiblePlanCount === 0 ? 0 : (filters.page * filters.perpage) + 1;
    const visibleTo = visiblePlanCount === 0 ? 0 : Math.min(total, visibleFrom + visiblePlanCount - 1);

    const updateFilters = (changes: Partial<typeof filters>) => {
        setFilters((current) => ({...current, ...changes, page: changes.page ?? 0}));
    };

    const updateForm = (changes: Partial<PlanForm>) => {
        setForm((current) => current ? {...current, ...changes} : current);
    };

    const openNewPlanForm = () => {
        setFormError("");
        setForm(emptyPlanForm());
    };

    const openEditPlanForm = (plan: Plan) => {
        setFormError("");
        setForm(planToForm(plan));
    };

    const submitForm = async(event?: FormEvent) => {
        event?.preventDefault();

        if (!form) {
            return;
        }
        const t = (key: string) => labels[key] ?? key;
        const found = runRules(form, {
            name: [required(labels.error_namerequired ?? t("error:required")), maxLength(255, t("error:maxlength"))],
            price: [required(t("error:required")), number(t("error:invalidnumber")), min(0, t("error:minvalue"))],
            billingcycle: required(t("error:selectoption")),
            trialdays: [integer(t("error:invalidinteger")), min(0, t("error:minvalue")), max(365, t("error:maxvalue"))],
            graceperioddays: [integer(t("error:invalidinteger")), min(0, t("error:minvalue"))],
            maxseats: [integer(t("error:invalidinteger")), min(0, t("error:minvalue"))],
            sortorder: integer(t("error:invalidinteger")),
        });
        setFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setFormError("");
            focusFirstError(found, ["name", "price", "billingcycle", "trialdays", "graceperioddays", "maxseats"]);
            return;
        }

        setSaving(true);
        setFormError("");
        try {
            const result = await callMoodleService<{success: boolean; message: string}>(methods.savePlan, {
                id: form.id,
                name: form.name.trim(),
                code: form.code.trim(),
                description: form.description,
                billingcycle: form.billingcycle,
                price: Number(form.price) || 0,
                promoprice: Number(form.promoprice) || 0,
                promoenddate: dateToTimestamp(form.promoenddate),
                trialdays: Number(form.trialdays) || 0,
                graceperioddays: Number(form.graceperioddays) || 0,
                maxseats: Number(form.maxseats) || 0,
                status: form.status,
                sortorder: Number(form.sortorder) || 0,
                featured: form.featured,
            });
            if (!result.success) {
                setFormError(result.message);
                return;
            }
            toast.success(result.message);
            setForm(null);
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setFormError(errorText(caught));
        } finally {
            setSaving(false);
        }
    };

    const deletePlan = async(plan: Plan) => {
        const force = plan.subscribercount > 0;
        const message = force
            ? labels.deleteplanwarning.replace("{$a}", String(plan.subscribercount))
            : labels.confirmdeleteplan;
        if (!await confirmDialog({message, danger: true})) {
            return;
        }
        setBusyId(plan.id);
        setError("");
        try {
            const result = await callMoodleService<SimpleResult>(methods.deletePlan, {id: plan.id, force});
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setBusyId(0);
        }
    };

    const renderPlanDrawer = () => {
        if (!form) {
            return null;
        }

        const title = form.id > 0 ? labels.editplan : labels.createplan;

        return (
            <McDrawer
                title={title}
                subtitle={form.id > 0 && form.name ? form.name : undefined}
                onClose={closeForm}
                closeLabel={labels.close}
                disableClose={saving}
                bodyRef={drawerBodyRef}
                footer={(
                    <>
                        <McButton
                            className={mcClasses("btn-mc-primary")}
                            disabled={saving}
                            form="mc-subscription-plan-drawer-form"
                            loading={saving}
                            loadingLabel={labels.saving || "Saving..."}
                            type="submit"
                        >
                            {form.id > 0 ? labels.savechanges : labels.save}
                        </McButton>
                        <button
                            className={mcClasses("mc-button btn-mc-secondary")}
                            disabled={saving}
                            onClick={closeForm}
                            type="button"
                        >
                            {labels.cancel}
                        </button>
                    </>
                )}
            >
                {formError && <ErrorAlert message={formError} />}

                <form id="mc-subscription-plan-drawer-form" noValidate onSubmit={submitForm}>
                            <div className={mcClasses("mc-form-section")}>
                                <div className={mcClasses("mc-form-section__header")}>
                                    <h4 className={mcClasses("mc-form-section__title")}>{labels.planinfo}</h4>
                                </div>
                                <div className={mcClasses("mc-form-section__body")}>
                                    <div className={mcClasses("mc-product-form__grid")}>
                                        <label className={mcClasses("mc-product-form__wide")} htmlFor="mc-plan-name">
                                            <span>
                                                {labels.planname}
                                                <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                            </span>
                                            <input
                                                autoFocus
                                                aria-describedby={fieldErrors.name ? "mc-plan-name-error" : undefined}
                                                aria-invalid={fieldErrors.name ? true : undefined}
                                                aria-required="true"
                                                className={mcClasses("mc-form-control",
                                                    fieldErrors.name ? "mc-field-invalid" : "")}
                                                id="mc-plan-name"
                                                maxLength={255}
                                                name="name"
                                                onChange={(event) => updateForm({name: event.target.value})}
                                                required
                                                type="text"
                                                value={form.name}
                                            />
                                            <FieldError id="mc-plan-name-error" message={fieldErrors.name} />
                                        </label>
                                        <label>
                                            <span>{labels.plancode}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => updateForm({code: event.target.value})}
                                                type="text"
                                                value={form.code}
                                            />
                                        </label>
                                        <label>
                                            <span>{labels.planstatus}</span>
                                            <select
                                                className={mcClasses("mc-select")}
                                                onChange={(event) => updateForm({status: event.target.value})}
                                                value={form.status}
                                            >
                                                {statusOptions.map((option) => (
                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                ))}
                                            </select>
                                        </label>
                                        <label className={mcClasses("mc-product-form__wide")}>
                                            <span>{labels.plandescription}</span>
                                            <textarea
                                                className={mcClasses("form-control form-control-sm")}
                                                onChange={(event) => updateForm({description: event.target.value})}
                                                rows={3}
                                                value={form.description}
                                            />
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div className={mcClasses("mc-form-section")}>
                                <div className={mcClasses("mc-form-section__header")}>
                                    <h4 className={mcClasses("mc-form-section__title")}>{labels.pricingsettings}</h4>
                                </div>
                                <div className={mcClasses("mc-form-section__body")}>
                                    <div className={mcClasses("mc-product-form__grid")}>
                                        <label>
                                            <span>{labels.billingcycle}</span>
                                            <select
                                                className={mcClasses("mc-select")}
                                                onChange={(event) => updateForm({billingcycle: event.target.value})}
                                                value={form.billingcycle}
                                            >
                                                {billingOptions.map((option) => (
                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                ))}
                                            </select>
                                        </label>
                                        <label>
                                            <span>
                                                {labels.planprice}
                                                <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                            </span>
                                            <input
                                                aria-describedby={fieldErrors.price ? "mc-plan-price-error" : undefined}
                                                aria-invalid={fieldErrors.price ? true : undefined}
                                                aria-required="true"
                                                className={mcClasses("mc-form-control",
                                                    fieldErrors.price ? "mc-field-invalid" : "")}
                                                id="mc-plan-price"
                                                min="0"
                                                name="price"
                                                step="0.01"
                                                onChange={(event) => updateForm({price: event.target.value})}
                                                type="number"
                                                value={form.price}
                                            />
                                            <FieldError id="mc-plan-price-error" message={fieldErrors.price} />
                                        </label>
                                        <label>
                                            <span>{labels.promoprice}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                min="0"
                                                step="0.01"
                                                onChange={(event) => updateForm({promoprice: event.target.value})}
                                                type="number"
                                                value={form.promoprice}
                                            />
                                        </label>
                                        <label>
                                            <span>{labels.promoenddate}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => updateForm({promoenddate: event.target.value})}
                                                type="date"
                                                value={form.promoenddate}
                                            />
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div className={mcClasses("mc-form-section")}>
                                <div className={mcClasses("mc-form-section__header")}>
                                    <h4 className={mcClasses("mc-form-section__title")}>{labels.subscriptionsettings}</h4>
                                </div>
                                <div className={mcClasses("mc-form-section__body")}>
                                    <div className={mcClasses("mc-product-form__grid")}>
                                        <label>
                                            <span>{labels.trialdays}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                min="0"
                                                onChange={(event) => updateForm({trialdays: event.target.value})}
                                                type="number"
                                                value={form.trialdays}
                                            />
                                        </label>
                                        <label>
                                            <span>{labels.graceperioddays}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                min="0"
                                                onChange={(event) => updateForm({graceperioddays: event.target.value})}
                                                type="number"
                                                value={form.graceperioddays}
                                            />
                                        </label>
                                        <label>
                                            <span>{labels.maxseats}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                min="0"
                                                onChange={(event) => updateForm({maxseats: event.target.value})}
                                                type="number"
                                                value={form.maxseats}
                                            />
                                        </label>
                                        <label>
                                            <span>{labels.sortorder}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                min="0"
                                                onChange={(event) => updateForm({sortorder: event.target.value})}
                                                type="number"
                                                value={form.sortorder}
                                            />
                                        </label>
                                    </div>
                                    <div className={mcClasses("mc-product-form__checks mt-3")}>
                                        <label className={mcClasses("mc-switch")}>
                                            <input
                                                checked={form.featured}
                                                onChange={(event) => updateForm({featured: event.target.checked})}
                                                type="checkbox"
                                            />
                                            <span className={mcClasses("mc-switch__track")} aria-hidden="true" />
                                            <span className={mcClasses("mc-switch__thumb")} aria-hidden="true" />
                                            <span className={mcClasses("mc-switch__label")}>{labels.featuredplan}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                </form>
            </McDrawer>
        );
    };

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.plans}>
            {error && <ErrorAlert message={error} />}

            {stats && (
                <div className={mcClasses("mc-stat-strip")} aria-label={labels.plans}>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--primary")}>
                        <i className="bi bi-card-list mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.totalplans}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.totalplans)}</strong>
                        </div>
                        <i className="bi bi-card-list mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                        <i className="bi bi-check-circle mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.activeplans}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.activeplans)}</strong>
                        </div>
                        <i className="bi bi-check-circle mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--info")}>
                        <i className="bi bi-people mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.totalsubscribers}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.totalsubscribers)}</strong>
                        </div>
                        <i className="bi bi-people mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--warning")}>
                        <i className="bi bi-graph-up-arrow mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.mrr}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{stats.displaymrr}</strong>
                        </div>
                        <i className="bi bi-graph-up-arrow mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                </div>
            )}

            <McTableCard
                title={<span className={mcClasses("mc-card-title")}>{labels.plans}</span>}
                actions={(
                    <button className={mcClasses("mc-button btn-mc-primary")} onClick={openNewPlanForm} type="button">
                        <i className="bi bi-plus-lg" aria-hidden="true" /> {labels.createplan}
                    </button>
                )}
                toolbar={(
                        <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                            <div className={mcClasses("mc-product-toolbar__search")}>
                                <label className={mcClasses("mc-filter-label")} htmlFor="mc-plans-search">
                                    {labels.search}
                                </label>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    id="mc-plans-search"
                                    onChange={(event) => setSearchInput(event.target.value)}
                                    placeholder={labels.searchplans}
                                    type="search"
                                    value={searchInput}
                                />
                            </div>
                            <label className={mcClasses("mc-product-toolbar__field")}>
                                <span className={mcClasses("mc-filter-label")}>{labels.status}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    onChange={(event) => updateFilters({status: event.target.value})}
                                    value={filters.status}
                                >
                                    <option value="">{labels.allstatuses}</option>
                                    {statusOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </label>
                            <label className={mcClasses("mc-product-toolbar__field")}>
                                <span className={mcClasses("mc-filter-label")}>{labels.billingcycle}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    onChange={(event) => updateFilters({billingcycle: event.target.value})}
                                    value={filters.billingcycle}
                                >
                                    <option value="">{labels.allcycles}</option>
                                    {billingOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </label>
                            <label className={mcClasses("mc-table-design-page-size")}>
                                <span className={mcClasses("mc-filter-label")}>{labels.perpage}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    onChange={(event) => updateFilters({perpage: Number(event.target.value) || 10})}
                                    value={filters.perpage}
                                >
                                    {perPageOptions.map((option) => (
                                        <option key={option} value={option}>{option}</option>
                                    ))}
                                </select>
                            </label>
                        </div>
                )}
                footer={(
                    <McTableFooter
                        summary={(
                            <span>
                                {labels.showing} {formatCount(visibleFrom)}-{formatCount(visibleTo)} / {formatCount(total)}
                            </span>
                        )}
                        pagination={(
                            <McTablePagination
                                previousLabel={labels.previous}
                                nextLabel={labels.next}
                                pageLabel={labels.page}
                                page={filters.page + 1}
                                totalPages={totalPages}
                                previousDisabled={loading || filters.page <= 0}
                                nextDisabled={loading || filters.page + 1 >= totalPages}
                                onPrevious={() => updateFilters({page: Math.max(0, filters.page - 1)})}
                                onNext={() => updateFilters({page: filters.page + 1})}
                            />
                        )}
                    />
                )}
            >
                        <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.plans}>
                            <thead>
                                <tr>
                                    <th scope="col">{labels.planname}</th>
                                    <th scope="col">{labels.billingcycle}</th>
                                    <th scope="col" className="text-end">{labels.planprice}</th>
                                    <th scope="col" className="text-end">{labels.subscribers}</th>
                                    <th scope="col">{labels.status}</th>
                                    <th scope="col" className="text-end">{labels.actions}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {!loading && data?.plans.length === 0 && (
                                    <tr>
                                        <td colSpan={6}>
                                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                <span className={mcClasses("mc-empty__icon")}>
                                                    <i className="bi bi-card-list" aria-hidden="true" />
                                                </span>
                                                <p className={mcClasses("mc-empty__title")}>
                                                    {total === 0 ? labels.noplansfound : labels.noresults}
                                                </p>
                                                {total === 0 && (
                                                    <button
                                                        className={mcClasses("mc-button btn-mc-primary mt-2")}
                                                        onClick={openNewPlanForm}
                                                        type="button"
                                                    >
                                                        {labels.createplan}
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {data?.plans.map((plan) => (
                                    <tr key={plan.id}>
                                        <td>
                                            <div className="d-flex align-items-center gap-2">
                                                <strong>{plan.name}</strong>
                                                {plan.featured && (
                                                    <McBadge variant="primary" tone="soft" icon="bi-star-fill">
                                                        {labels.featured || "Featured"}
                                                    </McBadge>
                                                )}
                                            </div>
                                            {plan.code !== "" && (
                                                <div className={mcClasses("mc-cell-muted small mc-cell-mono")}>
                                                    {plan.code}
                                                </div>
                                            )}
                                        </td>
                                        <td>
                                            <McBadge variant="neutral" tone="soft">
                                                {plan.billingcycle === "yearly"
                                                    ? labels.billingcycle_yearly
                                                    : labels.billingcycle_monthly}
                                            </McBadge>
                                        </td>
                                        <td className="text-end">
                                            {plan.promoprice > 0 ? (
                                                <span>
                                                    <span className={mcClasses("mc-price")}>
                                                        {plan.displaypromoprice}
                                                    </span>
                                                    <span className={mcClasses(
                                                        "mc-cell-muted small text-decoration-line-through ms-1"
                                                    )}>
                                                        {plan.displayprice}
                                                    </span>
                                                </span>
                                            ) : (
                                                <span className={mcClasses("mc-price")}>{plan.displayprice}</span>
                                            )}
                                        </td>
                                        <td className="text-end">{formatCount(plan.subscribercount)}</td>
                                        <td>
                                            <McBadge variant={plan.status === "active" ? "success" : "neutral"} tone="soft" dot>
                                                {plan.status === "active"
                                                    ? labels.planstatus_active
                                                    : labels.planstatus_inactive}
                                            </McBadge>
                                        </td>
                                        <td className="text-end">
                                            <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                                <McTableActionMenu
                                                    label={`${labels.actions}: ${plan.name}`}
                                                    disabled={busyId === plan.id}
                                                    items={[
                                                        {
                                                            key: "access",
                                                            label: labels.access,
                                                            icon: "bi bi-shield-check",
                                                            href: `${M.cfg.wwwroot}/local/moderncommerce/admin/subscription_plan_access.php?id=${plan.id}`,
                                                        },
                                                        {
                                                            key: "edit",
                                                            label: labels.edit,
                                                            icon: "bi bi-pencil",
                                                            onClick: () => openEditPlanForm(plan),
                                                        },
                                                        {
                                                            key: "delete",
                                                            label: labels.delete,
                                                            icon: "bi bi-trash",
                                                            danger: true,
                                                            onClick: () => void deletePlan(plan),
                                                        },
                                                    ]}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {loading && (
                                    <tr>
                                        <td colSpan={6}>
                                            <div className={mcClasses("mc-product-admin__loading")}>
                                                {labels.loading}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
            </McTableCard>

            {renderPlanDrawer()}
        </section>
    );
}

// --- Features tab ---------------------------------------------------------

const ICON_OPTION_LIMIT = 50;

type IconPickerProps = {
    value: string;
    options: IconOption[];
    labels: Labels;
    // eslint-disable-next-line no-unused-vars
    onChange(icon: string): void;
};

function IconPicker({
    value,
    options,
    labels,
    onChange,
}: IconPickerProps) {
    const inputId = useId();
    const normalizedValue = normalizeIconName(value);
    const [query, setQuery] = useState(normalizedValue);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        setQuery(normalizedValue);
    }, [normalizedValue]);

    const filteredOptions = useMemo(() => {
        const rawNeedle = query.trim().toLowerCase();
        const iconNeedle = normalizeIconName(query).toLowerCase();
        const matches = rawNeedle === "" ? options : options.filter((option) => {
            const optionIcon = normalizeIconName(option.value);
            const haystack = [
                option.label,
                optionIcon,
                option.domain ?? "",
                option.keywords ?? "",
            ].join(" ").toLowerCase();
            return haystack.includes(rawNeedle) || haystack.includes(iconNeedle);
        });

        const selected = options.find((option) =>
            normalizeIconName(option.value) === normalizedValue
        );
        const ordered = selected && !matches.includes(selected) ? [selected, ...matches] : matches;

        return ordered.slice(0, ICON_OPTION_LIMIT);
    }, [normalizedValue, options, query]);

    const commitQuery = () => {
        const rawQuery = query.trim();
        const normalizedQuery = normalizeIconName(rawQuery);
        const exactMatch = options.find((option) => {
            const optionIcon = normalizeIconName(option.value);
            return optionIcon.toLowerCase() === normalizedQuery.toLowerCase()
                || option.label.toLowerCase() === rawQuery.toLowerCase();
        });
        const nextIcon = normalizeIconName(exactMatch?.value ?? filteredOptions[0]?.value ?? normalizedQuery)
            || "check-circle";

        onChange(nextIcon);
        setQuery(nextIcon);
    };

    const selectIcon = (option: IconOption) => {
        const nextIcon = normalizeIconName(option.value) || "check-circle";

        onChange(nextIcon);
        setQuery(nextIcon);
        setOpen(false);
    };

    return (
        <div className="d-flex flex-column gap-1 position-relative">
            <label className={mcClasses("mc-field-label")} htmlFor={inputId}>{labels.featureicon}</label>
            <div className="d-flex align-items-stretch" style={{width: "100%"}}>
                <span
                    className="d-inline-flex align-items-center justify-content-center border bg-white"
                    aria-hidden="true"
                    style={{
                        borderRadius: "0.375rem 0 0 0.375rem",
                        borderRight: 0,
                        flex: "0 0 2.75rem",
                    }}
                >
                    <i className={iconClassName(normalizedValue)} />
                </span>
                <input
                    aria-autocomplete="list"
                    aria-expanded={open ? "true" : "false"}
                    aria-label={labels.selecticon ?? labels.featureicon}
                    className={mcClasses("mc-form-control")}
                    id={inputId}
                    onBlur={() => {
                        window.setTimeout(() => {
                            commitQuery();
                            setOpen(false);
                        }, 120);
                    }}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                    placeholder="check-circle"
                    role="combobox"
                    style={{
                        borderBottomLeftRadius: 0,
                        borderTopLeftRadius: 0,
                        minWidth: 0,
                    }}
                    type="search"
                    value={query}
                />
            </div>
            {labels.featureicon_help && (
                <span className={mcClasses("mc-cell-muted small")}>{labels.featureicon_help}</span>
            )}
            {open && (
                <div
                    className="position-absolute start-0 end-0 bg-white border rounded shadow-sm p-1"
                    role="listbox"
                    style={{
                        maxHeight: "18rem",
                        overflowY: "auto",
                        top: "100%",
                        zIndex: 1050,
                    }}
                >
                    {filteredOptions.length === 0 && (
                        <div className="px-3 py-2 text-muted small">{labels.noresults}</div>
                    )}
                    {filteredOptions.map((option) => {
                        const optionIcon = normalizeIconName(option.value);

                        return (
                            <button
                                className="mc-button dropdown-item d-flex align-items-center gap-2"
                                data-mc-button="ghost"
                                key={optionIcon}
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    selectIcon(option);
                                }}
                                role="option"
                                type="button"
                            >
                                <span
                                    className="d-inline-flex justify-content-center"
                                    style={{width: "1.75rem"}}
                                >
                                    <i className={iconClassName(optionIcon)} aria-hidden="true" />
                                </span>
                                <span className="flex-grow-1 text-start">{option.label}</span>
                                <code>{optionIcon}</code>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

type FeatureForm = {
    id: number;
    name: string;
    description: string;
    icon: string;
    status: string;
};

const matrixKey = (featureid: number, planid: number): string => `${featureid}:${planid}`;

function FeaturesTab({
    methods,
    labels,
    iconOptions,
    globalReload,
}: {
    methods: Methods;
    labels: Labels;
    iconOptions: IconOption[];
    globalReload: number;
}) {
    const [data, setData] = useState<MatrixResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [reloadToken, setReloadToken] = useState(0);
    const [matrix, setMatrix] = useState<Record<string, boolean>>({});
    const [dirty, setDirty] = useState(false);
    const [savingMatrix, setSavingMatrix] = useState(false);
    const [featureForm, setFeatureForm] = useState<FeatureForm | null>(null);
    const [featureFormError, setFeatureFormError] = useState("");
    const [featureFieldErrors, setFeatureFieldErrors] = useState<FieldErrorMap>({});
    const [savingFeature, setSavingFeature] = useState(false);
    const [busyId, setBusyId] = useState(0);
    const featureDrawerBodyRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");
        void callMoodleService<MatrixResponse>(methods.featureMatrix, {})
            .then((result) => {
                if (cancelled) {
                    return;
                }
                setData(result);
                const next: Record<string, boolean> = {};
                result.mappings.forEach((mapping) => {
                    next[matrixKey(mapping.featureid, mapping.planid)] = mapping.enabled;
                });
                setMatrix(next);
                setDirty(false);
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
    }, [methods.featureMatrix, reloadToken, globalReload]);

    function closeFeatureForm(): void {
        setFeatureForm(null);
        setFeatureFormError("");
    }

    const openNewFeatureForm = () => {
        setError("");
        setFeatureFormError("");
        setFeatureForm({id: 0, name: "", description: "", icon: "check-circle", status: "active"});
    };

    const openEditFeatureForm = (feature: Feature) => {
        setError("");
        setFeatureFormError("");
        setFeatureForm({
            id: feature.id,
            name: feature.name,
            description: feature.description,
            icon: feature.icon,
            status: feature.status,
        });
    };

    useEffect(() => {
        if (featureFormError && featureDrawerBodyRef.current) {
            featureDrawerBodyRef.current.scrollTo({top: 0, behavior: "smooth"});
        }
    }, [featureFormError]);

    const features = data?.features ?? [];
    const plans = data?.plans ?? [];

    const toggleCell = (featureid: number, planid: number) => {
        const key = matrixKey(featureid, planid);
        setMatrix((current) => ({...current, [key]: !current[key]}));
        setDirty(true);
    };

    const saveMatrix = async() => {
        setSavingMatrix(true);
        setError("");
        const mappings: Mapping[] = [];
        features.forEach((feature) => {
            plans.forEach((plan) => {
                mappings.push({featureid: feature.id, planid: plan.id, enabled: Boolean(matrix[matrixKey(feature.id, plan.id)])});
            });
        });
        try {
            const result = await callMoodleService<SimpleResult>(methods.saveFeatureMatrix, {mappings});
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            setDirty(false);
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setSavingMatrix(false);
        }
    };

    const submitFeature = async(event?: FormEvent) => {
        event?.preventDefault();

        if (!featureForm) {
            return;
        }
        const ft = (key: string) => labels[key] ?? key;
        const featureFound = runRules(featureForm, {
            name: [required(labels.error_namerequired ?? ft("error:required")), maxLength(255, ft("error:maxlength"))],
            description: maxLength(500, ft("error:maxlength")),
        });
        setFeatureFieldErrors(featureFound);
        if (Object.keys(featureFound).length > 0) {
            setFeatureFormError("");
            focusFirstError(featureFound, ["name", "description"]);
            return;
        }

        setSavingFeature(true);
        setFeatureFormError("");
        try {
            const result = await callMoodleService<{success: boolean; message: string}>(methods.saveFeature, {
                id: featureForm.id,
                name: featureForm.name.trim(),
                description: featureForm.description,
                icon: normalizeIconName(featureForm.icon) || "check-circle",
                status: featureForm.status,
            });
            if (!result.success) {
                setFeatureFormError(result.message);
                return;
            }
            toast.success(result.message);
            setFeatureForm(null);
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setFeatureFormError(errorText(caught));
        } finally {
            setSavingFeature(false);
        }
    };

    const deleteFeature = async(feature: Feature) => {
        if (!await confirmDialog({message: labels.confirmdeletefeaturematrix, danger: true})) {
            return;
        }
        setBusyId(feature.id);
        setError("");
        try {
            const result = await callMoodleService<SimpleResult>(methods.deleteFeature, {id: feature.id});
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setBusyId(0);
        }
    };

    const featureTitle = (
        <div className="d-flex flex-column gap-1">
            <span className={mcClasses("mc-card-title")}>{labels.featurematrix}</span>
            <p className={mcClasses("mc-cell-muted small mb-0")}>{labels.featurematrix_desc}</p>
        </div>
    );

    const featureActions = (
        <div className={mcClasses("mc-action-group")}>
            <button className={mcClasses("mc-button mc-btn-soft")} onClick={openNewFeatureForm} type="button">
                <i className="bi bi-plus-lg" aria-hidden="true" /> {labels.addnewfeature}
            </button>
            <McButton className={mcClasses("btn-mc-primary")} disabled={!dirty} loading={savingMatrix} loadingLabel={labels.saving || "Saving..."} onClick={saveMatrix} type="button">
                {labels.savechanges}
            </McButton>
        </div>
    );

    const featureEditor = featureForm && (() => {
        const title = featureForm.id > 0 ? labels.editfeature : labels.addnewfeature;

        return (
            <McDrawer
                title={title}
                subtitle={featureForm.id > 0 && featureForm.name ? featureForm.name : undefined}
                onClose={closeFeatureForm}
                closeLabel={labels.close}
                disableClose={savingFeature}
                bodyRef={featureDrawerBodyRef}
                footer={(
                    <>
                        <McButton
                            className={mcClasses("btn-mc-primary")}
                            disabled={savingFeature}
                            form="mc-subscription-feature-drawer-form"
                            loading={savingFeature}
                            loadingLabel={labels.saving || "Saving..."}
                            type="submit"
                        >
                            {featureForm.id > 0 ? labels.savechanges : labels.save}
                        </McButton>
                        <button
                            className={mcClasses("mc-button btn-mc-secondary")}
                            disabled={savingFeature}
                            onClick={closeFeatureForm}
                            type="button"
                        >
                            {labels.cancel}
                        </button>
                    </>
                )}
            >
                {featureFormError && <ErrorAlert message={featureFormError} />}

                <form id="mc-subscription-feature-drawer-form" noValidate onSubmit={submitFeature}>
                            <div className={mcClasses("mc-form-section")}>
                                <div className={mcClasses("mc-form-section__header")}>
                                    <h4 className={mcClasses("mc-form-section__title")}>{labels.feature}</h4>
                                </div>
                                <div className={mcClasses("mc-form-section__body")}>
                                    <div className={mcClasses("mc-product-form__grid")}>
                                        <label className={mcClasses("mc-product-form__wide")}>
                                            <span>{labels.featurename}</span>
                                            <input
                                                autoFocus
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => setFeatureForm((current) =>
                                                    current ? {...current, name: event.target.value} : current
                                                )}
                                                aria-describedby={featureFieldErrors.name
                                                    ? "mc-feature-name-error" : undefined}
                                                aria-invalid={featureFieldErrors.name ? true : undefined}
                                                aria-required="true"
                                                id="mc-feature-name"
                                                name="name"
                                                placeholder={labels.featurename_placeholder}
                                                required
                                                type="text"
                                                value={featureForm.name}
                                            />
                                            <FieldError id="mc-feature-name-error" message={featureFieldErrors.name} />
                                        </label>
                                        <IconPicker
                                            labels={labels}
                                            onChange={(icon) => setFeatureForm((current) =>
                                                current ? {...current, icon} : current
                                            )}
                                            options={iconOptions}
                                            value={featureForm.icon}
                                        />
                                        <label>
                                            <span>{labels.status}</span>
                                            <select
                                                className={mcClasses("mc-select")}
                                                onChange={(event) => setFeatureForm((current) =>
                                                    current ? {...current, status: event.target.value} : current
                                                )}
                                                value={featureForm.status}
                                            >
                                                <option value="active">{labels.planstatus_active}</option>
                                                <option value="inactive">{labels.planstatus_inactive}</option>
                                            </select>
                                        </label>
                                        <label className={mcClasses("mc-product-form__wide")}>
                                            <span>{labels.plandescription}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => setFeatureForm((current) =>
                                                    current ? {...current, description: event.target.value} : current
                                                )}
                                                type="text"
                                                value={featureForm.description}
                                            />
                                        </label>
                                    </div>
                                </div>
                            </div>
                </form>
            </McDrawer>
        );
    })();

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.featurematrix}>
            {error && <ErrorAlert message={error} />}

            <div className={mcClasses("mc-subscription-feature-layout")}>
                <div className={mcClasses("mc-subscription-feature-layout__main")}>
                    <McTableCard title={featureTitle} actions={featureActions}>
                        {loading && !data && <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>}

                        {!loading && plans.length === 0 && (
                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                <span className={mcClasses("mc-empty__icon")}><i className="bi bi-grid-3x3-gap" aria-hidden="true" /></span>
                                <p className={mcClasses("mc-empty__title")}>{labels.noplans}</p>
                            </div>
                        )}

                        {!loading && plans.length > 0 && features.length === 0 && (
                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                <span className={mcClasses("mc-empty__icon")}><i className="bi bi-list-check" aria-hidden="true" /></span>
                                <p className={mcClasses("mc-empty__title")}>{labels.nofeaturesmatrix}</p>
                            </div>
                        )}

                        {plans.length > 0 && features.length > 0 && (
                            <table
                                className={mcClasses("table mc-table mc-product-table mb-0")}
                                aria-label={labels.featurematrix}
                            >
                                <thead>
                                    <tr>
                                        <th scope="col">{labels.feature}</th>
                                        {plans.map((plan) => (
                                            <th key={plan.id} scope="col" className="text-center">{plan.name}</th>
                                        ))}
                                        <th scope="col" className="text-end">{labels.actions}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {features.map((feature) => (
                                        <tr key={feature.id}>
                                            <td>
                                                <div className="d-flex align-items-center gap-2">
                                                    <i className={iconClassName(feature.icon)} aria-hidden="true" />
                                                    <span>{feature.name}</span>
                                                </div>
                                                {feature.description !== "" && (
                                                    <div className={mcClasses("mc-cell-muted small")}>
                                                        {feature.description}
                                                    </div>
                                                )}
                                            </td>
                                            {plans.map((plan) => (
                                                <td key={plan.id} className="text-center">
                                                    <label className={mcClasses("mc-checkbox justify-content-center")}>
                                                        <input
                                                            aria-label={`${feature.name} - ${plan.name}`}
                                                            checked={Boolean(matrix[matrixKey(feature.id, plan.id)])}
                                                            onChange={() => toggleCell(feature.id, plan.id)}
                                                            type="checkbox"
                                                        />
                                                    </label>
                                                </td>
                                            ))}
                                            <td className="text-end">
                                                <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                                    <McTableActionMenu
                                                        label={`${labels.actions}: ${feature.name}`}
                                                        disabled={busyId === feature.id}
                                                        items={[
                                                            {
                                                                key: "edit",
                                                                label: labels.edit,
                                                                icon: "bi bi-pencil",
                                                                onClick: () => openEditFeatureForm(feature),
                                                            },
                                                            {
                                                                key: "delete",
                                                                label: labels.delete,
                                                                icon: "bi bi-trash",
                                                                danger: true,
                                                                onClick: () => void deleteFeature(feature),
                                                            },
                                                        ]}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </McTableCard>
                </div>
                {featureEditor}
            </div>
        </section>
    );
}

// --- Subscribers tab ------------------------------------------------------

function SubscribersTab({
    methods,
    billingOptions,
    subscriptionStatusOptions,
    perPageOptions,
    labels,
    globalReload,
}: {
    methods: Methods;
    billingOptions: SelectOption[];
    subscriptionStatusOptions: SelectOption[];
    perPageOptions: number[];
    labels: Labels;
    globalReload: number;
}) {
    const [filters, setFilters] = useState({search: "", status: "", planid: 0, billingcycle: "", page: 0, perpage: 10});
    const [searchInput, setSearchInput] = useState("");
    const [data, setData] = useState<SubscriptionListResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [reloadToken, setReloadToken] = useState(0);
    const [busyId, setBusyId] = useState(0);
    const [detail, setDetail] = useState<SubscriptionDetail | null>(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [columnsOpen, setColumnsOpen] = useState(false);
    const [selectedColumns, setSelectedColumns] = useState<SubscriberColumnKey[]>(DEFAULT_SUBSCRIBER_COLUMNS);
    const [draftColumns, setDraftColumns] = useState<SubscriberColumnKey[]>(DEFAULT_SUBSCRIBER_COLUMNS);
    const [exporting, setExporting] = useState(false);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setFilters((current) => current.search === searchInput ? current : {...current, search: searchInput, page: 0});
        }, 350);
        return () => window.clearTimeout(timer);
    }, [searchInput]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");
        void callMoodleService<SubscriptionListResponse>(methods.listSubscriptions, filters)
            .then((result) => {
                if (!cancelled) {
                    setData(result);
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
    }, [filters, methods.listSubscriptions, reloadToken, globalReload]);

    const stats = data?.stats;
    const plans = data?.plans ?? [];
    const total = data?.total ?? 0;
    const totalPages = Math.max(1, Math.ceil(total / filters.perpage));
    const visibleSubscriptionCount = data?.items.length ?? 0;
    const visibleFrom = total === 0 || visibleSubscriptionCount === 0 ? 0 : (filters.page * filters.perpage) + 1;
    const visibleTo = visibleSubscriptionCount === 0
        ? 0
        : Math.min(total, visibleFrom + visibleSubscriptionCount - 1);

    const updateFilters = (changes: Partial<typeof filters>) => {
        setFilters((current) => ({...current, ...changes, page: changes.page ?? 0}));
    };

    const statusLabel = (status: string): string => {
        const match = subscriptionStatusOptions.find((option) => option.value === status);
        return match ? match.label : status;
    };

    const billingCycleLabel = (billingcycle: string): string => {
        const match = billingOptions.find((option) => option.value === billingcycle);
        return match ? match.label : billingcycle || DASH;
    };

    const openDetail = (subscription: Subscription) => {
        setDetailLoading(true);
        setError("");
        setDetail({subscription, history: [], access: []});
        void callMoodleService<SubscriptionDetail>(methods.getSubscription, {id: subscription.id})
            .then((result) => setDetail(result))
            .catch((caught: unknown) => {
                setError(errorText(caught));
                setDetail(null);
            })
            .finally(() => setDetailLoading(false));
    };

    const runAction = async(subscription: Subscription, action: string, confirmMessage?: string) => {
        if (confirmMessage && !await confirmDialog({message: confirmMessage, danger: true})) {
            return;
        }
        setBusyId(subscription.id);
        setError("");
        try {
            const result = await callMoodleService<SimpleResult>(methods.subscriptionAction, {id: subscription.id, action, reason: "", planid: 0});
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            setReloadToken((current) => current + 1);
            if (detail && detail.subscription.id === subscription.id) {
                openDetail(subscription);
            }
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setBusyId(0);
        }
    };

    const allColumns: SubscriberColumn[] = [
        {
            key: "subscriber",
            label: labels.subscriber,
            render: (sub) => (
                <button
                    className={mcClasses("mc-button mc-btn-ghost p-0 text-start")}
                    onClick={() => openDetail(sub)}
                    type="button"
                >
                    <strong>{sub.userfullname}</strong>
                </button>
            ),
            exportValue: (sub) => sub.userfullname,
        },
        {
            key: "email",
            label: labels.email || "Email",
            render: (sub) => <span className={mcClasses("mc-cell-muted small")}>{sub.useremail}</span>,
            exportValue: (sub) => sub.useremail,
        },
        {
            key: "plan",
            label: labels.plan,
            render: (sub) => sub.planname,
            exportValue: (sub) => sub.planname,
        },
        {
            key: "billingcycle",
            label: labels.billingcycle,
            render: (sub) => (
                <McBadge variant="neutral" tone="soft">
                    {billingCycleLabel(sub.billingcycle)}
                </McBadge>
            ),
            exportValue: (sub) => billingCycleLabel(sub.billingcycle),
        },
        {
            key: "status",
            label: labels.status,
            render: (sub) => (
                <McBadge variant={subscriptionStatusClass(sub.status)} tone="soft" dot>
                    {statusLabel(sub.status)}
                </McBadge>
            ),
            exportValue: (sub) => statusLabel(sub.status),
        },
        {
            key: "startdate",
            label: labels.startdate,
            render: (sub) => <span className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{formatDate(sub.startdate)}</span>,
            exportValue: (sub) => formatCsvDate(sub.startdate),
        },
        {
            key: "enddate",
            label: labels.enddate,
            render: (sub) => <span className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{formatDate(sub.enddate)}</span>,
            exportValue: (sub) => formatCsvDate(sub.enddate),
        },
        {
            key: "trialenddate",
            label: labels.trialenddate,
            render: (sub) => <span className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{formatDate(sub.trialenddate)}</span>,
            exportValue: (sub) => formatCsvDate(sub.trialenddate),
        },
        {
            key: "graceenddate",
            label: labels.graceenddate,
            render: (sub) => <span className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{formatDate(sub.graceenddate)}</span>,
            exportValue: (sub) => formatCsvDate(sub.graceenddate),
        },
        {
            key: "autorenew",
            label: labels.autorenew,
            render: (sub) => {
                const label = sub.autorenew ? labels.autorenew_enabled : labels.autorenew_disabled;
                return (
                    <span className={mcClasses("mc-cell-nowrap")} title={label}>
                        <i
                            className={sub.autorenew ? "bi bi-check-circle text-success" : "bi bi-dash-circle text-muted"}
                            aria-hidden="true"
                        />
                        <span className="sr-only">{label}</span>
                    </span>
                );
            },
            exportValue: (sub) => sub.autorenew ? labels.autorenew_enabled : labels.autorenew_disabled,
        },
        {
            key: "renewalcount",
            label: labels.renewalcount,
            align: "right",
            render: (sub) => formatCount(sub.renewalcount),
            exportValue: (sub) => String(sub.renewalcount),
        },
        {
            key: "accountcredit",
            label: labels.accountcredit,
            align: "right",
            render: (sub) => sub.accountcredit > 0 ? sub.accountcredit.toFixed(2) : DASH,
            exportValue: (sub) => sub.accountcredit > 0 ? sub.accountcredit.toFixed(2) : "",
        },
        {
            key: "created",
            label: labels.created,
            render: (sub) => <span className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{formatDate(sub.timecreated)}</span>,
            exportValue: (sub) => formatCsvDate(sub.timecreated),
        },
        {
            key: "updated",
            label: labels.updated,
            render: (sub) => <span className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{formatDate(sub.timemodified)}</span>,
            exportValue: (sub) => formatCsvDate(sub.timemodified),
        },
    ];
    const allColumnKeys = allColumns.map((column) => column.key);
    const visibleColumns = allColumns.filter((column) => selectedColumns.includes(column.key));

    const openColumns = () => {
        setDraftColumns(selectedColumns);
        setColumnsOpen(true);
    };

    const toggleDraftColumn = (key: SubscriberColumnKey, checked: boolean) => {
        setDraftColumns((current) => {
            if (checked) {
                return allColumnKeys.filter((columnKey) => columnKey === key || current.includes(columnKey));
            }
            const next = current.filter((columnKey) => columnKey !== key);
            return next.length > 0 ? next : current;
        });
    };

    const applyColumns = () => {
        setSelectedColumns(allColumnKeys.filter((key) => draftColumns.includes(key)));
        setColumnsOpen(false);
    };

    const resetColumns = () => {
        setDraftColumns(DEFAULT_SUBSCRIBER_COLUMNS);
        setSelectedColumns(DEFAULT_SUBSCRIBER_COLUMNS);
        setColumnsOpen(false);
    };

    const exportSubscribers = async() => {
        setExporting(true);
        setError("");
        try {
            const batchSize = 100;
            let exportPage = 0;
            let expectedTotal = 0;
            let rows: Subscription[] = [];

            do {
                const result = await callMoodleService<SubscriptionListResponse>(methods.listSubscriptions, {
                    ...filters,
                    page: exportPage,
                    perpage: batchSize,
                });
                rows = [...rows, ...result.items];
                expectedTotal = result.total;
                exportPage += 1;

                if (result.items.length === 0) {
                    break;
                }
            } while (rows.length < expectedTotal);

            const lines = [
                visibleColumns.map((column) => csvCell(column.label)).join(","),
                ...rows.map((sub) => visibleColumns.map((column) => csvCell(column.exportValue(sub))).join(",")),
            ];
            const blob = new Blob([`\uFEFF${lines.join("\r\n")}`], {type: "text/csv;charset=utf-8"});
            const url = window.URL.createObjectURL(blob);
            const anchor = document.createElement("a");
            anchor.href = url;
            anchor.download = `moderncommerce-subscribers-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            window.URL.revokeObjectURL(url);
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setExporting(false);
        }
    };

    if (detail) {
        const sub = detail.subscription;
        return (
            <section className={mcClasses("mc-product-form")} aria-label={labels.subscriptiondetails}>
                <div className={mcClasses("mc-product-form__head")}>
                    <div>
                        <h3>{sub.userfullname}</h3>
                        <p className={mcClasses("mc-cell-muted small mb-0")}>{sub.useremail}</p>
                    </div>
                    <button className={mcClasses("mc-button mc-btn-soft")} onClick={() => setDetail(null)} type="button">{labels.backtolist}</button>
                </div>

                {error && <ErrorAlert message={error} />}

                <div className={mcClasses("mc-product-form__section")}>
                    <div className="row g-3">
                        <div className="col-6 col-md-3"><div className={mcClasses("mc-field-label")}>{labels.currentplan}</div><div>{sub.planname}</div></div>
                        <div className="col-6 col-md-3">
                            <div className={mcClasses("mc-field-label")}>{labels.status}</div>
                            <McBadge variant={subscriptionStatusClass(sub.status)} tone="soft" dot>{statusLabel(sub.status)}</McBadge>
                        </div>
                        <div className="col-6 col-md-3"><div className={mcClasses("mc-field-label")}>{labels.startdate}</div><div>{formatDate(sub.startdate)}</div></div>
                        <div className="col-6 col-md-3"><div className={mcClasses("mc-field-label")}>{labels.enddate}</div><div>{formatDate(sub.enddate)}</div></div>
                        <div className="col-6 col-md-3"><div className={mcClasses("mc-field-label")}>{labels.autorenew}</div><div>{sub.autorenew ? labels.autorenew_enabled : labels.autorenew_disabled}</div></div>
                        <div className="col-6 col-md-3"><div className={mcClasses("mc-field-label")}>{labels.renewalcount}</div><div>{formatCount(sub.renewalcount)}</div></div>
                        {sub.trialenddate > 0 && <div className="col-6 col-md-3"><div className={mcClasses("mc-field-label")}>{labels.trialenddate}</div><div>{formatDate(sub.trialenddate)}</div></div>}
                        {sub.accountcredit > 0 && <div className="col-6 col-md-3"><div className={mcClasses("mc-field-label")}>{labels.accountcredit}</div><div>{sub.accountcredit.toFixed(2)}</div></div>}
                    </div>
                </div>

                <div className={mcClasses("mc-product-form__section")}>
                    <h4>{labels.adminactions}</h4>
                    <div className="d-flex flex-wrap gap-2">
                        {(sub.status === "active" || sub.status === "trial" || sub.status === "grace") && (
                            <button className={mcClasses("mc-button btn-mc-danger")} disabled={busyId === sub.id} onClick={() => runAction(sub, "cancel", labels.confirmcancel)} type="button">{labels.action_cancel}</button>
                        )}
                        {(sub.status === "cancelled" || sub.status === "expired" || sub.status === "suspended") && (
                            <button className={mcClasses("mc-button btn-mc-primary")} disabled={busyId === sub.id} onClick={() => runAction(sub, "reactivate", labels.confirmreactivate)} type="button">{labels.action_reactivate}</button>
                        )}
                        {sub.status === "active" && (
                            <button className={mcClasses("mc-button mc-btn-soft")} disabled={busyId === sub.id} onClick={() => runAction(sub, "suspend")} type="button">{labels.action_suspend}</button>
                        )}
                        <button className={mcClasses("mc-button mc-btn-soft")} disabled={busyId === sub.id} onClick={() => runAction(sub, "renew", labels.confirmrenew)} type="button">{labels.action_renew}</button>
                        {sub.autorenew ? (
                            <button className={mcClasses("mc-button mc-btn-soft")} disabled={busyId === sub.id} onClick={() => runAction(sub, "autorenew_off")} type="button">{labels.disableautorenew}</button>
                        ) : (
                            <button className={mcClasses("mc-button mc-btn-soft")} disabled={busyId === sub.id} onClick={() => runAction(sub, "autorenew_on")} type="button">{labels.enableautorenew}</button>
                        )}
                    </div>
                </div>

                <div className={mcClasses("mc-product-form__section")}>
                    <h4>{labels.coursesincluded}</h4>
                    {detailLoading && <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>}
                    {!detailLoading && detail.access.length === 0 && <p className={mcClasses("mc-cell-muted small")}>{labels.nocourseaccess}</p>}
                    {detail.access.length > 0 && (
                        <ul className="list-unstyled mb-0">
                            {detail.access.map((entry) => (
                                <li key={entry.id} className="d-flex justify-content-between border-bottom py-1">
                                    <span>{entry.coursename}</span>
                                    <span className={mcClasses("mc-cell-muted small")}>{entry.expiresat > 0 ? `${labels.expires}: ${formatDate(entry.expiresat)}` : labels.fullaccess}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className={mcClasses("mc-product-form__section")}>
                    <h4>{labels.subscriptionhistory}</h4>
                    {!detailLoading && detail.history.length === 0 && <p className={mcClasses("mc-cell-muted small")}>{labels.nosubscriptionhistory}</p>}
                    {detail.history.length > 0 && (
                        <McTableFrame>
                                <table
                                    className={mcClasses("table mc-table mc-product-table mb-0")}
                                    aria-label={labels.subscriptionhistory}
                                >
                                    <thead>
                                        <tr>
                                            <th scope="col">{labels.date}</th>
                                            <th scope="col">{labels.action}</th>
                                            <th scope="col" className="text-end">{labels.amount}</th>
                                            <th scope="col">{labels.notes}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {detail.history.map((entry) => (
                                            <tr key={entry.id}>
                                                <td className={mcClasses("mc-cell-nowrap")}>{formatDate(entry.timecreated)}</td>
                                                <td><McBadge variant="neutral" tone="soft">{entry.action}</McBadge></td>
                                                <td className="text-end">{entry.amountpaid > 0 ? entry.amountpaid.toFixed(2) : DASH}</td>
                                                <td className={mcClasses("mc-cell-muted small")}>{entry.notes}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                        </McTableFrame>
                    )}
                </div>
            </section>
        );
    }

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.subscribers}>
            {error && <ErrorAlert message={error} />}

            {stats && (
                <div className={mcClasses("mc-stat-strip")} aria-label={labels.subscribers}>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--primary")}>
                        <i className="bi bi-people mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.totalsubscribers}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.total)}</strong>
                        </div>
                        <i className="bi bi-people mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                        <i className="bi bi-check-circle mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.status_active}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.active)}</strong>
                        </div>
                        <i className="bi bi-check-circle mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--info")}>
                        <i className="bi bi-hourglass-split mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.status_trial}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.trial)}</strong>
                        </div>
                        <i className="bi bi-hourglass-split mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--neutral")}>
                        <i className="bi bi-x-circle mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.status_cancelled}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.cancelled)}</strong>
                        </div>
                        <i className="bi bi-x-circle mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                </div>
            )}

            <McTableCard
                title={<span className={mcClasses("mc-card-title")}>{labels.subscribers}</span>}
                toolbar={(
                        <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                            <div className={mcClasses("mc-product-toolbar__search")}>
                                <label className={mcClasses("mc-filter-label")} htmlFor="mc-subs-search">
                                    {labels.search}
                                </label>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    id="mc-subs-search"
                                    onChange={(event) => setSearchInput(event.target.value)}
                                    placeholder={labels.searchsubscribers}
                                    type="search"
                                    value={searchInput}
                                />
                            </div>
                            <label className={mcClasses("mc-product-toolbar__field")}>
                                <span className={mcClasses("mc-filter-label")}>{labels.plan}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    onChange={(event) => updateFilters({planid: Number(event.target.value) || 0})}
                                    value={filters.planid}
                                >
                                    <option value="0">{labels.allplans}</option>
                                    {plans.map((plan) => (
                                        <option key={plan.id} value={plan.id}>{plan.name}</option>
                                    ))}
                                </select>
                            </label>
                            <label className={mcClasses("mc-product-toolbar__field")}>
                                <span className={mcClasses("mc-filter-label")}>{labels.status}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    onChange={(event) => updateFilters({status: event.target.value})}
                                    value={filters.status}
                                >
                                    <option value="">{labels.allstatuses}</option>
                                    {subscriptionStatusOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </label>
                            <label className={mcClasses("mc-product-toolbar__field")}>
                                <span className={mcClasses("mc-filter-label")}>{labels.billingcycle}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    onChange={(event) => updateFilters({billingcycle: event.target.value})}
                                    value={filters.billingcycle}
                                >
                                    <option value="">{labels.allcycles}</option>
                                    {billingOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </label>
                            <label className={mcClasses("mc-table-design-page-size")}>
                                <span className={mcClasses("mc-filter-label")}>{labels.perpage}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    onChange={(event) => updateFilters({perpage: Number(event.target.value) || 10})}
                                    value={filters.perpage}
                                >
                                    {perPageOptions.map((option) => (
                                        <option key={option} value={option}>{option}</option>
                                    ))}
                                </select>
                            </label>
                            <div className={mcClasses("mc-table-filter__actions")}>
                                <button className={mcClasses("mc-button mc-btn-soft")} onClick={openColumns} type="button">
                                    <i className="bi bi-layout-three-columns" aria-hidden="true" />
                                    {labels.showcolumns}
                                </button>
                                <McButton className={mcClasses("btn-mc-primary")} loading={exporting} loadingLabel={labels.loading} onClick={exportSubscribers} type="button">
                                    <i className="bi bi-download" aria-hidden="true" />
                                    {labels.exportsubscribers}
                                </McButton>
                            </div>
                        </div>
                )}
                footer={(
                    <McTableFooter
                        summary={(
                            <span>
                                {labels.showing} {formatCount(visibleFrom)}-{formatCount(visibleTo)} / {formatCount(total)}
                            </span>
                        )}
                        pagination={(
                            <McTablePagination
                                previousLabel={labels.previous}
                                nextLabel={labels.next}
                                pageLabel={labels.page}
                                page={filters.page + 1}
                                totalPages={totalPages}
                                previousDisabled={loading || filters.page <= 0}
                                nextDisabled={loading || filters.page + 1 >= totalPages}
                                onPrevious={() => updateFilters({page: Math.max(0, filters.page - 1)})}
                                onNext={() => updateFilters({page: filters.page + 1})}
                            />
                        )}
                    />
                )}
            >
                        <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.subscribers}>
                            <thead>
                                <tr>
                                    {visibleColumns.map((column) => (
                                        <th
                                            className={column.align === "right" ? "text-end" : ""}
                                            key={column.key}
                                            scope="col"
                                        >
                                            {column.label}
                                        </th>
                                    ))}
                                    <th scope="col" className="text-end">{labels.actions}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {!loading && data?.items.length === 0 && (
                                    <tr>
                                        <td colSpan={visibleColumns.length + 1}>
                                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                <span className={mcClasses("mc-empty__icon")}>
                                                    <i className="bi bi-people" aria-hidden="true" />
                                                </span>
                                                <p className={mcClasses("mc-empty__title")}>
                                                    {total === 0 ? labels.nosubscribers : labels.noresults}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {data?.items.map((sub) => (
                                    <tr key={sub.id}>
                                        {visibleColumns.map((column) => (
                                            <td
                                                className={column.align === "right" ? "text-end" : ""}
                                                key={column.key}
                                            >
                                                {column.render(sub)}
                                            </td>
                                        ))}
                                        <td className="text-end">
                                            <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                                <McTableActionMenu
                                                    label={`${labels.actions}: ${sub.userfullname}`}
                                                    disabled={busyId === sub.id}
                                                    items={[
                                                        {
                                                            key: "view",
                                                            label: labels.view,
                                                            icon: "bi bi-eye",
                                                            onClick: () => openDetail(sub),
                                                        },
                                                        ...(
                                                            sub.status === "active" || sub.status === "trial" || sub.status === "grace"
                                                                ? [{
                                                                    key: "cancel",
                                                                    label: labels.action_cancel,
                                                                    icon: "bi bi-x-lg",
                                                                    danger: true,
                                                                    onClick: () => void runAction(sub, "cancel", labels.confirmcancel),
                                                                }]
                                                                : []
                                                        ),
                                                        ...(
                                                            sub.status === "cancelled"
                                                                || sub.status === "expired"
                                                                || sub.status === "suspended"
                                                                ? [{
                                                                    key: "reactivate",
                                                                    label: labels.action_reactivate,
                                                                    icon: "bi bi-arrow-clockwise",
                                                                    onClick: () => void runAction(sub, "reactivate", labels.confirmreactivate),
                                                                }]
                                                                : []
                                                        ),
                                                    ]}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {loading && (
                                    <tr>
                                        <td colSpan={visibleColumns.length + 1}>
                                            <div className={mcClasses("mc-product-admin__loading")}>
                                                {labels.loading}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
            </McTableCard>

            {columnsOpen && (
                <McDrawer
                    title={labels.reportcolumns || labels.showcolumns}
                    subtitle={labels.reportcolumnsdesc}
                    onClose={() => setColumnsOpen(false)}
                    closeLabel={labels.close}
                    footer={(
                        <>
                            <McButton variant="primary" onClick={applyColumns} type="button">
                                {labels.applycolumns}
                            </McButton>
                            <button className={mcClasses("mc-button mc-btn-soft")} onClick={resetColumns} type="button">
                                {labels.resetcolumns}
                            </button>
                            <button className={mcClasses("mc-button")} onClick={() => setColumnsOpen(false)} type="button">
                                {labels.cancel}
                            </button>
                        </>
                    )}
                >
                    <div className={mcClasses("mc-report-column-list")}>
                        {allColumns.map((column) => (
                            <label className={mcClasses("mc-report-column-list__item")} key={column.key}>
                                <input
                                    checked={draftColumns.includes(column.key)}
                                    disabled={draftColumns.length === 1 && draftColumns.includes(column.key)}
                                    onChange={(event) => toggleDraftColumn(column.key, event.target.checked)}
                                    type="checkbox"
                                />
                                <span>{column.label}</span>
                            </label>
                        ))}
                    </div>
                </McDrawer>
            )}
        </section>
    );
}

// --- Root -----------------------------------------------------------------

export default function SubscriptionsAdmin({
    view,
    methods,
    statusOptions,
    billingOptions,
    subscriptionStatusOptions,
    iconOptions = [],
    perPageOptions,
    labels,
}: SubscriptionsAdminProps) {
    useModernCommerceClassSync();
    const fixedView = isTabKey(view);
    const [active, setActive] = useState<TabKey>(fixedView ? view : "plans");
    const [globalReload, setGlobalReload] = useState(0);
    const currentView = fixedView ? view : active;

    useEffect(() => {
        const refreshButton = document.getElementById("moderncommerce-subscriptions-refresh");
        const refresh = () => setGlobalReload((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);
        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    useEffect(() => {
        if (fixedView && active !== view) {
            setActive(view);
        }
    }, [active, fixedView, view]);

    const tabs = useMemo<Array<{key: TabKey; label: string; icon: string}>>(() => [
        {key: "plans", label: labels.plans, icon: "bi-card-list"},
        {key: "features", label: labels.featurematrix, icon: "bi-grid-3x3-gap"},
        {key: "subscribers", label: labels.subscribers, icon: "bi-people"},
    ], [labels]);

    return (
        <div className={mcClasses("mc-subscriptions-admin")}>
            {!fixedView && <div className={mcClasses("mc-action-group mb-4")} role="tablist" aria-label={labels.title}>
                {tabs.map((tab) => (
                    <button
                        aria-selected={active === tab.key ? "true" : "false"}
                        className={mcClasses(active === tab.key ? "mc-button btn-mc-primary" : "mc-button mc-btn-soft")}
                        key={tab.key}
                        onClick={() => setActive(tab.key)}
                        role="tab"
                        type="button"
                    >
                        <i className={`bi ${tab.icon}`} aria-hidden="true" /> {tab.label}
                    </button>
                ))}
            </div>}

            {currentView === "plans" && (
                <PlansTab methods={methods} statusOptions={statusOptions} billingOptions={billingOptions} perPageOptions={perPageOptions} labels={labels} globalReload={globalReload} />
            )}
            {currentView === "features" && (
                <FeaturesTab
                    methods={methods}
                    labels={labels}
                    iconOptions={iconOptions}
                    globalReload={globalReload}
                />
            )}
            {currentView === "subscribers" && (
                <SubscribersTab
                    methods={methods}
                    billingOptions={billingOptions}
                    subscriptionStatusOptions={subscriptionStatusOptions}
                    perPageOptions={perPageOptions}
                    labels={labels}
                    globalReload={globalReload}
                />
            )}
        </div>
    );
}

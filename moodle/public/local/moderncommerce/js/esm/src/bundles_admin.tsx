// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin bundles/programs builder for Modern Commerce.
 *
 * @module     local_moderncommerce/bundles_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef, useState} from "react";
import type {FormEvent} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {McTableActionMenu, McTableCard, McTableFooter, McTableFrame, McTablePagination} from "./table_components";
import {confirmDialog} from "./modal";
import {McDrawer} from "./drawer";
import {FieldError} from "./form";
import {
    afterField, focusFirstError, lteField, maxLength, min, normaliseServerErrors, number, required, runRules,
} from "./validation";
import type {FieldErrorMap} from "./validation";

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

type Currency = {
    code: string;
    symbol: string;
    position: string;
    decimals: number;
};

type BoolChange = (value: boolean) => void;
type StringChange = (value: string) => void;

type Filters = {
    search: string;
    status: string;
    type: string;
    page: number;
    perpage: number;
};

type Bundle = {
    id: number;
    name: string;
    isprogram: boolean;
    typelabel: string;
    coursecount: number;
    status: string;
    statuslabel: string;
    statusclass: string;
    onsale: boolean;
    displayprice: string;
    displaysaleprice: string;
    featured: boolean;
    visible: boolean;
    enrollmentcount: number;
    editurl: string;
    advancedurl: string;
};

type Stats = {
    total: number;
    active: number;
    bundles: number;
    programs: number;
};

type ListResponse = {
    items: Bundle[];
    total: number;
    page: number;
    perpage: number;
    stats: Stats;
};

type SelectedCourse = {
    courseid: number;
    fullname: string;
    shortname: string;
};

type Savings = {
    total: number;
    bundle: number;
    savings: number;
    percentage: number;
    displaytotal: string;
    displaysavings: string;
};

type BundleDetail = {
    id: number;
    name: string;
    shortdescription: string;
    description: string;
    isprogram: boolean;
    status: string;
    visible: boolean;
    featured: boolean;
    displayorder: number;
    maxenrollment: number;
    primarycategoryid: number;
    price: number;
    saleprice: number;
    salestartdate: number;
    saleenddate: number;
    imageurl: string;
    hasimage: boolean;
    courses: Array<SelectedCourse & {visible: boolean; sortorder: number}>;
    savings: Savings;
};

type CourseOption = {
    id: number;
    fullname: string;
    shortname: string;
};

type SaveResponse = {
    success: boolean;
    bundleid: number;
    message: string;
    errors?: Array<{field?: string; name?: string; message: string}>;
};

type BundleForm = {
    id: number;
    name: string;
    isprogram: boolean;
    shortdescription: string;
    description: string;
    status: string;
    visible: boolean;
    featured: boolean;
    displayorder: string;
    maxenrollment: string;
    categoryid: string;
    price: string;
    saleenabled: boolean;
    saleprice: string;
    salestartdate: string;
    saleenddate: string;
    imageurl: string;
    hasimage: boolean;
    imagedata: string;
    imagefilename: string;
    imagemimetype: string;
    imageremoved: boolean;
    courses: SelectedCourse[];
};

type BundlesAdminProps = {
    listMethodName: string;
    getMethodName: string;
    saveMethodName: string;
    saveImageMethodName: string;
    archiveMethodName: string;
    searchCoursesMethodName: string;
    openBundleId: number;
    advancedUrlBase: string;
    statusOptions: SelectOption[];
    categoryOptions: SelectOption[];
    perPageOptions: number[];
    currency: Currency;
    labels: Labels;
};

const defaultFilters: Filters = {
    search: "",
    status: "",
    type: "",
    page: 0,
    perpage: 10,
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

const formatMoney = (amount: number, currency: Currency): string => {
    const value = amount.toFixed(currency.decimals);
    return currency.position === "after" ? `${value} ${currency.symbol}` : `${currency.symbol}${value}`;
};

const computeDiscountPct = (regular: number, sale: number): number => {
    return regular > 0 && sale < regular ? Math.round((1 - sale / regular) * 100) : 0;
};

const computeSavings = (regular: number, sale: number): number => Math.max(0, regular - sale);

const timestampToDateInput = (timestamp: number): string => {
    if (!timestamp) {
        return "";
    }

    const date = new Date(timestamp * 1000);
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${date.getFullYear()}-${month}-${day}`;
};

const dateInputToTimestamp = (value: string): number => {
    if (!value) {
        return 0;
    }

    const timestamp = new Date(`${value}T00:00:00`).getTime();
    return Number.isFinite(timestamp) ? Math.floor(timestamp / 1000) : 0;
};

const toFloat = (value: string): number => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const badgeVariant = (variant: string): McBadgeVariant => {
    const variants: McBadgeVariant[] = ["primary", "secondary", "accent", "success", "warning", "danger", "info", "neutral"];
    return variants.includes(variant as McBadgeVariant) ? variant as McBadgeVariant : "neutral";
};

const emptyForm = (): BundleForm => ({
    id: 0,
    name: "",
    isprogram: false,
    shortdescription: "",
    description: "",
    status: "active",
    visible: true,
    featured: false,
    displayorder: "0",
    maxenrollment: "0",
    categoryid: "0",
    price: "0",
    saleenabled: false,
    saleprice: "0",
    salestartdate: "",
    saleenddate: "",
    imageurl: "",
    hasimage: false,
    imagedata: "",
    imagefilename: "",
    imagemimetype: "",
    imageremoved: false,
    courses: [],
});

const detailToForm = (detail: BundleDetail): BundleForm => ({
    id: detail.id,
    name: detail.name,
    isprogram: detail.isprogram,
    shortdescription: detail.shortdescription,
    description: detail.description,
    status: detail.status,
    visible: detail.visible,
    featured: detail.featured,
    displayorder: String(detail.displayorder),
    maxenrollment: detail.maxenrollment > 0 ? String(detail.maxenrollment) : "0",
    categoryid: String(detail.primarycategoryid || 0),
    price: String(detail.price),
    saleenabled: detail.saleprice > 0,
    saleprice: detail.saleprice > 0 ? String(detail.saleprice) : "0",
    salestartdate: timestampToDateInput(detail.salestartdate ?? 0),
    saleenddate: timestampToDateInput(detail.saleenddate ?? 0),
    imageurl: detail.imageurl ?? "",
    hasimage: Boolean(detail.hasimage),
    imagedata: "",
    imagefilename: "",
    imagemimetype: "",
    imageremoved: false,
    courses: detail.courses.map((course) => ({
        courseid: course.courseid,
        fullname: course.fullname,
        shortname: course.shortname,
    })),
});

export default function BundlesAdmin({
    listMethodName,
    getMethodName,
    saveMethodName,
    saveImageMethodName,
    archiveMethodName,
    searchCoursesMethodName,
    openBundleId,
    advancedUrlBase,
    statusOptions,
    categoryOptions,
    perPageOptions,
    currency,
    labels,
}: BundlesAdminProps) {
    useModernCommerceClassSync();
    const [filters, setFilters] = useState<Filters>(defaultFilters);
    const [searchInput, setSearchInput] = useState("");
    const [data, setData] = useState<ListResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState<BundleForm | null>(null);
    const [formError, setFormError] = useState("");
    const [fieldErrors, setFieldErrors] = useState<FieldErrorMap>({});
    const [savings, setSavings] = useState<Savings | null>(null);
    const [reloadToken, setReloadToken] = useState(0);
    const [courseQuery, setCourseQuery] = useState("");
    const [courseOptions, setCourseOptions] = useState<CourseOption[]>([]);
    const [bootstrapped, setBootstrapped] = useState(false);
    const drawerBodyRef = useRef<HTMLDivElement>(null);

    const openNewBundle = () => {
        setForm(emptyForm());
        setSavings(null);
        setFormError("");
        setFieldErrors({});
    };

    const loadBundle = (id: number) => {
        setFormError("");
        setFieldErrors({});
        void callMoodleService<BundleDetail>(getMethodName, {id})
            .then((detail) => {
                setForm(detailToForm(detail));
                setSavings(detail.savings);
            })
            .catch((caught: unknown) => {
                setError(caught instanceof Error ? caught.message : String(caught));
            });
    };

    // Open the builder once on load when the shell requests a specific bundle.
    useEffect(() => {
        if (bootstrapped) {
            return;
        }
        setBootstrapped(true);
        if (openBundleId === -1) {
            setForm(emptyForm());
            setSavings(null);
        } else if (openBundleId > 0) {
            loadBundle(openBundleId);
        }
    }, [openBundleId, bootstrapped]);

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

        void callMoodleService<ListResponse>(listMethodName, filters)
            .then((result) => {
                if (!cancelled) {
                    setData(result);
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
    }, [filters, listMethodName, reloadToken]);

    useEffect(() => {
        const newButton = document.getElementById("moderncommerce-bundles-new");
        const refreshButton = document.getElementById("moderncommerce-bundles-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        newButton?.addEventListener("click", openNewBundle);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            newButton?.removeEventListener("click", openNewBundle);
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    // Course typeahead search for the builder.
    useEffect(() => {
        if (!form) {
            return;
        }
        const query = courseQuery.trim();
        if (query.length < 2) {
            setCourseOptions([]);
            return;
        }

        let cancelled = false;
        const timer = window.setTimeout(() => {
            void callMoodleService<{items: CourseOption[]}>(searchCoursesMethodName, {query, limit: 20})
                .then((result) => {
                    if (!cancelled) {
                        setCourseOptions(result.items);
                    }
                })
                .catch(() => {
                    if (!cancelled) {
                        setCourseOptions([]);
                    }
                });
        }, 300);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [courseQuery, form, searchCoursesMethodName]);

    // Scroll the save error into view even if the admin scrolled down before saving.
    useEffect(() => {
        if (formError && drawerBodyRef.current) {
            drawerBodyRef.current.scrollTo({top: 0, behavior: "smooth"});
        }
    }, [formError]);

    const total = data?.total ?? 0;
    const stats = data?.stats;
    const totalPages = Math.max(1, Math.ceil(total / filters.perpage));
    const visibleFrom = total === 0 ? 0 : filters.page * filters.perpage + 1;
    const visibleTo = total === 0 ? 0 : Math.min(total, visibleFrom + (data?.items.length ?? 0) - 1);

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({...current, ...changes, page: changes.page ?? 0}));
    };

    const updateForm = (changes: Partial<BundleForm>) => {
        setForm((current) => current ? {...current, ...changes} : current);
    };

    const closeForm = () => {
        setForm(null);
        setSavings(null);
        setCourseQuery("");
        setCourseOptions([]);
        setFormError("");
    };

    const addCourse = (option: CourseOption) => {
        setForm((current) => {
            if (!current || current.courses.some((course) => course.courseid === option.id)) {
                return current;
            }

            return {
                ...current,
                courses: [...current.courses, {courseid: option.id, fullname: option.fullname, shortname: option.shortname}],
            };
        });
        setCourseQuery("");
        setCourseOptions([]);
    };

    const removeCourse = (courseid: number) => {
        updateForm({courses: form?.courses.filter((course) => course.courseid !== courseid) ?? []});
    };

    const moveCourse = (index: number, delta: number) => {
        if (!form) {
            return;
        }
        const next = [...form.courses];
        const target = index + delta;
        if (target < 0 || target >= next.length) {
            return;
        }
        [next[index], next[target]] = [next[target], next[index]];
        updateForm({courses: next});
    };

    const onImageFile = (file: File | undefined) => {
        if (!file) {
            return;
        }
        if (!file.type.startsWith("image/")) {
            setFormError(labels.imageinvalid);
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            updateForm({
                imagedata: String(reader.result ?? ""),
                imagefilename: file.name,
                imagemimetype: file.type,
                imageremoved: false,
            });
        };
        reader.readAsDataURL(file);
    };

    const clearImage = () => {
        updateForm({imagedata: "", imagefilename: "", imagemimetype: "", imageremoved: true});
    };

    const submitForm = async(event?: FormEvent<HTMLFormElement>) => {
        event?.preventDefault();
        if (!form) {
            return;
        }

        const t = (key: string) => labels[key] ?? key;
        // Previously this only checked the name and put the field LABEL in the
        // error slot, so the user saw "Name" rather than a message.
        const found = runRules(form, {
            name: [required(t("error:required")), maxLength(255, t("error:maxlength"))],
            // A bundle defaults to "0", which is a legitimate free bundle, so
            // this checks the value is a number in range rather than present.
            price: [number(t("error:invalidnumber")), min(0, t("error:minvalue"))],
            saleprice: form.saleenabled
                ? [number(t("error:invalidnumber")), min(0, t("error:minvalue")),
                    lteField("price", t("error:salepriceabovebase"))]
                : [],
            saleenddate: form.saleenabled ? afterField("salestartdate", t("error:enddatebeforestart")) : [],
            maxenrollment: min(0, t("error:minvalue")),
            displayorder: min(0, t("error:minvalue")),
        });
        if (form.courses.length === 0) {
            found.courses = t("error:bundleneedscourses");
        }

        setFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setFormError("");
            focusFirstError(found, ["name", "price", "saleprice", "saleenddate", "courses"]);
            return;
        }

        setSaving(true);
        setFormError("");

        try {
            const result = await callMoodleService<SaveResponse>(saveMethodName, {
                id: form.id,
                name: form.name,
                isprogram: form.isprogram,
                shortdescription: form.shortdescription,
                description: form.description,
                status: form.status,
                visible: form.visible,
                featured: form.featured,
                displayorder: Number(form.displayorder) || 0,
                maxenrollment: Number(form.maxenrollment) || 0,
                categoryid: Number(form.categoryid) || 0,
                price: toFloat(form.price),
                saleprice: form.saleenabled ? toFloat(form.saleprice) : 0,
                salestartdate: form.saleenabled ? dateInputToTimestamp(form.salestartdate) : 0,
                saleenddate: form.saleenabled ? dateInputToTimestamp(form.saleenddate) : 0,
                courseids: form.courses.map((course) => course.courseid),
            });

            if (!result.success) {
                const serverErrors = normaliseServerErrors(result.errors);
                if (Object.keys(serverErrors).length > 0) {
                    setFieldErrors(serverErrors);
                    focusFirstError(serverErrors, ["name", "price", "saleprice", "saleenddate", "courses"]);
                } else {
                    setFormError(result.message);
                }
                return;
            }

            // The image is owned by a separate file area, saved once we know the bundle id.
            let imageError = "";
            if (result.bundleid && (form.imagedata || form.imageremoved)) {
                // Moodle PARAM_BASE64 expects PEM formatting: base64 split into 64-char lines.
                const rawBase64 = form.imagedata.includes(",")
                    ? form.imagedata.substring(form.imagedata.indexOf(",") + 1)
                    : "";
                const base64 = rawBase64.replace(/\s+/g, "").match(/.{1,64}/g)?.join("\n") ?? "";
                try {
                    const imageResult = await callMoodleService<SaveResponse>(saveImageMethodName, {
                        bundleid: result.bundleid,
                        deletepicture: form.imageremoved && form.imagedata === "",
                        filename: form.imagefilename,
                        mimetype: form.imagemimetype,
                        filesize: 0,
                        imagecontent: form.imagedata ? base64 : "",
                    });
                    if (!imageResult.success) {
                        imageError = imageResult.message;
                    }
                } catch (caught) {
                    imageError = caught instanceof Error ? caught.message : String(caught);
                }
            }

            // The product saved; if the image step failed, keep the drawer open so the
            // admin sees exactly why (rather than a silent miss) and can retry.
            if (imageError) {
                setReloadToken((current) => current + 1);
                setFormError(imageError);
                return;
            }

            toast.success(result.message);
            closeForm();
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setFormError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const archiveBundle = async(bundle: Bundle) => {
        if (!await confirmDialog({message: labels.archiveconfirm, danger: true})) {
            return;
        }

        setSaving(true);
        setError("");

        try {
            const result = await callMoodleService<{success: boolean; message: string}>(archiveMethodName, {id: bundle.id});
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const renderSwitch = (checked: boolean, onChange: BoolChange, label: string, disabled = false) => (
        <label className={mcClasses("mc-switch")}>
            <input
                checked={checked}
                disabled={disabled}
                onChange={(event) => onChange(event.target.checked)}
                type="checkbox"
            />
            <span className={mcClasses("mc-switch__track")} aria-hidden="true" />
            <span className={mcClasses("mc-switch__thumb")} aria-hidden="true" />
            <span className={mcClasses("mc-switch__label")}>{label}</span>
        </label>
    );

    const renderPriceInput = (
        id: string,
        value: string,
        onChange: StringChange,
        disabled = false,
        options: {name?: string; error?: string; required?: boolean} = {}
    ) => {
        const after = currency.position === "after";
        const {name, error, required: isRequired} = options;
        return (
            <>
                <div className={mcClasses(after ? "mc-price-input mc-price-input--after" : "mc-price-input")}>
                    <span
                        className={mcClasses(after ? "mc-price-input__symbol mc-price-input__symbol--after" : "mc-price-input__symbol")}
                        aria-hidden="true"
                    >
                        {currency.symbol}
                    </span>
                    <input
                        aria-describedby={error ? `${id}-error` : undefined}
                        aria-invalid={error ? true : undefined}
                        aria-required={isRequired ? true : undefined}
                        className={mcClasses("mc-form-control", error ? "mc-field-invalid" : "")}
                        disabled={disabled}
                        id={id}
                        min="0"
                        name={name}
                        onChange={(event) => onChange(event.target.value)}
                        step="0.01"
                        type="number"
                        value={value}
                    />
                </div>
                <FieldError id={`${id}-error`} message={error} />
            </>
        );
    };

    const renderBundleActions = (bundle: Bundle) => (
        <div className={mcClasses("mc-table-design__actions justify-content-end")}>
            <McTableActionMenu
                label={`${labels.actions}: ${bundle.name}`}
                items={[
                    {
                        key: "edit",
                        label: labels.edit,
                        icon: "bi bi-pencil",
                        disabled: saving,
                        onClick: () => loadBundle(bundle.id),
                    },
                    {
                        key: "advanced",
                        label: labels.advanced,
                        icon: "bi bi-sliders",
                        href: `${advancedUrlBase}?bundleid=${bundle.id}`,
                    },
                    {
                        key: "archive",
                        label: labels.archive,
                        icon: "bi bi-archive",
                        danger: true,
                        disabled: saving || bundle.status === "archived",
                        onClick: () => void archiveBundle(bundle),
                    },
                ]}
            />
        </div>
    );

    const renderDrawer = () => {
        if (!form) {
            return null;
        }

        const baseAmount = toFloat(form.price);
        const saleAmount = toFloat(form.saleprice);
        const previewFree = baseAmount <= 0;
        const saleActive = form.saleenabled && saleAmount > 0 && saleAmount < baseAmount;
        const discountPct = computeDiscountPct(baseAmount, saleAmount);
        const liveAmount = saleActive ? saleAmount : baseAmount;

        return (
            <McDrawer
                title={form.id > 0 ? labels.editbundle : labels.createbundle}
                subtitle={form.id > 0 && form.name ? form.name : undefined}
                onClose={closeForm}
                closeLabel={labels.close}
                disableClose={saving}
                bodyRef={drawerBodyRef}
                footer={(
                    <>
                        <McButton
                            className={mcClasses("btn-mc-primary")}
                            form="mc-bundle-drawer-form"
                            loading={saving}
                            loadingLabel={labels.saving || "Saving..."}
                            type="submit"
                        >
                            {labels.save}
                        </McButton>
                        <button className={mcClasses("mc-button btn-mc-secondary")} disabled={saving} onClick={closeForm} type="button">
                            {labels.cancel}
                        </button>
                    </>
                )}
            >
                        {formError && (
                            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                                <div className={mcClasses("mc-alert__body")}>{formError}</div>
                            </div>
                        )}

                        <form id="mc-bundle-drawer-form" noValidate onSubmit={(event) => void submitForm(event)}>

                        <div className={mcClasses("mc-form-section")}>
                            <div className={mcClasses("mc-form-section__header")}>
                                <h4 className={mcClasses("mc-form-section__title")}>{labels.bundleinfo}</h4>
                            </div>
                            <div className={mcClasses("mc-form-section__body")}>
                                <div className={mcClasses("mc-field")}>
                                    <span className={mcClasses("mc-field-label")}>{labels.image}</span>
                                    {(() => {
                                        const previewSrc = form.imagedata
                                            || (form.hasimage && !form.imageremoved ? form.imageurl : "");
                                        const hasPreview = previewSrc !== "";
                                        return (
                                            <div className={mcClasses("mc-image-upload")}>
                                                <div className={mcClasses("mc-image-upload__preview")}>
                                                    {hasPreview
                                                        ? <img src={previewSrc} alt="" />
                                                        : <span className={mcClasses("mc-image-upload__placeholder")}><i className="bi bi-image" aria-hidden="true" /></span>}
                                                </div>
                                                <div className={mcClasses("mc-image-upload__controls")}>
                                                    <div className={mcClasses("d-flex gap-2")}>
                                                        <label className={mcClasses("mc-button mc-btn-soft mc-image-upload__btn")}>
                                                            <i className="bi bi-upload me-1" aria-hidden="true" />
                                                            {hasPreview ? labels.changeimage : labels.uploadimage}
                                                            <input
                                                                accept="image/*"
                                                                onChange={(event) => onImageFile(event.target.files?.[0])}
                                                                type="file"
                                                            />
                                                        </label>
                                                        {hasPreview && (
                                                            <button className={mcClasses("mc-button mc-btn-soft")} onClick={clearImage} type="button">
                                                                {labels.removeimage}
                                                            </button>
                                                        )}
                                                    </div>
                                                    <div className={mcClasses("mc-field-help")}>{labels.imagehelp}</div>
                                                </div>
                                            </div>
                                        );
                                    })()}
                                </div>
                                <div className={mcClasses("mc-product-form__grid")}>
                                    <label className={mcClasses("mc-product-form__wide")} htmlFor="mc-bundle-name">
                                        <span>
                                            {labels.name}
                                            <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                        </span>
                                        <input
                                            autoFocus
                                            aria-describedby={fieldErrors.name ? "mc-bundle-name-error" : undefined}
                                            aria-invalid={fieldErrors.name ? true : undefined}
                                            aria-required="true"
                                            className={mcClasses("mc-form-control", fieldErrors.name ? "mc-field-invalid" : "")}
                                            id="mc-bundle-name"
                                            maxLength={255}
                                            name="name"
                                            onChange={(event) => updateForm({name: event.target.value})}
                                            required
                                            type="text"
                                            value={form.name}
                                        />
                                        <FieldError id="mc-bundle-name-error" message={fieldErrors.name} />
                                    </label>
                                    <label>
                                        <span>{labels.type}</span>
                                        <select
                                            className={mcClasses("mc-select")}
                                            onChange={(event) => updateForm({isprogram: event.target.value === "program"})}
                                            value={form.isprogram ? "program" : "bundle"}
                                        >
                                            <option value="bundle">{labels.bundle}</option>
                                            <option value="program">{labels.program}</option>
                                        </select>
                                    </label>
                                    <label>
                                        <span>{labels.status}</span>
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
                                    <label>
                                        <span>{labels.category}</span>
                                        <select
                                            className={mcClasses("mc-select")}
                                            onChange={(event) => updateForm({categoryid: event.target.value})}
                                            value={form.categoryid}
                                        >
                                            {categoryOptions.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                    </label>
                                    <label>
                                        <span>{labels.displayorder}</span>
                                        <input
                                            className={mcClasses("mc-form-control")}
                                            onChange={(event) => updateForm({displayorder: event.target.value})}
                                            type="number"
                                            value={form.displayorder}
                                        />
                                    </label>
                                    <label>
                                        <span>{labels.maxenrollment}</span>
                                        <input
                                            className={mcClasses("mc-form-control")}
                                            min="0"
                                            onChange={(event) => updateForm({maxenrollment: event.target.value})}
                                            type="number"
                                            value={form.maxenrollment}
                                        />
                                    </label>
                                    <label className={mcClasses("mc-product-form__wide")}>
                                        <span>{labels.shortdescription}</span>
                                        <input
                                            className={mcClasses("mc-form-control")}
                                            onChange={(event) => updateForm({shortdescription: event.target.value})}
                                            type="text"
                                            value={form.shortdescription}
                                        />
                                    </label>
                                    <label className={mcClasses("mc-product-form__wide")}>
                                        <span>{labels.description}</span>
                                        <textarea
                                            className={mcClasses("mc-form-control")}
                                            onChange={(event) => updateForm({description: event.target.value})}
                                            rows={3}
                                            value={form.description}
                                        />
                                    </label>
                                </div>
                                <div className={mcClasses("mc-product-form__checks mt-3")}>
                                    {renderSwitch(form.visible, (value) => updateForm({visible: value}), labels.visible)}
                                    {renderSwitch(form.featured, (value) => updateForm({featured: value}), labels.featured)}
                                </div>
                            </div>
                        </div>

                        <div className={mcClasses("mc-form-section")}>
                            <div className={mcClasses("mc-form-section__header")}>
                                <h4 className={mcClasses("mc-form-section__title")}>{labels.pricingsettings}</h4>
                            </div>
                            <div className={mcClasses("mc-form-section__body")}>
                                <div className={mcClasses("mc-field")}>
                                    <label className={mcClasses("mc-field-label")} htmlFor="mc-bundle-price">
                                        {labels.price}
                                    </label>
                                    {renderPriceInput(
                                        "mc-bundle-price",
                                        form.price,
                                        (value) => updateForm({price: value}),
                                        false,
                                        {name: "price", error: fieldErrors.price}
                                    )}
                                </div>

                                <div className={mcClasses("mc-field")}>
                                    {renderSwitch(
                                        form.saleenabled,
                                        (value) => updateForm({saleenabled: value, saleprice: value ? form.saleprice : "0"}),
                                        labels.putonsale
                                    )}
                                </div>

                                {form.saleenabled && (
                                    <div className={mcClasses("mc-sale-panel")}>
                                        <div className={mcClasses("mc-product-form__grid")}>
                                            <label htmlFor="mc-bundle-saleprice">
                                                <span>{labels.saleprice}</span>
                                                {renderPriceInput(
                                                    "mc-bundle-saleprice",
                                                    form.saleprice,
                                                    (value) => updateForm({saleprice: value}),
                                                    false,
                                                    {name: "saleprice", error: fieldErrors.saleprice}
                                                )}
                                            </label>
                                            <label htmlFor="mc-bundle-salestartdate">
                                                <span>{labels.startdate}</span>
                                                <input
                                                    className={mcClasses("mc-form-control")}
                                                    id="mc-bundle-salestartdate"
                                                    name="salestartdate"
                                                    onChange={(event) => updateForm({salestartdate: event.target.value})}
                                                    type="date"
                                                    value={form.salestartdate}
                                                />
                                            </label>
                                            <label htmlFor="mc-bundle-saleenddate">
                                                <span>{labels.enddate}</span>
                                                <input
                                                    aria-describedby={fieldErrors.saleenddate
                                                        ? "mc-bundle-saleenddate-error" : undefined}
                                                    aria-invalid={fieldErrors.saleenddate ? true : undefined}
                                                    className={mcClasses("mc-form-control",
                                                        fieldErrors.saleenddate ? "mc-field-invalid" : "")}
                                                    id="mc-bundle-saleenddate"
                                                    name="saleenddate"
                                                    onChange={(event) => updateForm({saleenddate: event.target.value})}
                                                    type="date"
                                                    value={form.saleenddate}
                                                />
                                                <FieldError
                                                    id="mc-bundle-saleenddate-error"
                                                    message={fieldErrors.saleenddate}
                                                />
                                            </label>
                                        </div>
                                    </div>
                                )}

                                <div className={mcClasses("mc-price-preview")}>
                                    <span className={mcClasses("mc-field-label")}>{labels.pricepreview}</span>
                                    <div className={mcClasses(previewFree ? "mc-price mc-price--free" : "mc-price")}>
                                        {saleActive && (
                                            <span className={mcClasses("mc-price__original")}>{formatMoney(baseAmount, currency)}</span>
                                        )}
                                        <span className={mcClasses("mc-price__main")}>
                                            {previewFree ? labels.freeproduct : formatMoney(liveAmount, currency)}
                                        </span>
                                        {saleActive && (
                                            <span className={mcClasses("mc-price__sale")}>-{discountPct}%</span>
                                        )}
                                    </div>
                                    {form.saleenabled && !saleActive && !previewFree && (
                                        <div className={mcClasses("mc-field-help")}>{labels.nosaleprice}</div>
                                    )}
                                    {saleActive && (
                                        <div className={mcClasses("mc-field-help")}>
                                            {labels.yousave.replace("{$a}", formatMoney(computeSavings(baseAmount, saleAmount), currency))}
                                        </div>
                                    )}
                                    {savings && savings.savings > 0 && form.id > 0 && (
                                        <div className={mcClasses("mc-field-help")}>
                                            {labels.savings}: {savings.displaysavings} ({savings.percentage}%)
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className={mcClasses("mc-form-section")}>
                            <div className={mcClasses("mc-form-section__header")}>
                                <h4 className={mcClasses("mc-form-section__title")}>{labels.coursessettings}</h4>
                            </div>
                            <div className={mcClasses("mc-form-section__body")}>
                                <div className={mcClasses("mc-course-picker")}>
                                    <label htmlFor="mc-bundle-course-input">
                                        <span>
                                            {labels.selectcourses}
                                            <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                        </span>
                                        <input
                                            autoComplete="off"
                                            // The bundle needs at least one course, so the error is
                                            // reported against the picker the user types into.
                                            aria-describedby={fieldErrors.courses ? "mc-bundle-courses-error" : undefined}
                                            aria-invalid={fieldErrors.courses ? true : undefined}
                                            aria-required="true"
                                            className={mcClasses("mc-form-control",
                                                fieldErrors.courses ? "mc-field-invalid" : "")}
                                            id="mc-bundle-course-input"
                                            name="courses"
                                            onChange={(event) => setCourseQuery(event.target.value)}
                                            // This is a search box inside the save form: without
                                            // this, Enter would submit the bundle instead of
                                            // filtering the course list.
                                            onKeyDown={(event) => {
                                                if (event.key === "Enter") {
                                                    event.preventDefault();
                                                }
                                            }}
                                            placeholder={labels.searchcoursesplaceholder}
                                            type="search"
                                            value={courseQuery}
                                        />
                                        <FieldError id="mc-bundle-courses-error" message={fieldErrors.courses} />
                                    </label>
                                    {courseOptions.length > 0 && (
                                        <div className={mcClasses("mc-course-picker__results")} role="listbox">
                                            {courseOptions
                                                .filter((option) => !form.courses.some((course) => course.courseid === option.id))
                                                .map((option) => (
                                                    <button
                                                        className={mcClasses("mc-button mc-course-picker__option")}
                                                        data-mc-button="ghost"
                                                        key={option.id}
                                                        onClick={() => addCourse(option)}
                                                        type="button"
                                                    >
                                                        <span className={mcClasses("mc-course-picker__option-main")}>
                                                            <strong>{option.fullname}</strong>
                                                            <small>{option.shortname}</small>
                                                        </span>
                                                    </button>
                                                ))}
                                        </div>
                                    )}
                                </div>

                                <McTableFrame className="mt-3">
                                    <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.includedcourses}>
                                        <tbody>
                                            {form.courses.length === 0 && (
                                                <tr>
                                                    <td>
                                                        <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                            <p className={mcClasses("mc-empty__title")}>{labels.nocoursesselected}</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )}
                                            {form.courses.map((course, index) => (
                                                <tr key={course.courseid}>
                                                    <td>
                                                        <div className="fw-semibold">{course.fullname}</div>
                                                        <div className={mcClasses("mc-cell-muted")}>{course.shortname}</div>
                                                    </td>
                                                    <td className={mcClasses("text-end mc-cell-nowrap")}>
                                                        <button
                                                            aria-label={labels.reorder}
                                                            className={mcClasses("mc-button mc-btn-soft")}
                                                            disabled={index === 0}
                                                            onClick={() => moveCourse(index, -1)}
                                                            type="button"
                                                        >
                                                            &uarr;
                                                        </button>
                                                        <button
                                                            aria-label={labels.reorder}
                                                            className={mcClasses("mc-button mc-btn-soft ms-1")}
                                                            disabled={index === form.courses.length - 1}
                                                            onClick={() => moveCourse(index, 1)}
                                                            type="button"
                                                        >
                                                            &darr;
                                                        </button>
                                                        <button
                                                            className={mcClasses("mc-button btn-mc-danger ms-1")}
                                                            onClick={() => removeCourse(course.courseid)}
                                                            type="button"
                                                        >
                                                            &times;
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </McTableFrame>
                            </div>
                        </div>

                        {form.id > 0 && (
                            <a
                                className={mcClasses("mc-button mc-btn-soft")}
                                href={`${advancedUrlBase}?bundleid=${form.id}`}
                            >
                                <i className="bi bi-sliders me-1" aria-hidden="true" />
                                {labels.advancedfeatures}
                            </a>
                        )}
                        </form>
            </McDrawer>
        );
    };

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            {stats && (
                <div className={mcClasses("mc-stat-strip")} aria-label={labels.title}>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--primary")}>
                        <i className="bi bi-box-seam mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.total}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.total)}</strong>
                        </div>
                        <i className="bi bi-box-seam mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                        <i className="bi bi-check-circle mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.active}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.active)}</strong>
                        </div>
                        <i className="bi bi-check-circle mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--info")}>
                        <i className="bi bi-boxes mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.bundles}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.bundles)}</strong>
                        </div>
                        <i className="bi bi-boxes mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--warning")}>
                        <i className="bi bi-mortarboard mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.programs}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.programs)}</strong>
                        </div>
                        <i className="bi bi-mortarboard mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                </div>
            )}

            <McTableCard
                title={<h2 className={mcClasses("mc-card-title")}>{labels.title}</h2>}
                actions={(
                    <button
                        className={mcClasses("mc-button btn-mc-primary")}
                        id="moderncommerce-bundles-new"
                        onClick={openNewBundle}
                        type="button"
                    >
                        <i className="bi bi-plus-lg me-1" aria-hidden="true" />
                        {labels.createbundle}
                    </button>
                )}
                toolbar={(
                    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                        <div className={mcClasses("mc-product-toolbar__search")}>
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-bundle-search">
                                {labels.search}
                            </label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-bundle-search"
                                onChange={(event) => setSearchInput(event.target.value)}
                                placeholder={labels.searchplaceholder}
                                type="search"
                                value={searchInput}
                            />
                        </div>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.type}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({type: event.target.value})}
                                value={filters.type}
                            >
                                <option value="">{labels.alltypes}</option>
                                <option value="bundle">{labels.bundle}</option>
                                <option value="program">{labels.program}</option>
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
                                {statusOptions.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                        </label>
                        <label className={mcClasses("mc-product-toolbar__field mc-product-toolbar__field--small")}>
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
                                page={Math.min(filters.page + 1, totalPages)}
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
                <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.title}>
                            <thead>
                                <tr>
                                    <th scope="col">{labels.name}</th>
                                    <th scope="col">{labels.type}</th>
                                    <th scope="col" className="text-end">{labels.coursecount}</th>
                                    <th scope="col" className="text-end">{labels.price}</th>
                                    <th scope="col" className="text-end">{labels.enrollments}</th>
                                    <th scope="col">{labels.status}</th>
                                    <th scope="col" className="text-end">{labels.actions}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {!loading && data?.items.length === 0 && (
                                    <tr>
                                        <td colSpan={7}>
                                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                <span className={mcClasses("mc-empty__icon")}>
                                                    <i className="bi bi-box-seam" aria-hidden="true" />
                                                </span>
                                                <p className={mcClasses("mc-empty__title")}>
                                                    {total === 0 ? labels.nobundles : labels.noresults}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {data?.items.map((bundle) => (
                                    <tr key={bundle.id}>
                                        <td>
                                            <div className="fw-semibold">{bundle.name}</div>
                                            {bundle.featured && (
                                                <McBadge variant="primary" tone="soft">
                                                    {labels.featured}
                                                </McBadge>
                                            )}
                                        </td>
                                        <td>
                                            <McBadge variant="neutral" tone="soft">
                                                {bundle.typelabel}
                                            </McBadge>
                                        </td>
                                        <td className="text-end">{formatCount(bundle.coursecount)}</td>
                                        <td className="text-end">
                                            {bundle.onsale ? (
                                                <>
                                                    <span className={mcClasses("mc-cell-muted text-decoration-line-through me-1")}>
                                                        {bundle.displayprice}
                                                    </span>
                                                    <span className="fw-semibold">{bundle.displaysaleprice}</span>
                                                </>
                                            ) : (
                                                <span className="fw-semibold">{bundle.displayprice}</span>
                                            )}
                                        </td>
                                        <td className="text-end">{formatCount(bundle.enrollmentcount)}</td>
                                        <td>
                                            <McBadge variant={badgeVariant(bundle.statusclass)} tone="soft" dot>
                                                {bundle.statuslabel}
                                            </McBadge>
                                        </td>
                                        <td className="text-end">
                                            {renderBundleActions(bundle)}
                                        </td>
                                    </tr>
                                ))}
                                {loading && (
                                    <tr>
                                        <td colSpan={7}>
                                            <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                </table>
            </McTableCard>

            {renderDrawer()}
        </section>
    );
}

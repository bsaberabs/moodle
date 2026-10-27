// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin coupon list for Modern Commerce.
 *
 * @module     local_moderncommerce/coupons_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useMemo, useRef, useState} from "react";
import type {FormEvent} from "react";
import {mcClasses, sortIconClass, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {McTableActionMenu, McTableCard, McTableFooter, McTableFrame, McTablePagination} from "./table_components";
import {confirmDialog} from "./modal";
import {McDrawer} from "./drawer";
import {FieldError} from "./form";
import {
    afterField, focusFirstError, integer, lteField, maxLength, min, normaliseServerErrors, number, pattern,
    percent, required, runRules, when,
} from "./validation";
import type {FieldErrorMap, WithFieldErrors} from "./validation";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

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

type Filters = {
    search: string;
    status: string;
    discounttype: string;
    page: number;
    perpage: number;
    sort: string;
    direction: "ASC" | "DESC";
};

type Coupon = {
    id: number;
    code: string;
    name: string;
    discounttype: string;
    discounttypelabel: string;
    value: number;
    displayvalue: string;
    maxdiscount: number;
    displaymaxdiscount: string;
    minpurchase: number;
    displayminpurchase: string;
    minitems: number;
    maxuses: number;
    usedcount: number;
    actualuses: number;
    maxusesperuser: number;
    stackable: boolean;
    status: string;
    runtimestatus: string;
    statuslabel: string;
    statusclass: string;
    startdate: number;
    enddate: number;
    timecreated: number;
    timemodified: number;
    discounttotal: number;
    displaydiscounttotal: string;
};

type Stats = {
    totalcoupons: number;
    activecoupons: number;
    scheduledcoupons: number;
    expiredcoupons: number;
    depletedcoupons: number;
    inactivecoupons: number;
    totalredemptions: number;
    totaldiscount: number;
    displaytotaldiscount: string;
};

type CouponsResponse = {
    items: Coupon[];
    total: number;
    page: number;
    perpage: number;
    sort: string;
    direction: "ASC" | "DESC";
    currency: Currency;
    stats: Stats;
};

type SaveResponse = WithFieldErrors & {
    success: boolean;
    couponid: number;
    message: string;
};

type CouponForm = {
    id: number;
    code: string;
    name: string;
    discounttype: string;
    value: string;
    maxdiscount: string;
    minpurchase: string;
    minitems: string;
    maxuses: string;
    maxusesperuser: string;
    stackable: boolean;
    status: string;
    startdate: string;
    enddate: string;
};

type CouponTarget = {
    id: number;
    couponid: number;
    targettype: string;
    targettypelabel: string;
    targetid: number;
    targetvalue: string;
    includemode: string;
    includemodelabel: string;
    displayname: string;
    summary: string;
    timecreated: number;
};

type CouponTargetsResponse = {
    success: boolean;
    message: string;
    couponid: number;
    items: CouponTarget[];
};

type TargetOption = {
    id: number;
    targetvalue: string;
    label: string;
    summary: string;
};

type TargetOptionsResponse = {
    items: TargetOption[];
    total: number;
    query: string;
    limit: number;
    targettype: string;
};

type TargetSaveResponse = {
    success: boolean;
    targetid: number;
    couponid: number;
    message: string;
};

type TargetForm = {
    targettype: string;
    includemode: string;
    targetid: number;
    targetvalue: string;
    label: string;
    summary: string;
    query: string;
};

type Labels = Record<string, string>;

type CouponsAdminProps = {
    methodName: string;
    saveMethodName: string;
    archiveMethodName: string;
    listTargetsMethodName: string;
    saveTargetMethodName: string;
    deleteTargetMethodName: string;
    searchTargetOptionsMethodName: string;
    initialFilters: Partial<Filters>;
    currency: Currency;
    labels: Labels;
    typeOptions: SelectOption[];
    targetTypes: SelectOption[];
    includeModes: SelectOption[];
    statusOptions: SelectOption[];
    editableStatuses: SelectOption[];
    perPageOptions: number[];
};

const defaultFilters: Filters = {
    search: "",
    status: "",
    discounttype: "",
    page: 0,
    perpage: 10,
    sort: "timecreated",
    direction: "DESC",
};

const normaliseFilters = (filters: Partial<Filters>): Filters => ({
    ...defaultFilters,
    ...filters,
    page: Math.max(0, Number(filters.page ?? defaultFilters.page) || 0),
    perpage: Number(filters.perpage ?? defaultFilters.perpage) || defaultFilters.perpage,
    direction: filters.direction === "ASC" ? "ASC" : "DESC",
});

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

const formatCount = (value: number): string => {
    return new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);
};

const formatDate = (timestamp: number): string => {
    if (!timestamp) {
        return "-";
    }

    return new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
        day: "numeric",
        month: "short",
        year: "numeric",
    }).format(new Date(timestamp * 1000));
};

const timestampToDateInput = (timestamp: number): string => {
    if (!timestamp) {
        return "";
    }

    const date = new Date(timestamp * 1000);
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${date.getFullYear()}-${month}-${day}`;
};

const dateInputToTimestamp = (value: string, endOfDay = false): number => {
    if (!value) {
        return 0;
    }

    const time = endOfDay ? "23:59:59" : "00:00:00";
    const timestamp = new Date(`${value}T${time}`).getTime();
    return Number.isFinite(timestamp) ? Math.floor(timestamp / 1000) : 0;
};

const toInt = (value: string): number => {
    const parsed = parseInt(value, 10);
    return Number.isFinite(parsed) ? parsed : 0;
};

const toFloat = (value: string): number => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const badgeVariant = (variant: string): McBadgeVariant => {
    const variants: McBadgeVariant[] = ["primary", "secondary", "accent", "success", "warning", "danger", "info", "neutral"];
    return variants.includes(variant as McBadgeVariant) ? variant as McBadgeVariant : "neutral";
};

const getVisibleRange = (total: number, page: number, perpage: number): {from: number; to: number} => {
    if (total <= 0) {
        return {from: 0, to: 0};
    }

    return {
        from: page * perpage + 1,
        to: Math.min((page + 1) * perpage, total),
    };
};

const emptyForm = (): CouponForm => ({
    id: 0,
    code: "",
    name: "",
    discounttype: "percentage",
    value: "10",
    maxdiscount: "",
    minpurchase: "",
    minitems: "",
    maxuses: "",
    maxusesperuser: "1",
    stackable: false,
    status: "active",
    startdate: "",
    enddate: "",
});

const emptyTargetForm = (targetTypes: SelectOption[]): TargetForm => ({
    targettype: targetTypes[0]?.value || "product",
    includemode: "include",
    targetid: 0,
    targetvalue: "",
    label: "",
    summary: "",
    query: "",
});

const isValueTargetType = (targettype: string): boolean => {
    return targettype === "producttype" || targettype === "sku";
};

const couponToForm = (coupon: Coupon): CouponForm => ({
    id: coupon.id,
    code: coupon.code,
    name: coupon.name,
    discounttype: coupon.discounttype,
    value: String(coupon.value),
    maxdiscount: coupon.maxdiscount > 0 ? String(coupon.maxdiscount) : "",
    minpurchase: coupon.minpurchase > 0 ? String(coupon.minpurchase) : "",
    minitems: coupon.minitems > 0 ? String(coupon.minitems) : "",
    maxuses: coupon.maxuses > 0 ? String(coupon.maxuses) : "",
    maxusesperuser: coupon.maxusesperuser > 0 ? String(coupon.maxusesperuser) : "",
    stackable: coupon.stackable,
    status: coupon.status,
    startdate: timestampToDateInput(coupon.startdate),
    enddate: timestampToDateInput(coupon.enddate),
});

export default function CouponsAdmin({
    methodName,
    saveMethodName,
    archiveMethodName,
    listTargetsMethodName,
    saveTargetMethodName,
    deleteTargetMethodName,
    searchTargetOptionsMethodName,
    initialFilters,
    currency,
    labels,
    typeOptions,
    targetTypes,
    includeModes,
    statusOptions,
    editableStatuses,
    perPageOptions,
}: CouponsAdminProps) {
    useModernCommerceClassSync();
    const [filters, setFilters] = useState<Filters>(() => normaliseFilters(initialFilters));
    const [searchInput, setSearchInput] = useState(filters.search);
    const [data, setData] = useState<CouponsResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const [formError, setFormError] = useState("");
    const [fieldErrors, setFieldErrors] = useState<FieldErrorMap>({});
    const [reloadToken, setReloadToken] = useState(0);
    const [form, setForm] = useState<CouponForm | null>(null);
    const [targets, setTargets] = useState<CouponTarget[]>([]);
    const [targetsLoading, setTargetsLoading] = useState(false);
    const [targetError, setTargetError] = useState("");
    const [targetNotice, setTargetNotice] = useState("");
    const [targetForm, setTargetForm] = useState<TargetForm>(() => emptyTargetForm(targetTypes));
    const [targetOptions, setTargetOptions] = useState<TargetOption[]>([]);
    const [targetSearchLoading, setTargetSearchLoading] = useState(false);
    const [targetReloadToken, setTargetReloadToken] = useState(0);
    const drawerBodyRef = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const timer = window.setTimeout(() => {
            setFilters((current) => {
                if (current.search === searchInput) {
                    return current;
                }

                return {...current, search: searchInput, page: 0};
            });
        }, 350);

        return () => window.clearTimeout(timer);
    }, [searchInput]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<CouponsResponse>(methodName, filters)
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
    }, [filters, methodName, reloadToken]);

    useEffect(() => {
        if (!form || form.id <= 0) {
            setTargets([]);
            setTargetsLoading(false);
            setTargetError("");
            setTargetOptions([]);
            setTargetForm(emptyTargetForm(targetTypes));
            return;
        }

        let cancelled = false;
        setTargetsLoading(true);
        setTargetError("");

        void callMoodleService<CouponTargetsResponse>(listTargetsMethodName, {couponid: form.id})
            .then((result) => {
                if (cancelled) {
                    return;
                }

                if (!result.success) {
                    setTargetError(result.message);
                    setTargets([]);
                    return;
                }

                setTargets(result.items);
            })
            .catch((caught: unknown) => {
                if (!cancelled) {
                    setTargetError(caught instanceof Error ? caught.message : String(caught));
                    setTargets([]);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setTargetsLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [form?.id, listTargetsMethodName, targetReloadToken, targetTypes]);

    useEffect(() => {
        if (!form || form.id <= 0) {
            setTargetOptions([]);
            return;
        }

        const query = targetForm.query.trim();
        if (targetForm.targettype !== "producttype" && query.length < 2) {
            setTargetOptions([]);
            setTargetSearchLoading(false);
            return;
        }

        let cancelled = false;
        const timer = window.setTimeout(() => {
            setTargetSearchLoading(true);
            setTargetError("");

            void callMoodleService<TargetOptionsResponse>(searchTargetOptionsMethodName, {
                targettype: targetForm.targettype,
                query,
                limit: 20,
            })
                .then((result) => {
                    if (!cancelled) {
                        setTargetOptions(result.items);
                    }
                })
                .catch((caught: unknown) => {
                    if (!cancelled) {
                        setTargetError(caught instanceof Error ? caught.message : String(caught));
                        setTargetOptions([]);
                    }
                })
                .finally(() => {
                    if (!cancelled) {
                        setTargetSearchLoading(false);
                    }
                });
        }, 300);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [form?.id, searchTargetOptionsMethodName, targetForm.query, targetForm.targettype]);

    useEffect(() => {
        const newButton = document.getElementById("moderncommerce-coupons-new");
        const refreshButton = document.getElementById("moderncommerce-coupons-refresh");
        const openNew = () => {
            setForm(emptyForm());
            setFormError("");
            setTargetError("");
            setTargetNotice("");
            setTargetForm(emptyTargetForm(targetTypes));
        };
        const refresh = () => {
            setReloadToken((current) => current + 1);
            setTargetReloadToken((current) => current + 1);
        };

        newButton?.addEventListener("click", openNew);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            newButton?.removeEventListener("click", openNew);
            refreshButton?.removeEventListener("click", refresh);
        };
    }, [targetTypes]);

    const currentCurrency = data?.currency ?? currency;
    const stats = data?.stats;
    const total = data?.total ?? 0;
    const totalPages = Math.max(1, Math.ceil(total / filters.perpage));
    const range = getVisibleRange(total, filters.page, filters.perpage);

    const editableTypeOptions = useMemo(() => {
        return typeOptions.filter((option) => option.value !== "");
    }, [typeOptions]);

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({
            ...current,
            ...changes,
            page: changes.page ?? 0,
        }));
    };

    const changeSort = (sort: string) => {
        setFilters((current) => {
            if (current.sort === sort) {
                return {
                    ...current,
                    direction: current.direction === "ASC" ? "DESC" : "ASC",
                    page: 0,
                };
            }

            return {
                ...current,
                sort,
                direction: sort === "code" || sort === "name" ? "ASC" : "DESC",
                page: 0,
            };
        });
    };

    const openNewForm = () => {
        setForm(emptyForm());
        setFormError("");
        setFieldErrors({});
        setTargetError("");
        setTargetNotice("");
        setTargetForm(emptyTargetForm(targetTypes));
    };

    const openEditForm = (coupon: Coupon) => {
        setForm(couponToForm(coupon));
        setFormError("");
        setFieldErrors({});
        setTargetError("");
        setTargetNotice("");
        setTargetForm(emptyTargetForm(targetTypes));
    };

    const closeForm = () => {
        setForm(null);
        setFormError("");
        setFieldErrors({});
        setTargetError("");
        setTargetNotice("");
        setTargetOptions([]);
    };

    // Surface a save error even if the admin scrolled down before pressing Save.
    useEffect(() => {
        if (formError && drawerBodyRef.current) {
            drawerBodyRef.current.scrollTo({top: 0, behavior: "smooth"});
        }
    }, [formError]);

    const updateForm = (changes: Partial<CouponForm>) => {
        setForm((current) => current ? {...current, ...changes} : current);
    };

    const submitForm = async(event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!form) {
            return;
        }

        const t = (key: string) => labels[key] ?? key;
        const found = runRules(form, {
            code: [
                required(t("error:required")),
                pattern(/^[A-Za-z0-9_-]{3,50}$/, t("error:invalidcouponcode")),
            ],
            name: maxLength(255, t("error:maxlength")),
            discounttype: required(t("error:selectoption")),
            value: [
                required(t("error:required")),
                number(t("error:invalidnumber")),
                min(0, t("error:minvalue")),
                // A percentage discount above 100 would pay the customer.
                when((values) => values.discounttype === "percentage", percent(t("error:invalidpercent"))),
            ],
            maxdiscount: [number(t("error:invalidnumber")), min(0, t("error:minvalue"))],
            minpurchase: [number(t("error:invalidnumber")), min(0, t("error:minvalue"))],
            minitems: [integer(t("error:invalidinteger")), min(0, t("error:minvalue"))],
            maxuses: [integer(t("error:invalidinteger")), min(0, t("error:minvalue"))],
            maxusesperuser: [
                integer(t("error:invalidinteger")),
                min(0, t("error:minvalue")),
                // 0 means unlimited on both fields, so only compare real caps.
                when(
                    (values) => Number(values.maxuses) > 0 && Number(values.maxusesperuser) > 0,
                    lteField("maxuses", t("error:peruserlimitaboveusagelimit"))
                ),
            ],
            enddate: afterField("startdate", t("error:enddatebeforestart")),
        });

        setFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setFormError("");
            focusFirstError(found, ["code", "name", "discounttype", "value", "maxdiscount", "minpurchase",
                "minitems", "maxuses", "maxusesperuser", "enddate"]);
            return;
        }

        setSaving(true);
        setFormError("");

        try {
            const result = await callMoodleService<SaveResponse>(saveMethodName, {
                id: form.id,
                code: form.code,
                name: form.name,
                discounttype: form.discounttype,
                value: toFloat(form.value),
                maxdiscount: toFloat(form.maxdiscount),
                minpurchase: toFloat(form.minpurchase),
                minitems: toInt(form.minitems),
                maxuses: toInt(form.maxuses),
                maxusesperuser: toInt(form.maxusesperuser),
                stackable: form.stackable,
                status: form.status,
                startdate: dateInputToTimestamp(form.startdate),
                enddate: dateInputToTimestamp(form.enddate, true),
            });

            if (!result.success) {
                const serverErrors = normaliseServerErrors(result.errors);
                if (Object.keys(serverErrors).length > 0) {
                    setFieldErrors(serverErrors);
                    focusFirstError(serverErrors, ["code", "name", "discounttype", "value"]);
                } else {
                    setFormError(result.message);
                }
                return;
            }

            toast.success(result.message);
            setForm((current) => current ? {...current, id: result.couponid} : current);
            setReloadToken((current) => current + 1);
            setTargetReloadToken((current) => current + 1);
        } catch (caught) {
            setFormError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const archiveCoupon = async(coupon: Coupon) => {
        if (!await confirmDialog({message: labels.archivecouponconfirm, danger: true})) {
            return;
        }

        setSaving(true);
        setError("");

        try {
            const result = await callMoodleService<SaveResponse>(archiveMethodName, {id: coupon.id});
            if (!result.success) {
                setError(result.message);
                return;
            }

            toast.success(result.message);
            if (form?.id === coupon.id) {
                setForm(null);
            }
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const updateTargetForm = (changes: Partial<TargetForm>) => {
        setTargetForm((current) => ({...current, ...changes}));
    };

    const setTargetType = (targettype: string) => {
        setTargetForm({
            ...emptyTargetForm(targetTypes),
            targettype,
            includemode: targetForm.includemode,
        });
        setTargetOptions([]);
        setTargetError("");
        setTargetNotice("");
    };

    const selectTargetOption = (option: TargetOption) => {
        updateTargetForm({
            targetid: option.id,
            targetvalue: option.targetvalue,
            label: option.label,
            summary: option.summary,
            query: option.label,
        });
        setTargetOptions([]);
    };

    const addTarget = async() => {
        if (!form || form.id <= 0) {
            setTargetError(labels.savecouponfirsttargets);
            return;
        }

        if (isValueTargetType(targetForm.targettype)) {
            if (targetForm.targetvalue.trim() === "") {
                setTargetError(labels.coupontargetrequiresvalue);
                return;
            }
        } else if (targetForm.targetid <= 0) {
            setTargetError(labels.coupontargetrequiresselection);
            return;
        }

        setSaving(true);
        setTargetError("");
        setTargetNotice("");

        try {
            const result = await callMoodleService<TargetSaveResponse>(saveTargetMethodName, {
                id: 0,
                couponid: form.id,
                targettype: targetForm.targettype,
                targetid: targetForm.targetid,
                targetvalue: targetForm.targetvalue,
                includemode: targetForm.includemode,
            });

            if (!result.success) {
                setTargetError(result.message);
                return;
            }

            setTargetNotice(result.message);
            setTargetForm(emptyTargetForm(targetTypes));
            setTargetReloadToken((current) => current + 1);
        } catch (caught) {
            setTargetError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const deleteTarget = async(target: CouponTarget) => {
        if (!await confirmDialog({message: labels.deletetargetconfirm, danger: true})) {
            return;
        }

        setSaving(true);
        setTargetError("");
        setTargetNotice("");

        try {
            const result = await callMoodleService<TargetSaveResponse>(deleteTargetMethodName, {id: target.id});
            if (!result.success) {
                setTargetError(result.message);
                return;
            }

            setTargetNotice(result.message);
            setTargetReloadToken((current) => current + 1);
        } catch (caught) {
            setTargetError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const sortHeaderProps = (sort: string): {"aria-sort"?: "ascending" | "descending"} =>
        filters.sort === sort
            ? {"aria-sort": filters.direction === "ASC" ? "ascending" : "descending"}
            : {};

    const renderSortButton = (sort: string, label: string, align = "text-start") => {
        const active = filters.sort === sort;
        return (
            <button
                className={mcClasses(`mc-table-sort ${align}`)}
                onClick={() => changeSort(sort)}
                type="button"
            >
                <span>{label}</span>
                <i
                    className={mcClasses(
                        "mc-table-sort__indicator",
                        active && "mc-table-sort__indicator--active",
                        sortIconClass(active, filters.direction),
                    )}
                    aria-hidden="true"
                />
            </button>
        );
    };

    const renderCouponActions = (coupon: Coupon) => (
        <div className={mcClasses("mc-table-design__actions justify-content-end")}>
            <McTableActionMenu
                label={`${labels.actions}: ${coupon.code}`}
                items={[
                    {
                        key: "edit",
                        label: labels.edit,
                        icon: "bi bi-pencil",
                        disabled: saving,
                        onClick: () => openEditForm(coupon),
                    },
                    {
                        key: "archive",
                        label: labels.archivecoupon,
                        icon: "bi bi-archive",
                        danger: true,
                        disabled: saving || coupon.status === "archived",
                        onClick: () => void archiveCoupon(coupon),
                    },
                ]}
            />
        </div>
    );

    const renderTargetActions = (target: CouponTarget) => (
        <div className={mcClasses("mc-table-design__actions justify-content-end")}>
            <McTableActionMenu
                label={`${labels.actions}: ${target.displayname}`}
                items={[
                    {
                        key: "delete",
                        label: labels.deletetarget,
                        icon: "bi bi-trash",
                        danger: true,
                        disabled: saving,
                        onClick: () => void deleteTarget(target),
                    },
                ]}
            />
        </div>
    );

    const renderConstraints = (coupon: Coupon): string => {
        const parts = [];
        if (coupon.minpurchase > 0) {
            parts.push(`${labels.minpurchase}: ${coupon.displayminpurchase}`);
        }
        if (coupon.minitems > 0) {
            parts.push(`${labels.minitems}: ${coupon.minitems}`);
        }
        if (coupon.maxdiscount > 0) {
            parts.push(`${labels.maxdiscount}: ${coupon.displaymaxdiscount}`);
        }

        return parts.length > 0 ? parts.join(" / ") : "-";
    };

    const renderUsage = (coupon: Coupon): string => {
        const totalLimit = coupon.maxuses > 0 ? `${coupon.usedcount}/${coupon.maxuses}` : `${coupon.usedcount}/${labels.unlimited}`;
        const userLimit = coupon.maxusesperuser > 0 ? `${labels.maxusesperuser}: ${coupon.maxusesperuser}` : labels.unlimited;

        return `${totalLimit} / ${userLimit}`;
    };

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
            <div className={mcClasses("mc-card-sub mb-3")}>
                {currentCurrency.symbol} {currentCurrency.code}
            </div>


            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            {form && (
                <McDrawer
                    title={form.id > 0 ? labels.editcoupon : labels.newcoupon}
                    subtitle={form.id > 0 && form.name ? form.name : undefined}
                    onClose={closeForm}
                    closeLabel={labels.cancel}
                    disableClose={saving}
                    bodyRef={drawerBodyRef}
                    footer={(
                        <>
                            <McButton
                                className={mcClasses("btn-mc-primary")}
                                disabled={saving}
                                form="mc-coupon-drawer-form"
                                loading={saving}
                                loadingLabel={labels.saving || "Saving..."}
                                type="submit"
                            >
                                {labels.savecoupon}
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
                    {formError && (
                        <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                            <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                            <div className={mcClasses("mc-alert__body")}>{formError}</div>
                        </div>
                    )}

                    <form id="mc-coupon-drawer-form" noValidate onSubmit={submitForm}>
                    <div className={mcClasses("mc-product-form__section")}>
                        <h4>{labels.managecoupons}</h4>
                        <div className={mcClasses("mc-product-form__grid")}>
                            <label htmlFor="mc-coupon-code">
                                <span>
                                    {labels.code}
                                    <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                </span>
                                <input
                                    autoComplete="off"
                                    aria-describedby={fieldErrors.code ? "mc-coupon-code-error" : undefined}
                                    aria-invalid={fieldErrors.code ? true : undefined}
                                    aria-required="true"
                                    className={mcClasses("mc-form-control", fieldErrors.code ? "mc-field-invalid" : "")}
                                    id="mc-coupon-code"
                                    maxLength={50}
                                    name="code"
                                    onChange={(event) => updateForm({code: event.target.value.toUpperCase()})}
                                    required
                                    type="text"
                                    value={form.code}
                                />
                                <FieldError id="mc-coupon-code-error" message={fieldErrors.code} />
                            </label>
                            <label className={mcClasses("mc-product-form__wide")}>
                                <span>{labels.name}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    onChange={(event) => updateForm({name: event.target.value})}
                                    type="text"
                                    value={form.name}
                                />
                            </label>
                            <label>
                                <span>{labels.status}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    onChange={(event) => updateForm({status: event.target.value})}
                                    value={form.status}
                                >
                                    {editableStatuses.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                <span>{labels.type}</span>
                                <select
                                    className={mcClasses("mc-select")}
                                    onChange={(event) => updateForm({discounttype: event.target.value})}
                                    value={form.discounttype}
                                >
                                    {editableTypeOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </label>
                            <label htmlFor="mc-coupon-value">
                                <span>
                                    {labels.value}
                                    <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                </span>
                                <input
                                    aria-describedby={fieldErrors.value ? "mc-coupon-value-error" : undefined}
                                    aria-invalid={fieldErrors.value ? true : undefined}
                                    aria-required="true"
                                    className={mcClasses("mc-form-control", fieldErrors.value ? "mc-field-invalid" : "")}
                                    id="mc-coupon-value"
                                    min="0"
                                    name="value"
                                    onChange={(event) => updateForm({value: event.target.value})}
                                    required
                                    step="0.01"
                                    type="number"
                                    value={form.value}
                                />
                                <FieldError id="mc-coupon-value-error" message={fieldErrors.value} />
                            </label>
                            <label>
                                <span>{labels.maxdiscount}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    min="0"
                                    onChange={(event) => updateForm({maxdiscount: event.target.value})}
                                    step="0.01"
                                    type="number"
                                    value={form.maxdiscount}
                                />
                            </label>
                            <label>
                                <span>{labels.minpurchase}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    min="0"
                                    onChange={(event) => updateForm({minpurchase: event.target.value})}
                                    step="0.01"
                                    type="number"
                                    value={form.minpurchase}
                                />
                            </label>
                            <label>
                                <span>{labels.minitems}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    min="0"
                                    onChange={(event) => updateForm({minitems: event.target.value})}
                                    type="number"
                                    value={form.minitems}
                                />
                            </label>
                            <label>
                                <span>{labels.usagelimit}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    min="0"
                                    onChange={(event) => updateForm({maxuses: event.target.value})}
                                    type="number"
                                    value={form.maxuses}
                                />
                            </label>
                            <label>
                                <span>{labels.maxusesperuser}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    min="0"
                                    onChange={(event) => updateForm({maxusesperuser: event.target.value})}
                                    type="number"
                                    value={form.maxusesperuser}
                                />
                            </label>
                            <label>
                                <span>{labels.startdate}</span>
                                <input
                                    className={mcClasses("mc-form-control")}
                                    onChange={(event) => updateForm({startdate: event.target.value})}
                                    type="date"
                                    value={form.startdate}
                                />
                            </label>
                            <label htmlFor="mc-coupon-enddate">
                                <span>{labels.enddate}</span>
                                <input
                                    aria-describedby={fieldErrors.enddate ? "mc-coupon-enddate-error" : undefined}
                                    aria-invalid={fieldErrors.enddate ? true : undefined}
                                    className={mcClasses("mc-form-control",
                                        fieldErrors.enddate ? "mc-field-invalid" : "")}
                                    id="mc-coupon-enddate"
                                    name="enddate"
                                    onChange={(event) => updateForm({enddate: event.target.value})}
                                    type="date"
                                    value={form.enddate}
                                />
                                <FieldError id="mc-coupon-enddate-error" message={fieldErrors.enddate} />
                            </label>
                        </div>
                        <div className={mcClasses("mc-product-form__checks")}>
                            <label>
                                <input
                                    checked={form.stackable}
                                    onChange={(event) => updateForm({stackable: event.target.checked})}
                                    type="checkbox"
                                />
                                <span>{labels.stackable}</span>
                            </label>
                        </div>
                    </div>

                    <div className={mcClasses("mc-product-form__section")}>
                        <div className="d-flex flex-wrap justify-content-between gap-2">
                            <h4>{labels.appliesto}</h4>
                            <span className={mcClasses("mc-cell-muted")}>{labels.appliesall}</span>
                        </div>

                        {targetNotice && (
                            <div className={mcClasses("mc-alert mc-alert--success")} role="status">
                                <i className="bi bi-check-circle mc-alert__icon" aria-hidden="true" />
                                <div className={mcClasses("mc-alert__body")}>{targetNotice}</div>
                            </div>
                        )}

                        {targetError && (
                            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                                <div className={mcClasses("mc-alert__body")}>{targetError}</div>
                            </div>
                        )}

                        {form.id <= 0 && (
                            <div className={mcClasses("mc-alert mc-alert--info mb-0")} role="status">
                                <i className="bi bi-info-circle mc-alert__icon" aria-hidden="true" />
                                <div className={mcClasses("mc-alert__body")}>{labels.savecouponfirsttargets}</div>
                            </div>
                        )}

                        {form.id > 0 && (
                            <>
                                <McTableFrame className="mb-3">
                                    <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.appliesto}>
                                        <thead>
                                            <tr>
                                                <th scope="col">{labels.includemode}</th>
                                                <th scope="col">{labels.targettype}</th>
                                                <th scope="col">{labels.target}</th>
                                                <th scope="col" className="text-end">{labels.actions}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {!targetsLoading && targets.length === 0 && (
                                                <tr>
                                                    <td colSpan={4}>
                                                        <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-bullseye" aria-hidden="true" /></span>
                                                            <p className={mcClasses("mc-empty__title")}>{labels.notargets}</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )}
                                            {targets.map((target) => (
                                                <tr key={target.id}>
                                                    <td>
                                                        <McBadge variant={target.includemode === "exclude" ? "danger" : "success"} tone="soft" dot>
                                                            {target.includemodelabel}
                                                        </McBadge>
                                                    </td>
                                                    <td>{target.targettypelabel}</td>
                                                    <td>
                                                        <div className="fw-semibold">{target.displayname}</div>
                                                        {target.summary && <div className={mcClasses("mc-cell-muted")}>{target.summary}</div>}
                                                    </td>
                                                    <td className="text-end">
                                                        {renderTargetActions(target)}
                                                    </td>
                                                </tr>
                                            ))}
                                            {targetsLoading && (
                                                <tr>
                                                    <td colSpan={4}>
                                                        <div className={mcClasses("mc-product-admin__loading")}>{labels.loadingtargets}</div>
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </McTableFrame>

                                <div className={mcClasses("mc-product-form__grid")}>
                                    <label>
                                        <span>{labels.targettype}</span>
                                        <select
                                            className={mcClasses("mc-select")}
                                            onChange={(event) => setTargetType(event.target.value)}
                                            value={targetForm.targettype}
                                        >
                                            {targetTypes.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                    </label>
                                    <label>
                                        <span>{labels.includemode}</span>
                                        <select
                                            className={mcClasses("mc-select")}
                                            onChange={(event) => updateTargetForm({includemode: event.target.value})}
                                            value={targetForm.includemode}
                                        >
                                            {includeModes.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                    </label>
                                    <div className={mcClasses("mc-course-picker mc-product-form__wide")}>
                                        <label htmlFor="mc-coupon-target-picker-input">
                                            <span>{labels.searchtarget}</span>
                                            <input
                                                autoComplete="off"
                                                className={mcClasses("mc-form-control")}
                                                id="mc-coupon-target-picker-input"
                                                // This sits INSIDE the coupon form (a nested <form>
                                                // would be invalid HTML), so Enter here would save
                                                // the coupon instead of filtering targets.
                                                onKeyDown={(event) => {
                                                    if (event.key === "Enter") {
                                                        event.preventDefault();
                                                    }
                                                }}
                                                onChange={(event) => {
                                                    updateTargetForm({
                                                        query: event.target.value,
                                                        targetid: 0,
                                                        targetvalue: "",
                                                        label: "",
                                                        summary: "",
                                                    });
                                                }}
                                                placeholder={labels.searchtargetsplaceholder}
                                                type="search"
                                                value={targetForm.query}
                                            />
                                        </label>

                                        {targetSearchLoading && (
                                            <div className={mcClasses("mc-course-picker__message")}>{labels.loading}</div>
                                        )}

                                        {!targetSearchLoading && targetForm.query.trim().length >= 2 && targetOptions.length === 0 && targetForm.targetid <= 0 && targetForm.targetvalue === "" && (
                                            <div className={mcClasses("mc-course-picker__message")}>{labels.notargetmatches}</div>
                                        )}

                                        {targetOptions.length > 0 && (
                                            <div className={mcClasses("mc-course-picker__results")} role="listbox">
                                                {targetOptions.map((option) => (
                                                    <button
                                                        className={mcClasses("mc-button mc-course-picker__option")}
                                                        data-mc-button="ghost"
                                                        key={`${option.id}-${option.targetvalue}-${option.label}`}
                                                        onClick={() => selectTargetOption(option)}
                                                        role="option"
                                                        type="button"
                                                    >
                                                        <span className={mcClasses("mc-course-picker__option-main")}>
                                                            <strong>{option.label}</strong>
                                                            {option.summary && <small>{option.summary}</small>}
                                                        </span>
                                                    </button>
                                                ))}
                                            </div>
                                        )}

                                        {(targetForm.targetid > 0 || targetForm.targetvalue !== "") && (
                                            <div className={mcClasses("mc-course-picker__selection")}>
                                                <strong>{labels.selectedtarget}</strong>
                                                <span>{targetForm.label || targetForm.targetvalue}</span>
                                                {targetForm.summary && <small>{targetForm.summary}</small>}
                                            </div>
                                        )}
                                    </div>
                                    <div className="d-flex align-items-end">
                                        <button
                                            className={mcClasses("mc-button btn-mc-secondary")}
                                            disabled={saving || (isValueTargetType(targetForm.targettype) ? targetForm.targetvalue === "" : targetForm.targetid <= 0)}
                                            onClick={addTarget}
                                            type="button"
                                        >
                                            {labels.addtarget}
                                        </button>
                                    </div>
                                </div>
                            </>
                        )}
                    </div>

                    </form>
                </McDrawer>
            )}

            {stats && (
                <div className={mcClasses("mc-stat-strip")} aria-label={labels.managecoupons}>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--primary")}>
                        <i className="bi bi-ticket-perforated mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.totalcoupons}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.totalcoupons)}</strong>
                        </div>
                        <i className="bi bi-ticket-perforated mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                        <i className="bi bi-check-circle mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.active}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.activecoupons)}</strong>
                        </div>
                        <i className="bi bi-check-circle mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--warning")}>
                        <i className="bi bi-receipt mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.redemptions}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.totalredemptions)}</strong>
                        </div>
                        <i className="bi bi-receipt mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--info")}>
                        <i className="bi bi-percent mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.totaldiscount}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{stats.displaytotaldiscount}</strong>
                        </div>
                        <i className="bi bi-percent mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                </div>
            )}

            <McTableCard
                title={<h2 className={mcClasses("mc-card-title")}>{labels.title}</h2>}
                actions={(
                    <button className={mcClasses("mc-button btn-mc-primary")} onClick={openNewForm} type="button">
                        <i className="bi bi-plus-lg me-1" aria-hidden="true" />
                        {labels.newcoupon}
                    </button>
                )}
                toolbar={(
                    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                        <div className={mcClasses("mc-product-toolbar__search")}>
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-coupon-search">
                                {labels.search}
                            </label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-coupon-search"
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
                                onChange={(event) => updateFilters({discounttype: event.target.value})}
                                value={filters.discounttype}
                            >
                                {typeOptions.map((option) => (
                                    <option key={option.value || "all"} value={option.value}>{option.label}</option>
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
                                {statusOptions.map((option) => (
                                    <option key={option.value || "all"} value={option.value}>{option.label}</option>
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
                        summary={<span>{labels.showing} {range.from}-{range.to} / {formatCount(total)}</span>}
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
                                    <th scope="col" {...sortHeaderProps("code")}>{renderSortButton("code", labels.code)}</th>
                                    <th scope="col" {...sortHeaderProps("discounttype")}>{renderSortButton("discounttype", labels.type)}</th>
                                    <th scope="col" className="text-end" {...sortHeaderProps("value")}>
                                        {renderSortButton("value", labels.value, "text-end")}
                                    </th>
                                    <th scope="col">{labels.constraints}</th>
                                    <th scope="col" {...sortHeaderProps("usedcount")}>{renderSortButton("usedcount", labels.usage)}</th>
                                    <th scope="col">{labels.validity}</th>
                                    <th scope="col" {...sortHeaderProps("status")}>{renderSortButton("status", labels.status)}</th>
                                    <th scope="col" className="text-end" {...sortHeaderProps("timemodified")}>
                                        {renderSortButton("timemodified", labels.updated, "text-end")}
                                    </th>
                                    <th scope="col" className="text-end">{labels.actions}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {!loading && data?.items.length === 0 && (
                                    <tr>
                                        <td colSpan={9}>
                                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                <span className={mcClasses("mc-empty__icon")}>
                                                    <i className="bi bi-ticket-perforated" aria-hidden="true" />
                                                </span>
                                                <p className={mcClasses("mc-empty__title")}>
                                                    {total === 0 ? labels.nocouponsfound : labels.noresults}
                                                </p>
                                                <button
                                                    className={mcClasses("mc-button btn-mc-primary")}
                                                    onClick={openNewForm}
                                                    type="button"
                                                >
                                                    {labels.newcoupon}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {data?.items.map((coupon) => (
                                    <tr key={coupon.id}>
                                        <td>
                                            <div className="fw-semibold">{coupon.code}</div>
                                            <div className={mcClasses("mc-cell-muted")}>{coupon.name}</div>
                                        </td>
                                        <td>
                                            <McBadge variant="neutral" tone="soft">
                                                {coupon.discounttypelabel}
                                            </McBadge>
                                        </td>
                                        <td className="text-end fw-semibold">{coupon.displayvalue}</td>
                                        <td className={mcClasses("mc-cell-muted")}>{renderConstraints(coupon)}</td>
                                        <td>
                                            <div>{renderUsage(coupon)}</div>
                                            <div className={mcClasses("mc-cell-muted")}>
                                                {labels.discounttotal}: {coupon.displaydiscounttotal}
                                            </div>
                                        </td>
                                        <td className={mcClasses("mc-cell-muted")}>
                                            <div>{formatDate(coupon.startdate)}</div>
                                            <div>
                                                {coupon.enddate > 0
                                                    ? formatDate(coupon.enddate)
                                                    : labels.leaveemptynoexpiry}
                                            </div>
                                        </td>
                                        <td>
                                            <McBadge variant={badgeVariant(coupon.statusclass)} tone="soft" dot>
                                                {coupon.statuslabel}
                                            </McBadge>
                                        </td>
                                        <td className={mcClasses("text-end mc-cell-muted")}>
                                            {formatDate(coupon.timemodified)}
                                        </td>
                                        <td className="text-end">
                                            {renderCouponActions(coupon)}
                                        </td>
                                    </tr>
                                ))}
                                {loading && (
                                    <tr>
                                        <td colSpan={9}>
                                            <div className={mcClasses("mc-product-admin__loading")}>
                                                {labels.loading}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                </table>
            </McTableCard>
        </section>
    );
}

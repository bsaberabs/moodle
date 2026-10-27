// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin product/pricing list for Modern Commerce.
 *
 * @module     local_moderncommerce/pricing_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useMemo, useRef, useState} from "react";
import type {FormEvent} from "react";
import {mcClasses, sortIconClass, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {McDrawer} from "./drawer";
import {McTableActionMenu, McTableCard, McTableFooter, McTableFrame, McTablePagination} from "./table_components";
import {confirmDialog} from "./modal";
import {FieldError} from "./form";
import {
    afterField, focusFirstError, gteField, integer, lteField, maxLength, min, normaliseServerErrors, number,
    pattern, required, runRules,
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
    producttype: string;
    pricingstatus: string;
    categoryid: number;
    page: number;
    perpage: number;
    sort: string;
    direction: "ASC" | "DESC";
};

type Product = {
    id: number;
    producttype: string;
    name: string;
    slug: string;
    sku: string;
    status: string;
    visible: boolean;
    featured: boolean;
    shortdescription: string;
    displayorder: number;
    primarycategoryid: number;
    primarycourseid: number;
    primarycoursename: string;
    primarycourseshortname: string;
    primarycoursecategory: string;
    primarycoursevisible: boolean;
    categorynames: string;
    hasprice: boolean;
    onsale: boolean;
    rawprice: number;
    rawcompareamount: number;
    displayprice: string;
    displaycompareamount: string;
    stockmanaged: boolean;
    stock: number;
    reservedstock: number;
    allowbackorder: boolean;
    stocklabel: string;
    coursecount: number;
    soldcount: number;
    timecreated: number;
    timemodified: number;
};

type PriceRow = {
    id: number;
    productid: number;
    pricetype: string;
    pricetypelabel: string;
    amount: number;
    compareamount: number;
    displayamount: string;
    displaycompareamount: string;
    minquantity: number;
    maxquantity: number;
    startdate: number;
    enddate: number;
    enabled: boolean;
    active: boolean;
    timecreated: number;
    timemodified: number;
};

type Stats = {
    totalproducts: number;
    activeproducts: number;
    draftproducts: number;
    visibleproducts: number;
    featuredproducts: number;
    courseproducts: number;
    bundleproducts: number;
    pricedproducts: number;
    unpricedproducts: number;
    onsaleproducts: number;
};

type Category = {
    id: number;
    name: string;
    visible: boolean;
    productcount: number;
};

type CourseOption = {
    id: number;
    fullname: string;
    shortname: string;
    idnumber: string;
    categoryname: string;
    summary: string;
    visible: boolean;
    existingproductid: number;
    existingproductname: string;
    existingproductstatus: string;
    suggestedsku: string;
    suggestedslug: string;
};

type ProductsResponse = {
    items: Product[];
    total: number;
    page: number;
    perpage: number;
    sort: string;
    direction: "ASC" | "DESC";
    currency: Currency;
    stats: Stats;
    categories: Category[];
};

type CoursesResponse = {
    items: CourseOption[];
    total: number;
    query: string;
    limit: number;
};

type PricesResponse = {
    success: boolean;
    message: string;
    productid: number;
    productname: string;
    currency: Currency;
    items: PriceRow[];
};

type SaveResponse = WithFieldErrors & {
    success: boolean;
    productid: number;
    message: string;
};

type PriceSaveResponse = {
    success: boolean;
    priceid: number;
    productid: number;
    message: string;
};

type ProductForm = {
    id: number;
    producttype: string;
    name: string;
    sku: string;
    slug: string;
    status: string;
    visible: boolean;
    featured: boolean;
    shortdescription: string;
    displayorder: string;
    categoryid: number;
    courseid: number;
    coursename: string;
    courseshortname: string;
    coursecategory: string;
    coursevisible: boolean;
    priceenabled: boolean;
    // UI-only flag: when true the "original (compare-at) price" field is collected.
    saleenabled: boolean;
    regularprice: string;
    compareamount: string;
    stockmanaged: boolean;
    stock: string;
    reservedstock: string;
    allowbackorder: boolean;
};

type PriceForm = {
    id: number;
    productid: number;
    pricetype: string;
    amount: string;
    compareamount: string;
    minquantity: string;
    maxquantity: string;
    startdate: string;
    enddate: string;
    enabled: boolean;
};

type Labels = Record<string, string>;

type BoolChange = (value: boolean) => void;
type StringChange = (value: string) => void;

type PricingAdminProps = {
    methodName: string;
    saveMethodName: string;
    archiveMethodName: string;
    searchCoursesMethodName: string;
    listPricesMethodName: string;
    savePriceMethodName: string;
    archivePriceMethodName: string;
    initialFilters: Partial<Filters>;
    // SKU / slug are generated internally; only shown and editable in the form when enabled.
    showSku: boolean;
    showSlug: boolean;
    currency: Currency;
    labels: Labels;
    productTypes: SelectOption[];
    statusOptions: SelectOption[];
    pricingStatuses: SelectOption[];
    priceTypes: SelectOption[];
    perPageOptions: number[];
};

const defaultFilters: Filters = {
    search: "",
    status: "",
    producttype: "",
    pricingstatus: "",
    categoryid: 0,
    page: 0,
    perpage: 10,
    sort: "timecreated",
    direction: "DESC",
};

const normaliseFilters = (filters: Partial<Filters>): Filters => ({
    ...defaultFilters,
    ...filters,
    categoryid: Number(filters.categoryid ?? defaultFilters.categoryid) || 0,
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

const optionLabel = (options: SelectOption[], value: string, fallback: string): string => {
    return options.find((option) => option.value === value)?.label ?? fallback;
};

const formatCount = (value: number): string => {
    return new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);
};

const formatQuantity = (value: number): string => {
    return Number.isInteger(value) ? String(value) : value.toFixed(2);
};

const formatMoney = (amount: number, currency: Currency): string => {
    const value = amount.toFixed(currency.decimals);
    return currency.position === "after" ? `${value} ${currency.symbol}` : `${currency.symbol}${value}`;
};

const computeDiscountPct = (regular: number, sale: number): number => {
    return regular > 0 && sale < regular ? Math.round((1 - sale / regular) * 100) : 0;
};

const computeSavings = (regular: number, sale: number): number => {
    return Math.max(0, regular - sale);
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

const dateInputToTimestamp = (value: string): number => {
    if (!value) {
        return 0;
    }

    const timestamp = new Date(`${value}T00:00:00`).getTime();
    return Number.isFinite(timestamp) ? Math.floor(timestamp / 1000) : 0;
};

const formatQuantityRange = (price: PriceRow): string => {
    return price.maxquantity > 0 ? `${price.minquantity}-${price.maxquantity}` : `${price.minquantity}+`;
};

const formatPriceWindow = (price: PriceRow): string => {
    if (!price.startdate && !price.enddate) {
        return "-";
    }

    return `${formatDate(price.startdate)} - ${formatDate(price.enddate)}`;
};

const toInt = (value: string): number => {
    const parsed = parseInt(value, 10);
    return Number.isFinite(parsed) ? parsed : 0;
};

const toFloat = (value: string): number => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const statusBadgeVariant = (status: string): McBadgeVariant => {
    if (status === "active") {
        return "success";
    }

    if (status === "draft") {
        return "warning";
    }

    return "neutral";
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

const emptyForm = (categoryid = 0): ProductForm => ({
    id: 0,
    producttype: "course",
    name: "",
    sku: "",
    slug: "",
    status: "draft",
    visible: true,
    featured: false,
    shortdescription: "",
    displayorder: "0",
    categoryid,
    courseid: 0,
    coursename: "",
    courseshortname: "",
    coursecategory: "",
    coursevisible: true,
    priceenabled: true,
    saleenabled: false,
    regularprice: "0",
    compareamount: "",
    stockmanaged: false,
    stock: "0",
    reservedstock: "0",
    allowbackorder: false,
});

const productToForm = (product: Product): ProductForm => ({
    id: product.id,
    producttype: product.producttype,
    name: product.name,
    sku: product.sku,
    slug: product.slug,
    status: product.status,
    visible: product.visible,
    featured: product.featured,
    shortdescription: product.shortdescription,
    displayorder: String(product.displayorder),
    categoryid: product.primarycategoryid,
    courseid: product.primarycourseid,
    coursename: product.primarycoursename,
    courseshortname: product.primarycourseshortname,
    coursecategory: product.primarycoursecategory,
    coursevisible: product.primarycoursevisible,
    priceenabled: product.hasprice,
    saleenabled: product.onsale || product.rawcompareamount > 0,
    regularprice: String(product.rawprice),
    compareamount: product.rawcompareamount > 0 ? String(product.rawcompareamount) : "",
    stockmanaged: product.stockmanaged,
    stock: String(product.stock),
    reservedstock: String(product.reservedstock),
    allowbackorder: product.allowbackorder,
});

// Build the save_product web service payload from a product form. The base
// (regular) price is owned exclusively here — never created via save_product_price
// — so the canonical regular row is upserted idempotently and never duplicated.
//
// SKU and slug are only sent when their form fields are shown; otherwise they are
// omitted so the server generates (or preserves) them internally.
const buildProductPayload = (form: ProductForm, showSku: boolean, showSlug: boolean) => {
    const payload: Record<string, unknown> = {
        id: form.id,
        producttype: form.producttype,
        name: form.name.trim(),
        status: form.status,
        visible: form.visible,
        featured: form.featured,
        shortdescription: form.shortdescription.trim(),
        displayorder: toInt(form.displayorder),
        categoryid: form.categoryid,
        courseid: form.courseid,
        priceenabled: form.priceenabled,
        regularprice: toFloat(form.regularprice),
        compareamount: form.saleenabled ? toFloat(form.compareamount) : 0,
        stockmanaged: form.stockmanaged,
        stock: toInt(form.stock),
        reservedstock: toInt(form.reservedstock),
        allowbackorder: form.allowbackorder,
    };
    if (showSku) {
        payload.sku = form.sku.trim();
    }
    if (showSlug) {
        payload.slug = form.slug.trim();
    }
    return payload;
};

const emptyPriceForm = (productid: number): PriceForm => ({
    id: 0,
    productid,
    pricetype: "sale",
    amount: "0",
    compareamount: "",
    minquantity: "1",
    maxquantity: "",
    startdate: "",
    enddate: "",
    enabled: true,
});

const priceToForm = (price: PriceRow): PriceForm => ({
    id: price.id,
    productid: price.productid,
    pricetype: price.pricetype,
    amount: String(price.amount),
    compareamount: price.compareamount > 0 ? String(price.compareamount) : "",
    minquantity: String(price.minquantity),
    maxquantity: price.maxquantity > 0 ? String(price.maxquantity) : "",
    startdate: timestampToDateInput(price.startdate),
    enddate: timestampToDateInput(price.enddate),
    enabled: price.enabled,
});

export default function PricingAdmin({
    methodName,
    saveMethodName,
    archiveMethodName,
    searchCoursesMethodName,
    listPricesMethodName,
    savePriceMethodName,
    archivePriceMethodName,
    initialFilters,
    showSku,
    showSlug,
    currency,
    labels,
    productTypes,
    statusOptions,
    pricingStatuses,
    priceTypes,
    perPageOptions,
}: PricingAdminProps) {
    useModernCommerceClassSync();
    const [filters, setFilters] = useState<Filters>(() => normaliseFilters(initialFilters));
    const [searchInput, setSearchInput] = useState(filters.search);
    const [data, setData] = useState<ProductsResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const [formError, setFormError] = useState("");
    const [fieldErrors, setFieldErrors] = useState<FieldErrorMap>({});
    const [priceFieldErrors, setPriceFieldErrors] = useState<FieldErrorMap>({});
    const [reloadToken, setReloadToken] = useState(0);
    const [form, setForm] = useState<ProductForm | null>(null);
    const [courseSearch, setCourseSearch] = useState("");
    const [courseOptions, setCourseOptions] = useState<CourseOption[]>([]);
    const [courseLoading, setCourseLoading] = useState(false);
    const [courseError, setCourseError] = useState("");
    const [prices, setPrices] = useState<PriceRow[]>([]);
    const [pricesLoading, setPricesLoading] = useState(false);
    const [pricesError, setPricesError] = useState("");
    const [priceForm, setPriceForm] = useState<PriceForm | null>(null);
    const [priceFormError, setPriceFormError] = useState("");
    const [priceReloadToken, setPriceReloadToken] = useState(0);
    const [inlineEditId, setInlineEditId] = useState<number | null>(null);
    const [inlineValue, setInlineValue] = useState("");
    const skipBlurRef = useRef(false);
    const drawerBodyRef = useRef<HTMLDivElement>(null);

    const drawerOpen = form !== null;
    const priceDrawerOpen = priceForm !== null;
    const editingProductId = form && form.id > 0 ? form.id : 0;

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
        const query = courseSearch.trim();

        if (!form || form.producttype !== "course" || query.length < 2) {
            setCourseOptions([]);
            setCourseError("");
            setCourseLoading(false);
            return () => {
                cancelled = true;
            };
        }

        const selectedLabel = form.coursename
            ? `${form.coursename} (${form.courseshortname})`
            : "";
        if (form.courseid > 0 && query === selectedLabel) {
            setCourseOptions([]);
            setCourseError("");
            setCourseLoading(false);
            return () => {
                cancelled = true;
            };
        }

        setCourseLoading(true);
        setCourseError("");

        const timer = window.setTimeout(() => {
            callMoodleService<CoursesResponse>(searchCoursesMethodName, {query, limit: 20})
                .then((result) => {
                    if (!cancelled) {
                        setCourseOptions(result.items);
                    }
                })
                .catch((caught: Error) => {
                    if (!cancelled) {
                        setCourseOptions([]);
                        setCourseError(caught.message);
                    }
                })
                .finally(() => {
                    if (!cancelled) {
                        setCourseLoading(false);
                    }
                });
        }, 300);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [courseSearch, form, searchCoursesMethodName]);

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");

        callMoodleService<ProductsResponse>(methodName, filters)
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                }
            })
            .catch((caught: Error) => {
                if (!cancelled) {
                    setError(caught.message);
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
    }, [
        filters.categoryid,
        filters.direction,
        filters.page,
        filters.perpage,
        filters.pricingstatus,
        filters.producttype,
        filters.search,
        filters.sort,
        filters.status,
        methodName,
        reloadToken,
    ]);

    // Load the advanced price ledger for the product currently open in the drawer.
    useEffect(() => {
        let cancelled = false;

        if (!editingProductId) {
            setPrices([]);
            setPricesError("");
            return () => {
                cancelled = true;
            };
        }

        setPricesLoading(true);
        setPricesError("");

        callMoodleService<PricesResponse>(listPricesMethodName, {productid: editingProductId})
            .then((result) => {
                if (cancelled) {
                    return;
                }

                if (!result.success) {
                    setPrices([]);
                    setPricesError(result.message);
                    return;
                }

                setPrices(result.items);
            })
            .catch((caught: Error) => {
                if (!cancelled) {
                    setPrices([]);
                    setPricesError(caught.message);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setPricesLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [listPricesMethodName, editingProductId, priceReloadToken]);

    const currentCurrency = data?.currency ?? currency;
    const stats = data?.stats;
    const categories = data?.categories ?? [];
    const items = data?.items ?? [];
    const total = data?.total ?? 0;
    const totalPages = Math.max(1, Math.ceil(total / filters.perpage));
    const range = getVisibleRange(total, filters.page, filters.perpage);
    const advancedPriceTypes = useMemo(
        () => priceTypes.filter((option) => option.value !== "" && option.value !== "regular"),
        [priceTypes]
    );
    const advancedPrices = useMemo(
        () => prices.filter((price) => price.pricetype !== "regular"),
        [prices]
    );

    const categoryOptions = useMemo(() => {
        return [
            {id: 0, name: labels.allcategories, visible: true, productcount: total},
            ...categories,
        ];
    }, [categories, labels.allcategories, total]);

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({
            ...current,
            ...changes,
            page: changes.page ?? 0,
        }));
    };

    const updateForm = (changes: Partial<ProductForm>) => {
        setForm((current) => current ? {...current, ...changes} : current);
    };

    const setProductType = (producttype: string) => {
        setForm((current) => {
            if (!current) {
                return current;
            }

            if (producttype !== "course") {
                setCourseSearch("");
                setCourseOptions([]);
                return {
                    ...current,
                    producttype,
                    courseid: 0,
                    coursename: "",
                    courseshortname: "",
                    coursecategory: "",
                    coursevisible: true,
                };
            }

            return {...current, producttype};
        });
    };

    const updatePriceForm = (changes: Partial<PriceForm>) => {
        setPriceForm((current) => current ? {...current, ...changes} : current);
    };

    const reload = () => {
        setReloadToken((current) => current + 1);
    };

    const reloadPrices = () => {
        setPriceReloadToken((current) => current + 1);
    };

    const changeSort = (sort: string) => {
        setFilters((current) => ({
            ...current,
            sort,
            direction: current.sort === sort && current.direction === "ASC" ? "DESC" : "ASC",
            page: 0,
        }));
    };

    const openNewForm = () => {
        setForm(emptyForm(filters.categoryid));
        setCourseSearch("");
        setCourseOptions([]);
        setCourseError("");
        setFormError("");
        setFieldErrors({});
        setPriceForm(null);
        setPriceFormError("");
        setPriceFieldErrors({});
    };

    const openEditForm = (product: Product) => {
        setForm(productToForm(product));
        setCourseSearch(product.primarycoursename ? `${product.primarycoursename} (${product.primarycourseshortname})` : "");
        setCourseOptions([]);
        setCourseError("");
        setFormError("");
        setFieldErrors({});
        setPriceForm(null);
        setPriceFormError("");
        setPriceFieldErrors({});
    };

    const closeForm = () => {
        setForm(null);
        setCourseSearch("");
        setCourseOptions([]);
        setCourseError("");
        setFormError("");
        setFieldErrors({});
        setPrices([]);
        setPricesError("");
        setPriceForm(null);
        setPriceFormError("");
    };

    const selectCourse = (course: CourseOption) => {
        setForm((current) => {
            if (!current) {
                return current;
            }

            const isnew = current.id === 0;
            const next: ProductForm = {
                ...current,
                courseid: course.id,
                coursename: course.fullname,
                courseshortname: course.shortname,
                coursecategory: course.categoryname,
                coursevisible: course.visible,
            };

            if (isnew || current.name.trim() === "") {
                next.name = course.fullname;
            }
            // Only prefill SKU/slug when their fields are shown; otherwise they are generated
            // server-side and must stay blank so the form does not submit a stale value.
            if (showSku && (isnew || current.sku.trim() === "")) {
                next.sku = course.suggestedsku;
            }
            if (showSlug && (isnew || current.slug.trim() === "")) {
                next.slug = course.suggestedslug;
            }
            if (isnew || current.shortdescription.trim() === "") {
                next.shortdescription = course.summary;
            }

            return next;
        });
        setCourseSearch(`${course.fullname} (${course.shortname})`);
        setCourseOptions([]);
        setCourseError("");
    };

    const clearSelectedCourse = () => {
        updateForm({
            courseid: 0,
            coursename: "",
            courseshortname: "",
            coursecategory: "",
            coursevisible: true,
        });
        setCourseSearch("");
        setCourseOptions([]);
        setCourseError("");
    };

    const openNewPriceForm = () => {
        if (!form || form.id <= 0) {
            return;
        }

        setPriceForm(emptyPriceForm(form.id));
        setPriceFormError("");
        setPriceFieldErrors({});
    };

    const openEditPriceForm = (price: PriceRow) => {
        setPriceForm(priceToForm(price));
        setPriceFormError("");
        setPriceFieldErrors({});
    };

    const closePriceForm = () => {
        setPriceForm(null);
        setPriceFormError("");
    };

    useEffect(() => {
        const newProductButton = document.getElementById("moderncommerce-pricing-new-product");
        const refreshButton = document.getElementById("moderncommerce-pricing-refresh");

        const handleNewProduct = () => openNewForm();
        const handleRefresh = () => reload();

        newProductButton?.addEventListener("click", handleNewProduct);
        refreshButton?.addEventListener("click", handleRefresh);

        return () => {
            newProductButton?.removeEventListener("click", handleNewProduct);
            refreshButton?.removeEventListener("click", handleRefresh);
        };
    });

    // Surface a save error even if the admin scrolled down before pressing Save:
    // the error renders at the top of the drawer body, so scroll it into view.
    useEffect(() => {
        if (formError && drawerBodyRef.current) {
            drawerBodyRef.current.scrollTo({top: 0, behavior: "smooth"});
        }
    }, [formError]);

    const submitForm = async(event: FormEvent) => {
        event.preventDefault();

        if (!form) {
            return;
        }

        const t = (key: string) => labels[key] ?? key;
        const found = runRules(form, {
            name: [required(t("error:required")), maxLength(255, t("error:maxlength"))],
            sku: showSku ? pattern(/^[A-Za-z0-9_-]{0,64}$/, t("error:invalidsku")) : [],
            slug: showSlug ? pattern(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, t("error:invalidslug")) : [],
            regularprice: form.priceenabled
                ? [number(t("error:invalidnumber")), min(0, t("error:minvalue"))]
                : [],
            compareamount: form.saleenabled
                ? [number(t("error:invalidnumber")), min(0, t("error:minvalue")),
                    lteField("regularprice", t("error:salepriceabovebase"))]
                : [],
            stock: form.stockmanaged ? [integer(t("error:invalidinteger")), min(0, t("error:minvalue"))] : [],
            reservedstock: form.stockmanaged
                ? [integer(t("error:invalidinteger")), min(0, t("error:minvalue"))]
                : [],
            displayorder: integer(t("error:invalidinteger")),
        });
        // A course product without a course would be unbuyable.
        if (form.producttype === "course" && Number(form.courseid) <= 0) {
            found.courseid = t("error:required");
        }

        setFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setFormError("");
            focusFirstError(found, ["name", "courseid", "sku", "slug", "regularprice", "compareamount", "stock"]);
            return;
        }

        setSaving(true);
        setFormError("");

        try {
            const result = await callMoodleService<SaveResponse>(
                saveMethodName,
                buildProductPayload(form, showSku, showSlug)
            );

            if (!result.success) {
                const serverErrors = normaliseServerErrors(result.errors);
                if (Object.keys(serverErrors).length > 0) {
                    setFieldErrors(serverErrors);
                    focusFirstError(serverErrors, ["name", "courseid", "sku", "slug", "regularprice"]);
                } else {
                    setFormError(result.message);
                }
                return;
            }

            toast.success(result.message);
            closeForm();
            reload();
        } catch (caught) {
            setFormError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const beginInlineEdit = (product: Product) => {
        if (saving || drawerOpen) {
            return;
        }

        skipBlurRef.current = false;
        setInlineEditId(product.id);
        setInlineValue(product.rawprice > 0 ? String(product.rawprice) : "");
        setError("");
    };

    const cancelInlineEdit = () => {
        setInlineEditId(null);
        setInlineValue("");
    };

    const commitInlineEdit = async(product: Product) => {
        if (inlineEditId !== product.id) {
            return;
        }

        const amount = Math.max(0, toFloat(inlineValue));
        setInlineEditId(null);
        setInlineValue("");

        // Optimistic update so the cell reflects the new price instantly.
        const formatted = formatMoney(amount, currentCurrency);
        setData((current) => current ? {
            ...current,
            items: current.items.map((item) => item.id === product.id
                ? {...item, rawprice: amount, displayprice: formatted, hasprice: true}
                : item),
        } : current);

        setSaving(true);
        setError("");

        try {
            const payloadForm = productToForm(product);
            payloadForm.regularprice = String(amount);
            payloadForm.priceenabled = true;
            const result = await callMoodleService<SaveResponse>(
                saveMethodName,
                buildProductPayload(payloadForm, showSku, showSlug)
            );

            if (result.success) {
                toast.success(result.message);
            } else {
                setError(result.message);
            }
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
            reload();
        }
    };

    const submitPriceForm = async(event: FormEvent) => {
        event.preventDefault();

        if (!priceForm) {
            return;
        }

        const t = (key: string) => labels[key] ?? key;
        const found = runRules(priceForm, {
            amount: [required(t("error:required")), number(t("error:invalidnumber")), min(0, t("error:minvalue"))],
            compareamount: [number(t("error:invalidnumber")), min(0, t("error:minvalue"))],
            minquantity: [integer(t("error:invalidinteger")), min(1, t("error:minvalue"))],
            maxquantity: [integer(t("error:invalidinteger")), gteField("minquantity", t("error:maxqtybelowmin"))],
            enddate: afterField("startdate", t("error:enddatebeforestart")),
        });

        setPriceFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setPriceFormError("");
            focusFirstError(found, ["amount", "compareamount", "minquantity", "maxquantity", "enddate"]);
            return;
        }

        setSaving(true);
        setPriceFormError("");

        try {
            const result = await callMoodleService<PriceSaveResponse>(savePriceMethodName, {
                id: priceForm.id,
                productid: priceForm.productid,
                pricetype: priceForm.pricetype,
                amount: toFloat(priceForm.amount),
                compareamount: toFloat(priceForm.compareamount),
                minquantity: toInt(priceForm.minquantity),
                maxquantity: toInt(priceForm.maxquantity),
                startdate: dateInputToTimestamp(priceForm.startdate),
                enddate: dateInputToTimestamp(priceForm.enddate),
                enabled: priceForm.enabled,
            });

            if (!result.success) {
                setPriceFormError(result.message);
                return;
            }

            toast.success(result.message);
            setPriceForm(null);
            reloadPrices();
            reload();
        } catch (caught) {
            setPriceFormError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const archivePriceRow = async(price: PriceRow) => {
        if (!await confirmDialog({message: labels.archivepriceconfirm, danger: true})) {
            return;
        }

        setSaving(true);
        setPricesError("");

        try {
            const result = await callMoodleService<PriceSaveResponse>(archivePriceMethodName, {id: price.id});
            if (result.success) {
                toast.success(result.message);
                reloadPrices();
                reload();
            } else {
                setPricesError(result.message);
            }
        } catch (caught) {
            setPricesError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setSaving(false);
        }
    };

    const archiveProduct = async(product: Product) => {
        if (!await confirmDialog({message: labels.archiveconfirm, danger: true})) {
            return;
        }

        setSaving(true);
        setError("");

        try {
            const result = await callMoodleService<SaveResponse>(archiveMethodName, {id: product.id});
            if (result.success) {
                toast.success(result.message);
                reload();
            } else {
                setError(result.message);
            }
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
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

    const renderProductActions = (product: Product) => (
        <div className={mcClasses("mc-table-design__actions justify-content-end")}>
            <McTableActionMenu
                label={`${labels.actions}: ${product.name}`}
                items={[
                    {
                        key: "edit",
                        label: labels.edit,
                        icon: "bi bi-pencil",
                        disabled: saving,
                        onClick: () => openEditForm(product),
                    },
                    {
                        key: "archive",
                        label: labels.archiveproduct,
                        icon: "bi bi-archive",
                        danger: true,
                        disabled: saving || product.status === "archived",
                        onClick: () => void archiveProduct(product),
                    },
                ]}
            />
        </div>
    );

    const renderAdvancedPriceActions = (price: PriceRow) => (
        <div className={mcClasses("mc-table-design__actions justify-content-end")}>
            <McTableActionMenu
                label={`${labels.actions}: ${price.pricetypelabel}`}
                items={[
                    {
                        key: "edit",
                        label: labels.edit,
                        icon: "bi bi-pencil",
                        disabled: saving || priceDrawerOpen,
                        onClick: () => openEditPriceForm(price),
                    },
                    {
                        key: "archive",
                        label: labels.archiveprice,
                        icon: "bi bi-archive",
                        danger: true,
                        disabled: saving || priceDrawerOpen || !price.enabled,
                        onClick: () => void archivePriceRow(price),
                    },
                ]}
            />
        </div>
    );

    const renderSwitch = (
        checked: boolean,
        onChange: BoolChange,
        label: string,
        disabled = false
    ) => (
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
        const after = currentCurrency.position === "after";
        const {name, error, required: isRequired} = options;
        return (
            <>
                <div className={mcClasses(after ? "mc-price-input mc-price-input--after" : "mc-price-input")}>
                    <span
                        className={mcClasses(after ? "mc-price-input__symbol mc-price-input__symbol--after" : "mc-price-input__symbol")}
                        aria-hidden="true"
                    >
                        {currentCurrency.symbol}
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

    const renderPriceDrawer = () => {
        if (!priceForm) {
            return null;
        }

        return (
            <McDrawer
                title={priceForm.id > 0 ? labels.editprice : labels.newprice}
                subtitle={form?.name}
                onClose={closePriceForm}
                closeLabel={labels.close}
                disableClose={saving}
                nested
                className="mc-drawer--price-form"
                footer={(
                    <>
                        <McButton
                            className={mcClasses("btn-mc-primary")}
                            disabled={saving}
                            form="mc-price-drawer-form"
                            loading={saving}
                            loadingLabel={labels.saving || "Saving..."}
                            type="submit"
                        >
                            {labels.saveprice}
                        </McButton>
                        <button
                            className={mcClasses("mc-button btn-mc-secondary")}
                            disabled={saving}
                            onClick={closePriceForm}
                            type="button"
                        >
                            {labels.cancel}
                        </button>
                    </>
                )}
            >
                        {priceFormError && (
                            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                                <div className={mcClasses("mc-alert__body")}>{priceFormError}</div>
                            </div>
                        )}

                        <form id="mc-price-drawer-form" noValidate onSubmit={submitPriceForm}>
                            <div className={mcClasses("mc-form-section")}>
                                <div className={mcClasses("mc-form-section__header")}>
                                    <h4 className={mcClasses("mc-form-section__title")}>{labels.pricing}</h4>
                                </div>
                                <div className={mcClasses("mc-form-section__body")}>
                                    <div className={mcClasses("mc-product-form__grid")}>
                                        <label>
                                            <span>{labels.pricetype}</span>
                                            <select
                                                autoFocus
                                                className={mcClasses("mc-select")}
                                                onChange={(event) => updatePriceForm({pricetype: event.target.value})}
                                                value={priceForm.pricetype}
                                            >
                                                {advancedPriceTypes.map((option) => (
                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                ))}
                                            </select>
                                        </label>
                                        <div className={mcClasses("mc-field")}>
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-price-drawer-amount">
                                                {labels.amount}
                                                <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                            </label>
                                            {renderPriceInput(
                                                "mc-price-drawer-amount",
                                                priceForm.amount,
                                                (value) => updatePriceForm({amount: value}),
                                                false,
                                                {name: "amount", error: priceFieldErrors.amount, required: true}
                                            )}
                                        </div>
                                        <div className={mcClasses("mc-field")}>
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-price-drawer-compare">
                                                {labels.compareamount}
                                            </label>
                                            {renderPriceInput(
                                                "mc-price-drawer-compare",
                                                priceForm.compareamount,
                                                (value) => updatePriceForm({compareamount: value}),
                                                false,
                                                {name: "compareamount", error: priceFieldErrors.compareamount}
                                            )}
                                        </div>
                                        <label>
                                            <span>{labels.minquantity}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                min="1"
                                                onChange={(event) => updatePriceForm({minquantity: event.target.value})}
                                                type="number"
                                                value={priceForm.minquantity}
                                            />
                                        </label>
                                        <label>
                                            <span>{labels.maxquantity}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                min="0"
                                                onChange={(event) => updatePriceForm({maxquantity: event.target.value})}
                                                type="number"
                                                value={priceForm.maxquantity}
                                            />
                                        </label>
                                        <label>
                                            <span>{labels.startdate}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => updatePriceForm({startdate: event.target.value})}
                                                type="date"
                                                value={priceForm.startdate}
                                            />
                                        </label>
                                        <label>
                                            <span>{labels.enddate}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => updatePriceForm({enddate: event.target.value})}
                                                type="date"
                                                value={priceForm.enddate}
                                            />
                                        </label>
                                    </div>
                                    <div className={mcClasses("mc-product-form__checks mt-3")}>
                                        {renderSwitch(priceForm.enabled, (value) => updatePriceForm({enabled: value}), labels.enabled)}
                                    </div>
                                </div>
                            </div>
                        </form>
            </McDrawer>
        );
    };

    const renderDrawer = () => {
        if (!form) {
            return null;
        }

        const baseAmount = toFloat(form.regularprice);
        const compareAmount = toFloat(form.compareamount);
        const previewFree = form.priceenabled && baseAmount <= 0;
        const saleActive = form.saleenabled && form.priceenabled && compareAmount > baseAmount;
        const discountPct = computeDiscountPct(compareAmount, baseAmount);
        const savings = computeSavings(compareAmount, baseAmount);

        return (
            <McDrawer
                title={form.id > 0 ? labels.editproduct : labels.newproduct}
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
                            form="mc-product-drawer-form"
                            loading={saving}
                            loadingLabel={labels.saving || "Saving..."}
                            type="submit"
                        >
                            {form.id > 0 ? labels.savechanges : labels.saveproduct}
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

                        <form id="mc-product-drawer-form" noValidate onSubmit={submitForm}>
                            <div className={mcClasses("mc-form-section")}>
                                <div className={mcClasses("mc-form-section__header")}>
                                    <h4 className={mcClasses("mc-form-section__title")}>{labels.productbasics}</h4>
                                </div>
                                <div className={mcClasses("mc-form-section__body")}>
                                    <div className={mcClasses("mc-product-form__grid")}>
                                        <label htmlFor="mc-product-name">
                                            <span>
                                                {labels.productname}
                                                <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                            </span>
                                            <input
                                                autoFocus
                                                aria-describedby={fieldErrors.name ? "mc-product-name-error" : undefined}
                                                aria-invalid={fieldErrors.name ? true : undefined}
                                                aria-required="true"
                                                className={mcClasses("mc-form-control",
                                                    fieldErrors.name ? "mc-field-invalid" : "")}
                                                id="mc-product-name"
                                                maxLength={255}
                                                name="name"
                                                onChange={(event) => updateForm({name: event.target.value})}
                                                required
                                                type="text"
                                                value={form.name}
                                            />
                                            <FieldError id="mc-product-name-error" message={fieldErrors.name} />
                                        </label>
                                        {showSku && (
                                            <label htmlFor="mc-product-sku">
                                                <span>{labels.sku}</span>
                                                <input
                                                    aria-describedby={fieldErrors.sku ? "mc-product-sku-error" : undefined}
                                                    aria-invalid={fieldErrors.sku ? true : undefined}
                                                    className={mcClasses("mc-form-control",
                                                        fieldErrors.sku ? "mc-field-invalid" : "")}
                                                    id="mc-product-sku"
                                                    maxLength={64}
                                                    name="sku"
                                                    onChange={(event) => updateForm({sku: event.target.value})}
                                                    placeholder={labels.autogeneratedhint}
                                                    type="text"
                                                    value={form.sku}
                                                />
                                                <FieldError id="mc-product-sku-error" message={fieldErrors.sku} />
                                            </label>
                                        )}
                                        {showSlug && (
                                            <label htmlFor="mc-product-slug">
                                                <span>{labels.slug}</span>
                                                <input
                                                    aria-describedby={fieldErrors.slug ? "mc-product-slug-error" : undefined}
                                                    aria-invalid={fieldErrors.slug ? true : undefined}
                                                    className={mcClasses("mc-form-control",
                                                        fieldErrors.slug ? "mc-field-invalid" : "")}
                                                    id="mc-product-slug"
                                                    name="slug"
                                                    onChange={(event) => updateForm({slug: event.target.value})}
                                                    placeholder={labels.autogeneratedhint}
                                                    type="text"
                                                    value={form.slug}
                                                />
                                                <FieldError id="mc-product-slug-error" message={fieldErrors.slug} />
                                            </label>
                                        )}
                                        <label>
                                            <span>{labels.producttype}</span>
                                            <select
                                                className={mcClasses("mc-select")}
                                                onChange={(event) => setProductType(event.target.value)}
                                                value={form.producttype}
                                            >
                                                {productTypes.filter((option) => option.value !== "").map((option) => (
                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                ))}
                                            </select>
                                        </label>
                                        <label>
                                            <span>{labels.status}</span>
                                            <select
                                                className={mcClasses("mc-select")}
                                                onChange={(event) => updateForm({status: event.target.value})}
                                                value={form.status}
                                            >
                                                {statusOptions.filter((option) => option.value !== "").map((option) => (
                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                ))}
                                            </select>
                                        </label>
                                        <label>
                                            <span>{labels.category}</span>
                                            <select
                                                className={mcClasses("mc-select")}
                                                onChange={(event) => updateForm({categoryid: Number(event.target.value) || 0})}
                                                value={form.categoryid}
                                            >
                                                {categoryOptions.map((category) => (
                                                    <option key={category.id} value={category.id}>{category.name}</option>
                                                ))}
                                            </select>
                                        </label>
                                        {form.producttype === "course" && (
                                            <div className={mcClasses("mc-course-picker mc-product-form__wide")}>
                                                <label htmlFor="mc-course-picker-input">
                                                    <span>
                                                        {labels.selectmoodlecourse}
                                                        <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                                    </span>
                                                    <input
                                                        autoComplete="off"
                                                        aria-describedby={fieldErrors.courseid
                                                            ? "mc-product-courseid-error" : undefined}
                                                        aria-invalid={fieldErrors.courseid ? true : undefined}
                                                        aria-required="true"
                                                        className={mcClasses("mc-form-control",
                                                            fieldErrors.courseid ? "mc-field-invalid" : "")}
                                                        id="mc-course-picker-input"
                                                        name="courseid"
                                                        // Search box inside the save form: Enter must
                                                        // filter, not submit the product.
                                                        onKeyDown={(event) => {
                                                            if (event.key === "Enter") {
                                                                event.preventDefault();
                                                            }
                                                        }}
                                                        onChange={(event) => {
                                                            setCourseSearch(event.target.value);
                                                            if (form.courseid > 0) {
                                                                updateForm({
                                                                    courseid: 0,
                                                                    coursename: "",
                                                                    courseshortname: "",
                                                                    coursecategory: "",
                                                                    coursevisible: true,
                                                                });
                                                            }
                                                        }}
                                                        placeholder={labels.searchcoursesplaceholder}
                                                        type="search"
                                                        value={courseSearch}
                                                    />
                                                    <FieldError
                                                        id="mc-product-courseid-error"
                                                        message={fieldErrors.courseid}
                                                    />
                                                </label>
                                                {courseError && (
                                                    <div className={mcClasses("mc-course-picker__message mc-field-error")} role="alert">
                                                        <i
                                                            className={mcClasses("bi bi-exclamation-circle mc-field-error__icon")}
                                                            aria-hidden="true"
                                                        />
                                                        <span>{courseError}</span>
                                                    </div>
                                                )}
                                                {courseLoading && (
                                                    <div className={mcClasses("mc-course-picker__message")}>{labels.loading}</div>
                                                )}
                                                {!courseLoading && courseSearch.trim().length >= 2 && courseOptions.length === 0 && !form.courseid && !courseError && (
                                                    <div className={mcClasses("mc-course-picker__message")}>{labels.nocoursesfound}</div>
                                                )}
                                                {courseOptions.length > 0 && (
                                                    <div className={mcClasses("mc-course-picker__results")} role="listbox">
                                                        {courseOptions.map((course) => {
                                                            const duplicate = course.existingproductid > 0 && course.existingproductid !== form.id;
                                                            return (
                                                                <button
                                                                    className={mcClasses("mc-button mc-course-picker__option")}
                                                                    data-mc-button="ghost"
                                                                    disabled={duplicate}
                                                                    key={course.id}
                                                                    onClick={() => selectCourse(course)}
                                                                    type="button"
                                                                >
                                                                    <span className={mcClasses("mc-course-picker__option-main")}>
                                                                        <span className="fw-semibold">{course.fullname}</span>
                                                                        <span className={mcClasses("mc-cell-muted")}>{course.shortname} / {course.categoryname}</span>
                                                                    </span>
                                                                    <span className={mcClasses("mc-course-picker__badges")}>
                                                                        {!course.visible && (
                                                                            <McBadge variant="warning" tone="soft">{labels.coursehidden}</McBadge>
                                                                        )}
                                                                        {duplicate && (
                                                                            <McBadge variant="danger" tone="soft">{labels.coursealreadyhasproduct}</McBadge>
                                                                        )}
                                                                    </span>
                                                                </button>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                                {form.courseid > 0 && (
                                                    <div className={mcClasses("mc-course-picker__selection")}>
                                                        <div>
                                                            <span className={mcClasses("mc-filter-label")}>{labels.selectedcourse}</span>
                                                            <div className="fw-semibold">{form.coursename}</div>
                                                            <div className={mcClasses("mc-cell-muted")}>{form.courseshortname} / {form.coursecategory}</div>
                                                        </div>
                                                        <button
                                                            className={mcClasses("mc-button mc-btn-soft")}
                                                            disabled={saving}
                                                            onClick={clearSelectedCourse}
                                                            type="button"
                                                        >
                                                            {labels.clearcourse}
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                        <label>
                                            <span>{labels.displayorder}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                min="0"
                                                onChange={(event) => updateForm({displayorder: event.target.value})}
                                                type="number"
                                                value={form.displayorder}
                                            />
                                        </label>
                                        <label className={mcClasses("mc-product-form__wide")}>
                                            <span>{labels.shortdescription}</span>
                                            <textarea
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => updateForm({shortdescription: event.target.value})}
                                                rows={2}
                                                value={form.shortdescription}
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
                                    <h4 className={mcClasses("mc-form-section__title")}>{labels.pricing}</h4>
                                </div>
                                <div className={mcClasses("mc-form-section__body")}>
                                    {renderSwitch(form.priceenabled, (value) => updateForm({priceenabled: value}), labels.purchasable)}

                                    <div className={mcClasses("mc-field mt-3")}>
                                        <label className={mcClasses("mc-field-label")} htmlFor="mc-base-price">{labels.baseprice}</label>
                                        {renderPriceInput("mc-base-price", form.regularprice, (value) => updateForm({regularprice: value}), !form.priceenabled, {name: "regularprice", error: fieldErrors.regularprice})}
                                    </div>

                                    <div className={mcClasses("mc-field")}>
                                        {renderSwitch(
                                            form.saleenabled,
                                            (value) => updateForm({saleenabled: value, compareamount: value ? form.compareamount : ""}),
                                            labels.putonsale,
                                            !form.priceenabled
                                        )}
                                    </div>

                                    {form.priceenabled && form.saleenabled && (
                                        <div className={mcClasses("mc-sale-panel")}>
                                            <div className={mcClasses("mc-field")}>
                                                <label className={mcClasses("mc-field-label")} htmlFor="mc-compare-price">{labels.compareatprice}</label>
                                                {renderPriceInput("mc-compare-price", form.compareamount, (value) => updateForm({compareamount: value}), false, {name: "compareamount", error: fieldErrors.compareamount})}
                                                <div className={mcClasses("mc-field-help")}>{labels.salehint}</div>
                                            </div>
                                        </div>
                                    )}

                                    {form.priceenabled && (
                                        <div className={mcClasses("mc-price-preview")}>
                                            <span className={mcClasses("mc-field-label")}>{labels.pricepreview}</span>
                                            <div className={mcClasses(previewFree ? "mc-price mc-price--free" : "mc-price")}>
                                                {saleActive && (
                                                    <span className={mcClasses("mc-price__original")}>{formatMoney(compareAmount, currentCurrency)}</span>
                                                )}
                                                <span className={mcClasses("mc-price__main")}>
                                                    {previewFree ? labels.freeproduct : formatMoney(baseAmount, currentCurrency)}
                                                </span>
                                                {saleActive && (
                                                    <span className={mcClasses("mc-price__sale")}>-{discountPct}%</span>
                                                )}
                                            </div>
                                            {form.saleenabled && !saleActive && (
                                                <div className={mcClasses("mc-field-help")}>{labels.nosaleprice}</div>
                                            )}
                                            {saleActive && (
                                                <div className={mcClasses("mc-field-help")}>
                                                    {labels.yousave.replace("{$a}", formatMoney(savings, currentCurrency))}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className={mcClasses("mc-form-section")}>
                                <div className={mcClasses("mc-form-section__header")}>
                                    <h4 className={mcClasses("mc-form-section__title")}>{labels.inventory}</h4>
                                </div>
                                <div className={mcClasses("mc-form-section__body")}>
                                    <div className={mcClasses("mc-product-form__checks")}>
                                        {renderSwitch(form.stockmanaged, (value) => updateForm({stockmanaged: value}), labels.stockmanaged)}
                                        {renderSwitch(form.allowbackorder, (value) => updateForm({allowbackorder: value}), labels.allowbackorder)}
                                    </div>
                                    {form.stockmanaged && (
                                        <div className={mcClasses("mc-product-form__grid mt-3")}>
                                            <label>
                                                <span>{labels.stock}</span>
                                                <input
                                                    className={mcClasses("mc-form-control")}
                                                    min="0"
                                                    onChange={(event) => updateForm({stock: event.target.value})}
                                                    type="number"
                                                    value={form.stock}
                                                />
                                            </label>
                                            <label>
                                                <span>{labels.reservedstock}</span>
                                                <input
                                                    className={mcClasses("mc-form-control")}
                                                    min="0"
                                                    onChange={(event) => updateForm({reservedstock: event.target.value})}
                                                    type="number"
                                                    value={form.reservedstock}
                                                />
                                            </label>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </form>

                        {form.id > 0 ? (
                            <div className={mcClasses("mc-form-section")}>
                                <div className={mcClasses("mc-form-section__header d-flex justify-content-between align-items-start gap-3")}>
                                    <div>
                                        <h4 className={mcClasses("mc-form-section__title")}>{labels.advancedpricing}</h4>
                                        <div className={mcClasses("mc-field-help mt-1")}>{labels.advancedpricingdesc}</div>
                                    </div>
                                    <div className={mcClasses("mc-product-table__actions")}>
                                        <button
                                            className={mcClasses("mc-button mc-btn-soft")}
                                            disabled={saving}
                                            onClick={reloadPrices}
                                            type="button"
                                        >
                                            {labels.refresh}
                                        </button>
                                        <button
                                            className={mcClasses("mc-button mc-btn-soft")}
                                            disabled={saving || priceForm !== null}
                                            onClick={openNewPriceForm}
                                            type="button"
                                        >
                                            {labels.addadvancedprice}
                                        </button>
                                    </div>
                                </div>
                                <div className={mcClasses("mc-form-section__body")}>
                                    {pricesError && (
                                        <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                                            <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                                            <div className={mcClasses("mc-alert__body")}>{pricesError}</div>
                                        </div>
                                    )}

                                    <McTableFrame>
                                            <table className={mcClasses("table mc-table mc-price-ledger__table mb-0")}>
                                                <thead>
                                                    <tr>
                                                        <th scope="col">{labels.pricetype}</th>
                                                        <th scope="col" className="text-end">{labels.amount}</th>
                                                        <th scope="col">{labels.quantityrange}</th>
                                                        <th scope="col">{labels.pricewindow}</th>
                                                        <th scope="col">{labels.status}</th>
                                                        <th scope="col" className="text-end">{labels.actions}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {advancedPrices.length === 0 && !pricesLoading ? (
                                                        <tr>
                                                            <td colSpan={6}>
                                                                <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                                    <span className={mcClasses("mc-empty__icon")}><i className="bi bi-tags" aria-hidden="true" /></span>
                                                                    <p className={mcClasses("mc-empty__title")}>{labels.noprices}</p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ) : (
                                                        advancedPrices.map((price) => (
                                                            <tr key={price.id}>
                                                                <td>
                                                                    <McBadge variant="neutral" tone="soft">{price.pricetypelabel}</McBadge>
                                                                </td>
                                                                <td className="text-end">
                                                                    {price.displaycompareamount && (
                                                                        <div className={mcClasses("mc-product-table__compare")}>{price.displaycompareamount}</div>
                                                                    )}
                                                                    <div className="fw-semibold">{price.displayamount}</div>
                                                                </td>
                                                                <td>{formatQuantityRange(price)}</td>
                                                                <td>{formatPriceWindow(price)}</td>
                                                                <td>
                                                                    <McBadge variant={price.active ? "success" : "neutral"} tone="soft" dot>
                                                                        {price.active ? labels.active : labels.inactive}
                                                                    </McBadge>
                                                                </td>
                                                                <td className="text-end">
                                                                    {renderAdvancedPriceActions(price)}
                                                                </td>
                                                            </tr>
                                                        ))
                                                    )}
                                                    {pricesLoading && (
                                                        <tr>
                                                            <td colSpan={6}>
                                                                <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                                                            </td>
                                                        </tr>
                                                    )}
                                                </tbody>
                                            </table>
                                        </McTableFrame>
                                </div>
                            </div>
                        ) : (
                            <div className={mcClasses("mc-field-help px-1")}>
                                {labels.saveproductfirst}
                            </div>
                        )}
            </McDrawer>
        );
    };

    const renderPriceCell = (product: Product) => {
        if (inlineEditId === product.id) {
            return (
                <div className={mcClasses("mc-price-input mc-price-input--inline")}>
                    <input
                        autoFocus
                        className={mcClasses("mc-form-control")}
                        disabled={saving}
                        min="0"
                        onBlur={() => {
                            if (skipBlurRef.current) {
                                skipBlurRef.current = false;
                                return;
                            }
                            commitInlineEdit(product);
                        }}
                        onChange={(event) => setInlineValue(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === "Enter") {
                                event.preventDefault();
                                skipBlurRef.current = true;
                                commitInlineEdit(product);
                            } else if (event.key === "Escape") {
                                event.preventDefault();
                                skipBlurRef.current = true;
                                cancelInlineEdit();
                            }
                        }}
                        step="0.01"
                        type="number"
                        value={inlineValue}
                    />
                </div>
            );
        }

        return (
            <button
                className={mcClasses("mc-button mc-inline-price")}
                data-mc-button="ghost"
                disabled={saving || drawerOpen}
                onClick={() => beginInlineEdit(product)}
                title={labels.editbaseprice}
                type="button"
            >
                {product.hasprice ? (
                    <>
                        {product.onsale && (
                            <div className={mcClasses("mc-product-table__compare")}>
                                {product.displaycompareamount}
                            </div>
                        )}
                        <div className="fw-semibold">{product.displayprice}</div>
                        {product.onsale && (
                            <McBadge variant="success" tone="soft">{labels.onsale}</McBadge>
                        )}
                    </>
                ) : (
                    <McBadge variant="warning" tone="soft">{labels.noprice}</McBadge>
                )}
            </button>
        );
    };

    return (
        <section className={mcClasses("mc-product-admin")} aria-label={labels.title}>
            {stats && (
                <div className={mcClasses("mc-stat-strip")} aria-label={labels.title}>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--primary")}>
                        <i className="bi bi-grid mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.totalproducts}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.totalproducts)}</strong>
                        </div>
                        <i className="bi bi-grid mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                        <i className="bi bi-tag mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.priced}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.pricedproducts)}</strong>
                        </div>
                        <i className="bi bi-tag mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--warning")}>
                        <i className="bi bi-exclamation-circle mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.unpriced}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.unpricedproducts)}</strong>
                        </div>
                        <i className="bi bi-exclamation-circle mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--info")}>
                        <i className="bi bi-layers mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.bundles}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.bundleproducts)}</strong>
                        </div>
                        <i className="bi bi-layers mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                </div>
            )}

            <McTableCard
                className={mcClasses("mc-pricing-table-card")}
                title={<h2 className={mcClasses("mc-card-title")}>{labels.title}</h2>}
                actions={(
                    <button
                        className={mcClasses("mc-button btn-mc-primary")}
                        id="moderncommerce-pricing-new-product"
                        onClick={openNewForm}
                        type="button"
                    >
                        <i className="bi bi-plus-lg me-1" aria-hidden="true" />
                        {labels.newproduct}
                    </button>
                )}
                toolbar={(
                    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                        <div className={mcClasses("mc-product-toolbar__search")}>
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-product-search">
                                {labels.search}
                            </label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-product-search"
                                onChange={(event) => setSearchInput(event.target.value)}
                                placeholder={labels.searchplaceholder}
                                type="search"
                                value={searchInput}
                            />
                        </div>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.producttype}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({producttype: event.target.value})}
                                value={filters.producttype}
                            >
                                {productTypes.map((option) => (
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
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.price}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({pricingstatus: event.target.value})}
                                value={filters.pricingstatus}
                            >
                                {pricingStatuses.map((option) => (
                                    <option key={option.value || "all"} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                        </label>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.category}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({categoryid: Number(event.target.value) || 0})}
                                value={filters.categoryid}
                            >
                                {categoryOptions.map((category) => (
                                    <option key={category.id} value={category.id}>
                                        {category.name} ({formatCount(category.productcount)})
                                    </option>
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
                alert={error && (
                    <div className={mcClasses("mc-table-design-alert")}>
                        <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                            <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                            <div className={mcClasses("mc-alert__body")}>{error}</div>
                        </div>
                    </div>
                )}
                footer={(
                    <McTableFooter
                        summary={(
                            <>
                                <span>
                                    {labels.showing} {formatCount(range.from)}-{formatCount(range.to)} / {formatCount(total)}
                                </span>
                                {loading && <span>{labels.loading}</span>}
                            </>
                        )}
                        pagination={(
                            <McTablePagination
                                previousLabel={labels.previous}
                                nextLabel={labels.next}
                                page={filters.page + 1}
                                totalPages={totalPages}
                                previousDisabled={loading || filters.page <= 0}
                                nextDisabled={loading || filters.page >= totalPages - 1}
                                onPrevious={() => updateFilters({page: Math.max(0, filters.page - 1)})}
                                onNext={() => updateFilters({page: filters.page + 1})}
                            />
                        )}
                    />
                )}
            >
                <table className={mcClasses("table mc-table mc-product-table mb-0")}>
                            <thead>
                                <tr>
                                    <th scope="col" {...sortHeaderProps("name")}>{renderSortButton("name", labels.products)}</th>
                                    <th scope="col" {...sortHeaderProps("producttype")}>{renderSortButton("producttype", labels.type)}</th>
                                    <th scope="col" {...sortHeaderProps("status")}>{renderSortButton("status", labels.status)}</th>
                                    <th scope="col">{labels.category}</th>
                                    <th scope="col" className="text-end" {...sortHeaderProps("price")}>
                                        {renderSortButton("price", labels.price, "text-end")}
                                    </th>
                                    <th scope="col" className="text-center" {...sortHeaderProps("stock")}>
                                        {renderSortButton("stock", labels.stock, "text-center")}
                                    </th>
                                    <th scope="col" className="text-center" {...sortHeaderProps("sold")}>
                                        {renderSortButton("sold", labels.sold, "text-center")}
                                    </th>
                                    <th scope="col" className="text-end" {...sortHeaderProps("timemodified")}>
                                        {renderSortButton("timemodified", labels.updated, "text-end")}
                                    </th>
                                    <th scope="col" className="text-end">{labels.actions}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.length === 0 && !loading ? (
                                    <tr>
                                        <td colSpan={9}>
                                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                <span className={mcClasses("mc-empty__icon")}>
                                                    <i className="bi bi-search" aria-hidden="true" />
                                                </span>
                                                <p className={mcClasses("mc-empty__title")}>{labels.noresults}</p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    items.map((product) => (
                                        <tr key={product.id}>
                                            <td>
                                                <div className="fw-semibold">{product.name}</div>
                                                <div className={mcClasses("mc-cell-muted mc-mono")}>{product.sku}</div>
                                            </td>
                                            <td>
                                                <McBadge variant="neutral" tone="soft">
                                                    {optionLabel(productTypes, product.producttype, product.producttype)}
                                                </McBadge>
                                                {product.coursecount > 0 && (
                                                    <div className={mcClasses("mc-cell-muted mt-1")}>
                                                        {formatCount(product.coursecount)} {labels.courses}
                                                    </div>
                                                )}
                                            </td>
                                            <td>
                                                <McBadge variant={statusBadgeVariant(product.status)} tone="soft" dot>
                                                    {optionLabel(statusOptions, product.status, product.status)}
                                                </McBadge>
                                                <div className={mcClasses("mc-product-table__flags")}>
                                                    {product.visible ? labels.visible : labels.hidden}
                                                    {product.featured && <> / {labels.featured}</>}
                                                </div>
                                            </td>
                                            <td className={mcClasses("mc-cell-muted")}>{product.categorynames || "-"}</td>
                                            <td className="text-end">
                                                {renderPriceCell(product)}
                                            </td>
                                            <td className="text-center">
                                                <span>{product.stocklabel}</span>
                                                {product.allowbackorder && (
                                                    <div className={mcClasses("mc-cell-muted")}>{labels.backorders}</div>
                                                )}
                                            </td>
                                            <td className="text-center">{formatQuantity(product.soldcount)}</td>
                                            <td className={mcClasses("text-end mc-cell-muted")}>
                                                {formatDate(product.timemodified)}
                                            </td>
                                            <td className="text-end">
                                                {renderProductActions(product)}
                                            </td>
                                        </tr>
                                    ))
                                )}
                                {loading && (
                                    <tr>
                                        <td colSpan={9}>
                                            <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                </table>
            </McTableCard>

            {renderDrawer()}
            {renderPriceDrawer()}
        </section>
    );
}

// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin invoices manager for Modern Commerce.
 *
 * @module     local_moderncommerce/invoices_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef, useState} from "react";
import type {FormEvent} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McDrawer} from "./drawer";
import {McTableActionMenu, McTableCard, McTableFooter, McTableFrame, McTablePagination} from "./table_components";
import {confirmDialog} from "./modal";
import {FieldError} from "./form";
import {focusFirstError, maxLength, normaliseServerErrors, required, requiredId, runRules} from "./validation";
import type {FieldErrorMap, WithFieldErrors} from "./validation";

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

type Filters = {
    search: string;
    status: string;
    page: number;
    perpage: number;
};

type Invoice = {
    id: number;
    invoicenumber: string;
    customerid: number;
    customername: string;
    customeremail: string;
    rawtotal: number;
    displaytotal: string;
    status: string;
    statuslabel: string;
    statusclass: string;
    itemcount: number;
    duedate: string;
    created: string;
};

type Stats = {
    total: number;
    paid: number;
    pending: number;
    overdue: number;
    displayoutstanding: string;
};

type ListResponse = {
    items: Invoice[];
    total: number;
    page: number;
    perpage: number;
    stats: Stats;
};

type InvoiceItem = {
    description: string;
    quantity: number;
    unitprice: number;
};

type InvoiceDetail = {
    id: number;
    invoicenumber: string;
    customerid: number;
    customername: string;
    customeremail: string;
    status: string;
    currency: string;
    duedate: number;
    notes: string;
    terms: string;
    subtotal: number;
    tax: number;
    total: number;
    items: Array<InvoiceItem & {id: number; total: number}>;
};

type SaveResponse = WithFieldErrors & {
    success: boolean;
    invoiceid: number;
    message: string;
};

type StatusResponse = {
    success: boolean;
    message: string;
    status: string;
};

type CustomerOption = {
    id: number;
    fullname: string;
    email: string;
};

type InvoiceForm = {
    id: number;
    customerid: number;
    customername: string;
    customeremail: string;
    customerquery: string;
    invoicenumber: string;
    status: string;
    duedate: string;
    notes: string;
    terms: string;
    tax: string;
    items: InvoiceItem[];
};

type InvoicesAdminProps = {
    listMethodName: string;
    getMethodName: string;
    saveMethodName: string;
    setStatusMethodName: string;
    searchCustomersMethodName: string;
    currency: Currency;
    statusOptions: SelectOption[];
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

const getVisibleRange = (total: number, page: number, perpage: number): {from: number; to: number} => {
    if (total <= 0) {
        return {from: 0, to: 0};
    }

    return {
        from: page * perpage + 1,
        to: Math.min((page + 1) * perpage, total),
    };
};

const tsToDateInput = (timestamp: number): string => {
    if (!timestamp) {
        return "";
    }
    const date = new Date(timestamp * 1000);
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${date.getFullYear()}-${month}-${day}`;
};

const dateInputToTs = (value: string): number => {
    if (!value) {
        return 0;
    }
    const ts = new Date(`${value}T00:00:00`).getTime();
    return Number.isFinite(ts) ? Math.floor(ts / 1000) : 0;
};

const emptyForm = (): InvoiceForm => ({
    id: 0,
    customerid: 0,
    customername: "",
    customeremail: "",
    customerquery: "",
    invoicenumber: "",
    status: "draft",
    duedate: "",
    notes: "",
    terms: "",
    tax: "0",
    items: [{description: "", quantity: 1, unitprice: 0}],
});

const detailToForm = (detail: InvoiceDetail): InvoiceForm => ({
    id: detail.id,
    customerid: detail.customerid,
    customername: detail.customername,
    customeremail: detail.customeremail,
    customerquery: "",
    invoicenumber: detail.invoicenumber,
    status: detail.status,
    duedate: tsToDateInput(detail.duedate),
    notes: detail.notes,
    terms: detail.terms,
    tax: String(detail.tax),
    items: detail.items.length > 0
        ? detail.items.map((item) => ({description: item.description, quantity: item.quantity, unitprice: item.unitprice}))
        : [{description: "", quantity: 1, unitprice: 0}],
});

export default function InvoicesAdmin({
    listMethodName,
    getMethodName,
    saveMethodName,
    setStatusMethodName,
    searchCustomersMethodName,
    currency,
    statusOptions,
    perPageOptions,
    labels,
}: InvoicesAdminProps) {
    useModernCommerceClassSync();
    const [filters, setFilters] = useState<Filters>({search: "", status: "", page: 0, perpage: 10});
    const [searchInput, setSearchInput] = useState("");
    const [data, setData] = useState<ListResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [busyId, setBusyId] = useState(0);
    const [reloadToken, setReloadToken] = useState(0);
    const [form, setForm] = useState<InvoiceForm | null>(null);
    const [formError, setFormError] = useState("");
    const [fieldErrors, setFieldErrors] = useState<FieldErrorMap>({});
    const [saving, setSaving] = useState(false);
    const [customerOptions, setCustomerOptions] = useState<CustomerOption[]>([]);
    const drawerBodyRef = useRef<HTMLDivElement>(null);

    const formatMoney = (amount: number): string => {
        const value = amount.toFixed(currency.decimals);
        return currency.position === "after" ? `${value} ${currency.symbol}` : `${currency.symbol}${value}`;
    };

    const openNewInvoice = () => {
        setForm(emptyForm());
        setFormError("");
    };

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
        const newButton = document.getElementById("moderncommerce-invoices-new");
        const refreshButton = document.getElementById("moderncommerce-invoices-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        newButton?.addEventListener("click", openNewInvoice);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            newButton?.removeEventListener("click", openNewInvoice);
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    useEffect(() => {
        if (formError && drawerBodyRef.current) {
            drawerBodyRef.current.scrollTo({top: 0, behavior: "smooth"});
        }
    }, [formError]);

    // Customer typeahead for the editor.
    useEffect(() => {
        if (!form) {
            return;
        }
        const query = form.customerquery.trim();
        if (query.length < 2) {
            setCustomerOptions([]);
            return;
        }

        let cancelled = false;
        const timer = window.setTimeout(() => {
            void callMoodleService<{items: CustomerOption[]}>(searchCustomersMethodName, {query, limit: 20})
                .then((result) => {
                    if (!cancelled) {
                        setCustomerOptions(result.items);
                    }
                })
                .catch(() => {
                    if (!cancelled) {
                        setCustomerOptions([]);
                    }
                });
        }, 300);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [form?.customerquery, searchCustomersMethodName, form]);

    const total = data?.total ?? 0;
    const stats = data?.stats;
    const totalPages = Math.max(1, Math.ceil(total / filters.perpage));
    const range = getVisibleRange(total, filters.page, filters.perpage);

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({...current, ...changes, page: changes.page ?? 0}));
    };

    const updateForm = (changes: Partial<InvoiceForm>) => {
        setForm((current) => current ? {...current, ...changes} : current);
    };

    const openEdit = (invoice: Invoice) => {
        setFormError("");
        void callMoodleService<InvoiceDetail>(getMethodName, {id: invoice.id})
            .then((detail) => setForm(detailToForm(detail)))
            .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : String(caught)));
    };

    const closeForm = () => {
        setForm(null);
        setFormError("");
        setCustomerOptions([]);
    };

    const updateItem = (index: number, changes: Partial<InvoiceItem>) => {
        setForm((current) => {
            if (!current) {
                return current;
            }
            const items = current.items.map((item, idx) => idx === index ? {...item, ...changes} : item);
            return {...current, items};
        });
    };

    const addItem = () => {
        updateForm({items: [...(form?.items ?? []), {description: "", quantity: 1, unitprice: 0}]});
    };

    const removeItem = (index: number) => {
        updateForm({items: form?.items.filter((_, idx) => idx !== index) ?? []});
    };

    const subtotal = (form?.items ?? []).reduce((sum, item) => sum + (item.quantity * item.unitprice), 0);
    const taxValue = Number(form?.tax) || 0;
    const grandTotal = subtotal + taxValue;

    const submitForm = async(event?: FormEvent) => {
        event?.preventDefault();

        if (!form) {
            return;
        }

        const t = (key: string) => labels[key] ?? key;
        const found = runRules(form, {
            customerid: requiredId(labels.searchcustomer ?? t("error:required")),
            invoicenumber: maxLength(50, t("error:maxlength")),
            status: required(t("error:selectoption")),
        });
        // An invoice with no priced lines cannot be paid.
        if (form.items.filter((item) => item.description.trim() !== "").length === 0) {
            found.items = t("error:invoiceneedsitems");
        }

        setFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setFormError("");
            focusFirstError(found, ["customerid", "invoicenumber", "status", "items"]);
            return;
        }

        setSaving(true);
        setFormError("");

        try {
            const result = await callMoodleService<SaveResponse>(saveMethodName, {
                id: form.id,
                userid: form.customerid,
                invoicenumber: form.invoicenumber,
                status: form.status,
                tax: taxValue,
                duedate: dateInputToTs(form.duedate),
                notes: form.notes,
                terms: form.terms,
                items: form.items
                    .filter((item) => item.description.trim() !== "")
                    .map((item) => ({
                        description: item.description,
                        quantity: item.quantity,
                        unitprice: item.unitprice,
                    })),
            });

            if (!result.success) {
                const serverErrors = normaliseServerErrors(result.errors);
                if (Object.keys(serverErrors).length > 0) {
                    setFieldErrors(serverErrors);
                    focusFirstError(serverErrors, ["customerid", "invoicenumber", "status", "items"]);
                } else {
                    setFormError(result.message);
                }
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

    const changeStatus = async(invoice: Invoice, action: string) => {
        if (action === "delete" && !await confirmDialog({message: labels.deleteconfirm, danger: true})) {
            return;
        }

        setBusyId(invoice.id);
        setError("");

        try {
            const result = await callMoodleService<StatusResponse>(setStatusMethodName, {invoiceid: invoice.id, action});
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setBusyId(0);
        }
    };

    const renderInvoiceActions = (invoice: Invoice) => (
        <div className={mcClasses("mc-table-design__actions justify-content-end")}>
            <McTableActionMenu
                label={`${labels.actions}: ${invoice.invoicenumber}`}
                items={[
                    {
                        key: "edit",
                        label: labels.edit,
                        icon: "bi bi-pencil",
                        disabled: busyId === invoice.id,
                        onClick: () => openEdit(invoice),
                    },
                    {
                        key: "delete",
                        label: labels.delete,
                        icon: "bi bi-trash",
                        danger: true,
                        disabled: busyId === invoice.id,
                        onClick: () => void changeStatus(invoice, "delete"),
                    },
                ]}
            />
        </div>
    );

    const renderLineItemActions = (index: number) => (
        <div className={mcClasses("mc-table-design__actions justify-content-end")}>
            <McTableActionMenu
                label={labels.actions}
                items={[
                    {
                        key: "delete",
                        label: labels.delete,
                        icon: "bi bi-trash",
                        danger: true,
                        onClick: () => removeItem(index),
                    },
                ]}
            />
        </div>
    );

    const renderInvoiceDrawer = () => {
        if (!form) {
            return null;
        }

        return (
            <McDrawer
                title={form.id > 0 ? labels.editinvoice : labels.createinvoice}
                subtitle={form.id > 0 && form.invoicenumber ? form.invoicenumber : undefined}
                onClose={closeForm}
                closeLabel={labels.cancel}
                disableClose={saving}
                className="mc-drawer--invoice-form"
                bodyRef={drawerBodyRef}
                footer={(
                    <>
                        <McButton
                            className={mcClasses("btn-mc-primary")}
                            disabled={saving}
                            form="mc-invoice-drawer-form"
                            loading={saving}
                            loadingLabel={labels.saving || "Saving..."}
                            type="submit"
                        >
                            {labels.save}
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

                        <form id="mc-invoice-drawer-form" noValidate onSubmit={submitForm}>
                            <div className={mcClasses("mc-form-section")}>
                                <div className={mcClasses("mc-form-section__body")}>
                                    <div className={mcClasses("mc-product-form__grid")}>
                                        <div className={mcClasses("mc-course-picker mc-invoice-customer-picker mc-product-form__wide")}>
                                            <label htmlFor="mc-invoice-customer">
                                                <span>
                                                    {labels.customerlabel}
                                                    <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                                                </span>
                                                <input
                                                    autoComplete="off"
                                                    autoFocus
                                                    aria-describedby={fieldErrors.customerid
                                                        ? "mc-invoice-customer-error" : undefined}
                                                    aria-invalid={fieldErrors.customerid ? true : undefined}
                                                    aria-required="true"
                                                    className={mcClasses("mc-form-control",
                                                        fieldErrors.customerid ? "mc-field-invalid" : "")}
                                                    id="mc-invoice-customer"
                                                    name="customerid"
                                                    // Search box inside the save form: Enter must
                                                    // filter customers, not save the invoice.
                                                    onKeyDown={(event) => {
                                                        if (event.key === "Enter") {
                                                            event.preventDefault();
                                                        }
                                                    }}
                                                    onChange={(event) => updateForm({
                                                        customerquery: event.target.value,
                                                        customerid: 0,
                                                        customername: "",
                                                    })}
                                                    placeholder={labels.searchcustomer}
                                                    type="search"
                                                    value={form.customerid > 0
                                                        ? `${form.customername} (${form.customeremail})`
                                                        : form.customerquery}
                                                />
                                                <FieldError
                                                    id="mc-invoice-customer-error"
                                                    message={fieldErrors.customerid}
                                                />
                                            </label>
                                            {customerOptions.length > 0 && form.customerid <= 0 && (
                                                <div className={mcClasses("mc-course-picker__results")} role="listbox">
                                                    {customerOptions.map((option) => (
                                                        <button
                                                            className={mcClasses("mc-button mc-course-picker__option")}
                                                            data-mc-button="ghost"
                                                            key={option.id}
                                                            onClick={() => {
                                                                updateForm({
                                                                    customerid: option.id,
                                                                    customername: option.fullname,
                                                                    customeremail: option.email,
                                                                    customerquery: "",
                                                                });
                                                                setCustomerOptions([]);
                                                            }}
                                                            role="option"
                                                            type="button"
                                                        >
                                                            <span className={mcClasses("mc-course-picker__option-main")}>
                                                                <strong>{option.fullname}</strong>
                                                                <small>{option.email}</small>
                                                            </span>
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                        <label>
                                            <span>{labels.invoicenumber}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => updateForm({invoicenumber: event.target.value})}
                                                type="text"
                                                value={form.invoicenumber}
                                            />
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
                                            <span>{labels.duedatefield}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => updateForm({duedate: event.target.value})}
                                                type="date"
                                                value={form.duedate}
                                            />
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div className={mcClasses("mc-form-section")}>
                                <div className={mcClasses("mc-form-section__header d-flex justify-content-between align-items-center gap-3")}>
                                    <h4 className={mcClasses("mc-form-section__title")}>{labels.items}</h4>
                                    <button className={mcClasses("mc-button mc-btn-soft")} onClick={addItem} type="button">
                                        {labels.addlineitem}
                                    </button>
                                </div>
                                <div className={mcClasses("mc-form-section__body")}>
                                    <FieldError id="mc-invoice-items-error" message={fieldErrors.items} />
                                    <McTableFrame>
                                        <table
                                            className={mcClasses("table mc-table mc-product-table mc-invoice-items-table mb-0")}
                                            aria-label={labels.items}
                                        >
                                            <thead>
                                                <tr>
                                                    <th scope="col">{labels.description}</th>
                                                    <th scope="col" className="text-end">{labels.quantity}</th>
                                                    <th scope="col" className="text-end">{labels.unitprice}</th>
                                                    <th scope="col" className="text-end">{labels.amount}</th>
                                                    <th scope="col" className="text-end" aria-label={labels.actions} />
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {form.items.map((item, index) => (
                                                    <tr key={index}>
                                                        <td>
                                                            <input
                                                                // The "needs a line item" error belongs on the
                                                                // first description box, which is where the user
                                                                // has to type to resolve it.
                                                                aria-describedby={index === 0 && fieldErrors.items
                                                                    ? "mc-invoice-items-error" : undefined}
                                                                aria-invalid={index === 0 && fieldErrors.items
                                                                    ? true : undefined}
                                                                className={mcClasses("mc-form-control",
                                                                    index === 0 && fieldErrors.items ? "mc-field-invalid" : "")}
                                                                name={index === 0 ? "items" : undefined}
                                                                onChange={(event) => updateItem(index, {description: event.target.value})}
                                                                type="text"
                                                                value={item.description}
                                                            />
                                                        </td>
                                                        <td className="text-end">
                                                            <input
                                                                className={mcClasses("mc-form-control text-end mc-input-narrow")}
                                                                min="1"
                                                                onChange={(event) => updateItem(
                                                                    index,
                                                                    {quantity: Number(event.target.value) || 1}
                                                                )}
                                                                type="number"
                                                                value={item.quantity}
                                                            />
                                                        </td>
                                                        <td className="text-end">
                                                            <input
                                                                className={mcClasses("mc-form-control text-end mc-input-narrow")}
                                                                min="0"
                                                                onChange={(event) => updateItem(
                                                                    index,
                                                                    {unitprice: Number(event.target.value) || 0}
                                                                )}
                                                                step="0.01"
                                                                type="number"
                                                                value={item.unitprice}
                                                            />
                                                        </td>
                                                        <td className="text-end fw-semibold">
                                                            {formatMoney(item.quantity * item.unitprice)}
                                                        </td>
                                                        <td className="text-end">
                                                            {renderLineItemActions(index)}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </McTableFrame>
                                </div>
                            </div>

                            <div className={mcClasses("mc-form-section")}>
                                <div className={mcClasses("mc-form-section__body")}>
                                    <div className={mcClasses("mc-product-form__grid")}>
                                        <label className={mcClasses("mc-product-form__wide")}>
                                            <span>{labels.notes}</span>
                                            <textarea
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => updateForm({notes: event.target.value})}
                                                rows={2}
                                                value={form.notes}
                                            />
                                        </label>
                                        <label className={mcClasses("mc-product-form__wide")}>
                                            <span>{labels.terms}</span>
                                            <textarea
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => updateForm({terms: event.target.value})}
                                                rows={2}
                                                value={form.terms}
                                            />
                                        </label>
                                        <label>
                                            <span>{labels.tax}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                min="0"
                                                onChange={(event) => updateForm({tax: event.target.value})}
                                                step="0.01"
                                                type="number"
                                                value={form.tax}
                                            />
                                        </label>
                                    </div>
                                    <dl className={mcClasses("mc-order-summary__body mb-0 mt-2")}>
                                        <div className="d-flex justify-content-between py-1">
                                            <dt className="fw-normal">{labels.subtotal}</dt>
                                            <dd className="mb-0">{formatMoney(subtotal)}</dd>
                                        </div>
                                        <div className="d-flex justify-content-between py-1">
                                            <dt className="fw-normal">{labels.tax}</dt>
                                            <dd className="mb-0">{formatMoney(taxValue)}</dd>
                                        </div>
                                        <div className="d-flex justify-content-between py-2 border-top fw-semibold">
                                            <dt>{labels.total}</dt>
                                            <dd className="mb-0">{formatMoney(grandTotal)}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
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
                        <i className="bi bi-receipt-cutoff mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.totalinvoices}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.total)}</strong>
                        </div>
                        <i className="bi bi-receipt-cutoff mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                        <i className="bi bi-check-circle mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.paidinvoices}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.paid)}</strong>
                        </div>
                        <i className="bi bi-check-circle-fill mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--warning")}>
                        <i className="bi bi-hourglass-split mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.pendinginvoices}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.pending)}</strong>
                        </div>
                        <i className="bi bi-hourglass-split mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--info")}>
                        <i className="bi bi-cash-coin mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.outstanding}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{stats.displayoutstanding}</strong>
                        </div>
                        <i className="bi bi-cash-coin mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                </div>
            )}

            <McTableCard
                title={<h2 className={mcClasses("mc-card-title")}>{labels.title}</h2>}
                actions={(
                    <button
                        className={mcClasses("mc-button btn-mc-primary")}
                        id="moderncommerce-invoices-new"
                        onClick={openNewInvoice}
                        type="button"
                    >
                        <i className="bi bi-plus-lg me-1" aria-hidden="true" />
                        {labels.createinvoice}
                    </button>
                )}
                toolbar={(
                    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                        <div className={mcClasses("mc-product-toolbar__search")}>
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-invoices-search">
                                {labels.search}
                            </label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-invoices-search"
                                onChange={(event) => setSearchInput(event.target.value)}
                                placeholder={labels.searchplaceholder}
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
                                    <th scope="col">{labels.invoicenumber}</th>
                                    <th scope="col">{labels.customer}</th>
                                    <th scope="col" className="text-end">{labels.total}</th>
                                    <th scope="col">{labels.status}</th>
                                    <th scope="col">{labels.duedate}</th>
                                    <th scope="col" className="text-end">{labels.actions}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {!loading && data?.items.length === 0 && (
                                    <tr>
                                        <td colSpan={6}>
                                            <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                <span className={mcClasses("mc-empty__icon")}>
                                                    <i className="bi bi-receipt-cutoff" aria-hidden="true" />
                                                </span>
                                                <p className={mcClasses("mc-empty__title")}>
                                                    {total === 0 ? labels.noinvoices : labels.noresults}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                {data?.items.map((invoice) => (
                                    <tr key={invoice.id}>
                                        <td>
                                            <div className="fw-semibold">{invoice.invoicenumber}</div>
                                            <div className={mcClasses("mc-cell-muted small")}>
                                                {invoice.created} - {invoice.itemcount}
                                            </div>
                                        </td>
                                        <td>
                                            <div>{invoice.customername}</div>
                                            <div className={mcClasses("mc-cell-muted small")}>{invoice.customeremail}</div>
                                        </td>
                                        <td className="text-end fw-semibold">{invoice.displaytotal}</td>
                                        <td>
                                            <select
                                                aria-label={labels.status}
                                                className={mcClasses("mc-select mc-status-select")}
                                                disabled={busyId === invoice.id}
                                                onChange={(event) => changeStatus(invoice, event.target.value)}
                                                value={invoice.status}
                                            >
                                                {statusOptions.map((option) => (
                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{invoice.duedate}</td>
                                        <td className="text-end">
                                            {renderInvoiceActions(invoice)}
                                        </td>
                                    </tr>
                                ))}
                                {loading && (
                                    <tr>
                                        <td colSpan={6}>
                                            <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                </table>
            </McTableCard>

            {renderInvoiceDrawer()}
        </section>
    );
}

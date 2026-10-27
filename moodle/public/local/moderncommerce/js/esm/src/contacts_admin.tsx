// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin for contact submissions (Modern Commerce core contact
 * webservice endpoints): report + conversation thread + reply + status.
 *
 * @module     local_moderncommerce/contacts_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {McButton} from "./button";
import {McTableActionMenu, McTableCard, McTableFooter, McTablePagination} from "./table_components";
import {FieldError} from "./form";

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

type Option = {
    id: number;
    name: string;
    value: string;
};

type Contact = {
    id: number;
    fullname: string;
    email: string;
    subject: string;
    phone: string;
    message: string;
    status: string;
    statuslabel: string;
    statusclass: string;
    source: string;
    timecreated: number;
    displaydate: string;
    isunread: boolean;
};

type ThreadItem = {
    fromclient: boolean;
    sendername: string;
    message: string;
    displaydate: string;
    isoriginal: boolean;
};

type Stats = {
    total: number;
    unread: number;
    replied: number;
    thisweek: number;
};

type Filters = {
    search: string;
    status: string;
    sort: string;
    page: number;
    perpage: number;
};

type ListResponse = {
    items: Contact[];
    total: number;
    page: number;
    perpage: number;
    stats: Stats;
    statuses: Option[];
};

type DetailResponse = {
    contact: Contact;
    thread: ThreadItem[];
};

type ReplyResponse = {
    success: boolean;
    message: string;
    contact: Contact;
    thread: ThreadItem[];
};

type StatusResponse = {
    success: boolean;
    message: string;
    contact: Contact;
};

type Methods = {
    list: string;
    get: string;
    reply: string;
    setStatus: string;
};

type ContactsAdminProps = {
    methods: Methods;
    sortOptions: SelectOption[];
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

const errorText = (caught: unknown): string => caught instanceof Error ? caught.message : String(caught);

const badgeVariant = (variant: string): McBadgeVariant => {
    const variants: McBadgeVariant[] = ["primary", "secondary", "accent", "success", "warning", "danger", "info", "neutral"];
    return variants.includes(variant as McBadgeVariant) ? variant as McBadgeVariant : "neutral";
};

export default function ContactsAdmin({methods, sortOptions, perPageOptions, labels}: ContactsAdminProps) {
    useModernCommerceClassSync();

    const [filters, setFilters] = useState<Filters>({search: "", status: "", sort: "newest", page: 0, perpage: 10});
    const [searchInput, setSearchInput] = useState("");
    const [data, setData] = useState<ListResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [reloadToken, setReloadToken] = useState(0);

    const [detail, setDetail] = useState<DetailResponse | null>(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [replyText, setReplyText] = useState("");
    const [replyError, setReplyError] = useState("");
    const [busy, setBusy] = useState(false);

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
        void callMoodleService<ListResponse>(methods.list, filters)
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
    }, [filters, methods.list, reloadToken]);

    useEffect(() => {
        const refreshButton = document.getElementById("moderncommerce-contacts-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);
        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    const stats = data?.stats;
    const statuses = data?.statuses ?? [];
    const total = data?.total ?? 0;
    const totalPages = Math.max(1, Math.ceil(total / filters.perpage));
    const range = getVisibleRange(total, filters.page, filters.perpage);

    const updateFilters = (changes: Partial<Filters>) => {
        setFilters((current) => ({...current, ...changes, page: changes.page ?? 0}));
    };

    const openDetail = (id: number) => {
        setDetailLoading(true);
        setError("");
        setReplyText("");
        void callMoodleService<DetailResponse>(methods.get, {id})
            .then((result) => setDetail(result))
            .catch((caught: unknown) => setError(errorText(caught)))
            .finally(() => setDetailLoading(false));
    };

    const sendReply = async() => {
        if (!detail) {
            return;
        }
        // Was a silent return: the send button did nothing and said nothing.
        const trimmed = replyText.trim();
        if (trimmed === "" || trimmed.length < 2) {
            setReplyError(labels["error:required"] ?? "");
            return;
        }
        if (trimmed.length > 5000) {
            setReplyError(labels["error:maxlength"]?.replace("{$a}", "5000") ?? "");
            return;
        }
        setReplyError("");
        setBusy(true);
        setError("");
        try {
            const result = await callMoodleService<ReplyResponse>(methods.reply, {id: detail.contact.id, message: replyText});
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            setDetail({contact: result.contact, thread: result.thread});
            setReplyText("");
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setBusy(false);
        }
    };

    const changeStatus = async(id: number, status: string) => {
        setBusy(true);
        setError("");
        try {
            const result = await callMoodleService<StatusResponse>(methods.setStatus, {id, status});
            if (!result.success) {
                setError(result.message);
                return;
            }
            toast.success(result.message);
            if (detail && detail.contact.id === id) {
                setDetail({...detail, contact: result.contact});
            }
            setReloadToken((current) => current + 1);
        } catch (caught) {
            setError(errorText(caught));
        } finally {
            setBusy(false);
        }
    };

    // Detail view.
    if (detail) {
        const contact = detail.contact;
        return (
            <section className={mcClasses("mc-product-form")} aria-label={labels.conversation}>
                <div className={mcClasses("mc-product-form__head")}>
                    <div>
                        <h3>{contact.fullname}</h3>
                        <p className={mcClasses("mc-cell-muted small mb-0")}>{contact.email}{contact.phone !== "" ? ` · ${contact.phone}` : ""}</p>
                    </div>
                    <button className={mcClasses("mc-button mc-btn-soft")} onClick={() => setDetail(null)} type="button">{labels.backtolist}</button>
                </div>

                {error && (
                    <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                        <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{error}</div>
                    </div>
                )}

                <div className={mcClasses("mc-product-form__section")}>
                    <div className="d-flex flex-wrap align-items-center gap-2 mb-2">
                        {contact.subject !== "" && <strong>{contact.subject}</strong>}
                        <McBadge variant={badgeVariant(contact.statusclass)} tone="soft" dot>{contact.statuslabel}</McBadge>
                        <span className={mcClasses("mc-cell-muted small")}>{contact.displaydate}</span>
                    </div>
                    <div className="d-flex flex-wrap gap-2">
                        {statuses.map((option) => (
                            <button
                                className={mcClasses(contact.status === option.value ? "mc-button btn-mc-primary" : "mc-button mc-btn-soft")}
                                disabled={busy || contact.status === option.value}
                                key={option.value}
                                onClick={() => changeStatus(contact.id, option.value)}
                                type="button"
                            >
                                {option.name}
                            </button>
                        ))}
                    </div>
                </div>

                <div className={mcClasses("mc-product-form__section")}>
                    <h4>{labels.conversation}</h4>
                    {detailLoading && <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>}
                    <div className={mcClasses("mc-contact-thread")}>
                        {detail.thread.map((item, index) => (
                            <div
                                className={mcClasses("mc-contact-message", item.fromclient ? "mc-contact-message--client" : "mc-contact-message--admin")}
                                key={index}
                            >
                                <div className={mcClasses("mc-contact-message__head")}>
                                    <strong>{item.sendername}</strong>
                                    <span className={mcClasses("mc-cell-muted small")}>{item.displaydate}</span>
                                </div>
                                <div className={mcClasses("mc-contact-message__body")}>{item.message}</div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className={mcClasses("mc-product-form__section")}>
                    <label className={mcClasses("mc-field-label")} htmlFor="mc-contact-reply">
                        {labels.reply}
                        <span className={mcClasses("mc-required")} aria-hidden="true">*</span>
                    </label>
                    <textarea
                        aria-describedby={replyError ? "mc-contact-reply-error" : undefined}
                        aria-invalid={replyError ? true : undefined}
                        aria-required="true"
                        className={mcClasses("mc-form-control", replyError ? "mc-field-invalid" : "")}
                        id="mc-contact-reply"
                        maxLength={5000}
                        name="replyText"
                        onChange={(event) => {
                            setReplyText(event.target.value);
                            setReplyError("");
                        }}
                        placeholder={labels.replyplaceholder}
                        rows={5}
                        value={replyText}
                    />
                    <FieldError id="mc-contact-reply-error" message={replyError} />
                    <div className="mt-3">
                        <McButton
                            className={mcClasses("btn-mc-primary")}
                            disabled={replyText.trim() === ""}
                            loading={busy}
                            loadingLabel={labels.loading || "Sending..."}
                            onClick={sendReply}
                            type="button"
                        >
                            <i className="bi bi-send me-1" aria-hidden="true" /> {labels.sendreply}
                        </McButton>
                    </div>
                </div>
            </section>
        );
    }

    // List view.
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
                        <i className="bi bi-inbox mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.total}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.total)}</strong>
                        </div>
                        <i className="bi bi-inbox mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--warning")}>
                        <i className="bi bi-envelope mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.unread}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.unread)}</strong>
                        </div>
                        <i className="bi bi-envelope mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                        <i className="bi bi-check-circle mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.replied}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.replied)}</strong>
                        </div>
                        <i className="bi bi-check-circle mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                    <article className={mcClasses("mc-stat-tile mc-stat-tile--info")}>
                        <i className="bi bi-calendar-week mc-stat-tile__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{labels.thisweek}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.thisweek)}</strong>
                        </div>
                        <i className="bi bi-calendar-week mc-stat-tile__watermark" aria-hidden="true" />
                    </article>
                </div>
            )}

            <McTableCard
                title={<h3 className={mcClasses("mc-card-title")}>{labels.title}</h3>}
                toolbar={(
                    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                        <div className={mcClasses("mc-product-toolbar__search")}>
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-contacts-search">{labels.search}</label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-contacts-search"
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
                                {statuses.map((option) => (
                                    <option key={option.value} value={option.value}>{option.name}</option>
                                ))}
                            </select>
                        </label>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.sortby}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({sort: event.target.value})}
                                value={filters.sort}
                            >
                                {sortOptions.map((option) => (
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
                                {labels.showing} {formatCount(range.from)}-{formatCount(range.to)} / {formatCount(total)}
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
                <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.title}>
                    <thead>
                        <tr>
                            <th scope="col">{labels.from}</th>
                            <th scope="col">{labels.subject}</th>
                            <th scope="col">{labels.status}</th>
                            <th scope="col">{labels.received}</th>
                            <th scope="col" className="text-end">{labels.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {!loading && data?.items.length === 0 && (
                            <tr>
                                <td colSpan={5}>
                                    <div className={mcClasses("mc-empty mc-empty--centered")}>
                                        <span className={mcClasses("mc-empty__icon")}><i className="bi bi-inbox" aria-hidden="true" /></span>
                                        <p className={mcClasses("mc-empty__title")}>{total === 0 ? labels.nocontacts : labels.noresults}</p>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {data?.items.map((contact) => (
                            <tr key={contact.id} className={contact.isunread ? mcClasses("mc-contact-row--unread") : ""}>
                                <td>
                                    <button className={mcClasses("mc-button mc-btn-ghost p-0 text-start")} onClick={() => openDetail(contact.id)} type="button">
                                        <strong>{contact.fullname}</strong>
                                    </button>
                                    <div className={mcClasses("mc-cell-muted small")}>{contact.email}</div>
                                </td>
                                <td>{contact.subject || "-"}</td>
                                <td>
                                    <McBadge variant={badgeVariant(contact.statusclass)} tone="soft" dot>
                                        {contact.statuslabel}
                                    </McBadge>
                                </td>
                                <td className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{contact.displaydate}</td>
                                <td className="text-end">
                                    <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                        <McTableActionMenu
                                            label={`${labels.actions}: ${contact.fullname}`}
                                            items={[
                                                {
                                                    key: "view",
                                                    label: labels.view,
                                                    icon: "bi bi-eye",
                                                    onClick: () => openDetail(contact.id),
                                                },
                                            ]}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {loading && (
                            <tr>
                                <td colSpan={5}>
                                    <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </McTableCard>
        </section>
    );
}

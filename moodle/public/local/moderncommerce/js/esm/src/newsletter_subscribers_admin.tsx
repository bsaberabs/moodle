// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin for Modern Commerce newsletter subscribers.
 *
 * @module     local_moderncommerce/newsletter_subscribers_admin
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {McBadge} from "./badge";
import {McButton} from "./button";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McTableActionMenu, McTableCard, McTableFooter, McTablePagination} from "./table_components";

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

type Methods = {
    list: string;
    delete: string;
    export: string;
};

type Filters = {
    search: string;
    source: string;
    sort: string;
    page: number;
    perpage: number;
};

type Subscriber = {
    id: number;
    email: string;
    source: string;
    userid: number;
    userlabel: string;
    timecreated: number;
    displaydate: string;
};

type Stats = {
    total: number;
    thisweek: number;
    knownusers: number;
    guests: number;
};

type ListResponse = {
    items: Subscriber[];
    total: number;
    page: number;
    perpage: number;
    stats: Stats;
    sources: SelectOption[];
};

type SimpleResponse = {
    success: boolean;
    message: string;
};

type ExportResponse = {
    filename: string;
    mimetype: string;
    content: string;
};

type NewsletterSubscribersAdminProps = {
    methods: Methods;
    sortOptions: SelectOption[];
    perPageOptions: number[];
    canManage: boolean;
    labels: Labels;
};

const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php`
        + `?sesskey=${encodeURIComponent(M.cfg.sesskey)}`
        + `&info=${encodeURIComponent(methodName)}`;
    const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {"Content-Type": "application/json"},
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

const downloadFile = (filename: string, mimetype: string, content: string) => {
    const blob = new Blob([content], {type: mimetype || "text/csv"});
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename || "moderncommerce-newsletter-subscribers.csv";
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
};

export default function NewsletterSubscribersAdmin({
    methods,
    sortOptions,
    perPageOptions,
    canManage,
    labels,
}: NewsletterSubscribersAdminProps) {
    useModernCommerceClassSync();

    const [filters, setFilters] = useState<Filters>({search: "", source: "", sort: "newest", page: 0, perpage: 10});
    const [searchInput, setSearchInput] = useState("");
    const [data, setData] = useState<ListResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const [reloadToken, setReloadToken] = useState(0);

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
    }, [methods.list, filters, reloadToken]);

    useEffect(() => {
        const button = document.getElementById("moderncommerce-newsletter-subscribers-refresh");
        if (!button) {
            return undefined;
        }
        const handler = () => setReloadToken((value) => value + 1);
        button.addEventListener("click", handler);
        return () => button.removeEventListener("click", handler);
    }, []);

    const updateFilters = (patch: Partial<Filters>) => {
        setFilters((current) => ({
            ...current,
            ...patch,
            page: patch.page ?? 0,
        }));
    };

    const deleteSubscriber = async(subscriber: Subscriber) => {
        const template = labels.deletesubscriberconfirm || "Delete {$a} from newsletter subscribers?";
        if (!window.confirm(template.replace("{$a}", subscriber.email))) {
            return;
        }
        setBusy(true);
        try {
            const result = await callMoodleService<SimpleResponse>(methods.delete, {id: subscriber.id});
            if (result.success) {
                toast.success(result.message);
            } else {
                toast.warning(result.message);
            }
            setReloadToken((value) => value + 1);
        } catch (caught) {
            toast.error(errorText(caught));
        } finally {
            setBusy(false);
        }
    };

    const exportSubscribers = async() => {
        setBusy(true);
        try {
            const result = await callMoodleService<ExportResponse>(methods.export, {
                search: filters.search,
                source: filters.source,
                sort: filters.sort,
            });
            downloadFile(result.filename, result.mimetype, result.content);
            toast.success(labels.exported);
        } catch (caught) {
            toast.error(errorText(caught));
        } finally {
            setBusy(false);
        }
    };

    const total = data?.total ?? 0;
    const stats = data?.stats ?? {total: 0, thisweek: 0, knownusers: 0, guests: 0};
    const sources = data?.sources ?? [];
    const range = getVisibleRange(total, filters.page, filters.perpage);
    const totalPages = Math.max(1, Math.ceil(total / filters.perpage));

    return (
        <section className={mcClasses("mc-product-admin mc-newsletter-admin")}>
            {error && (
                <div className={mcClasses("alert alert-danger")} role="alert">
                    {error}
                </div>
            )}

            <div className={mcClasses("mc-stat-strip")} aria-label={labels.title}>
                <article className={mcClasses("mc-stat-tile mc-stat-tile--primary")}>
                    <i className="bi bi-envelope-paper mc-stat-tile__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-stat-tile__body")}>
                        <span className={mcClasses("mc-stat-tile__label")}>{labels.total}</span>
                        <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.total)}</strong>
                    </div>
                    <i className="bi bi-envelope-paper mc-stat-tile__watermark" aria-hidden="true" />
                </article>
                <article className={mcClasses("mc-stat-tile mc-stat-tile--info")}>
                    <i className="bi bi-calendar-week mc-stat-tile__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-stat-tile__body")}>
                        <span className={mcClasses("mc-stat-tile__label")}>{labels.thisweek}</span>
                        <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.thisweek)}</strong>
                    </div>
                    <i className="bi bi-calendar-week mc-stat-tile__watermark" aria-hidden="true" />
                </article>
                <article className={mcClasses("mc-stat-tile mc-stat-tile--success")}>
                    <i className="bi bi-person-check mc-stat-tile__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-stat-tile__body")}>
                        <span className={mcClasses("mc-stat-tile__label")}>{labels.knownusers}</span>
                        <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.knownusers)}</strong>
                    </div>
                    <i className="bi bi-person-check mc-stat-tile__watermark" aria-hidden="true" />
                </article>
                <article className={mcClasses("mc-stat-tile mc-stat-tile--neutral")}>
                    <i className="bi bi-person mc-stat-tile__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-stat-tile__body")}>
                        <span className={mcClasses("mc-stat-tile__label")}>{labels.guests}</span>
                        <strong className={mcClasses("mc-stat-tile__value")}>{formatCount(stats.guests)}</strong>
                    </div>
                    <i className="bi bi-person mc-stat-tile__watermark" aria-hidden="true" />
                </article>
            </div>

            <McTableCard
                title={<h3 className={mcClasses("mc-card-title")}>{labels.title}</h3>}
                actions={(
                    <McButton type="button" className="btn-mc-primary" onClick={exportSubscribers} loading={busy}>
                        <i className="bi bi-download" aria-hidden="true" />
                        {labels.exportcsv}
                    </McButton>
                )}
                toolbar={(
                    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
                        <div className={mcClasses("mc-product-toolbar__search")}>
                            <label className={mcClasses("mc-filter-label")} htmlFor="mc-newsletter-search">{labels.search}</label>
                            <input
                                className={mcClasses("mc-form-control")}
                                id="mc-newsletter-search"
                                onChange={(event) => setSearchInput(event.target.value)}
                                placeholder={labels.searchplaceholder}
                                type="search"
                                value={searchInput}
                            />
                        </div>
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.source}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => updateFilters({source: event.target.value})}
                                value={filters.source}
                            >
                                <option value="">{labels.allsources}</option>
                                {sources.map((source) => (
                                    <option key={source.value} value={source.value}>{source.label}</option>
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
                            <th scope="col">{labels.subscriber}</th>
                            <th scope="col">{labels.source}</th>
                            <th scope="col">{labels.user}</th>
                            <th scope="col">{labels.subscribedat}</th>
                            <th scope="col" className="text-end">{labels.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {!loading && data?.items.length === 0 && (
                            <tr>
                                <td colSpan={5}>
                                    <div className={mcClasses("mc-empty mc-empty--centered")}>
                                        <span className={mcClasses("mc-empty__icon")}><i className="bi bi-inbox" aria-hidden="true" /></span>
                                        <p className={mcClasses("mc-empty__title")}>{total === 0 ? labels.nosubscribers : labels.noresults}</p>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {data?.items.map((subscriber) => (
                            <tr key={subscriber.id}>
                                <td>
                                    <strong>{subscriber.email}</strong>
                                </td>
                                <td>
                                    {subscriber.source
                                        ? <McBadge variant="info" tone="soft">{subscriber.source}</McBadge>
                                        : <span className={mcClasses("mc-cell-muted")}>-</span>}
                                </td>
                                <td>
                                    {subscriber.userlabel
                                        ? subscriber.userlabel
                                        : <span className={mcClasses("mc-cell-muted")}>-</span>}
                                </td>
                                <td className={mcClasses("mc-cell-muted mc-cell-nowrap")}>{subscriber.displaydate}</td>
                                <td className="text-end">
                                    <div className={mcClasses("mc-table-design__actions justify-content-end")}>
                                        <McTableActionMenu
                                            label={`${labels.actions}: ${subscriber.email}`}
                                            disabled={!canManage || busy}
                                            items={[
                                                {
                                                    key: "delete",
                                                    label: labels.delete,
                                                    icon: "bi bi-trash",
                                                    danger: true,
                                                    disabled: !canManage || busy,
                                                    onClick: () => deleteSubscriber(subscriber),
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

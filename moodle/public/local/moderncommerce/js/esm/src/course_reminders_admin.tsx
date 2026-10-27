// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Read-only Modern Commerce admin console for the standalone Modern Course
 * Reminder add-on.
 *
 * @module     local_moderncommerce/course_reminders_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import {McTableCard, McTableFooter, McTablePagination} from "./table_components";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

type Methods = {
    dashboard: string;
    healthcheck: string;
    listRules: string;
    listTemplates: string;
    listSchedules: string;
    listQueue: string;
    listLogs: string;
    listManagerMaps: string;
};

type Analytics = {
    activerules: number;
    senttotal: number;
    failedtotal: number;
    pairsreminded: number;
    learnersreminded: number;
    completedafter: number;
    effectiveness: number;
    avgdaystocomplete: number;
    repeated1: number;
    repeated2: number;
    repeated3plus: number;
    time: number;
    cached: boolean;
};

type DashboardResponse = {
    enabled: boolean;
    activerules: number;
    disabledrules: number;
    templates: number;
    schedules: number;
    pendingqueue: number;
    failedqueue: number;
    senttoday: number;
    sentweek: number;
    failedtoday: number;
    overdue: number;
    escalations: number;
    analytics: Analytics;
};

type HealthCheck = {
    label: string;
    status: string;
    detail: string;
};

type HealthResponse = {
    checks: HealthCheck[];
};

type ListResponse<Row> = {
    items: Row[];
    total: number;
    page: number;
    perpage: number;
};

type RuleRow = {
    id: number;
    name: string;
    scope: string;
    coursename: string;
    triggertype: string;
    templatename: string;
    enabled: boolean;
    priority: number;
    frequencydays: number;
    editurl: string;
};

type TemplateRow = {
    id: number;
    name: string;
    audience: string;
    audiencelabel: string;
    subject: string;
    category: string;
    enabled: boolean;
    editurl: string;
};

type ScheduleRow = {
    id: number;
    name: string;
    coursename: string;
    target: string;
    recurrence: string;
    sendtime: number;
    status: string;
    editurl: string;
};

type QueueRow = {
    id: number;
    fullname: string;
    courseid: number;
    coursename: string;
    templatename: string;
    status: string;
    attempts: number;
    scheduledtime: number;
    senttime: number;
};

type LogRow = {
    id: number;
    fullname: string;
    coursename: string;
    channel: string;
    type: string;
    subject: string;
    status: string;
    timecreated: number;
};

type ManagerRow = {
    id: number;
    learnername: string;
    learneremail: string;
    managername: string;
    manageremail: string;
    coursename: string;
    source: string;
};

type TabKey = "rules" | "templates" | "schedules" | "queue" | "logs" | "managers";
type ViewKey = "overview" | TabKey;

type TabFilter = {
    search: string;
    page: number;
    perpage: number;
};

type SectionState<Row> = {
    loading: boolean;
    error: string;
    items: Row[];
    total: number;
    page: number;
    perpage: number;
};

type CourseRemindersAdminProps = {
    methods: Methods;
    initialView?: ViewKey;
    legacyUrl: string;
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

const formatPercent = (value: number): string => `${new Intl.NumberFormat(document.documentElement.lang || undefined, {maximumFractionDigits: 1}).format(value)}%`;

const formatDate = (timestamp: number): string => {
    if (!timestamp) {
        return "-";
    }

    return new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    }).format(new Date(timestamp * 1000));
};

const errorText = (caught: unknown): string => caught instanceof Error ? caught.message : String(caught);

const initialFilter = (): TabFilter => ({search: "", page: 0, perpage: 10});

const emptySection = <Row, >(perpage = 10): SectionState<Row> => ({
    loading: false,
    error: "",
    items: [],
    total: 0,
    page: 0,
    perpage,
});

const normaliseInitialView = (value?: string): ViewKey => {
    if (
        value === "rules" ||
        value === "templates" ||
        value === "schedules" ||
        value === "queue" ||
        value === "logs" ||
        value === "managers"
    ) {
        return value;
    }

    return "overview";
};

const sectionTitle = (tab: TabKey, labels: Labels): string => {
    if (tab === "rules") {
        return labels.rules;
    }
    if (tab === "templates") {
        return labels.templates;
    }
    if (tab === "schedules") {
        return labels.schedules;
    }
    if (tab === "queue") {
        return labels.queue;
    }
    if (tab === "logs") {
        return labels.logs;
    }

    return labels.managers;
};

const statusVariant = (status: string): string => {
    const normalised = status.toLowerCase();
    if (["ok", "sent", "enabled", "queued"].includes(normalised)) {
        return "success";
    }
    if (["warning", "pending", "sending"].includes(normalised)) {
        return "warning";
    }
    if (["problem", "failed", "cancelled", "disabled"].includes(normalised)) {
        return "danger";
    }
    return "neutral";
};

const StatusBadge = ({status, label}: {status: string; label?: string}) => (
    <span className={mcClasses(`mc-badge mc-badge--${statusVariant(status)}`)}>{label ?? status}</span>
);

const MetricTile = ({label, value, icon, variant}: {label: string; value: string; icon: string; variant: string}) => (
    <article className={mcClasses(`mc-stat-tile mc-stat-tile--${variant}`)}>
        <i className={`bi ${icon} mc-stat-tile__icon`} aria-hidden="true" />
        <div className={mcClasses("mc-stat-tile__body")}>
            <span className={mcClasses("mc-stat-tile__label")}>{label}</span>
            <strong className={mcClasses("mc-stat-tile__value")}>{value}</strong>
        </div>
        <i className={`bi ${icon} mc-stat-tile__watermark`} aria-hidden="true" />
    </article>
);

const SectionError = ({message, labels}: {message: string; labels: Labels}) => (
    <div className={mcClasses("mc-alert mc-alert--warning")} role="status">
        <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
        <div className={mcClasses("mc-alert__body")}>
            <p className={mcClasses("mc-alert__title")}>{labels.sectionUnavailable}</p>
            {message}
        </div>
    </div>
);

const LoadingState = ({labels}: {labels: Labels}) => (
    <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
);

const EmptyState = ({labels}: {labels: Labels}) => (
    <div className={mcClasses("mc-empty mc-empty--centered")}>
        <span className={mcClasses("mc-empty__icon")}>
            <i className="bi bi-inbox" aria-hidden="true" />
        </span>
        <p className={mcClasses("mc-empty__title")}>{labels.noresults}</p>
    </div>
);

const ExternalLink = ({url, labels}: {url?: string; labels: Labels}) => {
    if (!url) {
        return <span className="text-muted">-</span>;
    }

    return (
        <a
            className={mcClasses("mc-table-design__action mc-table-design__action--view")}
            href={url}
            aria-label={labels.view}
            title={labels.view}
        >
            <i className="bi bi-box-arrow-up-right" aria-hidden="true" />
        </a>
    );
};

const TableFooter = ({
    page,
    perpage,
    total,
    labels,
    onPage,
}: {
    page: number;
    perpage: number;
    total: number;
    labels: Labels;
    onPage: (page: number) => void;
}) => {
    const maxPage = Math.max(0, Math.ceil(total / perpage) - 1);
    const start = total === 0 ? 0 : (page * perpage) + 1;
    const end = Math.min(total, (page + 1) * perpage);
    const totalPages = Math.max(1, maxPage + 1);

    return (
        <McTableFooter
            summary={(
                <span>{labels.showing} {formatCount(start)}-{formatCount(end)} / {formatCount(total)}</span>
            )}
            pagination={(
                <McTablePagination
                    previousLabel={labels.previous}
                    nextLabel={labels.next}
                    pageLabel={labels.page}
                    page={page + 1}
                    totalPages={totalPages}
                    previousDisabled={page <= 0}
                    nextDisabled={page >= maxPage}
                    onPrevious={() => onPage(Math.max(0, page - 1))}
                    onNext={() => onPage(page + 1)}
                />
            )}
        />
    );
};

const SectionToolbar = ({
    activeFilter,
    labels,
    perPageOptions,
    onChange,
}: {
    activeFilter: TabFilter;
    labels: Labels;
    perPageOptions: number[];
    onChange: (changes: Partial<TabFilter>) => void;
}) => (
    <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar")}>
        <div className={mcClasses("mc-product-toolbar__search")}>
            <label className={mcClasses("mc-filter-label")} htmlFor="mc-course-reminders-search">
                {labels.search}
            </label>
            <span className={mcClasses("mc-search")}>
                <i className="bi bi-search mc-search__icon" aria-hidden="true" />
                <input
                    className={mcClasses("mc-search-input")}
                    id="mc-course-reminders-search"
                    type="search"
                    value={activeFilter.search}
                    placeholder={labels.searchPlaceholder}
                    onChange={(event) => onChange({search: event.currentTarget.value, page: 0})}
                />
            </span>
        </div>
        <label className={mcClasses("mc-product-toolbar__field mc-product-toolbar__field--small mc-table-design-page-size")}>
            <span className={mcClasses("mc-product-toolbar__label")}>{labels.perpage}</span>
            <select
                className="form-select"
                value={activeFilter.perpage}
                onChange={(event) => onChange({perpage: Number(event.currentTarget.value) || 10, page: 0})}
            >
                {perPageOptions.map((option) => (
                    <option value={option} key={option}>{option}</option>
                ))}
            </select>
        </label>
    </div>
);

export default function CourseRemindersAdmin({
    methods,
    initialView,
    legacyUrl,
    perPageOptions,
    labels,
}: CourseRemindersAdminProps) {
    useModernCommerceClassSync();

    const currentView = normaliseInitialView(initialView);
    const isOverview = currentView === "overview";
    const [dashboard, setDashboard] = useState<{loading: boolean; error: string; data: DashboardResponse | null}>({
        loading: true,
        error: "",
        data: null,
    });
    const [health, setHealth] = useState<{loading: boolean; error: string; data: HealthResponse | null}>({
        loading: true,
        error: "",
        data: null,
    });
    const [activeTab] = useState<TabKey>(currentView === "overview" ? "rules" : currentView);
    const [filters, setFilters] = useState<Record<TabKey, TabFilter>>({
        rules: initialFilter(),
        templates: initialFilter(),
        schedules: initialFilter(),
        queue: initialFilter(),
        logs: initialFilter(),
        managers: initialFilter(),
    });
    const [sections, setSections] = useState({
        rules: emptySection<RuleRow>(),
        templates: emptySection<TemplateRow>(),
        schedules: emptySection<ScheduleRow>(),
        queue: emptySection<QueueRow>(),
        logs: emptySection<LogRow>(),
        managers: emptySection<ManagerRow>(),
    });
    const [reloadToken, setReloadToken] = useState(0);

    useEffect(() => {
        const refreshButton = document.getElementById("moderncommerce-course-reminders-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);
        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    useEffect(() => {
        let cancelled = false;

        if (!isOverview) {
            setDashboard({loading: false, error: "", data: null});
            setHealth({loading: false, error: "", data: null});
            return () => {
                cancelled = true;
            };
        }

        setDashboard((current) => ({...current, loading: true, error: ""}));
        void callMoodleService<DashboardResponse>(methods.dashboard, {})
            .then((result) => {
                if (!cancelled) {
                    setDashboard({loading: false, error: "", data: result});
                }
            })
            .catch((caught: unknown) => {
                if (!cancelled) {
                    setDashboard((current) => ({...current, loading: false, error: errorText(caught)}));
                }
            });

        setHealth((current) => ({...current, loading: true, error: ""}));
        void callMoodleService<HealthResponse>(methods.healthcheck, {})
            .then((result) => {
                if (!cancelled) {
                    setHealth({loading: false, error: "", data: result});
                }
            })
            .catch((caught: unknown) => {
                if (!cancelled) {
                    setHealth((current) => ({...current, loading: false, error: errorText(caught)}));
                }
            });

        return () => {
            cancelled = true;
        };
    }, [isOverview, methods.dashboard, methods.healthcheck, reloadToken]);

    const activeFilter = filters[activeTab];

    useEffect(() => {
        let cancelled = false;

        if (isOverview) {
            return () => {
                cancelled = true;
            };
        }

        const setSectionLoading = (tab: TabKey) => {
            setSections((current) => ({
                ...current,
                [tab]: {...current[tab], loading: true, error: ""},
            }));
        };

        const setSectionError = (tab: TabKey, message: string) => {
            setSections((current) => ({
                ...current,
                [tab]: {...current[tab], loading: false, error: message},
            }));
        };

        const setSectionData = <Row, >(tab: TabKey, result: ListResponse<Row>) => {
            setSections((current) => ({
                ...current,
                [tab]: {
                    loading: false,
                    error: "",
                    items: result.items,
                    total: result.total,
                    page: result.page,
                    perpage: result.perpage,
                },
            }));
        };

        const args = {
            search: activeFilter.search,
            page: activeFilter.page,
            perpage: activeFilter.perpage,
        };

        setSectionLoading(activeTab);

        const request = (() => {
            if (activeTab === "rules") {
                return callMoodleService<ListResponse<RuleRow>>(methods.listRules, {
                    ...args,
                    scope: "",
                    triggertype: "",
                    enabled: "",
                    courseid: 0,
                });
            }
            if (activeTab === "templates") {
                return callMoodleService<ListResponse<TemplateRow>>(methods.listTemplates, {
                    ...args,
                    audience: "",
                    enabled: "",
                });
            }
            if (activeTab === "schedules") {
                return callMoodleService<ListResponse<ScheduleRow>>(methods.listSchedules, {
                    ...args,
                    status: "",
                    courseid: 0,
                });
            }
            if (activeTab === "queue") {
                return callMoodleService<ListResponse<QueueRow>>(methods.listQueue, {
                    ...args,
                    status: "",
                    courseid: 0,
                });
            }
            if (activeTab === "logs") {
                return callMoodleService<ListResponse<LogRow>>(methods.listLogs, {
                    ...args,
                    status: "",
                    channel: "",
                    type: "",
                    courseid: 0,
                });
            }
            return callMoodleService<ListResponse<ManagerRow>>(methods.listManagerMaps, {
                ...args,
                source: "",
                courseid: 0,
            });
        })();

        void request
            .then((result) => {
                if (!cancelled) {
                    setSectionData(activeTab, result);
                }
            })
            .catch((caught: unknown) => {
                if (!cancelled) {
                    setSectionError(activeTab, errorText(caught));
                }
            });

        return () => {
            cancelled = true;
        };
    }, [
        activeFilter.page,
        activeFilter.perpage,
        activeFilter.search,
        activeTab,
        isOverview,
        methods.listLogs,
        methods.listManagerMaps,
        methods.listQueue,
        methods.listRules,
        methods.listSchedules,
        methods.listTemplates,
        reloadToken,
    ]);

    const updateActiveFilter = (changes: Partial<TabFilter>) => {
        setFilters((current) => ({
            ...current,
            [activeTab]: {
                ...current[activeTab],
                ...changes,
            },
        }));
    };

    const dashboardData = dashboard.data;
    const metrics = dashboardData ? [
        {label: labels.activeRules, value: formatCount(dashboardData.activerules), icon: "bi-diagram-3", variant: "primary"},
        {label: labels.pendingQueue, value: formatCount(dashboardData.pendingqueue), icon: "bi-hourglass-split", variant: "warning"},
        {label: labels.sentToday, value: formatCount(dashboardData.senttoday), icon: "bi-send-check", variant: "success"},
        {label: labels.failedToday, value: formatCount(dashboardData.failedtoday), icon: "bi-exclamation-triangle", variant: "danger"},
        {label: labels.templatesTotal, value: formatCount(dashboardData.templates), icon: "bi-envelope-paper", variant: "info"},
        {label: labels.schedulesTotal, value: formatCount(dashboardData.schedules), icon: "bi-calendar-week", variant: "primary"},
        {label: labels.effectiveness, value: formatPercent(dashboardData.analytics.effectiveness), icon: "bi-graph-up-arrow", variant: "success"},
        {label: labels.overdue, value: formatCount(dashboardData.overdue), icon: "bi-calendar-x", variant: "danger"},
    ] : [];

    return (
        <section className={mcClasses("mc-dashboard")} aria-label={labels.title}>
            {isOverview && dashboard.error && <SectionError message={dashboard.error} labels={labels} />}

            {isOverview && dashboard.loading && !dashboardData ? (
                <LoadingState labels={labels} />
            ) : isOverview && dashboardData && (
                <>
                    <div className={mcClasses(`mc-alert ${dashboardData.enabled ? "mc-alert--success" : "mc-alert--warning"} mb-3`)} role="status">
                        <i className={`bi ${dashboardData.enabled ? "bi-check-circle" : "bi-pause-circle"} mc-alert__icon`} aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>
                            <p className={mcClasses("mc-alert__title")}>
                                {dashboardData.enabled ? labels.engineEnabled : labels.engineDisabled}
                            </p>
                            {labels.overview}
                        </div>
                    </div>

                    <section className={mcClasses("mc-stat-strip")} aria-label={labels.title}>
                        {metrics.map((metric) => (
                            <MetricTile key={metric.label} {...metric} />
                        ))}
                    </section>

                    <div className="row g-3 mb-4">
                        <div className="col-lg-7">
                            <section className={mcClasses("mc-card h-100")}>
                                <div className={mcClasses("mc-card-header")}>
                                    <div>
                                        <h2 className={mcClasses("mc-card-title")}>{labels.analytics}</h2>
                                        <p className="text-muted small mb-0">
                                            {dashboardData.analytics.cached ? labels.cached : labels.live}
                                            {dashboardData.analytics.time ? `: ${formatDate(dashboardData.analytics.time)}` : ""}
                                        </p>
                                    </div>
                                </div>
                                <div className={mcClasses("mc-card-body")}>
                                    <section className={mcClasses("mc-stat-strip")} aria-label={labels.analytics}>
                                        <MetricTile label={labels.sentWeek} value={formatCount(dashboardData.sentweek)} icon="bi-calendar-check" variant="success" />
                                        <MetricTile label={labels.disabledRules} value={formatCount(dashboardData.disabledrules)} icon="bi-slash-circle" variant="neutral" />
                                        <MetricTile label={labels.failedQueue} value={formatCount(dashboardData.failedqueue)} icon="bi-x-octagon" variant="danger" />
                                        <MetricTile label={labels.escalations} value={formatCount(dashboardData.escalations)} icon="bi-person-up" variant="warning" />
                                    </section>
                                </div>
                            </section>
                        </div>
                        <div className="col-lg-5">
                            <section className={mcClasses("mc-card h-100")}>
                                <div className={mcClasses("mc-card-header")}>
                                    <h2 className={mcClasses("mc-card-title")}>{labels.health}</h2>
                                </div>
                                <div className={mcClasses("mc-card-body")}>
                                    {health.loading && !health.data && <LoadingState labels={labels} />}
                                    {health.error && <SectionError message={health.error} labels={labels} />}
                                    {health.data && (
                                        <div className="d-flex flex-column gap-2">
                                            {health.data.checks.map((check) => (
                                                <div className="d-flex align-items-start justify-content-between gap-3" key={`${check.label}-${check.detail}`}>
                                                    <div>
                                                        <div className="fw-semibold">{check.label}</div>
                                                        <div className="text-muted small">{check.detail}</div>
                                                    </div>
                                                    <StatusBadge status={check.status} />
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>
                        </div>
                    </div>
                </>
            )}

            {!isOverview && (
                <McTableCard
                    title={<h2 className={mcClasses("mc-card-title mb-0")}>{sectionTitle(activeTab, labels)}</h2>}
                    actions={(
                        <a className={mcClasses("mc-button mc-btn-soft")} href={legacyUrl}>
                            <i className="bi bi-box-arrow-up-right mc-icon me-1" aria-hidden="true" />
                            {labels.openStandalone}
                        </a>
                    )}
                    toolbar={(
                        <SectionToolbar
                            activeFilter={activeFilter}
                            labels={labels}
                            perPageOptions={perPageOptions}
                            onChange={updateActiveFilter}
                        />
                    )}
                    footer={(
                        <TableFooter
                            page={sections[activeTab].page}
                            perpage={sections[activeTab].perpage}
                            total={sections[activeTab].total}
                            labels={labels}
                            onPage={(page) => updateActiveFilter({page})}
                        />
                    )}
                >
                    {renderActiveSection(activeTab, sections[activeTab], labels)}
                </McTableCard>
            )}
        </section>
    );
}

const renderActiveSection = (
    activeTab: TabKey,
    section: SectionState<RuleRow | TemplateRow | ScheduleRow | QueueRow | LogRow | ManagerRow>,
    labels: Labels
) => {
    if (section.loading && section.items.length === 0) {
        return <LoadingState labels={labels} />;
    }

    if (section.error) {
        return <SectionError message={section.error} labels={labels} />;
    }

    if (section.items.length === 0) {
        return <EmptyState labels={labels} />;
    }

    return (
        <>
            {activeTab === "rules" && renderRulesTable(section.items as RuleRow[], labels)}
            {activeTab === "templates" && renderTemplatesTable(section.items as TemplateRow[], labels)}
            {activeTab === "schedules" && renderSchedulesTable(section.items as ScheduleRow[], labels)}
            {activeTab === "queue" && renderQueueTable(section.items as QueueRow[], labels)}
            {activeTab === "logs" && renderLogsTable(section.items as LogRow[], labels)}
            {activeTab === "managers" && renderManagersTable(section.items as ManagerRow[], labels)}
        </>
    );
};

const renderRulesTable = (items: RuleRow[], labels: Labels) => (
    <table className={mcClasses("table mc-table mc-product-table mb-0")}>
        <thead>
            <tr>
                <th>{labels.name}</th>
                <th>{labels.scope}</th>
                <th>{labels.trigger}</th>
                <th>{labels.course}</th>
                <th>{labels.templates}</th>
                <th>{labels.priority}</th>
                <th>{labels.frequency}</th>
                <th>{labels.status}</th>
                <th>{labels.view}</th>
            </tr>
        </thead>
        <tbody>
            {items.map((item) => (
                <tr key={item.id}>
                    <td className="fw-semibold">{item.name}</td>
                    <td>{item.scope}</td>
                    <td>{item.triggertype}</td>
                    <td>{item.coursename || "-"}</td>
                    <td>{item.templatename || "-"}</td>
                    <td>{formatCount(item.priority)}</td>
                    <td>{formatCount(item.frequencydays)}</td>
                    <td><StatusBadge status={item.enabled ? "enabled" : "disabled"} label={item.enabled ? labels.enabled : labels.disabled} /></td>
                    <td><ExternalLink url={item.editurl} labels={labels} /></td>
                </tr>
            ))}
        </tbody>
    </table>
);

const renderTemplatesTable = (items: TemplateRow[], labels: Labels) => (
    <table className={mcClasses("table mc-table mc-product-table mb-0")}>
        <thead>
            <tr>
                <th>{labels.name}</th>
                <th>{labels.audience}</th>
                <th>{labels.subject}</th>
                <th>{labels.type}</th>
                <th>{labels.status}</th>
                <th>{labels.view}</th>
            </tr>
        </thead>
        <tbody>
            {items.map((item) => (
                <tr key={item.id}>
                    <td className="fw-semibold">{item.name}</td>
                    <td>{item.audiencelabel || item.audience}</td>
                    <td>{item.subject || "-"}</td>
                    <td>{item.category || "-"}</td>
                    <td><StatusBadge status={item.enabled ? "enabled" : "disabled"} label={item.enabled ? labels.enabled : labels.disabled} /></td>
                    <td><ExternalLink url={item.editurl} labels={labels} /></td>
                </tr>
            ))}
        </tbody>
    </table>
);

const renderSchedulesTable = (items: ScheduleRow[], labels: Labels) => (
    <table className={mcClasses("table mc-table mc-product-table mb-0")}>
        <thead>
            <tr>
                <th>{labels.name}</th>
                <th>{labels.course}</th>
                <th>{labels.type}</th>
                <th>{labels.frequency}</th>
                <th>{labels.scheduled}</th>
                <th>{labels.status}</th>
                <th>{labels.view}</th>
            </tr>
        </thead>
        <tbody>
            {items.map((item) => (
                <tr key={item.id}>
                    <td className="fw-semibold">{item.name}</td>
                    <td>{item.coursename || "-"}</td>
                    <td>{item.target}</td>
                    <td>{item.recurrence}</td>
                    <td>{formatDate(item.sendtime)}</td>
                    <td><StatusBadge status={item.status} /></td>
                    <td><ExternalLink url={item.editurl} labels={labels} /></td>
                </tr>
            ))}
        </tbody>
    </table>
);

const renderQueueTable = (items: QueueRow[], labels: Labels) => (
    <table className={mcClasses("table mc-table mc-product-table mb-0")}>
        <thead>
            <tr>
                <th>{labels.recipient}</th>
                <th>{labels.course}</th>
                <th>{labels.templates}</th>
                <th>{labels.status}</th>
                <th>{labels.attempts}</th>
                <th>{labels.scheduled}</th>
                <th>{labels.sent}</th>
            </tr>
        </thead>
        <tbody>
            {items.map((item) => (
                <tr key={item.id}>
                    <td className="fw-semibold">{item.fullname}</td>
                    <td>{item.coursename || `#${item.courseid}`}</td>
                    <td>{item.templatename || "-"}</td>
                    <td><StatusBadge status={item.status} /></td>
                    <td>{formatCount(item.attempts)}</td>
                    <td>{formatDate(item.scheduledtime)}</td>
                    <td>{formatDate(item.senttime)}</td>
                </tr>
            ))}
        </tbody>
    </table>
);

const renderLogsTable = (items: LogRow[], labels: Labels) => (
    <table className={mcClasses("table mc-table mc-product-table mb-0")}>
        <thead>
            <tr>
                <th>{labels.recipient}</th>
                <th>{labels.course}</th>
                <th>{labels.channel}</th>
                <th>{labels.type}</th>
                <th>{labels.subject}</th>
                <th>{labels.status}</th>
                <th>{labels.date}</th>
            </tr>
        </thead>
        <tbody>
            {items.map((item) => (
                <tr key={item.id}>
                    <td className="fw-semibold">{item.fullname}</td>
                    <td>{item.coursename || "-"}</td>
                    <td>{item.channel}</td>
                    <td>{item.type}</td>
                    <td>{item.subject || "-"}</td>
                    <td><StatusBadge status={item.status} /></td>
                    <td>{formatDate(item.timecreated)}</td>
                </tr>
            ))}
        </tbody>
    </table>
);

const renderManagersTable = (items: ManagerRow[], labels: Labels) => (
    <table className={mcClasses("table mc-table mc-product-table mb-0")}>
        <thead>
            <tr>
                <th>{labels.learner}</th>
                <th>{labels.manager}</th>
                <th>{labels.course}</th>
                <th>{labels.source}</th>
            </tr>
        </thead>
        <tbody>
            {items.map((item) => (
                <tr key={item.id}>
                    <td>
                        <div className="fw-semibold">{item.learnername}</div>
                        <div className="text-muted small">{item.learneremail}</div>
                    </td>
                    <td>
                        <div className="fw-semibold">{item.managername}</div>
                        <div className="text-muted small">{item.manageremail}</div>
                    </td>
                    <td>{item.coursename || "-"}</td>
                    <td>{item.source}</td>
                </tr>
            ))}
        </tbody>
    </table>
);

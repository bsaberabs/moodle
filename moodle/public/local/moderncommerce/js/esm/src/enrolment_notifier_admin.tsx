// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Read-only Modern Commerce admin console for the standalone Modern Enrolment
 * Notifier add-on.
 *
 * @module     local_moderncommerce/enrolment_notifier_admin
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
    listRules: string;
    listTemplates: string;
    listCourseSettings: string;
    listQueue: string;
    listLogs: string;
    listDigest: string;
    listManagerMaps: string;
};

type CountItem = {
    key: string;
    count: number;
};

type RecentLog = {
    id: number;
    timecreated: number;
    eventtype: string;
    channel: string;
    status: string;
    userid: number;
    learnername: string;
    courseid: number;
    coursename: string;
};

type DashboardResponse = {
    sent30: number;
    failed30: number;
    sentall: number;
    pending: number;
    failedqueue: number;
    cancelled: number;
    digestpending: number;
    activerules: number;
    disabledrules: number;
    templates: number;
    coursesettings: number;
    expiringsoon: number;
    byevent: CountItem[];
    bychannel: CountItem[];
    bystatus: CountItem[];
    recent: RecentLog[];
};

type ListResponse<Row> = {
    items: Row[];
    total: number;
    page: number;
    perpage: number;
    mcrprovided?: boolean;
    canwrite?: boolean;
};

type RuleRow = {
    id: number;
    name: string;
    enabled: boolean;
    eventtype: string;
    eventlabel: string;
    scope: string;
    scopelabel: string;
    coursename: string;
    categoryname: string;
    recipient: string;
    recipientlabel: string;
    templatename: string;
    channellist: string[];
    daysbefore: number;
    digest: string;
    digestlabel: string;
    priority: number;
    editurl: string;
};

type TemplateRow = {
    id: number;
    name: string;
    subject: string;
    updatedat: number;
    editurl: string;
    previewurl: string;
};

type CourseSettingRow = {
    id: number;
    courseid: number;
    coursename: string;
    enabled: boolean;
    templatename: string;
    usecustommessage: boolean;
    updatedat: number;
    settingsurl: string;
};

type QueueRow = {
    id: number;
    eventtype: string;
    rulename: string;
    learnername: string;
    coursename: string;
    templatename: string;
    recipientname: string;
    recipientemail: string;
    channel: string;
    status: string;
    attempts: number;
    lasterror: string;
    scheduledtime: number;
    senttime: number;
};

type LogRow = {
    id: number;
    eventtype: string;
    rulename: string;
    learnername: string;
    coursename: string;
    recipientemail: string;
    channel: string;
    status: string;
    subject: string;
    errormsg: string;
    timecreated: number;
};

type DigestRow = {
    id: number;
    recipientname: string;
    recipientemail: string;
    frequency: string;
    rulename: string;
    learnername: string;
    coursename: string;
    eventtype: string;
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

type TabKey = "rules" | "templates" | "settings" | "queue" | "logs" | "digest" | "managers";
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
    mcrprovided?: boolean;
    canwrite?: boolean;
};

type EnrolmentNotifierAdminProps = {
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
        value === "settings" ||
        value === "queue" ||
        value === "logs" ||
        value === "digest" ||
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
    if (tab === "settings") {
        return labels.courseSettings;
    }
    if (tab === "queue") {
        return labels.queue;
    }
    if (tab === "logs") {
        return labels.logs;
    }
    if (tab === "digest") {
        return labels.digest;
    }

    return labels.managers;
};

const statusVariant = (status: string): string => {
    const normalised = status.toLowerCase();
    if (["sent", "enabled", "complete", "success", "ok"].includes(normalised)) {
        return "success";
    }
    if (["pending", "sending", "daily", "weekly"].includes(normalised)) {
        return "warning";
    }
    if (["failed", "cancelled", "disabled"].includes(normalised)) {
        return "danger";
    }
    return "neutral";
};

const humanise = (value: string): string => value.replace(/_/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase());

const StatusBadge = ({status, label}: {status: string; label?: string}) => (
    <span className={mcClasses(`mc-badge mc-badge--${statusVariant(status)}`)}>{label ?? humanise(status)}</span>
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
            <label className={mcClasses("mc-filter-label")} htmlFor="mc-enrolment-notifier-search">
                {labels.search}
            </label>
            <span className={mcClasses("mc-search")}>
                <i className="bi bi-search mc-search__icon" aria-hidden="true" />
                <input
                    className={mcClasses("mc-search-input")}
                    id="mc-enrolment-notifier-search"
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

const DistributionList = ({title, items}: {title: string; items: CountItem[]}) => (
    <div>
        <h3 className={mcClasses("mc-card-subtitle")}>{title}</h3>
        <div className="d-flex flex-column gap-2">
            {items.length === 0 && <span className="text-muted small">-</span>}
            {items.map((item) => (
                <div className="d-flex align-items-center justify-content-between gap-3" key={`${title}-${item.key}`}>
                    <span>{humanise(item.key)}</span>
                    <span className={mcClasses("mc-badge mc-badge--neutral")}>{formatCount(item.count)}</span>
                </div>
            ))}
        </div>
    </div>
);

const RecentActivity = ({items, labels}: {items: RecentLog[]; labels: Labels}) => (
    <div className="d-flex flex-column gap-3">
        {items.length === 0 && <EmptyState labels={labels} />}
        {items.map((item) => (
            <div className="d-flex align-items-start justify-content-between gap-3" key={item.id}>
                <div>
                    <div className="fw-semibold">{item.learnername}</div>
                    <div className="text-muted small">{item.coursename}</div>
                    <div className="text-muted small">{humanise(item.eventtype)} · {item.channel}</div>
                </div>
                <div className="text-end">
                    <StatusBadge status={item.status} />
                    <div className="text-muted small mt-1">{formatDate(item.timecreated)}</div>
                </div>
            </div>
        ))}
    </div>
);

export default function EnrolmentNotifierAdmin({
    methods,
    initialView,
    legacyUrl,
    perPageOptions,
    labels,
}: EnrolmentNotifierAdminProps) {
    useModernCommerceClassSync();

    const currentView = normaliseInitialView(initialView);
    const isOverview = currentView === "overview";
    const showRulesSummary = currentView === "rules";
    const shouldLoadDashboard = isOverview || showRulesSummary;
    const [dashboard, setDashboard] = useState<{loading: boolean; error: string; data: DashboardResponse | null}>({
        loading: true,
        error: "",
        data: null,
    });
    const [activeTab] = useState<TabKey>(currentView === "overview" ? "rules" : currentView);
    const [filters, setFilters] = useState<Record<TabKey, TabFilter>>({
        rules: initialFilter(),
        templates: initialFilter(),
        settings: initialFilter(),
        queue: initialFilter(),
        logs: initialFilter(),
        digest: initialFilter(),
        managers: initialFilter(),
    });
    const [sections, setSections] = useState<Record<TabKey, SectionState<unknown>>>({
        rules: emptySection<RuleRow>(),
        templates: emptySection<TemplateRow>(),
        settings: emptySection<CourseSettingRow>(),
        queue: emptySection<QueueRow>(),
        logs: emptySection<LogRow>(),
        digest: emptySection<DigestRow>(),
        managers: emptySection<ManagerRow>(),
    });
    const [reloadToken, setReloadToken] = useState(0);

    useEffect(() => {
        const refreshButton = document.getElementById("moderncommerce-enrolment-notifier-refresh");
        const refresh = () => setReloadToken((current) => current + 1);
        refreshButton?.addEventListener("click", refresh);
        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    useEffect(() => {
        let cancelled = false;

        if (!shouldLoadDashboard) {
            setDashboard({loading: false, error: "", data: null});
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

        return () => {
            cancelled = true;
        };
    }, [methods.dashboard, reloadToken, shouldLoadDashboard]);

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
                    mcrprovided: result.mcrprovided,
                    canwrite: result.canwrite,
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
                    eventtype: "",
                    enabled: "",
                    courseid: 0,
                });
            }
            if (activeTab === "templates") {
                return callMoodleService<ListResponse<TemplateRow>>(methods.listTemplates, args);
            }
            if (activeTab === "settings") {
                return callMoodleService<ListResponse<CourseSettingRow>>(methods.listCourseSettings, {
                    ...args,
                    enabled: "",
                });
            }
            if (activeTab === "queue") {
                return callMoodleService<ListResponse<QueueRow>>(methods.listQueue, {
                    ...args,
                    status: "",
                    courseid: 0,
                    eventtype: "",
                    channel: "",
                });
            }
            if (activeTab === "logs") {
                return callMoodleService<ListResponse<LogRow>>(methods.listLogs, {
                    ...args,
                    status: "",
                    courseid: 0,
                    eventtype: "",
                    channel: "",
                });
            }
            if (activeTab === "digest") {
                return callMoodleService<ListResponse<DigestRow>>(methods.listDigest, {
                    ...args,
                    status: "",
                    frequency: "",
                    courseid: 0,
                    eventtype: "",
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
        methods.listCourseSettings,
        methods.listDigest,
        methods.listLogs,
        methods.listManagerMaps,
        methods.listQueue,
        methods.listRules,
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
        {label: labels.sent30, value: formatCount(dashboardData.sent30), icon: "bi-send-check", variant: "success"},
        {label: labels.failed30, value: formatCount(dashboardData.failed30), icon: "bi-exclamation-triangle", variant: "danger"},
        {label: labels.pendingQueue, value: formatCount(dashboardData.pending), icon: "bi-hourglass-split", variant: "warning"},
        {label: labels.digestPending, value: formatCount(dashboardData.digestpending), icon: "bi-collection", variant: "info"},
        {label: labels.templatesTotal, value: formatCount(dashboardData.templates), icon: "bi-envelope-paper", variant: "primary"},
        {label: labels.courseSettingsTotal, value: formatCount(dashboardData.coursesettings), icon: "bi-sliders", variant: "neutral"},
        {label: labels.expiringSoon, value: formatCount(dashboardData.expiringsoon), icon: "bi-calendar-event", variant: "warning"},
    ] : [];
    const rulesMetrics = dashboardData ? [
        {label: labels.activeRules, value: formatCount(dashboardData.activerules), icon: "bi-diagram-3", variant: "primary"},
        {label: labels.disabledRules, value: formatCount(dashboardData.disabledrules), icon: "bi-slash-circle", variant: "neutral"},
        {label: labels.templatesTotal, value: formatCount(dashboardData.templates), icon: "bi-envelope-paper", variant: "primary"},
        {label: labels.pendingQueue, value: formatCount(dashboardData.pending), icon: "bi-hourglass-split", variant: "warning"},
        {label: labels.sent30, value: formatCount(dashboardData.sent30), icon: "bi-send-check", variant: "success"},
    ] : [];

    return (
        <section className={mcClasses("mc-dashboard")} aria-label={labels.title}>
            {isOverview && dashboard.error && <SectionError message={dashboard.error} labels={labels} />}

            {isOverview && dashboard.loading && !dashboardData ? (
                <LoadingState labels={labels} />
            ) : isOverview && dashboardData && (
                <>
                    <div className={mcClasses("mc-alert mc-alert--success mb-3")} role="status">
                        <i className="bi bi-bell mc-alert__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>
                            <p className={mcClasses("mc-alert__title")}>{labels.title}</p>
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
                                    <h2 className={mcClasses("mc-card-title")}>{labels.recent}</h2>
                                </div>
                                <div className={mcClasses("mc-card-body")}>
                                    <RecentActivity items={dashboardData.recent} labels={labels} />
                                </div>
                            </section>
                        </div>
                        <div className="col-lg-5">
                            <section className={mcClasses("mc-card h-100")}>
                                <div className={mcClasses("mc-card-header")}>
                                    <h2 className={mcClasses("mc-card-title")}>{labels.byStatus}</h2>
                                </div>
                                <div className={mcClasses("mc-card-body")}>
                                    <div className="row g-3">
                                        <div className="col-md-4 col-lg-12">
                                            <DistributionList title={labels.byStatus} items={dashboardData.bystatus} />
                                        </div>
                                        <div className="col-md-4 col-lg-12">
                                            <DistributionList title={labels.byEvent} items={dashboardData.byevent} />
                                        </div>
                                        <div className="col-md-4 col-lg-12">
                                            <DistributionList title={labels.byChannel} items={dashboardData.bychannel} />
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </>
            )}

            {!isOverview && (
                <>
                    {showRulesSummary && rulesMetrics.length > 0 && (
                        <section className={mcClasses("mc-stat-strip mb-3")} aria-label={sectionTitle(activeTab, labels)}>
                            {rulesMetrics.map((metric) => (
                                <MetricTile key={metric.label} {...metric} />
                            ))}
                        </section>
                    )}

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
                        alert={activeTab === "managers" && sections.managers.mcrprovided ? (
                            <div className={mcClasses("mc-alert mc-alert--info mb-3")} role="status">
                                <i className="bi bi-info-circle mc-alert__icon" aria-hidden="true" />
                                <div className={mcClasses("mc-alert__body")}>{labels.mcrManagermap}</div>
                            </div>
                        ) : undefined}
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
                </>
            )}
        </section>
    );
}

const renderActiveSection = (
    activeTab: TabKey,
    section: SectionState<unknown>,
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
            {activeTab === "settings" && renderCourseSettingsTable(section.items as CourseSettingRow[], labels)}
            {activeTab === "queue" && renderQueueTable(section.items as QueueRow[], labels)}
            {activeTab === "logs" && renderLogsTable(section.items as LogRow[], labels)}
            {activeTab === "digest" && renderDigestTable(section.items as DigestRow[], labels)}
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
                <th>{labels.event}</th>
                <th>{labels.recipient}</th>
                <th>{labels.channel}</th>
                <th>{labels.template}</th>
                <th>{labels.priority}</th>
                <th>{labels.status}</th>
                <th>{labels.view}</th>
            </tr>
        </thead>
        <tbody>
            {items.map((item) => (
                <tr key={item.id}>
                    <td className="fw-semibold">{item.name}</td>
                    <td>
                        <div>{item.scopelabel || item.scope}</div>
                        <div className="text-muted small">{item.coursename || item.categoryname || ""}</div>
                    </td>
                    <td>{item.eventlabel || humanise(item.eventtype)}</td>
                    <td>{item.recipientlabel || humanise(item.recipient)}</td>
                    <td>{item.channellist.join(", ") || "-"}</td>
                    <td>{item.templatename || "-"}</td>
                    <td>{formatCount(item.priority)}</td>
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
                <th>{labels.subject}</th>
                <th>{labels.updated}</th>
                <th>{labels.view}</th>
            </tr>
        </thead>
        <tbody>
            {items.map((item) => (
                <tr key={item.id}>
                    <td className="fw-semibold">{item.name}</td>
                    <td>{item.subject || "-"}</td>
                    <td>{formatDate(item.updatedat)}</td>
                    <td><ExternalLink url={item.editurl || item.previewurl} labels={labels} /></td>
                </tr>
            ))}
        </tbody>
    </table>
);

const renderCourseSettingsTable = (items: CourseSettingRow[], labels: Labels) => (
    <table className={mcClasses("table mc-table mc-product-table mb-0")}>
        <thead>
            <tr>
                <th>{labels.course}</th>
                <th>{labels.template}</th>
                <th>{labels.customMessage}</th>
                <th>{labels.status}</th>
                <th>{labels.updated}</th>
                <th>{labels.view}</th>
            </tr>
        </thead>
        <tbody>
            {items.map((item) => (
                <tr key={item.id || item.courseid}>
                    <td className="fw-semibold">{item.coursename || `#${item.courseid}`}</td>
                    <td>{item.templatename || "-"}</td>
                    <td>{item.usecustommessage ? labels.enabled : labels.disabled}</td>
                    <td><StatusBadge status={item.enabled ? "enabled" : "disabled"} label={item.enabled ? labels.enabled : labels.disabled} /></td>
                    <td>{formatDate(item.updatedat)}</td>
                    <td><ExternalLink url={item.settingsurl} labels={labels} /></td>
                </tr>
            ))}
        </tbody>
    </table>
);

const renderQueueTable = (items: QueueRow[], labels: Labels) => (
    <table className={mcClasses("table mc-table mc-product-table mb-0")}>
        <thead>
            <tr>
                <th>{labels.learner}</th>
                <th>{labels.recipient}</th>
                <th>{labels.course}</th>
                <th>{labels.event}</th>
                <th>{labels.channel}</th>
                <th>{labels.status}</th>
                <th>{labels.attempts}</th>
                <th>{labels.scheduled}</th>
                <th>{labels.error}</th>
            </tr>
        </thead>
        <tbody>
            {items.map((item) => (
                <tr key={item.id}>
                    <td className="fw-semibold">{item.learnername}</td>
                    <td>
                        <div>{item.recipientname || "-"}</div>
                        <div className="text-muted small">{item.recipientemail}</div>
                    </td>
                    <td>{item.coursename || "-"}</td>
                    <td>{humanise(item.eventtype)}</td>
                    <td>{item.channel}</td>
                    <td><StatusBadge status={item.status} /></td>
                    <td>{formatCount(item.attempts)}</td>
                    <td>{formatDate(item.scheduledtime)}</td>
                    <td>{item.lasterror || "-"}</td>
                </tr>
            ))}
        </tbody>
    </table>
);

const renderLogsTable = (items: LogRow[], labels: Labels) => (
    <table className={mcClasses("table mc-table mc-product-table mb-0")}>
        <thead>
            <tr>
                <th>{labels.learner}</th>
                <th>{labels.course}</th>
                <th>{labels.event}</th>
                <th>{labels.channel}</th>
                <th>{labels.subject}</th>
                <th>{labels.status}</th>
                <th>{labels.date}</th>
                <th>{labels.error}</th>
            </tr>
        </thead>
        <tbody>
            {items.map((item) => (
                <tr key={item.id}>
                    <td className="fw-semibold">{item.learnername}</td>
                    <td>{item.coursename || "-"}</td>
                    <td>{humanise(item.eventtype)}</td>
                    <td>{item.channel}</td>
                    <td>{item.subject || "-"}</td>
                    <td><StatusBadge status={item.status} /></td>
                    <td>{formatDate(item.timecreated)}</td>
                    <td>{item.errormsg || "-"}</td>
                </tr>
            ))}
        </tbody>
    </table>
);

const renderDigestTable = (items: DigestRow[], labels: Labels) => (
    <table className={mcClasses("table mc-table mc-product-table mb-0")}>
        <thead>
            <tr>
                <th>{labels.recipient}</th>
                <th>{labels.learner}</th>
                <th>{labels.course}</th>
                <th>{labels.event}</th>
                <th>{labels.frequency}</th>
                <th>{labels.rule}</th>
                <th>{labels.status}</th>
                <th>{labels.date}</th>
            </tr>
        </thead>
        <tbody>
            {items.map((item) => (
                <tr key={item.id}>
                    <td>
                        <div className="fw-semibold">{item.recipientname}</div>
                        <div className="text-muted small">{item.recipientemail}</div>
                    </td>
                    <td>{item.learnername}</td>
                    <td>{item.coursename || "-"}</td>
                    <td>{humanise(item.eventtype)}</td>
                    <td>{item.frequency}</td>
                    <td>{item.rulename || "-"}</td>
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

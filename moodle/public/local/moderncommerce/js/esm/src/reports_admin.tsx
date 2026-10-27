// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin reports/analytics for Modern Commerce.
 *
 * @module     local_moderncommerce/reports_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {type ReactNode, useEffect, useMemo, useState} from "react";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {McButton} from "./button";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import {McDrawer} from "./drawer";
import {McTableCard, McTableFooter, McTablePagination} from "./table_components";

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

type Stats = {
    displayrevenue: string;
    totalorders: number;
    displayaverage: string;
    couponsused: number;
};

type SalesRow = {
    label: string;
    rawrevenue: number;
    displayrevenue: string;
    orders: number;
    displayaverage: string;
};

type CourseRow = {
    rank: number;
    courseid: number;
    fullname: string;
    orders: number;
    enrollments: number;
    rawrevenue: number;
    displayrevenue: string;
};

type CouponRow = {
    code: string;
    typelabel: string;
    valueformatted: string;
    usages: number;
    displaytotaldiscount: string;
};

type Metric = {
    key: string;
    label: string;
    value: string;
    variant: string;
    icon: string;
    hasdelta: boolean;
    delta: string;
    deltaup: boolean;
    deltadown: boolean;
    size: number;
};

type ChartSeries = {
    key: string;
    label: string;
    charttype: string;
    axis: string;
    data: number[];
};

type Chart = {
    id: string;
    type: string;
    title: string;
    subtitle: string;
    formattype: string;
    total: string;
    empty: boolean;
    size: number;
    labels: string[];
    series: ChartSeries[];
};

type ReportLayoutSection = "metrics" | "charts";

type ReportSizeMap = Record<string, number>;

type ColumnDef = {
    key: string;
    label: string;
    default: boolean;
    align: string;
};

type TableCell = {
    key: string;
    value: string;
    exportvalue: string;
    badge: boolean;
    badgeclass: string;
    href: string;
};

type TableRow = {
    cells: TableCell[];
};

type ReportResponse = {
    type: string;
    period: string;
    from: number;
    to: number;
    stats: Stats;
    metrics: Metric[];
    charts: Chart[];
    sales: {maxrevenue: number; rows: SalesRow[]};
    courses: CourseRow[];
    coupons: CouponRow[];
    availablecolumns: ColumnDef[];
    selectedcolumns: string[];
    tablerows: TableRow[];
    tabletotal: number;
    tablepage: number;
    tableperpage: number;
    tabletruncated: boolean;
};

type ReportVisibility = {
    metrics: string[];
    charts: string[];
    metricSizes: ReportSizeMap;
    chartSizes: ReportSizeMap;
    metricOrder: string[];
    chartOrder: string[];
};

type ReportsAdminProps = {
    methodName: string;
    initialType: string;
    initialPeriod: string;
    initialDateRange?: string;
    initialFrom: number;
    initialTo: number;
    initialProductSearch?: string;
    initialCourseSearch?: string;
    initialTableSearch?: string;
    exportBase: string;
    sesskey: string;
    perPageOptions?: number[];
    reportTypes: SelectOption[];
    periodOptions: SelectOption[];
    dateRangeOptions: SelectOption[];
    labels: Labels;
};

const DEFAULT_PER_PAGE_OPTIONS = [10, 25, 50, 100];
const TILE_SPANS = [12, 6, 4, 3];
const tileSpanClass = (size?: number): string =>
    `mc-stat-tile--span${TILE_SPANS.includes(size as number) ? size : 3}`;
const chartSpanClass = (size?: number): string =>
    `mc-chart-card--span${TILE_SPANS.includes(size as number) ? size : 6}`;
const emptyReportVisibility = (): ReportVisibility => ({
    metrics: [],
    charts: [],
    metricSizes: {},
    chartSizes: {},
    metricOrder: [],
    chartOrder: [],
});
const clampGridSize = (size?: number, fallback = 3): number => TILE_SPANS.includes(size as number) ? Number(size) : fallback;

const CHART_COLORS: Record<string, string> = {
    revenue: "#2563eb",
    net: "#2563eb",
    gross: "#93c5fd",
    orders: "#2563eb",
    paid: "#16a34a",
    rate: "#f59e0b",
    discount: "#dc2626",
    returning: "#7c3aed",
    new: "#0891b2",
    saves: "#65a30d",
};

const PALETTE = ["#2563eb", "#16a34a", "#f59e0b", "#7c3aed", "#0891b2", "#db2777", "#65a30d", "#dc2626"];

const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php?sesskey=${encodeURIComponent(M.cfg.sesskey)}`
        + `&info=${encodeURIComponent(methodName)}`;
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

const tsToDateInput = (timestamp: number): string => {
    if (!timestamp) {
        return "";
    }
    const date = new Date(timestamp * 1000);
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${date.getFullYear()}-${month}-${day}`;
};

const dateToInput = (date: Date): string => {
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${date.getFullYear()}-${month}-${day}`;
};

const addDays = (date: Date, days: number): Date =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);

const resolveDateRange = (range: string): {from: string; to: string} | null => {
    const today = new Date();
    const end = new Date(today.getFullYear(), today.getMonth(), today.getDate());

    switch (range) {
        case "today":
            return {from: dateToInput(end), to: dateToInput(end)};
        case "last7days":
            return {from: dateToInput(addDays(end, -6)), to: dateToInput(end)};
        case "last30days":
            return {from: dateToInput(addDays(end, -30)), to: dateToInput(end)};
        case "thismonth":
            return {from: dateToInput(new Date(end.getFullYear(), end.getMonth(), 1)), to: dateToInput(end)};
        case "lastmonth": {
            const first = new Date(end.getFullYear(), end.getMonth() - 1, 1);
            const last = new Date(end.getFullYear(), end.getMonth(), 0);
            return {from: dateToInput(first), to: dateToInput(last)};
        }
        case "thisyear":
            return {from: dateToInput(new Date(end.getFullYear(), 0, 1)), to: dateToInput(end)};
        default:
            return null;
    }
};

const dateInputToTs = (value: string, endOfDay = false): number => {
    if (!value) {
        return 0;
    }
    const time = endOfDay ? "23:59:59" : "00:00:00";
    const ts = new Date(`${value}T${time}`).getTime();
    return Number.isFinite(ts) ? Math.floor(ts / 1000) : 0;
};

const storageKey = (type: string): string => `local-moderncommerce-report-columns-${type}`;
const visibilityStorageKey = (type: string): string => `local-moderncommerce-report-visibility-${type}`;

const readStoredColumns = (type: string): string[] => {
    if (typeof window === "undefined") {
        return [];
    }
    try {
        const stored = window.localStorage.getItem(storageKey(type));
        const parsed = stored ? JSON.parse(stored) : [];
        return Array.isArray(parsed) ? parsed.filter((key) => typeof key === "string") : [];
    } catch {
        return [];
    }
};

const saveStoredColumns = (type: string, columns: string[]): void => {
    if (typeof window === "undefined") {
        return;
    }
    window.localStorage.setItem(storageKey(type), JSON.stringify(columns));
};

const normaliseVisibility = (value: unknown): ReportVisibility => {
    if (!value || typeof value !== "object") {
        return emptyReportVisibility();
    }
    const candidate = value as Partial<ReportVisibility>;
    const normaliseSizeMap = (sizes: unknown): ReportSizeMap => {
        if (!sizes || typeof sizes !== "object") {
            return {};
        }

        return Object.entries(sizes as Record<string, unknown>).reduce<ReportSizeMap>((result, [key, size]) => {
            if (typeof key === "string" && TILE_SPANS.includes(Number(size))) {
                result[key] = Number(size);
            }

            return result;
        }, {});
    };
    const normaliseOrder = (order: unknown): string[] =>
        Array.isArray(order) ? order.filter((key) => typeof key === "string") : [];

    return {
        metrics: Array.isArray(candidate.metrics) ? candidate.metrics.filter((key) => typeof key === "string") : [],
        charts: Array.isArray(candidate.charts) ? candidate.charts.filter((key) => typeof key === "string") : [],
        metricSizes: normaliseSizeMap(candidate.metricSizes),
        chartSizes: normaliseSizeMap(candidate.chartSizes),
        metricOrder: normaliseOrder(candidate.metricOrder),
        chartOrder: normaliseOrder(candidate.chartOrder),
    };
};

const readStoredVisibility = (type: string): ReportVisibility => {
    if (typeof window === "undefined") {
        return emptyReportVisibility();
    }
    try {
        const stored = window.localStorage.getItem(visibilityStorageKey(type));
        return normaliseVisibility(stored ? JSON.parse(stored) : null);
    } catch {
        return emptyReportVisibility();
    }
};

const saveStoredVisibility = (type: string, visibility: ReportVisibility): void => {
    if (typeof window === "undefined") {
        return;
    }
    window.localStorage.setItem(visibilityStorageKey(type), JSON.stringify(visibility));
};

const mergeOrder = (stored: string[], available: string[]): string[] => {
    const allowed = new Set(available);
    const ordered = stored.filter((key, index) => allowed.has(key) && stored.indexOf(key) === index);
    available.forEach((key) => {
        if (!ordered.includes(key)) {
            ordered.push(key);
        }
    });
    return ordered;
};

const normaliseSizePreferences = <T extends {size: number}>(
    sizes: ReportSizeMap,
    items: T[],
    getId: (item: T) => string,
): ReportSizeMap =>
    items.reduce<ReportSizeMap>((result, item) => {
        const key = getId(item);
        result[key] = clampGridSize(sizes[key] ?? item.size, item.size);
        return result;
    }, {});

const visibilityWithDefaults = (
    visibility: ReportVisibility,
    metrics: Metric[],
    charts: Chart[],
): ReportVisibility => ({
    metrics: visibility.metrics.filter((key) => metrics.some((metric) => metric.key === key)),
    charts: visibility.charts.filter((key) => charts.some((chart) => chart.id === key)),
    metricSizes: normaliseSizePreferences(visibility.metricSizes, metrics, (metric) => metric.key),
    chartSizes: normaliseSizePreferences(visibility.chartSizes, charts, (chart) => chart.id),
    metricOrder: mergeOrder(visibility.metricOrder, metrics.map((metric) => metric.key)),
    chartOrder: mergeOrder(visibility.chartOrder, charts.map((chart) => chart.id)),
});

const applyItemLayout = <T extends {size: number}>(
    items: T[],
    hidden: string[],
    sizes: ReportSizeMap,
    order: string[],
    getId: (item: T) => string,
): T[] => {
    const orderIndex = new Map(order.map((key, index) => [key, index]));
    const originalIndex = new Map(items.map((item, index) => [getId(item), index]));
    return [...items]
        .sort((a, b) => {
            const aKey = getId(a);
            const bKey = getId(b);
            const aOrder = orderIndex.has(aKey) ? orderIndex.get(aKey) as number : Number.MAX_SAFE_INTEGER;
            const bOrder = orderIndex.has(bKey) ? orderIndex.get(bKey) as number : Number.MAX_SAFE_INTEGER;
            if (aOrder !== bOrder) {
                return aOrder - bOrder;
            }
            return (originalIndex.get(aKey) ?? 0) - (originalIndex.get(bKey) ?? 0);
        })
        .filter((item) => !hidden.includes(getId(item)))
        .map((item) => ({...item, size: clampGridSize(sizes[getId(item)] ?? item.size, item.size)}));
};

const normalisePerPageOptions = (options?: number[]): number[] => {
    const values = (options && options.length > 0 ? options : DEFAULT_PER_PAGE_OPTIONS)
        .map((option) => Number(option))
        .filter((option) => Number.isFinite(option) && option > 0 && option <= 100);
    return Array.from(new Set(values)).sort((a, b) => a - b);
};

const defaultColumns = (columns: ColumnDef[]): string[] =>
    columns.filter((column) => column.default).map((column) => column.key);

const normaliseColumns = (columns: ColumnDef[], selected: string[]): string[] => {
    const allowed = new Set(columns.map((column) => column.key));
    const clean = selected.filter((key, index) => allowed.has(key) && selected.indexOf(key) === index);
    return clean.length > 0 ? clean : defaultColumns(columns);
};

const colorFor = (key: string, index: number): string => CHART_COLORS[key] ?? PALETTE[index % PALETTE.length];

const niceMax = (value: number): number => {
    if (value <= 0) {
        return 1;
    }
    const pow = Math.pow(10, Math.floor(Math.log10(value)));
    return Math.ceil(value / pow) * pow;
};

const displayChartValue = (value: number, type: string): string => {
    if (type === "percent") {
        return `${Math.round(value)}%`;
    }
    return new Intl.NumberFormat(document.documentElement.lang || undefined).format(Math.round(value));
};

const MetricStrip = ({metrics}: {metrics: Metric[]}) => (
    <div className={mcClasses("mc-stat-strip mc-stat-strip--grid mc-report-metrics")}>
        {metrics.map((metric) => (
            <article
                className={mcClasses(`mc-stat-tile mc-stat-tile--${metric.variant} ${tileSpanClass(metric.size)}`)}
                key={metric.key}
            >
                <i className={`bi ${metric.icon} mc-stat-tile__icon`} aria-hidden="true" />
                <div className={mcClasses("mc-stat-tile__body")}>
                    <span className={mcClasses("mc-stat-tile__label")}>{metric.label}</span>
                    <strong className={mcClasses("mc-stat-tile__value")}>{metric.value}</strong>
                </div>
                <i className={`bi ${metric.icon} mc-stat-tile__watermark`} aria-hidden="true" />
            </article>
        ))}
    </div>
);

const MetricSection = ({
    metrics,
    charts,
    title,
    chartTitle,
    empty,
    filters,
    actions,
}: {
    metrics: Metric[];
    charts: Chart[];
    title: string;
    chartTitle: string;
    empty: string;
    filters?: ReactNode;
    actions?: ReactNode;
}) => (
    <section
        className={mcClasses("mc-card mc-card--table-design mc-report-metrics-card")}
        aria-labelledby="mc-report-metrics-title"
    >
        <div className={mcClasses("mc-card-header mc-report-metrics-header")}>
            <div className={mcClasses("mc-table-design-summary mc-report-metrics-summary")}>
                <h2 className={mcClasses("mc-card-title mb-0")} id="mc-report-metrics-title">{title}</h2>
            </div>
            {actions && <div className={mcClasses("mc-report-metrics-actions")}>{actions}</div>}
        </div>
        <div className={mcClasses("mc-report-metrics-body")}>
            {filters}
            {metrics.length > 0 && <MetricStrip metrics={metrics} />}
            {charts.length > 0 && (
                <div className={mcClasses("mc-charts mc-report-charts")}>
                    <div className={mcClasses("mc-charts__head")}>
                        <h2 className={mcClasses("mc-charts__title")}>{chartTitle}</h2>
                    </div>
                    <div className={mcClasses("mc-charts-grid")}>
                        {charts.map((chart) => (
                            <section className={mcClasses(`mc-chart-card ${chartSpanClass(chart.size)}`)} key={chart.id}>
                                <div className={mcClasses("mc-chart-card__head")}>
                                    <div>
                                        <h3 className={mcClasses("mc-chart-card__title")}>{chart.title}</h3>
                                        <p className={mcClasses("mc-chart-card__sub")}>{chart.subtitle}</p>
                                    </div>
                                    {chart.total && <span className={mcClasses("mc-chart-card__total")}>{chart.total}</span>}
                                </div>
                                <div className={mcClasses("mc-chart-card__body")}>
                                    <ReportChart chart={chart} empty={empty} />
                                </div>
                            </section>
                        ))}
                    </div>
                </div>
            )}
        </div>
    </section>
);

const CartesianChart = ({chart}: {chart: Chart}) => {
    const width = (chart.size ?? 6) >= 12 ? 980 : 520;
    const height = 230;
    const padLeft = 42;
    const padRight = chart.series.some((series) => series.axis === "right") ? 36 : 14;
    const padTop = 14;
    const padBottom = 34;
    const plotWidth = width - padLeft - padRight;
    const plotHeight = height - padTop - padBottom;
    const count = Math.max(chart.labels.length, 1);
    const band = plotWidth / count;
    const leftSeries = chart.series.filter((series) => series.axis !== "right");
    const rightSeries = chart.series.filter((series) => series.axis === "right");
    const barSeries = leftSeries.filter((series) => series.charttype === "bar");
    const lineSeries = leftSeries.filter((series) => series.charttype === "line");
    const leftMax = niceMax(Math.max(1, ...leftSeries.flatMap((series) => series.data)));
    const rightMaxRaw = rightSeries.length ? Math.max(1, ...rightSeries.flatMap((series) => series.data)) : 1;
    const rightMax = rightMaxRaw <= 100 ? 100 : niceMax(rightMaxRaw);
    const yLeft = (value: number) => padTop + plotHeight - (value / leftMax) * plotHeight;
    const yRight = (value: number) => padTop + plotHeight - (value / rightMax) * plotHeight;
    const centerX = (index: number) => padLeft + band * index + band / 2;
    const barGroupWidth = band * 0.62;
    const barWidth = barSeries.length > 0 ? barGroupWidth / barSeries.length : barGroupWidth;
    const everyX = count > 10 ? Math.ceil(count / 8) : 1;
    const ticks = [0, 0.25, 0.5, 0.75, 1];

    return (
        <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label={chart.title}>
            {ticks.map((tick) => {
                const y = padTop + plotHeight - tick * plotHeight;
                return (
                    <g key={tick}>
                        <line x1={padLeft} y1={y} x2={padLeft + plotWidth} y2={y} stroke="#eef2f7" strokeWidth={1} />
                        <text x={padLeft - 6} y={y + 3} textAnchor="end" fontSize={9} fill="#64748b">
                            {displayChartValue(leftMax * tick, chart.formattype)}
                        </text>
                    </g>
                );
            })}
            {barSeries.map((series, seriesIndex) => (
                <g key={series.key}>
                    {series.data.map((value, index) => {
                        const h = (value / leftMax) * plotHeight;
                        const x = padLeft + band * index + (band - barGroupWidth) / 2 + seriesIndex * barWidth;
                        const y = padTop + plotHeight - h;
                        return (
                            <rect
                                fill={colorFor(series.key, seriesIndex)}
                                height={Math.max(0, h)}
                                key={index}
                                rx={2}
                                width={Math.max(2, barWidth - 2)}
                                x={x}
                                y={y}
                            >
                                <title>{`${chart.labels[index]} · ${series.label}: ${displayChartValue(value, chart.formattype)}`}</title>
                            </rect>
                        );
                    })}
                </g>
            ))}
            {lineSeries.map((series, seriesIndex) => {
                const points = series.data.map((value, index) => `${centerX(index)},${yLeft(value)}`).join(" ");
                const color = colorFor(series.key, seriesIndex + barSeries.length);
                return (
                    <g key={series.key}>
                        <polyline points={points} fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
                        {series.data.map((value, index) => (
                            <circle cx={centerX(index)} cy={yLeft(value)} fill={color} key={index} r={2.5}>
                                <title>{`${chart.labels[index]} · ${series.label}: ${displayChartValue(value, chart.formattype)}`}</title>
                            </circle>
                        ))}
                    </g>
                );
            })}
            {rightSeries.map((series, seriesIndex) => {
                const points = series.data.map((value, index) => `${centerX(index)},${yRight(value)}`).join(" ");
                const color = colorFor(series.key, seriesIndex + leftSeries.length);
                return (
                    <g key={series.key}>
                        <polyline
                            points={points}
                            fill="none"
                            stroke={color}
                            strokeDasharray="4 3"
                            strokeWidth={2}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                        {series.data.map((value, index) => (
                            <circle cx={centerX(index)} cy={yRight(value)} fill={color} key={index} r={2.5}>
                                <title>{`${chart.labels[index]} · ${series.label}: ${Math.round(value)}%`}</title>
                            </circle>
                        ))}
                    </g>
                );
            })}
            <line x1={padLeft} y1={padTop + plotHeight} x2={padLeft + plotWidth} y2={padTop + plotHeight} stroke="#cbd5e1" />
            {chart.labels.map((label, index) => (
                index % everyX === 0 ? (
                    <text key={label + index} x={centerX(index)} y={height - 12} textAnchor="middle" fontSize={9} fill="#64748b">
                        {label}
                    </text>
                ) : null
            ))}
        </svg>
    );
};

const HorizontalBarChart = ({chart}: {chart: Chart}) => {
    const values = chart.series[0]?.data ?? [];
    const max = niceMax(Math.max(1, ...values));

    return (
        <div className={mcClasses("mc-report-hbar")} role="img" aria-label={chart.title}>
            {chart.labels.map((label, index) => {
                const value = values[index] ?? 0;
                const width = Math.max(2, (value / max) * 100);
                return (
                    <div className={mcClasses("mc-report-hbar__row")} key={`${label}-${index}`}>
                        <span className={mcClasses("mc-report-hbar__label")} title={label}>{label}</span>
                        <span className={mcClasses("mc-report-hbar__track")}>
                            <span className={mcClasses("mc-report-hbar__bar")} style={{width: `${width}%`}} />
                        </span>
                        <span className={mcClasses("mc-report-hbar__value")}>{displayChartValue(value, chart.formattype)}</span>
                    </div>
                );
            })}
        </div>
    );
};

const DonutLegendChart = ({chart}: {chart: Chart}) => {
    const values = chart.series[0]?.data ?? [];
    const total = values.reduce((sum, value) => sum + value, 0) || 1;

    return (
        <div className={mcClasses("mc-report-donut-list")} role="img" aria-label={chart.title}>
            {chart.labels.map((label, index) => {
                const value = values[index] ?? 0;
                const share = Math.round((value / total) * 100);
                return (
                    <div className={mcClasses("mc-report-donut-list__row")} key={`${label}-${index}`}>
                        <span className={mcClasses("mc-report-donut-list__swatch")} style={{background: colorFor(label, index)}} />
                        <span className={mcClasses("mc-report-donut-list__label")}>{label}</span>
                        <span className={mcClasses("mc-report-donut-list__bar")}><span style={{width: `${Math.max(2, share)}%`}} /></span>
                        <strong>{share}%</strong>
                    </div>
                );
            })}
        </div>
    );
};

const Legend = ({chart}: {chart: Chart}) => (
    <div className={mcClasses("mc-chart-legend")}>
        {chart.series.map((series, index) => (
            <span className={mcClasses("mc-chart-legend__item")} key={series.key}>
                <span className={mcClasses("mc-chart-legend__swatch")} style={{background: colorFor(series.key, index)}} />
                {series.label}
            </span>
        ))}
    </div>
);

const ReportChart = ({chart, empty}: {chart: Chart; empty: string}) => {
    if (chart.empty || chart.labels.length === 0) {
        return <div className={mcClasses("mc-chart-empty")}>{empty}</div>;
    }
    if (chart.type === "hbar") {
        return <HorizontalBarChart chart={chart} />;
    }
    if (chart.type === "donut") {
        return <DonutLegendChart chart={chart} />;
    }

    return (
        <>
            <CartesianChart chart={chart} />
            {chart.series.length > 1 && <Legend chart={chart} />}
        </>
    );
};

export default function ReportsAdmin({
    methodName,
    initialType,
    initialPeriod,
    initialDateRange,
    initialFrom,
    initialTo,
    initialProductSearch,
    initialCourseSearch,
    initialTableSearch,
    exportBase,
    sesskey,
    perPageOptions,
    reportTypes,
    periodOptions,
    dateRangeOptions,
    labels,
}: ReportsAdminProps) {
    useModernCommerceClassSync();
    const resolvedPerPageOptions = useMemo(() => normalisePerPageOptions(perPageOptions), [perPageOptions]);
    const [type, setType] = useState(initialType);
    const [period, setPeriod] = useState(initialPeriod);
    const [dateRange, setDateRange] = useState(initialDateRange ?? "custom");
    const [fromInput, setFromInput] = useState(tsToDateInput(initialFrom));
    const [toInput, setToInput] = useState(tsToDateInput(initialTo));
    const [productSearchInput, setProductSearchInput] = useState(initialProductSearch ?? "");
    const [courseSearchInput, setCourseSearchInput] = useState(initialCourseSearch ?? "");
    const [tableSearchInput, setTableSearchInput] = useState(initialTableSearch ?? "");
    const [productSearch, setProductSearch] = useState((initialProductSearch ?? "").trim());
    const [courseSearch, setCourseSearch] = useState((initialCourseSearch ?? "").trim());
    const [tableSearch, setTableSearch] = useState((initialTableSearch ?? "").trim());
    const [selectedColumns, setSelectedColumns] = useState<string[]>(() => readStoredColumns(initialType));
    const [draftColumns, setDraftColumns] = useState<string[]>([]);
    const [columnsOpen, setColumnsOpen] = useState(false);
    const [hiddenLayout, setHiddenLayout] = useState<ReportVisibility>(() => readStoredVisibility(initialType));
    const [draftLayout, setDraftLayout] = useState<ReportVisibility>(() => emptyReportVisibility());
    const [layoutOpen, setLayoutOpen] = useState(false);
    const [tablePage, setTablePage] = useState(0);
    const [tablePerPage, setTablePerPage] = useState(() => {
        const options = normalisePerPageOptions(perPageOptions);
        return options.includes(10) ? 10 : options[0] ?? 10;
    });
    const [data, setData] = useState<ReportResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    const fromTs = dateInputToTs(fromInput);
    const toTs = dateInputToTs(toInput, true);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setProductSearch(productSearchInput.trim());
            setCourseSearch(courseSearchInput.trim());
            setTablePage(0);
        }, 350);

        return () => window.clearTimeout(timer);
    }, [productSearchInput, courseSearchInput]);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setTableSearch(tableSearchInput.trim());
            setTablePage(0);
        }, 350);

        return () => window.clearTimeout(timer);
    }, [tableSearchInput]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<ReportResponse>(methodName, {
            type,
            period,
            from: fromTs,
            to: toTs,
            page: tablePage,
            perpage: tablePerPage,
            productsearch: productSearch,
            coursesearch: courseSearch,
            tablesearch: tableSearch,
            columns: selectedColumns,
        })
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                    if (selectedColumns.length === 0) {
                        setSelectedColumns(result.selectedcolumns);
                    }
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
    }, [
        methodName,
        type,
        period,
        fromTs,
        toTs,
        tablePage,
        tablePerPage,
        productSearch,
        courseSearch,
        tableSearch,
        selectedColumns,
    ]);

    useEffect(() => {
        if (!data || tablePage <= 0 || data.tablerows.length > 0 || data.tabletotal <= 0) {
            return;
        }
        setTablePage(Math.max(0, Math.ceil(data.tabletotal / tablePerPage) - 1));
    }, [data, tablePage, tablePerPage]);

    const availableColumns = data?.availablecolumns ?? [];
    const resolvedColumns = useMemo(
        () => normaliseColumns(availableColumns, data?.selectedcolumns ?? selectedColumns),
        [availableColumns, data?.selectedcolumns, selectedColumns],
    );
    const columnByKey = useMemo(() => new Map(availableColumns.map((column) => [column.key, column])), [availableColumns]);
    const visibleColumns = resolvedColumns
        .map((key) => columnByKey.get(key))
        .filter((column): column is ColumnDef => !!column);
    const visibleMetrics = applyItemLayout(
        data?.metrics ?? [],
        hiddenLayout.metrics,
        hiddenLayout.metricSizes,
        hiddenLayout.metricOrder,
        (metric) => metric.key,
    );
    const visibleCharts = applyItemLayout(
        data?.charts ?? [],
        hiddenLayout.charts,
        hiddenLayout.chartSizes,
        hiddenLayout.chartOrder,
        (chart) => chart.id,
    );
    const draftMetricRows = data
        ? applyItemLayout(data.metrics, [], draftLayout.metricSizes, draftLayout.metricOrder, (metric) => metric.key)
        : [];
    const draftChartRows = data
        ? applyItemLayout(data.charts, [], draftLayout.chartSizes, draftLayout.chartOrder, (chart) => chart.id)
        : [];
    const sizeOptions = [
        {value: 12, label: labels.sizefull},
        {value: 6, label: labels.sizehalf},
        {value: 4, label: labels.sizethird},
        {value: 3, label: labels.sizequarter},
    ];

    const exportParams = new URLSearchParams({
        type,
        period,
        daterange: dateRange,
        from: fromInput,
        to: toInput,
        export: "csv",
        sesskey,
    });
    if (productSearch !== "") {
        exportParams.set("productsearch", productSearch);
    }
    if (courseSearch !== "") {
        exportParams.set("coursesearch", courseSearch);
    }
    if (tableSearch !== "") {
        exportParams.set("tablesearch", tableSearch);
    }
    resolvedColumns.forEach((column) => exportParams.append("columns[]", column));
    const exportUrl = `${exportBase}?${exportParams.toString()}`;

    const openColumns = () => {
        setDraftColumns(resolvedColumns);
        setLayoutOpen(false);
        setColumnsOpen(true);
    };

    const openLayout = () => {
        setDraftLayout(visibilityWithDefaults(hiddenLayout, data?.metrics ?? [], data?.charts ?? []));
        setColumnsOpen(false);
        setLayoutOpen(true);
    };

    const toggleDraftColumn = (key: string, checked: boolean) => {
        setDraftColumns((current) => {
            if (checked) {
                return current.includes(key) ? current : [...current, key];
            }
            const next = current.filter((column) => column !== key);
            return next.length > 0 ? next : current;
        });
    };

    const applyColumns = () => {
        const clean = normaliseColumns(availableColumns, draftColumns);
        setSelectedColumns(clean);
        saveStoredColumns(type, clean);
        setColumnsOpen(false);
    };

    const resetColumns = () => {
        const defaults = defaultColumns(availableColumns);
        setDraftColumns(defaults);
    };

    const toggleDraftVisibility = (section: ReportLayoutSection, key: string, checked: boolean) => {
        setDraftLayout((current) => {
            const currentHidden = current[section];
            const nextHidden = checked
                ? currentHidden.filter((hiddenKey) => hiddenKey !== key)
                : (currentHidden.includes(key) ? currentHidden : [...currentHidden, key]);
            return {...current, [section]: nextHidden};
        });
    };

    const updateDraftSize = (section: ReportLayoutSection, key: string, size: number) => {
        const sizeKey = section === "metrics" ? "metricSizes" : "chartSizes";
        setDraftLayout((current) => ({
            ...current,
            [sizeKey]: {
                ...current[sizeKey],
                [key]: clampGridSize(size, 3),
            },
        }));
    };

    const moveDraftItem = (section: ReportLayoutSection, index: number, delta: number) => {
        const orderKey = section === "metrics" ? "metricOrder" : "chartOrder";
        setDraftLayout((current) => {
            const nextOrder = [...current[orderKey]];
            const target = index + delta;
            if (target < 0 || target >= nextOrder.length) {
                return current;
            }
            [nextOrder[index], nextOrder[target]] = [nextOrder[target], nextOrder[index]];
            return {...current, [orderKey]: nextOrder};
        });
    };

    const applyLayout = () => {
        const next = visibilityWithDefaults(draftLayout, data?.metrics ?? [], data?.charts ?? []);
        setHiddenLayout(next);
        saveStoredVisibility(type, next);
        setLayoutOpen(false);
    };

    const resetLayout = () => {
        setDraftLayout(visibilityWithDefaults(emptyReportVisibility(), data?.metrics ?? [], data?.charts ?? []));
    };

    const changeReportType = (value: string) => {
        setType(value);
        setSelectedColumns(readStoredColumns(value));
        setHiddenLayout(readStoredVisibility(value));
        setTablePage(0);
        setColumnsOpen(false);
        setLayoutOpen(false);
    };

    const tableRows = data?.tablerows ?? [];
    const tableTotal = data?.tabletotal ?? 0;
    const tablePageCount = Math.max(1, Math.ceil(tableTotal / tablePerPage));
    const currentTablePage = Math.min(tablePage, tablePageCount - 1);
    const visibleFrom = tableTotal === 0 || tableRows.length === 0 ? 0 : currentTablePage * tablePerPage + 1;
    const visibleTo = tableTotal === 0 || tableRows.length === 0 ? 0 : currentTablePage * tablePerPage + tableRows.length;

    const updateReportDate = (setter: (value: string) => void, value: string) => {
        setDateRange("custom");
        setter(value);
        setTablePage(0);
    };

    const updateDateRange = (value: string) => {
        setDateRange(value);
        const resolved = resolveDateRange(value);
        if (resolved) {
            setFromInput(resolved.from);
            setToInput(resolved.to);
        }
        setTablePage(0);
    };

    const updateTablePerPage = (value: number) => {
        setTablePerPage(value);
        setTablePage(0);
    };

    const reportFilters = (
        <div className={mcClasses("mc-report-filter-section")}>
            <div className={mcClasses("mc-table-design-summary mc-report-controls-summary")}>
                <h3 className={mcClasses("mc-card-title mb-1")}>{labels.reportfilters}</h3>
                <div className={mcClasses("mc-cell-muted")}>{labels.reportfiltersdesc}</div>
            </div>
            <div className={mcClasses("mc-table-design-controls__filters mc-report-controls-row")}>
                <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar mc-report-toolbar")}>
                    <label className={mcClasses("mc-product-toolbar__field")}>
                        <span className={mcClasses("mc-filter-label")}>{labels.reporttype}</span>
                        <select
                            className={mcClasses("mc-select")}
                            onChange={(event) => changeReportType(event.target.value)}
                            value={type}
                        >
                            {reportTypes.map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </select>
                    </label>
                    {type === "sales" && (
                        <label className={mcClasses("mc-product-toolbar__field")}>
                            <span className={mcClasses("mc-filter-label")}>{labels.period}</span>
                            <select
                                className={mcClasses("mc-select")}
                                onChange={(event) => {
                                    setPeriod(event.target.value);
                                    setTablePage(0);
                                }}
                                value={period}
                            >
                                {periodOptions.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                        </label>
                    )}
                    <label className={mcClasses("mc-product-toolbar__field")}>
                        <span className={mcClasses("mc-filter-label")}>{labels.productsearch}</span>
                        <input
                            className={mcClasses("mc-form-control")}
                            onChange={(event) => setProductSearchInput(event.target.value)}
                            placeholder={labels.productsearchplaceholder}
                            type="search"
                            value={productSearchInput}
                        />
                    </label>
                    <label className={mcClasses("mc-product-toolbar__field")}>
                        <span className={mcClasses("mc-filter-label")}>{labels.coursesearch}</span>
                        <input
                            className={mcClasses("mc-form-control")}
                            onChange={(event) => setCourseSearchInput(event.target.value)}
                            placeholder={labels.coursesearchplaceholder}
                            type="search"
                            value={courseSearchInput}
                        />
                    </label>
                </div>
            </div>
        </div>
    );
    const customizeButton = (
        <button
            className={mcClasses("mc-button mc-btn-soft")}
            disabled={!data}
            onClick={openLayout}
            type="button"
        >
            <i className="bi bi-sliders" aria-hidden="true" />
            {labels.customize}
        </button>
    );

    return (
        <section className={mcClasses("mc-product-admin mc-report-admin")} aria-label={labels.title}>
            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}

            <MetricSection
                metrics={visibleMetrics}
                charts={visibleCharts}
                title={labels.metrictiles}
                chartTitle={labels.charts}
                empty={labels.nodata}
                filters={reportFilters}
                actions={customizeButton}
            />

            {loading && !data && (
                <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
            )}

            {data && (
                <>
                    <McTableCard
                        className={mcClasses("mc-report-table-card")}
                        title={(
                            <div className={mcClasses("mc-report-table-summary")}>
                                <h2 className={mcClasses("mc-card-title mb-1")}>{labels.reporttable}</h2>
                                <div className={mcClasses("mc-cell-muted")}>
                                    {formatCount(visibleColumns.length)} {labels.selectedcolumns}
                                </div>
                            </div>
                        )}
                        toolbar={(
                            <div className={mcClasses("mc-report-table-controls-row")}>
                                <div className={mcClasses("mc-toolbar mc-product-toolbar mc-table-design-toolbar mc-report-table-toolbar")}>
                                    <div className={mcClasses("mc-product-toolbar__search mc-report-table-search")}>
                                        <label className={mcClasses("mc-filter-label")} htmlFor="mc-report-table-search">
                                            {labels.tablesearch}
                                        </label>
                                        <input
                                            className={mcClasses("mc-form-control")}
                                            id="mc-report-table-search"
                                            onChange={(event) => setTableSearchInput(event.target.value)}
                                            placeholder={labels.tablesearchplaceholder}
                                            type="search"
                                            value={tableSearchInput}
                                        />
                                    </div>
                                    <div className={mcClasses("mc-report-table-date-filters")}>
                                        <label className={mcClasses("mc-product-toolbar__field")}>
                                            <span className={mcClasses("mc-filter-label")}>{labels.daterange}</span>
                                            <select
                                                className={mcClasses("mc-select")}
                                                onChange={(event) => updateDateRange(event.target.value)}
                                                value={dateRange}
                                            >
                                                {dateRangeOptions.map((option) => (
                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                ))}
                                            </select>
                                        </label>
                                        <label className={mcClasses("mc-product-toolbar__field")}>
                                            <span className={mcClasses("mc-filter-label")}>{labels.datefrom}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => updateReportDate(setFromInput, event.target.value)}
                                                type="date"
                                                value={fromInput}
                                            />
                                        </label>
                                        <label className={mcClasses("mc-product-toolbar__field")}>
                                            <span className={mcClasses("mc-filter-label")}>{labels.dateto}</span>
                                            <input
                                                className={mcClasses("mc-form-control")}
                                                onChange={(event) => updateReportDate(setToInput, event.target.value)}
                                                type="date"
                                                value={toInput}
                                            />
                                        </label>
                                    </div>
                                    <div className={mcClasses("mc-report-table-toolbar__actions")}>
                                        <button
                                            className={mcClasses("mc-button mc-btn-soft")}
                                            disabled={availableColumns.length === 0}
                                            onClick={openColumns}
                                            type="button"
                                        >
                                            <i className="bi bi-layout-three-columns" aria-hidden="true" />
                                            {labels.showcolumns}
                                        </button>
                                        <label className={mcClasses("mc-table-design-page-size")}>
                                            <span className={mcClasses("mc-filter-label")}>{labels.perpage}</span>
                                            <select
                                                className={mcClasses("mc-select")}
                                                onChange={(event) => updateTablePerPage(Number(event.target.value) || 10)}
                                                value={tablePerPage}
                                            >
                                                {resolvedPerPageOptions.map((option) => (
                                                    <option key={option} value={option}>{option}</option>
                                                ))}
                                            </select>
                                        </label>
                                        <a className={mcClasses("mc-button")} data-mc-button="primary" href={exportUrl}>
                                            <i className="bi bi-download" aria-hidden="true" />
                                            {labels.exportcsv}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        )}
                        footer={(
                            <McTableFooter
                                summary={(
                                    <>
                                        <span>
                                            {labels.showing} {formatCount(visibleFrom)}-{formatCount(visibleTo)} / {formatCount(tableTotal)}
                                        </span>
                                        {data.tabletruncated && <span>{labels.reporttabletruncated}</span>}
                                    </>
                                )}
                                pagination={(
                                    <McTablePagination
                                        previousLabel={labels.previous}
                                        nextLabel={labels.next}
                                        pageLabel={labels.page}
                                        page={Math.min(tablePage + 1, tablePageCount)}
                                        totalPages={tablePageCount}
                                        previousDisabled={loading || tablePage <= 0}
                                        nextDisabled={loading || tablePage + 1 >= tablePageCount}
                                        onPrevious={() => setTablePage(Math.max(0, tablePage - 1))}
                                        onNext={() => setTablePage(tablePage + 1)}
                                    />
                                )}
                            />
                        )}
                    >
                                <table className={mcClasses("table mc-table mc-product-table mb-0")} aria-label={labels.reporttable}>
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
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {!loading && tableRows.length === 0 && (
                                            <tr>
                                                <td colSpan={Math.max(visibleColumns.length, 1)}>
                                                    <div className={mcClasses("mc-empty mc-empty--centered")}>
                                                        <span className={mcClasses("mc-empty__icon")}>
                                                            <i className="bi bi-table" aria-hidden="true" />
                                                        </span>
                                                        <p className={mcClasses("mc-empty__title")}>{labels.nodata}</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                        {tableRows.map((row, rowIndex) => (
                                            <tr key={rowIndex}>
                                                {row.cells.map((cell) => {
                                                    const column = columnByKey.get(cell.key);
                                                    const align = column?.align === "right" ? "text-end" : "";
                                                    const content = cell.badge ? (
                                                        <McBadge variant={badgeVariant(cell.badgeclass)} tone="soft" dot>
                                                            {cell.value}
                                                        </McBadge>
                                                    ) : cell.href ? (
                                                        <a className="fw-semibold" href={cell.href}>{cell.value}</a>
                                                    ) : cell.value;

                                                    return (
                                                        <td className={mcClasses(align)} key={cell.key}>
                                                            {content}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                        {loading && data && (
                                            <tr>
                                                <td colSpan={Math.max(visibleColumns.length, 1)}>
                                                    <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                    </McTableCard>
                </>
            )}

            {layoutOpen && data && (
                <McDrawer
                    title={labels.customize}
                    subtitle={labels.customizedesc}
                    onClose={() => setLayoutOpen(false)}
                    closeLabel={labels.close}
                    footer={(
                        <>
                            <McButton variant="primary" onClick={applyLayout} type="button">
                                {labels.save}
                            </McButton>
                            <button className={mcClasses("mc-button mc-btn-soft")} onClick={resetLayout} type="button">
                                {labels.reset}
                            </button>
                            <button className={mcClasses("mc-button")} onClick={() => setLayoutOpen(false)} type="button">
                                {labels.cancel}
                            </button>
                        </>
                    )}
                >
                            <div className={mcClasses("mc-report-layout-section")}>
                                <h3 className={mcClasses("mc-report-layout-section__title")}>{labels.metrictiles}</h3>
                                {draftMetricRows.map((metric, index) => (
                                    <div
                                        className={mcClasses(
                                            "mc-layout-row mc-report-layout-row",
                                            draftLayout.metrics.includes(metric.key) && "mc-layout-row--off",
                                        )}
                                        key={metric.key}
                                    >
                                        <div className={mcClasses("mc-layout-row__reorder")}>
                                            <button
                                                className={mcClasses("mc-button mc-btn-soft")}
                                                data-mc-button="soft"
                                                data-mc-button-size="icon"
                                                disabled={index === 0}
                                                onClick={() => moveDraftItem("metrics", index, -1)}
                                                type="button"
                                                aria-label={labels.moveup}
                                            >
                                                <i className="bi bi-chevron-up" aria-hidden="true" />
                                            </button>
                                            <button
                                                className={mcClasses("mc-button mc-btn-soft")}
                                                data-mc-button="soft"
                                                data-mc-button-size="icon"
                                                disabled={index === draftMetricRows.length - 1}
                                                onClick={() => moveDraftItem("metrics", index, 1)}
                                                type="button"
                                                aria-label={labels.movedown}
                                            >
                                                <i className="bi bi-chevron-down" aria-hidden="true" />
                                            </button>
                                        </div>
                                        <label className={mcClasses("mc-layout-row__show")} title={labels.show}>
                                            <input
                                                checked={!draftLayout.metrics.includes(metric.key)}
                                                onChange={(event) => toggleDraftVisibility("metrics", metric.key, event.target.checked)}
                                                type="checkbox"
                                                aria-label={labels.show}
                                            />
                                        </label>
                                        <i className={`bi ${metric.icon} mc-report-layout-row__icon`} aria-hidden="true" />
                                        <span className={mcClasses("mc-layout-row__title")}>{metric.label}</span>
                                        <select
                                            className={mcClasses("mc-select mc-layout-row__size")}
                                            disabled={draftLayout.metrics.includes(metric.key)}
                                            onChange={(event) => updateDraftSize("metrics", metric.key, Number(event.target.value))}
                                            value={draftLayout.metricSizes[metric.key] ?? metric.size}
                                            aria-label={labels.size}
                                        >
                                            {sizeOptions.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                ))}
                            </div>
                            <div className={mcClasses("mc-report-layout-section")}>
                                <h3 className={mcClasses("mc-report-layout-section__title")}>{labels.chartvisibility}</h3>
                                {draftChartRows.map((chart, index) => (
                                    <div
                                        className={mcClasses(
                                            "mc-layout-row mc-report-layout-row",
                                            draftLayout.charts.includes(chart.id) && "mc-layout-row--off",
                                        )}
                                        key={chart.id}
                                    >
                                        <div className={mcClasses("mc-layout-row__reorder")}>
                                            <button
                                                className={mcClasses("mc-button mc-btn-soft")}
                                                data-mc-button="soft"
                                                data-mc-button-size="icon"
                                                disabled={index === 0}
                                                onClick={() => moveDraftItem("charts", index, -1)}
                                                type="button"
                                                aria-label={labels.moveup}
                                            >
                                                <i className="bi bi-chevron-up" aria-hidden="true" />
                                            </button>
                                            <button
                                                className={mcClasses("mc-button mc-btn-soft")}
                                                data-mc-button="soft"
                                                data-mc-button-size="icon"
                                                disabled={index === draftChartRows.length - 1}
                                                onClick={() => moveDraftItem("charts", index, 1)}
                                                type="button"
                                                aria-label={labels.movedown}
                                            >
                                                <i className="bi bi-chevron-down" aria-hidden="true" />
                                            </button>
                                        </div>
                                        <label className={mcClasses("mc-layout-row__show")} title={labels.show}>
                                            <input
                                                checked={!draftLayout.charts.includes(chart.id)}
                                                onChange={(event) => toggleDraftVisibility("charts", chart.id, event.target.checked)}
                                                type="checkbox"
                                                aria-label={labels.show}
                                            />
                                        </label>
                                        <i className="bi bi-bar-chart mc-report-layout-row__icon" aria-hidden="true" />
                                        <span className={mcClasses("mc-layout-row__title")}>{chart.title}</span>
                                        <select
                                            className={mcClasses("mc-select mc-layout-row__size")}
                                            disabled={draftLayout.charts.includes(chart.id)}
                                            onChange={(event) => updateDraftSize("charts", chart.id, Number(event.target.value))}
                                            value={draftLayout.chartSizes[chart.id] ?? chart.size}
                                            aria-label={labels.size}
                                        >
                                            {sizeOptions.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                ))}
                            </div>
                </McDrawer>
            )}

            {columnsOpen && (
                <McDrawer
                    title={labels.columns}
                    subtitle={labels.columnsdesc}
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
                                {availableColumns.map((column) => (
                                    <label className={mcClasses("mc-report-column-list__item")} key={column.key}>
                                        <input
                                            checked={draftColumns.includes(column.key)}
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

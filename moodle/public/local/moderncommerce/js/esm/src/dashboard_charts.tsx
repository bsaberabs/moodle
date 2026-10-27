// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin dashboard analytics charts for Modern Commerce.
 *
 * @module     local_moderncommerce/dashboard_charts
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {McBadge} from "./badge";
import type {McBadgeVariant} from "./badge";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McDrawer} from "./drawer";
import {McTableFrame} from "./table_components";

declare const M: { cfg: { sesskey: string; wwwroot: string } };

type Labels = Record<string, string>;
type Currency = { code: string; symbol: string; position: string; decimals: number };
type Series = { key: string; label: string; charttype: string; axis: string; data: number[] };
type Matrix = { rows: string[]; cols: string[]; values: number[][] };
type TableCell = { value: string; badge?: boolean; badgeclass?: string; href?: string };
type TableData = { columns: { label: string; align: string }[]; rows: { cells: TableCell[] }[] };
type Chart = {
    id: string; type: string; title: string; subtitle: string;
    formattype: string; total: string; empty: boolean; labels: string[]; series: Series[];
    stacked?: boolean; matrix?: Matrix; links?: string[]; size?: number; table?: TableData;
};
type ChartsResponse = { currency: Currency; range: string; granularity: string; charts: Chart[] };
type Props = {
    methodName: string; defaultRange: string; labels: Labels;
    canManage?: boolean; canSaveDefault?: boolean; layoutGetMethod?: string; layoutSaveMethod?: string;
};
type LayoutItem = { id: string; title: string; enabled: boolean; size: number; order: number };
type PanelItem = { id: string; title: string; enabled: boolean; size: number; order: number };
type SizeOption = { value: number; label: string };
type LayoutResponse = {
    charts: LayoutItem[]; panels: PanelItem[]; sizeoptions: SizeOption[];
    range: string; ranges: string[]; cansavedefault: boolean;
};

const BADGE_VARIANTS: McBadgeVariant[] = ["primary", "secondary", "accent", "success", "warning", "danger", "info", "neutral"];
const badgeVariant = (variant?: string): McBadgeVariant => (
    BADGE_VARIANTS.includes(variant as McBadgeVariant) ? variant as McBadgeVariant : "neutral"
);

// Other dashboard React roots (the KPI strip) re-fetch when this fires after a save.
const PREFS_SAVED_EVENT = "mc:dashboard-prefs-saved";
const DASHBOARD_REFRESH_BUTTON_ID = "moderncommerce-dashboard-refresh";

const RANGES = ["7d", "30d", "90d", "12m", "ytd"];
const SPANS = [12, 6, 4, 3];
const spanClass = (size?: number): string => `mc-chart-card--span${SPANS.includes(size as number) ? size : 6}`;
const COLORS: Record<string, string> = {
    net: "#2563eb", gross: "#93c5fd", orders: "#2563eb", paid: "#16a34a",
    rate: "#f59e0b", successful: "#16a34a", failed: "#dc2626", revenue: "#2563eb",
};
const PALETTE = ["#2563eb", "#16a34a", "#f59e0b", "#7c3aed", "#0891b2", "#db2777", "#65a30d", "#dc2626"];

const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php`
        + `?sesskey=${encodeURIComponent(M.cfg.sesskey)}&info=${encodeURIComponent(methodName)}`;
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

const colorFor = (key: string, i: number): string => COLORS[key] ?? PALETTE[i % PALETTE.length];

const abbrNum = (v: number): string => {
    const a = Math.abs(v);
    if (a >= 1e6) {
        return (v / 1e6).toFixed(1) + "M";
    }
    if (a >= 1e3) {
        return (v / 1e3).toFixed(1) + "k";
    }
    return String(Math.round(v));
};

const fmt = (v: number, type: string, cur: Currency): string => {
    if (type === "percent") {
        return Math.round(v) + "%";
    }
    if (type === "currency") {
        const n = new Intl.NumberFormat(document.documentElement.lang || undefined, {
            minimumFractionDigits: cur.decimals, maximumFractionDigits: cur.decimals,
        }).format(v);
        return cur.position === "after" ? `${n}${cur.symbol}` : `${cur.symbol}${n}`;
    }
    return new Intl.NumberFormat(document.documentElement.lang || undefined).format(Math.round(v));
};

const axisLabel = (v: number, type: string, cur: Currency): string => {
    if (type === "percent") {
        return Math.round(v) + "%";
    }
    if (type === "currency") {
        const s = cur.position === "after" ? "" : cur.symbol;
        return s + abbrNum(v);
    }
    return abbrNum(v);
};

const niceMax = (v: number): number => {
    if (v <= 0) {
        return 1;
    }
    const pow = Math.pow(10, Math.floor(Math.log10(v)));
    return Math.ceil(v / pow) * pow;
};

const Legend = ({items}: {items: {label: string; color: string}[]}) => (
    <div className={mcClasses("mc-chart-legend")}>
        {items.map((it) => (
            <span className={mcClasses("mc-chart-legend__item")} key={it.label}>
                <span className={mcClasses("mc-chart-legend__swatch")} style={{background: it.color}} />
                {it.label}
            </span>
        ))}
    </div>
);

/** Cartesian renderer: handles line, combo (bars + right-axis line), and grouped bar. */
const Cartesian = ({chart, cur}: {chart: Chart; cur: Currency}) => {
    // Full-row charts use a wider viewBox so they stay a sensible height on wide screens.
    const W = (chart.size ?? 6) >= 12 ? 1040 : 520;
    const H = 240; const PL = 46; const PR = chart.series.some((s) => s.axis === "right") ? 40 : 14;
    const PT = 14; const PB = 30;
    const plotW = W - PL - PR; const plotH = H - PT - PB;
    const n = chart.labels.length || 1;
    const band = plotW / n;

    const left = chart.series.filter((s) => s.axis !== "right");
    const right = chart.series.filter((s) => s.axis === "right");
    const bars = left.filter((s) => s.charttype === "bar");
    const lines = left.filter((s) => s.charttype === "line");

    const stacked = !!chart.stacked && bars.length > 1;
    const leftMaxRaw = stacked
        ? Math.max(1, ...chart.labels.map((_, i) => bars.reduce((a, s) => a + (s.data[i] || 0), 0)))
        : Math.max(1, ...left.flatMap((s) => s.data));
    const leftMax = niceMax(leftMaxRaw);
    const rightMaxRaw = right.length ? Math.max(1, ...right.flatMap((s) => s.data)) : 0;
    const rightMax = right.length ? (rightMaxRaw <= 100 ? 100 : niceMax(rightMaxRaw)) : 1;

    const yL = (v: number) => PT + plotH - (v / leftMax) * plotH;
    const yR = (v: number) => PT + plotH - (v / rightMax) * plotH;
    const cx = (i: number) => PL + band * i + band / 2;

    const ticks = [0, 0.25, 0.5, 0.75, 1];
    const gw = band * 0.6;
    const bw = bars.length ? gw / bars.length : gw;
    const everyX = n > 10 ? Math.ceil(n / 8) : 1;

    return (
        <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label={chart.title}>
            {ticks.map((t) => {
                const y = PT + plotH - t * plotH;
                return (
                    <g key={t}>
                        <line x1={PL} y1={y} x2={PL + plotW} y2={y} stroke="#eef2f7" strokeWidth={1} />
                        <text x={PL - 6} y={y + 3} textAnchor="end" fontSize={9} fill="#94a3b8">
                            {axisLabel(leftMax * t, chart.formattype, cur)}
                        </text>
                    </g>
                );
            })}
            {right.length > 0 && ticks.filter((t) => t === 0 || t === 0.5 || t === 1).map((t) => {
                const y = PT + plotH - t * plotH;
                return (
                    <text key={`r${t}`} x={PL + plotW + 6} y={y + 3} textAnchor="start" fontSize={9} fill="#cbb277">
                        {Math.round(rightMax * t)}{chart.formattype === "number" ? "%" : ""}
                    </text>
                );
            })}

            {bars.map((s, si) => (
                <g key={s.key}>
                    {s.data.map((v, i) => {
                        const h = (v / leftMax) * plotH;
                        let x; let w; let y;
                        if (stacked) {
                            x = PL + band * i + (band - gw) / 2;
                            w = gw;
                            const below = bars.slice(0, si).reduce((a, b) => a + (b.data[i] || 0), 0);
                            y = PT + plotH - ((below + v) / leftMax) * plotH;
                        } else {
                            x = PL + band * i + (band - gw) / 2 + si * bw;
                            w = Math.max(1, bw - 2);
                            y = PT + plotH - h;
                        }
                        return (
                            <rect key={i} x={x} y={y} width={w} height={Math.max(0, h)} fill={colorFor(s.key, si)} rx={1}>
                                <title>{`${chart.labels[i]} · ${s.label}: ${fmt(v, chart.formattype === "currency" ? "currency" : "number", cur)}`}</title>
                            </rect>
                        );
                    })}
                </g>
            ))}

            {lines.map((s, si) => {
                const pts = s.data.map((v, i) => `${cx(i)},${yL(v)}`).join(" ");
                const color = colorFor(s.key, si);
                return (
                    <g key={s.key}>
                        <polyline points={pts} fill="none" stroke={color} strokeWidth={2}
                            strokeLinejoin="round" strokeLinecap="round" />
                        {s.data.map((v, i) => (
                            <circle key={i} cx={cx(i)} cy={yL(v)} r={2.5} fill={color}>
                                <title>{`${chart.labels[i]} · ${s.label}: ${fmt(v, chart.formattype, cur)}`}</title>
                            </circle>
                        ))}
                    </g>
                );
            })}

            {right.map((s, si) => {
                const pts = s.data.map((v, i) => `${cx(i)},${yR(v)}`).join(" ");
                const color = colorFor(s.key, si);
                return (
                    <g key={s.key}>
                        <polyline points={pts} fill="none" stroke={color} strokeWidth={2}
                            strokeDasharray="4 3" strokeLinejoin="round" strokeLinecap="round" />
                        {s.data.map((v, i) => (
                            <circle key={i} cx={cx(i)} cy={yR(v)} r={2.5} fill={color}>
                                <title>{`${chart.labels[i]} · ${s.label}: ${Math.round(v)}%`}</title>
                            </circle>
                        ))}
                    </g>
                );
            })}

            <line x1={PL} y1={PT + plotH} x2={PL + plotW} y2={PT + plotH} stroke="#cbd5e1" strokeWidth={1} />
            {chart.labels.map((lab, i) => (i % everyX === 0 ? (
                <text key={i} x={cx(i)} y={H - 12} textAnchor="middle" fontSize={9} fill="#64748b">{lab}</text>
            ) : null))}
        </svg>
    );
};

const HBar = ({chart, cur}: {chart: Chart; cur: Currency}) => {
    const data = chart.series[0]?.data ?? [];
    const W = 520; const rowH = 26; const gap = 8; const labelW = 150; const PR = 56;
    const H = Math.max(60, data.length * (rowH + gap) + 8);
    const max = niceMax(Math.max(1, ...data));
    const barW = W - labelW - PR;
    return (
        <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label={chart.title}>
            {chart.labels.map((lab, i) => {
                const y = i * (rowH + gap) + 4;
                const w = (data[i] / max) * barW;
                const short = lab.length > 22 ? lab.slice(0, 21) + "…" : lab;
                return (
                    <g key={i}>
                        <text x={0} y={y + rowH / 2 + 3} fontSize={11} fill="#0f172a">{short}
                            <title>{lab}</title>
                        </text>
                        <rect x={labelW} y={y} width={Math.max(1, w)} height={rowH} fill={colorFor("revenue", 0)} rx={3}>
                            <title>{`${lab}: ${fmt(data[i], chart.formattype, cur)}`}</title>
                        </rect>
                        <text x={labelW + w + 6} y={y + rowH / 2 + 3} fontSize={10} fill="#475569">
                            {fmt(data[i], chart.formattype, cur)}
                        </text>
                    </g>
                );
            })}
        </svg>
    );
};

const Donut = ({chart, cur}: {chart: Chart; cur: Currency}) => {
    const data = chart.series[0]?.data ?? [];
    const total = data.reduce((a, b) => a + b, 0) || 1;
    const cxp = 110; const cyp = 110; const r = 98; const ir = 60;
    let acc = 0;
    const arc = (start: number, end: number) => {
        const a0 = (start / total) * 2 * Math.PI - Math.PI / 2;
        const a1 = (end / total) * 2 * Math.PI - Math.PI / 2;
        const large = end - start > total / 2 ? 1 : 0;
        const x0 = cxp + r * Math.cos(a0); const y0 = cyp + r * Math.sin(a0);
        const x1 = cxp + r * Math.cos(a1); const y1 = cyp + r * Math.sin(a1);
        const xi1 = cxp + ir * Math.cos(a1); const yi1 = cyp + ir * Math.sin(a1);
        const xi0 = cxp + ir * Math.cos(a0); const yi0 = cyp + ir * Math.sin(a0);
        return `M ${x0} ${y0} A ${r} ${r} 0 ${large} 1 ${x1} ${y1} L ${xi1} ${yi1} A ${ir} ${ir} 0 ${large} 0 ${xi0} ${yi0} Z`;
    };
    return (
        <div style={{alignItems: "center", display: "flex", flexDirection: "column", gap: ".85rem"}}>
            <svg viewBox="0 0 220 220" role="img" aria-label={chart.title}
                style={{display: "block", height: "auto", margin: "0 auto", maxWidth: 340, width: "80%"}}>
                {data.map((v, i) => {
                    const start = acc; acc += v;
                    return (
                        <path key={i} d={arc(start, acc)} fill={PALETTE[i % PALETTE.length]}>
                            <title>{`${chart.labels[i]}: ${fmt(v, chart.formattype, cur)} (${Math.round(v / total * 100)}%)`}</title>
                        </path>
                    );
                })}
            </svg>
            <div className={mcClasses("mc-chart-legend")} style={{justifyContent: "center"}}>
                {chart.labels.map((lab, i) => (
                    <span className={mcClasses("mc-chart-legend__item")} key={lab}>
                        <span className={mcClasses("mc-chart-legend__swatch")} style={{background: PALETTE[i % PALETTE.length]}} />
                        {lab} — {fmt(data[i], chart.formattype, cur)} ({Math.round(data[i] / total * 100)}%)
                    </span>
                ))}
            </div>
        </div>
    );
};

const Funnel = ({chart, cur}: {chart: Chart; cur: Currency}) => {
    const data = chart.series[0]?.data ?? [];
    const labels = chart.labels;
    const links = chart.links ?? [];
    const n = data.length;
    const top = data[0] || 1;
    const W = 520; const maxW = W * 0.9; const bandH = 66; const gap = 32; const padTop = 8;
    const H = Math.max(80, n * bandH + Math.max(0, n - 1) * gap + padTop + 8);
    const max = Math.max(1, ...data);
    const widthOf = (v: number) => Math.max(46, (v / max) * maxW);
    // Deep -> light blue gradient across stages.
    const shade = (i: number) => `hsl(217, 80%, ${38 + (n > 1 ? i / (n - 1) : 0) * 20}%)`;
    return (
        <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label={chart.title}>
            {data.map((v, i) => {
                const y = padTop + i * (bandH + gap);
                const topW = widthOf(v);
                const botW = widthOf(i < n - 1 ? data[i + 1] : v);
                const tl = (W - topW) / 2; const tr = (W + topW) / 2;
                const bl = (W - botW) / 2; const br = (W + botW) / 2;
                const pct = Math.round(v / top * 100);
                const drop = i < n - 1 ? Math.round((1 - (data[i + 1] / (v || 1))) * 100) : null;
                const lost = i < n - 1 ? v - data[i + 1] : 0;
                const href = links[i];
                const seg = (
                    <g className={mcClasses("mc-funnel-seg" + (href ? " mc-funnel-seg--link" : ""))}>
                        <polygon points={`${tl},${y} ${tr},${y} ${br},${y + bandH} ${bl},${y + bandH}`} fill={shade(i)}>
                            <title>
                                {`${labels[i]}: ${fmt(v, "number", cur)} (${pct}% of ${labels[0]})${href ? " — click to view" : ""}`}
                            </title>
                        </polygon>
                        <text x={W / 2} y={y + bandH / 2 - 3} textAnchor="middle" fontSize={13} fontWeight={700} fill="#fff">
                            {labels[i]}
                        </text>
                        <text x={W / 2} y={y + bandH / 2 + 15} textAnchor="middle" fontSize={12} fill="#fff">
                            {fmt(v, "number", cur)} &middot; {pct}%
                        </text>
                    </g>
                );
                return (
                    <g key={i}>
                        {href ? <a href={href}>{seg}</a> : seg}
                        {drop !== null && (
                            <text x={W / 2} y={y + bandH + gap / 2 + 4} textAnchor="middle" fontSize={11}
                                fontWeight={600} fill="#94a3b8">
                                &#9660; {drop}% drop &middot; {fmt(lost, "number", cur)} lost
                            </text>
                        )}
                    </g>
                );
            })}
        </svg>
    );
};

const Heatmap = ({chart, cur}: {chart: Chart; cur: Currency}) => {
    const m = chart.matrix;
    if (!m || !m.values.length) {
        return null;
    }
    const labelW = 34; const W = 520; const cellH = 20; const topPad = 4;
    const cellW = (W - labelW) / m.cols.length;
    const H = m.rows.length * cellH + topPad + 16;
    let max = 0;
    m.values.forEach((r) => r.forEach((v) => { if (v > max) { max = v; } }));
    max = max || 1;
    return (
        <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label={chart.title}>
            {m.rows.map((rl, ri) => (
                <g key={ri}>
                    <text x={0} y={topPad + ri * cellH + cellH / 2 + 3} fontSize={9} fill="#64748b">{rl}</text>
                    {m.cols.map((cl, ci) => {
                        const v = m.values[ri][ci] || 0;
                        const op = v > 0 ? 0.12 + 0.88 * (v / max) : 0;
                        return (
                            <rect key={ci} x={labelW + ci * cellW} y={topPad + ri * cellH}
                                width={Math.max(1, cellW - 1)} height={cellH - 1}
                                fill="#2563eb" fillOpacity={op} stroke="#eef2f7" strokeWidth={0.5}>
                                <title>{`${rl} ${cl}:00 — ${fmt(v, chart.formattype, cur)}`}</title>
                            </rect>
                        );
                    })}
                </g>
            ))}
            {m.cols.map((cl, ci) => (ci % 3 === 0 ? (
                <text key={`c${ci}`} x={labelW + ci * cellW + cellW / 2} y={H - 5} textAnchor="middle" fontSize={8} fill="#94a3b8">{cl}</text>
            ) : null))}
        </svg>
    );
};

const Table = ({chart}: {chart: Chart}) => {
    const t = chart.table;
    if (!t || !t.rows.length) {
        return null;
    }
    return (
        <McTableFrame>
            <table className={mcClasses("table mc-table mc-chart-table mb-0")} aria-label={chart.title}>
                <thead>
                    <tr>
                        {t.columns.map((c, i) => (
                            <th key={i} scope="col" className={c.align === "right" ? "text-end" : ""}>{c.label}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {t.rows.map((row, ri) => (
                        <tr key={ri}>
                            {row.cells.map((cell, ci) => {
                                const align = t.columns[ci]?.align === "right" ? "text-end" : "";
                                const content = cell.badge
                                    ? (
                                        <McBadge variant={badgeVariant(cell.badgeclass)} tone="soft" dot>
                                            {cell.value}
                                        </McBadge>
                                    )
                                    : (cell.href ? <a href={cell.href}>{cell.value}</a> : cell.value);
                                return <td key={ci} className={mcClasses(align)}>{content}</td>;
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </McTableFrame>
    );
};

const MCChart = ({chart, cur, empty}: {chart: Chart; cur: Currency; empty: string}) => {
    if (chart.empty || (!["heatmap", "table"].includes(chart.type) && !chart.labels.length)) {
        return <div className={mcClasses("mc-chart-empty")}>{empty}</div>;
    }
    if (chart.type === "table") {
        return <Table chart={chart} />;
    }
    if (chart.type === "hbar") {
        return <HBar chart={chart} cur={cur} />;
    }
    if (chart.type === "donut") {
        return <Donut chart={chart} cur={cur} />;
    }
    if (chart.type === "funnel") {
        return <Funnel chart={chart} cur={cur} />;
    }
    if (chart.type === "heatmap") {
        return <Heatmap chart={chart} cur={cur} />;
    }
    const legend = chart.type !== "line" || chart.series.length > 1
        ? <Legend items={chart.series.map((s, i) => ({label: s.label, color: colorFor(s.key, i)}))} />
        : null;
    return (<>
        <Cartesian chart={chart} cur={cur} />
        {legend}
    </>);
};

export default function DashboardCharts({
    methodName, defaultRange, labels, canManage = false, canSaveDefault = false,
    layoutGetMethod = "", layoutSaveMethod = "",
}: Props) {
    useModernCommerceClassSync();
    const [range, setRange] = useState(RANGES.includes(defaultRange) ? defaultRange : "30d");
    const [data, setData] = useState<ChartsResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [reloadToken, setReloadToken] = useState(0);

    const [layoutOpen, setLayoutOpen] = useState(false);
    const [layout, setLayout] = useState<LayoutItem[] | null>(null);
    const [panels, setPanels] = useState<PanelItem[] | null>(null);
    const [sizeOptions, setSizeOptions] = useState<SizeOption[]>([]);
    const [prefRange, setPrefRange] = useState(RANGES.includes(defaultRange) ? defaultRange : "30d");
    const [scope, setScope] = useState<"personal" | "sitedefault">("personal");
    const [layoutBusy, setLayoutBusy] = useState(false);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");
        void callMoodleService<ChartsResponse>(methodName, {range})
            .then((result) => { if (!cancelled) { setData(result); } return result; })
            .catch((caught: unknown) => {
                if (!cancelled) { setError(caught instanceof Error ? caught.message : String(caught)); }
                return null;
            })
            .finally(() => { if (!cancelled) { setLoading(false); } });
        return () => { cancelled = true; };
    }, [methodName, range, reloadToken]);

    useEffect(() => {
        const refreshButton = document.getElementById(DASHBOARD_REFRESH_BUTTON_ID);
        const refresh = () => setReloadToken((t) => t + 1);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    const cur = data?.currency ?? {code: "", symbol: "", position: "before", decimals: 2};

    const applyLayout = (r: LayoutResponse) => {
        setLayout(r.charts);
        setPanels(r.panels);
        setSizeOptions(r.sizeoptions);
        if (RANGES.includes(r.range)) {
            setPrefRange(r.range);
        }
    };

    const openLayout = () => {
        setLayoutOpen(true);
        if (!layoutGetMethod) {
            return;
        }
        setLayoutBusy(true);
        void callMoodleService<LayoutResponse>(layoutGetMethod, {})
            .then((r) => { applyLayout(r); return r; })
            .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : String(caught)))
            .finally(() => setLayoutBusy(false));
    };

    const updateRow = (i: number, changes: Partial<LayoutItem>) =>
        setLayout((cur2) => (cur2 ? cur2.map((r, idx) => (idx === i ? {...r, ...changes} : r)) : cur2));

    const moveRow = (i: number, delta: number) =>
        setLayout((cur2) => {
            if (!cur2) {
                return cur2;
            }
            const next = [...cur2];
            const t = i + delta;
            if (t < 0 || t >= next.length) {
                return cur2;
            }
            [next[i], next[t]] = [next[t], next[i]];
            return next;
        });

    const updatePanel = (i: number, changes: Partial<PanelItem>) =>
        setPanels((cur2) => (cur2 ? cur2.map((r, idx) => (idx === i ? {...r, ...changes} : r)) : cur2));

    const movePanel = (i: number, delta: number) =>
        setPanels((cur2) => {
            if (!cur2) {
                return cur2;
            }
            const next = [...cur2];
            const t = i + delta;
            if (t < 0 || t >= next.length) {
                return cur2;
            }
            [next[i], next[t]] = [next[t], next[i]];
            return next;
        });

    // Refresh this app's charts and tell other dashboard roots (KPI strip) to refetch.
    const broadcastSaved = () => {
        setReloadToken((t) => t + 1);
        window.dispatchEvent(new CustomEvent(PREFS_SAVED_EVENT));
    };

    const saveLayout = () => {
        if (!layoutSaveMethod || !layout || !panels) {
            return;
        }
        setLayoutBusy(true);
        const items = layout.map((r, idx) => ({id: r.id, enabled: r.enabled, size: r.size, order: idx}));
        const panelitems = panels.map((r, idx) => ({id: r.id, enabled: r.enabled, size: r.size, order: idx}));
        const savescope = canSaveDefault ? scope : "personal";
        void callMoodleService<{success: boolean; message: string}>(layoutSaveMethod, {
            items, panels: panelitems, range: prefRange, scope: savescope, reset: false,
        })
            .then(() => {
                // Reflect the chosen default range in the live view immediately.
                if (RANGES.includes(prefRange)) {
                    setRange(prefRange);
                }
                setLayoutOpen(false);
                broadcastSaved();
                return null;
            })
            .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : String(caught)))
            .finally(() => setLayoutBusy(false));
    };

    const resetLayout = () => {
        if (!layoutSaveMethod || !layoutGetMethod) {
            return;
        }
        const savescope = canSaveDefault ? scope : "personal";
        setLayoutBusy(true);
        void callMoodleService(layoutSaveMethod, {items: [], panels: [], range: "", scope: savescope, reset: true})
            .then(() => callMoodleService<LayoutResponse>(layoutGetMethod, {}))
            .then((r) => { applyLayout(r); broadcastSaved(); return null; })
            .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : String(caught)))
            .finally(() => setLayoutBusy(false));
    };

    return (
        <div className={mcClasses("mc-charts")}>
            <div className={mcClasses("mc-charts__head")}>
                <h2 className={mcClasses("mc-charts__title")}>{labels.title}</h2>
                <div className={mcClasses("mc-charts__actions")}>
                    <div className={mcClasses("mc-charts__range")} role="group" aria-label={labels.title}>
                        {RANGES.map((rk) => (
                            <button
                                className={mcClasses("mc-button", rk === range ? "is-active" : "")}
                                data-mc-button={rk === range ? "primary" : "light"}
                                key={rk}
                                onClick={() => setRange(rk)}
                                type="button"
                            >
                                {labels["range_" + rk] ?? rk}
                            </button>
                        ))}
                    </div>
                    {canManage && layoutGetMethod && (
                        <button className={mcClasses("mc-charts__customize")} onClick={openLayout} type="button">
                            <i className="bi bi-sliders" aria-hidden="true" /> {labels.customize}
                        </button>
                    )}
                </div>
            </div>

            {error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{labels.error}: {error}</div>
                </div>
            )}

            <div className={mcClasses("mc-charts-grid")}>
                {loading && !data && [0, 1, 2, 3, 4].map((i) => (
                    <div className={mcClasses(`mc-chart-card ${spanClass(i < 2 ? 12 : 6)}`)} key={i}>
                        <div className={mcClasses("mc-chart-skel")}>{labels.loading}</div>
                    </div>
                ))}
                {data && data.charts.length === 0 && (
                    <div className={mcClasses(`mc-chart-card ${spanClass(12)}`)}>
                        <div className={mcClasses("mc-chart-empty")}>{labels.nocharts}</div>
                    </div>
                )}
                {data?.charts.map((chart) => (
                    <section className={mcClasses(`mc-chart-card ${spanClass(chart.size)}`)} key={chart.id}>
                        <div className={mcClasses("mc-chart-card__head")}>
                            <div>
                                <h3 className={mcClasses("mc-chart-card__title")}>{chart.title}</h3>
                                <p className={mcClasses("mc-chart-card__sub")}>{chart.subtitle}</p>
                            </div>
                            {chart.total && <span className={mcClasses("mc-chart-card__total")}>{chart.total}</span>}
                        </div>
                        <div className={mcClasses("mc-chart-card__body")}>
                            <MCChart chart={chart} cur={cur} empty={labels.noresults} />
                        </div>
                    </section>
                ))}
            </div>

            {layoutOpen && (
                <McDrawer
                    title={labels.managetitle}
                    subtitle={labels.manageintro}
                    onClose={() => setLayoutOpen(false)}
                    closeLabel={labels.cancel}
                    disableClose={layoutBusy}
                    footer={(
                        <>
                            <McButton className={mcClasses("btn-mc-primary")} disabled={!layout || !panels}
                                loading={layoutBusy} loadingLabel={labels.saving || "Saving..."} onClick={saveLayout} type="button">
                                {labels.save}
                            </McButton>
                            <button className={mcClasses("mc-button btn-mc-secondary")} disabled={layoutBusy}
                                onClick={() => setLayoutOpen(false)} type="button">
                                {labels.cancel}
                            </button>
                            <button className={mcClasses("mc-button mc-btn-soft mc-layout-reset")} disabled={layoutBusy}
                                onClick={resetLayout} type="button" title={labels.resethelp}>
                                {labels.reset}
                            </button>
                        </>
                    )}
                >
                            {(!layout || !panels) && (
                                <div className={mcClasses("mc-chart-skel")}>{labels.loading}</div>
                            )}

                            {layout && panels && (<>
                                {canSaveDefault && (
                                    <div className={mcClasses("mc-layout-scope")}>
                                        <div className={mcClasses("mc-layout-section__title")}>{labels.scopeheading}</div>
                                        <div className={mcClasses("mc-layout-scope__opts")} role="group"
                                            aria-label={labels.scopeheading}>
                                            <button type="button" disabled={layoutBusy}
                                                className={mcClasses("mc-button", scope === "personal" ? "is-active" : "")}
                                                data-mc-button={scope === "personal" ? "primary" : "light"}
                                                onClick={() => setScope("personal")}>
                                                <i className="bi bi-person" aria-hidden="true" /> {labels.scopepersonal}
                                            </button>
                                            <button type="button" disabled={layoutBusy}
                                                className={mcClasses("mc-button", scope === "sitedefault" ? "is-active" : "")}
                                                data-mc-button={scope === "sitedefault" ? "primary" : "light"}
                                                onClick={() => setScope("sitedefault")}>
                                                <i className="bi bi-people" aria-hidden="true" /> {labels.scopesite}
                                            </button>
                                        </div>
                                        <p className={mcClasses("mc-layout-help")}>{labels.scopehelp}</p>
                                    </div>
                                )}

                                <div className={mcClasses("mc-layout-section__title")}>{labels.rangeheading}</div>
                                <div className={mcClasses("mc-layout-range")}>
                                    <select className={mcClasses("mc-select")} value={prefRange} disabled={layoutBusy}
                                        onChange={(e) => setPrefRange(e.target.value)} aria-label={labels.rangeheading}>
                                        {RANGES.map((rk) => (
                                            <option key={rk} value={rk}>{labels["range_" + rk] ?? rk}</option>
                                        ))}
                                    </select>
                                    <p className={mcClasses("mc-layout-help")}>{labels.rangehelp}</p>
                                </div>

                                <div className={mcClasses("mc-layout-section__title")}>{labels.panelsheading}</div>
                                {panels.map((row, i) => (
                                    <div
                                        className={mcClasses("mc-layout-row" + (row.enabled ? "" : " mc-layout-row--off"))}
                                        key={row.id}
                                    >
                                        <div className={mcClasses("mc-layout-row__reorder")}>
                                            <button className={mcClasses("mc-button mc-btn-soft")} data-mc-button="soft"
                                                data-mc-button-size="icon" disabled={i === 0 || layoutBusy} onClick={() => movePanel(i, -1)}
                                                type="button" aria-label={labels.moveup}>
                                                <i className="bi bi-chevron-up" aria-hidden="true" />
                                            </button>
                                            <button className={mcClasses("mc-button mc-btn-soft")} data-mc-button="soft"
                                                data-mc-button-size="icon" disabled={i === panels.length - 1 || layoutBusy}
                                                onClick={() => movePanel(i, 1)} type="button" aria-label={labels.movedown}>
                                                <i className="bi bi-chevron-down" aria-hidden="true" />
                                            </button>
                                        </div>
                                        <label className={mcClasses("mc-layout-row__show")} title={labels.show}>
                                            <input type="checkbox" checked={row.enabled}
                                                onChange={(e) => updatePanel(i, {enabled: e.target.checked})}
                                                aria-label={labels.show} />
                                        </label>
                                        <span className={mcClasses("mc-layout-row__title")}>{row.title}</span>
                                        <select className={mcClasses("mc-select mc-layout-row__size")} value={row.size}
                                            onChange={(e) => updatePanel(i, {size: Number(e.target.value)})}
                                            disabled={!row.enabled} aria-label={labels.size}>
                                            {sizeOptions.map((o) => (
                                                <option key={o.value} value={o.value}>{o.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                ))}

                                <div className={mcClasses("mc-layout-section__title")}>{labels.chartsheading}</div>
                                {layout.map((row, i) => (
                                    <div
                                        className={mcClasses("mc-layout-row" + (row.enabled ? "" : " mc-layout-row--off"))}
                                        key={row.id}
                                    >
                                        <div className={mcClasses("mc-layout-row__reorder")}>
                                            <button className={mcClasses("mc-button mc-btn-soft")} data-mc-button="soft"
                                                data-mc-button-size="icon" disabled={i === 0 || layoutBusy} onClick={() => moveRow(i, -1)}
                                                type="button" aria-label={labels.moveup}>
                                                <i className="bi bi-chevron-up" aria-hidden="true" />
                                            </button>
                                            <button className={mcClasses("mc-button mc-btn-soft")} data-mc-button="soft"
                                                data-mc-button-size="icon" disabled={i === layout.length - 1 || layoutBusy}
                                                onClick={() => moveRow(i, 1)} type="button" aria-label={labels.movedown}>
                                                <i className="bi bi-chevron-down" aria-hidden="true" />
                                            </button>
                                        </div>
                                        <label className={mcClasses("mc-layout-row__show")} title={labels.show}>
                                            <input type="checkbox" checked={row.enabled}
                                                onChange={(e) => updateRow(i, {enabled: e.target.checked})}
                                                aria-label={labels.show} />
                                        </label>
                                        <span className={mcClasses("mc-layout-row__title")}>{row.title}</span>
                                        <select className={mcClasses("mc-select mc-layout-row__size")} value={row.size}
                                            onChange={(e) => updateRow(i, {size: Number(e.target.value)})}
                                            disabled={!row.enabled} aria-label={labels.size}>
                                            {sizeOptions.map((o) => (
                                                <option key={o.value} value={o.value}>{o.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                ))}
                            </>)}
                </McDrawer>
            )}
        </div>
    );
}

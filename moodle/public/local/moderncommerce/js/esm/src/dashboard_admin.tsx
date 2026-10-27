// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React admin dashboard for Modern Commerce.
 *
 * @module     local_moderncommerce/dashboard_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {mcClasses, useModernCommerceClassSync} from "./design_system";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type Labels = Record<string, string>;

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
    size?: number;
};

const TILE_SPANS = [12, 6, 4, 3];
const tileSpanClass = (size?: number): string =>
    `mc-stat-tile--span${TILE_SPANS.includes(size as number) ? size : 3}`;

type RecentOrder = {
    id: number;
    ordernumber: string;
    customername: string;
    displaytotal: string;
    status: string;
    statuslabel: string;
    statusclass: string;
    date: string;
    viewurl: string;
};

type TopProduct = {
    rank: number;
    name: string;
    producttype: string;
    sold: number;
    displayrevenue: string;
};

type Alert = {
    level: string;
    message: string;
    actionlabel: string;
    actionurl: string;
};

type DashboardResponse = {
    metrics: Metric[];
    recentorders: RecentOrder[];
    topproducts: TopProduct[];
    alerts: Alert[];
};

type DashboardAdminProps = {
    methodName: string;
    ordersUrl: string;
    reportsUrl: string;
    useComponentTypography?: boolean;
    labels: Labels;
};

const callMoodleService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const url = `${M.cfg.wwwroot}/lib/ajax/service.php`
        + `?sesskey=${encodeURIComponent(M.cfg.sesskey)}`
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

const metricDeltaClass = (metric: Metric): string => {
    if (metric.deltaup) {
        return "up";
    }

    if (metric.deltadown) {
        return "down";
    }

    return "flat";
};

// Fired by the charts app after the customization drawer saves; keeps the KPI strip in sync.
const PREFS_SAVED_EVENT = "mc:dashboard-prefs-saved";
const DASHBOARD_REFRESH_BUTTON_ID = "moderncommerce-dashboard-refresh";

export default function DashboardAdmin({
    methodName,
    ordersUrl,
    reportsUrl,
    useComponentTypography = false,
    labels,
}: DashboardAdminProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<DashboardResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [reloadToken, setReloadToken] = useState(0);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError("");

        void callMoodleService<DashboardResponse>(methodName, {})
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                }

                return result;
            })
            .catch((caught: unknown) => {
                if (!cancelled) {
                    setError(caught instanceof Error ? caught.message : String(caught));
                }

                return null;
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [methodName, reloadToken]);

    // Re-fetch when the admin reorders/hides KPI tiles in the customization drawer.
    useEffect(() => {
        const onSaved = () => setReloadToken((t) => t + 1);
        window.addEventListener(PREFS_SAVED_EVENT, onSaved);
        return () => window.removeEventListener(PREFS_SAVED_EVENT, onSaved);
    }, []);

    useEffect(() => {
        const refreshButton = document.getElementById(DASHBOARD_REFRESH_BUTTON_ID);
        const refresh = () => setReloadToken((t) => t + 1);
        refreshButton?.addEventListener("click", refresh);

        return () => {
            refreshButton?.removeEventListener("click", refresh);
        };
    }, []);

    if (loading && !data) {
        return <div className={mcClasses("mc-product-admin__loading")}>{labels.loading}</div>;
    }

    if (error) {
        return (
            <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                <div className={mcClasses("mc-alert__body")}>{error}</div>
            </div>
        );
    }

    if (!data) {
        return null;
    }

    return (
        <section
            className={mcClasses(
                "mc-dashboard mc-component-upgrades",
                useComponentTypography && "mc-component-upgrades--typography",
            )}
            aria-label={labels.title}
        >
            {data.alerts.length > 0 && (
                <div className="mb-3">
                    {data.alerts.map((alert, index) => {
                        const alertVariant = alert.level === "info" ? "info" : "warning";

                        return (
                            <div
                                className={mcClasses(
                                    `mc-alert mc-alert--${alertVariant}`,
                                    "d-flex flex-wrap justify-content-between align-items-center gap-2",
                                )}
                                key={index}
                                role="status"
                            >
                                <span>{alert.message}</span>
                                <a className={mcClasses("mc-button mc-btn-soft")} href={alert.actionurl}>
                                    {alert.actionlabel}
                                </a>
                            </div>
                        );
                    })}
                </div>
            )}

            <div className={mcClasses("mc-stat-strip mc-stat-strip--grid")} aria-label={labels.title}>
                {data.metrics.map((metric) => (
                    <article
                        className={mcClasses(
                            `mc-stat-tile mc-stat-tile--${metric.variant} ${tileSpanClass(metric.size)}`,
                        )}
                        key={metric.key}
                    >
                        <i className={`bi ${metric.icon} mc-stat-tile__icon`} aria-hidden="true" />
                        <div className={mcClasses("mc-stat-tile__body")}>
                            <span className={mcClasses("mc-stat-tile__label")}>{metric.label}</span>
                            <strong className={mcClasses("mc-stat-tile__value")}>{metric.value}</strong>
                            {metric.hasdelta && (
                                <span
                                    className={mcClasses(
                                        "mc-stat-tile__delta",
                                        `mc-stat-tile__delta--${metricDeltaClass(metric)}`,
                                    )}
                                >
                                    {metric.deltaup && <i className="bi bi-arrow-up-short" aria-hidden="true" />}
                                    {metric.deltadown && <i className="bi bi-arrow-down-short" aria-hidden="true" />}
                                    {metric.delta}
                                </span>
                            )}
                        </div>
                        <i className={`bi ${metric.icon} mc-stat-tile__watermark`} aria-hidden="true" />
                    </article>
                ))}
            </div>

        </section>
    );
}

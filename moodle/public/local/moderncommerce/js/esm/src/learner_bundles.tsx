// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React learner bundles page for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_bundles
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {badgeClass, callMoodleService, formatCount, Labels} from "./learner_common";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import ModernLearnerLayout, {learnerAppHashUrl, type LearnerLayoutContext} from "./learner_layout";
import {LearnerStatStrip, LearnerStatTile} from "./learner_stat_tiles";

type Stats = {
    courses: number;
    completedcourses: number;
    bundles: number;
    programs: number;
    subscriptions: number;
    plans: number;
    certificates: number;
};

type Product = {
    id: number;
    name: string;
    description: string;
    producttype: string;
    typelabel: string;
    coursecount: number;
    imageurl: string;
    hasimage: boolean;
    detailsurl: string;
    status: string;
    statuslabel: string;
    statusclass: string;
};

type BundlesResponse = {
    success: boolean;
    message: string;
    stats: Stats;
    access: {
        bundles: Product[];
    };
    urls: {
        catalog: string;
    };
};

type LearnerBundlesProps = {
    methodName: string;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

type ViewMode = "grid" | "list";

const label = (labels: Labels, key: string, fallback = ""): string => labels[key] || fallback || key;

const activeAccessCount = (stats: Stats): number => (
    stats.bundles + stats.programs + stats.subscriptions + stats.plans
);

const bundleAccessUrl = (bundle: Product): string => learnerAppHashUrl(`bundle/${bundle.id}`);

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}>
                <i className="bi bi-layers" aria-hidden="true" />
            </span>
            <p className={mcClasses("mc-empty__title")}>{label(labels, "loading")}</p>
        </div>
    );
}

function EmptyState({
    labels,
    catalogUrl,
}: {
    labels: Labels;
    catalogUrl: string;
}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}>
                <i className="bi bi-layers" aria-hidden="true" />
            </span>
            <p className={mcClasses("mc-empty__title")}>
                {label(labels, "nobundlesowned")}
            </p>
            <p className={mcClasses("mc-empty__desc")}>
                {label(
                    labels,
                    "nobundlesowneddesc",
                    "Purchased bundles will appear here. Browse the catalog to find bundles available to you."
                )}
            </p>
            <a className={mcClasses("mc-button btn-mc-primary")} href={catalogUrl}>
                <i className="bi bi-grid" aria-hidden="true" />
                {label(labels, "browsecatalog")}
            </a>
        </div>
    );
}

function BundleListCard({
    bundle,
    labels,
}: {
    bundle: Product;
    labels: Labels;
}) {
    return (
        <article className={mcClasses("mc-card mb-2")}>
            <div className={mcClasses("mc-card-body d-flex gap-3 align-items-start flex-wrap flex-md-nowrap")}>
                {bundle.hasimage ? (
                    <img
                        src={bundle.imageurl}
                        alt=""
                        width={144}
                        height={82}
                        className="rounded object-fit-cover flex-shrink-0"
                        loading="lazy"
                    />
                ) : (
                    <span
                        className="rounded bg-light d-flex align-items-center justify-content-center flex-shrink-0"
                        style={{width: 144, height: 82}}
                        aria-hidden="true"
                    >
                        <i className="bi bi-layers text-muted" />
                    </span>
                )}
                <div className="flex-grow-1 min-w-0">
                    <div className="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <span className={mcClasses("mc-badge mc-badge-bundle")}>{bundle.typelabel}</span>
                        <span className={mcClasses(`mc-badge mc-badge--${badgeClass(bundle.statusclass)}`)}>
                            {bundle.statuslabel}
                        </span>
                        <span className={mcClasses("mc-cell-muted small")}>
                            {formatCount(bundle.coursecount)} {label(labels, "includedcourses")}
                        </span>
                    </div>
                    <h2 className={mcClasses("mc-card-title mb-1")}>{bundle.name}</h2>
                    {bundle.description && (
                        <p className={mcClasses("mc-cell-muted mb-0")}>{bundle.description}</p>
                    )}
                </div>
                <div className="d-flex flex-column gap-2 align-items-md-end ms-md-auto">
                    <a className={mcClasses("mc-button btn-mc-primary")} href={bundleAccessUrl(bundle)}>
                        <i className="bi bi-list-check" aria-hidden="true" />
                        {label(labels, "openbundle")}
                    </a>
                    <a className={mcClasses("mc-button btn-mc-secondary")} href={bundle.detailsurl}>
                        <i className="bi bi-box-arrow-up-right" aria-hidden="true" />
                        {label(labels, "viewbundle")}
                    </a>
                </div>
            </div>
        </article>
    );
}

function BundleGridCard({
    bundle,
    labels,
}: {
    bundle: Product;
    labels: Labels;
}) {
    const accessUrl = bundleAccessUrl(bundle);

    return (
        <article className={mcClasses("mc-learner-bundle-card")}>
            <a className={mcClasses("mc-learner-bundle-card__media")} href={accessUrl}>
                {bundle.hasimage ? (
                    <img src={bundle.imageurl} alt="" loading="lazy" />
                ) : (
                    <span className={mcClasses("mc-learner-bundle-card__fallback")} aria-hidden="true">
                        <i className="bi bi-layers" />
                    </span>
                )}
            </a>
            <div className={mcClasses("mc-learner-bundle-card__body")}>
                <div className={mcClasses("mc-learner-bundle-card__badges")}>
                    <span className={mcClasses("mc-badge mc-badge-bundle")}>{bundle.typelabel}</span>
                    <span className={mcClasses(`mc-badge mc-badge--${badgeClass(bundle.statusclass)}`)}>
                        {bundle.statuslabel}
                    </span>
                </div>
                <h3 className={mcClasses("mc-learner-bundle-card__title")}>
                    <a href={accessUrl}>{bundle.name}</a>
                </h3>
                {bundle.description && (
                    <p className={mcClasses("mc-learner-bundle-card__desc")}>{bundle.description}</p>
                )}
                <div className={mcClasses("mc-learner-bundle-card__meta")}>
                    <span>
                        <i className="bi bi-play-circle" aria-hidden="true" />
                        {formatCount(bundle.coursecount)} {label(labels, "includedcourses")}
                    </span>
                </div>
            </div>
            <div className={mcClasses("mc-learner-bundle-card__actions")}>
                <a className={mcClasses("mc-button btn-mc-primary")} href={accessUrl}>
                    <i className="bi bi-list-check" aria-hidden="true" />
                    {label(labels, "openbundle")}
                </a>
                <a className={mcClasses("mc-button btn-mc-secondary")} href={bundle.detailsurl}>
                    <i className="bi bi-box-arrow-up-right" aria-hidden="true" />
                    {label(labels, "viewbundle")}
                </a>
            </div>
        </article>
    );
}

export default function LearnerBundles({
    methodName,
    labels,
    layout,
}: LearnerBundlesProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<BundlesResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [viewMode, setViewMode] = useState<ViewMode>("list");

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");

        callMoodleService<BundlesResponse>(methodName, {})
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                }
                return result;
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
    }, [methodName]);

    const bundles = data?.access.bundles ?? [];
    const catalogUrl = data?.urls.catalog || learnerAppHashUrl("library");
    const stats = data?.stats;
    const isGridView = viewMode === "grid";
    const bundleListId = "mc-learner-bundles-list";
    const gridLabel = label(labels, "viewgrid");
    const listLabel = label(labels, "viewlist");
    const actions = (
        <a className={mcClasses("mc-button btn-mc-secondary")} href={catalogUrl}>
            <i className="bi bi-grid" aria-hidden="true" />
            {label(labels, "browsecatalog")}
        </a>
    );

    return (
        <ModernLearnerLayout
            activeNav="bundles"
            title={label(labels, "mybundles")}
            subtitle={label(
                labels,
                "mybundlesdesc",
                "Bundles attached to your account. Open a bundle to continue its included courses."
            )}
            labels={labels}
            layout={layout}
            stats={stats ? {
                courses: stats.courses,
                completedcourses: stats.completedcourses,
                certificates: stats.certificates,
                activeaccess: activeAccessCount(stats),
            } : undefined}
            actions={actions}
        >
            {loading && <LoadingState labels={labels} />}

            {!loading && (error || !data || !data.success) && (
                <div className={mcClasses("mc-alert mc-alert--warning")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>
                        {error || data?.message || label(labels, "dashboardempty")}
                    </div>
                </div>
            )}

            {!loading && data?.success && (
                <div className={mcClasses("mc-learner-bundles")}>
                    <LearnerStatStrip>
                        <LearnerStatTile
                            label={label(labels, "mybundles")}
                            value={bundles.length}
                            icon="bi-layers"
                            variant="primary"
                        />
                        <LearnerStatTile
                            label={label(labels, "totalcourses")}
                            value={bundles.reduce((total, bundle) => total + bundle.coursecount, 0)}
                            icon="bi-play-circle"
                            variant="success"
                        />
                        <LearnerStatTile
                            label={label(labels, "activebundles")}
                            value={bundles.filter((bundle) => bundle.status === "active").length}
                            icon="bi-check-circle"
                            variant="info"
                        />
                        <LearnerStatTile
                            label={label(labels, "activeaccess")}
                            value={stats ? activeAccessCount(stats) : bundles.length}
                            icon="bi-unlock"
                            variant="warning"
                        />
                    </LearnerStatStrip>

                    {bundles.length > 0 ? (
                        <section className={mcClasses("mc-card")}>
                            <div className={mcClasses("mc-card-header")}>
                                <h2 className={mcClasses("mc-card-title")}>
                                    {label(labels, "mybundles")}
                                </h2>
                                <div className="d-flex align-items-center gap-2 flex-wrap">
                                    <span className={mcClasses("mc-badge mc-badge--neutral")}>
                                        {formatCount(bundles.length)}
                                    </span>
                                    <div
                                        className={mcClasses("mc-modern-view-toggle")}
                                        role="group"
                                        aria-label={label(labels, "bundleviewtoggle")}
                                    >
                                        <button
                                            className={mcClasses("mc-button", isGridView ? "active" : "")}
                                            data-mc-button={isGridView ? "primary" : "light"}
                                            data-mc-button-size="icon"
                                            type="button"
                                            aria-controls={bundleListId}
                                            aria-label={gridLabel}
                                            aria-pressed={isGridView}
                                            title={gridLabel}
                                            onClick={() => setViewMode("grid")}
                                        >
                                            <i className="bi bi-grid-3x3-gap-fill" aria-hidden="true" />
                                        </button>
                                        <button
                                            className={mcClasses("mc-button", !isGridView ? "active" : "")}
                                            data-mc-button={!isGridView ? "primary" : "light"}
                                            data-mc-button-size="icon"
                                            type="button"
                                            aria-controls={bundleListId}
                                            aria-label={listLabel}
                                            aria-pressed={!isGridView}
                                            title={listLabel}
                                            onClick={() => setViewMode("list")}
                                        >
                                            <i className="bi bi-list-ul" aria-hidden="true" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div className={mcClasses("mc-card-body")}>
                                <div
                                    className={mcClasses(
                                        isGridView ? "mc-learner-bundle-grid" : "mc-learner-bundle-list"
                                    )}
                                    id={bundleListId}
                                >
                                    {bundles.map((bundle) => (
                                        isGridView ? (
                                            <BundleGridCard bundle={bundle} labels={labels} key={bundle.id} />
                                        ) : (
                                            <BundleListCard bundle={bundle} labels={labels} key={bundle.id} />
                                        )
                                    ))}
                                </div>
                            </div>
                        </section>
                    ) : (
                        <EmptyState labels={labels} catalogUrl={catalogUrl} />
                    )}
                </div>
            )}
        </ModernLearnerLayout>
    );
}

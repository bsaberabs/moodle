// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Learner wishlist page for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_wishlist
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {callMoodleService, Labels, refreshNavbarCart} from "./learner_common";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";
import {LearnerStatStrip, LearnerStatTile} from "./learner_stat_tiles";
import {confirmDialog} from "./modal";

type WishlistItem = {
    wishlistid: number;
    productid: number;
    courseid: number;
    title: string;
    producttype: string;
    typelabel: string;
    category: string;
    thumbnail: string;
    displayprice: string;
    displayoriginalprice: string;
    hasoriginalprice: boolean;
    detailsurl: string;
    saveddate: string;
    available: boolean;
    hasaccess: boolean;
};

type WishlistResponse = {
    success: boolean;
    message: string;
    items: WishlistItem[];
    stats: {
        total: number;
        available: number;
    };
    urls: {
        catalog: string;
        cart: string;
    };
};

type WishlistProps = {
    methodName: string;
    updateMethodName: string;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

const label = (labels: Labels, key: string, fallback = ""): string => labels[key] || fallback || key;

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}>
                <i className="bi bi-heart" aria-hidden="true" />
            </span>
            <p className={mcClasses("mc-empty__title")}>{label(labels, "loading")}</p>
        </div>
    );
}

function EmptyState({labels, catalogUrl}: {labels: Labels; catalogUrl: string}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}>
                <i className="bi bi-heart" aria-hidden="true" />
            </span>
            <p className={mcClasses("mc-empty__title")}>
                {label(labels, "wishlistempty")}
            </p>
            <p className={mcClasses("mc-empty__desc")}>
                {label(labels, "wishlistemptydesc")}
            </p>
            <a className={mcClasses("mc-button btn-mc-primary")} href={catalogUrl}>
                <i className="bi bi-search" aria-hidden="true" />
                {label(labels, "browsecatalog")}
            </a>
        </div>
    );
}

function WishlistCard({
    item,
    labels,
    busy,
    onMove,
    onRemove,
}: {
    item: WishlistItem;
    labels: Labels;
    busy: boolean;
    onMove: (item: WishlistItem) => void;
    onRemove: (item: WishlistItem) => void;
}) {
    const actionDisabled = busy || !item.available || item.hasaccess;
    const actionLabel = item.hasaccess
        ? label(labels, "alreadyowned")
        : !item.available
            ? label(labels, "notavailable")
            : label(labels, "movetocart");
    const statusLabel = item.hasaccess
        ? label(labels, "alreadyowned")
        : item.available
            ? label(labels, "available")
            : label(labels, "notavailable");

    return (
        <article
            className={mcClasses(
                "mc-learner-wishlist-card",
                !item.available && "mc-learner-wishlist-card--unavailable",
                item.hasaccess && "mc-learner-wishlist-card--owned"
            )}
        >
            <a className={mcClasses("mc-learner-wishlist-card__media")} href={item.detailsurl}>
                {item.thumbnail ? (
                    <img
                        src={item.thumbnail}
                        alt=""
                        width={176}
                        height={99}
                        loading="lazy"
                    />
                ) : (
                    <span className={mcClasses("mc-learner-wishlist-card__placeholder")} aria-hidden="true">
                        <i className="bi bi-mortarboard" />
                    </span>
                )}
            </a>
            <div className={mcClasses("mc-learner-wishlist-card__body")}>
                <div className={mcClasses("mc-learner-wishlist-card__meta")}>
                    <span className={mcClasses("mc-badge mc-badge--neutral")}>{item.typelabel}</span>
                    <span
                        className={mcClasses(
                            "mc-badge",
                            item.hasaccess ? "mc-badge--success" : item.available ? "mc-badge--primary" : "mc-badge--warning"
                        )}
                    >
                        {statusLabel}
                    </span>
                    {item.category && <span className={mcClasses("mc-cell-muted small")}>{item.category}</span>}
                    <span className={mcClasses("mc-cell-muted small")}>
                        {label(labels, "saved")} {item.saveddate}
                    </span>
                </div>
                <h2 className={mcClasses("mc-learner-wishlist-card__title")}>
                    <a href={item.detailsurl}>{item.title}</a>
                </h2>
                <div className={mcClasses("mc-learner-wishlist-card__price")}>
                    <strong>{item.displayprice}</strong>
                    {item.hasoriginalprice && (
                        <span>
                            {item.displayoriginalprice}
                        </span>
                    )}
                </div>
            </div>
            <div className={mcClasses("mc-learner-wishlist-card__actions")}>
                <a className={mcClasses("mc-button btn-mc-secondary")} href={item.detailsurl}>
                    <i className="bi bi-eye" aria-hidden="true" />
                    {label(labels, "viewdetails")}
                </a>
                <button
                    type="button"
                    className={mcClasses("mc-button btn-mc-primary")}
                    disabled={actionDisabled}
                    onClick={() => onMove(item)}
                >
                    <i className="bi bi-cart-plus" aria-hidden="true" />
                    {actionLabel}
                </button>
                <button
                    type="button"
                    className={mcClasses("mc-button btn-mc-secondary")}
                    disabled={busy}
                    onClick={() => onRemove(item)}
                >
                    <i className="bi bi-trash" aria-hidden="true" />
                    {label(labels, "remove")}
                </button>
            </div>
        </article>
    );
}

async function confirmRemove(labels: Labels): Promise<boolean> {
    return confirmDialog({
        title: label(labels, "remove"),
        message: label(labels, "wishlistremoveconfirm"),
        confirmLabel: label(labels, "remove"),
        cancelLabel: label(labels, "cancel"),
        danger: true,
    });
}

export default function LearnerWishlist({
    methodName,
    updateMethodName,
    labels,
    layout,
}: WishlistProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<WishlistResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [busyProduct, setBusyProduct] = useState(0);

    const load = () => {
        setLoading(true);
        setError("");
        callMoodleService<WishlistResponse>(methodName, {})
            .then(setData)
            .catch((caught: Error) => setError(caught.message))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
    }, [methodName]);

    const mutate = async(action: "remove" | "movetocart", item: WishlistItem) => {
        setBusyProduct(item.productid);
        setError("");
        try {
            const result = await callMoodleService<WishlistResponse>(updateMethodName, {
                action,
                productid: item.productid,
            });
            setData(result);
            if (action === "movetocart") {
                void refreshNavbarCart();
            }
            if (result.message) {
                toast.success(result.message);
            }
        } catch (caught) {
            setError((caught as Error).message);
        } finally {
            setBusyProduct(0);
        }
    };

    const removeItem = async(item: WishlistItem) => {
        if (!await confirmRemove(labels)) {
            return;
        }

        await mutate("remove", item);
    };

    const title = label(labels, "wishlist");
    const catalogUrl = data?.urls.catalog || "#/library";
    const pageActions = (
        <a className={mcClasses("mc-button btn-mc-secondary")} href={catalogUrl}>
            <i className="bi bi-grid" aria-hidden="true" />
            {label(labels, "browsecatalog")}
        </a>
    );

    return (
        <ModernLearnerLayout
            activeNav="wishlist"
            title={title}
            subtitle={label(labels, "wishlistdesc")}
            labels={labels}
            layout={layout}
            actions={pageActions}
        >
            {loading && <LoadingState labels={labels} />}
            {!loading && error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}
            {!loading && !error && data && (
                <div className={mcClasses("mc-learner-wishlist")}>
                    <LearnerStatStrip>
                        <LearnerStatTile
                            label={label(labels, "saveditems")}
                            value={data.stats.total}
                            icon="bi-heart"
                            variant="primary"
                        />
                        <LearnerStatTile
                            label={label(labels, "available")}
                            value={data.stats.available}
                            icon="bi-bag-check"
                            variant="success"
                        />
                    </LearnerStatStrip>
                    {data.items.length === 0 ? (
                        <EmptyState labels={labels} catalogUrl={catalogUrl} />
                    ) : (
                        <div className={mcClasses("mc-learner-wishlist__list")}>
                            {data.items.map((item) => (
                                <WishlistCard
                                    key={item.productid}
                                    item={item}
                                    labels={labels}
                                    busy={busyProduct === item.productid}
                                    onMove={(selected) => void mutate("movetocart", selected)}
                                    onRemove={(selected) => void removeItem(selected)}
                                />
                            ))}
                        </div>
                    )}
                </div>
            )}
        </ModernLearnerLayout>
    );
}

// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Shared modern learner layout for Modern Commerce learner React pages.
 *
 * @module     local_moderncommerce/learner_layout
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import type {Dispatch, ReactNode} from "react";
import type {Labels} from "./learner_common";
import {callMoodleService, wwwroot} from "./learner_common";
import {mcClasses} from "./design_system";
import {FieldError} from "./form";

export type LearnerLayoutUser = {
    id?: number;
    fullname: string;
    initials: string;
    avatarurl?: string;
    membersince?: string;
    email?: string;
    canedit?: boolean;
    editmessage?: string;
};

export type LearnerLayoutStats = {
    courses?: number;
    completedcourses?: number;
    certificates?: number;
    activeaccess?: number;
};

export type LearnerLayoutFeatures = {
    subscriptions?: boolean;
};

export type LearnerLayoutAvatar = {
    savemethod?: string;
};

export type LearnerLayoutContext = {
    user?: LearnerLayoutUser;
    stats?: LearnerLayoutStats;
    features?: LearnerLayoutFeatures;
    avatar?: LearnerLayoutAvatar;
    cartcount?: number;
    labels?: Labels;
};

export type LearnerNavKey =
    | "dashboard"
    | "catalog"
    | "courses"
    | "bundles"
    | "orders"
    | "wishlist"
    | "subscriptions"
    | "access"
    | "certificates"
    | "redeem"
    | "cart"
    | "checkout"
    | "calendar"
    | "grades"
    | "profile";

type ModernLearnerLayoutProps = {
    activeNav: LearnerNavKey;
    title: string;
    subtitle?: string;
    eyebrow?: string;
    labels: Labels;
    layout?: LearnerLayoutContext;
    profile?: LearnerLayoutUser;
    stats?: LearnerLayoutStats;
    avatarEditor?: LearnerAvatarEditor;
    actions?: ReactNode;
    /** Live cart size; overrides the server-seeded count so the badge tracks edits. */
    cartCount?: number;
    children: ReactNode;
};

export type LearnerAvatarEditor = {
    canEdit: boolean;
    editMessage?: string;
    imageUrl: string;
    imagePreview?: string;
    imageBusy: boolean;
    imageErrors: Record<string, string>;
    feedbackMessage?: string;
    feedbackType?: "success" | "danger";
    onFile: Dispatch<File | null>;
    onUpload: () => void;
    onRemove: () => void;
};

type NavItem = {
    key: LearnerNavKey;
    labelText: string;
    icon: string;
    href: string;
    badge?: number;
};

const label = (labels: Labels, key: string): string => labels[key] || key;

const localCommerceUrl = (path: string): string => `${wwwroot()}/local/moderncommerce/${path}`;

export const learnerAppHashUrl = (route = "dashboard"): string => {
    const cleanroute = route.replace(/^#?\//, "");
    return `#/${cleanroute || "dashboard"}`;
};

export const learnerAppUrl = (route = "dashboard"): string => {
    const cleanroute = route.replace(/^#?\//, "");
    return `${localCommerceUrl("learner/index.php")}${learnerAppHashUrl(cleanroute)}`;
};

export const learnerAppHref = (href: string | undefined, fallbackRoute = "dashboard"): string => {
    const fallback = learnerAppHashUrl(fallbackRoute);

    if (!href) {
        return fallback;
    }

    try {
        const expected = new URL(localCommerceUrl("learner/index.php"), window.location.href);
        const target = new URL(href, window.location.href);

        if (target.origin === expected.origin && target.pathname === expected.pathname && target.hash.startsWith("#/")) {
            return target.hash;
        }
    } catch {
        return href;
    }

    return href;
};

const firstName = (fullname: string): string => {
    return fullname.trim().split(/\s+/)[0] || fullname;
};

const learnerLabels = (labels: Labels, layout?: LearnerLayoutContext): Labels => ({
    ...(layout?.labels ?? {}),
    ...labels,
});

const defaultProfile = (labels: Labels): LearnerLayoutUser => ({
    fullname: label(labels, "learner"),
    initials: "LC",
});

const navItems = (labels: Labels, layout?: LearnerLayoutContext, cartCountOverride?: number): NavItem[] => {
    const subscriptionsEnabled = layout?.features?.subscriptions !== false;
    // Live count when the current screen knows it, otherwise the server-seeded value.
    const cartCount = cartCountOverride ?? layout?.cartcount ?? 0;
    const items: NavItem[] = [
        {
            key: "dashboard",
            labelText: label(labels, "dashboard"),
            icon: "bi-speedometer2",
            href: learnerAppUrl("dashboard"),
        },
        {
            key: "catalog",
            labelText: label(labels, "courselibrary"),
            icon: "bi-search",
            href: learnerAppUrl("library"),
        },
        {
            key: "courses",
            labelText: label(labels, "mycourses"),
            icon: "bi-mortarboard",
            href: learnerAppUrl("courses"),
        },
        {
            key: "bundles",
            labelText: label(labels, "mybundles"),
            icon: "bi-layers",
            href: learnerAppUrl("bundles"),
        },
        {
            key: "orders",
            labelText: label(labels, "ordersandinvoices"),
            icon: "bi-receipt",
            href: learnerAppUrl("orders"),
        },
        {
            key: "wishlist",
            labelText: label(labels, "wishlist"),
            icon: "bi-heart",
            href: learnerAppUrl("wishlist"),
        },
        {
            key: "subscriptions",
            labelText: label(labels, "subscriptions"),
            icon: "bi-credit-card",
            href: learnerAppUrl("subscriptions"),
        },
        {
            key: "certificates",
            labelText: label(labels, "mycertificates"),
            icon: "bi-patch-check-fill",
            href: learnerAppUrl("certificates"),
        },
        {
            key: "redeem",
            labelText: label(labels, "redeemkeys"),
            icon: "bi-key",
            href: learnerAppUrl("redeem"),
        },
        // Checkout is a step in the cart flow, not a destination: it is only reachable
        // with a non-empty cart, so it is surfaced by the cart's CTA rather than as a
        // standing nav item. The #/checkout route still works and highlights Cart.
        {
            key: "cart",
            labelText: label(labels, "cart"),
            icon: "bi-bag",
            href: learnerAppUrl("cart"),
            badge: cartCount > 0 ? cartCount : undefined,
        },
        {
            key: "calendar",
            labelText: label(labels, "calendar"),
            icon: "bi-calendar",
            href: learnerAppUrl("calendar"),
        },
        {
            key: "grades",
            labelText: label(labels, "mygrades"),
            icon: "bi-clipboard-check",
            href: learnerAppUrl("grades"),
        },
        {
            key: "profile",
            labelText: label(labels, "myprofile"),
            icon: "bi-person-fill",
            href: learnerAppUrl("profile"),
        },
    ];

    return items.filter((item) => item.key !== "subscriptions" || subscriptionsEnabled);
};

function HeroBand({
    title,
    subtitle,
    eyebrow,
    labels,
}: {
    title: string;
    subtitle?: string;
    eyebrow?: string;
    labels: Labels;
}) {
    return (
        <section className={mcClasses("mc-modern-learner-hero")} aria-labelledby="mc-modern-learner-page-title">
            <span className={mcClasses("mc-modern-learner-eyebrow")}>
                {eyebrow || label(labels, "learnerworkspace")}
            </span>
            <h1 id="mc-modern-learner-page-title">{title}</h1>
            {subtitle && <p>{subtitle}</p>}
        </section>
    );
}

type AvatarFieldError = {name: string; message: string};

type AvatarSaveResponse = {
    success: boolean;
    message: string;
    errors: AvatarFieldError[];
    profileimage: string;
};

const avatarFileToBase64 = async(file: File): Promise<string> => {
    const dataUrl = await new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ""));
        reader.onerror = () => reject(reader.error || new Error("File could not be read."));
        reader.readAsDataURL(file);
    });
    const content = dataUrl.includes(",") ? dataUrl.substring(dataUrl.indexOf(",") + 1) : dataUrl;
    const clean = content.replace(/\s+/g, "");
    return clean.match(/.{1,64}/g)?.join("\n") || clean;
};

const avatarErrorMap = (errors: AvatarFieldError[]): Record<string, string> =>
    errors.reduce((carry, error) => ({...carry, [error.name]: error.message}), {} as Record<string, string>);

// Self-contained avatar editor for the shared sidebar, so the camera button opens
// the change-photo modal on every learner page. The profile page passes its own
// (richer) editor instead, which takes precedence over this fallback.
function useSidebarAvatarEditor(config: {
    savemethod: string;
    userid: number;
    canEdit: boolean;
    editMessage?: string;
    initialImageUrl: string;
    labels: Labels;
}): {editor: LearnerAvatarEditor; currentImageUrl: string} {
    const [selectedImage, setSelectedImage] = useState<File | null>(null);
    const [imagePreview, setImagePreview] = useState("");
    const [imageUrl, setImageUrl] = useState(config.initialImageUrl);
    const [imageBusy, setImageBusy] = useState(false);
    const [imageErrors, setImageErrors] = useState<Record<string, string>>({});
    const [feedbackMessage, setFeedbackMessage] = useState("");
    const [feedbackType, setFeedbackType] = useState<"success" | "danger">("success");

    useEffect(() => {
        setImageUrl(config.initialImageUrl);
    }, [config.initialImageUrl]);

    const chooseImage = (file: File | null) => {
        setSelectedImage(file);
        setImageErrors({});
        setFeedbackMessage("");
        setImagePreview(file ? URL.createObjectURL(file) : "");
    };

    const persist = async(args: Record<string, unknown>) => {
        setImageBusy(true);
        setImageErrors({});
        try {
            const result = await callMoodleService<AvatarSaveResponse>(config.savemethod, args);
            setFeedbackMessage(result.message);
            setFeedbackType(result.success ? "success" : "danger");
            setImageErrors(avatarErrorMap(result.errors || []));
            if (result.success) {
                setImageUrl(result.profileimage);
                setSelectedImage(null);
                setImagePreview("");
            }
        } catch (caught) {
            setFeedbackMessage(caught instanceof Error ? caught.message : String(caught));
            setFeedbackType("danger");
        } finally {
            setImageBusy(false);
        }
    };

    const uploadImage = async() => {
        if (!selectedImage) {
            setImageErrors({profileimage: label(config.labels, "profileimagefilemissing")});
            setFeedbackMessage("");
            return;
        }
        const imagecontent = await avatarFileToBase64(selectedImage);
        await persist({
            userid: config.userid,
            deletepicture: false,
            filename: selectedImage.name,
            mimetype: selectedImage.type,
            filesize: selectedImage.size,
            imagecontent,
        });
    };

    const removeImage = async() => {
        await persist({userid: config.userid, deletepicture: true});
    };

    return {
        editor: {
            canEdit: config.canEdit,
            editMessage: config.editMessage,
            imageUrl,
            imagePreview,
            imageBusy,
            imageErrors,
            feedbackMessage,
            feedbackType,
            onFile: chooseImage,
            onUpload: () => void uploadImage(),
            onRemove: () => void removeImage(),
        },
        currentImageUrl: imageUrl,
    };
}

function ProfileSidebar({
    activeNav,
    labels,
    layout,
    profile,
    avatarEditor,
    cartCount,
}: {
    activeNav: LearnerNavKey;
    labels: Labels;
    layout?: LearnerLayoutContext;
    profile: LearnerLayoutUser;
    avatarEditor?: LearnerAvatarEditor;
    cartCount?: number;
}) {
    const [isAvatarModalOpen, setIsAvatarModalOpen] = useState(false);
    const avatarButtonLabel = label(labels, "changeprofileimage");

    // The profile page supplies its own avatar editor; on every other learner page
    // fall back to the shared sidebar editor so the camera button still opens the
    // change-photo modal (rather than being inert).
    const userid = profile.id ?? layout?.user?.id ?? 0;
    const savemethod = layout?.avatar?.savemethod ?? "";
    const sidebarAvatar = useSidebarAvatarEditor({
        savemethod,
        userid,
        canEdit: profile.canedit ?? layout?.user?.canedit ?? false,
        editMessage: profile.editmessage ?? layout?.user?.editmessage,
        initialImageUrl: profile.avatarurl || "",
        labels,
    });
    const fallbackAvailable = savemethod !== "" && userid > 0;
    const effectiveEditor = avatarEditor ?? (fallbackAvailable ? sidebarAvatar.editor : undefined);
    const avatarImageUrl = avatarEditor ? profile.avatarurl : (sidebarAvatar.currentImageUrl || profile.avatarurl);

    return (
        <aside
            className={mcClasses("mc-modern-learner-sidebar")}
            aria-label={label(labels, "learnernavigation")}
        >
            <div className={mcClasses("mc-modern-profile")}>
                <span className={mcClasses("mc-modern-avatar-wrap")}>
                    {avatarImageUrl ? (
                        <img src={avatarImageUrl} alt={profile.fullname} className={mcClasses("mc-modern-avatar")} />
                    ) : (
                        <span className={mcClasses("mc-modern-avatar mc-modern-avatar--initials")} aria-hidden="true">
                            {profile.initials}
                        </span>
                    )}
                    {effectiveEditor ? (
                        <button
                            type="button"
                            className={mcClasses("mc-modern-avatar-edit")}
                            aria-label={avatarButtonLabel}
                            title={avatarButtonLabel}
                            onClick={() => setIsAvatarModalOpen(true)}
                        >
                            <i className="bi bi-camera-fill" aria-hidden="true" />
                        </button>
                    ) : (
                        <span className={mcClasses("mc-modern-avatar-edit")} aria-hidden="true">
                            <i className="bi bi-camera-fill" />
                        </span>
                    )}
                </span>
                <h2>{profile.fullname}</h2>
                {profile.membersince && <p>{profile.membersince}</p>}
            </div>

            <nav className={mcClasses("mc-modern-side-nav")} aria-label={label(labels, "learnernavigation")}>
                {navItems(labels, layout, cartCount).map((item) => {
                    // Checkout has no nav item of its own; it is a step within the cart
                    // flow, so the Cart item stays highlighted while checking out.
                    const isActive = item.key === activeNav || (item.key === "cart" && activeNav === "checkout");

                    return (
                        <a
                            className={mcClasses("mc-modern-side-nav__link", isActive && "active")}
                            href={item.href}
                            aria-current={isActive ? "page" : undefined}
                            key={item.key}
                        >
                            <i className={`bi ${item.icon}`} aria-hidden="true" />
                            <span>{item.labelText}</span>
                            {item.badge !== undefined && (
                                <span
                                    className={mcClasses("mc-modern-side-nav__badge")}
                                    aria-label={`${item.badge} ${label(labels, "cartitems")}`}
                                >
                                    {item.badge > 99 ? "99+" : item.badge}
                                </span>
                            )}
                        </a>
                    );
                })}
            </nav>
            {effectiveEditor && isAvatarModalOpen && (
                <AvatarEditorModal
                    editor={effectiveEditor}
                    labels={labels}
                    profile={profile}
                    onClose={() => setIsAvatarModalOpen(false)}
                />
            )}
        </aside>
    );
}

function AvatarEditorModal({
    editor,
    labels,
    profile,
    onClose,
}: {
    editor: LearnerAvatarEditor;
    labels: Labels;
    profile: LearnerLayoutUser;
    onClose: () => void;
}) {
    const title = label(labels, "changeprofileimage");
    const previewUrl = editor.imagePreview || editor.imageUrl || profile.avatarurl || "";
    const feedbackClassName = editor.feedbackType === "danger" ? "mc-alert--danger" : "mc-alert--success";

    useEffect(() => {
        const handleKeydown = (event: KeyboardEvent) => {
            if (event.key === "Escape") {
                onClose();
            }
        };

        document.addEventListener("keydown", handleKeydown);
        return () => document.removeEventListener("keydown", handleKeydown);
    }, [onClose]);

    return (
        <div className={mcClasses("mc-avatar-modal-backdrop")} role="presentation" onMouseDown={onClose}>
            <section
                className={mcClasses("mc-avatar-modal")}
                role="dialog"
                aria-modal="true"
                aria-labelledby="mc-avatar-modal-title"
                onMouseDown={(event) => event.stopPropagation()}
            >
                <header className={mcClasses("mc-avatar-modal__header")}>
                    <div>
                        <h2 id="mc-avatar-modal-title">{title}</h2>
                        <p>{label(labels, "profileimagedesc")}</p>
                    </div>
                    <button
                        type="button"
                        className={mcClasses("mc-button mc-btn-icon")}
                        aria-label={label(labels, "close")}
                        onClick={onClose}
                    >
                        <i className="bi bi-x-lg" aria-hidden="true" />
                    </button>
                </header>
                <div className={mcClasses("mc-avatar-modal__body")}>
                    {editor.feedbackMessage && (
                        <div className={mcClasses("mc-alert", feedbackClassName)} role="status">
                            <i
                                className={mcClasses(
                                    "bi",
                                    editor.feedbackType === "danger" ? "bi-exclamation-triangle" : "bi-check-circle",
                                    "mc-alert__icon"
                                )}
                                aria-hidden="true"
                            />
                            <div className={mcClasses("mc-alert__body")}>{editor.feedbackMessage}</div>
                        </div>
                    )}
                    {editor.canEdit ? (
                        <>
                            <div className={mcClasses("mc-avatar-modal__preview")}>
                                {previewUrl ? (
                                    <img src={previewUrl} alt={profile.fullname} />
                                ) : (
                                    <span aria-hidden="true">{profile.initials}</span>
                                )}
                            </div>
                            <div>
                                <label className={mcClasses("mc-field-label")} htmlFor="mc-avatar-modal-file">
                                    {label(labels, "selectprofileimage")}
                                </label>
                                <input
                                    id="mc-avatar-modal-file"
                                    className="form-control"
                                    type="file"
                                    accept="image/*"
                                    disabled={editor.imageBusy}
                                    onChange={(event) => editor.onFile(event.currentTarget.files?.[0] ?? null)}
                                />
                                <FieldError message={editor.imageErrors.profileimage} />
                            </div>
                        </>
                    ) : (
                        <div className={mcClasses("mc-alert mc-alert--info")} role="status">
                            <i className="bi bi-info-circle mc-alert__icon" aria-hidden="true" />
                            <div className={mcClasses("mc-alert__body")}>{editor.editMessage}</div>
                        </div>
                    )}
                </div>
                <footer className={mcClasses("mc-avatar-modal__footer")}>
                    {editor.canEdit && (
                        <button
                            type="button"
                            className={mcClasses("mc-button btn-mc-secondary mc-avatar-modal__remove")}
                            disabled={editor.imageBusy}
                            onClick={editor.onRemove}
                        >
                            <i className="bi bi-trash" aria-hidden="true" />
                            {label(labels, "removeprofileimage")}
                        </button>
                    )}
                    <button type="button" className={mcClasses("mc-button btn-mc-secondary")} onClick={onClose}>
                        {label(labels, "cancel")}
                    </button>
                    {editor.canEdit && (
                        <button
                            type="button"
                            className={mcClasses("mc-button btn-mc-primary")}
                            disabled={editor.imageBusy}
                            onClick={editor.onUpload}
                        >
                            {editor.imageBusy
                                ? label(labels, "saving")
                                : label(labels, "uploadprofileimage")}
                        </button>
                    )}
                </footer>
            </section>
        </div>
    );
}

export function welcomeTitle(profile: LearnerLayoutUser, labels: Labels): string {
    return `${label(labels, "welcomeback")}, ${firstName(profile.fullname)}`;
}

export default function ModernLearnerLayout({
    activeNav,
    title,
    subtitle,
    eyebrow,
    labels,
    layout,
    profile,
    avatarEditor,
    actions,
    cartCount,
    children,
}: ModernLearnerLayoutProps) {
    const resolvedLabels = learnerLabels(labels, layout);
    const resolvedProfile = profile ?? layout?.user ?? defaultProfile(resolvedLabels);

    return (
        <div className={mcClasses("mc-modern-learner-dashboard mc-modern-learner-page")}>
            <HeroBand title={title} subtitle={subtitle} eyebrow={eyebrow} labels={resolvedLabels} />
            <div className={mcClasses("mc-modern-learner-shell")}>
                <ProfileSidebar
                    activeNav={activeNav}
                    labels={resolvedLabels}
                    layout={layout}
                    profile={resolvedProfile}
                    avatarEditor={avatarEditor}
                    cartCount={cartCount}
                />
                <main className={mcClasses("mc-modern-learner-main")}>
                    {actions && <div className={mcClasses("mc-modern-page-actions")}>{actions}</div>}
                    {children}
                </main>
            </div>
        </div>
    );
}

// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React public catalog page for Modern Commerce.
 *
 * @module     local_moderncommerce/catalog
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties, Dispatch, FormEvent, ReactNode, useEffect, useState} from "react";
import {callMoodleService, formatCount, Labels, refreshNavbarCart} from "./learner_common";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import {McTablePagination} from "./table_components";

type CatalogItem = {
    id: number;
    productid: number;
    inwishlist: boolean;
    itemtype: string;
    title: string;
    thumbnail: string;
    alt: string;
    category: string;
    categoryid: number;
    coursetype: string;
    level: string;
    duration: string;
    rating: number;
    reviewcount: number;
    price: number;
    originalprice: number;
    displayprice: string;
    displayoriginalprice: string;
    hasoriginalprice: boolean;
    isbundle: boolean;
    isprogram: boolean;
    bestseller: boolean;
    hasaccess: boolean;
    accessurl: string;
    detailsurl: string;
};

type CatalogFilters = {
    search: string;
    coursetype: string;
    categoryid: number;
    level: string;
    minprice: number;
    maxprice: number;
    sort: string;
    page: number;
    perpage: number;
};

type Option = {
    value: string;
    label: string;
};

type CategoryOption = {
    id: number;
    name: string;
};

type CatalogResponse = {
    items: CatalogItem[];
    total: number;
    page: number;
    perpage: number;
    totalpages: number;
    hasmore: boolean;
    filters: Omit<CatalogFilters, "sort" | "page" | "perpage">;
    filteroptions: {
        categories: CategoryOption[];
        coursetypes: Option[];
        levels: Option[];
        maxprice: number;
    };
    state: {
        isloggedin: boolean;
    };
    urls: {
        catalog: string;
        cart: string;
        login: string;
        register: string;
    };
};

type CartResponse = {
    success: boolean;
    message: string;
};

type CatalogProps = {
    methodName: string;
    cartMethodName: string;
    wishlistUpdateMethodName?: string;
    initialFilters: CatalogFilters;
    displaySettings?: Partial<CatalogDisplaySettings>;
    labels: Labels;
    syncUrlEnabled?: boolean;
};

type CatalogDisplaySettings = {
    title: string;
    showheader: boolean;
    perpage: number;
    sidebarposition: "left" | "right";
    bgcolor: string;
    herobgcolor: string;
    herobordercolor: string;
    heroradius: number;
    eyebrowcolor: string;
    titlecolor: string;
    titlefontsize: number;
    textcolor: string;
    textfontsize: number;
    accentcolor: string;
    heropanelbgcolor: string;
    heropanelbordercolor: string;
    heropaneltextcolor: string;
    heropanelaccentcolor: string;
    heropanelvaluecolor: string;
    heropanelvaluefontsize: number;
    cardbgcolor: string;
    cardbordercolor: string;
    cardborderwidth: number;
    cardradius: number;
    cardfooterbgcolor: string;
    cardtitlecolor: string;
    cardtitlefontsize: number;
    cardtextcolor: string;
    cardmetabgcolor: string;
    cardmetatextcolor: string;
    ratingcolor: string;
    ratingtextcolor: string;
    originalpricecolor: string;
    buttoncolor: string;
    buttontextcolor: string;
    buttonradius: number;
    badgebgcolor: string;
    badgebordercolor: string;
    badgetextcolor: string;
    badgeradius: number;
    badgefontsize: number;
    coursebadgebgcolor: string;
    coursebadgebordercolor: string;
    coursebadgetextcolor: string;
    programbadgebgcolor: string;
    programbadgebordercolor: string;
    programbadgetextcolor: string;
    bundlebadgebgcolor: string;
    bundlebadgebordercolor: string;
    bundlebadgetextcolor: string;
    filterbgcolor: string;
    filterbordercolor: string;
    filterborderwidth: number;
    filterradius: number;
    filtertitlecolor: string;
    filtertextcolor: string;
    inputbgcolor: string;
    inputbordercolor: string;
    inputtextcolor: string;
    placeholdercolor: string;
    tabbgcolor: string;
    tabbordercolor: string;
    tabtextcolor: string;
    tabactivebgcolor: string;
    tabactivetextcolor: string;
    pricecolor: string;
    paddingtop: number;
    paddingbottom: number;
    paddingleft: number;
    paddingright: number;
    margintop: number;
    marginbottom: number;
};

const label = (labels: Labels, key: string, fallback = ""): string => labels[key] || fallback || key;
const formatMoodleLabel = (template: string, replacements: Record<string, string | number>): string => (
    Object.entries(replacements).reduce(
        (text, [key, value]) => text.split(`{$a->${key}}`).join(String(value)),
        template
    )
);
const settingText = (value: unknown): string => String(value ?? "").trim();
const settingNumber = (value: unknown, fallback = 0): number => {
    const parsed = Number(value ?? fallback);
    return Number.isFinite(parsed) ? Math.max(0, parsed) : fallback;
};

const displaySettingsDefaults = (
    settings: Partial<CatalogDisplaySettings> | undefined,
    initialFilters: CatalogFilters
): CatalogDisplaySettings => {
    const configuredPerPage = Math.max(1, Number(settings?.perpage ?? initialFilters.perpage ?? 12));

    return {
        title: settings?.title || "",
        showheader: settings?.showheader !== false,
        perpage: configuredPerPage,
        sidebarposition: settings?.sidebarposition === "right" ? "right" : "left",
        bgcolor: settingText(settings?.bgcolor),
        herobgcolor: settingText(settings?.herobgcolor),
        herobordercolor: settingText(settings?.herobordercolor),
        heroradius: settingNumber(settings?.heroradius, 8),
        eyebrowcolor: settingText(settings?.eyebrowcolor),
        titlecolor: settingText(settings?.titlecolor),
        titlefontsize: settingNumber(settings?.titlefontsize),
        textcolor: settingText(settings?.textcolor),
        textfontsize: settingNumber(settings?.textfontsize),
        accentcolor: settingText(settings?.accentcolor),
        heropanelbgcolor: settingText(settings?.heropanelbgcolor),
        heropanelbordercolor: settingText(settings?.heropanelbordercolor),
        heropaneltextcolor: settingText(settings?.heropaneltextcolor),
        heropanelaccentcolor: settingText(settings?.heropanelaccentcolor),
        heropanelvaluecolor: settingText(settings?.heropanelvaluecolor),
        heropanelvaluefontsize: settingNumber(settings?.heropanelvaluefontsize),
        cardbgcolor: settingText(settings?.cardbgcolor),
        cardbordercolor: settingText(settings?.cardbordercolor),
        cardborderwidth: settingNumber(settings?.cardborderwidth, 1),
        cardradius: settingNumber(settings?.cardradius, 8),
        cardfooterbgcolor: settingText(settings?.cardfooterbgcolor),
        cardtitlecolor: settingText(settings?.cardtitlecolor),
        cardtitlefontsize: settingNumber(settings?.cardtitlefontsize),
        cardtextcolor: settingText(settings?.cardtextcolor),
        cardmetabgcolor: settingText(settings?.cardmetabgcolor),
        cardmetatextcolor: settingText(settings?.cardmetatextcolor),
        ratingcolor: settingText(settings?.ratingcolor),
        ratingtextcolor: settingText(settings?.ratingtextcolor),
        originalpricecolor: settingText(settings?.originalpricecolor),
        buttoncolor: settingText(settings?.buttoncolor),
        buttontextcolor: settingText(settings?.buttontextcolor),
        buttonradius: settingNumber(settings?.buttonradius),
        badgebgcolor: settingText(settings?.badgebgcolor),
        badgebordercolor: settingText(settings?.badgebordercolor),
        badgetextcolor: settingText(settings?.badgetextcolor),
        badgeradius: settingNumber(settings?.badgeradius, 6),
        badgefontsize: settingNumber(settings?.badgefontsize),
        coursebadgebgcolor: settingText(settings?.coursebadgebgcolor),
        coursebadgebordercolor: settingText(settings?.coursebadgebordercolor),
        coursebadgetextcolor: settingText(settings?.coursebadgetextcolor),
        programbadgebgcolor: settingText(settings?.programbadgebgcolor),
        programbadgebordercolor: settingText(settings?.programbadgebordercolor),
        programbadgetextcolor: settingText(settings?.programbadgetextcolor),
        bundlebadgebgcolor: settingText(settings?.bundlebadgebgcolor),
        bundlebadgebordercolor: settingText(settings?.bundlebadgebordercolor),
        bundlebadgetextcolor: settingText(settings?.bundlebadgetextcolor),
        filterbgcolor: settingText(settings?.filterbgcolor),
        filterbordercolor: settingText(settings?.filterbordercolor),
        filterborderwidth: settingNumber(settings?.filterborderwidth, 1),
        filterradius: settingNumber(settings?.filterradius, 8),
        filtertitlecolor: settingText(settings?.filtertitlecolor),
        filtertextcolor: settingText(settings?.filtertextcolor),
        inputbgcolor: settingText(settings?.inputbgcolor),
        inputbordercolor: settingText(settings?.inputbordercolor),
        inputtextcolor: settingText(settings?.inputtextcolor),
        placeholdercolor: settingText(settings?.placeholdercolor),
        tabbgcolor: settingText(settings?.tabbgcolor),
        tabbordercolor: settingText(settings?.tabbordercolor),
        tabtextcolor: settingText(settings?.tabtextcolor),
        tabactivebgcolor: settingText(settings?.tabactivebgcolor),
        tabactivetextcolor: settingText(settings?.tabactivetextcolor),
        pricecolor: settingText(settings?.pricecolor),
        paddingtop: settingNumber(settings?.paddingtop),
        paddingbottom: settingNumber(settings?.paddingbottom),
        paddingleft: settingNumber(settings?.paddingleft),
        paddingright: settingNumber(settings?.paddingright),
        margintop: settingNumber(settings?.margintop),
        marginbottom: settingNumber(settings?.marginbottom),
    };
};

const catalogSectionStyle = (settings: CatalogDisplaySettings): CSSProperties | undefined => {
    const style: CSSProperties & Record<string, string | number> = {};

    if (settings.margintop > 0) {
        style.marginTop = settings.margintop;
    }
    if (settings.marginbottom > 0) {
        style.marginBottom = settings.marginbottom;
    }
    if (settings.paddingtop > 0) {
        style["--mc-catalog-padding-top"] = `${settings.paddingtop}px`;
    }
    if (settings.paddingbottom > 0) {
        style["--mc-catalog-padding-bottom"] = `${settings.paddingbottom}px`;
    }
    if (settings.paddingleft > 0) {
        style["--mc-catalog-padding-left"] = `${settings.paddingleft}px`;
    }
    if (settings.paddingright > 0) {
        style["--mc-catalog-padding-right"] = `${settings.paddingright}px`;
    }
    if (settings.bgcolor) {
        style.backgroundColor = settings.bgcolor;
        style["--mc-catalog-bg"] = settings.bgcolor;
    }
    if (settings.herobgcolor) {
        style["--mc-catalog-hero-bg"] = settings.herobgcolor;
    }
    if (settings.herobordercolor) {
        style["--mc-catalog-hero-border"] = settings.herobordercolor;
    }
    if (settings.heroradius >= 0) {
        style["--mc-catalog-hero-radius"] = `${settings.heroradius}px`;
    }
    if (settings.eyebrowcolor) {
        style["--mc-catalog-eyebrow-color"] = settings.eyebrowcolor;
    }
    if (settings.titlecolor) {
        style["--mc-catalog-title-color"] = settings.titlecolor;
    }
    if (settings.titlefontsize > 0) {
        style["--mc-catalog-title-font-size"] = `${settings.titlefontsize}px`;
    }
    if (settings.textcolor) {
        style["--mc-catalog-text-color"] = settings.textcolor;
    }
    if (settings.textfontsize > 0) {
        style["--mc-catalog-text-font-size"] = `${settings.textfontsize}px`;
    }
    if (settings.accentcolor) {
        style["--mc-catalog-accent"] = settings.accentcolor;
    }
    if (settings.heropanelbgcolor) {
        style["--mc-catalog-hero-panel-bg"] = settings.heropanelbgcolor;
    }
    if (settings.heropanelbordercolor) {
        style["--mc-catalog-hero-panel-border"] = settings.heropanelbordercolor;
    }
    if (settings.heropaneltextcolor) {
        style["--mc-catalog-hero-panel-text"] = settings.heropaneltextcolor;
    }
    if (settings.heropanelaccentcolor) {
        style["--mc-catalog-hero-panel-accent"] = settings.heropanelaccentcolor;
    }
    if (settings.heropanelvaluecolor) {
        style["--mc-catalog-hero-panel-value"] = settings.heropanelvaluecolor;
    }
    if (settings.heropanelvaluefontsize > 0) {
        style["--mc-catalog-hero-panel-value-font-size"] = `${settings.heropanelvaluefontsize}px`;
    }
    if (settings.cardbgcolor) {
        style["--mc-catalog-card-bg"] = settings.cardbgcolor;
    }
    if (settings.cardbordercolor) {
        style["--mc-catalog-card-border"] = settings.cardbordercolor;
    }
    if (settings.cardborderwidth >= 0) {
        style["--mc-catalog-card-border-width"] = `${settings.cardborderwidth}px`;
    }
    if (settings.cardradius >= 0) {
        style["--mc-catalog-card-radius"] = `${settings.cardradius}px`;
    }
    if (settings.cardfooterbgcolor) {
        style["--mc-catalog-card-footer-bg"] = settings.cardfooterbgcolor;
    }
    if (settings.cardtitlecolor) {
        style["--mc-catalog-card-title-color"] = settings.cardtitlecolor;
    }
    if (settings.cardtitlefontsize > 0) {
        style["--mc-catalog-card-title-font-size"] = `${settings.cardtitlefontsize}px`;
    }
    if (settings.cardtextcolor) {
        style["--mc-catalog-card-text-color"] = settings.cardtextcolor;
    }
    if (settings.cardmetabgcolor) {
        style["--mc-catalog-card-meta-bg"] = settings.cardmetabgcolor;
    }
    if (settings.cardmetatextcolor) {
        style["--mc-catalog-card-meta-text"] = settings.cardmetatextcolor;
    }
    if (settings.ratingcolor) {
        style["--mc-catalog-rating-color"] = settings.ratingcolor;
    }
    if (settings.ratingtextcolor) {
        style["--mc-catalog-rating-text"] = settings.ratingtextcolor;
    }
    if (settings.originalpricecolor) {
        style["--mc-catalog-original-price-color"] = settings.originalpricecolor;
    }
    if (settings.buttoncolor) {
        style["--mc-catalog-button-bg"] = settings.buttoncolor;
    }
    if (settings.buttontextcolor) {
        style["--mc-catalog-button-text"] = settings.buttontextcolor;
    }
    if (settings.buttonradius >= 0) {
        style["--mc-catalog-button-radius"] = `${settings.buttonradius}px`;
    }
    if (settings.badgebgcolor) {
        style["--mc-catalog-badge-bg"] = settings.badgebgcolor;
    }
    if (settings.badgebordercolor) {
        style["--mc-catalog-badge-border"] = settings.badgebordercolor;
    }
    if (settings.badgetextcolor) {
        style["--mc-catalog-badge-text"] = settings.badgetextcolor;
    }
    if (settings.badgeradius >= 0) {
        style["--mc-catalog-badge-radius"] = `${settings.badgeradius}px`;
    }
    if (settings.badgefontsize > 0) {
        style["--mc-catalog-badge-font-size"] = `${settings.badgefontsize}px`;
    }
    if (settings.coursebadgebgcolor) {
        style["--mc-catalog-course-badge-bg"] = settings.coursebadgebgcolor;
    }
    if (settings.coursebadgebordercolor) {
        style["--mc-catalog-course-badge-border"] = settings.coursebadgebordercolor;
    }
    if (settings.coursebadgetextcolor) {
        style["--mc-catalog-course-badge-text"] = settings.coursebadgetextcolor;
    }
    if (settings.programbadgebgcolor) {
        style["--mc-catalog-program-badge-bg"] = settings.programbadgebgcolor;
    }
    if (settings.programbadgebordercolor) {
        style["--mc-catalog-program-badge-border"] = settings.programbadgebordercolor;
    }
    if (settings.programbadgetextcolor) {
        style["--mc-catalog-program-badge-text"] = settings.programbadgetextcolor;
    }
    if (settings.bundlebadgebgcolor) {
        style["--mc-catalog-bundle-badge-bg"] = settings.bundlebadgebgcolor;
    }
    if (settings.bundlebadgebordercolor) {
        style["--mc-catalog-bundle-badge-border"] = settings.bundlebadgebordercolor;
    }
    if (settings.bundlebadgetextcolor) {
        style["--mc-catalog-bundle-badge-text"] = settings.bundlebadgetextcolor;
    }
    if (settings.filterbgcolor) {
        style["--mc-catalog-filter-bg"] = settings.filterbgcolor;
    }
    if (settings.filterbordercolor) {
        style["--mc-catalog-filter-border"] = settings.filterbordercolor;
    }
    if (settings.filterborderwidth >= 0) {
        style["--mc-catalog-filter-border-width"] = `${settings.filterborderwidth}px`;
    }
    if (settings.filterradius >= 0) {
        style["--mc-catalog-filter-radius"] = `${settings.filterradius}px`;
    }
    if (settings.filtertitlecolor) {
        style["--mc-catalog-filter-title"] = settings.filtertitlecolor;
    }
    if (settings.filtertextcolor) {
        style["--mc-catalog-filter-text"] = settings.filtertextcolor;
    }
    if (settings.inputbgcolor) {
        style["--mc-catalog-input-bg"] = settings.inputbgcolor;
    }
    if (settings.inputbordercolor) {
        style["--mc-catalog-input-border"] = settings.inputbordercolor;
    }
    if (settings.inputtextcolor) {
        style["--mc-catalog-input-text"] = settings.inputtextcolor;
    }
    if (settings.placeholdercolor) {
        style["--mc-catalog-input-placeholder"] = settings.placeholdercolor;
    }
    if (settings.tabbgcolor) {
        style["--mc-catalog-tab-bg"] = settings.tabbgcolor;
    }
    if (settings.tabbordercolor) {
        style["--mc-catalog-tab-border"] = settings.tabbordercolor;
    }
    if (settings.tabtextcolor) {
        style["--mc-catalog-tab-text"] = settings.tabtextcolor;
    }
    if (settings.tabactivebgcolor) {
        style["--mc-catalog-tab-active-bg"] = settings.tabactivebgcolor;
    }
    if (settings.tabactivetextcolor) {
        style["--mc-catalog-tab-active-text"] = settings.tabactivetextcolor;
    }
    if (settings.pricecolor) {
        style["--mc-catalog-price-color"] = settings.pricecolor;
    }

    return Object.keys(style).length > 0 ? style : undefined;
};

// Hero background is intentionally separate from the section background.
const catalogHeroStyle = (settings: CatalogDisplaySettings): CSSProperties | undefined => (
    settings.herobgcolor ? {backgroundColor: settings.herobgcolor} : undefined
);

const perPageOptions = (configured: number, current: number): number[] => (
    Array.from(new Set([6, 12, 18, 24, 30, 36, configured, current]))
        .filter((value) => Number.isFinite(value) && value > 0)
        .sort((a, b) => a - b)
);

const defaultFilters = (initialFilters: CatalogFilters): CatalogFilters => ({
    search: initialFilters.search ?? "",
    coursetype: initialFilters.coursetype ?? "",
    categoryid: Number(initialFilters.categoryid ?? 0),
    level: initialFilters.level ?? "",
    minprice: Number(initialFilters.minprice ?? 0),
    maxprice: Number(initialFilters.maxprice ?? 0),
    sort: initialFilters.sort || "popular",
    page: Math.max(0, Number(initialFilters.page ?? 0)),
    perpage: Math.max(1, Number(initialFilters.perpage ?? 12)),
});

// syncUrl() writes the active filters into the query string; read them back so a shared or
// bookmarked catalog link - including a category tile's ?categoryid=N - opens already filtered.
const filtersFromUrl = (base: CatalogFilters): CatalogFilters => {
    if (typeof window === "undefined") {
        return base;
    }

    const params = new URLSearchParams(window.location.search);
    if (![...params.keys()].length) {
        return base;
    }

    const text = (key: string, fallback: string): string => (
        params.has(key) ? String(params.get(key)) : fallback
    );
    const number = (key: string, fallback: number, min: number): number => {
        if (!params.has(key)) {
            return fallback;
        }
        const parsed = Number(params.get(key));
        return Number.isFinite(parsed) ? Math.max(min, parsed) : fallback;
    };

    return {
        search: text("search", base.search),
        coursetype: text("coursetype", base.coursetype),
        categoryid: number("categoryid", base.categoryid, 0),
        level: text("level", base.level),
        minprice: number("minprice", base.minprice, 0),
        maxprice: number("maxprice", base.maxprice, 0),
        sort: text("sort", base.sort) || "popular",
        page: number("page", base.page, 0),
        perpage: number("perpage", base.perpage, 1),
    };
};

const filtersForService = (filters: CatalogFilters) => ({
    search: filters.search,
    coursetype: filters.coursetype,
    categoryid: filters.categoryid,
    level: filters.level,
    minprice: filters.minprice,
    maxprice: filters.maxprice,
    sort: filters.sort,
    page: filters.page,
    perpage: filters.perpage,
});

const itemTypeLabel = (item: CatalogItem, labels: Labels): string => {
    if (item.isprogram) {
        return labels.program;
    }
    if (item.isbundle) {
        return labels.bundle;
    }
    return labels.course;
};

const itemBadgeClass = (item: CatalogItem): string => {
    if (item.isprogram) {
        return "mc-badge-program";
    }
    if (item.isbundle) {
        return "mc-badge-bundle";
    }
    return "mc-badge-course mc-badge--neutral";
};

const accessAction = (item: CatalogItem, labels: Labels): {label: string; icon: string} => {
    const itemType = item.itemtype.toLowerCase();

    if (itemType === "course") {
        return {
            label: labels.viewcourse || "View course",
            icon: "bi-play-circle",
        };
    }

    if (item.isprogram) {
        return {
            label: labels.viewincludedcourses || "View included courses",
            icon: "bi-list-check",
        };
    }

    if (item.isbundle) {
        return {
            label: labels.viewbundle || "View bundle",
            icon: "bi-layers",
        };
    }

    return {
        label: labels.viewaccesslibrary || "View access library",
        icon: "bi-unlock",
    };
};

const syncUrl = (filters: CatalogFilters, defaultPerPage: number) => {
    const params = new URLSearchParams();

    Object.entries(filtersForService(filters)).forEach(([key, value]) => {
        if (value === "" || value === 0 || (key === "sort" && value === "popular")) {
            return;
        }
        if (key === "perpage" && Number(value) === defaultPerPage) {
            return;
        }
        params.set(key, String(value));
    });

    const query = params.toString();
    const nextUrl = query ? `${location.pathname}?${query}` : location.pathname;
    window.history.replaceState({}, "", nextUrl);
};

const activeFilterCount = (filters: CatalogFilters): number => (
    Number(Boolean(filters.search)) +
    Number(Boolean(filters.coursetype)) +
    Number(filters.categoryid > 0) +
    Number(Boolean(filters.level)) +
    Number(filters.minprice > 0) +
    Number(filters.maxprice > 0)
);

function FilterSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className={mcClasses("mc-filter-section")}>
            <div className={mcClasses("mc-filter-section-header")}>
                <span>{title}</span>
            </div>
            <div className={mcClasses("mc-filter-section-body")}>{children}</div>
        </section>
    );
}

function CatalogHero({
    labels,
    title,
    total,
    draft,
    loading,
    style,
    onSearchChange,
    onSearchSubmit,
    onFilterToggle,
}: {
    labels: Labels;
    title: string;
    total: number;
    draft: CatalogFilters;
    loading: boolean;
    style?: CSSProperties;
    onSearchChange: Dispatch<string>;
    onSearchSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onFilterToggle: () => void;
}) {
    return (
        <section className={mcClasses("mc-catalog-hero")} style={style} aria-labelledby="mc-catalog-hero-title">
            <div className={mcClasses("mc-catalog-hero__copy")}>
                <span className={mcClasses("mc-catalog-eyebrow")}>
                    {label(labels, "catalog_eyebrow")}
                </span>
                <h1 id="mc-catalog-hero-title">{title}</h1>
                <p>{label(
                    labels,
                    "catalog_intro",
                    "Find practical courses, bundles, and programs with secure checkout and immediate learner access."
                )}</p>
                <form className={mcClasses("mc-catalog-hero-search")} onSubmit={onSearchSubmit}>
                    <i className="bi bi-search" aria-hidden="true" />
                    <input
                        type="search"
                        value={draft.search}
                        placeholder={labels.catalog_search_placeholder}
                        onChange={(event) => onSearchChange(event.currentTarget.value)}
                    />
                    <button type="submit" className={mcClasses("mc-button btn-mc-primary")} disabled={loading}>
                        {labels.catalog_show_results}
                    </button>
                </form>
            </div>
            <div className={mcClasses("mc-catalog-hero__panel")} aria-label={labels.catalog}>
                <div>
                    <span>{label(labels, "catalog_results")}</span>
                    <strong>{formatCount(total)}</strong>
                </div>
                <div>
                    <span>{label(labels, "catalog_trust_secure")}</span>
                    <i className="bi bi-shield-check" aria-hidden="true" />
                </div>
                <div>
                    <span>{label(labels, "catalog_trust_certificates")}</span>
                    <i className="bi bi-patch-check" aria-hidden="true" />
                </div>
                <div>
                    <span>{label(labels, "catalog_trust_access")}</span>
                    <i className="bi bi-unlock" aria-hidden="true" />
                </div>
                <button
                    type="button"
                    className={mcClasses("mc-button mc-catalog-filter-toggle")}
                    data-mc-button="primary"
                    onClick={onFilterToggle}
                >
                    <i className="bi bi-sliders" aria-hidden="true" />
                    {label(labels, "catalog_filter_button")}
                </button>
            </div>
        </section>
    );
}

function TypeTabs({
    labels,
    options,
    selected,
    onSelect,
}: {
    labels: Labels;
    options: Option[];
    selected: string;
    onSelect: Dispatch<string>;
}) {
    const allLabel = label(labels, "catalog_all_products");

    return (
        <div className={mcClasses("mc-catalog-type-tabs")} role="group" aria-label={labels.catalog_coursetype_title}>
            <button
                type="button"
                className={mcClasses("mc-button mc-catalog-type-tab", selected === "" && "active")}
                data-mc-button={selected === "" ? "primary" : "light"}
                aria-pressed={selected === ""}
                onClick={() => onSelect("")}
            >
                {allLabel}
            </button>
            {options.map((option) => (
                <button
                    type="button"
                    className={mcClasses("mc-button mc-catalog-type-tab", selected === option.value && "active")}
                    data-mc-button={selected === option.value ? "primary" : "light"}
                    aria-pressed={selected === option.value}
                    onClick={() => onSelect(option.value)}
                    key={option.value}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}

function LoginNotice({
    data,
    labels,
}: {
    data: CatalogResponse | null;
    labels: Labels;
}) {
    if (!data || data.state.isloggedin) {
        return null;
    }

    return (
        <div className={mcClasses("mc-alert mc-alert--info")} role="status">
            <i className="bi bi-info-circle mc-alert__icon" aria-hidden="true" />
            <div className={mcClasses("mc-alert__body d-flex flex-wrap align-items-center gap-2")}>
                <span className="me-auto">{labels.loginrequiredmessage}</span>
                <a className={mcClasses("mc-button mc-btn-soft")} href={data.urls.login}>
                    {labels.login}
                </a>
                <a className={mcClasses("mc-button btn-mc-primary")} href={data.urls.register}>
                    {labels.startsignup}
                </a>
            </div>
        </div>
    );
}

function RatingStars({rating}: {rating: number}) {
    const rounded = Math.round(rating);

    return (
        <span className="mc-rating-stars d-inline-flex align-items-center gap-1 text-warning" aria-hidden="true">
            {[1, 2, 3, 4, 5].map((star) => (
                <i className={`bi ${star <= rounded ? "bi-star-fill" : "bi-star"}`} key={star} />
            ))}
        </span>
    );
}

function CatalogCard({
    item,
    labels,
    busy,
    onAddToCart,
    wishlistBusy,
    wishlistSaved,
    onToggleWishlist,
}: {
    item: CatalogItem;
    labels: Labels;
    busy: boolean;
    onAddToCart: Dispatch<CatalogItem>;
    wishlistBusy: boolean;
    wishlistSaved: boolean;
    onToggleWishlist?: (item: CatalogItem, saved: boolean) => void;
}) {
    const typeLabel = itemTypeLabel(item, labels);
    const accessUrl = item.accessurl || "#";
    const ownedAction = accessAction(item, labels);
    const wishlistTitle = wishlistSaved
        ? label(labels, "removefromwishlist")
        : label(labels, "savetowishlist");
    const wishlistIcon = wishlistSaved ? "bi-heart-fill" : "bi-heart";

    return (
        <article className={mcClasses("mc-course-card", item.hasaccess && "mc-course-card--owned")}>
            <div className={mcClasses("mc-course-card-image")}>
                {item.thumbnail ? (
                    <img
                        src={item.thumbnail}
                        alt={item.alt || item.title}
                        width={480}
                        height={270}
                        loading="lazy"
                    />
                ) : (
                    <div className="w-100 h-100 bg-light" aria-hidden="true" />
                )}
                <div className={mcClasses("mc-course-card-badges")}>
                    <span
                        className={mcClasses(`mc-badge ${itemBadgeClass(item)}`)}
                    >
                        {typeLabel}
                    </span>
                    {item.bestseller && (
                        <span className={mcClasses("mc-badge mc-badge-bestseller")}>{labels.bestseller}</span>
                    )}
                </div>
                {onToggleWishlist && !item.hasaccess && (
                    <div className={mcClasses("mc-course-card-actions")}>
                        <button
                            type="button"
                            className={mcClasses("mc-button mc-course-card-wishlist")}
                            data-mc-button="ghost"
                            data-mc-button-size="icon"
                            aria-pressed={wishlistSaved}
                            disabled={wishlistBusy}
                            onClick={() => onToggleWishlist(item, wishlistSaved)}
                            aria-label={wishlistTitle}
                            title={wishlistTitle}
                        >
                            <i className={`bi ${wishlistIcon}`} aria-hidden="true" />
                        </button>
                    </div>
                )}
            </div>

            <div className={mcClasses("mc-course-card-body")}>
                <div className={mcClasses("mc-course-card-meta")}>
                    {item.category && <span className={mcClasses("mc-course-card-category")}>{item.category}</span>}
                    {item.duration && (
                        <span className={mcClasses("mc-course-card-duration")}>
                            <i className="bi bi-clock" aria-hidden="true" />
                            {item.duration}
                        </span>
                    )}
                </div>
                <h2 className={mcClasses("mc-course-card-title")}>
                    <a href={item.detailsurl} className="text-decoration-none text-reset">
                        {item.title}
                    </a>
                </h2>
                {item.level && <p className={mcClasses("mc-course-card-instructor")}>{item.level}</p>}
                <div className={mcClasses("mc-course-card-detailsrow")}>
                    <a href={item.detailsurl} className={mcClasses("mc-course-card-details")}>
                        {labels.viewdetails}
                        <i className="bi bi-arrow-right" aria-hidden="true" />
                    </a>
                    {item.reviewcount > 0 && (
                        <div className={mcClasses("mc-course-card-rating")}>
                            <RatingStars rating={item.rating} />
                            <span className={mcClasses("mc-rating-value")}>{item.rating.toFixed(1)}</span>
                            <span className={mcClasses("mc-rating-count")}>({formatCount(item.reviewcount)})</span>
                        </div>
                    )}
                </div>
            </div>

            <div className={mcClasses("mc-course-card-footer")}>
                <div className={mcClasses("mc-course-card-price")}>
                    <span className="fw-bold">{item.displayprice}</span>
                    {item.hasoriginalprice && (
                        <span className={mcClasses("mc-course-card-original-price")}>
                            {item.displayoriginalprice}
                        </span>
                    )}
                </div>
                {item.hasaccess ? (
                    <a className={mcClasses("mc-button btn-mc-secondary mc-course-card-btn")} href={accessUrl}>
                        <i className={`bi ${ownedAction.icon}`} aria-hidden="true" />
                        {ownedAction.label}
                    </a>
                ) : (
                    <McButton
                        type="button"
                        className={mcClasses("btn-mc-primary mc-course-card-btn")}
                        loading={busy}
                        loadingLabel={label(labels, "loading")}
                        onClick={() => onAddToCart(item)}
                    >
                        <i className="bi bi-cart-plus" aria-hidden="true" />
                        {label(labels, "catalog_add_to_cart_full")}
                    </McButton>
                )}
            </div>
        </article>
    );
}

function CatalogContent({
    loading,
    data,
    labels,
    busyItem,
    onAddToCart,
    onClearFilters,
    onPageChange,
    busyWishlistItem,
    savedWishlistProductIds,
    onToggleWishlist,
}: {
    loading: boolean;
    data: CatalogResponse | null;
    labels: Labels;
    busyItem: number;
    onAddToCart: Dispatch<CatalogItem>;
    onClearFilters: () => void;
    onPageChange: Dispatch<number>;
    busyWishlistItem: number;
    savedWishlistProductIds: Set<number>;
    onToggleWishlist?: (item: CatalogItem, saved: boolean) => void;
}) {
    if (loading && !data) {
        return (
            <div className={mcClasses("mc-empty mc-empty--centered")}>
                <span className={mcClasses("mc-empty__icon")}>
                    <i className="bi bi-grid" aria-hidden="true" />
                </span>
                <p className={mcClasses("mc-empty__title")}>{labels.loading}</p>
            </div>
        );
    }

    if (!data || data.items.length === 0) {
        return (
            <div className={mcClasses("mc-catalog-no-results")}>
                <div className={mcClasses("mc-no-results-icon")}>
                    <i className="bi bi-search" aria-hidden="true" />
                </div>
                <h2>{labels.catalog_no_courses}</h2>
                <button
                    type="button"
                    className={mcClasses("mc-button mc-btn-soft")}
                    onClick={onClearFilters}
                >
                    {labels.catalog_clear_filters}
                </button>
            </div>
        );
    }

    const canPrevious = data.page > 0;
    const canNext = data.page + 1 < data.totalpages;

    return (
        <>
            <div className={mcClasses("mc-catalog-grid")}>
                {data.items.map((item) => (
                    <CatalogCard
                        item={item}
                        labels={labels}
                        busy={busyItem === item.id}
                        onAddToCart={onAddToCart}
                        wishlistBusy={busyWishlistItem === item.productid}
                        wishlistSaved={item.inwishlist || savedWishlistProductIds.has(item.productid)}
                        onToggleWishlist={onToggleWishlist}
                        key={`${item.itemtype}-${item.id}`}
                    />
                ))}
            </div>

            <McTablePagination
                className="mt-3"
                previousLabel={labels.catalog_previous}
                nextLabel={labels.catalog_next}
                pageLabel={formatMoodleLabel(labels.catalog_page_x_of_y, {
                    page: formatCount(data.page + 1),
                    total: formatCount(data.totalpages),
                })}
                previousDisabled={!canPrevious || loading}
                nextDisabled={!canNext || loading}
                onPrevious={() => onPageChange(data.page - 1)}
                onNext={() => onPageChange(data.page + 1)}
            />
        </>
    );
}

export default function Catalog({
    methodName,
    cartMethodName,
    wishlistUpdateMethodName,
    initialFilters,
    displaySettings,
    labels,
    syncUrlEnabled = true,
}: CatalogProps) {
    useModernCommerceClassSync();
    const display = displaySettingsDefaults(displaySettings, initialFilters);
    const defaultPerPage = display.perpage;
    const catalogTitle = display.title || labels.catalog;
    // The URL wins over the widget's configured defaults, but only where the page owns the URL.
    const initial = syncUrlEnabled
        ? filtersFromUrl(defaultFilters(initialFilters))
        : defaultFilters(initialFilters);
    const [filters, setFilters] = useState<CatalogFilters>(initial);
    const [draft, setDraft] = useState<CatalogFilters>(initial);
    const [data, setData] = useState<CatalogResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [busyItem, setBusyItem] = useState(0);
    const [busyWishlistItem, setBusyWishlistItem] = useState(0);
    const [savedWishlistProductIds, setSavedWishlistProductIds] = useState<Set<number>>(new Set());

    useEffect(() => {
        document.body.classList.toggle("mc-sidebar-open", sidebarOpen);

        return () => {
            document.body.classList.remove("mc-sidebar-open");
        };
    }, [sidebarOpen]);

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");
        if (syncUrlEnabled) {
            syncUrl(filters, defaultPerPage);
        }

        callMoodleService<CatalogResponse>(methodName, filtersForService(filters))
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                    setDraft({...filters, ...result.filters});
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
    }, [defaultPerPage, filters, methodName, syncUrlEnabled]);

    const applyFilters = (next: Partial<CatalogFilters>) => {
        setFilters((current) => ({
            ...current,
            ...next,
            page: next.page ?? 0,
        }));
        setDraft((current) => ({
            ...current,
            ...next,
            page: next.page ?? 0,
        }));
        setMessage("");
    };

    const handleSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        applyFilters({
            search: draft.search,
            minprice: Number(draft.minprice) || 0,
            maxprice: Number(draft.maxprice) || 0,
        });
        setSidebarOpen(false);
    };

    const clearFilters = () => {
        const next = defaultFilters({
            ...initialFilters,
            search: "",
            coursetype: "",
            categoryid: 0,
            level: "",
            minprice: 0,
            maxprice: 0,
            page: 0,
        });
        setDraft(next);
        setFilters(next);
        setMessage("");
    };

    const toggleWishlist = async(item: CatalogItem, saved: boolean) => {
        if (!wishlistUpdateMethodName || item.productid <= 0) {
            return;
        }

        if (!data?.state.isloggedin) {
            setMessage(labels.loginrequiredmessage);
            return;
        }

        setBusyWishlistItem(item.productid);
        setError("");
        setMessage("");

        try {
            const result = await callMoodleService<{message: string}>(wishlistUpdateMethodName, {
                action: saved ? "remove" : "add",
                productid: item.productid,
            });
            setSavedWishlistProductIds((current) => {
                const next = new Set(current);
                if (saved) {
                    next.delete(item.productid);
                } else {
                    next.add(item.productid);
                }
                return next;
            });
            setData((current) => current ? {
                ...current,
                items: current.items.map((currentItem) => currentItem.productid === item.productid
                    ? {...currentItem, inwishlist: !saved}
                    : currentItem
                ),
            } : current);
            toast.success(result.message || label(labels, saved ? "wishlistremoved" : "wishlistadded"));
        } catch (caught) {
            setError((caught as Error).message);
        } finally {
            setBusyWishlistItem(0);
        }
    };

    const addToCart = async(item: CatalogItem) => {
        if (item.hasaccess) {
            window.location.assign(item.accessurl || data?.urls.catalog || location.href);
            return;
        }

        if (!data?.state.isloggedin) {
            setMessage(labels.loginrequiredmessage);
            return;
        }

        setBusyItem(item.id);
        setError("");
        setMessage("");

        try {
            const args = item.isbundle || item.isprogram
                ? {action: "addbundle", bundleid: item.id}
                : {action: "addcourse", courseid: item.id};
            const result = await callMoodleService<CartResponse>(cartMethodName, args);
            void refreshNavbarCart(result);
            setMessage(result.message);
        } catch (caught) {
            setError((caught as Error).message);
        } finally {
            setBusyItem(0);
        }
    };

    const total = data?.total ?? 0;
    const activeFilters = activeFilterCount(filters);
    const sidebarClassName = mcClasses(
        "mc-catalog-sidebar",
        sidebarOpen ? "mc-catalog-sidebar--open" : ""
    );
    const backdropClassName = mcClasses(
        "mc-catalog-backdrop",
        sidebarOpen ? "mc-catalog-backdrop--open" : ""
    );
    const noticeClassName = mcClasses(
        "mc-alert",
        error ? "mc-alert--danger" : "mc-alert--info"
    );
    const noticeIconClassName = mcClasses(
        "bi",
        error ? "bi-exclamation-triangle" : "bi-info-circle",
        "mc-alert__icon"
    );
    const sectionClassName = mcClasses(
        "mc-catalog-section",
        display.sidebarposition === "right" && "mc-sidebar-right"
    );
    const sectionStyle = catalogSectionStyle(display);
    const pageOptions = perPageOptions(display.perpage, filters.perpage);

    return (
        <div className={sectionClassName} style={sectionStyle}>
            <div className={mcClasses("mc-catalog-container")}>
                {display.showheader ? (
                    <CatalogHero
                        labels={labels}
                        title={catalogTitle}
                        total={total}
                        draft={draft}
                        loading={loading}
                        style={catalogHeroStyle(display)}
                        onSearchChange={(search) => setDraft({...draft, search})}
                        onSearchSubmit={handleSearch}
                        onFilterToggle={() => setSidebarOpen(true)}
                    />
                ) : (
                    /* The hero owns the only filter toggle, so a hidden header still needs
                       one for narrow screens where the sidebar is off-canvas. */
                    <div className={mcClasses("mc-catalog-mobile-header")}>
                        <h2 className={mcClasses("mc-catalog-title-mobile")}>{catalogTitle}</h2>
                        <button
                            type="button"
                            className={mcClasses("mc-button mc-catalog-filter-toggle")}
                            data-mc-button="light"
                            onClick={() => setSidebarOpen(true)}
                        >
                            <i className="bi bi-sliders" aria-hidden="true" />
                            {label(labels, "catalog_filter_button")}
                        </button>
                    </div>
                )}

                {(message || error) && (
                    <div className={noticeClassName} role="alert">
                        <i className={noticeIconClassName} aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{error || message}</div>
                    </div>
                )}
                <LoginNotice data={data} labels={labels} />

                <div className={mcClasses("mc-catalog-layout")}>
                    <aside className={sidebarClassName}>
                        <div className={mcClasses("mc-catalog-sidebar-inner")}>
                            <div className={mcClasses("mc-catalog-sidebar-header")}>
                                <h3>{label(labels, "catalog_refine_results")}</h3>
                                {activeFilters > 0 && (
                                    <span className={mcClasses("mc-catalog-filter-count")}>
                                        {formatCount(activeFilters)}
                                    </span>
                                )}
                                <button
                                    type="button"
                                    className={mcClasses("mc-catalog-sidebar-close")}
                                    aria-label={labels.catalog_filter_title}
                                    onClick={() => setSidebarOpen(false)}
                                >
                                    <i className="bi bi-x-lg" aria-hidden="true" />
                                </button>
                            </div>

                            <form onSubmit={handleSearch}>
                                <FilterSection title={label(labels, "catalog_search_title")}>
                                    <div className={mcClasses("mc-catalog-search max-w-100")}>
                                        <i
                                            className={mcClasses("bi bi-search mc-catalog-search-icon")}
                                            aria-hidden="true"
                                        />
                                        <input
                                            type="search"
                                            className={mcClasses("mc-catalog-search-input")}
                                            value={draft.search}
                                            placeholder={labels.catalog_search_placeholder}
                                            onChange={(event) => setDraft({
                                                ...draft,
                                                search: event.currentTarget.value,
                                            })}
                                        />
                                    </div>
                                </FilterSection>

                                <FilterSection title={labels.catalog_coursetype_title}>
                                    <select
                                        className={mcClasses("mc-select")}
                                        value={draft.coursetype}
                                        onChange={(event) => applyFilters({
                                            coursetype: event.currentTarget.value,
                                        })}
                                    >
                                        <option value="">{label(labels, "catalog_all_types")}</option>
                                        {(data?.filteroptions.coursetypes ?? []).map((option) => (
                                            <option value={option.value} key={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </FilterSection>

                                <FilterSection title={labels.catalog_topic_title}>
                                    <select
                                        className={mcClasses("mc-select")}
                                        value={draft.categoryid}
                                        onChange={(event) => applyFilters({
                                            categoryid: Number(event.currentTarget.value),
                                        })}
                                    >
                                        <option value={0}>{label(labels, "catalog_all_topics")}</option>
                                        {(data?.filteroptions.categories ?? []).map((category) => (
                                            <option value={category.id} key={category.id}>
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                </FilterSection>

                                <FilterSection title={labels.catalog_level_title}>
                                    <select
                                        className={mcClasses("mc-select")}
                                        value={draft.level}
                                        onChange={(event) => applyFilters({
                                            level: event.currentTarget.value,
                                        })}
                                    >
                                        <option value="">{labels.catalog_level_all || "All levels"}</option>
                                        {(data?.filteroptions.levels ?? []).map((option) => (
                                            <option value={option.value} key={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </FilterSection>

                                <FilterSection title={labels.catalog_price_title}>
                                    <div className="d-flex gap-2">
                                        <input
                                            type="number"
                                            min="0"
                                            className={mcClasses("mc-form-control")}
                                            value={draft.minprice || ""}
                                            placeholder={label(labels, "catalog_price_min")}
                                            aria-label={label(labels, "catalog_price_min")}
                                            onChange={(event) => setDraft({
                                                ...draft,
                                                minprice: Number(event.currentTarget.value),
                                            })}
                                        />
                                        <input
                                            type="number"
                                            min="0"
                                            className={mcClasses("mc-form-control")}
                                            value={draft.maxprice || ""}
                                            placeholder={label(labels, "catalog_price_max")}
                                            aria-label={label(labels, "catalog_price_max")}
                                            onChange={(event) => setDraft({
                                                ...draft,
                                                maxprice: Number(event.currentTarget.value),
                                            })}
                                        />
                                    </div>
                                </FilterSection>

                                <div className="p-3 d-grid gap-2">
                                    <button type="submit" className={mcClasses("mc-button btn-mc-primary")}>
                                        {labels.catalog_show_results}
                                    </button>
                                    <button
                                        type="button"
                                        className={mcClasses("mc-button mc-btn-soft")}
                                        onClick={clearFilters}
                                    >
                                        {labels.catalog_clear_filters}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </aside>

                    <div
                        className={backdropClassName}
                        onClick={() => setSidebarOpen(false)}
                        role="presentation"
                    />

                    <main className={mcClasses("mc-catalog-main")} aria-busy={loading}>
                        <div className={mcClasses("mc-catalog-results-bar")}>
                            <div>
                                <span className={mcClasses("mc-catalog-kicker")}>
                                    {label(labels, "catalog_showing_results")}
                                </span>
                                <h2 className={mcClasses("mc-catalog-title")}>
                                    {formatCount(total)} {labels.catalog_results}
                                </h2>
                            </div>
                            <div className={mcClasses("mc-catalog-controls")}>
                                <label className={mcClasses("mc-catalog-perpage")}>
                                    <span className={mcClasses("mc-catalog-perpage-label")}>
                                        {labels.catalog_show}
                                    </span>
                                    <select
                                        className={mcClasses("mc-catalog-perpage-select")}
                                        value={filters.perpage}
                                        onChange={(event) => applyFilters({
                                            perpage: Number(event.currentTarget.value),
                                        })}
                                    >
                                        {pageOptions.map((value) => (
                                            <option value={value} key={value}>{value}</option>
                                        ))}
                                    </select>
                                </label>

                                <label className={mcClasses("mc-catalog-sort")}>
                                    <span className={mcClasses("mc-catalog-sort-label")}>
                                        {labels.catalog_sortby}
                                    </span>
                                    <select
                                        className={mcClasses("mc-catalog-sort-select")}
                                        value={filters.sort}
                                        onChange={(event) => applyFilters({
                                            sort: event.currentTarget.value,
                                        })}
                                    >
                                        <option value="popular">{labels.catalog_sort_popular}</option>
                                        <option value="newest">{labels.catalog_sort_newest}</option>
                                        <option value="pricelow">{labels.catalog_sort_pricelow}</option>
                                        <option value="pricehigh">{labels.catalog_sort_pricehigh}</option>
                                    </select>
                                </label>
                            </div>
                        </div>

                        <TypeTabs
                            labels={labels}
                            options={data?.filteroptions.coursetypes ?? []}
                            selected={filters.coursetype}
                            onSelect={(coursetype) => applyFilters({coursetype})}
                        />

                        <CatalogContent
                            loading={loading}
                            data={data}
                            labels={labels}
                            busyItem={busyItem}
                            onAddToCart={addToCart}
                            onClearFilters={clearFilters}
                            onPageChange={(nextPage) => applyFilters({page: nextPage})}
                            busyWishlistItem={busyWishlistItem}
                            savedWishlistProductIds={savedWishlistProductIds}
                            onToggleWishlist={
                                wishlistUpdateMethodName && data?.state.isloggedin ? toggleWishlist : undefined
                            }
                        />
                    </main>
                </div>
            </div>
        </div>
    );
}

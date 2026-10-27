// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Preview-only SaaS-style widget gallery for Modern Commerce storefront widgets.
 *
 * @module     local_moderncommerce/storefront_gallery
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useMemo, useState} from "react";
import {toast} from "./design_system";
import {IconPicker} from "./icon_picker";
import {
    callService,
    mergePresetIntoData,
    parseJson,
    RenderedWidget,
    safePresetFieldNames,
    universalStyleFields,
} from "./storefront";
import type {GetPageResponse, Labels, StyleConfig, WidgetInstance, WidgetPreset, ZonePayload} from "./storefront";

type ShowcaseSection = {
    type: string;
    label: string;
    anchor: string;
    stylelabels: string[];
    hasstyles: boolean;
    stylesummary: string;
};

type GalleryLabels = Record<string, string>;

type GalleryPresetMethods = {
    list: string;
    save: string;
    delete: string;
};

type PresetResponse = {presets: WidgetPreset[]; warnings: unknown[]};
type SavePresetResponse = {success: boolean; message: string; preset: WidgetPreset; warnings: unknown[]};
type DeletePresetResponse = {success: boolean; message: string; warnings: unknown[]};

type GalleryStyleState = {
    presetId: number;
    presetName: string;
    styleconfig: StyleConfig;
    settingspatch: Record<string, unknown>;
    fullWidth: boolean;
};

type VariantSummary = {
    widget: WidgetInstance;
    label: string;
    styleKey: string;
    title: string;
};

type BreadcrumbSettingItem = "background" | "breadcrumb" | "title" | "subtitle" | "overlay" | "padding" | "position";
type VideoHeroSettingItem = "background" | "heading" | "body" | "buttons" | "panel" | "infocard" | "spacing";
type CountdownSettingItem = "background" | "heading" | "timer" | "button" | "expired" | "spacing";
type CategoriesSettingItem = "layout" | "heading" | "cards" | "icons" | "count" | "spacing";
type GalleryVisualSection = {
    key: string;
    label: string;
    icon: string;
    styleKeys?: string[];
    patchKeys?: string[];
};
type GalleryVisibilityValue = string | number | boolean;
type GalleryVisibilityRule = {
    field: string;
    equals?: GalleryVisibilityValue | GalleryVisibilityValue[];
    notequals?: GalleryVisibilityValue | GalleryVisibilityValue[];
    truthy?: boolean;
};

type Props = {
    getGalleryMethod: string;
    pageType?: string;
    renderContext?: Record<string, string | number | boolean>;
    defaultType?: string;
    defaultVariantStyle?: string;
    exitUrl: string;
    showcase: ShowcaseSection[];
    catalogLabels: Labels;
    storefrontLabels: Labels;
    presetMethods?: GalleryPresetMethods;
    labels: GalleryLabels;
};

const typeIcons: Record<string, string> = {
    slider: "bi-images",
    videohero: "bi-play-circle",
    breadcrumb: "bi-signpost-split",
    featured: "bi-stars",
    related: "bi-intersect",
    categories: "bi-grid-3x3-gap",
    trustbadges: "bi-shield-check",
    partners: "bi-buildings",
    countdown: "bi-hourglass-split",
    testimonials: "bi-chat-quote",
    instructors: "bi-person-video3",
    newsletter: "bi-envelope-paper",
    content: "bi-layout-text-window",
    mediastorycarousel: "bi-film",
    featuregrid: "bi-grid-3x3-gap-fill",
    subscriptionplans: "bi-credit-card-2-front",
    learningpromise: "bi-patch-check",
    belief: "bi-lightbulb",
    policy: "bi-file-earmark-text",
    faq: "bi-question-circle",
    cta: "bi-megaphone",
    supportform: "bi-life-preserver",
    contactcards: "bi-person-lines-fill",
    footer: "bi-layout-three-columns",
    catalog: "bi-shop",
};

const typeDescriptions: Record<string, string> = {
    slider: "Compare homepage hero sliders for launch messages, course promotions, and seasonal campaigns.",
    videohero: "Preview a video-led hero section for high-impact storefront entry points.",
    breadcrumb: "Create page headers with title, subtitle, breadcrumb trail, background media, alignment controls, and overlay treatments.",
    featured: "Showcase curated courses, bundles, and programmes in carousel or grid layouts.",
    related: "Preview companion product recommendations for course and bundle detail pages.",
    categories: "Compare category navigation blocks for storefront discovery and browsing.",
    trustbadges: "Present checkout confidence signals such as security, instant access, certificates, and guarantees.",
    partners: "Display partner, client, and accreditation logos as a static row, a paged carousel, or a continuous marquee.",
    countdown: "Preview urgency and campaign countdown sections for limited-time offers.",
    testimonials: "Display learner proof and outcomes in a compact testimonial section.",
    instructors: "Feature instructor expertise with portraits, roles, and short biographies.",
    newsletter: "Preview the storefront lead-capture block for email signups.",
    content: "Compare flexible editorial sections for explanatory copy, images, and calls to action.",
    mediastorycarousel: "Preview story-led media panels for programme narratives and learning outcomes.",
    featuregrid: "Compare icon feature cards for benefits, capabilities, and programme highlights.",
    subscriptionplans: "Compare subscription pricing tables built from your live plans, prices, and feature matrix.",
    learningpromise: "Show a focused brand promise statement for learning pages.",
    belief: "Preview a full-width principles band for company and about pages.",
    policy: "Display structured policy copy with dates, sections, and supporting bullets.",
    faq: "Preview accordion-style answers for purchase and enrolment questions.",
    cta: "Compare call-to-action bands for storefront conversion points.",
    supportform: "Preview the support request form used around purchase and learner-help pages.",
    contactcards: "Display contact and help channels as compact action cards.",
    footer: "Compare storefront footer styles with links, contact details, social icons, and compliance copy.",
    catalog: "Preview the course catalog grid and filter frame with demo product data.",
};

const l = (labels: GalleryLabels, key: string, fallback?: string): string => labels[key] ?? fallback ?? key;

const slugify = (value: string): string =>
    value.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") || "default";

const titleCase = (value: string): string =>
    value.replace(/[-_]+/g, " ").replace(/\b\w/g, (match) => match.toUpperCase());

const textValue = (value: unknown): string => typeof value === "string" ? value : "";

const widgetKey = (widget: WidgetInstance): string => `${widget.type}:${widget.id}`;
const safeKeySet = new Set(safePresetFieldNames);
// Widened to Set<string>: universalStyleFields is `as const`, so the inferred key union
// would reject the plain string keys this set is used to membership-test.
const universalStyleKeySet = new Set<string>(universalStyleFields.map((field) => field.key));
const breadcrumbSettingItems: Array<{key: BreadcrumbSettingItem; label: string; icon: string}> = [
    {key: "background", label: "Background", icon: "bi-palette"},
    {key: "breadcrumb", label: "Breadcrumb", icon: "bi-signpost-split"},
    {key: "title", label: "Title", icon: "bi-type-h1"},
    {key: "subtitle", label: "Subtitle", icon: "bi-text-paragraph"},
    {key: "overlay", label: "Overlay", icon: "bi-layers"},
    {key: "padding", label: "Padding", icon: "bi-arrows-expand"},
    {key: "position", label: "Position", icon: "bi-text-center"},
];
const videoHeroSettingItems: Array<{key: VideoHeroSettingItem; label: string; icon: string}> = [
    {key: "background", label: "Background", icon: "bi-palette"},
    {key: "heading", label: "Heading", icon: "bi-type-h1"},
    {key: "body", label: "Body text", icon: "bi-body-text"},
    {key: "buttons", label: "Buttons", icon: "bi-cursor"},
    {key: "panel", label: "Video panel", icon: "bi-play-btn"},
    {key: "infocard", label: "Info card", icon: "bi-info-square"},
    {key: "spacing", label: "Spacing", icon: "bi-arrows-expand"},
];
const countdownSettingItems: Array<{key: CountdownSettingItem; label: string; icon: string}> = [
    {key: "background", label: "Background", icon: "bi-palette"},
    {key: "heading", label: "Heading", icon: "bi-type-h1"},
    {key: "timer", label: "Timer", icon: "bi-hourglass-split"},
    {key: "button", label: "Button", icon: "bi-cursor"},
    {key: "expired", label: "Expired state", icon: "bi-clock-history"},
    {key: "spacing", label: "Spacing", icon: "bi-arrows-expand"},
];
const categoriesSettingItems: Array<{key: CategoriesSettingItem; label: string; icon: string}> = [
    {key: "layout", label: "Layout", icon: "bi-grid-3x3-gap"},
    {key: "heading", label: "Heading", icon: "bi-type-h2"},
    {key: "cards", label: "Cards", icon: "bi-collection"},
    {key: "icons", label: "Icons", icon: "bi-stars"},
    {key: "count", label: "Count text", icon: "bi-123"},
    {key: "spacing", label: "Spacing", icon: "bi-arrows-expand"},
];
const galleryVisualProfiles: Record<string, GalleryVisualSection[]> = {
    slider: [
        {key: "design", label: "Design", icon: "bi-sliders", patchKeys: ["design"]},
        {key: "motion", label: "Motion", icon: "bi-play-circle", patchKeys: ["autoplay"]},
        {key: "controls", label: "Controls", icon: "bi-toggles", patchKeys: ["showarrows", "showdots"]},
        {key: "button", label: "Button", icon: "bi-cursor",
            patchKeys: ["buttoncolor", "buttontextcolor", "buttonfontsize", "buttonradius"]},
        {key: "appearance", label: "Appearance", icon: "bi-palette",
            styleKeys: ["bg", "headingcolor", "textcolor", "accentcolor", "headingfontsize", "bodyfontsize", "radius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", styleKeys: ["spacingtop", "spacingbottom"]},
    ],
    featured: [
        {key: "layout", label: "Layout", icon: "bi-layout-three-columns", patchKeys: ["align", "layout", "columns", "navposition"]},
        {key: "cards", label: "Cards", icon: "bi-collection",
            patchKeys: ["cardbgcolor", "cardbordercolor", "cardborderwidth"]},
        {key: "button", label: "Button", icon: "bi-cursor", patchKeys: ["buttoncolor", "buttontextcolor"]},
        {key: "appearance", label: "Appearance", icon: "bi-palette",
            styleKeys: ["bg", "headingcolor", "textcolor", "accentcolor", "headingfontsize", "bodyfontsize", "radius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", styleKeys: ["spacingtop", "spacingbottom"]},
    ],
    related: [
        {key: "layout", label: "Layout", icon: "bi-layout-three-columns", patchKeys: ["align", "layout", "columns", "navposition"]},
        {key: "cards", label: "Cards", icon: "bi-collection",
            patchKeys: ["cardbgcolor", "cardbordercolor", "cardborderwidth"]},
        {key: "button", label: "Button", icon: "bi-cursor", patchKeys: ["buttoncolor", "buttontextcolor"]},
        {key: "appearance", label: "Appearance", icon: "bi-palette",
            styleKeys: ["bg", "headingcolor", "textcolor", "accentcolor", "headingfontsize", "bodyfontsize", "radius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", styleKeys: ["spacingtop", "spacingbottom"]},
    ],
    trustbadges: [
        {key: "background", label: "Background", icon: "bi-palette", patchKeys: ["bgcolor"]},
        {key: "title", label: "Title", icon: "bi-type-h2", patchKeys: ["titlecolor", "titlefontsize"]},
        {key: "card", label: "Card", icon: "bi-credit-card-2-front",
            patchKeys: ["cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius"]},
        {key: "icons", label: "Icon style", icon: "bi-shield-check", patchKeys: ["iconbgcolor", "iconcolor", "iconsize"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["labelcolor", "labelfontsize", "sublabelcolor", "sublabelfontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    partners: [
        {key: "layout", label: "Layout", icon: "bi-layout-three-columns",
            patchKeys: ["style", "visiblelogos", "speed", "pauseonhover"]},
        {key: "logos", label: "Logo style", icon: "bi-image",
            patchKeys: ["greyscale", "logoheight", "logogap"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            patchKeys: ["headingcolor", "headingfontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand",
            patchKeys: ["bgcolor", "paddingtop", "paddingbottom"]},
    ],
    testimonials: [
        {key: "background", label: "Background", icon: "bi-palette", patchKeys: ["bgcolor"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            patchKeys: ["titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "cards", label: "Cards", icon: "bi-collection",
            patchKeys: ["cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius", "ratingcolor"]},
        {key: "text", label: "Text", icon: "bi-chat-quote",
            patchKeys: ["quotecolor", "quotefontsize", "avatarbgcolor", "avatarcolor",
                "namecolor", "namefontsize", "rolecolor", "rolefontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    instructors: [
        {key: "background", label: "Background", icon: "bi-palette", patchKeys: ["bgcolor"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            patchKeys: ["titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "cards", label: "Cards", icon: "bi-collection",
            patchKeys: ["cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius"]},
        {key: "avatar", label: "Avatar", icon: "bi-person-circle", patchKeys: ["avatarbgcolor", "avatarcolor"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["namecolor", "namefontsize", "rolecolor", "rolefontsize", "biocolor", "biofontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    newsletter: [
        {key: "panel", label: "Panel", icon: "bi-window",
            patchKeys: ["bgcolor", "panelbgcolor", "panelbordercolor", "panelborderwidth", "panelradius",
                "panelpaddingtop", "panelpaddingright", "panelpaddingbottom", "panelpaddingleft"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["titlecolor", "titlefontsize", "textcolor", "textfontsize"]},
        {key: "form", label: "Form", icon: "bi-envelope",
            patchKeys: ["inputbgcolor", "inputbordercolor", "inputtextcolor", "placeholdercolor"]},
        {key: "button", label: "Button", icon: "bi-cursor", patchKeys: ["buttoncolor", "buttontextcolor", "buttonradius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    mediastorycarousel: [
        {key: "layout", label: "Layout", icon: "bi-layout-split", patchKeys: ["mediaposition", "navicon"]},
        {key: "panel", label: "Panel", icon: "bi-window",
            patchKeys: ["bgcolor", "cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius", "mediaradius"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["titlecolor", "titlefontsize", "textcolor", "textfontsize"]},
        {key: "navigation", label: "Navigation", icon: "bi-arrow-left-right", patchKeys: ["iconcolor", "iconbgcolor"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    content: [
        {key: "layout", label: "Layout", icon: "bi-layout-text-window",
            patchKeys: ["layout", "mediaposition", "bgcolor", "panelbgcolor", "panelbordercolor", "cardradius",
                "mediaradius"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize", "textcolor", "textfontsize"]},
        {key: "icon", label: "Icon", icon: "bi-stars", patchKeys: ["iconbgcolor", "iconcolor", "iconsize"]},
        {key: "benefits", label: "Benefits", icon: "bi-list-ol",
            patchKeys: ["benefitnumbercolor", "benefitnumberfontsize", "benefittitlecolor", "benefittitlefontsize",
                "benefittextcolor", "benefittextfontsize", "benefitbordercolor"]},
        {key: "button", label: "Button", icon: "bi-cursor", patchKeys: ["buttoncolor", "buttontextcolor", "buttonradius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand",
            patchKeys: ["paddingtop", "paddingbottom", "paddingleft", "paddingright"]},
    ],
    leadermessage: [
        {key: "layout", label: "Layout", icon: "bi-layout-split",
            patchKeys: ["style", "mediaposition", "showphoto", "mediawidth", "mediaradius", "justifytext"]},
        {key: "visibility", label: "Visible elements", icon: "bi-eye",
            patchKeys: ["showeyebrow", "showtitle", "showattribution", "showsignature", "showcta", "showaccent"]},
        {key: "background", label: "Background", icon: "bi-palette", patchKeys: ["bgcolor", "accentcolor"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["eyebrowcolor", "eyebrowfontsize", "titlecolor", "titlefontsize", "textcolor", "textfontsize",
                "namecolor", "namefontsize", "rolecolor", "rolefontsize"]},
        {key: "button", label: "Button", icon: "bi-cursor",
            patchKeys: ["buttoncolor", "buttontextcolor", "buttonradius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    featuregrid: [
        {key: "layout", label: "Layout", icon: "bi-grid-3x3-gap",
            patchKeys: ["style", "columns", "showheader", "centercards"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            patchKeys: ["titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "cards", label: "Cards", icon: "bi-collection",
            patchKeys: ["cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius"]},
        {key: "icons", label: "Icons", icon: "bi-stars", patchKeys: ["iconbgcolor", "iconcolor", "iconsize"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["cardtitlecolor", "cardtitlefontsize", "textcolor", "textfontsize", "linkcolor"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand",
            patchKeys: ["bgcolor", "paddingtop", "paddingbottom"]},
    ],
    subscriptionplans: [
        {key: "layout", label: "Layout", icon: "bi-layout-three-columns",
            patchKeys: ["style", "columns", "showheader", "showdescription", "showfeatures", "showtrial", "showpromo",
                "highlightfeatured"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            patchKeys: ["titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "cards", label: "Cards", icon: "bi-window",
            patchKeys: ["bgcolor", "cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius", "accentcolor"]},
        {key: "pricing", label: "Pricing", icon: "bi-cash-coin",
            patchKeys: ["plannamecolor", "plannamefontsize", "pricecolor", "pricefontsize", "cyclecolor"]},
        {key: "features", label: "Features", icon: "bi-check2-square",
            patchKeys: ["textcolor", "textfontsize", "featurecolor", "featureiconcolor"]},
        {key: "button", label: "Button", icon: "bi-cursor",
            patchKeys: ["buttoncolor", "buttontextcolor", "buttonradius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    learningpromise: [
        {key: "background", label: "Background", icon: "bi-palette", patchKeys: ["bgcolor"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["headingcolor", "headingfontsize", "textcolor", "textfontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    belief: [
        {key: "background", label: "Background", icon: "bi-palette", patchKeys: ["bgcolor"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            patchKeys: ["titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "icons", label: "Icons", icon: "bi-patch-check", patchKeys: ["iconcolor", "iconsize"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["textcolor", "textfontsize", "labelcolor", "labelfontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    policy: [
        {key: "cards", label: "Content", icon: "bi-file-earmark-text",
            patchKeys: ["bgcolor", "cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            patchKeys: ["titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["labelcolor", "labelfontsize", "textcolor", "textfontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    faq: [
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            patchKeys: ["titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "items", label: "Items", icon: "bi-list-check",
            patchKeys: ["bgcolor", "itembgcolor", "itembordercolor", "cardborderwidth", "cardradius", "iconcolor"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["questioncolor", "labelfontsize", "answercolor", "textfontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    cta: [
        {key: "layout", label: "Tone", icon: "bi-megaphone", patchKeys: ["tone"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["titlecolor", "titlefontsize", "textcolor", "textfontsize"]},
        {key: "buttons", label: "Buttons", icon: "bi-cursor",
            patchKeys: ["primarybuttoncolor", "primarybuttontextcolor", "secondarybuttoncolor",
                "secondarybuttontextcolor", "buttonradius"]},
        {key: "appearance", label: "Appearance", icon: "bi-palette", patchKeys: ["bgcolor", "cardradius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    supportform: [
        {key: "panel", label: "Panel", icon: "bi-window",
            patchKeys: ["bgcolor", "cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["titlecolor", "titlefontsize", "textcolor", "textfontsize", "formlabelcolor"]},
        {key: "form", label: "Form", icon: "bi-ui-checks",
            patchKeys: ["inputbgcolor", "inputbordercolor", "inputtextcolor"]},
        {key: "buttons", label: "Buttons", icon: "bi-cursor",
            patchKeys: ["buttoncolor", "buttontextcolor", "secondarybuttoncolor",
                "secondarybuttontextcolor", "buttonradius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    contactcards: [
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            patchKeys: ["titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "cards", label: "Cards", icon: "bi-postcard",
            patchKeys: ["bgcolor", "cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius"]},
        {key: "icons", label: "Icons", icon: "bi-patch-check", patchKeys: ["iconbgcolor", "iconcolor", "iconsize"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            patchKeys: ["labelcolor", "labelfontsize", "textcolor", "textfontsize", "linkcolor"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    footer: [
        {key: "layout", label: "Style", icon: "bi-layout-three-columns", patchKeys: ["style", "mode", "logoheight"]},
        {key: "appearance", label: "Appearance", icon: "bi-palette",
            patchKeys: ["bgcolor", "panelbgcolor", "titlecolor", "titlefontsize", "textcolor", "textfontsize",
                "linkcolor", "iconbgcolor", "iconcolor"]},
        {key: "subscribe", label: "Subscribe", icon: "bi-envelope",
            patchKeys: ["inputbgcolor", "inputbordercolor", "inputtextcolor", "buttoncolor", "buttontextcolor"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", patchKeys: ["paddingtop", "paddingbottom"]},
    ],
    catalog: [
        {key: "layout", label: "Layout", icon: "bi-layout-sidebar", patchKeys: ["sidebarposition"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            patchKeys: ["titlecolor", "titlefontsize", "textcolor", "textfontsize", "accentcolor", "eyebrowcolor"]},
        {key: "hero", label: "Hero", icon: "bi-window",
            patchKeys: ["showheader", "herobgcolor", "herobordercolor", "heroradius", "heropanelbgcolor",
                "heropanelbordercolor", "heropaneltextcolor", "heropanelaccentcolor",
                "heropanelvaluecolor", "heropanelvaluefontsize"]},
        {key: "cards", label: "Course cards", icon: "bi-collection",
            patchKeys: ["cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius", "cardfooterbgcolor", "cardtitlecolor",
                "cardtitlefontsize", "cardtextcolor", "cardmetabgcolor", "cardmetatextcolor", "ratingcolor",
                "ratingtextcolor", "pricecolor", "originalpricecolor"]},
        {key: "buttons", label: "Buttons", icon: "bi-cursor",
            patchKeys: ["buttoncolor", "buttontextcolor", "buttonradius"]},
        {key: "badges", label: "Badges", icon: "bi-tags",
            patchKeys: ["badgebgcolor", "badgebordercolor", "badgetextcolor", "badgeradius", "badgefontsize",
                "coursebadgebgcolor", "coursebadgebordercolor", "coursebadgetextcolor",
                "programbadgebgcolor", "programbadgebordercolor", "programbadgetextcolor",
                "bundlebadgebgcolor", "bundlebadgebordercolor", "bundlebadgetextcolor"]},
        {key: "filters", label: "Filters", icon: "bi-funnel",
            patchKeys: ["filterbgcolor", "filterbordercolor", "filterborderwidth", "filterradius",
                "filtertitlecolor", "filtertextcolor", "inputbgcolor", "inputbordercolor", "inputtextcolor",
                "placeholdercolor"]},
        {key: "tabs", label: "Tabs", icon: "bi-segmented-nav",
            patchKeys: ["tabbgcolor", "tabbordercolor", "tabtextcolor", "tabactivebgcolor", "tabactivetextcolor"]},
        {key: "appearance", label: "Appearance", icon: "bi-palette", patchKeys: ["bgcolor"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand",
            patchKeys: ["paddingtop", "paddingbottom", "paddingleft", "paddingright", "margintop", "marginbottom"]},
    ],
};
const visualFieldLabels: Record<string, string> = {
    showheader: "Show hero section",
    showdescription: "Show plan descriptions",
    showfeatures: "Show plan features",
    showtrial: "Show trial details",
    showpromo: "Show promotion details",
    highlightfeatured: "Highlight featured plan",
    showeyebrow: "Show eyebrow label",
    showtitle: "Show heading",
    justifytext: "Justify message text",
    showphoto: "Show portrait",
    showattribution: "Show name and role",
    showsignature: "Show signature",
    showcta: "Show button",
    showaccent: "Show accent",
    autoplay: "Autoplay",
    showarrows: "Show arrows",
    showdots: "Show dots",
    greyscale: "Greyscale logos",
    pauseonhover: "Pause on hover",
    centercards: "Centre cards",
    style: "Style",
    design: "Design",
    layout: "Layout",
    mode: "Mode",
    tone: "Tone",
    mediaposition: "Media position",
    sidebarposition: "Sidebar position",
    navicon: "Navigation icon",
    align: "Alignment",
    alignment: "Alignment",
    navposition: "Navigation position",
    columns: "Columns",
    bgcolor: "Background colour",
    textcolor: "Text colour",
    headingcolor: "Heading colour",
    accentcolor: "Accent colour",
    overlaycolor: "Overlay colour",
    gradientstart: "Gradient start",
    gradientend: "Gradient end",
    breadcrumbcolor: "Breadcrumb colour",
    titlecolor: "Title colour",
    subtitlecolor: "Subtitle colour",
    paddingtop: "Padding top",
    paddingbottom: "Padding bottom",
    paddingleft: "Padding left",
    paddingright: "Padding right",
    cardradius: "Card radius",
    cardbordercolor: "Card border colour",
    cardborderwidth: "Card border weight",
    breadcrumbfontsize: "Breadcrumb font size",
    titlefontsize: "Title font size",
    subtitlefontsize: "Subtitle font size",
    benefitnumbercolor: "Benefit number colour",
    benefitnumberfontsize: "Benefit number font size",
    benefittitlecolor: "Benefit heading colour",
    benefittitlefontsize: "Benefit heading font size",
    benefittextcolor: "Benefit description colour",
    benefittextfontsize: "Benefit description font size",
    benefitbordercolor: "Benefit divider colour",
    overlayopacity: "Overlay opacity",
    primarybuttoncolor: "Primary button background",
    primarybuttontextcolor: "Primary button text",
    secondarybuttoncolor: "Secondary button background",
    secondarybuttontextcolor: "Secondary button text",
    infocardbgcolor: "Info card background",
    infoiconbgcolor: "Icon background",
    infoiconcolor: "Icon colour",
    infoheadingcolor: "Info heading colour",
    infoheadingfontsize: "Info heading font size",
    infotextcolor: "Info sub text colour",
    timerbgcolor: "Timer box background",
    timernumbercolor: "Timer number colour",
    timernumberfontsize: "Timer number font size",
    timerlabelcolor: "Timer label colour",
    timerlabelfontsize: "Timer label font size",
    buttoncolor: "Button background",
    buttontextcolor: "Button text colour",
    buttonfontsize: "Button font size",
    buttonradius: "Button radius",
    expiredbgcolor: "Expired background",
    expiredtextcolor: "Expired text colour",
    visiblecards: "Visible cards",
    iconcolor: "Icon colour",
    iconbgcolor: "Icon background",
    iconsize: "Icon size",
    cardbgcolor: "Card background",
    cardtextcolor: "Card text colour",
    cardtextfontsize: "Card text font size",
    labelcolor: "Label colour",
    labelfontsize: "Label font size",
    sublabelcolor: "Sublabel colour",
    sublabelfontsize: "Sublabel font size",
    countcolor: "Count text colour",
    countfontsize: "Count font size",
    margintop: "Margin top",
    marginbottom: "Margin bottom",
    logoheight: "Logo height",
    visiblelogos: "Visible logos",
    speed: "Scroll speed",
    logogap: "Logo gap",
    panelbgcolor: "Panel background",
    panelbordercolor: "Panel border colour",
    panelborderwidth: "Panel border weight",
    panelradius: "Panel radius",
    panelpaddingtop: "Panel padding top",
    panelpaddingright: "Panel padding right",
    panelpaddingbottom: "Panel padding bottom",
    panelpaddingleft: "Panel padding left",
    inputbgcolor: "Input background",
    inputbordercolor: "Input border colour",
    inputtextcolor: "Input text colour",
    placeholdercolor: "Placeholder colour",
    formlabelcolor: "Form label colour",
    linkcolor: "Link colour",
    ratingcolor: "Rating colour",
    avatarbgcolor: "Avatar background",
    avatarcolor: "Avatar colour",
    quotecolor: "Quote colour",
    quotefontsize: "Quote font size",
    textfontsize: "Text font size",
    namecolor: "Name colour",
    namefontsize: "Name font size",
    rolecolor: "Role colour",
    rolefontsize: "Role font size",
    biocolor: "Bio colour",
    biofontsize: "Bio font size",
    mediaradius: "Media radius",
    eyebrowfontsize: "Eyebrow font size",
    mediawidth: "Portrait width",
    herobgcolor: "Hero background",
    herobordercolor: "Hero border colour",
    heroradius: "Hero radius",
    eyebrowcolor: "Eyebrow colour",
    heropanelbgcolor: "Hero panel background",
    heropanelbordercolor: "Hero panel border colour",
    heropaneltextcolor: "Hero panel text colour",
    heropanelaccentcolor: "Hero panel icon colour",
    heropanelvaluecolor: "Hero panel number colour",
    heropanelvaluefontsize: "Hero panel number font size",
    cardfooterbgcolor: "Course card footer background",
    cardtitlecolor: "Course title colour",
    cardtitlefontsize: "Course title font size",
    cardmetabgcolor: "Category chip background",
    cardmetatextcolor: "Category chip text colour",
    ratingtextcolor: "Rating number colour",
    originalpricecolor: "Original price colour",
    questioncolor: "Question colour",
    answercolor: "Answer colour",
    itembgcolor: "Item background",
    itembordercolor: "Item border colour",
    pricecolor: "Price colour",
    plannamecolor: "Plan name colour",
    plannamefontsize: "Plan name font size",
    pricefontsize: "Price font size",
    cyclecolor: "Billing cycle colour",
    featurecolor: "Feature text colour",
    featureiconcolor: "Feature icon colour",
    badgebgcolor: "Badge background",
    badgebordercolor: "Badge border colour",
    badgetextcolor: "Badge text colour",
    badgeradius: "Badge radius",
    badgefontsize: "Badge font size",
    coursebadgebgcolor: "Course badge background",
    coursebadgebordercolor: "Course badge border colour",
    coursebadgetextcolor: "Course badge text colour",
    programbadgebgcolor: "Programme badge background",
    programbadgebordercolor: "Programme badge border colour",
    programbadgetextcolor: "Programme badge text colour",
    bundlebadgebgcolor: "Bundle badge background",
    bundlebadgebordercolor: "Bundle badge border colour",
    bundlebadgetextcolor: "Bundle badge text colour",
    filterbgcolor: "Filter background",
    filterbordercolor: "Filter border colour",
    filterborderwidth: "Filter border weight",
    filterradius: "Filter radius",
    filtertitlecolor: "Filter heading colour",
    filtertextcolor: "Filter text colour",
    tabbgcolor: "Tab background",
    tabbordercolor: "Tab border colour",
    tabtextcolor: "Tab text colour",
    tabactivebgcolor: "Active tab background",
    tabactivetextcolor: "Active tab text colour",
};
const colorKey = (key: string): boolean => key.includes("color") || key === "bg" || key === "bgcolor"
    || key === "gradientstart" || key === "gradientend";
const numberKey = (key: string): boolean => key.includes("font") || key.includes("padding")
    || key.includes("spacing") || key.includes("radius")
    || key === "overlayopacity" || key === "visiblecards" || key === "iconsize"
    || key.endsWith("borderwidth") || key === "panelborderwidth" || key.includes("margin") || key === "logoheight"
    || key === "visiblelogos" || key === "speed" || key === "logogap" || key === "mediawidth";
// Boolean widget settings are stored as 1/0, so they need a switch rather than the
// text input the generic renderer would otherwise fall through to.
const boolKey = (key: string): boolean => key.startsWith("show") || key.startsWith("hide")
    || key === "autoplay" || key === "greyscale" || key === "pauseonhover" || key === "centercards"
    || key === "justifytext" || key === "highlightfeatured";
const boolValue = (value: unknown, fallback = true): boolean => {
    if (value === "" || value === null || typeof value === "undefined") {
        return fallback;
    }
    return value !== 0 && value !== "0" && value !== false && value !== "false";
};
const gallerySelectChoices: Record<string, Record<string, string[]>> = {
    slider: {design: ["overlay", "split", "card"]},
    featured: {
        align: ["left", "center"], layout: ["carousel", "grid"], columns: ["2", "3", "4", "5"],
        navposition: ["topleft", "topcenter", "topright", "bottomleft", "bottomcenter", "bottomright"],
    },
    related: {
        align: ["left", "center"], layout: ["carousel", "grid"], columns: ["2", "3", "4", "5"],
        navposition: ["topleft", "topcenter", "topright", "bottomleft", "bottomcenter", "bottomright"],
    },
    partners: {style: ["grid", "carousel", "marquee"]},
    mediastorycarousel: {mediaposition: ["left", "right"]},
    content: {layout: ["card", "centered", "split"], mediaposition: ["left", "right"]},
    leadermessage: {style: ["elevated", "overlay", "quote", "flat"], mediaposition: ["right", "left"]},
    featuregrid: {style: ["topbar", "plain", "tile", "bordered"], columns: ["3", "4"]},
    subscriptionplans: {style: ["elevated", "bordered", "minimal"], columns: ["2", "3", "4"]},
    cta: {tone: ["primary", "quiet", "success"]},
    footer: {style: ["default", "modern-classical", "enterprise-navy"], mode: ["light", "dark"]},
    catalog: {sidebarposition: ["left", "right"]},
};
const hexColour = (value: string): boolean => /^#[0-9a-f]{6}$/i.test(value);

const galleryVisibilityRules: Record<string, GalleryVisibilityRule | GalleryVisibilityRule[]> = {
    "breadcrumb.overlaycolor": {field: "style", equals: "imagehero"},
    "breadcrumb.gradientstart": {field: "style", equals: "gradient"},
    "breadcrumb.gradientend": {field: "style", equals: "gradient"},
    "featured.navposition": {field: "layout", equals: "carousel"},
    "related.navposition": {field: "layout", equals: "carousel"},
    "categories.visiblecards": {field: "style", equals: "carousel"},
    "categories.iconcolor": {field: "style", equals: "minimal"},
    "content.mediaposition": {field: "layout", equals: ["card", "split"]},
    "content.cardradius": {field: "layout", equals: "card"},
    "footer.mode": {field: "style", equals: "default"},
};
const galleryFieldDefaults: Record<string, Record<string, GalleryVisibilityValue>> = {
    breadcrumb: {style: "imagehero"},
    featured: {layout: "carousel"},
    related: {layout: "carousel"},
    categories: {style: "minimal"},
    content: {layout: "card"},
    footer: {style: "default"},
};

const normaliseVisibilityValue = (value: unknown): string => {
    if (typeof value === "boolean") {
        return value ? "1" : "0";
    }
    return String(value ?? "");
};

const visibilityList = (value: GalleryVisibilityValue | GalleryVisibilityValue[] | undefined): string[] => {
    if (typeof value === "undefined") {
        return [];
    }
    return (Array.isArray(value) ? value : [value]).map(normaliseVisibilityValue);
};

const isTruthyVisibilityValue = (value: unknown): boolean => {
    if (typeof value === "boolean") {
        return value;
    }
    if (typeof value === "number") {
        return value !== 0;
    }
    const text = String(value ?? "").trim().toLowerCase();
    return text !== "" && text !== "0" && text !== "false";
};

const passesVisibilityRule = (rule: GalleryVisibilityRule, value: unknown): boolean => {
    const current = normaliseVisibilityValue(value);
    const allowed = visibilityList(rule.equals);
    const blocked = visibilityList(rule.notequals);
    if (allowed.length > 0 && !allowed.includes(current)) {
        return false;
    }
    if (blocked.length > 0 && blocked.includes(current)) {
        return false;
    }
    if (typeof rule.truthy === "boolean" && isTruthyVisibilityValue(value) !== rule.truthy) {
        return false;
    }
    return true;
};

// Style controls are always scalar. Some safe keys (e.g. "columns") are a number on one
// widget but a repeater of raw rows on another (the footer's link columns), and patching the
// raw rows back over the resolved data breaks the renderer — so keep scalars only.
const pickVisualSettings = (widget: WidgetInstance): Record<string, unknown> => {
    const settings = parseJson<Record<string, unknown>>(widget.settings, {});
    const data = parseJson<Record<string, unknown>>(widget.data, {});
    return Object.fromEntries(Object.entries({...data, ...settings}).filter(([key, value]) =>
        safeKeySet.has(key) && (typeof value === "string" || typeof value === "number" || typeof value === "boolean")));
};

const compactPresetObject = (
    values: Record<string, unknown>,
    allowedKeys: Set<string>
): Record<string, string | number> => {
    const out: Record<string, string | number> = {};
    Object.entries(values).forEach(([key, value]) => {
        if (!allowedKeys.has(key) || value === "" || value === null || typeof value === "undefined") {
            return;
        }
        if (typeof value === "number") {
            if (Number.isFinite(value)) {
                out[key] = value;
            }
            return;
        }
        if (typeof value === "boolean") {
            out[key] = value ? 1 : 0;
            return;
        }
        if (typeof value === "string") {
            out[key] = value;
        }
    });
    return out;
};

const styleStateFromWidget = (widget: WidgetInstance, variantTitleText: string): GalleryStyleState => ({
    presetId: 0,
    presetName: variantTitleText,
    styleconfig: parseJson<StyleConfig>(widget.styleconfig ?? "{}", {}),
    settingspatch: pickVisualSettings(widget),
    fullWidth: true,
});

const styleKeyFor = (widget: WidgetInstance, data: Record<string, unknown>, label: string): string => {
    const raw = textValue(data.style) || textValue(data.design) || textValue(data.layout) || textValue(data.tone) || label;
    return slugify(raw);
};

const labelForVariant = (section: ShowcaseSection | null, widget: WidgetInstance, index: number): string => {
    const fromMeta = section?.stylelabels?.[index];
    if (fromMeta) {
        return fromMeta;
    }
    const data = parseJson<Record<string, unknown>>(widget.data, {});
    return titleCase(textValue(data.style) || textValue(data.design) || textValue(data.layout) || "Default");
};

const variantTitle = (section: ShowcaseSection | null, variantLabel: string, widget: WidgetInstance): string => {
    if (widget.type === "breadcrumb") {
        if (/image/i.test(variantLabel)) {
            return "Image Hero Banner";
        }
        if (/clean|minimal/i.test(variantLabel)) {
            return "Clean Minimal Banner";
        }
        if (/gradient/i.test(variantLabel)) {
            return "Gradient Media Banner";
        }
        return `${variantLabel} Banner`;
    }
    const sectionLabel = section?.label ?? titleCase(widget.type);
    return /default/i.test(variantLabel) ? sectionLabel : `${variantLabel} ${sectionLabel}`;
};

const applyStyleState = (widget: WidgetInstance, state: GalleryStyleState): WidgetInstance => {
    const data = parseJson<Record<string, unknown>>(widget.data, {});
    return {
        ...widget,
        styleconfig: JSON.stringify(state.styleconfig),
        data: JSON.stringify(mergePresetIntoData(data, state.settingspatch)),
    };
};

const applyPreviewState = (widget: WidgetInstance, activeCountdownItem: CountdownSettingItem): WidgetInstance => {
    if (widget.type !== "countdown" || activeCountdownItem !== "expired") {
        return widget;
    }
    const data = parseJson<Record<string, unknown>>(widget.data, {});
    return {
        ...widget,
        data: JSON.stringify({
            ...data,
            endtime: 1,
            expiredmessage: String(data.expiredmessage || "This offer has ended."),
        }),
    };
};

const presetPatchForWidget = (
    widget: WidgetInstance,
    styleConfig: StyleConfig,
    settingsPatch: Record<string, unknown>
): Record<string, unknown> => {
    if (widget.type === "categories") {
        const patch = {...settingsPatch};
        if (!patch.bgcolor && styleConfig.bg) {
            patch.bgcolor = styleConfig.bg;
        }
        if (!patch.titlecolor && styleConfig.headingcolor) {
            patch.titlecolor = styleConfig.headingcolor;
        }
        if (!patch.subtitlecolor && styleConfig.textcolor) {
            patch.subtitlecolor = styleConfig.textcolor;
        }
        if (!patch.cardtextcolor && styleConfig.textcolor) {
            patch.cardtextcolor = styleConfig.textcolor;
        }
        if (!patch.iconcolor && styleConfig.accentcolor) {
            patch.iconcolor = styleConfig.accentcolor;
        }
        if (!patch.titlefontsize && Number(styleConfig.headingfontsize || 0) > 0) {
            patch.titlefontsize = Number(styleConfig.headingfontsize);
        }
        if (!patch.subtitlefontsize && Number(styleConfig.bodyfontsize || 0) > 0) {
            patch.subtitlefontsize = Number(styleConfig.bodyfontsize);
        }
        if (!patch.paddingtop && Number(styleConfig.spacingtop || 0) > 0) {
            patch.paddingtop = Number(styleConfig.spacingtop);
        }
        if (!patch.paddingbottom && Number(styleConfig.spacingbottom || 0) > 0) {
            patch.paddingbottom = Number(styleConfig.spacingbottom);
        }
        if (!patch.cardradius && Number(styleConfig.radius || 0) > 0) {
            patch.cardradius = Number(styleConfig.radius);
        }
        return patch;
    }

    if (widget.type === "countdown") {
        const patch = {...settingsPatch};
        if (!patch.bgcolor && styleConfig.bg) {
            patch.bgcolor = styleConfig.bg;
        }
        if (!patch.headingcolor && styleConfig.headingcolor) {
            patch.headingcolor = styleConfig.headingcolor;
        }
        if (!patch.textcolor && styleConfig.textcolor) {
            patch.textcolor = styleConfig.textcolor;
        }
        if (!patch.buttoncolor && styleConfig.accentcolor) {
            patch.buttoncolor = styleConfig.accentcolor;
        }
        if (!patch.headingfontsize && Number(styleConfig.headingfontsize || 0) > 0) {
            patch.headingfontsize = Number(styleConfig.headingfontsize);
        }
        if (!patch.timerlabelfontsize && Number(styleConfig.bodyfontsize || 0) > 0) {
            patch.timerlabelfontsize = Number(styleConfig.bodyfontsize);
        }
        if (!patch.paddingtop && Number(styleConfig.spacingtop || 0) > 0) {
            patch.paddingtop = Number(styleConfig.spacingtop);
        }
        if (!patch.paddingbottom && Number(styleConfig.spacingbottom || 0) > 0) {
            patch.paddingbottom = Number(styleConfig.spacingbottom);
        }
        return patch;
    }

    if (widget.type === "trustbadges") {
        const patch = {...settingsPatch};
        if (!patch.bgcolor && styleConfig.bg) {
            patch.bgcolor = styleConfig.bg;
        }
        if (!patch.titlecolor && styleConfig.headingcolor) {
            patch.titlecolor = styleConfig.headingcolor;
        }
        if (!patch.labelcolor && styleConfig.headingcolor) {
            patch.labelcolor = styleConfig.headingcolor;
        }
        if (!patch.sublabelcolor && styleConfig.textcolor) {
            patch.sublabelcolor = styleConfig.textcolor;
        }
        if (!patch.iconcolor && styleConfig.accentcolor) {
            patch.iconcolor = styleConfig.accentcolor;
        }
        if (!patch.titlefontsize && Number(styleConfig.headingfontsize || 0) > 0) {
            patch.titlefontsize = Number(styleConfig.headingfontsize);
        }
        if (!patch.labelfontsize && Number(styleConfig.bodyfontsize || 0) > 0) {
            patch.labelfontsize = Number(styleConfig.bodyfontsize);
        }
        if (!patch.sublabelfontsize && Number(styleConfig.bodyfontsize || 0) > 0) {
            patch.sublabelfontsize = Number(styleConfig.bodyfontsize);
        }
        if (!patch.paddingtop && Number(styleConfig.spacingtop || 0) > 0) {
            patch.paddingtop = Number(styleConfig.spacingtop);
        }
        if (!patch.paddingbottom && Number(styleConfig.spacingbottom || 0) > 0) {
            patch.paddingbottom = Number(styleConfig.spacingbottom);
        }
        if (!patch.cardradius && Number(styleConfig.radius || 0) > 0) {
            patch.cardradius = Number(styleConfig.radius);
        }
        return patch;
    }

    if (widget.type !== "breadcrumb") {
        return settingsPatch;
    }

    const patch = {...settingsPatch};
    const textColour = String(styleConfig.textcolor || "");
    const headingColour = String(styleConfig.headingcolor || "");
    const accentColour = String(styleConfig.accentcolor || "");
    const genericTextColour = textColour || headingColour || String(patch.textcolor || "") || String(patch.headingcolor || "");
    const genericHeadingSize = Number(styleConfig.headingfontsize || 0);
    const genericBodySize = Number(styleConfig.bodyfontsize || 0);
    const spacingTop = Number(styleConfig.spacingtop || 0);
    const spacingBottom = Number(styleConfig.spacingbottom || 0);

    if (!patch.breadcrumbcolor && genericTextColour) {
        patch.breadcrumbcolor = genericTextColour;
    }
    if (!patch.titlecolor && (headingColour || genericTextColour)) {
        patch.titlecolor = headingColour || genericTextColour;
    }
    if (!patch.subtitlecolor && genericTextColour) {
        patch.subtitlecolor = genericTextColour;
    }
    if (!patch.accentcolor && accentColour) {
        patch.accentcolor = accentColour;
    }
    if (!patch.breadcrumbfontsize && genericBodySize > 0) {
        patch.breadcrumbfontsize = genericBodySize;
    }
    if (!patch.titlefontsize && genericHeadingSize > 0) {
        patch.titlefontsize = genericHeadingSize;
    }
    if (!patch.subtitlefontsize && genericBodySize > 0) {
        patch.subtitlefontsize = genericBodySize;
    }
    if (!patch.paddingtop && spacingTop > 0) {
        patch.paddingtop = spacingTop;
    }
    if (!patch.paddingbottom && spacingBottom > 0) {
        patch.paddingbottom = spacingBottom;
    }

    return patch;
};

const preferredVariant = (
    variants: VariantSummary[],
    preferredStyle: string,
    selectedId: number | undefined
): VariantSummary | null => {
    if (selectedId) {
        const selected = variants.find((variant) => variant.widget.id === selectedId);
        if (selected) {
            return selected;
        }
    }
    const preferred = slugify(preferredStyle);
    return variants.find((variant) => variant.styleKey === preferred || slugify(variant.label).includes(preferred))
        ?? variants[0]
        ?? null;
};

export default function StorefrontGallery(props: Props) {
    const {
        getGalleryMethod,
        pageType = "gallery",
        renderContext = {},
        defaultType = "breadcrumb",
        defaultVariantStyle = "gradient",
        exitUrl,
        showcase = [],
        catalogLabels,
        presetMethods,
        labels = {},
    } = props;

    const [data, setData] = useState<GetPageResponse | null>(null);
    const [error, setError] = useState("");
    const [activeType, setActiveType] = useState(defaultType);
    const [selectedByType, setSelectedByType] = useState<Record<string, number>>({});
    const [styleByWidget, setStyleByWidget] = useState<Record<string, GalleryStyleState>>({});
    const [presets, setPresets] = useState<WidgetPreset[]>([]);
    const [presetBusy, setPresetBusy] = useState(false);
    const [activeItemByWidget, setActiveItemByWidget] = useState<Record<string, BreadcrumbSettingItem>>({});
    const [activeVideoHeroItemByWidget, setActiveVideoHeroItemByWidget] = useState<Record<string, VideoHeroSettingItem>>({});
    const [activeCountdownItemByWidget, setActiveCountdownItemByWidget] = useState<Record<string, CountdownSettingItem>>({});
    const [activeCategoriesItemByWidget, setActiveCategoriesItemByWidget] = useState<Record<string, CategoriesSettingItem>>({});
    const [activeVisualItemByWidget, setActiveVisualItemByWidget] = useState<Record<string, string>>({});

    const contextJson = useMemo(() => JSON.stringify(renderContext ?? {}), [renderContext]);

    useEffect(() => {
        let cancelled = false;
        setError("");
        callService<GetPageResponse>(getGalleryMethod, {page: pageType, zone: "", context: contextJson})
            .then((response) => {
                if (!cancelled) {
                    setData(response);
                }
                return response;
            })
            .catch((exception: unknown) => {
                if (!cancelled) {
                    setError(exception instanceof Error ? exception.message : String(exception));
                }
            });
        return () => {
            cancelled = true;
        };
    }, [contextJson, getGalleryMethod, pageType]);

    const zonesByType = useMemo(() => {
        const map = new Map<string, ZonePayload>();
        data?.zones.forEach((zone) => map.set(zone.slug, zone));
        return map;
    }, [data]);

    const sections = useMemo(() => showcase.map((section) => ({
        ...section,
        count: zonesByType.get(section.type)?.widgets.length ?? section.stylelabels?.length ?? 0,
    })), [showcase, zonesByType]);

    const availableTypes = useMemo(() => sections.filter((section) => section.count > 0).map((section) => section.type), [sections]);
    const availableTypeKey = availableTypes.join("|");

    useEffect(() => {
        if (!availableTypes.length || availableTypes.includes(activeType)) {
            return;
        }
        setActiveType(availableTypes.includes(defaultType) ? defaultType : availableTypes[0]);
    }, [activeType, availableTypeKey, availableTypes, defaultType]);

    const activeSection = sections.find((section) => section.type === activeType) ?? sections[0] ?? null;

    const variants = useMemo<VariantSummary[]>(() => {
        const zone = zonesByType.get(activeType);
        if (!zone || !activeSection) {
            return [];
        }
        return zone.widgets.map((widget, index) => {
            const dataForWidget = parseJson<Record<string, unknown>>(widget.data, {});
            const label = labelForVariant(activeSection, widget, index);
            const styleKey = styleKeyFor(widget, dataForWidget, label);
            return {
                widget,
                label,
                styleKey,
                title: variantTitle(activeSection, label, widget),
            };
        });
    }, [activeSection, activeType, zonesByType]);

    const selectedVariant = preferredVariant(variants, defaultVariantStyle, selectedByType[activeType]);
    const selectedWidget = selectedVariant?.widget ?? null;
    const settingsKey = selectedWidget ? widgetKey(selectedWidget) : "";
    const baseStyleState = useMemo(() => selectedWidget
        ? styleStateFromWidget(selectedWidget, selectedVariant?.title ?? selectedWidget.title)
        : null, [selectedVariant?.title, selectedWidget]);
    const currentStyleState = settingsKey ? (styleByWidget[settingsKey] ?? baseStyleState) : baseStyleState;
    const currentWidgetData = useMemo(() => selectedWidget
        ? parseJson<Record<string, unknown>>(selectedWidget.data, {})
        : {}, [selectedWidget]);
    const galleryControlValue = (field: string): unknown => {
        if (!selectedWidget) {
            return "";
        }
        const patchValue = currentStyleState?.settingspatch[field];
        if (patchValue !== null && typeof patchValue !== "undefined") {
            return patchValue;
        }
        const dataValue = currentWidgetData[field];
        if (dataValue !== null && typeof dataValue !== "undefined" && dataValue !== "") {
            return dataValue;
        }
        return galleryFieldDefaults[selectedWidget.type]?.[field] ?? "";
    };
    const isGalleryControlVisible = (key: string): boolean => {
        if (!selectedWidget) {
            return false;
        }
        const rules = galleryVisibilityRules[`${selectedWidget.type}.${key}`] ?? galleryVisibilityRules[key];
        if (!rules) {
            return true;
        }
        return (Array.isArray(rules) ? rules : [rules])
            .every((rule) => passesVisibilityRule(rule, galleryControlValue(rule.field)));
    };
    const activeBreadcrumbItem = settingsKey ? (activeItemByWidget[settingsKey] ?? "breadcrumb") : "breadcrumb";
    const activeVideoHeroItem = settingsKey ? (activeVideoHeroItemByWidget[settingsKey] ?? "background") : "background";
    const activeCountdownItem = settingsKey ? (activeCountdownItemByWidget[settingsKey] ?? "background") : "background";
    const activeCategoriesItem = settingsKey ? (activeCategoriesItemByWidget[settingsKey] ?? "layout") : "layout";
    const activeVisualItem = settingsKey ? (activeVisualItemByWidget[settingsKey] ?? "") : "";
    const visibleBreadcrumbSettingItems = breadcrumbSettingItems.filter((item) =>
        item.key !== "overlay"
        || isGalleryControlVisible("overlaycolor")
        || isGalleryControlVisible("gradientstart")
        || isGalleryControlVisible("gradientend")
    );
    const effectiveBreadcrumbItem = visibleBreadcrumbSettingItems.some((item) => item.key === activeBreadcrumbItem)
        ? activeBreadcrumbItem
        : (visibleBreadcrumbSettingItems[0]?.key ?? "breadcrumb");
    const styledPreviewWidget = selectedWidget && currentStyleState ? applyStyleState(selectedWidget, currentStyleState) : selectedWidget;
    const previewWidget = styledPreviewWidget ? applyPreviewState(styledPreviewWidget, activeCountdownItem) : styledPreviewWidget;
    const previewKey = previewWidget
        ? `${previewWidget.type}:${previewWidget.id}:${previewWidget.styleconfig ?? ""}:${previewWidget.data}`
        : "";
    const visualFieldKeys = useMemo(() => {
        if (!selectedWidget) {
            return [];
        }
        const fromSettings = Object.keys(parseJson<Record<string, unknown>>(selectedWidget.settings, {}));
        const fromData = Object.keys(parseJson<Record<string, unknown>>(selectedWidget.data, {}));
        return Array.from(new Set([...fromSettings, ...fromData]))
            .filter((key) => safeKeySet.has(key) && isGalleryControlVisible(key));
    }, [currentStyleState?.settingspatch, currentWidgetData, selectedWidget]);

    useEffect(() => {
        if (!presetMethods?.list || !activeType) {
            setPresets([]);
            return;
        }
        let cancelled = false;
        callService<PresetResponse>(presetMethods.list, {type: activeType})
            .then((response) => {
                if (!cancelled) {
                    setPresets(response.presets ?? []);
                }
                return response;
            })
            .catch((exception: unknown) => setError(exception instanceof Error ? exception.message : String(exception)));
        return () => {
            cancelled = true;
        };
    }, [activeType, presetMethods?.list]);

    useEffect(() => {
        if (!selectedVariant || selectedByType[activeType] === selectedVariant.widget.id) {
            return;
        }
        setSelectedByType((current) => ({...current, [activeType]: selectedVariant.widget.id}));
    }, [activeType, selectedByType, selectedVariant]);

    const setActiveWidgetType = (type: string) => {
        setActiveType(type);
    };

    const selectVariant = (variant: VariantSummary) => {
        setSelectedByType((current) => ({...current, [variant.widget.type]: variant.widget.id}));
    };

    const updateStyleState = (changes: Partial<GalleryStyleState>) => {
        if (!selectedWidget || !currentStyleState) {
            return;
        }
        setStyleByWidget((current) => ({
            ...current,
            [widgetKey(selectedWidget)]: {...currentStyleState, ...changes},
        }));
    };

    const updateStyleConfig = (key: string, value: string | number) => {
        if (!currentStyleState) {
            return;
        }
        const next = {...currentStyleState.styleconfig};
        if (value === "") {
            delete next[key];
        } else {
            next[key] = value;
        }
        updateStyleState({styleconfig: next});
    };

    const updateSettingsPatch = (key: string, value: unknown) => {
        if (!currentStyleState) {
            return;
        }
        const next = {...currentStyleState.settingspatch};
        if (value === "") {
            delete next[key];
        } else {
            next[key] = value;
        }
        updateStyleState({settingspatch: next});
    };

    const setActiveBreadcrumbItem = (item: BreadcrumbSettingItem) => {
        if (!settingsKey) {
            return;
        }
        setActiveItemByWidget((current) => ({...current, [settingsKey]: item}));
    };

    const setActiveVideoHeroItem = (item: VideoHeroSettingItem) => {
        if (!settingsKey) {
            return;
        }
        setActiveVideoHeroItemByWidget((current) => ({...current, [settingsKey]: item}));
    };

    const setActiveCountdownItem = (item: CountdownSettingItem) => {
        if (!settingsKey) {
            return;
        }
        setActiveCountdownItemByWidget((current) => ({...current, [settingsKey]: item}));
    };

    const setActiveCategoriesItem = (item: CategoriesSettingItem) => {
        if (!settingsKey) {
            return;
        }
        setActiveCategoriesItemByWidget((current) => ({...current, [settingsKey]: item}));
    };

    const breadcrumbPatchValue = (key: string, fallbackKey = ""): unknown => {
        const explicit = currentStyleState?.settingspatch[key];
        if (explicit !== null && typeof explicit !== "undefined") {
            return explicit;
        }
        if (fallbackKey) {
            const fallback = currentStyleState?.settingspatch[fallbackKey] ?? currentWidgetData[fallbackKey];
            if (fallback !== null && typeof fallback !== "undefined") {
                return fallback;
            }
        }
        return currentWidgetData[key] ?? "";
    };

    const videoHeroPatchValue = (key: string, fallbackKey = ""): unknown => {
        const explicit = currentStyleState?.settingspatch[key];
        if (explicit !== null && typeof explicit !== "undefined") {
            return explicit;
        }
        if (fallbackKey) {
            const fallback = currentStyleState?.settingspatch[fallbackKey] ?? currentWidgetData[fallbackKey];
            if (fallback !== null && typeof fallback !== "undefined") {
                return fallback;
            }
        }
        return currentWidgetData[key] ?? "";
    };

    const countdownPatchValue = (key: string, fallbackKey = ""): unknown => {
        const explicit = currentStyleState?.settingspatch[key];
        if (explicit !== null && typeof explicit !== "undefined") {
            return explicit;
        }
        if (fallbackKey) {
            const fallback = currentStyleState?.settingspatch[fallbackKey] ?? currentWidgetData[fallbackKey];
            if (fallback !== null && typeof fallback !== "undefined") {
                return fallback;
            }
        }
        return currentWidgetData[key] ?? "";
    };

    const countdownNumberValue = (key: string, fallback = 0): number => {
        const numeric = Number(countdownPatchValue(key));
        return numeric > 0 ? numeric : fallback;
    };

    const categoriesPatchValue = (key: string, fallbackKey = ""): unknown => {
        const explicit = currentStyleState?.settingspatch[key];
        if (explicit !== null && typeof explicit !== "undefined") {
            return explicit;
        }
        if (fallbackKey) {
            const fallback = currentStyleState?.settingspatch[fallbackKey] ?? currentWidgetData[fallbackKey];
            if (fallback !== null && typeof fallback !== "undefined") {
                return fallback;
            }
        }
        return currentWidgetData[key] ?? "";
    };

    const categoriesNumberValue = (key: string, fallback = 0): number => {
        const numeric = Number(categoriesPatchValue(key));
        return numeric > 0 ? numeric : fallback;
    };

    const styleConfigValue = (key: string, fallback: unknown = ""): unknown =>
        currentStyleState?.styleconfig[key] ?? fallback;

    // The testimonial block is on unless it has been explicitly switched off (stored as 1/0).
    const videoHeroQuoteVisible = (): boolean => {
        const value = videoHeroPatchValue("showquote");
        return !(value === false || value === 0 || value === "0" || value === "false");
    };

    const currentBreadcrumbStyle = (): string =>
        String(currentStyleState?.settingspatch.style ?? currentWidgetData.style ?? "");

    const breadcrumbFontDefault = (key: "breadcrumbfontsize" | "titlefontsize" | "subtitlefontsize"): number => {
        const style = currentBreadcrumbStyle();
        if (key === "breadcrumbfontsize") {
            return style === "clean" || style === "illustration" ? 12 : 14;
        }
        if (key === "titlefontsize") {
            return style === "clean" ? 36 : 44;
        }
        return style === "clean" || style === "illustration" ? 16 : 18;
    };

    const breadcrumbFontValue = (key: "breadcrumbfontsize" | "titlefontsize" | "subtitlefontsize"): number => {
        const value = breadcrumbPatchValue(key);
        const numeric = Number(value);
        return numeric > 0 ? numeric : breadcrumbFontDefault(key);
    };

    const applyPreset = (presetId: number) => {
        const preset = presets.find((item) => item.id === presetId);
        if (!preset || !currentStyleState || !baseStyleState || !selectedWidget) {
            if (baseStyleState) {
                updateStyleState({...baseStyleState, presetId: 0});
            }
            return;
        }
        const presetStyle = parseJson<StyleConfig>(preset.styleconfig, {});
        const rawPresetPatch = parseJson<Record<string, unknown>>(preset.settingspatch, {});
        const presetPatch = presetPatchForWidget(selectedWidget, presetStyle, rawPresetPatch);
        updateStyleState({
            presetId,
            presetName: preset.name,
            styleconfig: {...baseStyleState.styleconfig, ...presetStyle},
            settingspatch: {...baseStyleState.settingspatch, ...presetPatch},
        });
    };

    const savePreset = (updateExisting: boolean) => {
        if (!presetMethods?.save || !selectedWidget || !currentStyleState) {
            return;
        }
        const name = currentStyleState.presetName.trim() || selectedVariant?.title || selectedWidget.type;
        const currentPresetId = Number(currentStyleState.presetId);
        const id = updateExisting && Number.isFinite(currentPresetId) && currentPresetId > 0 ? currentPresetId : 0;
        const visiblePatch = Object.fromEntries(Object.entries(currentStyleState.settingspatch)
            .filter(([key]) => isGalleryControlVisible(key)));
        const styleconfig = compactPresetObject(currentStyleState.styleconfig, universalStyleKeySet);
        const settingspatch = compactPresetObject(visiblePatch, safeKeySet);
        setPresetBusy(true);
        callService<SavePresetResponse>(presetMethods.save, {
            id,
            type: selectedWidget.type,
            name,
            styleconfig: JSON.stringify(styleconfig),
            settingspatch: JSON.stringify(settingspatch),
        })
            .then((response) => {
                setPresets((items) => {
                    const next = items.filter((item) => item.id !== response.preset.id);
                    return [...next, response.preset].sort((a, b) => a.name.localeCompare(b.name));
                });
                const savedStyle = parseJson<StyleConfig>(response.preset.styleconfig, {});
                const savedPatch = presetPatchForWidget(
                    selectedWidget,
                    savedStyle,
                    parseJson<Record<string, unknown>>(response.preset.settingspatch, {})
                );
                updateStyleState({
                    presetId: response.preset.id,
                    presetName: response.preset.name,
                    styleconfig: {...(baseStyleState?.styleconfig ?? currentStyleState.styleconfig), ...savedStyle},
                    settingspatch: {...(baseStyleState?.settingspatch ?? currentStyleState.settingspatch), ...savedPatch},
                });
                toast.success(response.message);
                return response;
            })
            .catch((exception: unknown) => setError(exception instanceof Error ? exception.message : String(exception)))
            .finally(() => setPresetBusy(false));
    };

    const deletePreset = () => {
        if (!presetMethods?.delete || !currentStyleState?.presetId) {
            return;
        }
        const presetId = currentStyleState.presetId;
        setPresetBusy(true);
        callService<DeletePresetResponse>(presetMethods.delete, {id: presetId})
            .then((response) => {
                setPresets((items) => items.filter((item) => item.id !== presetId));
                updateStyleState({presetId: 0});
                toast.success(response.message);
                return response;
            })
            .catch((exception: unknown) => setError(exception instanceof Error ? exception.message : String(exception)))
            .finally(() => setPresetBusy(false));
    };

    const visualChoicesForKey = (key: string): string[] => Array.from(new Set(variants.map((variant) => {
        const settings = parseJson<Record<string, unknown>>(variant.widget.settings, {});
        const dataForWidget = parseJson<Record<string, unknown>>(variant.widget.data, {});
        return String(settings[key] ?? dataForWidget[key] ?? "");
    }).filter(Boolean)));

    const renderControl = (
        key: string,
        label: string,
        value: unknown,
        onChange: (value: string | number | boolean) => void,
        choices: string[] = []
    ) => {
        const current = value === null || typeof value === "undefined" ? "" : String(value);
        const selectChoices = gallerySelectChoices[selectedWidget?.type ?? ""]?.[key] ?? choices;
        if (colorKey(key)) {
            return (
                <label className="mcg-field" key={key}>
                    <span>{label}</span>
                    <div className="mcg-color-field">
                        <input type="color" value={hexColour(current) ? current : "#1565c0"}
                            aria-label={`${label} colour picker`}
                            onChange={(event) => onChange(event.currentTarget.value)} />
                        <input type="text" value={current} placeholder="#1565c0 or var(--mc-primary)"
                            onChange={(event) => onChange(event.currentTarget.value)} />
                    </div>
                </label>
            );
        }
        if (boolKey(key)) {
            return (
                <label className="mcg-switch" key={key}>
                    <span>{label}</span>
                    <input type="checkbox" checked={boolValue(value)}
                        onChange={(event) => onChange(event.currentTarget.checked)} />
                    <i aria-hidden="true" />
                </label>
            );
        }
        if (key === "navicon") {
            const iconChoices = Array.from(new Set([...choices, current].filter(Boolean)));
            return <IconPicker key={key} label={label} value={current}
                options={iconChoices.map((choice) => ({value: choice, label: titleCase(choice)}))}
                onChange={(next) => onChange(next)} />;
        }
        if (key === "align" || key === "alignment") {
            return (
                <label className="mcg-field" key={key}>
                    <span>{label}</span>
                    <select value={current} onChange={(event) => onChange(event.currentTarget.value)}>
                        <option value="">Default</option>
                        <option value="left">{l(labels, "alignleft")}</option>
                        <option value="center">{l(labels, "aligncenter")}</option>
                        <option value="right">Right</option>
                    </select>
                </label>
            );
        }
        if (key === "mode") {
            return (
                <label className="mcg-field" key={key}>
                    <span>{label}</span>
                    <select value={current} onChange={(event) => onChange(event.currentTarget.value)}>
                        <option value="">Default</option>
                        <option value="light">Light</option>
                        <option value="dark">Dark</option>
                    </select>
                </label>
            );
        }
        if (selectChoices.length > 0 && !numberKey(key)) {
            return (
                <label className="mcg-field" key={key}>
                    <span>{label}</span>
                    <select value={current} onChange={(event) => onChange(event.currentTarget.value)}>
                        <option value="">Default</option>
                        {selectChoices.map((choice) => (
                            <option key={choice} value={choice}>{titleCase(choice)}</option>
                        ))}
                    </select>
                </label>
            );
        }
        if (numberKey(key)) {
            const max = key === "columns" || key === "visiblecards"
                ? 6
                : (key.endsWith("borderwidth") || key === "panelborderwidth" ? 24
                    : (key === "overlayopacity" ? 100 : (key.includes("font") || key === "iconsize" ? 96 : 240)));
            return (
                <label className="mcg-field" key={key}>
                    <span>{label}</span>
                    <input type="number" min={0} max={max} value={current}
                        onChange={(event) => onChange(event.currentTarget.value === ""
                            ? ""
                            : Number(event.currentTarget.value))} />
                </label>
            );
        }
        return (
            <label className="mcg-field" key={key}>
                <span>{label}</span>
                <input type="text" value={current} onChange={(event) => onChange(event.currentTarget.value)} />
            </label>
        );
    };

    const renderTransparencyControl = () => {
        const defaultOpacity = currentBreadcrumbStyle() === "gradient" ? 88 : 52;
        const rawOpacity = breadcrumbPatchValue("overlayopacity");
        const opacity = rawOpacity === "" || rawOpacity === null || typeof rawOpacity === "undefined"
            ? defaultOpacity
            : Math.max(0, Math.min(100, Number(rawOpacity)));
        const transparency = 100 - opacity;
        return (
            <label className="mcg-field mcg-range-field">
                <span>{l(labels, "breadcrumb_transparency")}</span>
                <div className="mcg-range-field__control">
                    <input type="range" min={0} max={100} value={transparency}
                        onChange={(event) => updateSettingsPatch("overlayopacity", 100 - Number(event.currentTarget.value))} />
                    <output>{transparency}%</output>
                </div>
            </label>
        );
    };

    const renderBreadcrumbItemControls = () => {
        switch (effectiveBreadcrumbItem) {
            case "background":
                return (
                    <>
                        {renderControl("bgcolor", l(labels, "visual_bgcolor", "Background colour"),
                            breadcrumbPatchValue("bgcolor"), (value) => updateSettingsPatch("bgcolor", value))}
                        {renderControl("textcolor", l(labels, "visual_textcolor", "Base text colour"),
                            breadcrumbPatchValue("textcolor"), (value) => updateSettingsPatch("textcolor", value))}
                        {renderControl("accentcolor", l(labels, "visual_accentcolor", "Accent colour"),
                            breadcrumbPatchValue("accentcolor"), (value) => updateSettingsPatch("accentcolor", value))}
                    </>
                );
            case "title":
                return (
                    <>
                        {renderControl("titlecolor", l(labels, "breadcrumb_fontcolor"),
                            breadcrumbPatchValue("titlecolor", "textcolor"), (value) => updateSettingsPatch("titlecolor", value))}
                        {renderControl("titlefontsize", l(labels, "breadcrumb_fontsize"),
                            breadcrumbFontValue("titlefontsize"), (value) => updateSettingsPatch("titlefontsize", value))}
                    </>
                );
            case "subtitle":
                return (
                    <>
                        {renderControl("subtitlecolor", l(labels, "breadcrumb_fontcolor"),
                            breadcrumbPatchValue("subtitlecolor", "textcolor"), (value) => updateSettingsPatch("subtitlecolor", value))}
                        {renderControl("subtitlefontsize", l(labels, "breadcrumb_fontsize"),
                            breadcrumbFontValue("subtitlefontsize"), (value) => updateSettingsPatch("subtitlefontsize", value))}
                    </>
                );
            case "overlay":
                return (
                    <>
                        {isGalleryControlVisible("overlaycolor") && renderControl(
                            "overlaycolor",
                            l(labels, "breadcrumb_overlaycolor"),
                            breadcrumbPatchValue("overlaycolor"),
                            (value) => updateSettingsPatch("overlaycolor", value)
                        )}
                        {isGalleryControlVisible("gradientstart") && renderControl(
                            "gradientstart",
                            l(labels, "visual_gradientstart"),
                            breadcrumbPatchValue("gradientstart"),
                            (value) => updateSettingsPatch("gradientstart", value)
                        )}
                        {isGalleryControlVisible("gradientend") && renderControl(
                            "gradientend",
                            l(labels, "visual_gradientend"),
                            breadcrumbPatchValue("gradientend"),
                            (value) => updateSettingsPatch("gradientend", value)
                        )}
                        {renderTransparencyControl()}
                    </>
                );
            case "padding":
                return (
                    <>
                        {renderControl("paddingtop", l(labels, "breadcrumb_paddingtop"),
                            breadcrumbPatchValue("paddingtop"), (value) => updateSettingsPatch("paddingtop", value))}
                        {renderControl("paddingbottom", l(labels, "breadcrumb_paddingbottom"),
                            breadcrumbPatchValue("paddingbottom"), (value) => updateSettingsPatch("paddingbottom", value))}
                    </>
                );
            case "position":
                return renderControl("alignment", l(labels, "breadcrumb_textposition"),
                    breadcrumbPatchValue("alignment"), (value) => updateSettingsPatch("alignment", value));
            case "breadcrumb":
            default:
                return (
                    <>
                        {renderControl("breadcrumbcolor", l(labels, "breadcrumb_fontcolor"),
                            breadcrumbPatchValue("breadcrumbcolor", "textcolor"), (value) => updateSettingsPatch("breadcrumbcolor", value))}
                        {renderControl("breadcrumbfontsize", l(labels, "breadcrumb_fontsize"),
                            breadcrumbFontValue("breadcrumbfontsize"), (value) => updateSettingsPatch("breadcrumbfontsize", value))}
                    </>
                );
        }
    };

    const renderVideoHeroItemControls = () => {
        switch (activeVideoHeroItem) {
            case "heading":
                return (
                    <>
                        {renderControl("headingcolor", l(labels, "videohero_headingcolor"),
                            styleConfigValue("headingcolor"), (value) => updateStyleConfig("headingcolor", value))}
                        {renderControl("headingfontsize", l(labels, "videohero_headingsize"),
                            styleConfigValue("headingfontsize", 44), (value) => updateStyleConfig("headingfontsize", value))}
                    </>
                );
            case "body":
                return (
                    <>
                        {renderControl("textcolor", l(labels, "videohero_textcolor"),
                            styleConfigValue("textcolor"), (value) => updateStyleConfig("textcolor", value))}
                        {renderControl("bodyfontsize", l(labels, "videohero_bodysize"),
                            styleConfigValue("bodyfontsize", 18), (value) => updateStyleConfig("bodyfontsize", value))}
                    </>
                );
            case "buttons":
                return (
                    <>
                        {renderControl(
                            "primarybuttoncolor",
                            l(labels, "videohero_primarybuttoncolor"),
                            videoHeroPatchValue("primarybuttoncolor", "accentcolor"),
                            (value) => updateSettingsPatch("primarybuttoncolor", value)
                        )}
                        {renderControl(
                            "primarybuttontextcolor",
                            l(labels, "videohero_primarybuttontextcolor"),
                            videoHeroPatchValue("primarybuttontextcolor"),
                            (value) => updateSettingsPatch("primarybuttontextcolor", value)
                        )}
                        {renderControl(
                            "secondarybuttoncolor",
                            l(labels, "videohero_secondarybuttoncolor"),
                            videoHeroPatchValue("secondarybuttoncolor"),
                            (value) => updateSettingsPatch("secondarybuttoncolor", value)
                        )}
                        {renderControl(
                            "secondarybuttontextcolor",
                            l(labels, "videohero_secondarybuttontextcolor"),
                            videoHeroPatchValue("secondarybuttontextcolor"),
                            (value) => updateSettingsPatch("secondarybuttontextcolor", value)
                        )}
                    </>
                );
            case "panel":
                return renderControl("radius", l(labels, "videohero_panelradius"),
                    styleConfigValue("radius", 0), (value) => updateStyleConfig("radius", value));
            case "infocard":
                return (
                    <>
                        <label className="mcg-switch" key="showquote">
                            <span>{l(labels, "videohero_showquote")}</span>
                            <input type="checkbox" checked={videoHeroQuoteVisible()}
                                onChange={(event) => updateSettingsPatch("showquote",
                                    event.currentTarget.checked ? 1 : 0)} />
                            <i aria-hidden="true" />
                        </label>
                        {renderControl(
                            "infocardbgcolor",
                            l(labels, "videohero_infocardbgcolor"),
                            videoHeroPatchValue("infocardbgcolor"),
                            (value) => updateSettingsPatch("infocardbgcolor", value)
                        )}
                        {renderControl(
                            "infoiconbgcolor",
                            l(labels, "videohero_infoiconbgcolor"),
                            videoHeroPatchValue("infoiconbgcolor"),
                            (value) => updateSettingsPatch("infoiconbgcolor", value)
                        )}
                        {renderControl(
                            "infoiconcolor",
                            l(labels, "videohero_infoiconcolor"),
                            videoHeroPatchValue("infoiconcolor"),
                            (value) => updateSettingsPatch("infoiconcolor", value)
                        )}
                        {renderControl(
                            "infoheadingcolor",
                            l(labels, "videohero_infoheadingcolor"),
                            videoHeroPatchValue("infoheadingcolor"),
                            (value) => updateSettingsPatch("infoheadingcolor", value)
                        )}
                        {renderControl(
                            "infoheadingfontsize",
                            l(labels, "videohero_infoheadingsize"),
                            videoHeroPatchValue("infoheadingfontsize"),
                            (value) => updateSettingsPatch("infoheadingfontsize", value)
                        )}
                        {renderControl(
                            "infotextcolor",
                            l(labels, "videohero_infotextcolor"),
                            videoHeroPatchValue("infotextcolor"),
                            (value) => updateSettingsPatch("infotextcolor", value)
                        )}
                    </>
                );
            case "spacing":
                return (
                    <>
                        {renderControl("spacingtop", l(labels, "videohero_spacingtop"),
                            styleConfigValue("spacingtop", 0), (value) => updateStyleConfig("spacingtop", value))}
                        {renderControl("spacingbottom", l(labels, "videohero_spacingbottom"),
                            styleConfigValue("spacingbottom", 0), (value) => updateStyleConfig("spacingbottom", value))}
                    </>
                );
            case "background":
            default:
                return (
                    <>
                        {renderControl("bg", l(labels, "videohero_sectionbgcolor"),
                            styleConfigValue("bg"), (value) => updateStyleConfig("bg", value))}
                        {renderControl("bgcolor", l(labels, "videohero_bgcolor"),
                            videoHeroPatchValue("bgcolor"), (value) => updateSettingsPatch("bgcolor", value))}
                        {renderControl("accentcolor", l(labels, "videohero_accentcolor"),
                            videoHeroPatchValue("accentcolor", "accent"), (value) => updateSettingsPatch("accentcolor", value))}
                    </>
                );
        }
    };

    const renderCountdownItemControls = () => {
        switch (activeCountdownItem) {
            case "heading":
                return (
                    <>
                        {renderControl("headingcolor", l(labels, "countdown_headingcolor"),
                            countdownPatchValue("headingcolor", "textcolor"), (value) => updateSettingsPatch("headingcolor", value))}
                        {renderControl("headingfontsize", l(labels, "countdown_headingfontsize"),
                            countdownNumberValue("headingfontsize", 17), (value) => updateSettingsPatch("headingfontsize", value))}
                    </>
                );
            case "timer":
                return (
                    <>
                        {renderControl("timerbgcolor", l(labels, "countdown_timerbgcolor"),
                            countdownPatchValue("timerbgcolor"), (value) => updateSettingsPatch("timerbgcolor", value))}
                        {renderControl("timernumbercolor", l(labels, "countdown_timernumbercolor"),
                            countdownPatchValue("timernumbercolor", "textcolor"), (value) => updateSettingsPatch("timernumbercolor", value))}
                        {renderControl("timernumberfontsize", l(labels, "countdown_timernumberfontsize"),
                            countdownNumberValue("timernumberfontsize", 22), (value) => updateSettingsPatch("timernumberfontsize", value))}
                        {renderControl("timerlabelcolor", l(labels, "countdown_timerlabelcolor"),
                            countdownPatchValue("timerlabelcolor", "textcolor"), (value) => updateSettingsPatch("timerlabelcolor", value))}
                        {renderControl("timerlabelfontsize", l(labels, "countdown_timerlabelfontsize"),
                            countdownNumberValue("timerlabelfontsize", 10), (value) => updateSettingsPatch("timerlabelfontsize", value))}
                    </>
                );
            case "button":
                return (
                    <>
                        {renderControl("buttoncolor", l(labels, "countdown_buttoncolor"),
                            countdownPatchValue("buttoncolor", "accentcolor"), (value) => updateSettingsPatch("buttoncolor", value))}
                        {renderControl("buttontextcolor", l(labels, "countdown_buttontextcolor"),
                            countdownPatchValue("buttontextcolor"), (value) => updateSettingsPatch("buttontextcolor", value))}
                    </>
                );
            case "expired":
                return (
                    <>
                        {renderControl("expiredbgcolor", l(labels, "countdown_expiredbgcolor"),
                            countdownPatchValue("expiredbgcolor"), (value) => updateSettingsPatch("expiredbgcolor", value))}
                        {renderControl("expiredtextcolor", l(labels, "countdown_expiredtextcolor"),
                            countdownPatchValue("expiredtextcolor", "textcolor"), (value) => updateSettingsPatch("expiredtextcolor", value))}
                    </>
                );
            case "spacing":
                return (
                    <>
                        {renderControl("paddingtop", l(labels, "countdown_paddingtop"),
                            countdownPatchValue("paddingtop"), (value) => updateSettingsPatch("paddingtop", value))}
                        {renderControl("paddingbottom", l(labels, "countdown_paddingbottom"),
                            countdownPatchValue("paddingbottom"), (value) => updateSettingsPatch("paddingbottom", value))}
                    </>
                );
            case "background":
            default:
                return (
                    <>
                        {renderControl("bgcolor", l(labels, "countdown_bgcolor"),
                            countdownPatchValue("bgcolor"), (value) => updateSettingsPatch("bgcolor", value))}
                        {renderControl("textcolor", l(labels, "countdown_textcolor"),
                            countdownPatchValue("textcolor"), (value) => updateSettingsPatch("textcolor", value))}
                    </>
                );
        }
    };

    const renderCategoriesItemControls = () => {
        switch (activeCategoriesItem) {
            case "heading":
                return (
                    <>
                        {renderControl("titlecolor", l(labels, "categories_titlecolor"),
                            categoriesPatchValue("titlecolor"), (value) => updateSettingsPatch("titlecolor", value))}
                        {renderControl("titlefontsize", l(labels, "categories_titlefontsize"),
                            categoriesNumberValue("titlefontsize", 36), (value) => updateSettingsPatch("titlefontsize", value))}
                        {renderControl("subtitlecolor", l(labels, "categories_subtitlecolor"),
                            categoriesPatchValue("subtitlecolor"), (value) => updateSettingsPatch("subtitlecolor", value))}
                        {renderControl("subtitlefontsize", l(labels, "categories_subtitlefontsize"),
                            categoriesNumberValue("subtitlefontsize", 18), (value) => updateSettingsPatch("subtitlefontsize", value))}
                    </>
                );
            case "cards":
                return (
                    <>
                        {renderControl("cardbgcolor", l(labels, "categories_cardbgcolor"),
                            categoriesPatchValue("cardbgcolor"), (value) => updateSettingsPatch("cardbgcolor", value))}
                        {renderControl("cardtextcolor", l(labels, "categories_cardtextcolor"),
                            categoriesPatchValue("cardtextcolor"), (value) => updateSettingsPatch("cardtextcolor", value))}
                        {renderControl("cardtextfontsize", l(labels, "categories_cardtextfontsize"),
                            categoriesNumberValue("cardtextfontsize", 20), (value) => updateSettingsPatch("cardtextfontsize", value))}
                        {renderControl("cardradius", l(labels, "categories_cardradius"),
                            categoriesPatchValue("cardradius"), (value) => updateSettingsPatch("cardradius", value))}
                    </>
                );
            case "icons":
                return (
                    <>
                        {renderControl("iconbgcolor", l(labels, "categories_iconbgcolor"),
                            categoriesPatchValue("iconbgcolor"), (value) => updateSettingsPatch("iconbgcolor", value))}
                        {isGalleryControlVisible("iconcolor") && renderControl(
                            "iconcolor",
                            l(labels, "categories_iconcolor"),
                            categoriesPatchValue("iconcolor"),
                            (value) => updateSettingsPatch("iconcolor", value)
                        )}
                        {renderControl("iconsize", l(labels, "categories_iconsize"),
                            categoriesNumberValue("iconsize", 26), (value) => updateSettingsPatch("iconsize", value))}
                    </>
                );
            case "count":
                return (
                    <>
                        {renderControl("countcolor", l(labels, "categories_countcolor"),
                            categoriesPatchValue("countcolor"), (value) => updateSettingsPatch("countcolor", value))}
                        {renderControl("countfontsize", l(labels, "categories_countfontsize"),
                            categoriesNumberValue("countfontsize", 14), (value) => updateSettingsPatch("countfontsize", value))}
                    </>
                );
            case "spacing":
                return (
                    <>
                        {renderControl("paddingtop", l(labels, "categories_paddingtop"),
                            categoriesPatchValue("paddingtop"), (value) => updateSettingsPatch("paddingtop", value))}
                        {renderControl("paddingbottom", l(labels, "categories_paddingbottom"),
                            categoriesPatchValue("paddingbottom"), (value) => updateSettingsPatch("paddingbottom", value))}
                    </>
                );
            case "layout":
            default:
                return (
                    <>
                        {renderControl("style", l(labels, "categories_style"),
                            categoriesPatchValue("style"), (value) => updateSettingsPatch("style", value),
                            visualChoicesForKey("style"))}
                        {isGalleryControlVisible("visiblecards") && renderControl(
                            "visiblecards",
                            l(labels, "categories_visiblecards"),
                            categoriesPatchValue("visiblecards"),
                            (value) => updateSettingsPatch("visiblecards", value)
                        )}
                        {renderControl("bgcolor", l(labels, "categories_bgcolor"),
                            categoriesPatchValue("bgcolor"), (value) => updateSettingsPatch("bgcolor", value))}
                    </>
                );
        }
    };

    const renderGalleryVisualProfile = () => {
        if (!selectedWidget || !currentStyleState || !settingsKey) {
            return null;
        }
        const profile = galleryVisualProfiles[selectedWidget.type];
        if (!profile) {
            return null;
        }
        const visibleStyleKeys = (section: GalleryVisualSection): string[] =>
            (section.styleKeys ?? []).filter((key) => universalStyleKeySet.has(key) && isGalleryControlVisible(key));
        const visiblePatchKeys = (section: GalleryVisualSection): string[] =>
            (section.patchKeys ?? []).filter((key) => safeKeySet.has(key) && isGalleryControlVisible(key));
        const sections = profile.filter((section) =>
            visibleStyleKeys(section).length > 0 || visiblePatchKeys(section).length > 0
        );
        const activeSection = sections.find((section) => section.key === activeVisualItem) ?? sections[0];
        if (!activeSection) {
            return null;
        }
        const styleKeys = visibleStyleKeys(activeSection);
        const patchKeys = visiblePatchKeys(activeSection);
        return (
            <div className="mcg-settings__section">
                <h3>{l(labels, "visualsettings")}</h3>
                <div className="mcg-item-picker" role="tablist"
                    aria-label={l(labels, "visualsettings")}>
                    {sections.map((section) => (
                        <button key={section.key} type="button" role="tab"
                            aria-selected={activeSection.key === section.key}
                            className={activeSection.key === section.key ? "is-active" : ""}
                            onClick={() => setActiveVisualItemByWidget((items) => ({...items, [settingsKey]: section.key}))}>
                            <i className={`bi ${section.icon}`} aria-hidden="true" />
                            <span>{l(labels, `visual_item_${section.key}`, section.label)}</span>
                        </button>
                    ))}
                </div>
                <div className="mcg-control-grid mcg-control-grid--focused">
                    {styleKeys.map((key) => {
                        const field = universalStyleFields.find((candidate) => candidate.key === key);
                        return renderControl(
                            key,
                            l(labels, `style_${key}`, field?.label ?? visualFieldLabels[key] ?? titleCase(key)),
                            currentStyleState.styleconfig[key],
                            (value) => updateStyleConfig(key, value)
                        );
                    })}
                    {patchKeys.map((key) => renderControl(
                        key,
                        l(labels, `visual_${key}`, visualFieldLabels[key] ?? titleCase(key)),
                        currentStyleState.settingspatch[key],
                        (value) => updateSettingsPatch(key, value),
                        visualChoicesForKey(key)
                    ))}
                </div>
            </div>
        );
    };

    return (
        <div className="mcg-app">
            <aside className="mcg-sidebar" aria-label={l(labels, "sidebarnav")}>
                <div className="mcg-brand">
                    <span className="mcg-brand__mark local-moderncommerce-admin-sidebar__logo" aria-hidden="true">
                        <i className="bi bi-cart3" />
                    </span>
                    <span>
                        <small>{l(labels, "brandlabel")}</small>
                        <strong>{l(labels, "title")}</strong>
                    </span>
                </div>

                <div className="mcg-sidebar__section">
                    <h2>{l(labels, "sidebarnav")}</h2>
                    <nav className="mcg-nav">
                        {sections.map((section) => (
                            <button key={section.type} type="button"
                                className={`mcg-nav__item${activeType === section.type ? " is-active" : ""}`}
                                aria-current={activeType === section.type ? "page" : undefined}
                                onClick={() => setActiveWidgetType(section.type)}>
                                <span className="mcg-nav__label">
                                    <i className={`bi ${typeIcons[section.type] ?? "bi-square"}`} aria-hidden="true" />
                                    <span>{section.label}</span>
                                </span>
                                <b>{section.count}</b>
                            </button>
                        ))}
                    </nav>
                </div>
            </aside>

            <main className="mcg-main">
                <header className="mcg-hero">
                    <div>
                        <span className="mcg-hero__eyebrow">{l(labels, "eyebrow")}</span>
                        <h1>{l(labels, "title")}</h1>
                        <p>{l(labels, "intro")}</p>
                    </div>
                    <div className="mcg-hero__actions">
                        <a className="mcg-btn mcg-btn--soft" href={exitUrl}>{l(labels, "exit")}</a>
                    </div>
                </header>

                {error && (
                    <div className="mcg-alert" role="alert">
                        <strong>{l(labels, "errortitle")}</strong>
                        <span>{error}</span>
                    </div>
                )}

                {!data && !error && (
                    <div className="mcg-loading" aria-live="polite">
                        <span className="mcg-loading__bar" />
                        <span className="mcg-loading__bar" />
                        <span className="mcg-loading__bar" />
                    </div>
                )}

                {data && activeSection && (
                    <div className="mcg-workspace">
                        <section className="mcg-panel mcg-panel--variants" aria-labelledby="mcg-widget-heading">
                            <div className="mcg-panel__head">
                                <div>
                                    <h2 id="mcg-widget-heading">{activeSection.label}</h2>
                                    <p>{typeDescriptions[activeSection.type] ?? l(labels, "genericdesc")}</p>
                                </div>
                            </div>

                            <section className="mcg-selected">
                                <div className={`mcg-preview-shell mcg-preview-shell--${activeType}${currentStyleState?.fullWidth ? " mcg-preview-shell--wide" : ""}`}>
                                    {previewWidget ? (
                                        <div className="local-moderncommerce-storefront local-moderncommerce-storefront--gallery">
                                            <div className={`mw-zone-render mw-zone-render--${activeType}`}>
                                                <RenderedWidget key={previewKey} widget={previewWidget} catalogLabels={catalogLabels}
                                                    editing={false} typeLabel={activeSection.label} onGear={() => undefined}
                                                    previewMode={true} />
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="mcg-empty">{l(labels, "emptyvariants")}</div>
                                    )}
                                </div>
                            </section>
                        </section>

                        <aside className="mcg-settings" aria-labelledby="mcg-settings-heading">
                            <div className="mcg-settings__intro">
                                <h2 id="mcg-settings-heading">{l(labels, "variantsettings")}</h2>
                                <p>{l(labels, "variantsettingsdesc")}</p>
                            </div>

                            {selectedWidget && selectedVariant && currentStyleState && (
                                <div className="mcg-settings__form">
                                    <label className="mcg-field">
                                        <span>{l(labels, "selectedvariant")}</span>
                                        <select value={selectedWidget.id}
                                            onChange={(event) => {
                                                const next = variants.find((variant) => variant.widget.id === Number(event.currentTarget.value));
                                                if (next) {
                                                    selectVariant(next);
                                                }
                                            }}>
                                            {variants.map((variant) => (
                                                <option key={variant.widget.id} value={variant.widget.id}>{variant.title}</option>
                                            ))}
                                        </select>
                                    </label>

                                    {presetMethods?.list && (
                                        <label className="mcg-field">
                                            <span>{l(labels, "widgetpreset")}</span>
                                            <select value={currentStyleState.presetId}
                                                onChange={(event) => applyPreset(Number(event.currentTarget.value))}>
                                                <option value={0}>{l(labels, "widgetpresetnone")}</option>
                                                {presets.map((preset) => (
                                                    <option key={preset.id} value={preset.id}>{preset.name}</option>
                                                ))}
                                            </select>
                                        </label>
                                    )}

                                    <div className="mcg-settings__section">
                                        <h3>{l(labels, "presetactions")}</h3>
                                        <label className="mcg-field">
                                            <span>{l(labels, "presetname")}</span>
                                            <input type="text" value={currentStyleState.presetName}
                                                onChange={(event) => updateStyleState({presetName: event.currentTarget.value})} />
                                        </label>
                                        <div className="mcg-action-grid">
                                            <button type="button" className="mcg-btn mcg-btn--primary"
                                                disabled={presetBusy || !presetMethods?.save}
                                                onClick={() => savePreset(false)}>
                                                {l(labels, "savepreset")}
                                            </button>
                                            <button type="button" className="mcg-btn mcg-btn--soft"
                                                disabled={presetBusy || !presetMethods?.save || !currentStyleState.presetId}
                                                onClick={() => savePreset(true)}>
                                                {l(labels, "updatepreset")}
                                            </button>
                                            <button type="button" className="mcg-btn mcg-btn--danger"
                                                disabled={presetBusy || !presetMethods?.delete || !currentStyleState.presetId}
                                                onClick={deletePreset}>
                                                {l(labels, "deletepreset")}
                                            </button>
                                        </div>
                                    </div>

                                    {selectedWidget.type === "breadcrumb" ? (
                                        <div className="mcg-settings__section">
                                            <h3>{l(labels, "breadcrumb_edititem")}</h3>
                                            <div className="mcg-item-picker" role="tablist"
                                                aria-label={l(labels, "breadcrumb_edititem")}>
                                                {visibleBreadcrumbSettingItems.map((item) => (
                                                    <button key={item.key} type="button" role="tab"
                                                        aria-selected={effectiveBreadcrumbItem === item.key}
                                                        className={effectiveBreadcrumbItem === item.key ? "is-active" : ""}
                                                        onClick={() => setActiveBreadcrumbItem(item.key)}>
                                                        <i className={`bi ${item.icon}`} aria-hidden="true" />
                                                        <span>{l(labels, `breadcrumb_item_${item.key}`, item.label)}</span>
                                                    </button>
                                                ))}
                                            </div>
                                            <div className="mcg-control-grid mcg-control-grid--focused">
                                                {renderBreadcrumbItemControls()}
                                            </div>
                                        </div>
                                    ) : selectedWidget.type === "videohero" ? (
                                        <div className="mcg-settings__section">
                                            <h3>{l(labels, "videohero_edititem")}</h3>
                                            <div className="mcg-item-picker" role="tablist"
                                                aria-label={l(labels, "videohero_edititem")}>
                                                {videoHeroSettingItems.map((item) => (
                                                    <button key={item.key} type="button" role="tab"
                                                        aria-selected={activeVideoHeroItem === item.key}
                                                        className={activeVideoHeroItem === item.key ? "is-active" : ""}
                                                        onClick={() => setActiveVideoHeroItem(item.key)}>
                                                        <i className={`bi ${item.icon}`} aria-hidden="true" />
                                                        <span>{l(labels, `videohero_item_${item.key}`, item.label)}</span>
                                                    </button>
                                                ))}
                                            </div>
                                            <div className="mcg-control-grid mcg-control-grid--focused">
                                                {renderVideoHeroItemControls()}
                                            </div>
                                        </div>
                                    ) : selectedWidget.type === "countdown" ? (
                                        <div className="mcg-settings__section">
                                            <h3>{l(labels, "countdown_edititem")}</h3>
                                            <div className="mcg-item-picker" role="tablist"
                                                aria-label={l(labels, "countdown_edititem")}>
                                                {countdownSettingItems.map((item) => (
                                                    <button key={item.key} type="button" role="tab"
                                                        aria-selected={activeCountdownItem === item.key}
                                                        className={activeCountdownItem === item.key ? "is-active" : ""}
                                                        onClick={() => setActiveCountdownItem(item.key)}>
                                                        <i className={`bi ${item.icon}`} aria-hidden="true" />
                                                        <span>{l(labels, `countdown_item_${item.key}`, item.label)}</span>
                                                    </button>
                                                ))}
                                            </div>
                                            <div className="mcg-control-grid mcg-control-grid--focused">
                                                {renderCountdownItemControls()}
                                            </div>
                                        </div>
                                    ) : selectedWidget.type === "categories" ? (
                                        <div className="mcg-settings__section">
                                            <h3>{l(labels, "categories_edititem")}</h3>
                                            <div className="mcg-item-picker" role="tablist"
                                                aria-label={l(labels, "categories_edititem")}>
                                                {categoriesSettingItems.map((item) => (
                                                    <button key={item.key} type="button" role="tab"
                                                        aria-selected={activeCategoriesItem === item.key}
                                                        className={activeCategoriesItem === item.key ? "is-active" : ""}
                                                        onClick={() => setActiveCategoriesItem(item.key)}>
                                                        <i className={`bi ${item.icon}`} aria-hidden="true" />
                                                        <span>{l(labels, `categories_item_${item.key}`, item.label)}</span>
                                                    </button>
                                                ))}
                                            </div>
                                            <div className="mcg-control-grid mcg-control-grid--focused">
                                                {renderCategoriesItemControls()}
                                            </div>
                                        </div>
                                    ) : (
                                        renderGalleryVisualProfile() ?? <>
                                            <div className="mcg-settings__section">
                                                <h3>{l(labels, "universalstyles")}</h3>
                                                <div className="mcg-control-grid">
                                                    {universalStyleFields.map((field) => renderControl(
                                                        field.key,
                                                        l(labels, `style_${field.key}`, field.label),
                                                        currentStyleState.styleconfig[field.key],
                                                        (value) => updateStyleConfig(field.key, value)
                                                    ))}
                                                </div>
                                            </div>

                                            {visualFieldKeys.length > 0 && (
                                                <div className="mcg-settings__section">
                                                    <h3>{l(labels, "visualsettings")}</h3>
                                                    <div className="mcg-control-grid">
                                                        {visualFieldKeys.map((key) => renderControl(
                                                            key,
                                                            l(labels, `visual_${key}`, visualFieldLabels[key] ?? titleCase(key)),
                                                            currentStyleState.settingspatch[key],
                                                            (value) => updateSettingsPatch(key, value),
                                                            visualChoicesForKey(key)
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {visualFieldKeys.length === 0 && (
                                                <div className="mcg-settings__note">
                                                    {l(labels, "nofields")}
                                                </div>
                                            )}
                                        </>
                                    )}

                                    <div className="mcg-settings__section">
                                        <h3>{l(labels, "previewoptions")}</h3>
                                        <label className="mcg-switch">
                                            <span>{l(labels, "fullwidthpreview")}</span>
                                            <input type="checkbox" checked={currentStyleState.fullWidth}
                                                onChange={(event) => updateStyleState({fullWidth: event.currentTarget.checked})} />
                                            <i aria-hidden="true" />
                                        </label>
                                    </div>
                                </div>
                            )}
                        </aside>
                    </div>
                )}
            </main>
        </div>
    );
}

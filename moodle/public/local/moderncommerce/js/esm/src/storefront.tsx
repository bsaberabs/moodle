// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Modern Commerce storefront landing page: renders all widget zones in one root and
 * hosts the in-page Customize drawer + per-widget React editors (manager edit mode).
 *
 * @module     local_moderncommerce/storefront
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties, FC, FormEvent, useEffect, useId, useMemo, useState} from "react";
import {createPortal} from "react-dom";
import {McButton} from "./button";
import {McDrawer} from "./drawer";
import {IconPicker, normIcon} from "./icon_picker";
import type {IconOption as SharedIconOption} from "./icon_picker";
import {HeroSlider, SliderData, sliderDataDefaults} from "./storefront/slider";
import ProductCarousel from "./storefront/product_carousel";
import TrustStrip from "./storefront/trustbadges";
import PartnerLogos from "./storefront/partners";
import CountdownBar from "./storefront/countdown";
import TestimonialGrid from "./storefront/testimonials";
import LeaderMessage from "./storefront/leadermessage";
import FeatureGrid, {FeatureCard} from "./storefront/featuregrid";
import SubscriptionPlans, {SubscriptionPlan, PlanCycle} from "./storefront/subscription_plans";
import CategoryTiles from "./storefront/categories";
import InstructorSpotlight from "./storefront/instructors";
import NewsletterForm from "./storefront/newsletter";
import RecaptchaField, {getRecaptchaResponse, recaptchaConfigDefaults, RecaptchaConfig} from "./storefront/recaptcha";
import VideoHero, {VideoHeroData} from "./storefront/videohero";
import Footer, {FooterData, footerDataDefaults} from "./storefront/footer";
import Catalog from "./catalog";
import {FieldError} from "./form";
import {
    email as emailRule, integer, max, maxLength, min, minLength, number, required,
    runRules, slug as slugRule, url as urlRule, when, widgetColour,
} from "./validation";
import type {FieldErrorMap, Rule, RuleMap} from "./validation";

declare const M: { cfg: { sesskey: string; wwwroot: string } };

export type Labels = Record<string, string>;
export type WidgetInstance = {
    id: number; type: string; sortorder: number; title: string; subtitle: string;
    settings: string; styleconfig?: string; data: string; bg: string; spacingtop: number; spacingbottom: number;
};
export type ZonePayload = { slug: string; widgets: WidgetInstance[] };
export type GetPageResponse = { zones: ZonePayload[]; warnings: unknown[] };

type LayoutWidget = {
    id: number; type: string; typelabel: string; zone: string; enabled: boolean;
    sortorder: number; title: string; pagetype?: string; scope?: "page" | "global";
};
type ZoneOption = { slug: string; label: string };
type TypeOption = { key: string; label: string };
type LayoutResponse = { widgets: LayoutWidget[]; zones: ZoneOption[]; types: TypeOption[] };
const GLOBAL_PAGE = "global";
const GLOBAL_ZONE_SLUGS = ["global_top", "global_bottom"];
const isGlobalZone = (slug: string): boolean => GLOBAL_ZONE_SLUGS.includes(slug);
const isGlobalLayoutRow = (row: LayoutWidget): boolean =>
    row.scope === "global" || row.pagetype === GLOBAL_PAGE || isGlobalZone(row.zone);
const zonesForScope = (zones: ZoneOption[], scope: "page" | "global"): ZoneOption[] =>
    zones.filter((zone) => scope === "global" ? isGlobalZone(zone.slug) : !isGlobalZone(zone.slug));
const zonesForNewWidget = (zones: ZoneOption[], pageType: string): ZoneOption[] =>
    zonesForScope(zones, pageType === GLOBAL_PAGE ? "global" : "page");

export type FieldDef = {
    name: string; label: string; type: string; default?: unknown;
    choices?: Record<string, string>; fields?: FieldDef[];
    showwhen?: FieldVisibilityRule | FieldVisibilityRule[];
    // Optional validation metadata, supplied by the PHP widget schema. Declaring
    // it here rather than hand-writing rules per widget means every widget type
    // gains validation from one place.
    required?: boolean;
    minlength?: number; maxlength?: number;
    min?: number; max?: number;
    format?: "email" | "url" | "hex" | "integer" | "number" | "slug";
};
type FieldVisibilityValue = string | number | boolean;
type FieldVisibilityRule = {
    field: string;
    equals?: FieldVisibilityValue | FieldVisibilityValue[];
    notequals?: FieldVisibilityValue | FieldVisibilityValue[];
    truthy?: boolean;
};
type IconOption = SharedIconOption;
type Slide = {
    image: string; imagesource?: string; imageurl?: string; imagefile?: string;
    heading: string; subheading: string; ctalabel: string;
    ctaurl: string; ctastyle: string; bgcolor: string; enabled: number;
};
export type StyleConfig = Record<string, string | number>;
export type WidgetPreset = {
    id: number;
    type: string;
    name: string;
    styleconfig: string;
    settingspatch: string;
    timemodified: number;
};
type PresetResponse = { presets: WidgetPreset[]; warnings: unknown[] };
type WidgetEditorData = {
    id: number; type: string; fields: FieldDef[]; values: Record<string, unknown>;
    pagetype?: string; styleconfig: StyleConfig; slides: Slide[];
};
type SliderEditorItem = "design" | "motion" | "controls" | "button" | "slides" | "appearance" | "spacing";
type BreadcrumbEditorItem = "background" | "breadcrumb" | "title" | "subtitle" | "overlay" | "padding" | "position";
type VideoHeroEditorItem = "background" | "heading" | "body" | "buttons" | "panel" | "infocard" | "spacing";
type CountdownEditorItem = "background" | "heading" | "timer" | "button" | "expired" | "spacing";
type CategoriesEditorItem = "layout" | "heading" | "cards" | "icons" | "count" | "content" | "spacing";
type FeaturedEditorItem = "heading" | "products" | "layout" | "cards" | "button" | "appearance" | "spacing";
type GenericEditorItem = "content" | "media" | "layout" | "appearance" | "spacing" | "slides";
type FocusedEditorSection = {
    key: string;
    label: string;
    icon: string;
    fields?: string[];
    includeUniversal?: boolean;
    slideEditor?: boolean;
};
const breadcrumbEditorItems: Array<{key: BreadcrumbEditorItem; label: string; icon: string}> = [
    {key: "background", label: "Background", icon: "bi-palette"},
    {key: "breadcrumb", label: "Breadcrumb", icon: "bi-signpost-split"},
    {key: "title", label: "Title", icon: "bi-type-h1"},
    {key: "subtitle", label: "Subtitle", icon: "bi-text-paragraph"},
    {key: "overlay", label: "Overlay", icon: "bi-layers"},
    {key: "padding", label: "Padding", icon: "bi-arrows-expand"},
    {key: "position", label: "Position", icon: "bi-text-center"},
];
const videoHeroEditorItems: Array<{key: VideoHeroEditorItem; label: string; icon: string}> = [
    {key: "background", label: "Background", icon: "bi-palette"},
    {key: "heading", label: "Heading", icon: "bi-type-h1"},
    {key: "body", label: "Body text", icon: "bi-body-text"},
    {key: "buttons", label: "Buttons", icon: "bi-cursor"},
    {key: "panel", label: "Video panel", icon: "bi-play-btn"},
    {key: "infocard", label: "Info card", icon: "bi-info-square"},
    {key: "spacing", label: "Spacing", icon: "bi-arrows-expand"},
];
const countdownEditorItems: Array<{key: CountdownEditorItem; label: string; icon: string}> = [
    {key: "background", label: "Background", icon: "bi-palette"},
    {key: "heading", label: "Heading", icon: "bi-type-h1"},
    {key: "timer", label: "Timer", icon: "bi-hourglass-split"},
    {key: "button", label: "Button", icon: "bi-cursor"},
    {key: "expired", label: "Expired state", icon: "bi-clock-history"},
    {key: "spacing", label: "Spacing", icon: "bi-arrows-expand"},
];
const categoriesEditorItems: Array<{key: CategoriesEditorItem; label: string; icon: string}> = [
    {key: "layout", label: "Layout", icon: "bi-grid-3x3-gap"},
    {key: "heading", label: "Heading", icon: "bi-type-h2"},
    {key: "cards", label: "Cards", icon: "bi-collection"},
    {key: "icons", label: "Icons", icon: "bi-stars"},
    {key: "count", label: "Count text", icon: "bi-123"},
    {key: "content", label: "Categories", icon: "bi-folder2-open"},
    {key: "spacing", label: "Spacing", icon: "bi-arrows-expand"},
];
const featuredEditorItems: Array<{key: FeaturedEditorItem; label: string; icon: string}> = [
    {key: "heading", label: "Heading", icon: "bi-type-h2"},
    {key: "products", label: "Products", icon: "bi-box-seam"},
    {key: "layout", label: "Layout", icon: "bi-layout-three-columns"},
    {key: "cards", label: "Cards", icon: "bi-collection"},
    {key: "button", label: "Button", icon: "bi-cursor"},
    {key: "appearance", label: "Appearance", icon: "bi-palette"},
    {key: "spacing", label: "Spacing", icon: "bi-arrows-expand"},
];
const genericEditorItems: Array<{key: GenericEditorItem; label: string; icon: string}> = [
    {key: "content", label: "Content", icon: "bi-input-cursor-text"},
    {key: "media", label: "Media", icon: "bi-image"},
    {key: "layout", label: "Layout", icon: "bi-layout-three-columns"},
    {key: "appearance", label: "Appearance", icon: "bi-palette"},
    {key: "spacing", label: "Spacing", icon: "bi-arrows-expand"},
    {key: "slides", label: "Slides", icon: "bi-collection-play"},
];
const sliderEditorItems: Array<{key: SliderEditorItem; label: string; icon: string}> = [
    {key: "design", label: "Design", icon: "bi-sliders"},
    {key: "motion", label: "Motion", icon: "bi-play-circle"},
    {key: "controls", label: "Controls", icon: "bi-toggles"},
    {key: "button", label: "Button", icon: "bi-cursor"},
    {key: "slides", label: "Slides", icon: "bi-collection-play"},
    {key: "appearance", label: "Appearance", icon: "bi-palette"},
    {key: "spacing", label: "Spacing", icon: "bi-arrows-expand"},
];
const remainingEditorProfiles: Record<string, FocusedEditorSection[]> = {
    slider: [
        {key: "design", label: "Design", icon: "bi-sliders", fields: ["title", "design"]},
        {key: "motion", label: "Motion", icon: "bi-play-circle", fields: ["autoplay", "interval"]},
        {key: "controls", label: "Controls", icon: "bi-toggles", fields: ["showarrows", "showdots"]},
        {key: "button", label: "Button", icon: "bi-cursor",
            fields: ["buttoncolor", "buttontextcolor", "buttonfontsize", "buttonradius"]},
        {key: "appearance", label: "Appearance", icon: "bi-palette", includeUniversal: true},
        {key: "slides", label: "Slides", icon: "bi-collection-play", slideEditor: true},
    ],
    featured: [
        {key: "heading", label: "Heading", icon: "bi-type-h2", fields: ["title", "subtitle"]},
        {key: "products", label: "Products", icon: "bi-box-seam", fields: ["coursetype", "categoryid", "sort", "perpage"]},
        {key: "layout", label: "Layout", icon: "bi-layout-three-columns",
            fields: ["align", "layout", "columns", "navposition"]},
        {key: "cards", label: "Cards", icon: "bi-collection",
            fields: ["cardbgcolor", "cardbordercolor", "cardborderwidth"]},
        {key: "button", label: "Button", icon: "bi-cursor", fields: ["buttoncolor", "buttontextcolor"]},
        {key: "appearance", label: "Appearance", icon: "bi-palette", includeUniversal: true},
    ],
    related: [
        {key: "heading", label: "Heading", icon: "bi-type-h2", fields: ["title", "subtitle"]},
        {key: "products", label: "Products", icon: "bi-box-seam", fields: ["coursetype", "categoryid", "sort", "perpage"]},
        {key: "layout", label: "Layout", icon: "bi-layout-three-columns",
            fields: ["align", "layout", "columns", "navposition"]},
        {key: "cards", label: "Cards", icon: "bi-collection",
            fields: ["cardbgcolor", "cardbordercolor", "cardborderwidth"]},
        {key: "button", label: "Button", icon: "bi-cursor", fields: ["buttoncolor", "buttontextcolor"]},
        {key: "appearance", label: "Appearance", icon: "bi-palette", includeUniversal: true},
    ],
    trustbadges: [
        {key: "badges", label: "Badges", icon: "bi-patch-check", fields: ["title", "badges"]},
        {key: "background", label: "Background", icon: "bi-palette", fields: ["bgcolor"]},
        {key: "title", label: "Title", icon: "bi-type-h2", fields: ["titlecolor", "titlefontsize"]},
        {key: "card", label: "Card", icon: "bi-credit-card-2-front",
            fields: ["cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius"]},
        {key: "icons", label: "Icon style", icon: "bi-shield-check", fields: ["iconbgcolor", "iconcolor", "iconsize"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["labelcolor", "labelfontsize", "sublabelcolor", "sublabelfontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    partners: [
        {key: "layout", label: "Layout", icon: "bi-layout-three-columns",
            fields: ["style", "visiblelogos", "speed", "pauseonhover"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            fields: ["heading", "headingcolor", "headingfontsize"]},
        {key: "logos", label: "Logo style", icon: "bi-image",
            fields: ["logos", "greyscale", "logoheight", "logogap"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand",
            fields: ["bgcolor", "paddingtop", "paddingbottom"]},
    ],
    testimonials: [
        {key: "background", label: "Background", icon: "bi-palette", fields: ["bgcolor"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            fields: ["title", "subtitle", "titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "testimonials", label: "Testimonials", icon: "bi-chat-quote", fields: ["testimonials"]},
        {key: "cards", label: "Cards", icon: "bi-collection",
            fields: ["cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius", "ratingcolor"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["quotecolor", "quotefontsize", "avatarbgcolor", "avatarcolor",
                "namecolor", "namefontsize", "rolecolor", "rolefontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    instructors: [
        {key: "background", label: "Background", icon: "bi-palette", fields: ["bgcolor"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            fields: ["title", "subtitle", "titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "instructors", label: "Instructors", icon: "bi-person-video3", fields: ["instructors"]},
        {key: "cards", label: "Cards", icon: "bi-collection",
            fields: ["cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius"]},
        {key: "avatar", label: "Avatar", icon: "bi-person-circle", fields: ["avatarbgcolor", "avatarcolor"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["namecolor", "namefontsize", "rolecolor", "rolefontsize", "biocolor", "biofontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    newsletter: [
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            fields: ["title", "subtitle", "heading", "description"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["titlecolor", "titlefontsize", "textcolor", "textfontsize"]},
        {key: "form", label: "Form", icon: "bi-envelope",
            fields: ["placeholder", "buttonlabel", "successmessage", "inputbgcolor", "inputbordercolor",
                "inputtextcolor", "placeholdercolor"]},
        {key: "button", label: "Button", icon: "bi-cursor", fields: ["buttoncolor", "buttontextcolor", "buttonradius"]},
        {key: "panel", label: "Panel", icon: "bi-window",
            fields: ["bgcolor", "panelbgcolor", "panelbordercolor", "panelborderwidth", "panelradius",
                "panelpaddingtop", "panelpaddingright", "panelpaddingbottom", "panelpaddingleft"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    mediastorycarousel: [
        {key: "layout", label: "Layout", icon: "bi-layout-split", fields: ["mediaposition", "navicon"]},
        {key: "panel", label: "Panel", icon: "bi-window",
            fields: ["bgcolor", "cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius", "mediaradius"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["titlecolor", "titlefontsize", "textcolor", "textfontsize"]},
        {key: "navigation", label: "Navigation", icon: "bi-arrow-left-right", fields: ["iconcolor", "iconbgcolor"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
        {key: "slides", label: "Slides", icon: "bi-collection-play", fields: ["slides"]},
    ],
    content: [
        {key: "intro", label: "Intro", icon: "bi-type",
            fields: ["eyebrow", "title", "subtitle", "icon"]},
        {key: "body", label: "Body & Media", icon: "bi-card-image",
            fields: ["body", "imagesource", "imagefile", "imageurl"]},
        {key: "button", label: "Button", icon: "bi-cursor",
            fields: ["ctalabel", "ctaurl", "buttoncolor", "buttontextcolor", "buttonradius"]},
        {key: "layout", label: "Layout", icon: "bi-layout-text-window",
            fields: ["layout", "mediaposition", "bgcolor", "panelbgcolor", "panelbordercolor", "cardradius",
                "mediaradius"]},
        {key: "icon", label: "Icon", icon: "bi-stars", fields: ["iconbgcolor", "iconcolor", "iconsize"]},
        {key: "benefits", label: "Benefits", icon: "bi-list-ol",
            fields: ["benefits", "benefitnumbercolor", "benefitnumberfontsize", "benefittitlecolor",
                "benefittitlefontsize", "benefittextcolor", "benefittextfontsize", "benefitbordercolor"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize", "textcolor", "textfontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand",
            fields: ["paddingtop", "paddingbottom", "paddingleft", "paddingright"]},
    ],
    leadermessage: [
        {key: "layout", label: "Layout", icon: "bi-layout-split",
            fields: ["style", "mediaposition", "showphoto", "mediawidth", "mediaradius", "justifytext",
                "photosource", "photofile", "photourl"]},
        {key: "visibility", label: "Visible elements", icon: "bi-eye",
            fields: ["showeyebrow", "showtitle", "showattribution", "showsignature", "showcta", "showaccent"]},
        {key: "content", label: "Message", icon: "bi-body-text",
            fields: ["eyebrow", "title", "message", "leadername", "leaderrole"]},
        {key: "signature", label: "Signature", icon: "bi-pen",
            fields: ["signaturesource", "signaturefile", "signatureurl"]},
        {key: "background", label: "Background", icon: "bi-palette", fields: ["bgcolor", "accentcolor"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["eyebrowcolor", "eyebrowfontsize", "titlecolor", "titlefontsize", "textcolor", "textfontsize",
                "namecolor", "namefontsize", "rolecolor", "rolefontsize"]},
        {key: "button", label: "Button", icon: "bi-cursor",
            fields: ["ctalabel", "ctaurl", "buttoncolor", "buttontextcolor", "buttonradius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    featuregrid: [
        {key: "layout", label: "Layout", icon: "bi-grid-3x3-gap",
            fields: ["style", "columns", "showheader", "centercards"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            fields: ["title", "subtitle", "titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "cards", label: "Cards", icon: "bi-collection",
            fields: ["cards", "cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius"]},
        {key: "icons", label: "Icons", icon: "bi-stars", fields: ["iconbgcolor", "iconcolor", "iconsize"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["cardtitlecolor", "cardtitlefontsize", "textcolor", "textfontsize", "linkcolor"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand",
            fields: ["bgcolor", "paddingtop", "paddingbottom"]},
    ],
    subscriptionplans: [
        {key: "layout", label: "Layout", icon: "bi-layout-three-columns",
            fields: ["style", "columns", "showheader", "showdescription", "showfeatures", "showtrial", "showpromo",
                "highlightfeatured"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            fields: ["title", "subtitle", "titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "plans", label: "Plans", icon: "bi-card-list",
            fields: ["billingcycle", "defaultcycle", "monthlylabel", "yearlylabel", "savingslabel", "plancodes",
                "maxplans", "maxfeatures", "featuredlabel"]},
        {key: "cards", label: "Cards", icon: "bi-window",
            fields: ["bgcolor", "cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius", "accentcolor"]},
        {key: "pricing", label: "Pricing", icon: "bi-cash-coin",
            fields: ["plannamecolor", "plannamefontsize", "pricecolor", "pricefontsize", "cyclecolor"]},
        {key: "features", label: "Features", icon: "bi-check2-square",
            fields: ["textcolor", "textfontsize", "featurecolor", "featureiconcolor"]},
        {key: "button", label: "Button", icon: "bi-cursor",
            fields: ["ctalabel", "ctaurl", "buttoncolor", "buttontextcolor", "buttonradius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    learningpromise: [
        {key: "background", label: "Background", icon: "bi-palette", fields: ["bgcolor"]},
        {key: "copy", label: "Copy", icon: "bi-body-text", fields: ["title", "body"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["headingcolor", "headingfontsize", "textcolor", "textfontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    belief: [
        {key: "background", label: "Background", icon: "bi-palette", fields: ["bgcolor"]},
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            fields: ["title", "subtitle", "titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "points", label: "Points", icon: "bi-stars", fields: ["items", "closing"]},
        {key: "icons", label: "Icons", icon: "bi-patch-check", fields: ["iconcolor", "iconsize"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["textcolor", "textfontsize", "labelcolor", "labelfontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    policy: [
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            fields: ["title", "subtitle", "effectivedate", "titlecolor", "titlefontsize",
                "subtitlecolor", "subtitlefontsize"]},
        {key: "sections", label: "Sections", icon: "bi-file-text", fields: ["sections"]},
        {key: "cards", label: "Content", icon: "bi-file-earmark-text",
            fields: ["bgcolor", "cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["labelcolor", "labelfontsize", "textcolor", "textfontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    faq: [
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            fields: ["title", "subtitle", "titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "questions", label: "Questions", icon: "bi-question-circle", fields: ["items"]},
        {key: "items", label: "Items", icon: "bi-list-check",
            fields: ["bgcolor", "itembgcolor", "itembordercolor", "cardborderwidth", "cardradius", "iconcolor"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["questioncolor", "labelfontsize", "answercolor", "textfontsize"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    cta: [
        {key: "copy", label: "Copy", icon: "bi-body-text", fields: ["heading", "text"]},
        {key: "layout", label: "Tone", icon: "bi-megaphone", fields: ["tone"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["titlecolor", "titlefontsize", "textcolor", "textfontsize"]},
        {key: "buttons", label: "Buttons", icon: "bi-cursor",
            fields: ["primarylabel", "primaryurl", "secondarylabel", "secondaryurl",
                "primarybuttoncolor", "primarybuttontextcolor", "secondarybuttoncolor", "secondarybuttontextcolor",
                "buttonradius"]},
        {key: "appearance", label: "Appearance", icon: "bi-palette",
            fields: ["bgcolor", "cardradius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    supportform: [
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            fields: ["heading", "description"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["titlecolor", "titlefontsize", "textcolor", "textfontsize", "formlabelcolor"]},
        {key: "form", label: "Form", icon: "bi-ui-checks",
            fields: ["messagelabel", "messageplaceholder",
                "inputbgcolor", "inputbordercolor", "inputtextcolor"]},
        {key: "buttons", label: "Buttons", icon: "bi-cursor",
            fields: ["buttonlabel", "emailbuttonlabel", "buttoncolor", "buttontextcolor",
                "secondarybuttoncolor", "secondarybuttontextcolor", "buttonradius"]},
        {key: "panel", label: "Panel", icon: "bi-window",
            fields: ["bgcolor", "cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    contactcards: [
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            fields: ["title", "subtitle", "titlecolor", "titlefontsize", "subtitlecolor", "subtitlefontsize"]},
        {key: "cards", label: "Cards", icon: "bi-postcard",
            fields: ["cards", "bgcolor", "cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius"]},
        {key: "icons", label: "Icons", icon: "bi-patch-check", fields: ["iconbgcolor", "iconcolor", "iconsize"]},
        {key: "text", label: "Text", icon: "bi-fonts",
            fields: ["labelcolor", "labelfontsize", "textcolor", "textfontsize", "linkcolor"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    footer: [
        {key: "layout", label: "Style", icon: "bi-layout-three-columns", fields: ["style", "mode", "logoheight"]},
        {key: "brand", label: "Brand", icon: "bi-building",
            fields: ["logosource", "logo", "brandname", "description"]},
        {key: "contact", label: "Contact", icon: "bi-telephone", fields: ["address", "phone", "email"]},
        {key: "links", label: "Links", icon: "bi-link-45deg", fields: ["columns", "appstitle", "googleplayurl", "appstoreurl", "social"]},
        {key: "enterprise", label: "Enterprise", icon: "bi-shield-check", fields: ["languagelabel", "subscribeplaceholder", "compliancelabel"]},
        {key: "legal", label: "Legal", icon: "bi-c-circle", fields: ["copyright"]},
        {key: "appearance", label: "Appearance", icon: "bi-palette",
            fields: ["bgcolor", "panelbgcolor", "titlecolor", "titlefontsize", "textcolor", "textfontsize",
                "linkcolor", "iconbgcolor", "iconcolor"]},
        {key: "subscribe", label: "Subscribe", icon: "bi-envelope",
            fields: ["inputbgcolor", "inputbordercolor", "inputtextcolor", "buttoncolor", "buttontextcolor"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand", fields: ["paddingtop", "paddingbottom"]},
    ],
    catalog: [
        {key: "heading", label: "Heading", icon: "bi-type-h2",
            fields: ["title", "titlecolor", "titlefontsize", "textcolor", "textfontsize", "accentcolor",
                "eyebrowcolor"]},
        {key: "hero", label: "Hero", icon: "bi-window",
            fields: ["showheader", "herobgcolor", "herobordercolor", "heroradius", "heropanelbgcolor",
                "heropanelbordercolor", "heropaneltextcolor", "heropanelaccentcolor",
                "heropanelvaluecolor", "heropanelvaluefontsize"]},
        {key: "layout", label: "Layout", icon: "bi-layout-sidebar", fields: ["perpage", "sidebarposition"]},
        {key: "cards", label: "Course cards", icon: "bi-collection",
            fields: ["cardbgcolor", "cardbordercolor", "cardborderwidth", "cardradius", "cardfooterbgcolor", "cardtitlecolor",
                "cardtitlefontsize", "cardtextcolor", "cardmetabgcolor", "cardmetatextcolor",
                "ratingcolor", "ratingtextcolor", "pricecolor", "originalpricecolor"]},
        {key: "buttons", label: "Buttons", icon: "bi-cursor",
            fields: ["buttoncolor", "buttontextcolor", "buttonradius"]},
        {key: "badges", label: "Badges", icon: "bi-tags",
            fields: ["badgebgcolor", "badgebordercolor", "badgetextcolor", "badgeradius", "badgefontsize",
                "coursebadgebgcolor", "coursebadgebordercolor", "coursebadgetextcolor",
                "programbadgebgcolor", "programbadgebordercolor", "programbadgetextcolor",
                "bundlebadgebgcolor", "bundlebadgebordercolor", "bundlebadgetextcolor"]},
        {key: "filters", label: "Filters", icon: "bi-funnel",
            fields: ["filterbgcolor", "filterbordercolor", "filterborderwidth", "filterradius",
                "filtertitlecolor", "filtertextcolor", "inputbgcolor", "inputbordercolor", "inputtextcolor", "placeholdercolor"]},
        {key: "tabs", label: "Tabs", icon: "bi-segmented-nav",
            fields: ["tabbgcolor", "tabbordercolor", "tabtextcolor", "tabactivebgcolor", "tabactivetextcolor"]},
        {key: "appearance", label: "Appearance", icon: "bi-palette", fields: ["bgcolor"]},
        {key: "spacing", label: "Spacing", icon: "bi-arrows-expand",
            fields: ["paddingtop", "paddingbottom", "paddingleft", "paddingright", "margintop", "marginbottom"]},
    ],
};
const isFocusedEditorType = (type?: string): boolean =>
    Boolean(type && (["breadcrumb", "videohero", "countdown", "categories"].includes(type)
        || Object.prototype.hasOwnProperty.call(remainingEditorProfiles, type)));
const breadcrumbStyleChoices: Array<{value: string; label: string}> = [
    {value: "imagehero", label: "Image Hero Banner"},
    {value: "clean", label: "Clean Minimal Banner"},
    {value: "gradient", label: "Gradient Media Banner"},
    {value: "pastel", label: "Pastel Title Band"},
    {value: "illustration", label: "Illustrated Learning Banner"},
];
const breadcrumbHexColour = (value: unknown, fallback = "#1565c0"): string => {
    const raw = String(value ?? "");
    return /^#[0-9a-fA-F]{6}$/.test(raw) ? raw : fallback;
};
const breadcrumbFontDefault = (
    style: string,
    key: "breadcrumbfontsize" | "titlefontsize" | "subtitlefontsize"
): number => {
    if (key === "breadcrumbfontsize") {
        return style === "clean" || style === "illustration" ? 12 : 14;
    }
    if (key === "titlefontsize") {
        return style === "clean" ? 36 : 44;
    }
    return style === "clean" || style === "illustration" ? 16 : 18;
};
const breadcrumbPatchFromPreset = (
    styleConfig: StyleConfig,
    settingsPatch: Record<string, unknown>
): Record<string, unknown> => {
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
const videoHeroPatchFromPreset = (
    styleConfig: StyleConfig,
    settingsPatch: Record<string, unknown>
): Record<string, unknown> => {
    const patch = {...settingsPatch};
    if (!patch.bgcolor && styleConfig.bg) {
        patch.bgcolor = styleConfig.bg;
    }
    if (!patch.accentcolor && styleConfig.accentcolor) {
        patch.accentcolor = styleConfig.accentcolor;
    }
    return patch;
};
const countdownPatchFromPreset = (
    styleConfig: StyleConfig,
    settingsPatch: Record<string, unknown>
): Record<string, unknown> => {
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
};
const categoriesPatchFromPreset = (
    styleConfig: StyleConfig,
    settingsPatch: Record<string, unknown>
): Record<string, unknown> => {
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
};
const trustBadgesPatchFromPreset = (
    styleConfig: StyleConfig,
    settingsPatch: Record<string, unknown>
): Record<string, unknown> => {
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
};

type Props = {
    getPageMethod: string;
    layoutGetMethod: string;
    layoutSaveMethod: string;
    widgetGetMethod: string;
    widgetSaveMethod: string;
    presetListMethod?: string;
    addMethod: string;
    deleteMethod: string;
    uploadMethod: string;
    videoUploadUrl: string;
    iconOptions: IconOption[];
    pageType?: string;
    // When set, the app renders only this single zone (display-only band, e.g. a global footer).
    onlyZone?: string;
    renderContext?: Record<string, string | number | boolean>;
    editing?: boolean;
    canManage?: boolean;
    showToolbar?: boolean;
    toolbarTargetId?: string;
    catalogLabels: Labels;
    labels: Labels;
};

export const callService = async <T, >(methodName: string, args: unknown): Promise<T> => {
    const query = new URLSearchParams({sesskey: M.cfg.sesskey, info: methodName});
    const response = await fetch(`${M.cfg.wwwroot}/lib/ajax/service.php?${query.toString()}`, {
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
        throw new Error(first.exception?.message ?? "Modern Commerce service request failed.");
    }
    return (first.data ?? first) as T;
};

export const parseJson = <T, >(raw: string, fallback: T): T => {
    if (!raw) {
        return fallback;
    }
    try {
        return JSON.parse(raw) as T;
    } catch {
        return fallback;
    }
};

const presetPatchForType = (
    type: string,
    styleConfig: StyleConfig,
    settingsPatch: Record<string, unknown>
): Record<string, unknown> => {
    if (type === "breadcrumb") {
        return breadcrumbPatchFromPreset(styleConfig, settingsPatch);
    }
    if (type === "videohero") {
        return videoHeroPatchFromPreset(styleConfig, settingsPatch);
    }
    if (type === "countdown") {
        return countdownPatchFromPreset(styleConfig, settingsPatch);
    }
    if (type === "categories") {
        return categoriesPatchFromPreset(styleConfig, settingsPatch);
    }
    if (type === "trustbadges") {
        return trustBadgesPatchFromPreset(styleConfig, settingsPatch);
    }
    return settingsPatch;
};

const presetIdFromValues = (values: Record<string, unknown>): number => {
    const id = Number(values.presetid ?? 0);
    return Number.isFinite(id) && id > 0 ? Math.round(id) : 0;
};

const comparablePresetValue = (value: unknown): string => {
    if (typeof value === "boolean") {
        return value ? "1" : "0";
    }
    if (typeof value === "number") {
        return String(value);
    }
    return String(value ?? "");
};

const currentPresetId = (
    type: string,
    values: Record<string, unknown>,
    styleConfig: StyleConfig,
    presets: WidgetPreset[]
): number => {
    const savedId = presetIdFromValues(values);
    if (savedId > 0 && presets.some((preset) => preset.id === savedId && preset.type === type)) {
        return savedId;
    }
    const match = presets.find((preset) => {
        if (preset.type !== type) {
            return false;
        }
        const style = parseJson<StyleConfig>(preset.styleconfig, {});
        const patch = presetPatchForType(type, style, parseJson<Record<string, unknown>>(preset.settingspatch, {}));
        const styleEntries = Object.entries(style);
        const patchEntries = Object.entries(patch);
        if (styleEntries.length === 0 && patchEntries.length === 0) {
            return false;
        }
        return styleEntries.every(([key, value]) => comparablePresetValue(styleConfig[key]) === comparablePresetValue(value))
            && patchEntries.every(([key, value]) => comparablePresetValue(values[key]) === comparablePresetValue(value));
    });
    return match?.id ?? 0;
};

export const universalStyleFields = [
    {key: "bg", label: "Background colour", type: "color", defaultValue: ""},
    {key: "headingcolor", label: "Heading colour", type: "color", defaultValue: ""},
    {key: "textcolor", label: "Body text colour", type: "color", defaultValue: ""},
    {key: "accentcolor", label: "Accent colour", type: "color", defaultValue: ""},
    {key: "headingfontsize", label: "Heading font size (px)", type: "number", defaultValue: 0},
    {key: "bodyfontsize", label: "Body font size (px)", type: "number", defaultValue: 0},
    {key: "spacingtop", label: "Padding top (px)", type: "number", defaultValue: 0},
    {key: "spacingbottom", label: "Padding bottom (px)", type: "number", defaultValue: 0},
    {key: "radius", label: "Border radius (px)", type: "number", defaultValue: 0},
] as const;

export const safePresetFieldNames = [
    "style", "design", "layout", "mode", "align", "alignment", "navposition", "columns",
    "tone", "theme", "mediaposition", "sidebarposition", "navicon",
    "bgcolor", "textcolor", "headingcolor", "accentcolor", "overlaycolor", "gradientstart",
    "gradientend", "breadcrumbcolor", "titlecolor", "subtitlecolor", "paddingtop", "paddingbottom",
    "paddingleft", "paddingright",
    "cardradius", "breadcrumbfontsize", "titlefontsize", "subtitlefontsize", "overlayopacity",
    "herobgcolor", "herobordercolor", "heroradius", "eyebrowcolor", "heropanelbgcolor",
    "heropanelbordercolor", "heropaneltextcolor", "heropanelaccentcolor",
    "heropanelvaluecolor", "heropanelvaluefontsize",
    "primarybuttoncolor", "primarybuttontextcolor", "secondarybuttoncolor", "secondarybuttontextcolor",
    "showquote", "showheader", "showcount", "showarrows", "showdots", "showfeatures",
    "showdescription", "showtrial", "showpromo", "highlightfeatured",
    "showeyebrow", "showtitle", "justifytext", "showphoto", "showattribution",
    "showsignature", "showcta", "showaccent",
    "autoplay", "greyscale", "pauseonhover", "centercards",
    "infocardbgcolor", "infoiconbgcolor", "infoiconcolor", "infoheadingcolor", "infoheadingfontsize",
    "infotextcolor", "headingfontsize", "timerbgcolor", "timernumbercolor", "timernumberfontsize",
    "timerlabelcolor", "timerlabelfontsize", "buttoncolor", "buttontextcolor", "buttonfontsize",
    "buttonradius", "expiredbgcolor", "expiredtextcolor", "visiblecards", "iconcolor", "iconbgcolor", "iconsize", "cardbgcolor",
    "cardbordercolor", "cardborderwidth", "cardfooterbgcolor", "cardtitlecolor", "cardtitlefontsize", "cardtextcolor",
    "cardtextfontsize", "cardmetabgcolor", "cardmetatextcolor", "labelcolor", "labelfontsize",
    "sublabelcolor", "sublabelfontsize", "countcolor", "countfontsize", "margintop", "marginbottom", "logoheight",
    "visiblelogos", "speed", "logogap",
    "benefitnumbercolor", "benefitnumberfontsize", "benefittitlecolor", "benefittitlefontsize",
    "benefittextcolor", "benefittextfontsize", "benefitbordercolor",
    "panelbgcolor", "panelbordercolor", "panelborderwidth", "panelradius", "panelpaddingtop",
    "panelpaddingright", "panelpaddingbottom", "panelpaddingleft", "inputbgcolor", "inputbordercolor", "inputtextcolor",
    "placeholdercolor", "formlabelcolor", "linkcolor", "ratingcolor", "ratingtextcolor", "originalpricecolor", "avatarbgcolor", "avatarcolor",
    "quotecolor", "quotefontsize", "textfontsize", "namecolor", "namefontsize", "rolecolor", "rolefontsize",
    "biocolor", "biofontsize", "mediaradius", "eyebrowfontsize", "mediawidth",
    "questioncolor", "answercolor", "itembgcolor", "itembordercolor",
    "pricecolor", "badgebgcolor", "badgebordercolor", "badgetextcolor", "badgeradius", "badgefontsize",
    "plannamecolor", "plannamefontsize", "pricefontsize", "cyclecolor", "featurecolor", "featureiconcolor",
    "coursebadgebgcolor", "coursebadgebordercolor", "coursebadgetextcolor",
    "programbadgebgcolor", "programbadgebordercolor", "programbadgetextcolor",
    "bundlebadgebgcolor", "bundlebadgebordercolor", "bundlebadgetextcolor",
    "filterbgcolor", "filterbordercolor",
    "filterborderwidth", "filterradius", "filtertitlecolor", "filtertextcolor", "tabbgcolor", "tabbordercolor", "tabtextcolor",
    "tabactivebgcolor", "tabactivetextcolor",
];
const safePresetKeys = new Set(safePresetFieldNames);

export const visualFieldsForPreset = (fields: FieldDef[]): FieldDef[] =>
    fields.filter((field) => safePresetKeys.has(field.name) && field.type !== "list");

export const extractSettingsPatch = (values: Record<string, unknown>, fields: FieldDef[]): Record<string, unknown> => {
    const allowed = new Set(visualFieldsForPreset(fields).map((field) => field.name));
    return Object.fromEntries(Object.entries(values).filter(([key]) => allowed.has(key)));
};

export const mergePresetIntoData = (
    rawData: Record<string, unknown>,
    settingsPatch: Record<string, unknown>
): Record<string, unknown> => {
    const next = {...rawData, ...settingsPatch};
    if (next.displaysettings && typeof next.displaysettings === "object") {
        next.displaysettings = {...(next.displaysettings as Record<string, unknown>), ...settingsPatch};
    }
    return next;
};

/**
 * Build a render-only widget while its settings drawer is open.
 *
 * The page response contains resolver output, whereas the editor owns raw schema
 * values. Merging the current values into both the resolved payload and its
 * displaysettings envelope gives controls immediate visual feedback without
 * mutating the saved page response. Closing the drawer therefore restores the
 * persisted widget automatically; Save still remains the only server write.
 */
const widgetPreviewFromEditor = (widget: WidgetInstance, editor: WidgetEditorData): WidgetInstance => {
    if (widget.id !== editor.id) {
        return widget;
    }
    const values = {...editor.values};
    delete values.presetid;
    const data = mergePresetIntoData(parseJson<Record<string, unknown>>(widget.data, {}), values);
    return {
        ...widget,
        title: Object.prototype.hasOwnProperty.call(values, "title") ? String(values.title ?? "") : widget.title,
        subtitle: Object.prototype.hasOwnProperty.call(values, "subtitle") ? String(values.subtitle ?? "") : widget.subtitle,
        settings: JSON.stringify({...parseJson<Record<string, unknown>>(widget.settings, {}), ...values}),
        styleconfig: JSON.stringify(editor.styleconfig),
        data: JSON.stringify(data),
    };
};

const styleNumber = (value: unknown): number => Number.isFinite(Number(value)) ? Math.max(0, Number(value)) : 0;
const setCssVar = (
    style: CSSProperties & Record<string, string | number>,
    name: string,
    value: unknown
): void => {
    const text = String(value ?? "");
    if (text !== "") {
        style[name] = text;
    }
};
const setPxVar = (
    style: CSSProperties & Record<string, string | number>,
    name: string,
    value: unknown
): void => {
    const pixels = styleNumber(value);
    if (pixels > 0) {
        style[name] = `${pixels}px`;
    }
};
const setPaddingVars = (
    style: CSSProperties & Record<string, string | number>,
    top: unknown,
    bottom: unknown
): void => {
    const topValue = styleNumber(top);
    const bottomValue = styleNumber(bottom);
    if (topValue > 0) {
        style.paddingTop = topValue;
    }
    if (bottomValue > 0) {
        style.paddingBottom = bottomValue;
    }
};

export const wrapperStyleFromConfig = (
    widget: WidgetInstance,
    styleConfig?: StyleConfig
): CSSProperties => {
    const cfg = styleConfig ?? parseJson<StyleConfig>(widget.styleconfig ?? "{}", {});
    const style: CSSProperties & Record<string, string | number> = {};
    const background = String(cfg.bg ?? widget.bg ?? "");
    if (background) {
        style.backgroundColor = background;
        style["--mc-widget-bg"] = background;
    }
    const spacingTop = styleNumber(cfg.spacingtop ?? widget.spacingtop);
    const spacingBottom = styleNumber(cfg.spacingbottom ?? widget.spacingbottom);
    const useWrapperSpacing = widget.type !== "trustbadges";
    if (useWrapperSpacing && spacingTop > 0) {
        style.paddingTop = spacingTop;
    }
    if (useWrapperSpacing && spacingBottom > 0) {
        style.paddingBottom = spacingBottom;
    }
    const headingColor = String(cfg.headingcolor ?? "");
    const textColor = String(cfg.textcolor ?? "");
    const accentColor = String(cfg.accentcolor ?? "");
    const headingSize = styleNumber(cfg.headingfontsize);
    const bodySize = styleNumber(cfg.bodyfontsize);
    const radius = styleNumber(cfg.radius);
    if (headingColor) {
        style["--mc-widget-heading-color"] = headingColor;
        style["--mc-vh-heading-color"] = headingColor;
    }
    if (textColor) {
        style["--mc-widget-body-color"] = textColor;
        style["--mc-vh-body-color"] = textColor;
    }
    if (accentColor) {
        style["--mc-widget-accent-color"] = accentColor;
        style["--mc-primary"] = accentColor;
        style["--mc-link"] = accentColor;
    }
    if (headingSize > 0) {
        style["--mc-widget-heading-font-size"] = `${headingSize}px`;
    }
    if (bodySize > 0) {
        style["--mc-widget-body-font-size"] = `${bodySize}px`;
    }
    if (radius > 0) {
        style["--mc-widget-radius"] = `${radius}px`;
        style["--mc-radius"] = `${radius}px`;
        style["--mc-radius-lg"] = `${Math.max(radius, 8)}px`;
    }
    return style;
};

const normaliseVisibilityValue = (value: unknown): string => {
    if (typeof value === "boolean") {
        return value ? "1" : "0";
    }
    return String(value ?? "");
};

const visibilityList = (value: FieldVisibilityValue | FieldVisibilityValue[] | undefined): string[] => {
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

const shouldShowField = (field: FieldDef, values: Record<string, unknown>): boolean => {
    if (!field.showwhen) {
        return true;
    }
    const rules = Array.isArray(field.showwhen) ? field.showwhen : [field.showwhen];
    return rules.every((rule) => {
        const currentValue = values[rule.field];
        const current = normaliseVisibilityValue(currentValue);
        const allowed = visibilityList(rule.equals);
        const blocked = visibilityList(rule.notequals);
        if (allowed.length > 0 && !allowed.includes(current)) {
            return false;
        }
        if (blocked.length > 0 && blocked.includes(current)) {
            return false;
        }
        if (typeof rule.truthy === "boolean" && isTruthyVisibilityValue(currentValue) !== rule.truthy) {
            return false;
        }
        return true;
    });
};

/**
 * Compile a widget's field schema into a rule map.
 *
 * Hidden fields (showwhen) are skipped: a value the admin cannot see must never
 * block the save. Repeatable rows are keyed `${field}_${index}_${sub}` with
 * underscores, because the wire format types the key as PARAM_ALPHANUMEXT and a
 * dot would be silently stripped.
 */
export const rulesFromSchema = (
    fields: FieldDef[],
    messages: Record<string, string>
): RuleMap<Record<string, unknown>> => {
    const t = (key: string) => messages[key] ?? key;
    const rules: RuleMap<Record<string, unknown>> = {};

    fields.forEach((field) => {
        const list: Array<Rule<Record<string, unknown>>> = [];

        if (field.required) {
            list.push(required(t("error:required")));
        }
        if (typeof field.minlength === "number") {
            list.push(minLength(field.minlength, t("error:minlength")));
        }
        if (typeof field.maxlength === "number") {
            list.push(maxLength(field.maxlength, t("error:maxlength")));
        }
        if (typeof field.min === "number") {
            list.push(min(field.min, t("error:minvalue")));
        }
        if (typeof field.max === "number") {
            list.push(max(field.max, t("error:maxvalue")));
        }
        // A field's own type already carries the intent, so `url` and `color`
        // fields validate without every schema entry having to say so. An
        // explicit `format` still wins.
        const format = field.format
            ?? (field.type === "url" ? "url" : (field.type === "color" ? "hex" : undefined));

        switch (format) {
            case "email":
                list.push(emailRule(t("error:invalidemail")));
                break;
            case "url":
                list.push(urlRule(t("error:invalidurl")));
                break;
            case "hex":
                // Widget colours may be a design token as well as a hex value.
                list.push(widgetColour(t("error:invalidcolour")));
                break;
            case "integer":
                list.push(integer(t("error:invalidinteger")));
                break;
            case "number":
                list.push(number(t("error:invalidnumber")));
                break;
            case "slug":
                list.push(slugRule(t("error:invalidslug")));
                break;
            default:
                break;
        }

        if (list.length === 0) {
            return;
        }

        rules[field.name] = when(
            (values) => shouldShowField(field, values as Record<string, unknown>),
            ...list
        );
    });

    return rules;
};

const genericFieldGroup = (field: FieldDef): GenericEditorItem => {
    const name = field.name.toLowerCase();
    const type = field.type.toLowerCase();
    if (name.includes("padding") || name.includes("margin") || name.includes("spacing")) {
        return "spacing";
    }
    if (name === "logoheight") {
        return "appearance";
    }
    if (type === "image" || type === "videofile"
        || name.includes("image") || name.includes("photo") || name.includes("poster")
        || name.includes("video") || name.includes("media") || name.includes("logo")) {
        return "media";
    }
    if (type === "list") {
        return "content";
    }
    if (type === "color" || name.includes("color") || name.includes("colour") || name.includes("fontsize")
        || name.includes("radius") || name === "theme" || name.endsWith("icon") || name.endsWith("icons")) {
        return "appearance";
    }
    if (["style", "design", "layout", "mode", "align", "alignment", "navposition", "mediaposition",
        "sidebarposition", "columns", "tone", "perpage", "showarrows", "showdots", "autoplay", "interval"].includes(name)) {
        return "layout";
    }
    return "content";
};

const genericGroupLabel = (key: string, labels: Labels, section?: FocusedEditorSection): string =>
    labels[`settings_group_${key}`]
        ?? section?.label
        ?? genericEditorItems.find((item) => item.key === key)?.label
        ?? key;

// --- Per-type widget renderers (mirrors the former zone.tsx dispatch) ----------------

function SliderWidget({widget}: {widget: WidgetInstance}) {
    const data = {...sliderDataDefaults(), ...parseJson<Partial<SliderData>>(widget.data, {})};
    return <HeroSlider data={data as SliderData} title={widget.title} />;
}

type FeaturedConfig = {
    method: string; cartmethod: string; wishlistmethod?: string;
    filters: {coursetype: string; categoryid: number; sort: string; perpage: number};
    layout: string; align: string; navposition: string; columns: number;
    buttoncolor: string; buttontextcolor: string; cardbgcolor: string; cardbordercolor: string; cardborderwidth: number;
    labels: Record<string, string>;
};
function FeaturedWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<FeaturedConfig>(widget.data, {
        method: "local_moderncommerce_get_catalog",
        cartmethod: "local_moderncommerce_update_cart",
        wishlistmethod: "local_moderncommerce_update_learner_wishlist",
        filters: {coursetype: "", categoryid: 0, sort: "popular", perpage: 8},
        layout: "carousel", align: "left", navposition: "topright", columns: 4,
        buttoncolor: "", buttontextcolor: "", cardbgcolor: "", cardbordercolor: "", cardborderwidth: 0, labels: {},
    });
    return (
        <ProductCarousel
            title={widget.title} subtitle={widget.subtitle}
            methodName={cfg.method} cartMethodName={cfg.cartmethod}
            wishlistMethodName={cfg.wishlistmethod}
            filters={cfg.filters} layout={cfg.layout} align={cfg.align}
            navposition={cfg.navposition} columns={cfg.columns}
            buttoncolor={cfg.buttoncolor} buttontextcolor={cfg.buttontextcolor}
            cardbgcolor={cfg.cardbgcolor} cardbordercolor={cfg.cardbordercolor}
            cardborderwidth={cfg.cardborderwidth}
            labels={cfg.labels}
        />
    );
}

function TrustBadgesWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        badges: Array<{icon: string; label: string; sublabel: string}>;
        bgcolor?: string; titlecolor?: string; titlefontsize?: number;
        cardbgcolor?: string; cardbordercolor?: string; cardborderwidth?: number; cardradius?: number;
        iconbgcolor?: string; iconcolor?: string; iconsize?: number;
        labelcolor?: string; labelfontsize?: number; sublabelcolor?: string; sublabelfontsize?: number;
        paddingtop?: number; paddingbottom?: number;
        labels?: {trust?: string};
    }>(widget.data, {
        badges: [], bgcolor: "", titlecolor: "", titlefontsize: 24,
        cardbgcolor: "", cardbordercolor: "", cardborderwidth: 1, cardradius: 8,
        iconbgcolor: "", iconcolor: "", iconsize: 26,
        labelcolor: "", labelfontsize: 16, sublabelcolor: "", sublabelfontsize: 14,
        paddingtop: 0, paddingbottom: 0,
        labels: {trust: ""},
    });
    return (
        <TrustStrip title={widget.title} badges={cfg.badges}
            bgcolor={cfg.bgcolor} titlecolor={cfg.titlecolor} titlefontsize={cfg.titlefontsize}
            cardbgcolor={cfg.cardbgcolor} cardbordercolor={cfg.cardbordercolor}
            cardborderwidth={cfg.cardborderwidth} cardradius={cfg.cardradius}
            iconbgcolor={cfg.iconbgcolor} iconcolor={cfg.iconcolor} iconsize={cfg.iconsize}
            labelcolor={cfg.labelcolor} labelfontsize={cfg.labelfontsize}
            sublabelcolor={cfg.sublabelcolor} sublabelfontsize={cfg.sublabelfontsize}
            paddingTop={cfg.paddingtop} paddingBottom={cfg.paddingbottom}
            labels={cfg.labels} />
    );
}

function CountdownWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson(widget.data, {
        heading: "", endtime: 0, expiredmessage: "", ctalabel: "", ctaurl: "", bgcolor: "",
        textcolor: "", headingcolor: "", headingfontsize: 0, timerbgcolor: "", timernumbercolor: "",
        timernumberfontsize: 0, timerlabelcolor: "", timerlabelfontsize: 0, buttoncolor: "",
        buttontextcolor: "", expiredbgcolor: "", expiredtextcolor: "", paddingtop: 0, paddingbottom: 0,
        labels: {days: "Days", hours: "Hours", minutes: "Minutes", seconds: "Seconds"},
    });
    return (
        <CountdownBar heading={cfg.heading} endtime={cfg.endtime} expiredmessage={cfg.expiredmessage}
            ctalabel={cfg.ctalabel} ctaurl={cfg.ctaurl} bgcolor={cfg.bgcolor}
            textcolor={cfg.textcolor} headingcolor={cfg.headingcolor} headingfontsize={cfg.headingfontsize}
            timerbgcolor={cfg.timerbgcolor} timernumbercolor={cfg.timernumbercolor}
            timernumberfontsize={cfg.timernumberfontsize} timerlabelcolor={cfg.timerlabelcolor}
            timerlabelfontsize={cfg.timerlabelfontsize} buttoncolor={cfg.buttoncolor}
            buttontextcolor={cfg.buttontextcolor} expiredbgcolor={cfg.expiredbgcolor}
            expiredtextcolor={cfg.expiredtextcolor}
            paddingTop={cfg.paddingtop} paddingBottom={cfg.paddingbottom} labels={cfg.labels} />
    );
}

function TestimonialsWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        testimonials: Array<{quote: string; author: string; role: string; rating: number}>;
        bgcolor: string; titlecolor: string; titlefontsize: number; subtitlecolor: string; subtitlefontsize: number;
        cardbgcolor: string; cardbordercolor: string; cardborderwidth: number; cardradius: number;
        ratingcolor: string; quotecolor: string; quotefontsize: number; avatarbgcolor: string; avatarcolor: string;
        namecolor: string; namefontsize: number; rolecolor: string; rolefontsize: number;
        paddingtop: number; paddingbottom: number;
    }>(widget.data, {
        testimonials: [], bgcolor: "", titlecolor: "", titlefontsize: 0, subtitlecolor: "", subtitlefontsize: 0,
        cardbgcolor: "", cardbordercolor: "", cardborderwidth: 0, cardradius: 0, ratingcolor: "",
        quotecolor: "", quotefontsize: 0, avatarbgcolor: "", avatarcolor: "", namecolor: "", namefontsize: 0,
        rolecolor: "", rolefontsize: 0, paddingtop: 0, paddingbottom: 0,
    });
    const style: CSSProperties & Record<string, string | number> = {};
    if (cfg.bgcolor) {
        style.backgroundColor = cfg.bgcolor;
    }
    setCssVar(style, "--mc-tm-title-color", cfg.titlecolor);
    setPxVar(style, "--mc-tm-title-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-tm-subtitle-color", cfg.subtitlecolor);
    setPxVar(style, "--mc-tm-subtitle-font-size", cfg.subtitlefontsize);
    setCssVar(style, "--mc-tm-card-bg", cfg.cardbgcolor);
    setCssVar(style, "--mc-tm-card-border", cfg.cardbordercolor);
    setPxVar(style, "--mc-tm-card-border-width", cfg.cardborderwidth);
    setPxVar(style, "--mc-tm-card-radius", cfg.cardradius);
    setCssVar(style, "--mc-tm-rating-color", cfg.ratingcolor);
    setCssVar(style, "--mc-tm-quote-color", cfg.quotecolor);
    setPxVar(style, "--mc-tm-quote-font-size", cfg.quotefontsize);
    setCssVar(style, "--mc-tm-avatar-bg", cfg.avatarbgcolor);
    setCssVar(style, "--mc-tm-avatar-color", cfg.avatarcolor);
    setCssVar(style, "--mc-tm-name-color", cfg.namecolor);
    setPxVar(style, "--mc-tm-name-font-size", cfg.namefontsize);
    setCssVar(style, "--mc-tm-role-color", cfg.rolecolor);
    setPxVar(style, "--mc-tm-role-font-size", cfg.rolefontsize);
    setPaddingVars(style, cfg.paddingtop, cfg.paddingbottom);
    return <TestimonialGrid title={widget.title} subtitle={widget.subtitle}
        testimonials={cfg.testimonials} style={style} />;
}

function CategoriesWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson(widget.data, {
        categories: [] as Array<{id: number; name: string; count: number; url: string; icon: string; color: string}>,
        showcount: true, style: "minimal", iconcolor: "", visiblecards: 4, bgcolor: "",
        titlecolor: "", titlefontsize: 0, subtitlecolor: "", subtitlefontsize: 0,
        cardbgcolor: "", cardtextcolor: "", cardtextfontsize: 0, cardradius: 0,
        iconbgcolor: "", iconsize: 0, countcolor: "", countfontsize: 0,
        paddingtop: 0, paddingbottom: 0, labels: {courses: "", scrollleft: "", scrollright: ""},
    });
    return (
        <CategoryTiles title={widget.title} subtitle={widget.subtitle} categories={cfg.categories}
            showcount={cfg.showcount} style={cfg.style} iconColor={cfg.iconcolor}
            visibleCards={cfg.visiblecards} bgColor={cfg.bgcolor}
            titleColor={cfg.titlecolor} titleFontSize={cfg.titlefontsize}
            subtitleColor={cfg.subtitlecolor} subtitleFontSize={cfg.subtitlefontsize}
            cardBgColor={cfg.cardbgcolor} cardTextColor={cfg.cardtextcolor}
            cardTextFontSize={cfg.cardtextfontsize} cardRadius={cfg.cardradius}
            iconBgColor={cfg.iconbgcolor} iconSize={cfg.iconsize}
            countColor={cfg.countcolor} countFontSize={cfg.countfontsize}
            paddingTop={cfg.paddingtop} paddingBottom={cfg.paddingbottom} labels={cfg.labels} />
    );
}

function PartnersWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        logos: Array<{image: string; name: string; linkurl: string}>;
        style: string; heading: string; visiblelogos: number; speed: number;
        pauseonhover: boolean; greyscale: boolean; logoheight: number; logogap: number;
        bgcolor: string; headingcolor: string; headingfontsize: number;
        paddingtop: number; paddingbottom: number;
        labels: {scrollleft: string; scrollright: string};
    }>(widget.data, {
        logos: [], style: "grid", heading: "", visiblelogos: 5, speed: 30,
        pauseonhover: true, greyscale: false, logoheight: 48, logogap: 48,
        bgcolor: "", headingcolor: "", headingfontsize: 0, paddingtop: 0, paddingbottom: 0,
        labels: {scrollleft: "", scrollright: ""},
    });
    return (
        <PartnerLogos heading={cfg.heading || widget.title} logos={cfg.logos} variant={cfg.style}
            visibleLogos={cfg.visiblelogos} speed={cfg.speed} pauseOnHover={cfg.pauseonhover}
            greyscale={cfg.greyscale} logoHeight={cfg.logoheight} logoGap={cfg.logogap}
            bgColor={cfg.bgcolor} headingColor={cfg.headingcolor} headingFontSize={cfg.headingfontsize}
            paddingTop={cfg.paddingtop} paddingBottom={cfg.paddingbottom} labels={cfg.labels} />
    );
}

function InstructorsWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        instructors: Array<{name: string; role: string; bio: string; photo: string}>;
        bgcolor: string; titlecolor: string; titlefontsize: number; subtitlecolor: string; subtitlefontsize: number;
        cardbgcolor: string; cardbordercolor: string; cardborderwidth: number; cardradius: number;
        avatarbgcolor: string; avatarcolor: string; namecolor: string; namefontsize: number;
        rolecolor: string; rolefontsize: number; biocolor: string; biofontsize: number;
        paddingtop: number; paddingbottom: number;
    }>(widget.data, {
        instructors: [], bgcolor: "", titlecolor: "", titlefontsize: 0, subtitlecolor: "", subtitlefontsize: 0,
        cardbgcolor: "", cardbordercolor: "", cardborderwidth: 0, cardradius: 0, avatarbgcolor: "",
        avatarcolor: "", namecolor: "", namefontsize: 0, rolecolor: "", rolefontsize: 0, biocolor: "",
        biofontsize: 0, paddingtop: 0, paddingbottom: 0,
    });
    const style: CSSProperties & Record<string, string | number> = {};
    if (cfg.bgcolor) {
        style.backgroundColor = cfg.bgcolor;
    }
    setCssVar(style, "--mc-inst-title-color", cfg.titlecolor);
    setPxVar(style, "--mc-inst-title-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-inst-subtitle-color", cfg.subtitlecolor);
    setPxVar(style, "--mc-inst-subtitle-font-size", cfg.subtitlefontsize);
    setCssVar(style, "--mc-inst-card-bg", cfg.cardbgcolor);
    setCssVar(style, "--mc-inst-card-border", cfg.cardbordercolor);
    setPxVar(style, "--mc-inst-card-border-width", cfg.cardborderwidth);
    setPxVar(style, "--mc-inst-card-radius", cfg.cardradius);
    setCssVar(style, "--mc-inst-avatar-bg", cfg.avatarbgcolor);
    setCssVar(style, "--mc-inst-avatar-color", cfg.avatarcolor);
    setCssVar(style, "--mc-inst-name-color", cfg.namecolor);
    setPxVar(style, "--mc-inst-name-font-size", cfg.namefontsize);
    setCssVar(style, "--mc-inst-role-color", cfg.rolecolor);
    setPxVar(style, "--mc-inst-role-font-size", cfg.rolefontsize);
    setCssVar(style, "--mc-inst-bio-color", cfg.biocolor);
    setPxVar(style, "--mc-inst-bio-font-size", cfg.biofontsize);
    setPaddingVars(style, cfg.paddingtop, cfg.paddingbottom);
    return <InstructorSpotlight title={widget.title} subtitle={widget.subtitle}
        instructors={cfg.instructors} style={style} />;
}

function NewsletterWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson(widget.data, {
        method: "local_moderncommerce_newsletter_subscribe", heading: "", description: "",
        placeholder: "", buttonlabel: "Subscribe", successmessage: "",
        recaptcha: recaptchaConfigDefaults(),
        bgcolor: "", panelbgcolor: "", panelbordercolor: "", panelborderwidth: 1, panelradius: 0,
        panelpaddingtop: 0, panelpaddingright: 0, panelpaddingbottom: 0, panelpaddingleft: 0,
        titlecolor: "", titlefontsize: 0,
        textcolor: "", textfontsize: 0, inputbgcolor: "", inputbordercolor: "", inputtextcolor: "",
        placeholdercolor: "", buttoncolor: "", buttontextcolor: "", buttonradius: 0, paddingtop: 0, paddingbottom: 0,
        labels: {emailrequired: "", invalidemail: "", subscribing: "", servicerequestfailed: ""},
    });
    const style: CSSProperties & Record<string, string | number> = {};
    if (cfg.bgcolor) {
        style.backgroundColor = cfg.bgcolor;
    }
    setCssVar(style, "--mc-news-panel-bg", cfg.panelbgcolor);
    setCssVar(style, "--mc-news-panel-border", cfg.panelbordercolor);
    style["--mc-news-panel-border-width"] = `${Math.min(24, styleNumber(cfg.panelborderwidth))}px`;
    setPxVar(style, "--mc-news-panel-radius", cfg.panelradius);
    style["--mc-news-panel-padding-top"] = `${Math.min(240, styleNumber(cfg.panelpaddingtop))}px`;
    style["--mc-news-panel-padding-right"] = `${Math.min(240, styleNumber(cfg.panelpaddingright))}px`;
    style["--mc-news-panel-padding-bottom"] = `${Math.min(240, styleNumber(cfg.panelpaddingbottom))}px`;
    style["--mc-news-panel-padding-left"] = `${Math.min(240, styleNumber(cfg.panelpaddingleft))}px`;
    setCssVar(style, "--mc-news-heading-color", cfg.titlecolor);
    setPxVar(style, "--mc-news-heading-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-news-text-color", cfg.textcolor);
    setPxVar(style, "--mc-news-text-font-size", cfg.textfontsize);
    setCssVar(style, "--mc-news-input-bg", cfg.inputbgcolor);
    setCssVar(style, "--mc-news-input-border", cfg.inputbordercolor);
    setCssVar(style, "--mc-news-input-text", cfg.inputtextcolor);
    setCssVar(style, "--mc-news-placeholder", cfg.placeholdercolor);
    setCssVar(style, "--mc-news-button-bg", cfg.buttoncolor);
    setCssVar(style, "--mc-news-button-text", cfg.buttontextcolor);
    setPxVar(style, "--mc-news-button-radius", cfg.buttonradius);
    setPaddingVars(style, cfg.paddingtop, cfg.paddingbottom);
    return (
        <NewsletterForm method={cfg.method} heading={cfg.heading} description={cfg.description}
            placeholder={cfg.placeholder} buttonlabel={cfg.buttonlabel}
            successmessage={cfg.successmessage} recaptcha={cfg.recaptcha} labels={cfg.labels} style={style} />
    );
}

type CatalogConfig = {
    method: string; cartmethod: string; wishlistmethod?: string;
    displaysettings: {title: string; perpage: number; sidebarposition: string;
        bgcolor: string; herobgcolor: string; herobordercolor: string; heroradius: number; eyebrowcolor: string;
        titlecolor: string; titlefontsize: number; textcolor: string; textfontsize: number;
        accentcolor: string; heropanelbgcolor: string; heropanelbordercolor: string;
        heropaneltextcolor: string; heropanelaccentcolor: string; heropanelvaluecolor: string;
        heropanelvaluefontsize: number; cardbgcolor: string;
        cardbordercolor: string; cardborderwidth: number; cardradius: number; cardfooterbgcolor: string; cardtitlecolor: string;
        cardtitlefontsize: number; cardtextcolor: string; cardmetabgcolor: string; cardmetatextcolor: string;
        ratingcolor: string; ratingtextcolor: string; pricecolor: string; originalpricecolor: string; buttoncolor: string;
        buttontextcolor: string; buttonradius: number; badgebgcolor: string; badgebordercolor: string;
        badgetextcolor: string; badgeradius: number; badgefontsize: number;
        coursebadgebgcolor: string; coursebadgebordercolor: string; coursebadgetextcolor: string;
        programbadgebgcolor: string; programbadgebordercolor: string; programbadgetextcolor: string;
        bundlebadgebgcolor: string; bundlebadgebordercolor: string; bundlebadgetextcolor: string;
        filterbgcolor: string; filterbordercolor: string; filterborderwidth: number;
        filterradius: number; filtertitlecolor: string; filtertextcolor: string; inputbgcolor: string; inputbordercolor: string;
        inputtextcolor: string; placeholdercolor: string; tabbgcolor: string; tabbordercolor: string; tabtextcolor: string;
        tabactivebgcolor: string; tabactivetextcolor: string;
        paddingtop: number; paddingbottom: number; paddingleft: number; paddingright: number;
        margintop: number; marginbottom: number};
    initialfilters: Record<string, unknown>;
};
function CatalogWidget({widget, catalogLabels, previewMode = false}: {
    widget: WidgetInstance;
    catalogLabels: Labels;
    previewMode?: boolean;
}) {
    const cfg = parseJson<CatalogConfig>(widget.data, {
        method: "local_moderncommerce_get_catalog",
        cartmethod: "local_moderncommerce_update_cart",
        wishlistmethod: "local_moderncommerce_update_learner_wishlist",
        displaysettings: {
            title: "", perpage: 12, sidebarposition: "left", bgcolor: "", herobgcolor: "",
            herobordercolor: "", heroradius: 8, eyebrowcolor: "", titlecolor: "", titlefontsize: 0,
            textcolor: "", textfontsize: 0, accentcolor: "", heropanelbgcolor: "", heropanelbordercolor: "",
            heropaneltextcolor: "", heropanelaccentcolor: "", heropanelvaluecolor: "", heropanelvaluefontsize: 0,
            cardbgcolor: "", cardbordercolor: "",
            cardborderwidth: 1, cardradius: 8, cardfooterbgcolor: "", cardtitlecolor: "", cardtitlefontsize: 0, cardtextcolor: "",
            cardmetabgcolor: "", cardmetatextcolor: "", ratingcolor: "", ratingtextcolor: "", pricecolor: "",
            originalpricecolor: "", buttoncolor: "", buttontextcolor: "", buttonradius: 0,
            badgebgcolor: "", badgebordercolor: "", badgetextcolor: "", badgeradius: 6, badgefontsize: 0,
            coursebadgebgcolor: "", coursebadgebordercolor: "", coursebadgetextcolor: "",
            programbadgebgcolor: "", programbadgebordercolor: "", programbadgetextcolor: "",
            bundlebadgebgcolor: "", bundlebadgebordercolor: "", bundlebadgetextcolor: "",
            filterbgcolor: "",
            filterbordercolor: "", filterborderwidth: 1, filterradius: 8, filtertitlecolor: "", filtertextcolor: "",
            inputbgcolor: "", inputbordercolor: "", inputtextcolor: "", placeholdercolor: "", tabbgcolor: "",
            tabbordercolor: "", tabtextcolor: "", tabactivebgcolor: "", tabactivetextcolor: "",
            paddingtop: 0, paddingbottom: 0, paddingleft: 0, paddingright: 0, margintop: 0, marginbottom: 0,
        },
        initialfilters: {},
    });
    return (
        <Catalog
            methodName={cfg.method}
            cartMethodName={cfg.cartmethod}
            wishlistUpdateMethodName={cfg.wishlistmethod}
            initialFilters={cfg.initialfilters as never}
            displaySettings={cfg.displaysettings as never}
            labels={catalogLabels}
            syncUrlEnabled={!previewMode}
        />
    );
}

function VideoHeroWidget({widget}: {widget: WidgetInstance}) {
    const data = parseJson<VideoHeroData>(widget.data, {
        headinglines: [], subtext: "", primary: {label: "", url: "#"}, secondary: null, hassecondary: false,
        bgcolor: "var(--mc-primary)", accent: "var(--mc-surface)",
        video: {mode: "image", haspanelvideo: false, embedurl: "", fileurl: "", mimetype: "",
            posterurl: "", hasposter: false, title: "", hastitle: false},
        infoitems: [], quote: {text: "", author: "", hasauthor: false},
        labels: {playvideo: ""},
    });
    return <VideoHero data={data} title={widget.title} />;
}

type MediaStorySlide = {
    heading: string;
    subheading: string;
    media: {
        type: "image" | "video";
        mode: "image" | "file" | "embed";
        url: string;
        posterurl: string;
        embedurl: string;
        mimetype: string;
        alt: string;
        hasmedia: boolean;
    };
};

function MediaStoryCarouselWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        mediaposition: string; bgcolor: string; paddingtop: number; paddingbottom: number;
        previcon: string; nexticon: string; cardbgcolor: string; cardbordercolor: string;
        cardborderwidth: number; cardradius: number; titlecolor: string; titlefontsize: number;
        textcolor: string; textfontsize: number; iconcolor: string; iconbgcolor: string;
        mediaradius: number; slides: MediaStorySlide[];
        labels: {previousslide: string; nextslide: string; playvideo: string; video: string};
    }>(widget.data, {
        mediaposition: "left", bgcolor: "var(--mc-surface-alt)", paddingtop: 20, paddingbottom: 80,
        previcon: "chevron-left", nexticon: "chevron-right", cardbgcolor: "", cardbordercolor: "",
        cardborderwidth: 0, cardradius: 0, titlecolor: "", titlefontsize: 0, textcolor: "",
        textfontsize: 0, iconcolor: "", iconbgcolor: "", mediaradius: 0, slides: [],
        labels: {previousslide: "", nextslide: "", playvideo: "", video: ""},
    });
    const slides = cfg.slides.filter((slide) => slide.heading || slide.subheading || slide.media?.hasmedia);
    const [active, setActive] = useState(0);
    const [playing, setPlaying] = useState(false);
    const count = slides.length;
    const current = slides[Math.min(active, Math.max(0, count - 1))];
    const sectionStyle: CSSProperties & Record<string, string | number> = {
        backgroundColor: cfg.bgcolor || "var(--mc-surface-alt)",
        paddingTop: Math.max(0, Number(cfg.paddingtop) || 0),
        paddingBottom: Math.max(0, Number(cfg.paddingbottom) || 0),
    };
    setCssVar(sectionStyle, "--mc-msc-card-bg", cfg.cardbgcolor);
    setCssVar(sectionStyle, "--mc-msc-card-border", cfg.cardbordercolor);
    setPxVar(sectionStyle, "--mc-msc-card-border-width", cfg.cardborderwidth);
    setPxVar(sectionStyle, "--mc-msc-card-radius", cfg.cardradius);
    setCssVar(sectionStyle, "--mc-msc-title-color", cfg.titlecolor);
    setPxVar(sectionStyle, "--mc-msc-title-font-size", cfg.titlefontsize);
    setCssVar(sectionStyle, "--mc-msc-text-color", cfg.textcolor);
    setPxVar(sectionStyle, "--mc-msc-text-font-size", cfg.textfontsize);
    setCssVar(sectionStyle, "--mc-msc-icon-color", cfg.iconcolor);
    setCssVar(sectionStyle, "--mc-msc-icon-bg", cfg.iconbgcolor);
    setPxVar(sectionStyle, "--mc-msc-media-radius", cfg.mediaradius);
    const go = (delta: number) => {
        if (count < 2) {
            return;
        }
        setActive((value) => (value + delta + count) % count);
    };

    useEffect(() => {
        setActive((value) => Math.min(value, Math.max(0, count - 1)));
    }, [count]);

    useEffect(() => {
        setPlaying(false);
    }, [active]);

    if (!current) {
        return null;
    }

    const stageClass = cfg.mediaposition === "right"
        ? "mc-msc__stage mc-msc__stage--media-right"
        : "mc-msc__stage";
    const previousIcon = normIcon(cfg.previcon) || "chevron-left";
    const nextIcon = normIcon(cfg.nexticon) || "chevron-right";
    const media = current.media;
    const hasPoster = Boolean(media.posterurl);
    const showPlaceholder = !media.hasmedia || (media.type === "video" && !hasPoster && !playing);

    return (
        <section className="mc-msc" style={sectionStyle}>
            <div className="mc-msc__inner">
                {count > 1 && (
                    <button type="button" className="mc-button mc-msc__nav mc-msc__nav--prev"
                        data-mc-button="light" data-mc-button-size="icon" onClick={() => go(-1)}
                        aria-label={cfg.labels.previousslide}>
                        <i className={`bi bi-${previousIcon}`} aria-hidden="true" />
                    </button>
                )}
                <article className={stageClass}>
                    <div className="mc-msc__media">
                        {media.type === "image" && media.hasmedia && (
                            <img src={media.url} alt={media.alt || ""} />
                        )}
                        {media.type === "video" && media.hasmedia && !playing && hasPoster && (
                            <img src={media.posterurl} alt={media.alt || ""} />
                        )}
                        {media.type === "video" && media.hasmedia && playing && media.mode === "embed" && (
                            <iframe className="mc-msc__iframe" src={media.embedurl} title={current.heading || cfg.labels.video}
                                allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowFullScreen />
                        )}
                        {media.type === "video" && media.hasmedia && playing && media.mode === "file" && (
                            <video className="mc-msc__video" controls autoPlay playsInline poster={media.posterurl || undefined}>
                                <source src={media.url} type={media.mimetype || undefined} />
                            </video>
                        )}
                        {showPlaceholder && (
                            <span className="mc-msc__placeholder" aria-hidden="true">
                                <i className={`bi bi-${media.type === "video" ? "play-circle" : "image"}`} />
                            </span>
                        )}
                        {media.type === "video" && media.hasmedia && !playing && (
                            <button type="button" className="mc-button mc-msc__play" data-mc-button="light"
                                data-mc-button-size="icon" aria-label={cfg.labels.playvideo}
                                onClick={() => setPlaying(true)}>
                                <i className="bi bi-play-fill" aria-hidden="true" />
                            </button>
                        )}
                    </div>
                    <div className="mc-msc__copy">
                        {current.heading && <h2>{current.heading}</h2>}
                        {current.subheading && <p>{current.subheading}</p>}
                    </div>
                </article>
                {count > 1 && (
                    <button type="button" className="mc-button mc-msc__nav mc-msc__nav--next"
                        data-mc-button="light" data-mc-button-size="icon" onClick={() => go(1)}
                        aria-label={cfg.labels.nextslide}>
                        <i className={`bi bi-${nextIcon}`} aria-hidden="true" />
                    </button>
                )}
            </div>
        </section>
    );
}

type ContentBenefit = {
    number?: string;
    title?: string;
    text?: string;
};

function ContentWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        eyebrow: string; title: string; subtitle: string; layout: string; mediaposition: string;
        paragraphs: string[]; image: string; bgcolor: string; paddingtop: number; paddingbottom: number;
        paddingleft: number; paddingright: number; cardradius: number;
        panelbgcolor: string; panelbordercolor: string; titlecolor: string; titlefontsize: number;
        subtitlecolor: string; subtitlefontsize: number; textcolor: string; textfontsize: number;
        benefits: ContentBenefit[];
        benefitnumbercolor: string; benefitnumberfontsize: number; benefittitlecolor: string;
        benefittitlefontsize: number; benefittextcolor: string; benefittextfontsize: number; benefitbordercolor: string;
        buttoncolor: string; buttontextcolor: string; buttonradius: number; mediaradius: number;
        cta: {label: string; url: string};
    }>(widget.data, {
        eyebrow: "", title: widget.title, subtitle: widget.subtitle, cardradius: 8,
        layout: "card", mediaposition: "right", paragraphs: [], image: "", bgcolor: "var(--mc-surface)",
        paddingtop: 72, paddingbottom: 72,
        paddingleft: 0, paddingright: 0,
        panelbgcolor: "", panelbordercolor: "", titlecolor: "", titlefontsize: 0, subtitlecolor: "",
        subtitlefontsize: 0, textcolor: "", textfontsize: 0,
        benefits: [], benefitnumbercolor: "", benefitnumberfontsize: 0, benefittitlecolor: "",
        benefittitlefontsize: 0, benefittextcolor: "", benefittextfontsize: 0, benefitbordercolor: "",
        buttoncolor: "", buttontextcolor: "", buttonradius: 0, mediaradius: 0,
        cta: {label: "", url: ""},
    });
    const hasCta = cfg.cta.label && cfg.cta.url;
    const hasMedia = cfg.image.trim() !== "";
    const benefits = Array.isArray(cfg.benefits) ? cfg.benefits
        .map((benefit, index) => {
            const number = String(benefit?.number ?? "").trim()
                || String(index + 1).padStart(2, "0");
            const title = String(benefit?.title ?? "").trim();
            const text = String(benefit?.text ?? "").trim();
            return {number, title, text};
        })
        .filter((benefit) => benefit.title !== "" || benefit.text !== "") : [];
    const style: CSSProperties & Record<string, string | number> = {
        backgroundColor: cfg.bgcolor || "var(--mc-surface)",
        paddingTop: Number(cfg.paddingtop) || 0,
        paddingBottom: Number(cfg.paddingbottom) || 0,
        paddingLeft: Number(cfg.paddingleft) || 0,
        paddingRight: Number(cfg.paddingright) || 0,
        "--mc-pw-card-radius": `${Math.max(0, Number(cfg.cardradius) || 0)}px`,
    };
    setCssVar(style, "--mc-pw-panel-bg", cfg.panelbgcolor);
    setCssVar(style, "--mc-pw-panel-border", cfg.panelbordercolor);
    setCssVar(style, "--mc-pw-title-color", cfg.titlecolor);
    setPxVar(style, "--mc-pw-title-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-pw-subtitle-color", cfg.subtitlecolor);
    setPxVar(style, "--mc-pw-subtitle-font-size", cfg.subtitlefontsize);
    setCssVar(style, "--mc-pw-text-color", cfg.textcolor);
    setPxVar(style, "--mc-pw-text-font-size", cfg.textfontsize);
    setCssVar(style, "--mc-pw-benefit-number-color", cfg.benefitnumbercolor);
    setPxVar(style, "--mc-pw-benefit-number-font-size", cfg.benefitnumberfontsize);
    setCssVar(style, "--mc-pw-benefit-title-color", cfg.benefittitlecolor);
    setPxVar(style, "--mc-pw-benefit-title-font-size", cfg.benefittitlefontsize);
    setCssVar(style, "--mc-pw-benefit-text-color", cfg.benefittextcolor);
    setPxVar(style, "--mc-pw-benefit-text-font-size", cfg.benefittextfontsize);
    setCssVar(style, "--mc-pw-benefit-border", cfg.benefitbordercolor);
    setCssVar(style, "--mc-pw-button-bg", cfg.buttoncolor);
    setCssVar(style, "--mc-pw-button-text", cfg.buttontextcolor);
    setPxVar(style, "--mc-pw-button-radius", cfg.buttonradius);
    setPxVar(style, "--mc-pw-media-radius", cfg.mediaradius);
    const layout = ["card", "centered", "split"].includes(cfg.layout) ? cfg.layout : "card";
    const mediaPosition = cfg.mediaposition === "left" ? "left" : "right";
    return (
        <section className={`mc-pw-content mc-pw-content--${layout} mc-pw-content--media-${mediaPosition}${hasMedia ? " mc-pw-content--has-media" : ""}`}
            style={style}>
            <div className="mc-pw-content__inner">
                <div className="mc-pw-content__body">
                    {cfg.eyebrow && <div className="mc-pw-eyebrow">{cfg.eyebrow}</div>}
                    {cfg.title && <h2>{cfg.title}</h2>}
                    {cfg.subtitle && <p className="mc-pw-subtitle">{cfg.subtitle}</p>}
                    {cfg.paragraphs.length > 0 && (
                        <div className="mc-pw-content__text">
                            {cfg.paragraphs.map((p, i) => <p key={i}>{p}</p>)}
                        </div>
                    )}
                    {benefits.length > 0 && (
                        <div className="mc-pw-benefits">
                            {benefits.map((benefit, index) => (
                                <article className="mc-pw-benefit" key={`${benefit.number}-${index}`}>
                                    <span className="mc-pw-benefit__number">{benefit.number}</span>
                                    <div className="mc-pw-benefit__copy">
                                        {benefit.title && <h3>{benefit.title}</h3>}
                                        {benefit.text && <p>{benefit.text}</p>}
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                    {hasCta && <a className="mc-button btn-mc-primary mc-pw-content__cta" href={cfg.cta.url} data-mc-button="primary">{cfg.cta.label}</a>}
                </div>
                {hasMedia && (
                    <div className="mc-pw-content__media">
                        <img src={cfg.image} alt="" loading="lazy" />
                    </div>
                )}
            </div>
        </section>
    );
}

function LearningPromiseWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        title: string; body: string; bgcolor: string; headingcolor: string; headingfontsize: number;
        textcolor: string; textfontsize: number; paddingtop: number; paddingbottom: number;
    }>(widget.data, {
        title: widget.title, body: "", bgcolor: "var(--mc-surface)", headingcolor: "var(--mc-text)",
        headingfontsize: 0, textcolor: "var(--mc-text)", textfontsize: 0, paddingtop: 0, paddingbottom: 0,
    });
    const sectionStyle: CSSProperties & Record<string, string | number> = {
        backgroundColor: cfg.bgcolor || "var(--mc-surface)",
    };
    setPxVar(sectionStyle, "--mc-lp-padding-top", cfg.paddingtop);
    setPxVar(sectionStyle, "--mc-lp-padding-bottom", cfg.paddingbottom);
    const headingStyle: CSSProperties = {
        color: cfg.headingcolor || "var(--mc-widget-heading-color, var(--mc-text))",
        ...(styleNumber(cfg.headingfontsize) > 0 ? {fontSize: styleNumber(cfg.headingfontsize)} : {}),
    };
    const bodyStyle: CSSProperties = {
        color: cfg.textcolor || "var(--mc-widget-body-color, var(--mc-text))",
        ...(styleNumber(cfg.textfontsize) > 0 ? {fontSize: styleNumber(cfg.textfontsize)} : {}),
    };

    return (
        <section className="mc-learning-promise text-center" style={sectionStyle}>
            <div className="mc-learning-promise__inner container px-4">
                {cfg.title && (
                    <h2 className="mc-learning-promise__title fw-bold mx-auto mb-3" style={headingStyle}>
                        {cfg.title}
                    </h2>
                )}
                {cfg.body && (
                    <p className="mc-learning-promise__body mx-auto mb-0" style={bodyStyle}>
                        {cfg.body}
                    </p>
                )}
            </div>
        </section>
    );
}

function FeatureGridWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        style: string; columns: number; cards: FeatureCard[];
        title: string; subtitle: string; showheader: boolean; centercards: boolean;
        bgcolor: string;
        titlecolor: string; titlefontsize: number;
        subtitlecolor: string; subtitlefontsize: number;
        cardbgcolor: string; cardbordercolor: string; cardborderwidth: number; cardradius: number;
        iconcolor: string; iconsize: number; iconbgcolor: string;
        cardtitlecolor: string; cardtitlefontsize: number;
        textcolor: string; textfontsize: number; linkcolor: string;
        paddingtop: number; paddingbottom: number;
    }>(widget.data, {
        style: "topbar", columns: 3, cards: [],
        title: widget.title, subtitle: widget.subtitle, showheader: true, centercards: true,
        bgcolor: "",
        titlecolor: "", titlefontsize: 0,
        subtitlecolor: "", subtitlefontsize: 0,
        cardbgcolor: "", cardbordercolor: "", cardborderwidth: 1, cardradius: 12,
        iconcolor: "", iconsize: 40, iconbgcolor: "",
        cardtitlecolor: "", cardtitlefontsize: 0,
        textcolor: "", textfontsize: 0, linkcolor: "",
        paddingtop: 72, paddingbottom: 72,
    });

    const style: CSSProperties & Record<string, string | number> = {};
    setCssVar(style, "--mc-fg-bg", cfg.bgcolor);
    setCssVar(style, "--mc-fg-title-color", cfg.titlecolor);
    setPxVar(style, "--mc-fg-title-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-fg-subtitle-color", cfg.subtitlecolor);
    setPxVar(style, "--mc-fg-subtitle-font-size", cfg.subtitlefontsize);
    setCssVar(style, "--mc-fg-card-bg", cfg.cardbgcolor);
    setCssVar(style, "--mc-fg-card-border-color", cfg.cardbordercolor);
    setPxVar(style, "--mc-fg-card-border-width", cfg.cardborderwidth);
    setPxVar(style, "--mc-fg-card-radius", cfg.cardradius);
    setCssVar(style, "--mc-fg-card-icon", cfg.iconcolor);
    setPxVar(style, "--mc-fg-icon-size", cfg.iconsize);
    setCssVar(style, "--mc-fg-icon-bg", cfg.iconbgcolor);
    setCssVar(style, "--mc-fg-card-title-color", cfg.cardtitlecolor);
    setPxVar(style, "--mc-fg-card-title-font-size", cfg.cardtitlefontsize);
    setCssVar(style, "--mc-fg-card-text-color", cfg.textcolor);
    setPxVar(style, "--mc-fg-card-text-font-size", cfg.textfontsize);
    setCssVar(style, "--mc-fg-card-link-color", cfg.linkcolor);
    setPxVar(style, "--mc-fg-padding-top", cfg.paddingtop);
    setPxVar(style, "--mc-fg-padding-bottom", cfg.paddingbottom);

    return <FeatureGrid {...cfg} cssvars={style} />;
}

function SubscriptionPlansWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        style: string; columns: number; plans: SubscriptionPlan[];
        cycles: PlanCycle[]; defaultcycle: string; showcycleswitch: boolean; savingslabel: string;
        cyclegrouplabel: string;
        title: string; subtitle: string; showheader: boolean; showfeatures: boolean;
        ctalabel: string; ctaurl: string; featuredlabel: string;
        bgcolor: string;
        titlecolor: string; titlefontsize: number;
        subtitlecolor: string; subtitlefontsize: number;
        cardbgcolor: string; cardbordercolor: string; cardborderwidth: number; cardradius: number;
        accentcolor: string;
        plannamecolor: string; plannamefontsize: number;
        pricecolor: string; pricefontsize: number; cyclecolor: string;
        textcolor: string; textfontsize: number;
        featurecolor: string; featureiconcolor: string;
        buttoncolor: string; buttontextcolor: string; buttonradius: number;
        paddingtop: number; paddingbottom: number;
    }>(widget.data, {
        style: "elevated", columns: 3, plans: [],
        cycles: [], defaultcycle: "monthly", showcycleswitch: false, savingslabel: "",
        cyclegrouplabel: "",
        title: widget.title, subtitle: widget.subtitle, showheader: true, showfeatures: true,
        ctalabel: "", ctaurl: "", featuredlabel: "",
        bgcolor: "",
        titlecolor: "", titlefontsize: 0,
        subtitlecolor: "", subtitlefontsize: 0,
        cardbgcolor: "", cardbordercolor: "", cardborderwidth: 1, cardradius: 12,
        accentcolor: "",
        plannamecolor: "", plannamefontsize: 0,
        pricecolor: "", pricefontsize: 40, cyclecolor: "",
        textcolor: "", textfontsize: 0,
        featurecolor: "", featureiconcolor: "",
        buttoncolor: "", buttontextcolor: "", buttonradius: 8,
        paddingtop: 72, paddingbottom: 72,
    });

    const style: CSSProperties & Record<string, string | number> = {};
    setCssVar(style, "--mc-sp-bg", cfg.bgcolor);
    setCssVar(style, "--mc-sp-title-color", cfg.titlecolor);
    setPxVar(style, "--mc-sp-title-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-sp-subtitle-color", cfg.subtitlecolor);
    setPxVar(style, "--mc-sp-subtitle-font-size", cfg.subtitlefontsize);
    setCssVar(style, "--mc-sp-card-bg", cfg.cardbgcolor);
    setCssVar(style, "--mc-sp-card-border-color", cfg.cardbordercolor);
    setPxVar(style, "--mc-sp-card-border-width", cfg.cardborderwidth);
    setPxVar(style, "--mc-sp-card-radius", cfg.cardradius);
    setCssVar(style, "--mc-sp-accent", cfg.accentcolor);
    setCssVar(style, "--mc-sp-plan-name-color", cfg.plannamecolor);
    setPxVar(style, "--mc-sp-plan-name-font-size", cfg.plannamefontsize);
    setCssVar(style, "--mc-sp-price-color", cfg.pricecolor);
    setPxVar(style, "--mc-sp-price-font-size", cfg.pricefontsize);
    setCssVar(style, "--mc-sp-cycle-color", cfg.cyclecolor);
    setCssVar(style, "--mc-sp-text-color", cfg.textcolor);
    setPxVar(style, "--mc-sp-text-font-size", cfg.textfontsize);
    setCssVar(style, "--mc-sp-feature-color", cfg.featurecolor);
    setCssVar(style, "--mc-sp-feature-icon-color", cfg.featureiconcolor);
    setCssVar(style, "--mc-sp-button-bg", cfg.buttoncolor);
    setCssVar(style, "--mc-sp-button-color", cfg.buttontextcolor);
    setPxVar(style, "--mc-sp-button-radius", cfg.buttonradius);
    setPxVar(style, "--mc-sp-padding-top", cfg.paddingtop);
    setPxVar(style, "--mc-sp-padding-bottom", cfg.paddingbottom);

    return <SubscriptionPlans {...cfg} cssvars={style} />;
}

function LeaderMessageWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        style: string; mediaposition: string;
        eyebrow: string; showeyebrow: boolean; title: string; showtitle: boolean;
        message: string; justifytext: boolean;
        photo: string; showphoto: boolean; photoalt: string;
        leadername: string; leaderrole: string; showattribution: boolean;
        signature: string; showsignature: boolean;
        ctalabel: string; ctaurl: string; showcta: boolean;
        showaccent: boolean;
        bgcolor: string; accentcolor: string;
        eyebrowcolor: string; eyebrowfontsize: number;
        titlecolor: string; titlefontsize: number;
        textcolor: string; textfontsize: number;
        namecolor: string; namefontsize: number;
        rolecolor: string; rolefontsize: number;
        buttoncolor: string; buttontextcolor: string; buttonradius: number;
        mediaradius: number; mediawidth: number;
        paddingtop: number; paddingbottom: number;
    }>(widget.data, {
        style: "elevated", mediaposition: "right",
        eyebrow: "", showeyebrow: false, title: widget.title, showtitle: true,
        message: "", justifytext: false,
        photo: "", showphoto: true, photoalt: "",
        leadername: "", leaderrole: "", showattribution: true,
        signature: "", showsignature: false,
        ctalabel: "", ctaurl: "", showcta: false,
        showaccent: true,
        bgcolor: "", accentcolor: "",
        eyebrowcolor: "", eyebrowfontsize: 0,
        titlecolor: "", titlefontsize: 0,
        textcolor: "", textfontsize: 0,
        namecolor: "", namefontsize: 0,
        rolecolor: "", rolefontsize: 0,
        buttoncolor: "", buttontextcolor: "", buttonradius: 8,
        mediaradius: 16, mediawidth: 38,
        paddingtop: 72, paddingbottom: 72,
    });

    const style: CSSProperties & Record<string, string | number> = {};
    setCssVar(style, "--mc-leader-bg", cfg.bgcolor);
    setCssVar(style, "--mc-leader-accent", cfg.accentcolor);
    setCssVar(style, "--mc-leader-eyebrow-color", cfg.eyebrowcolor);
    setPxVar(style, "--mc-leader-eyebrow-font-size", cfg.eyebrowfontsize);
    setCssVar(style, "--mc-leader-title-color", cfg.titlecolor);
    setPxVar(style, "--mc-leader-title-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-leader-text-color", cfg.textcolor);
    setPxVar(style, "--mc-leader-text-font-size", cfg.textfontsize);
    setCssVar(style, "--mc-leader-name-color", cfg.namecolor);
    setPxVar(style, "--mc-leader-name-font-size", cfg.namefontsize);
    setCssVar(style, "--mc-leader-role-color", cfg.rolecolor);
    setPxVar(style, "--mc-leader-role-font-size", cfg.rolefontsize);
    setCssVar(style, "--mc-leader-button-bg", cfg.buttoncolor);
    setCssVar(style, "--mc-leader-button-color", cfg.buttontextcolor);
    setPxVar(style, "--mc-leader-button-radius", cfg.buttonradius);
    setPxVar(style, "--mc-leader-media-radius", cfg.mediaradius);
    setPxVar(style, "--mc-leader-padding-top", cfg.paddingtop);
    setPxVar(style, "--mc-leader-padding-bottom", cfg.paddingbottom);
    if (cfg.mediawidth) {
        style["--mc-leader-media-width"] = `${cfg.mediawidth}%`;
    }

    return <LeaderMessage {...cfg} cssvars={style} />;
}

function BeliefWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        title: string; subtitle: string; bgcolor: string; closing: string;
        titlecolor: string; titlefontsize: number; subtitlecolor: string; subtitlefontsize: number;
        iconcolor: string; iconsize: number; textcolor: string; textfontsize: number;
        labelcolor: string; labelfontsize: number; paddingtop: number; paddingbottom: number;
        items: Array<{icon: string; text: string}>;
    }>(widget.data, {
        title: widget.title, subtitle: widget.subtitle, bgcolor: "var(--mc-primary)", closing: "",
        titlecolor: "", titlefontsize: 0, subtitlecolor: "", subtitlefontsize: 0, iconcolor: "",
        iconsize: 0, textcolor: "", textfontsize: 0, labelcolor: "", labelfontsize: 0,
        paddingtop: 0, paddingbottom: 0, items: [],
    });
    const items = cfg.items.filter((item) => item.text);
    const style: CSSProperties & Record<string, string | number> = {backgroundColor: cfg.bgcolor || "var(--mc-primary)"};
    setCssVar(style, "--mc-belief-title-color", cfg.titlecolor);
    setPxVar(style, "--mc-belief-title-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-belief-subtitle-color", cfg.subtitlecolor);
    setPxVar(style, "--mc-belief-subtitle-font-size", cfg.subtitlefontsize);
    setCssVar(style, "--mc-belief-icon-color", cfg.iconcolor);
    setPxVar(style, "--mc-belief-icon-size", cfg.iconsize);
    setCssVar(style, "--mc-belief-copy-color", cfg.textcolor);
    setPxVar(style, "--mc-belief-copy-font-size", cfg.textfontsize);
    setCssVar(style, "--mc-belief-closing-color", cfg.labelcolor);
    setPxVar(style, "--mc-belief-closing-font-size", cfg.labelfontsize);
    setPxVar(style, "--mc-belief-padding-top", cfg.paddingtop);
    setPxVar(style, "--mc-belief-padding-bottom", cfg.paddingbottom);

    return (
        <section className="mc-belief text-white text-center" style={style}>
            <div className="mc-belief__inner container px-4">
                {(cfg.title || cfg.subtitle) && (
                    <header className="mc-belief__header mx-auto mb-5">
                        {cfg.title && <h2 className="mc-belief__title fw-bold mb-3">{cfg.title}</h2>}
                        {cfg.subtitle && <p className="mc-belief__subtitle fw-semibold mb-0">{cfg.subtitle}</p>}
                    </header>
                )}
                {items.length > 0 && (
                    <div className="row g-4 g-lg-5 justify-content-center">
                        {items.map((item, i) => {
                            const icon = item.icon.trim().replace(/^bi\s+/, "").replace(/^bi-/, "") || "globe2";
                            return (
                                <div className="col-12 col-sm-6 col-lg-3 d-flex flex-column align-items-center" key={i}>
                                    <span className="mc-belief__icon d-inline-flex align-items-center justify-content-center mb-3">
                                        <i className={`bi bi-${icon}`} aria-hidden="true" />
                                    </span>
                                    <p className="mc-belief__copy fw-semibold mx-auto mb-0">{item.text}</p>
                                </div>
                            );
                        })}
                    </div>
                )}
                {cfg.closing && <p className="mc-belief__closing fw-bold mx-auto mb-0">{cfg.closing}</p>}
            </div>
        </section>
    );
}

function PolicyWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        title: string; subtitle: string; effectivedate: string;
        bgcolor: string; cardbgcolor: string; cardbordercolor: string; cardborderwidth: number; cardradius: number;
        titlecolor: string; titlefontsize: number; subtitlecolor: string; subtitlefontsize: number;
        labelcolor: string; labelfontsize: number; textcolor: string; textfontsize: number;
        paddingtop: number; paddingbottom: number;
        sections: Array<{heading: string; body: string[]; bullets: string[]}>;
    }>(widget.data, {
        title: widget.title, subtitle: widget.subtitle, effectivedate: "", bgcolor: "", cardbgcolor: "",
        cardbordercolor: "", cardborderwidth: 0, cardradius: 0, titlecolor: "", titlefontsize: 0,
        subtitlecolor: "", subtitlefontsize: 0, labelcolor: "", labelfontsize: 0, textcolor: "",
        textfontsize: 0, paddingtop: 0, paddingbottom: 0, sections: [],
    });
    const style: CSSProperties & Record<string, string | number> = {};
    if (cfg.bgcolor) {
        style.backgroundColor = cfg.bgcolor;
    }
    setCssVar(style, "--mc-pw-card-bg", cfg.cardbgcolor);
    setCssVar(style, "--mc-pw-card-border", cfg.cardbordercolor);
    setPxVar(style, "--mc-pw-card-border-width", cfg.cardborderwidth);
    setPxVar(style, "--mc-pw-card-radius", cfg.cardradius);
    setCssVar(style, "--mc-pw-title-color", cfg.titlecolor);
    setPxVar(style, "--mc-pw-title-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-pw-subtitle-color", cfg.subtitlecolor);
    setPxVar(style, "--mc-pw-subtitle-font-size", cfg.subtitlefontsize);
    setCssVar(style, "--mc-pw-label-color", cfg.labelcolor);
    setPxVar(style, "--mc-pw-label-font-size", cfg.labelfontsize);
    setCssVar(style, "--mc-pw-text-color", cfg.textcolor);
    setPxVar(style, "--mc-pw-text-font-size", cfg.textfontsize);
    setPaddingVars(style, cfg.paddingtop, cfg.paddingbottom);
    return (
        <section className="mc-pw-policy" style={style}>
            {(cfg.title || cfg.subtitle || cfg.effectivedate) && (
                <header className="mc-pw-sectionhead">
                    {cfg.title && <h2>{cfg.title}</h2>}
                    {cfg.subtitle && <p>{cfg.subtitle}</p>}
                    {cfg.effectivedate && <span>{cfg.effectivedate}</span>}
                </header>
            )}
            <div className="mc-pw-policy__sections">
                {cfg.sections.map((section, i) => (
                    <article className="mc-pw-policy__section" key={i}>
                        {section.heading && <h3>{section.heading}</h3>}
                        {section.body.map((p, idx) => <p key={idx}>{p}</p>)}
                        {section.bullets.length > 0 && (
                            <ul>
                                {section.bullets.map((item, idx) => <li key={idx}>{item}</li>)}
                            </ul>
                        )}
                    </article>
                ))}
            </div>
        </section>
    );
}

function FaqWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        title: string; subtitle: string; bgcolor: string; titlecolor: string; titlefontsize: number;
        subtitlecolor: string; subtitlefontsize: number; itembgcolor: string; itembordercolor: string;
        cardborderwidth: number; cardradius: number; questioncolor: string; labelfontsize: number;
        answercolor: string; textfontsize: number; iconcolor: string; paddingtop: number; paddingbottom: number;
        items: Array<{question: string; answer: string[]}>;
    }>(widget.data, {
        title: widget.title, subtitle: widget.subtitle, bgcolor: "", titlecolor: "", titlefontsize: 0,
        subtitlecolor: "", subtitlefontsize: 0, itembgcolor: "", itembordercolor: "",
        cardborderwidth: 0, cardradius: 0, questioncolor: "", labelfontsize: 0, answercolor: "",
        textfontsize: 0, iconcolor: "", paddingtop: 0, paddingbottom: 0, items: [],
    });
    const style: CSSProperties & Record<string, string | number> = {};
    if (cfg.bgcolor) {
        style.backgroundColor = cfg.bgcolor;
    }
    setCssVar(style, "--mc-pw-title-color", cfg.titlecolor);
    setPxVar(style, "--mc-pw-title-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-pw-subtitle-color", cfg.subtitlecolor);
    setPxVar(style, "--mc-pw-subtitle-font-size", cfg.subtitlefontsize);
    setCssVar(style, "--mc-pw-item-bg", cfg.itembgcolor);
    setCssVar(style, "--mc-pw-item-border", cfg.itembordercolor);
    setPxVar(style, "--mc-pw-item-border-width", cfg.cardborderwidth);
    setPxVar(style, "--mc-pw-item-radius", cfg.cardradius);
    setCssVar(style, "--mc-pw-question-color", cfg.questioncolor);
    setPxVar(style, "--mc-pw-question-font-size", cfg.labelfontsize);
    setCssVar(style, "--mc-pw-answer-color", cfg.answercolor);
    setPxVar(style, "--mc-pw-answer-font-size", cfg.textfontsize);
    setCssVar(style, "--mc-pw-icon-color", cfg.iconcolor);
    setPaddingVars(style, cfg.paddingtop, cfg.paddingbottom);
    return (
        <section className="mc-pw-faq" style={style}>
            {(cfg.title || cfg.subtitle) && (
                <header className="mc-pw-sectionhead">
                    {cfg.title && <h2>{cfg.title}</h2>}
                    {cfg.subtitle && <p>{cfg.subtitle}</p>}
                </header>
            )}
            <div className="mc-pw-faq__items">
                {cfg.items.map((item, i) => (
                    <details className="mc-pw-faq__item" key={i}>
                        <summary>{item.question}</summary>
                        <div>
                            {item.answer.map((p, idx) => <p key={idx}>{p}</p>)}
                        </div>
                    </details>
                ))}
            </div>
        </section>
    );
}

function CtaWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        heading: string; text: string; tone: string;
        bgcolor: string; titlecolor: string; titlefontsize: number; textcolor: string; textfontsize: number;
        primarybuttoncolor: string; primarybuttontextcolor: string; secondarybuttoncolor: string;
        secondarybuttontextcolor: string; buttonradius: number; cardradius: number;
        paddingtop: number; paddingbottom: number;
        primary: {label: string; url: string}; secondary: {label: string; url: string};
    }>(widget.data, {
        heading: widget.title, text: widget.subtitle, tone: "primary",
        bgcolor: "", titlecolor: "", titlefontsize: 0, textcolor: "", textfontsize: 0,
        primarybuttoncolor: "", primarybuttontextcolor: "", secondarybuttoncolor: "", secondarybuttontextcolor: "",
        buttonradius: 0, cardradius: 0, paddingtop: 0, paddingbottom: 0,
        primary: {label: "", url: ""}, secondary: {label: "", url: ""},
    });
    const style: CSSProperties & Record<string, string | number> = {};
    if (cfg.bgcolor) {
        style.backgroundColor = cfg.bgcolor;
    }
    setCssVar(style, "--mc-pw-cta-title-color", cfg.titlecolor);
    setPxVar(style, "--mc-pw-cta-title-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-pw-cta-text-color", cfg.textcolor);
    setPxVar(style, "--mc-pw-cta-text-font-size", cfg.textfontsize);
    setCssVar(style, "--mc-pw-cta-primary-bg", cfg.primarybuttoncolor);
    setCssVar(style, "--mc-pw-cta-primary-text", cfg.primarybuttontextcolor);
    setCssVar(style, "--mc-pw-cta-secondary-bg", cfg.secondarybuttoncolor);
    setCssVar(style, "--mc-pw-cta-secondary-text", cfg.secondarybuttontextcolor);
    setPxVar(style, "--mc-pw-cta-button-radius", cfg.buttonradius);
    setPxVar(style, "--mc-pw-cta-radius", cfg.cardradius);
    setPaddingVars(style, cfg.paddingtop, cfg.paddingbottom);
    return (
        <section className={`mc-pw-cta mc-pw-cta--${cfg.tone}`} style={style}>
            <div>
                {cfg.heading && <h2>{cfg.heading}</h2>}
                {cfg.text && <p>{cfg.text}</p>}
            </div>
            <div className="mc-pw-cta__actions">
                {cfg.primary.label && cfg.primary.url && (
                    <a className="mc-button btn-mc-light mc-pw-cta__primary" href={cfg.primary.url} data-mc-button="light">{cfg.primary.label}</a>
                )}
                {cfg.secondary.label && cfg.secondary.url && (
                    <a className="mc-button btn-mc-light mc-pw-cta__secondary" href={cfg.secondary.url} data-mc-button="light">{cfg.secondary.label}</a>
                )}
            </div>
        </section>
    );
}

function SupportFormWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        heading: string; description: string; action: string; supportemail: string; emailurl: string;
        buttonlabel: string; emailbuttonlabel: string; messagelabel: string; messageplaceholder: string;
        bgcolor: string; cardbgcolor: string; cardbordercolor: string; cardborderwidth: number; cardradius: number;
        titlecolor: string; titlefontsize: number; textcolor: string; textfontsize: number; formlabelcolor: string;
        inputbgcolor: string; inputbordercolor: string; inputtextcolor: string; buttoncolor: string; buttontextcolor: string;
        secondarybuttoncolor: string; secondarybuttontextcolor: string; buttonradius: number; paddingtop: number; paddingbottom: number;
        defaultname: string; defaultemail: string; categories: Array<{value: string; label: string}>;
        recaptcha: RecaptchaConfig;
        labels: {name: string; email: string; category: string; ordernumber: string; optional: string; message: string; sending: string};
    }>(widget.data, {
        heading: widget.title, description: widget.subtitle, action: "", supportemail: "", emailurl: "",
        buttonlabel: "", emailbuttonlabel: "",
        messagelabel: "", messageplaceholder: "", defaultname: "", defaultemail: "",
        bgcolor: "", cardbgcolor: "", cardbordercolor: "", cardborderwidth: 0, cardradius: 0,
        titlecolor: "", titlefontsize: 0, textcolor: "", textfontsize: 0, formlabelcolor: "",
        inputbgcolor: "", inputbordercolor: "", inputtextcolor: "", buttoncolor: "", buttontextcolor: "",
        secondarybuttoncolor: "", secondarybuttontextcolor: "", buttonradius: 0, paddingtop: 0, paddingbottom: 0,
        categories: [],
        recaptcha: recaptchaConfigDefaults(),
        labels: {name: "", email: "", category: "", ordernumber: "", optional: "", message: "", sending: ""},
    });
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState("");
    const style: CSSProperties & Record<string, string | number> = {};
    if (cfg.bgcolor) {
        style.backgroundColor = cfg.bgcolor;
    }
    setCssVar(style, "--mc-pw-card-bg", cfg.cardbgcolor);
    setCssVar(style, "--mc-pw-card-border", cfg.cardbordercolor);
    setPxVar(style, "--mc-pw-card-border-width", cfg.cardborderwidth);
    setPxVar(style, "--mc-pw-card-radius", cfg.cardradius);
    setCssVar(style, "--mc-pw-title-color", cfg.titlecolor);
    setPxVar(style, "--mc-pw-title-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-pw-text-color", cfg.textcolor);
    setPxVar(style, "--mc-pw-text-font-size", cfg.textfontsize);
    setCssVar(style, "--mc-pw-form-label-color", cfg.formlabelcolor);
    setCssVar(style, "--mc-pw-input-bg", cfg.inputbgcolor);
    setCssVar(style, "--mc-pw-input-border", cfg.inputbordercolor);
    setCssVar(style, "--mc-pw-input-text", cfg.inputtextcolor);
    setCssVar(style, "--mc-pw-button-bg", cfg.buttoncolor);
    setCssVar(style, "--mc-pw-button-text", cfg.buttontextcolor);
    setCssVar(style, "--mc-pw-secondary-button-bg", cfg.secondarybuttoncolor);
    setCssVar(style, "--mc-pw-secondary-button-text", cfg.secondarybuttontextcolor);
    setPxVar(style, "--mc-pw-button-radius", cfg.buttonradius);
    setPaddingVars(style, cfg.paddingtop, cfg.paddingbottom);
    const handleSupportSubmit = (event: FormEvent<HTMLFormElement>) => {
        if (cfg.recaptcha.enabled && !getRecaptchaResponse(event.currentTarget)) {
            event.preventDefault();
            setSubmitError(cfg.recaptcha.requiredmessage);
            return;
        }
        setSubmitError("");
        setSubmitting(true);
    };

    return (
        <section className="mc-pw-support" style={style}>
            {(cfg.heading || cfg.description) && (
                <header className="mc-pw-sectionhead">
                    {cfg.heading && <h2>{cfg.heading}</h2>}
                    {cfg.description && <p>{cfg.description}</p>}
                </header>
            )}
            <form method="post" action={cfg.action} className="mc-pw-support__form" onSubmit={handleSupportSubmit}>
                <input type="hidden" name="sesskey" value={M.cfg.sesskey} />
                <div className="mc-pw-formgrid">
                    <label>
                        <span>{cfg.labels.name}</span>
                        <input name="name" required type="text" defaultValue={cfg.defaultname} />
                    </label>
                    <label>
                        <span>{cfg.labels.email}</span>
                        <input name="email" required type="email" defaultValue={cfg.defaultemail} />
                    </label>
                    <label>
                        <span>{cfg.labels.category}</span>
                        <select name="category" defaultValue="general">
                            {cfg.categories.map((category) => (
                                <option key={category.value} value={category.value}>{category.label}</option>
                            ))}
                        </select>
                    </label>
                    <label>
                        <span>{cfg.labels.ordernumber}</span>
                        <input name="ordernumber" type="text" placeholder={cfg.labels.optional} />
                    </label>
                    <label className="mc-pw-formgrid__wide">
                        <span>{cfg.messagelabel || cfg.labels.message}</span>
                        <textarea name="message" rows={6} required placeholder={cfg.messageplaceholder} />
                    </label>
                </div>
                <RecaptchaField config={cfg.recaptcha} className="mc-pw-recaptcha" />
                {submitError && <p className="mc-pw-support__error">{submitError}</p>}
                <div className="mc-pw-support__actions">
                    <McButton className="btn-mc-primary" loading={submitting} loadingLabel={cfg.labels.sending} type="submit">
                        {cfg.buttonlabel}
                    </McButton>
                    {cfg.emailurl && <a className="mc-button mc-btn-soft" data-mc-button="soft"
                        href={cfg.emailurl}>{cfg.emailbuttonlabel}</a>}
                </div>
            </form>
        </section>
    );
}

function ContactCardsWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<{
        title: string; subtitle: string; bgcolor: string; titlecolor: string; titlefontsize: number;
        subtitlecolor: string; subtitlefontsize: number; cardbgcolor: string; cardbordercolor: string;
        cardborderwidth: number; cardradius: number; iconbgcolor: string; iconcolor: string; iconsize: number;
        labelcolor: string; labelfontsize: number; textcolor: string; textfontsize: number; linkcolor: string;
        paddingtop: number; paddingbottom: number;
        cards: Array<{icon: string; title: string; text: string; linklabel: string; linkurl: string}>;
    }>(widget.data, {
        title: widget.title, subtitle: widget.subtitle, bgcolor: "", titlecolor: "", titlefontsize: 0,
        subtitlecolor: "", subtitlefontsize: 0, cardbgcolor: "", cardbordercolor: "", cardborderwidth: 0,
        cardradius: 0, iconbgcolor: "", iconcolor: "", iconsize: 0, labelcolor: "", labelfontsize: 0,
        textcolor: "", textfontsize: 0, linkcolor: "", paddingtop: 0, paddingbottom: 0, cards: [],
    });
    const style: CSSProperties & Record<string, string | number> = {};
    if (cfg.bgcolor) {
        style.backgroundColor = cfg.bgcolor;
    }
    setCssVar(style, "--mc-pw-title-color", cfg.titlecolor);
    setPxVar(style, "--mc-pw-title-font-size", cfg.titlefontsize);
    setCssVar(style, "--mc-pw-subtitle-color", cfg.subtitlecolor);
    setPxVar(style, "--mc-pw-subtitle-font-size", cfg.subtitlefontsize);
    setCssVar(style, "--mc-pw-card-bg", cfg.cardbgcolor);
    setCssVar(style, "--mc-pw-card-border", cfg.cardbordercolor);
    setPxVar(style, "--mc-pw-card-border-width", cfg.cardborderwidth);
    setPxVar(style, "--mc-pw-card-radius", cfg.cardradius);
    setCssVar(style, "--mc-pw-icon-bg", cfg.iconbgcolor);
    setCssVar(style, "--mc-pw-icon-color", cfg.iconcolor);
    setPxVar(style, "--mc-pw-icon-size", cfg.iconsize);
    setCssVar(style, "--mc-pw-label-color", cfg.labelcolor);
    setPxVar(style, "--mc-pw-label-font-size", cfg.labelfontsize);
    setCssVar(style, "--mc-pw-text-color", cfg.textcolor);
    setPxVar(style, "--mc-pw-text-font-size", cfg.textfontsize);
    setCssVar(style, "--mc-pw-link-color", cfg.linkcolor);
    setPaddingVars(style, cfg.paddingtop, cfg.paddingbottom);
    return (
        <section className="mc-pw-contactcards" style={style}>
            {(cfg.title || cfg.subtitle) && (
                <header className="mc-pw-sectionhead">
                    {cfg.title && <h2>{cfg.title}</h2>}
                    {cfg.subtitle && <p>{cfg.subtitle}</p>}
                </header>
            )}
            <div className="mc-pw-contactcards__grid">
                {cfg.cards.map((card, i) => (
                    <article className="mc-pw-contactcards__card" key={i}>
                        <span className="mc-pw-icon"><i className={`bi bi-${card.icon}`} aria-hidden="true" /></span>
                        {card.title && <h3>{card.title}</h3>}
                        {card.text && <p>{card.text}</p>}
                        {card.linklabel && card.linkurl && <a href={card.linkurl}>{card.linklabel}</a>}
                    </article>
                ))}
            </div>
        </section>
    );
}

function FooterWidget({widget}: {widget: WidgetInstance}) {
    const data = {...footerDataDefaults(), ...parseJson<Partial<FooterData>>(widget.data, {})};
    const style: CSSProperties & Record<string, string | number> = {};
    setCssVar(style, "--mc-footer-bg", data.bgcolor);
    setCssVar(style, "--mc-footer-en-bg", data.bgcolor);
    setCssVar(style, "--mc-footer-mc-bottom", data.panelbgcolor);
    setCssVar(style, "--mc-footer-soft", data.panelbgcolor);
    setCssVar(style, "--mc-footer-heading", data.titlecolor);
    setCssVar(style, "--mc-footer-en-text", data.titlecolor);
    setPxVar(style, "--mc-footer-heading-font-size", data.titlefontsize);
    setCssVar(style, "--mc-footer-text", data.textcolor);
    setCssVar(style, "--mc-footer-mc-muted", data.textcolor);
    setCssVar(style, "--mc-footer-en-muted", data.textcolor);
    setPxVar(style, "--mc-footer-text-font-size", data.textfontsize);
    setCssVar(style, "--mc-footer-link", data.linkcolor);
    setCssVar(style, "--mc-footer-chip", data.iconbgcolor);
    setCssVar(style, "--mc-footer-accent", data.iconcolor);
    setCssVar(style, "--mc-footer-en-accent", data.iconcolor);
    setCssVar(style, "--mc-footer-input-bg", data.inputbgcolor);
    setCssVar(style, "--mc-footer-input-border", data.inputbordercolor);
    setCssVar(style, "--mc-footer-input-text", data.inputtextcolor);
    setCssVar(style, "--mc-footer-button-bg", data.buttoncolor);
    setCssVar(style, "--mc-footer-button-text", data.buttontextcolor);
    setPaddingVars(style, data.paddingtop, data.paddingbottom);
    return <Footer data={data as FooterData} style={style} />;
}

type BreadcrumbData = {
    hidden: boolean;
    currentpage: string;
    style: string;
    alignment: string;
    title: string;
    subtitle: string;
    items: Array<{label: string; url: string; active: boolean}>;
    backgroundimage: string;
    bgcolor: string;
    overlaycolor: string;
    gradientstart: string;
    gradientend: string;
    textcolor: string;
    accentcolor: string;
    breadcrumbcolor?: string;
    titlecolor?: string;
    subtitlecolor?: string;
    breadcrumbfontsize?: number;
    titlefontsize?: number;
    subtitlefontsize?: number;
    overlayopacity?: number | null;
    paddingtop: number;
    paddingbottom: number;
    labels: {breadcrumb: string};
};

function BreadcrumbWidget({widget}: {widget: WidgetInstance}) {
    const cfg = parseJson<BreadcrumbData>(widget.data, {
        hidden: false,
        currentpage: "",
        style: "imagehero",
        alignment: "center",
        title: widget.title,
        subtitle: "",
        items: [],
        backgroundimage: "",
        bgcolor: "var(--mc-surface-alt)",
        overlaycolor: "var(--mc-text)",
        gradientstart: "var(--mc-primary)",
        gradientend: "var(--mc-primary-hover)",
        textcolor: "var(--mc-text)",
        accentcolor: "var(--mc-primary)",
        breadcrumbcolor: "",
        titlecolor: "",
        subtitlecolor: "",
        breadcrumbfontsize: 0,
        titlefontsize: 0,
        subtitlefontsize: 0,
        overlayopacity: null,
        paddingtop: 82,
        paddingbottom: 82,
        labels: {breadcrumb: ""},
    });
    if (cfg.hidden) {
        return null;
    }

    const styleKey = ["imagehero", "clean", "gradient", "pastel", "illustration"].includes(cfg.style)
        ? cfg.style
        : "imagehero";
    const alignment = ["left", "center", "right"].includes(cfg.alignment) ? cfg.alignment : "center";
    const sectionStyle = {
        "--mc-bc-bg": cfg.bgcolor || "var(--mc-surface-alt)",
        "--mc-bc-overlay": cfg.overlaycolor || "var(--mc-text)",
        "--mc-bc-gradient-start": cfg.gradientstart || "var(--mc-primary)",
        "--mc-bc-gradient-end": cfg.gradientend || "var(--mc-primary-hover)",
        "--mc-bc-text": cfg.textcolor || "var(--mc-text)",
        "--mc-bc-accent": cfg.accentcolor || "var(--mc-primary)",
        paddingTop: Math.max(32, Number(cfg.paddingtop) || 82),
        paddingBottom: Math.max(32, Number(cfg.paddingbottom) || 82),
    } as CSSProperties & Record<string, string | number>;
    if (cfg.overlayopacity !== null && typeof cfg.overlayopacity !== "undefined" && String(cfg.overlayopacity) !== "") {
        sectionStyle["--mc-bc-overlay-opacity"] = `${Math.max(0, Math.min(100, Number(cfg.overlayopacity))) / 100}`;
    }
    if (cfg.backgroundimage && (styleKey === "imagehero" || styleKey === "gradient")) {
        sectionStyle.backgroundImage = `url("${cfg.backgroundimage}")`;
    }

    const textStyle = (color?: string, size?: number): CSSProperties | undefined => {
        const style: CSSProperties = {};
        if (color) {
            style.color = color;
        }
        if (Number(size) > 0) {
            style.fontSize = `${Number(size)}px`;
        }
        return Object.keys(style).length > 0 ? style : undefined;
    };
    const breadcrumbStyle = textStyle(cfg.breadcrumbcolor, cfg.breadcrumbfontsize);
    const titleStyle = textStyle(cfg.titlecolor, cfg.titlefontsize);
    const subtitleStyle = textStyle(cfg.subtitlecolor, cfg.subtitlefontsize);

    return (
        <section
            className={`mc-breadcrumb-widget mc-breadcrumb-widget--${styleKey} mc-breadcrumb-widget--${alignment}`}
            style={sectionStyle}
            data-current-page={cfg.currentpage}
        >
            <span className="mc-breadcrumb-widget__mark mc-breadcrumb-widget__mark--one" aria-hidden="true" />
            <span className="mc-breadcrumb-widget__mark mc-breadcrumb-widget__mark--two" aria-hidden="true" />
            <div className="mc-breadcrumb-widget__inner">
                {cfg.items.length > 0 && (
                    <nav className="mc-breadcrumb-widget__trail" aria-label={cfg.labels.breadcrumb} style={breadcrumbStyle}>
                        {cfg.items.map((item, index) => (
                            <span className="mc-breadcrumb-widget__crumbwrap" key={`${item.label}-${index}`}>
                                {item.url && !item.active ? (
                                    <a href={item.url}>{item.label}</a>
                                ) : (
                                    <span aria-current={item.active ? "page" : undefined}>{item.label}</span>
                                )}
                                {index < cfg.items.length - 1 && (
                                    <span className="mc-breadcrumb-widget__separator" aria-hidden="true">/</span>
                                )}
                            </span>
                        ))}
                    </nav>
                )}
                {cfg.title && <h1 className="mc-breadcrumb-widget__title" style={titleStyle}>{cfg.title}</h1>}
                {cfg.subtitle && <p className="mc-breadcrumb-widget__subtitle" style={subtitleStyle}>{cfg.subtitle}</p>}
            </div>
        </section>
    );
}

const WIDGETS: Record<string, FC<{widget: WidgetInstance}>> = {
    slider: SliderWidget, featured: FeaturedWidget, related: FeaturedWidget,
    trustbadges: TrustBadgesWidget, partners: PartnersWidget,
    countdown: CountdownWidget, testimonials: TestimonialsWidget,
    categories: CategoriesWidget, instructors: InstructorsWidget, newsletter: NewsletterWidget,
    videohero: VideoHeroWidget, mediastorycarousel: MediaStoryCarouselWidget,
    breadcrumb: BreadcrumbWidget,
    content: ContentWidget, learningpromise: LearningPromiseWidget,
    leadermessage: LeaderMessageWidget, featuregrid: FeatureGridWidget,
    subscriptionplans: SubscriptionPlansWidget,
    belief: BeliefWidget, policy: PolicyWidget, faq: FaqWidget,
    cta: CtaWidget, supportform: SupportFormWidget, contactcards: ContactCardsWidget,
    footer: FooterWidget,
};

export function RenderedWidget(
    {widget, catalogLabels, editing, typeLabel, onGear, previewMode = false}:
    {widget: WidgetInstance; catalogLabels: Labels; editing: boolean; typeLabel: string;
        onGear: (id: number) => void; previewMode?: boolean}
) {
    let body;
    if (widget.type === "catalog") {
        body = <CatalogWidget widget={widget} catalogLabels={catalogLabels} previewMode={previewMode} />;
    } else {
        const Component = WIDGETS[widget.type];
        body = Component ? <Component widget={widget} /> : null;
    }

    const style = wrapperStyleFromConfig(widget);

    return (
        <div className={`mw-widget mw-widget--${widget.type} mc-sf-widget`} style={style} data-widget-id={widget.id}>
            {editing && (
                <span className="mc-sf-widget__tag">
                    {typeLabel || widget.type}
                    <button type="button" className="mc-button mc-sf-widget__gear" data-mc-button="ghost"
                        data-mc-button-size="icon" title="Edit settings"
                        onClick={() => onGear(widget.id)}>
                        <i className="bi bi-gear" aria-hidden="true" />
                    </button>
                </span>
            )}
            {body}
        </div>
    );
}

// --- Generic field editor ------------------------------------------------------------

type FieldControlLabels = {
    moveup?: string;
    movedown?: string;
    add?: string;
    remove?: string;
    filecouldnotread?: string;
    removeimage?: string;
    removevideo?: string;
    uploadfailed?: string;
    uploading?: string;
    videouploaded?: string;
};

/**
 * Wraps the schema-driven control with its inline error. Field has a branch per
 * field type, so decorating the rendered output once is the only way to give
 * every widget type the same treatment without touching each branch.
 */
function Field(props: FieldProps) {
    const {field, error} = props;
    const rendered = FieldControl(props);
    if (!error) {
        return rendered;
    }

    const id = `mc-sf-f-${field.name}`;
    return (
        <div className="mc-sf-field-wrap" data-mc-invalid="true">
            {rendered}
            <FieldError id={`${id}-error`} message={error} />
        </div>
    );
}

type FieldProps = {
    field: FieldDef; value: unknown; onChange: (v: unknown) => void; iconOptions?: IconOption[];
    uploadMethod?: string; videoUploadUrl?: string; contextValues?: Record<string, unknown>;
    controlLabels?: FieldControlLabels; error?: string;
};

function FieldControl(
    {field, value, onChange, iconOptions, uploadMethod = "", videoUploadUrl = "", contextValues = {},
        controlLabels = {}}: FieldProps
) {
    const id = `mc-sf-f-${field.name}`;
    const controls = {
        moveup: controlLabels.moveup ?? "Move up",
        movedown: controlLabels.movedown ?? "Move down",
        add: controlLabels.add ?? "Add",
        remove: controlLabels.remove ?? "Remove",
    };
    if (field.type === "icon") {
        const choices = field.choices ?? {};
        const options = Object.keys(choices).length > 0
            ? Object.entries(choices).map(([v, l]) => ({value: v, label: l}))
            : (iconOptions ?? []);
        return <IconPicker label={field.label} value={String(value ?? "")}
            options={options} onChange={(v) => onChange(v)} />;
    }
    if (field.type === "image") {
        return <SlideImageField label={field.label} uploadMethod={uploadMethod}
            value={String(value || "")} labels={controlLabels} onChange={(v) => onChange(v)} />;
    }
    if (field.type === "videofile") {
        return <VideoUploadField label={field.label} uploadUrl={videoUploadUrl}
            value={value} labels={controlLabels} onChange={(v) => onChange(v)} />;
    }
    if (field.type === "checkbox") {
        // An unset value means the widget predates the field, so fall back to the
        // schema default rather than showing an unticked box for on-by-default fields.
        const checked = typeof value === "undefined" || value === null
            ? field.default === true || String(field.default) === "1"
            : !!value;
        return (
            <div className="mc-sf-field">
                <label className="mc-sf-switch" htmlFor={id}>
                    <span>{field.label}</span>
                    <input id={id} type="checkbox" checked={checked}
                        onChange={(e) => onChange(e.target.checked)} />
                    <i aria-hidden="true" />
                </label>
            </div>
        );
    }
    if (field.type === "select") {
        return (
            <div className="mc-sf-field">
                <label htmlFor={id}>{field.label}</label>
                <select id={id} value={String(value ?? "")} onChange={(e) => onChange(e.target.value)}>
                    {Object.entries(field.choices ?? {}).map(([v, l]) => (
                        <option key={v} value={v}>{l}</option>
                    ))}
                </select>
            </div>
        );
    }
    if (field.type === "textarea") {
        return (
            <div className="mc-sf-field">
                <label htmlFor={id}>{field.label}</label>
                <textarea id={id} value={String(value ?? "")} onChange={(e) => onChange(e.target.value)} />
            </div>
        );
    }
    if (field.type === "list") {
        const rows = Array.isArray(value) ? (value as Array<Record<string, unknown>>) : [];
        const sub = field.fields ?? [];
        const blank = () => Object.fromEntries(sub.map((f) => [f.name, f.default ?? ""]));
        const update = (i: number, k: string, v: unknown) =>
            onChange(rows.map((r, idx) => (idx === i ? {...r, [k]: v} : r)));
        const move = (i: number, delta: number) => {
            const t = i + delta;
            if (t < 0 || t >= rows.length) {
                return;
            }
            const next = [...rows];
            [next[i], next[t]] = [next[t], next[i]];
            onChange(next);
        };
        return (
            <div className="mc-sf-field">
                <label>{field.label}</label>
                {rows.map((row, i) => (
                    <div className="mc-sf-list__item" key={i}>
                        {sub.filter((f) => shouldShowField(f, {...contextValues, ...row})).map((f) => (
                            <Field key={f.name} field={f} value={row[f.name]} iconOptions={iconOptions}
                                uploadMethod={uploadMethod} videoUploadUrl={videoUploadUrl}
                                contextValues={{...contextValues, ...row}}
                                controlLabels={controlLabels}
                                onChange={(v) => update(i, f.name, v)} />
                        ))}
                        <div className="mc-sf-list__actions">
                            <button type="button" className="mc-button mc-sf-list__move" data-mc-button="ghost"
                                data-mc-button-size="icon" disabled={i === 0}
                                aria-label={controls.moveup} onClick={() => move(i, -1)}>
                                <i className="bi bi-arrow-up" aria-hidden="true" />
                            </button>
                            <button type="button" className="mc-button mc-sf-list__move" data-mc-button="ghost"
                                data-mc-button-size="icon" disabled={i === rows.length - 1}
                                aria-label={controls.movedown} onClick={() => move(i, 1)}>
                                <i className="bi bi-arrow-down" aria-hidden="true" />
                            </button>
                            <button type="button" className="mc-button mc-sf-list__remove" data-mc-button="ghost"
                                onClick={() => onChange(rows.filter((_, idx) => idx !== i))}>
                                <i className="bi bi-trash" aria-hidden="true" /> {controls.remove}
                            </button>
                        </div>
                    </div>
                ))}
                <button type="button" className="mc-button mc-sf-btn-add" data-mc-button="soft"
                    onClick={() => onChange([...rows, blank()])}>
                    <i className="bi bi-plus-lg" aria-hidden="true" /> {controls.add}
                </button>
            </div>
        );
    }
    if (field.type === "datetime") {
        // Stored as Unix seconds; the <input> works in the browser's local time.
        const seconds = Number(value) || 0;
        const pad2 = (n: number): string => String(n).padStart(2, "0");
        const toLocal = (s: number): string => {
            if (!s) {
                return "";
            }
            const d = new Date(s * 1000);
            return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`
                + `T${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
        };
        return (
            <div className="mc-sf-field">
                <label htmlFor={id}>{field.label}</label>
                <input id={id} type="datetime-local" value={toLocal(seconds)}
                    onChange={(e) => {
                        const v = e.target.value;
                        onChange(v ? Math.floor(new Date(v).getTime() / 1000) : 0);
                    }} />
            </div>
        );
    }
    if (field.type === "color") {
        const raw = String(value ?? "");
        const swatch = /^#[0-9a-fA-F]{6}$/.test(raw) ? raw
            : (/^#[0-9a-fA-F]{6}$/.test(String(field.default ?? "")) ? String(field.default) : "#7c3aed");
        return (
            <div className="mc-sf-field">
                <label htmlFor={id}>{field.label}</label>
                <div className="mc-sf-color">
                    <input type="color" className="mc-sf-color__swatch" value={swatch}
                        aria-label={field.label} onChange={(e) => onChange(e.target.value)} />
                    <input id={id} type="text" className="mc-sf-color__text" value={raw}
                        placeholder={swatch} onChange={(e) => onChange(e.target.value)} />
                </div>
            </div>
        );
    }
    const inputtype = field.type === "number" ? "number" : (field.type === "url" ? "url" : "text");
    const placeholder = typeof field.default !== "undefined" && String(field.default) !== ""
        ? String(field.default)
        : undefined;
    return (
        <div className="mc-sf-field">
            <label htmlFor={id}>{field.label}</label>
            <input id={id} type={inputtype} value={String(value ?? "")} placeholder={placeholder}
                onChange={(e) => onChange(field.type === "number"
                    ? (e.target.value === "" ? "" : Number(e.target.value))
                    : e.target.value)} />
        </div>
    );
}


/** Searchable Bootstrap-icon typeahead with live previews (stores the bare icon name). */
/** File-upload control for a slide image: uploads to Moodle storage, stores the returned URL. */
function SlideImageField(
    {value, label, uploadMethod, labels = {}, onChange}:
    {value: string; label: string; uploadMethod: string; labels?: FieldControlLabels; onChange: (v: string) => void}
) {
    const [busy, setBusy] = useState(false);
    const [err, setErr] = useState("");
    const text = {
        filecouldnotread: labels.filecouldnotread ?? "Could not read the file.",
        removeimage: labels.removeimage ?? "Remove image",
        uploading: labels.uploading ?? "Uploading...",
    };
    const onFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files && e.target.files[0];
        e.target.value = "";
        if (!file) {
            return;
        }
        setErr("");
        setBusy(true);
        const reader = new FileReader();
        reader.onload = () => {
            const result = String(reader.result || "");
            const base64 = result.includes(",") ? result.slice(result.indexOf(",") + 1) : result;
            void callService<{url: string}>(uploadMethod, {filename: file.name, content: base64})
                .then((r) => { onChange(r.url); return null; })
                .catch((caught: unknown) => setErr(caught instanceof Error ? caught.message : String(caught)))
                .finally(() => setBusy(false));
        };
        reader.onerror = () => { setErr(text.filecouldnotread); setBusy(false); };
        reader.readAsDataURL(file);
    };
    return (
        <div className="mc-sf-field">
            <label>{label}</label>
            {value && <img src={value} alt="" className="mc-sf-img-preview" />}
            <input type="file" accept="image/png,image/jpeg,image/gif,image/webp"
                onChange={onFile} disabled={busy} />
            {busy && <span className="mc-sf-img-hint">{text.uploading}</span>}
            {value && !busy && (
                <button type="button" className="mc-button mc-sf-list__remove" data-mc-button="ghost"
                    onClick={() => onChange("")}>
                    <i className="bi bi-trash" aria-hidden="true" /> {text.removeimage}
                </button>
            )}
            {err && <div className="mc-sf-error">{err}</div>}
        </div>
    );
}

type VideoValue = {url: string; mime: string};

/** Multipart upload control for a hero video file (handles large files via storefront_upload.php). */
function VideoUploadField(
    {value, label, uploadUrl, labels = {}, onChange}:
    {value: unknown; label: string; uploadUrl: string; labels?: FieldControlLabels; onChange: (v: VideoValue) => void}
) {
    const [busy, setBusy] = useState(false);
    const [err, setErr] = useState("");
    const text = {
        removevideo: labels.removevideo ?? "Remove video",
        uploadfailed: labels.uploadfailed ?? "Upload failed.",
        uploading: labels.uploading ?? "Uploading...",
        videouploaded: labels.videouploaded ?? "Video uploaded",
    };
    const current: VideoValue = value && typeof value === "object"
        ? (value as VideoValue) : {url: "", mime: ""};
    const onFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files && e.target.files[0];
        e.target.value = "";
        if (!file) {
            return;
        }
        setErr("");
        setBusy(true);
        const fd = new FormData();
        fd.append("file", file);
        fd.append("sesskey", M.cfg.sesskey);
        void fetch(uploadUrl, {method: "POST", credentials: "same-origin", body: fd})
            .then((r) => r.json())
            .then((j) => {
                if (j && j.ok) { onChange({url: j.url, mime: j.mimetype}); } else { setErr((j && j.error) || text.uploadfailed); }
                return null;
            })
            .catch((caught: unknown) => setErr(caught instanceof Error ? caught.message : String(caught)))
            .finally(() => setBusy(false));
    };
    return (
        <div className="mc-sf-field">
            <label>{label}</label>
            {current.url && (
                <div className="mc-sf-img-hint">
                    <i className="bi bi-camera-video" aria-hidden="true" /> {text.videouploaded}
                </div>
            )}
            <input type="file" accept="video/mp4,video/webm,video/ogg,video/quicktime"
                onChange={onFile} disabled={busy} />
            {busy && <span className="mc-sf-img-hint">{text.uploading}</span>}
            {current.url && !busy && (
                <button type="button" className="mc-button mc-sf-list__remove" data-mc-button="ghost"
                    onClick={() => onChange({url: "", mime: ""})}>
                    <i className="bi bi-trash" aria-hidden="true" /> {text.removevideo}
                </button>
            )}
            {err && <div className="mc-sf-error">{err}</div>}
        </div>
    );
}

const SLIDE_FIELDS: FieldDef[] = [
    {name: "heading", label: "Heading", type: "text"},
    {name: "subheading", label: "Subheading", type: "textarea"},
    {name: "image", label: "Image", type: "image"},
    {name: "ctalabel", label: "Button label", type: "text"},
    {name: "ctaurl", label: "Button URL", type: "url",
        showwhen: {field: "ctalabel", truthy: true}},
    {name: "ctastyle", label: "Button style", type: "select",
        choices: {primary: "Primary", light: "Light", outline: "Outline"},
        showwhen: {field: "ctalabel", truthy: true}},
    {name: "bgcolor", label: "Background colour", type: "color"},
];

// --- Main app ------------------------------------------------------------------------

export default function Storefront(props: Props) {
    const {
        getPageMethod, layoutGetMethod, layoutSaveMethod, widgetGetMethod, widgetSaveMethod, presetListMethod,
        addMethod, deleteMethod, uploadMethod, videoUploadUrl, iconOptions = [], editing = false,
        canManage = false, showToolbar = true, pageType = "catalog", onlyZone = "", renderContext = {}, toolbarTargetId = "",
        catalogLabels, labels,
    } = props;

    const [data, setData] = useState<GetPageResponse | null>(null);
    const [error, setError] = useState("");
    const [reload, setReload] = useState(0);
    const contextJson = useMemo(() => JSON.stringify(renderContext ?? {}), [renderContext]);

    // The widget customizer affordance appears only when the admin has Moodle's Edit mode switched on.
    const isEditing = editing && canManage;

    const [drawerOpen, setDrawerOpen] = useState(false);
    const [layout, setLayout] = useState<LayoutResponse | null>(null);
    const [rows, setRows] = useState<LayoutWidget[]>([]);
    const [busy, setBusy] = useState(false);
    const [addType, setAddType] = useState("");
    const [addZone, setAddZone] = useState("");
    const [addPresets, setAddPresets] = useState<WidgetPreset[]>([]);
    const [addPresetId, setAddPresetId] = useState(0);

    const [editorId, setEditorId] = useState<number | null>(null);
    const [editor, setEditor] = useState<WidgetEditorData | null>(null);
    const [editorBusy, setEditorBusy] = useState(false);
    const [editorFieldErrors, setEditorFieldErrors] = useState<FieldErrorMap>({});
    const [editorPresets, setEditorPresets] = useState<WidgetPreset[]>([]);
    const [editorPresetsLoading, setEditorPresetsLoading] = useState(false);
    const [selectedPresetId, setSelectedPresetId] = useState(0);
    const [activeSliderItem, setActiveSliderItem] = useState<SliderEditorItem>("design");
    const [activeBreadcrumbItem, setActiveBreadcrumbItem] = useState<BreadcrumbEditorItem>("breadcrumb");
    const [activeVideoHeroItem, setActiveVideoHeroItem] = useState<VideoHeroEditorItem>("background");
    const [activeCountdownItem, setActiveCountdownItem] = useState<CountdownEditorItem>("background");
    const [activeCategoriesItem, setActiveCategoriesItem] = useState<CategoriesEditorItem>("layout");
    const [activeFeaturedItem, setActiveFeaturedItem] = useState<FeaturedEditorItem>("heading");
    const [activeGenericItem, setActiveGenericItem] = useState<string>("content");

    useEffect(() => {
        let cancelled = false;
        callService<GetPageResponse>(getPageMethod, {page: pageType, zone: onlyZone, context: contextJson})
            .then((r) => { if (!cancelled) { setData(r); } return r; })
            .catch((e: unknown) => { if (!cancelled) { setError(e instanceof Error ? e.message : String(e)); } return null; });
        return () => { cancelled = true; };
    }, [contextJson, getPageMethod, pageType, onlyZone, reload]);

    const typeLabelFor = (type: string): string =>
        layout?.types.find((t) => t.key === type)?.label
        ?? labels["widget_type_" + type] ?? type;

    const openDrawer = () => {
        setDrawerOpen(true);
        setBusy(true);
        callService<LayoutResponse>(layoutGetMethod, {page: pageType})
            .then((r) => {
                setLayout(r);
                setRows(r.widgets);
                setAddType(r.types[0]?.key ?? "");
                setAddZone(zonesForNewWidget(r.zones, pageType)[0]?.slug ?? r.zones[0]?.slug ?? "");
                setAddPresetId(0);
                return r;
            })
            .catch((e: unknown) => setError(e instanceof Error ? e.message : String(e)))
            .finally(() => setBusy(false));
    };

    useEffect(() => {
        const handler = (event: Event) => {
            const detail = (event as CustomEvent<{pageType?: string}>).detail;
            if (!detail?.pageType || detail.pageType !== pageType || !canManage) {
                return;
            }
            openDrawer();
        };
        window.addEventListener("moderncommerce:open-widget-designer", handler);
        return () => window.removeEventListener("moderncommerce:open-widget-designer", handler);
    }, [canManage, pageType]);

    const refresh = () => setReload((n) => n + 1);

    useEffect(() => {
        const handler = (event: Event) => {
            const detail = (event as CustomEvent<{pageType?: string}>).detail;
            if (!detail?.pageType || detail.pageType === pageType) {
                refresh();
            }
        };
        window.addEventListener("moderncommerce:storefront-refresh", handler);
        return () => window.removeEventListener("moderncommerce:storefront-refresh", handler);
    }, [pageType]);

    const setRow = (i: number, changes: Partial<LayoutWidget>) =>
        setRows((cur) => cur.map((r, idx) => (idx === i ? {...r, ...changes} : r)));

    const moveRow = (i: number, delta: number) =>
        setRows((cur) => {
            const next = [...cur];
            const t = i + delta;
            if (t < 0 || t >= next.length) {
                return cur;
            }
            [next[i], next[t]] = [next[t], next[i]];
            return next;
        });

    const saveLayout = () => {
        setBusy(true);
        const items = rows.map((r, idx) => ({id: r.id, zone: r.zone, enabled: r.enabled, sortorder: idx}));
        const hasGlobalRows = rows.some(isGlobalLayoutRow);
        callService(layoutSaveMethod, {page: pageType, items})
            .then(() => {
                setDrawerOpen(false);
                refresh();
                if (hasGlobalRows) {
                    window.dispatchEvent(new CustomEvent("moderncommerce:storefront-refresh", {
                        detail: {pageType: GLOBAL_PAGE},
                    }));
                }
                return null;
            })
            .catch((e: unknown) => setError(e instanceof Error ? e.message : String(e)))
            .finally(() => setBusy(false));
    };

    const addWidget = () => {
        if (!addType || !addZone) {
            return;
        }
        setBusy(true);
        callService<{id: number}>(addMethod, {page: pageType, type: addType, zone: addZone, presetid: addPresetId})
            .then(() => callService<LayoutResponse>(layoutGetMethod, {page: pageType}))
            .then((r) => { setLayout(r); setRows(r.widgets); return null; })
            .catch((e: unknown) => setError(e instanceof Error ? e.message : String(e)))
            .finally(() => setBusy(false));
    };

    useEffect(() => {
        if (!drawerOpen || !presetListMethod || !addType) {
            setAddPresets([]);
            setAddPresetId(0);
            return;
        }
        let cancelled = false;
        callService<PresetResponse>(presetListMethod, {type: addType})
            .then((result) => {
                if (!cancelled) {
                    setAddPresets(result.presets ?? []);
                    setAddPresetId(0);
                }
                return result;
            })
            .catch((e: unknown) => {
                if (!cancelled) {
                    setError(e instanceof Error ? e.message : String(e));
                }
            });
        return () => {
            cancelled = true;
        };
    }, [addType, drawerOpen, presetListMethod]);

    const deleteWidget = (id: number) => {
        const removedGlobalRow = rows.find((row) => row.id === id);
        setBusy(true);
        callService(deleteMethod, {id})
            .then(() => callService<LayoutResponse>(layoutGetMethod, {page: pageType}))
            .then((r) => {
                setLayout(r);
                setRows(r.widgets);
                refresh();
                if (removedGlobalRow && isGlobalLayoutRow(removedGlobalRow)) {
                    window.dispatchEvent(new CustomEvent("moderncommerce:storefront-refresh", {
                        detail: {pageType: GLOBAL_PAGE},
                    }));
                }
                return null;
            })
            .catch((e: unknown) => setError(e instanceof Error ? e.message : String(e)))
            .finally(() => setBusy(false));
    };

    const openEditor = (id: number) => {
        setEditorId(id);
        setEditor(null);
        setEditorPresets([]);
        setEditorPresetsLoading(Boolean(presetListMethod));
        setSelectedPresetId(0);
        setActiveSliderItem("design");
        setActiveBreadcrumbItem("breadcrumb");
        setActiveVideoHeroItem("background");
        setActiveCountdownItem("background");
        setActiveCategoriesItem("layout");
        setActiveFeaturedItem("heading");
        setActiveGenericItem("content");
        setEditorBusy(true);
        callService<{
            fields: string; values: string; styleconfig: string; slides: string; type: string; pagetype?: string
        }>(widgetGetMethod, {id})
            .then((r) => {
                const values = parseJson<Record<string, unknown>>(r.values, {});
                const styleconfig = parseJson<StyleConfig>(r.styleconfig, {});
                setEditor({
                    id, type: r.type,
                    pagetype: r.pagetype,
                    fields: parseJson<FieldDef[]>(r.fields, []),
                    values,
                    styleconfig,
                    slides: parseJson<Slide[]>(r.slides, []),
                });
                if (presetListMethod) {
                    void callService<PresetResponse>(presetListMethod, {type: r.type})
                        .then((result) => {
                            const presets = result.presets ?? [];
                            setEditorPresets(presets);
                            setSelectedPresetId(currentPresetId(r.type, values, styleconfig, presets));
                            return result;
                        })
                        .catch((e: unknown) => setError(e instanceof Error ? e.message : String(e)))
                        .finally(() => setEditorPresetsLoading(false));
                } else {
                    setEditorPresetsLoading(false);
                }
                return r;
            })
            .catch((e: unknown) => {
                setEditorPresetsLoading(false);
                setError(e instanceof Error ? e.message : String(e));
            })
            .finally(() => setEditorBusy(false));
    };

    const closeEditor = () => {
        setEditorId(null);
        setEditor(null);
        setEditorPresets([]);
        setEditorPresetsLoading(false);
        setSelectedPresetId(0);
    };

    const setValue = (name: string, v: unknown) =>
        setEditor((cur) => (cur ? {...cur, values: {...cur.values, [name]: v}} : cur));

    const setValues = (changes: Record<string, unknown>) =>
        setEditor((cur) => (cur ? {...cur, values: {...cur.values, ...changes}} : cur));

    const setStyleValue = (name: string, v: string | number) =>
        setEditor((cur) => {
            if (!cur) {
                return cur;
            }
            const styleconfig = {...cur.styleconfig};
            if (v === "") {
                delete styleconfig[name];
            } else {
                styleconfig[name] = v;
            }
            return {...cur, styleconfig};
        });

    const setSlide = (i: number, k: string, v: unknown) =>
        setEditor((cur) => (cur
            ? {...cur, slides: cur.slides.map((s, idx) => (idx === i ? {...s, [k]: v} : s))} : cur));

    const setSlideValues = (i: number, values: Partial<Slide>) =>
        setEditor((cur) => (cur
            ? {...cur, slides: cur.slides.map((s, idx) => (idx === i ? {...s, ...values} : s))} : cur));

    const moveSlide = (i: number, delta: number) =>
        setEditor((cur) => {
            if (!cur) {
                return cur;
            }
            const target = i + delta;
            if (target < 0 || target >= cur.slides.length) {
                return cur;
            }
            const slides = [...cur.slides];
            [slides[i], slides[target]] = [slides[target], slides[i]];
            return {...cur, slides};
        });

    const blankSlide = (): Slide => ({
        image: "", imagesource: "url", imageurl: "", imagefile: "",
        heading: "", subheading: "", ctalabel: "", ctaurl: "",
        ctastyle: "primary", bgcolor: "", enabled: 1,
    });

    const saveEditor = () => {
        if (!editor) {
            return;
        }

        // Rules are compiled from the widget's own schema, so a new widget type
        // gains validation by declaring it in PHP, with no JavaScript change.
        const found = runRules(editor.values, rulesFromSchema(editor.fields, labels));
        setEditorFieldErrors(found);
        if (Object.keys(found).length > 0) {
            setError("");
            return;
        }

        setEditorBusy(true);
        callService(widgetSaveMethod, {
            id: editor.id,
            values: JSON.stringify(editor.values),
            slides: JSON.stringify(editor.slides),
            styleconfig: JSON.stringify(editor.styleconfig),
        })
            .then(() => {
                const savedPageType = editor.pagetype || pageType;
                closeEditor();
                refresh();
                if (savedPageType === GLOBAL_PAGE) {
                    window.dispatchEvent(new CustomEvent("moderncommerce:storefront-refresh", {
                        detail: {pageType: GLOBAL_PAGE},
                    }));
                }
                return null;
            })
            .catch((e: unknown) => setError(e instanceof Error ? e.message : String(e)))
            .finally(() => setEditorBusy(false));
    };

    const applyEditorPreset = (presetId: number) => {
        setSelectedPresetId(presetId);
        const preset = editorPresets.find((item) => item.id === presetId);
        if (!preset) {
            setEditor((cur) => {
                if (!cur) {
                    return cur;
                }
                const values = {...cur.values};
                delete values.presetid;
                return {...cur, values};
            });
            return;
        }
        const patch = parseJson<Record<string, unknown>>(preset.settingspatch, {});
        const style = parseJson<StyleConfig>(preset.styleconfig, {});
        const presetValues = presetPatchForType(editor?.type ?? "", style, patch);
        setEditor((cur) => cur ? {
            ...cur,
            values: {
                ...cur.values,
                ...presetValues,
                presetid: preset.id,
            },
            styleconfig: {...cur.styleconfig, ...style},
        } : cur);
    };

    const editorValue = (key: string, fallbackKey = "", fallback: unknown = ""): unknown => {
        if (!editor) {
            return fallback;
        }
        const explicit = editor.values[key];
        if (explicit !== null && typeof explicit !== "undefined" && String(explicit) !== "") {
            return explicit;
        }
        if (fallbackKey) {
            const fallbackValue = editor.values[fallbackKey];
            if (fallbackValue !== null && typeof fallbackValue !== "undefined" && String(fallbackValue) !== "") {
                return fallbackValue;
            }
        }
        return fallback;
    };

    const hasSavedEditorValue = (key: string): boolean => {
        if (!editor) {
            return false;
        }
        const value = editor.values[key];
        return value !== null && typeof value !== "undefined" && String(value) !== "";
    };

    const savedEditorValue = (key: string): unknown => {
        if (!editor) {
            return "";
        }
        const value = editor.values[key];
        return value !== null && typeof value !== "undefined" ? value : "";
    };

    const savedEditorString = (key: string): string => String(savedEditorValue(key) ?? "");

    const fallbackEditorString = (fallbackKey = "", fallback: unknown = ""): string => {
        if (fallbackKey && hasSavedEditorValue(fallbackKey)) {
            return String(editor?.values[fallbackKey] ?? "");
        }
        return String(fallback ?? "");
    };

    const savedStyleValue = (key: string): unknown => {
        if (!editor) {
            return "";
        }
        const value = editor.styleconfig[key];
        return value !== null && typeof value !== "undefined" ? value : "";
    };

    const savedStyleString = (key: string): string => String(savedStyleValue(key) ?? "");

    const numberInputValue = (value: unknown): string =>
        value !== null && typeof value !== "undefined" && String(value) !== "" ? String(value) : "";

    const currentBreadcrumbStyle = (): string =>
        String(editorValue("style", "", "imagehero") || "imagehero");

    const updateBreadcrumbOverlayColor = (value: string | number) => {
        const colour = String(value);
        const changes: Record<string, unknown> = {overlaycolor: colour};
        if (currentBreadcrumbStyle() === "gradient") {
            changes.gradientstart = colour;
            changes.gradientend = colour;
        }
        setValues(changes);
    };

    const currentBreadcrumbImageSource = (): "url" | "upload" => {
        const source = String(editorValue("backgroundsource"));
        if (source === "url" || source === "upload") {
            return source;
        }
        return String(editorValue("backgroundfile")) !== "" ? "upload" : "url";
    };

    const renderPresetSelector = () => {
        if (!editor || !presetListMethod) {
            return null;
        }
        return (
            <section className="mc-sf-bc-card mc-sf-preset-card">
                <div className="mc-sf-section__title">{labels.widgetpreset ?? "Widget preset"}</div>
                <div className="mc-sf-field">
                    <label htmlFor="mc-sf-editor-preset">{labels.applypreset ?? "Apply preset"}</label>
                    <select id="mc-sf-editor-preset" value={selectedPresetId}
                        disabled={editorPresetsLoading || editorBusy}
                        onChange={(event) => applyEditorPreset(Number(event.currentTarget.value))}>
                        <option value={0}>{labels.widgetpresetnone ?? "No preset"}</option>
                        {editorPresets.map((preset) => (
                            <option key={preset.id} value={preset.id}>{preset.name}</option>
                        ))}
                    </select>
                </div>
                {editorPresetsLoading && (
                    <div className="mc-sf-img-hint">
                        {labels.loading ?? "Loading..."}
                    </div>
                )}
                {!editorPresetsLoading && editorPresets.length === 0 && (
                    <div className="mc-sf-img-hint">
                        {labels.widgetpresetempty ?? "No presets are saved for this widget type."}
                    </div>
                )}
            </section>
        );
    };

    const renderEditorSchemaField = (name: string, fallbackLabel: string, fallbackType = "text") => {
        if (!editor) {
            return null;
        }
        const field = editor.fields.find((candidate) => candidate.name === name) ?? {
            name,
            label: fallbackLabel,
            type: fallbackType,
        };
        if (!shouldShowField(field, editor.values)) {
            return null;
        }
        if (field.type === "image") {
            return <SlideImageField key={name} label={field.label} uploadMethod={uploadMethod}
                value={String(editor.values[name] || "")}
                labels={labels}
                onChange={(value) => setValue(name, value)} />;
        }
        if (field.type === "videofile") {
            return <VideoUploadField key={name} label={field.label} uploadUrl={videoUploadUrl}
                value={editor.values[name]}
                labels={labels}
                onChange={(value) => setValue(name, value)} />;
        }
        return <Field key={name} field={field} value={editor.values[name]}
            error={editorFieldErrors[name]}
            iconOptions={iconOptions}
            uploadMethod={uploadMethod}
            videoUploadUrl={videoUploadUrl}
            contextValues={editor.values}
            controlLabels={labels}
            onChange={(value) => setValue(name, value)} />;
    };

    const renderEditorField = (field: FieldDef) => {
        if (!editor || !shouldShowField(field, editor.values)) {
            return null;
        }
        if (field.type === "image") {
            return <SlideImageField key={field.name} label={field.label} uploadMethod={uploadMethod}
                value={String(editor.values[field.name] || "")}
                labels={labels}
                onChange={(value) => setValue(field.name, value)} />;
        }
        if (field.type === "videofile") {
            return <VideoUploadField key={field.name} label={field.label} uploadUrl={videoUploadUrl}
                value={editor.values[field.name]}
                labels={labels}
                onChange={(value) => setValue(field.name, value)} />;
        }
        return <Field key={field.name} field={field} value={editor.values[field.name]}
            error={editorFieldErrors[field.name]}
            iconOptions={iconOptions}
            uploadMethod={uploadMethod}
            videoUploadUrl={videoUploadUrl}
            contextValues={editor.values}
            controlLabels={labels}
            onChange={(value) => setValue(field.name, value)} />;
    };

    const renderStyleField = (key: string) => {
        if (!editor) {
            return null;
        }
        const styleField = universalStyleFields.find((candidate) => candidate.key === key);
        if (!styleField) {
            return null;
        }
        return (
            <Field key={styleField.key}
                field={{
                    name: styleField.key,
                    label: labels[`style_${styleField.key}`] ?? styleField.label,
                    type: styleField.type,
                    default: styleField.defaultValue,
                }}
                value={savedStyleValue(styleField.key)}
                iconOptions={iconOptions}
                uploadMethod={uploadMethod}
                videoUploadUrl={videoUploadUrl}
                contextValues={editor.values}
                controlLabels={labels}
                onChange={(v) => setStyleValue(styleField.key, v as string | number)} />
        );
    };

    const renderStyleGrid = (keys?: string[]) => {
        if (!editor) {
            return null;
        }
        const fields = keys ?? universalStyleFields.map((styleField) => styleField.key);
        return (
            <div className="mc-sf-stylegrid">
                {fields.map((key) => renderStyleField(key))}
            </div>
        );
    };

    const renderUniversalStyleGrid = () => renderStyleGrid();

    const renderSlidesEditor = () => {
        if (!editor || editor.type !== "slider") {
            return null;
        }
        const slideImageSource = (slide: Slide): "url" | "upload" => {
            if (slide.imagesource === "upload" || slide.imagesource === "url") {
                return slide.imagesource;
            }
            const image = String(slide.image || "");
            return image.includes("/pluginfile.php/") || image.startsWith("pluginfile.php/") ? "upload" : "url";
        };
        return (
            <>
                {editor.slides.map((slide, i) => (
                    <div className="mc-sf-list__item mc-sf-slide-card" key={i}>
                        <div className="mc-sf-slide-card__head">
                            <strong>{labels.slide ?? "Slide"} {i + 1}</strong>
                            <div className="mc-sf-list__actions">
                                <button type="button" className="mc-button mc-sf-list__move" data-mc-button="ghost"
                                    data-mc-button-size="icon" disabled={i === 0}
                                    aria-label={labels.moveup} onClick={() => moveSlide(i, -1)}>
                                    <i className="bi bi-arrow-up" aria-hidden="true" />
                                </button>
                                <button type="button" className="mc-button mc-sf-list__move" data-mc-button="ghost"
                                    data-mc-button-size="icon" disabled={i === editor.slides.length - 1}
                                    aria-label={labels.movedown} onClick={() => moveSlide(i, 1)}>
                                    <i className="bi bi-arrow-down" aria-hidden="true" />
                                </button>
                                <button type="button" className="mc-button mc-sf-list__remove"
                                    data-mc-button="ghost"
                                    onClick={() => setEditor((cur) => (cur
                                        ? {...cur, slides: cur.slides.filter((_, idx) => idx !== i)} : cur))}>
                                    <i className="bi bi-trash" aria-hidden="true" /> Remove
                                </button>
                            </div>
                        </div>
                        <div className="mc-sf-field">
                            <label>
                                <input type="checkbox" checked={!!slide.enabled}
                                    onChange={(event) => setSlide(i, "enabled", event.currentTarget.checked ? 1 : 0)} />
                                {" "}{labels.enabled ?? "Enabled"}
                            </label>
                        </div>
                        <div className="mc-sf-stylegrid">
                            <Field field={{name: "heading", label: "Heading", type: "text"}}
                                value={slide.heading} onChange={(value) => setSlide(i, "heading", value)}
                                iconOptions={iconOptions} uploadMethod={uploadMethod} videoUploadUrl={videoUploadUrl}
                                contextValues={slide as unknown as Record<string, unknown>} />
                            <Field field={{name: "bgcolor", label: "Background colour", type: "color"}}
                                value={slide.bgcolor} onChange={(value) => setSlide(i, "bgcolor", value)}
                                iconOptions={iconOptions} uploadMethod={uploadMethod} videoUploadUrl={videoUploadUrl}
                                contextValues={slide as unknown as Record<string, unknown>} />
                        </div>
                        <Field field={{name: "subheading", label: "Subheading", type: "textarea"}}
                            value={slide.subheading} onChange={(value) => setSlide(i, "subheading", value)}
                            iconOptions={iconOptions} uploadMethod={uploadMethod} videoUploadUrl={videoUploadUrl}
                            contextValues={slide as unknown as Record<string, unknown>} />
                        <div className="mc-sf-field">
                            <label htmlFor={`mc-sf-slide-source-${i}`}>Image source</label>
                            <select id={`mc-sf-slide-source-${i}`} value={slideImageSource(slide)}
                                onChange={(event) => {
                                    const source = event.currentTarget.value as "url" | "upload";
                                    setSlideValues(i, {
                                        imagesource: source,
                                        image: source === "upload"
                                            ? String(slide.imagefile || slide.image || "")
                                            : String(slide.imageurl || slide.image || ""),
                                    });
                                }}>
                                <option value="url">Image URL</option>
                                <option value="upload">Uploaded image</option>
                            </select>
                        </div>
                        {slideImageSource(slide) === "url" ? (
                            <Field field={{name: "imageurl", label: "Image URL", type: "url"}}
                                value={slide.imageurl || slide.image}
                                onChange={(value) => setSlideValues(i, {
                                    imageurl: String(value),
                                    image: String(value),
                                    imagesource: "url",
                                })}
                                iconOptions={iconOptions} uploadMethod={uploadMethod} videoUploadUrl={videoUploadUrl}
                                contextValues={slide as unknown as Record<string, unknown>} />
                        ) : (
                            <SlideImageField label="Uploaded image" uploadMethod={uploadMethod}
                                value={String(slide.imagefile || slide.image || "")}
                                labels={labels}
                                onChange={(value) => setSlideValues(i, {
                                    imagefile: value,
                                    image: value,
                                    imagesource: "upload",
                                })} />
                        )}
                        <div className="mc-sf-stylegrid">
                            <Field field={{name: "ctalabel", label: "Button label", type: "text"}}
                                value={slide.ctalabel} onChange={(value) => setSlide(i, "ctalabel", value)}
                                iconOptions={iconOptions} uploadMethod={uploadMethod} videoUploadUrl={videoUploadUrl}
                                contextValues={slide as unknown as Record<string, unknown>} />
                            <Field field={{name: "ctaurl", label: "Button URL", type: "url"}}
                                value={slide.ctaurl} onChange={(value) => setSlide(i, "ctaurl", value)}
                                iconOptions={iconOptions} uploadMethod={uploadMethod} videoUploadUrl={videoUploadUrl}
                                contextValues={slide as unknown as Record<string, unknown>} />
                            <Field field={{name: "ctastyle", label: "Button style", type: "select",
                                choices: {primary: "Primary", light: "Light", outline: "Outline"}}}
                                value={slide.ctastyle} onChange={(value) => setSlide(i, "ctastyle", value)}
                                iconOptions={iconOptions} uploadMethod={uploadMethod} videoUploadUrl={videoUploadUrl}
                                contextValues={slide as unknown as Record<string, unknown>} />
                        </div>
                    </div>
                ))}
                <button type="button" className="mc-button mc-sf-btn-add" data-mc-button="soft"
                    onClick={() => setEditor((cur) => (cur
                        ? {...cur, slides: [...cur.slides, blankSlide()]} : cur))}>
                    <i className="bi bi-plus-lg" aria-hidden="true" /> {labels.addslide ?? "Add slide"}
                </button>
            </>
        );
    };

    const renderSliderItemControls = () => {
        switch (activeSliderItem) {
            case "design":
                return (
                    <>
                        {renderEditorSchemaField("title", "Title")}
                        {renderEditorSchemaField("design", "Design", "select")}
                    </>
                );
            case "motion":
                return (
                    <>
                        {renderEditorSchemaField("autoplay", "Autoplay", "checkbox")}
                        {renderEditorSchemaField("interval", "Interval (ms)", "number")}
                    </>
                );
            case "controls":
                return (
                    <>
                        {renderEditorSchemaField("showarrows", "Show arrows", "checkbox")}
                        {renderEditorSchemaField("showdots", "Show dots", "checkbox")}
                    </>
                );
            case "button":
                return (
                    <>
                        {renderEditorSchemaField("buttoncolor", "Button background", "color")}
                        {renderEditorSchemaField("buttontextcolor", "Button text colour", "color")}
                        {renderEditorSchemaField("buttonfontsize", "Button font size (px)", "number")}
                        {renderEditorSchemaField("buttonradius", "Button radius (px)", "number")}
                    </>
                );
            case "slides":
                return renderSlidesEditor();
            case "spacing":
                return (
                    <>
                        <div className="mc-sf-section__title">Slider spacing</div>
                        {renderStyleGrid(["spacingtop", "spacingbottom"])}
                    </>
                );
            case "appearance":
            default:
                return (
                    <>
                        <div className="mc-sf-section__title">{labels.universalstyles ?? "Universal styles"}</div>
                        {renderStyleGrid(["bg", "headingcolor", "textcolor", "accentcolor",
                            "headingfontsize", "bodyfontsize", "radius"])}
                    </>
                );
        }
    };

    const renderSliderEditor = () => {
        if (!editor) {
            return null;
        }
        return (
            <div className="mc-sf-generic-editor mc-sf-slider-editor">
                {renderPresetSelector()}
                <div className="mc-sf-bc-card">
                    <div className="mc-sf-bc-card__head">
                        <span>{labels.settings_editsection ?? "Edit section"}</span>
                        <strong>{sliderEditorItems.find((item) => item.key === activeSliderItem)?.label}</strong>
                    </div>
                    <div className="mc-sf-bc-picker mc-sf-generic-tabs" role="tablist"
                        aria-label={labels.settings_editsection ?? "Edit section"}>
                        {sliderEditorItems.map((item) => (
                            <button key={item.key}
                                type="button"
                                role="tab"
                                aria-selected={activeSliderItem === item.key}
                                className={activeSliderItem === item.key ? "is-active" : ""}
                                onClick={() => setActiveSliderItem(item.key)}>
                                <i className={`bi ${item.icon}`} aria-hidden="true" />
                                <span>{item.label}</span>
                            </button>
                        ))}
                    </div>
                    <div className="mc-sf-bc-controls">
                        {renderSliderItemControls()}
                    </div>
                </div>
            </div>
        );
    };

    const renderFeaturedProductsEditor = () => {
        if (!editor || (editor.type !== "featured" && editor.type !== "related")) {
            return null;
        }
        const activeItem = featuredEditorItems.find((item) => item.key === activeFeaturedItem)
            ?? featuredEditorItems[0];
        const title = typeLabelFor(editor.type);
        const renderActiveControls = () => {
            switch (activeItem.key) {
                case "heading":
                    return (
                        <>
                            {renderEditorSchemaField("title", "Title")}
                            {renderEditorSchemaField("subtitle", "Subtitle")}
                        </>
                    );
                case "products":
                    return (
                        <>
                            {renderEditorSchemaField("coursetype", "Product type", "select")}
                            {renderEditorSchemaField("categoryid", "Category", "select")}
                            {renderEditorSchemaField("sort", "Sort by", "select")}
                            {renderEditorSchemaField("perpage", "Count", "number")}
                        </>
                    );
                case "layout":
                    return (
                        <>
                            {renderEditorSchemaField("align", "Heading alignment", "select")}
                            {renderEditorSchemaField("layout", "Layout", "select")}
                            {renderEditorSchemaField("columns", "Columns", "select")}
                            {renderEditorSchemaField("navposition", "Arrow controls position", "select")}
                        </>
                    );
                case "cards":
                    return (
                        <>
                            {renderEditorSchemaField("cardbgcolor", "Card background", "color")}
                            {renderEditorSchemaField("cardbordercolor", "Card border colour", "color")}
                            {renderEditorSchemaField("cardborderwidth", "Card border weight (px)", "number")}
                        </>
                    );
                case "button":
                    return (
                        <>
                            {renderEditorSchemaField("buttoncolor", "Button background", "color")}
                            {renderEditorSchemaField("buttontextcolor", "Button text colour", "color")}
                        </>
                    );
                case "appearance":
                    return (
                        <>
                            <div className="mc-sf-section__title">{title} appearance</div>
                            {renderStyleGrid(["bg", "headingcolor", "textcolor", "accentcolor",
                                "headingfontsize", "bodyfontsize", "radius"])}
                        </>
                    );
                case "spacing":
                    return (
                        <>
                            <div className="mc-sf-section__title">{title} spacing</div>
                            {renderStyleGrid(["spacingtop", "spacingbottom"])}
                        </>
                    );
                default:
                    return null;
            }
        };

        return (
            <div className={`mc-sf-generic-editor mc-sf-featured-editor mc-sf-product-list-editor mc-sf-product-list-editor--${editor.type}`}>
                {renderPresetSelector()}
                <div className="mc-sf-bc-card">
                    <div className="mc-sf-bc-card__head">
                        <span>{title}</span>
                        <strong>{activeItem.label}</strong>
                    </div>
                    <div className="mc-sf-bc-picker mc-sf-generic-tabs"
                        role="tablist"
                        aria-label={`${title} settings`}>
                        {featuredEditorItems.map((item) => (
                            <button key={item.key}
                                type="button"
                                role="tab"
                                aria-selected={activeItem.key === item.key}
                                className={activeItem.key === item.key ? "is-active" : ""}
                                onClick={() => setActiveFeaturedItem(item.key)}>
                                <i className={`bi ${item.icon}`} aria-hidden="true" />
                                <span>{item.label}</span>
                            </button>
                        ))}
                    </div>
                    <div className="mc-sf-bc-controls">
                        {renderActiveControls()}
                    </div>
                </div>
            </div>
        );
    };

    const renderGenericEditor = () => {
        if (!editor) {
            return null;
        }
        const visibleFields = editor.fields.filter((field) => shouldShowField(field, editor.values));
        const fieldsByName = new Map(visibleFields.map((field) => [field.name, field]));
        const profile = remainingEditorProfiles[editor.type] ?? null;
        const fallbackGrouped = visibleFields.reduce<Record<GenericEditorItem, FieldDef[]>>((acc, field) => {
            const group = genericFieldGroup(field);
            acc[group].push(field);
            return acc;
        }, {content: [], media: [], layout: [], appearance: [], spacing: [], slides: []});
        const fallbackSections: FocusedEditorSection[] = genericEditorItems.map((item) => ({
            key: item.key,
            label: item.label,
            icon: item.icon,
            includeUniversal: item.key === "appearance",
            slideEditor: item.key === "slides" && editor.type === "slider",
            fields: fallbackGrouped[item.key].map((field) => field.name),
        }));
        const sections = (profile ?? fallbackSections).filter((section) => {
            if (section.includeUniversal || section.slideEditor) {
                return true;
            }
            return (section.fields ?? []).some((name) => fieldsByName.has(name));
        });
        const activeSection = sections.find((section) => section.key === activeGenericItem) ?? sections[0];
        const fields = (activeSection?.fields ?? [])
            .map((name) => fieldsByName.get(name))
            .filter((field): field is FieldDef => Boolean(field));
        const activeKey = activeSection?.key ?? "appearance";
        return (
            <div className="mc-sf-generic-editor">
                {renderPresetSelector()}
                <div className="mc-sf-bc-card">
                    <div className="mc-sf-bc-card__head">
                        <span>{labels.settings_editsection ?? "Edit section"}</span>
                        <strong>{genericGroupLabel(activeKey, labels, activeSection)}</strong>
                    </div>
                    <div className="mc-sf-bc-picker mc-sf-generic-tabs"
                        role="tablist"
                        aria-label={labels.settings_editsection ?? "Edit section"}>
                        {sections.map((section) => (
                            <button key={section.key}
                                type="button"
                                role="tab"
                                aria-selected={activeKey === section.key}
                                className={activeKey === section.key ? "is-active" : ""}
                                onClick={() => setActiveGenericItem(section.key)}>
                                <i className={`bi ${section.icon}`} aria-hidden="true" />
                                <span>{genericGroupLabel(section.key, labels, section)}</span>
                            </button>
                        ))}
                    </div>
                    <div className="mc-sf-bc-controls">
                        {activeSection?.includeUniversal && (
                            <>
                                <div className="mc-sf-section__title">
                                    {labels.universalstyles ?? "Universal styles"}
                                </div>
                                {renderUniversalStyleGrid()}
                            </>
                        )}
                        {activeSection?.slideEditor ? renderSlidesEditor() : fields.map((field) => renderEditorField(field))}
                        {!activeSection?.includeUniversal && !activeSection?.slideEditor && fields.length === 0 && (
                            <div className="mc-sf-img-hint">
                                {labels.settings_emptysection ?? "No editable controls in this section."}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        );
    };

    const renderBreadcrumbColourControl = (key: string, label: string, fallbackKey = "") => {
        const value = savedEditorString(key);
        const placeholder = fallbackEditorString(fallbackKey);
        const swatch = value || placeholder;
        return (
            <label className="mc-sf-bc-field" key={key}>
                <span>{label}</span>
                <div className="mc-sf-color">
                    <input type="color" className="mc-sf-color__swatch" value={breadcrumbHexColour(swatch)}
                        aria-label={`${label} colour picker`}
                        onChange={(event) => {
                            const next = event.currentTarget.value;
                            if (key === "overlaycolor") {
                                updateBreadcrumbOverlayColor(next);
                            } else {
                                setValue(key, next);
                            }
                        }} />
                    <input type="text" className="mc-sf-color__text" value={value}
                        placeholder={placeholder || "#1565c0 or var(--mc-primary)"}
                        onChange={(event) => {
                            const next = event.currentTarget.value;
                            if (key === "overlaycolor") {
                                updateBreadcrumbOverlayColor(next);
                            } else {
                                setValue(key, next);
                            }
                        }} />
                </div>
            </label>
        );
    };

    const renderBreadcrumbNumberControl = (key: string, label: string, max = 240, placeholder = 0) => (
        <label className="mc-sf-bc-field" key={key}>
            <span>{label}</span>
            <input type="number" min={0} max={max} value={numberInputValue(savedEditorValue(key))}
                placeholder={placeholder > 0 ? String(placeholder) : undefined}
                onChange={(event) => setValue(key, event.currentTarget.value === ""
                    ? ""
                    : Number(event.currentTarget.value))} />
        </label>
    );

    const renderBreadcrumbTransparencyControl = () => {
        const defaultOpacity = currentBreadcrumbStyle() === "gradient" ? 88 : 52;
        const rawOpacity = editorValue("overlayopacity");
        const opacity = rawOpacity === "" || rawOpacity === null || typeof rawOpacity === "undefined"
            ? defaultOpacity
            : Math.max(0, Math.min(100, Number(rawOpacity)));
        const transparency = 100 - opacity;
        return (
            <label className="mc-sf-bc-field mc-sf-bc-range">
                <span>{labels.breadcrumb_transparency ?? "Transparency"}</span>
                <div className="mc-sf-bc-range__control">
                    <input type="range" min={0} max={100} value={transparency}
                        onChange={(event) => setValue("overlayopacity", 100 - Number(event.currentTarget.value))} />
                    <output>{transparency}%</output>
                </div>
            </label>
        );
    };

    const renderBreadcrumbBackgroundControls = () => {
        const supportsMedia = ["imagehero", "gradient"].includes(currentBreadcrumbStyle());
        const source = currentBreadcrumbImageSource();
        return (
            <>
                <label className="mc-sf-bc-field">
                    <span>{labels.breadcrumb_imagesource ?? "Image source"}</span>
                    <select value={source} onChange={(event) => setValue("backgroundsource", event.currentTarget.value)}>
                        <option value="url">{labels.breadcrumb_sourceurl ?? "Image URL"}</option>
                        <option value="upload">{labels.breadcrumb_sourceupload ?? "Uploaded image"}</option>
                    </select>
                </label>
                {source === "url" && (
                    <label className="mc-sf-bc-field">
                        <span>{labels.breadcrumb_backgroundimage ?? "Image URL"}</span>
                        <input type="text" value={String(editorValue("backgroundimage"))}
                            placeholder="https://example.com/banner.jpg"
                            onChange={(event) => setValue("backgroundimage", event.currentTarget.value)} />
                    </label>
                )}
                {source === "upload" && (
                    <SlideImageField label={labels.breadcrumb_backgroundfile ?? "Upload image"}
                        uploadMethod={uploadMethod}
                        value={String(editorValue("backgroundfile"))}
                        labels={labels}
                        onChange={(value) => setValue("backgroundfile", value)} />
                )}
                {!supportsMedia && (
                    <div className="mc-sf-img-hint">
                        {labels.breadcrumb_backgroundhint
                            ?? "Background images are shown by Image Hero and Gradient Media banner types."}
                    </div>
                )}
                {renderEditorSchemaField("bgcolor", "Background colour", "color")}
                {renderEditorSchemaField("textcolor", "Base text colour", "color")}
                {currentBreadcrumbStyle() === "gradient" && (
                    <>
                        {renderEditorSchemaField("gradientstart", "Gradient start", "color")}
                        {renderEditorSchemaField("gradientend", "Gradient end", "color")}
                    </>
                )}
                {renderEditorSchemaField("accentcolor", "Accent colour", "color")}
            </>
        );
    };

    const renderBreadcrumbItemControls = () => {
        switch (activeBreadcrumbItem) {
            case "background":
                return renderBreadcrumbBackgroundControls();
            case "title":
                return (
                    <>
                        {renderEditorSchemaField("title", "Title")}
                        {renderBreadcrumbColourControl("titlecolor", labels.breadcrumb_fontcolor ?? "Font colour", "textcolor")}
                        {renderBreadcrumbNumberControl(
                            "titlefontsize",
                            labels.breadcrumb_fontsize ?? "Font size",
                            96,
                            breadcrumbFontDefault(currentBreadcrumbStyle(), "titlefontsize")
                        )}
                    </>
                );
            case "subtitle":
                return (
                    <>
                        {renderEditorSchemaField("subtitle", "Subtitle", "textarea")}
                        {renderBreadcrumbColourControl("subtitlecolor", labels.breadcrumb_fontcolor ?? "Font colour", "textcolor")}
                        {renderBreadcrumbNumberControl(
                            "subtitlefontsize",
                            labels.breadcrumb_fontsize ?? "Font size",
                            96,
                            breadcrumbFontDefault(currentBreadcrumbStyle(), "subtitlefontsize")
                        )}
                    </>
                );
            case "overlay":
                return (
                    <>
                        {renderBreadcrumbColourControl(
                            "overlaycolor",
                            labels.breadcrumb_overlaycolor ?? "Overlay colour",
                            "gradientstart"
                        )}
                        {renderBreadcrumbTransparencyControl()}
                    </>
                );
            case "padding":
                return (
                    <>
                        {renderBreadcrumbNumberControl(
                            "paddingtop",
                            labels.breadcrumb_paddingtop ?? "Padding top",
                            240,
                            82
                        )}
                        {renderBreadcrumbNumberControl(
                            "paddingbottom",
                            labels.breadcrumb_paddingbottom ?? "Padding bottom",
                            240,
                            82
                        )}
                    </>
                );
            case "position":
                return (
                    <label className="mc-sf-bc-field">
                        <span>{labels.breadcrumb_textposition ?? "Text position"}</span>
                        <select value={String(editorValue("alignment", "", "center"))}
                            onChange={(event) => setValue("alignment", event.currentTarget.value)}>
                            <option value="left">{labels.alignleft ?? "Left"}</option>
                            <option value="center">{labels.aligncenter ?? "Center"}</option>
                            <option value="right">{labels.alignright ?? "Right"}</option>
                        </select>
                    </label>
                );
            case "breadcrumb":
            default:
                return (
                    <>
                        {renderEditorSchemaField("homelabel", "Home label")}
                        {renderEditorSchemaField("homeurl", "Home URL", "url")}
                        {renderEditorSchemaField("sectionlabel", "Section label")}
                        {renderEditorSchemaField("sectionurl", "Section URL", "url")}
                        {renderEditorSchemaField("excludedpages", "Excluded pages", "textarea")}
                        {renderBreadcrumbColourControl(
                            "breadcrumbcolor",
                            labels.breadcrumb_fontcolor ?? "Font colour",
                            "textcolor"
                        )}
                        {renderBreadcrumbNumberControl(
                            "breadcrumbfontsize",
                            labels.breadcrumb_fontsize ?? "Font size",
                            96,
                            breadcrumbFontDefault(currentBreadcrumbStyle(), "breadcrumbfontsize")
                        )}
                    </>
                );
        }
    };

    const renderBreadcrumbEditor = () => {
        if (!editor) {
            return null;
        }
        return (
            <div className="mc-sf-breadcrumb-editor">
                {renderPresetSelector()}

                <section className="mc-sf-bc-card">
                    <div className="mc-sf-bc-card__head">
                        <span>{labels.widgetgallery_variant ?? "Banner type"}</span>
                        <strong>{breadcrumbStyleChoices.find((item) => item.value === currentBreadcrumbStyle())?.label
                            ?? "Image Hero Banner"}</strong>
                    </div>
                    <label className="mc-sf-bc-field">
                        <span>{labels.selectedvariant ?? "Selected variant"}</span>
                        <select value={currentBreadcrumbStyle()}
                            onChange={(event) => setValue("style", event.currentTarget.value)}>
                            {breadcrumbStyleChoices.map((choice) => (
                                <option key={choice.value} value={choice.value}>{choice.label}</option>
                            ))}
                        </select>
                    </label>
                </section>

                <section className="mc-sf-bc-card">
                    <div className="mc-sf-bc-card__head">
                        <span>{labels.breadcrumb_edititem ?? "Edit item"}</span>
                        <strong>{breadcrumbEditorItems.find((item) => item.key === activeBreadcrumbItem)?.label}</strong>
                    </div>
                    <div className="mc-sf-bc-picker" role="tablist"
                        aria-label={labels.breadcrumb_edititem ?? "Edit item"}>
                        {breadcrumbEditorItems.map((item) => (
                            <button key={item.key} type="button"
                                role="tab"
                                aria-selected={activeBreadcrumbItem === item.key}
                                className={activeBreadcrumbItem === item.key ? "is-active" : ""}
                                onClick={() => setActiveBreadcrumbItem(item.key)}>
                                <i className={`bi ${item.icon}`} aria-hidden="true" />
                                <span>{labels[`breadcrumb_item_${item.key}`] ?? item.label}</span>
                            </button>
                        ))}
                    </div>
                    <div className="mc-sf-bc-controls">
                        {renderBreadcrumbItemControls()}
                    </div>
                </section>
            </div>
        );
    };

    const renderVideoHeroValueColourControl = (key: string, label: string, fallbackKey = "") => {
        const value = savedEditorString(key);
        const placeholder = fallbackEditorString(fallbackKey);
        const swatch = value || placeholder;
        return (
            <label className="mc-sf-bc-field" key={key}>
                <span>{label}</span>
                <div className="mc-sf-color">
                    <input type="color" className="mc-sf-color__swatch" value={breadcrumbHexColour(swatch)}
                        aria-label={`${label} colour picker`}
                        onChange={(event) => setValue(key, event.currentTarget.value)} />
                    <input type="text" className="mc-sf-color__text" value={value}
                        placeholder={placeholder || "#1565c0 or var(--mc-primary)"}
                        onChange={(event) => setValue(key, event.currentTarget.value)} />
                </div>
            </label>
        );
    };

    const renderVideoHeroStyleColourControl = (key: string, label: string) => {
        const value = savedStyleString(key);
        return (
            <label className="mc-sf-bc-field" key={key}>
                <span>{label}</span>
                <div className="mc-sf-color">
                    <input type="color" className="mc-sf-color__swatch" value={breadcrumbHexColour(value)}
                        aria-label={`${label} colour picker`}
                        onChange={(event) => setStyleValue(key, event.currentTarget.value)} />
                    <input type="text" className="mc-sf-color__text" value={value}
                        placeholder="#1565c0 or var(--mc-primary)"
                        onChange={(event) => setStyleValue(key, event.currentTarget.value)} />
                </div>
            </label>
        );
    };

    const renderVideoHeroStyleNumberControl = (key: string, label: string, max = 240, fallback = 0) => (
        <label className="mc-sf-bc-field" key={key}>
            <span>{label}</span>
            <input type="number" min={0} max={max} value={numberInputValue(savedStyleValue(key))}
                placeholder={fallback > 0 ? String(fallback) : undefined}
                onChange={(event) => setStyleValue(key, event.currentTarget.value === ""
                    ? ""
                    : Number(event.currentTarget.value))} />
        </label>
    );

    const renderVideoHeroValueNumberControl = (key: string, label: string, max = 240, fallback = 0) => (
        <label className="mc-sf-bc-field" key={key}>
            <span>{label}</span>
            <input type="number" min={0} max={max} value={numberInputValue(savedEditorValue(key))}
                placeholder={fallback > 0 ? String(fallback) : undefined}
                onChange={(event) => setValue(key, event.currentTarget.value === ""
                    ? ""
                    : Number(event.currentTarget.value))} />
        </label>
    );

    const currentVideoHeroSource = (): string => {
        const source = String(editorValue("video_source", "", "none") || "none");
        return ["none", "upload", "url"].includes(source) ? source : "none";
    };

    const renderVideoHeroTextControl = (key: string, label: string, placeholder = "") => (
        <label className="mc-sf-bc-field" key={key}>
            <span>{label}</span>
            <input type="text" value={String(editorValue(key))}
                placeholder={placeholder}
                onChange={(event) => setValue(key, event.currentTarget.value)} />
        </label>
    );

    const renderVideoHeroPanelControls = () => {
        const source = currentVideoHeroSource();
        return (
            <>
                <label className="mc-sf-bc-field">
                    <span>{labels.videohero_videosource ?? "Video source"}</span>
                    <select value={source} onChange={(event) => setValue("video_source", event.currentTarget.value)}>
                        <option value="none">{labels.videohero_sourcenone ?? "No video"}</option>
                        <option value="upload">{labels.videohero_sourceupload ?? "Uploaded video"}</option>
                        <option value="url">{labels.videohero_sourceurl ?? "Video URL"}</option>
                    </select>
                </label>
                {source === "upload" && (
                    <VideoUploadField label={labels.videohero_videofile ?? "Upload video"}
                        uploadUrl={videoUploadUrl}
                        value={editor?.values.video_file}
                        labels={labels}
                        onChange={(value) => setValues({
                            video_source: value.url ? "upload" : "none",
                            video_file: value,
                        })} />
                )}
                {source === "url" && renderVideoHeroTextControl(
                    "video_url",
                    labels.videohero_videourl ?? "Video URL",
                    "https://youtu.be/... or https://example.com/video.mp4"
                )}
                {source !== "none" && (
                    <>
                        <SlideImageField label={labels.videohero_videoposter ?? "Poster image"}
                            uploadMethod={uploadMethod}
                            value={String(editorValue("video_poster"))}
                            onChange={(value) => setValue("video_poster", value)} />
                        {renderVideoHeroTextControl(
                            "video_title",
                            labels.videohero_videotitle ?? "Video caption",
                            labels.videohero_videotitleplaceholder ?? "Featured course offers"
                        )}
                    </>
                )}
                {renderVideoHeroStyleNumberControl("radius", labels.videohero_panelradius ?? "Panel radius", 96)}
            </>
        );
    };

    const renderVideoHeroItemControls = () => {
        switch (activeVideoHeroItem) {
            case "heading":
                return (
                    <>
                        {renderEditorSchemaField("heading", "Heading (use | for a line break)")}
                        {renderVideoHeroStyleColourControl("headingcolor", labels.videohero_headingcolor ?? "Heading colour")}
                        {renderVideoHeroStyleNumberControl("headingfontsize", labels.videohero_headingsize ?? "Heading font size", 96, 44)}
                    </>
                );
            case "body":
                return (
                    <>
                        {renderEditorSchemaField("subtext", "Sub text", "textarea")}
                        {renderVideoHeroStyleColourControl("textcolor", labels.videohero_textcolor ?? "Body text colour")}
                        {renderVideoHeroStyleNumberControl("bodyfontsize", labels.videohero_bodysize ?? "Body font size", 96, 18)}
                    </>
                );
            case "buttons":
                return (
                    <>
                        {renderEditorSchemaField("btn_primary_label", "Primary button label")}
                        {renderEditorSchemaField("btn_primary_url", "Primary button URL", "url")}
                        {renderEditorSchemaField("btn_secondary_label", "Secondary button label")}
                        {renderEditorSchemaField("btn_secondary_url", "Secondary button URL", "url")}
                        {renderVideoHeroValueColourControl("primarybuttoncolor",
                            labels.videohero_primarybuttoncolor ?? "Primary button background", "accentcolor")}
                        {renderVideoHeroValueColourControl("primarybuttontextcolor",
                            labels.videohero_primarybuttontextcolor ?? "Primary button text")}
                        {renderVideoHeroValueColourControl("secondarybuttoncolor",
                            labels.videohero_secondarybuttoncolor ?? "Secondary button background")}
                        {renderVideoHeroValueColourControl("secondarybuttontextcolor",
                            labels.videohero_secondarybuttontextcolor ?? "Secondary button text")}
                    </>
                );
            case "panel":
                return renderVideoHeroPanelControls();
            case "infocard":
                return (
                    <>
                        {renderEditorSchemaField("infoitems", "Info card boxes", "list")}
                        {renderEditorSchemaField("showquote",
                            labels.videohero_showquote ?? "Show testimonial quote", "checkbox")}
                        {renderEditorSchemaField("quote_text", "Testimonial quote", "textarea")}
                        {renderEditorSchemaField("quote_author", "Testimonial author")}
                        {renderVideoHeroValueColourControl("infocardbgcolor",
                            labels.videohero_infocardbgcolor ?? "Info card background")}
                        {renderVideoHeroValueColourControl("infoiconbgcolor",
                            labels.videohero_infoiconbgcolor ?? "Icon background")}
                        {renderVideoHeroValueColourControl("infoiconcolor",
                            labels.videohero_infoiconcolor ?? "Icon colour")}
                        {renderVideoHeroValueColourControl("infoheadingcolor",
                            labels.videohero_infoheadingcolor ?? "Info heading colour")}
                        {renderVideoHeroValueNumberControl("infoheadingfontsize",
                            labels.videohero_infoheadingsize ?? "Info heading font size", 96)}
                        {renderVideoHeroValueColourControl("infotextcolor",
                            labels.videohero_infotextcolor ?? "Info sub text colour")}
                    </>
                );
            case "spacing":
                return (
                    <>
                        {renderVideoHeroStyleNumberControl("spacingtop", labels.videohero_spacingtop ?? "Padding top")}
                        {renderVideoHeroStyleNumberControl("spacingbottom", labels.videohero_spacingbottom ?? "Padding bottom")}
                    </>
                );
            case "background":
            default:
                return (
                    <>
                        {renderVideoHeroStyleColourControl("bg", labels.videohero_sectionbgcolor ?? "Section background")}
                        {renderVideoHeroValueColourControl("bgcolor", labels.videohero_bgcolor ?? "Content background")}
                        {renderVideoHeroValueColourControl("accentcolor", labels.videohero_accentcolor ?? "Accent colour", "accent")}
                    </>
                );
        }
    };

    const renderVideoHeroEditor = () => {
        if (!editor) {
            return null;
        }
        return (
            <div className="mc-sf-videohero-editor">
                {renderPresetSelector()}

                <section className="mc-sf-bc-card">
                    <div className="mc-sf-bc-card__head">
                        <span>{labels.videohero_edititem ?? "Edit item"}</span>
                        <strong>{videoHeroEditorItems.find((item) => item.key === activeVideoHeroItem)?.label}</strong>
                    </div>
                    <div className="mc-sf-bc-picker" role="tablist"
                        aria-label={labels.videohero_edititem ?? "Edit item"}>
                        {videoHeroEditorItems.map((item) => (
                            <button key={item.key} type="button"
                                role="tab"
                                aria-selected={activeVideoHeroItem === item.key}
                                className={activeVideoHeroItem === item.key ? "is-active" : ""}
                                onClick={() => setActiveVideoHeroItem(item.key)}>
                                <i className={`bi ${item.icon}`} aria-hidden="true" />
                                <span>{labels[`videohero_item_${item.key}`] ?? item.label}</span>
                            </button>
                        ))}
                    </div>
                    <div className="mc-sf-bc-controls">
                        {renderVideoHeroItemControls()}
                    </div>
                </section>
            </div>
        );
    };

    const renderCountdownColourControl = (key: string, label: string, fallbackKey = "") => {
        const value = savedEditorString(key);
        const placeholder = fallbackEditorString(fallbackKey);
        const swatch = value || placeholder;
        return (
            <label className="mc-sf-bc-field" key={key}>
                <span>{label}</span>
                <div className="mc-sf-color">
                    <input type="color" className="mc-sf-color__swatch" value={breadcrumbHexColour(swatch)}
                        aria-label={`${label} colour picker`}
                        onChange={(event) => setValue(key, event.currentTarget.value)} />
                    <input type="text" className="mc-sf-color__text" value={value}
                        placeholder={placeholder || "#1565c0 or var(--mc-primary)"}
                        onChange={(event) => setValue(key, event.currentTarget.value)} />
                </div>
            </label>
        );
    };

    const renderCountdownNumberControl = (key: string, label: string, max = 240, fallback = 0) => (
        <label className="mc-sf-bc-field" key={key}>
            <span>{label}</span>
            <input type="number" min={0} max={max} value={numberInputValue(savedEditorValue(key))}
                placeholder={fallback > 0 ? String(fallback) : undefined}
                onChange={(event) => setValue(key, event.currentTarget.value === ""
                    ? ""
                    : Number(event.currentTarget.value))} />
        </label>
    );

    const renderCountdownItemControls = () => {
        switch (activeCountdownItem) {
            case "heading":
                return (
                    <>
                        {renderEditorSchemaField("heading", "Heading")}
                        {renderCountdownColourControl("headingcolor", labels.countdown_headingcolor ?? "Heading colour", "textcolor")}
                        {renderCountdownNumberControl(
                            "headingfontsize",
                            labels.countdown_headingfontsize ?? "Heading font size",
                            96,
                            17
                        )}
                    </>
                );
            case "timer":
                return (
                    <>
                        {renderCountdownColourControl("timerbgcolor",
                            labels.countdown_timerbgcolor ?? "Timer box background")}
                        {renderCountdownColourControl("timernumbercolor",
                            labels.countdown_timernumbercolor ?? "Timer number colour", "textcolor")}
                        {renderCountdownNumberControl(
                            "timernumberfontsize",
                            labels.countdown_timernumberfontsize ?? "Timer number font size",
                            96,
                            22
                        )}
                        {renderCountdownColourControl("timerlabelcolor",
                            labels.countdown_timerlabelcolor ?? "Timer label colour", "textcolor")}
                        {renderCountdownNumberControl(
                            "timerlabelfontsize",
                            labels.countdown_timerlabelfontsize ?? "Timer label font size",
                            96,
                            10
                        )}
                    </>
                );
            case "button":
                return (
                    <>
                        {renderEditorSchemaField("ctalabel", "CTA label")}
                        {renderEditorSchemaField("ctaurl", "CTA URL", "url")}
                        {renderCountdownColourControl("buttoncolor",
                            labels.countdown_buttoncolor ?? "Button background", "accentcolor")}
                        {renderCountdownColourControl("buttontextcolor",
                            labels.countdown_buttontextcolor ?? "Button text colour")}
                    </>
                );
            case "expired":
                return (
                    <>
                        {renderEditorSchemaField("expiredmessage", "Expired message")}
                        {renderCountdownColourControl("expiredbgcolor",
                            labels.countdown_expiredbgcolor ?? "Expired background")}
                        {renderCountdownColourControl("expiredtextcolor",
                            labels.countdown_expiredtextcolor ?? "Expired text colour", "textcolor")}
                    </>
                );
            case "spacing":
                return (
                    <>
                        {renderCountdownNumberControl("paddingtop", labels.countdown_paddingtop ?? "Padding top")}
                        {renderCountdownNumberControl("paddingbottom", labels.countdown_paddingbottom ?? "Padding bottom")}
                    </>
                );
            case "background":
            default:
                return (
                    <>
                        {renderCountdownColourControl("bgcolor", labels.countdown_bgcolor ?? "Bar background")}
                        {renderCountdownColourControl("textcolor", labels.countdown_textcolor ?? "Text colour")}
                        {renderEditorSchemaField("endtime", "End time", "datetime")}
                    </>
                );
        }
    };

    const renderCountdownEditor = () => {
        if (!editor) {
            return null;
        }
        return (
            <div className="mc-sf-countdown-editor">
                {renderPresetSelector()}

                <section className="mc-sf-bc-card">
                    <div className="mc-sf-bc-card__head">
                        <span>{labels.countdown_edititem ?? "Edit item"}</span>
                        <strong>{countdownEditorItems.find((item) => item.key === activeCountdownItem)?.label}</strong>
                    </div>
                    <div className="mc-sf-bc-picker" role="tablist"
                        aria-label={labels.countdown_edititem ?? "Edit item"}>
                        {countdownEditorItems.map((item) => (
                            <button key={item.key} type="button"
                                role="tab"
                                aria-selected={activeCountdownItem === item.key}
                                className={activeCountdownItem === item.key ? "is-active" : ""}
                                onClick={() => setActiveCountdownItem(item.key)}>
                                <i className={`bi ${item.icon}`} aria-hidden="true" />
                                <span>{labels[`countdown_item_${item.key}`] ?? item.label}</span>
                            </button>
                        ))}
                    </div>
                    <div className="mc-sf-bc-controls">
                        {renderCountdownItemControls()}
                    </div>
                </section>
            </div>
        );
    };

    const renderCategoriesColourControl = (key: string, label: string, fallbackKey = "") => {
        const value = savedEditorString(key);
        const placeholder = fallbackEditorString(fallbackKey);
        const swatch = value || placeholder;
        return (
            <label className="mc-sf-bc-field" key={key}>
                <span>{label}</span>
                <div className="mc-sf-color">
                    <input type="color" className="mc-sf-color__swatch" value={breadcrumbHexColour(swatch)}
                        aria-label={`${label} colour picker`}
                        onChange={(event) => setValue(key, event.currentTarget.value)} />
                    <input type="text" className="mc-sf-color__text" value={value}
                        placeholder={placeholder || "#1565c0 or var(--mc-primary)"}
                        onChange={(event) => setValue(key, event.currentTarget.value)} />
                </div>
            </label>
        );
    };

    const renderCategoriesNumberControl = (key: string, label: string, max = 240, fallback = 0) => (
        <label className="mc-sf-bc-field" key={key}>
            <span>{label}</span>
            <input type="number" min={0} max={max} value={numberInputValue(savedEditorValue(key))}
                placeholder={fallback > 0 ? String(fallback) : undefined}
                onChange={(event) => setValue(key, event.currentTarget.value === ""
                    ? ""
                    : Number(event.currentTarget.value))} />
        </label>
    );

    const renderCategoriesItemControls = () => {
        switch (activeCategoriesItem) {
            case "heading":
                return (
                    <>
                        {renderEditorSchemaField("title", "Title")}
                        {renderCategoriesColourControl("titlecolor", labels.categories_titlecolor ?? "Title colour")}
                        {renderCategoriesNumberControl(
                            "titlefontsize",
                            labels.categories_titlefontsize ?? "Title font size",
                            96,
                            36
                        )}
                        {renderEditorSchemaField("subtitle", "Subtitle")}
                        {renderCategoriesColourControl("subtitlecolor", labels.categories_subtitlecolor ?? "Subtitle colour")}
                        {renderCategoriesNumberControl(
                            "subtitlefontsize",
                            labels.categories_subtitlefontsize ?? "Subtitle font size",
                            96,
                            18
                        )}
                    </>
                );
            case "cards":
                return (
                    <>
                        {renderCategoriesColourControl("cardbgcolor", labels.categories_cardbgcolor ?? "Card background")}
                        {renderCategoriesColourControl("cardtextcolor", labels.categories_cardtextcolor ?? "Card text colour")}
                        {renderCategoriesNumberControl(
                            "cardtextfontsize",
                            labels.categories_cardtextfontsize ?? "Card text font size",
                            96,
                            20
                        )}
                        {renderCategoriesNumberControl("cardradius", labels.categories_cardradius ?? "Card radius", 96)}
                    </>
                );
            case "icons":
                return (
                    <>
                        {renderCategoriesColourControl("iconbgcolor", labels.categories_iconbgcolor ?? "Icon background")}
                        {renderCategoriesColourControl("iconcolor", labels.categories_iconcolor ?? "Icon colour")}
                        {renderCategoriesNumberControl("iconsize", labels.categories_iconsize ?? "Icon size", 96, 26)}
                    </>
                );
            case "count":
                return (
                    <>
                        {renderEditorSchemaField("showcount", "Show course count", "checkbox")}
                        {renderCategoriesColourControl("countcolor", labels.categories_countcolor ?? "Count text colour")}
                        {renderCategoriesNumberControl(
                            "countfontsize",
                            labels.categories_countfontsize ?? "Count font size",
                            96,
                            14
                        )}
                    </>
                );
            case "content":
                return renderEditorSchemaField("items", "Categories", "list");
            case "spacing":
                return (
                    <>
                        {renderCategoriesNumberControl("paddingtop", labels.categories_paddingtop ?? "Padding top")}
                        {renderCategoriesNumberControl("paddingbottom", labels.categories_paddingbottom ?? "Padding bottom")}
                    </>
                );
            case "layout":
            default:
                return (
                    <>
                        {renderEditorSchemaField("style", "Style", "select")}
                        {renderEditorSchemaField("visiblecards", "Categories visible per view", "number")}
                        {renderCategoriesColourControl("bgcolor", labels.categories_bgcolor ?? "Section background")}
                    </>
                );
        }
    };

    const renderCategoriesEditor = () => {
        if (!editor) {
            return null;
        }
        return (
            <div className="mc-sf-categories-editor">
                {renderPresetSelector()}

                <section className="mc-sf-bc-card">
                    <div className="mc-sf-bc-card__head">
                        <span>{labels.categories_edititem ?? "Edit item"}</span>
                        <strong>{categoriesEditorItems.find((item) => item.key === activeCategoriesItem)?.label}</strong>
                    </div>
                    <div className="mc-sf-bc-picker" role="tablist"
                        aria-label={labels.categories_edititem ?? "Edit item"}>
                        {categoriesEditorItems.map((item) => (
                            <button key={item.key} type="button"
                                role="tab"
                                aria-selected={activeCategoriesItem === item.key}
                                className={activeCategoriesItem === item.key ? "is-active" : ""}
                                onClick={() => setActiveCategoriesItem(item.key)}>
                                <i className={`bi ${item.icon}`} aria-hidden="true" />
                                <span>{labels[`categories_item_${item.key}`] ?? item.label}</span>
                            </button>
                        ))}
                    </div>
                    <div className="mc-sf-bc-controls">
                        {renderCategoriesItemControls()}
                    </div>
                </section>
            </div>
        );
    };

    const toolbar = isEditing && showToolbar ? (
        <div className="mc-sf-toolbar">
            <button type="button" className="mc-button mc-sf-customize" data-mc-button="primary"
                onClick={openDrawer}>
                <i className="bi bi-sliders" aria-hidden="true" /> {labels.customize ?? "Widget customizer"}
            </button>
        </div>
    ) : null;
    const toolbarTarget = toolbarTargetId ? document.getElementById(toolbarTargetId) : null;
    const addableZones = layout ? zonesForNewWidget(layout.zones, pageType) : [];

    return (
        <div className={isEditing ? "mc-sf mc-sf-editing" : "mc-sf"}>
            {toolbarTarget && toolbar ? createPortal(toolbar, toolbarTarget) : toolbar}

            {error && <div className="mc-sf-error" role="alert">{error}</div>}

            {data?.zones.map((zone) => (
                <div className={`mw-zone-render mw-zone-render--${zone.slug}`} key={zone.slug}>
                    {zone.widgets.map((widget) => {
                        const renderedWidget = editor ? widgetPreviewFromEditor(widget, editor) : widget;
                        return <RenderedWidget key={widget.id} widget={renderedWidget} catalogLabels={catalogLabels}
                            editing={isEditing} typeLabel={typeLabelFor(widget.type)} onGear={openEditor} />;
                    })}
                </div>
            ))}

            {/* Customize drawer: arrange every widget (show/hide, reorder, move zone, add, delete, edit). */}
            {drawerOpen && (
                <McDrawer
                    title={labels.managetitle ?? "Customize storefront"}
                    subtitle={labels.manageintro}
                    onClose={() => setDrawerOpen(false)}
                    closeLabel={labels.cancel}
                    disableClose={busy}
                    footer={(
                        <>
                            <McButton className="mc-sf-btn-primary" variant="primary"
                                disabled={!layout} loading={busy} loadingLabel={labels.saving || "Saving..."} onClick={saveLayout}>
                                {labels.save}
                            </McButton>
                            <button className="mc-button mc-sf-btn-secondary" data-mc-button="soft"
                                disabled={busy} onClick={() => setDrawerOpen(false)}>
                                {labels.cancel}
                            </button>
                        </>
                    )}
                >
                            {!layout && <div>{labels.loading}</div>}
                            {layout && rows.map((row, i) => {
                                const rowZones = zonesForScope(layout.zones, isGlobalLayoutRow(row) ? "global" : "page");
                                const scopeLabel = isGlobalLayoutRow(row) ? (labels.global ?? "Global") : "";
                                return (
                                <div className={"mc-sf-row" + (row.enabled ? "" : " mc-sf-row--off")} key={row.id}>
                                    <div className="mc-sf-row__reorder">
                                        <button className="mc-button mc-sf-row__btn" data-mc-button="ghost"
                                            data-mc-button-size="icon" disabled={i === 0 || busy}
                                            onClick={() => moveRow(i, -1)} type="button" aria-label={labels.moveup}>
                                            <i className="bi bi-chevron-up" aria-hidden="true" />
                                        </button>
                                        <button className="mc-button mc-sf-row__btn" data-mc-button="ghost"
                                            data-mc-button-size="icon" disabled={i === rows.length - 1 || busy}
                                            onClick={() => moveRow(i, 1)} type="button" aria-label={labels.movedown}>
                                            <i className="bi bi-chevron-down" aria-hidden="true" />
                                        </button>
                                    </div>
                                    <label title={labels.show}>
                                        <input type="checkbox" checked={row.enabled}
                                            onChange={(e) => setRow(i, {enabled: e.target.checked})} />
                                    </label>
                                    <span className="mc-sf-row__title">
                                        {row.title || row.typelabel}
                                        <span className="mc-sf-row__type">
                                            {" · "}{row.typelabel}{scopeLabel ? ` · ${scopeLabel}` : ""}
                                        </span>
                                    </span>
                                    <select className="mc-sf-row__zone" value={row.zone} disabled={busy}
                                        onChange={(e) => setRow(i, {zone: e.target.value})} aria-label={labels.zone}>
                                        {rowZones.map((z) => (
                                            <option key={z.slug} value={z.slug}>{z.label}</option>
                                        ))}
                                    </select>
                                    <button type="button" className="mc-button mc-sf-row__btn" data-mc-button="ghost"
                                        data-mc-button-size="icon" title={labels.editwidget} disabled={busy}
                                        onClick={() => openEditor(row.id)}>
                                        <i className="bi bi-gear" aria-hidden="true" />
                                    </button>
                                    <button type="button" className="mc-button mc-sf-row__btn" data-mc-button="ghost"
                                        data-mc-button-size="icon" title={labels.deletewidget} disabled={busy}
                                        onClick={() => deleteWidget(row.id)}>
                                        <i className="bi bi-trash" aria-hidden="true" />
                                    </button>
                                </div>
                                );
                            })}

                            {layout && (
                                <>
                                    <div className="mc-sf-section__title">{labels.addwidget ?? "Add widget"}</div>
                                    <div className="mc-sf-row">
                                        <select value={addType} onChange={(e) => setAddType(e.target.value)}
                                            disabled={busy} aria-label={labels.choosetype}>
                                            {layout.types.map((t) => (
                                                <option key={t.key} value={t.key}>{t.label}</option>
                                            ))}
                                        </select>
                                        {presetListMethod && (
                                            <select value={addPresetId}
                                                onChange={(e) => setAddPresetId(Number(e.target.value))}
                                                disabled={busy || addPresets.length === 0}
                                                aria-label={labels.widgetpreset ?? "Widget preset"}>
                                                <option value={0}>{labels.widgetpresetnone ?? "No preset"}</option>
                                                {addPresets.map((preset) => (
                                                    <option key={preset.id} value={preset.id}>{preset.name}</option>
                                                ))}
                                            </select>
                                        )}
                                        <select value={addZone} onChange={(e) => setAddZone(e.target.value)}
                                            disabled={busy} aria-label={labels.zone}>
                                            {addableZones.map((z) => (
                                                <option key={z.slug} value={z.slug}>{z.label}</option>
                                            ))}
                                        </select>
                                        <button type="button" className="mc-button mc-sf-row__btn" data-mc-button="ghost"
                                            data-mc-button-size="icon" disabled={busy} onClick={addWidget}
                                            title={labels.addwidget}>
                                            <i className="bi bi-plus-lg" aria-hidden="true" />
                                        </button>
                                    </div>
                                </>
                            )}
                </McDrawer>
            )}

            {editorId !== null && (
                <McDrawer
                    title={isFocusedEditorType(editor?.type)
                        ? (labels.variantsettings ?? "Variant Settings")
                        : (labels.editwidget ?? "Edit settings")}
                    subtitle={isFocusedEditorType(editor?.type)
                        ? (labels.variantsettingsdesc ?? "Configure the selected widget style and reusable preset.")
                        : undefined}
                    onClose={closeEditor}
                    closeLabel={labels.cancel}
                    disableClose={editorBusy}
                    nested={drawerOpen}
                    footer={(
                        <>
                            <McButton className="mc-sf-btn-primary" variant="primary"
                                disabled={!editor} loading={editorBusy} loadingLabel={labels.saving || "Saving..."} onClick={saveEditor}>
                                {labels.save}
                            </McButton>
                            <button className="mc-button mc-sf-btn-secondary" data-mc-button="soft"
                                disabled={editorBusy} onClick={closeEditor}>
                                {labels.cancel}
                            </button>
                        </>
                    )}
                >
                            {!editor && <div>{labels.loading}</div>}
                            {editor?.type === "slider" && renderSliderEditor()}
                            {editor?.type === "breadcrumb" && renderBreadcrumbEditor()}
                            {editor?.type === "videohero" && renderVideoHeroEditor()}
                            {editor?.type === "countdown" && renderCountdownEditor()}
                            {editor?.type === "categories" && renderCategoriesEditor()}
                            {(editor?.type === "featured" || editor?.type === "related") && renderFeaturedProductsEditor()}
                            {editor && editor.type !== "slider" && editor.type !== "breadcrumb" && editor.type !== "videohero"
                                && editor.type !== "countdown" && editor.type !== "categories"
                                && editor.type !== "featured" && editor.type !== "related" && (
                                renderGenericEditor()
                            )}
                </McDrawer>
            )}
        </div>
    );
}

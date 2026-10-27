// This file is part of Moodle and is licensed under the GNU GPL v3 or later.

/**
 * Static contract audit for Gallery and live-page widget controls.
 *
 * Run from the repository root:
 *   node public/local/moderncommerce/tests/widget_editor_parity_audit.mjs
 */

import {execFileSync} from "node:child_process";
import {readFileSync} from "node:fs";
import {dirname, resolve} from "node:path";
import {fileURLToPath} from "node:url";

const pluginRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const pageSource = readFileSync(resolve(pluginRoot, "js/esm/src/storefront.tsx"), "utf8");
const gallerySource = readFileSync(resolve(pluginRoot, "js/esm/src/storefront_gallery.tsx"), "utf8");

const extractLiteral = (source, declaration, opening, closing) => {
    const declarationAt = source.indexOf(`const ${declaration}`);
    if (declarationAt < 0) {
        throw new Error(`Missing ${declaration} declaration.`);
    }
    const start = source.indexOf(opening, declarationAt);
    let depth = 0;
    for (let end = start; end < source.length; end++) {
        if (source[end] === opening) {
            depth++;
        } else if (source[end] === closing && --depth === 0) {
            return Function(`"use strict"; return (${source.slice(start, end + 1)});`)();
        }
    }
    throw new Error(`Unclosed ${declaration} declaration.`);
};

const pageProfiles = extractLiteral(pageSource, "remainingEditorProfiles", "{", "}");
const galleryProfiles = extractLiteral(gallerySource, "galleryVisualProfiles", "{", "}");
const gallerySelects = extractLiteral(gallerySource, "gallerySelectChoices", "{", "}");
const widgetTypes = Object.keys(galleryProfiles);
const phpCode = String.raw`
define('CLI_SCRIPT', true);
require getenv('MC_CONFIG');
require_once getenv('MC_SCHEMA');
$result = [];
foreach (json_decode(getenv('MC_TYPES'), true) as $type) {
    foreach (\local_moderncommerce\storefront\field_schema::for_type($type) as $field) {
        $result[$type][$field['name']] = $field['type'];
    }
}
echo json_encode($result, JSON_THROW_ON_ERROR);
`;
const schema = JSON.parse(execFileSync("php", ["-r", phpCode], {
    encoding: "utf8",
    env: {
        ...process.env,
        MC_CONFIG: resolve(pluginRoot, "../../config.php"),
        MC_SCHEMA: resolve(pluginRoot, "classes/storefront/field_schema.php"),
        MC_TYPES: JSON.stringify(widgetTypes),
    },
}));

const failures = [];
const fail = (message) => failures.push(message);
const booleanKeys = new Set(["autoplay", "greyscale", "pauseonhover", "centercards", "justifytext",
    "highlightfeatured"]);
const isBoolean = (key) => key.startsWith("show") || key.startsWith("hide") || booleanKeys.has(key);
const isColour = (key) => key.includes("color") || ["bg", "bgcolor", "gradientstart", "gradientend"].includes(key);
const isNumber = (key) => key.includes("font") || key.includes("padding") || key.includes("spacing")
    || key.includes("radius") || ["overlayopacity", "visiblecards", "iconsize", "panelborderwidth",
        "logoheight", "visiblelogos", "speed", "logogap", "mediawidth"].includes(key)
    || key.endsWith("borderwidth") || key.includes("margin");
const galleryKind = (type, key) => isColour(key) ? "color"
    : isBoolean(key) ? "checkbox"
        : key === "navicon" ? "icon"
            : gallerySelects[type]?.[key] ? "select"
                : isNumber(key) ? "number" : "text";

for (const [type, gallerySections] of Object.entries(galleryProfiles)) {
    const pageSections = pageProfiles[type];
    if (!pageSections) {
        fail(`${type}: missing focused live-page profile`);
        continue;
    }
    const pageFieldGroup = new Map();
    for (const section of pageSections) {
        for (const field of section.fields ?? []) {
            pageFieldGroup.set(field, section);
        }
    }
    for (const section of gallerySections) {
        for (const key of section.patchKeys ?? []) {
            const pageSection = pageFieldGroup.get(key);
            if (!pageSection) {
                fail(`${type}.${key}: missing from live-page profile`);
                continue;
            }
            if (pageSection.key !== section.key || pageSection.label !== section.label) {
                fail(`${type}.${key}: Gallery ${section.label} (${section.key}) != page `
                    + `${pageSection.label} (${pageSection.key})`);
            }
            const pageKind = schema[type]?.[key];
            const expectedGalleryKind = galleryKind(type, key);
            if (pageKind !== expectedGalleryKind) {
                fail(`${type}.${key}: Gallery ${expectedGalleryKind} != page ${pageKind ?? "missing schema"}`);
            }
        }
    }
    for (const key of Object.keys(schema[type] ?? {})) {
        if (!pageFieldGroup.has(key)) {
            fail(`${type}.${key}: schema control missing from live-page profile`);
        }
    }
}

const customPairs = [
    ["breadcrumbEditorItems", "breadcrumbSettingItems"],
    ["videoHeroEditorItems", "videoHeroSettingItems"],
    ["countdownEditorItems", "countdownSettingItems"],
    ["categoriesEditorItems", "categoriesSettingItems"],
];
for (const [pageName, galleryName] of customPairs) {
    const pageItems = extractLiteral(pageSource, pageName, "[", "]");
    const galleryItems = extractLiteral(gallerySource, galleryName, "[", "]");
    for (const galleryItem of galleryItems) {
        const pageItem = pageItems.find((item) => item.key === galleryItem.key);
        if (!pageItem || pageItem.label !== galleryItem.label || pageItem.icon !== galleryItem.icon) {
            fail(`${galleryName}.${galleryItem.key}: custom tab differs from live page`);
        }
    }
}

if (!pageSource.includes('className="mc-sf-switch"') || !gallerySource.includes('className="mcg-switch"')) {
    fail("Gallery and live-page schema booleans must both render as switches.");
}

if (failures.length) {
    console.error(failures.map((message) => `- ${message}`).join("\n"));
    process.exit(1);
}

const sharedControls = Object.values(galleryProfiles).flatMap((sections) =>
    sections.flatMap((section) => section.patchKeys ?? [])).length;
console.log(`Widget editor parity audit passed: ${widgetTypes.length} profiles, ${sharedControls} shared controls.`);

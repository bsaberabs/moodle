#!/usr/bin/env node
/**
 * This file is part of Moodle and is licensed under the
 * GNU General Public License, version 3 or later.
 *
 * You may redistribute and modify it under the terms of the GPL.
 * See the plugin root LICENSE file for complete terms.
 *
 * Modern Commerce performance report utility.
 *
 * @module     local_moderncommerce/performance_report
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import fs from "node:fs";
import path from "node:path";
import process from "node:process";

const root = process.cwd();
const pluginRoot = path.join(root, "public/local/moderncommerce");
const json = process.argv.includes("--json");
const maxItemsArg = process.argv.find((arg) => arg.startsWith("--limit="));
const maxItems = maxItemsArg ? Math.max(1, Number.parseInt(maxItemsArg.split("=")[1], 10) || 20) : 20;

const assetExtensions = new Set([
    ".avif",
    ".css",
    ".jpg",
    ".jpeg",
    ".js",
    ".png",
    ".svg",
    ".webp",
    ".woff",
    ".woff2",
]);
const ignoredSegments = new Set([
    ".git",
    "amd/build",
    "js/esm/build",
    "node_modules",
    "releases",
    "vendor",
]);

const toRelative = (file) => path.relative(pluginRoot, file).replaceAll("\\", "/");

const shouldIgnore = (file) => {
    const relative = toRelative(file);
    for (const segment of ignoredSegments) {
        if (relative === segment || relative.startsWith(`${segment}/`)) {
            return true;
        }
    }
    return false;
};

const walk = (dir, predicate) => {
    const files = [];
    for (const entry of fs.readdirSync(dir, {withFileTypes: true})) {
        const full = path.join(dir, entry.name);
        if (shouldIgnore(full)) {
            continue;
        }
        if (entry.isDirectory()) {
            files.push(...walk(full, predicate));
        } else if (predicate(full)) {
            files.push(full);
        }
    }
    return files;
};

const kb = (bytes) => Math.round(bytes / 102.4) / 10;

const assetFiles = walk(pluginRoot, (file) => assetExtensions.has(path.extname(file).toLowerCase()));
const assets = assetFiles
    .map((file) => ({
        path: toRelative(file),
        kb: kb(fs.statSync(file).size),
    }))
    .sort((a, b) => b.kb - a.kb);

const css = assets.filter((asset) => asset.path.endsWith(".css"));
const js = assets.filter((asset) => asset.path.endsWith(".js"));
const images = assets.filter((asset) => /\.(avif|jpe?g|png|svg|webp)$/i.test(asset.path));
const fonts = assets.filter((asset) => /\.(woff2?|otf|ttf)$/i.test(asset.path));

const sourceFiles = walk(pluginRoot, (file) =>
    [".php", ".mustache", ".scss", ".js", ".ts", ".tsx"].includes(path.extname(file).toLowerCase())
);
const externalUrlPattern = /https?:\/\/[^\s"'<>)]*/g;
const externalUrls = new Map();
for (const file of sourceFiles) {
    const content = fs.readFileSync(file, "utf8");
    const matches = content.match(externalUrlPattern) || [];
    for (const match of matches) {
        const url = match.replace(/[.,;]+$/, "");
        if (/gnu\.org|moodle\.org|w3\.org/i.test(url)) {
            continue;
        }
        if (!externalUrls.has(url)) {
            externalUrls.set(url, new Set());
        }
        externalUrls.get(url).add(toRelative(file));
    }
}

const reactRoutePattern = /['"]component['"]\s*=>\s*['"]@moodle\/lms\/local_moderncommerce\/([^'"]+)['"]/g;
const reactRoutes = [];
for (const file of sourceFiles.filter((source) => path.extname(source).toLowerCase() === ".php")) {
    const content = fs.readFileSync(file, "utf8");
    let match;
    while ((match = reactRoutePattern.exec(content)) !== null) {
        const bundlePath = path.join(pluginRoot, "js/esm/build", `${match[1]}.js`);
        reactRoutes.push({
            routeFile: toRelative(file),
            component: match[1],
            bundle: fs.existsSync(bundlePath) ? toRelative(bundlePath) : "",
            kb: fs.existsSync(bundlePath) ? kb(fs.statSync(bundlePath).size) : 0,
        });
    }
}
reactRoutes.sort((a, b) => b.kb - a.kb);

const report = {
    generatedAt: new Date().toISOString(),
    topAssets: assets.slice(0, maxItems),
    css,
    js: js.slice(0, maxItems),
    images,
    fonts,
    externalUrls: [...externalUrls.entries()]
        .map(([url, files]) => ({url, files: [...files].sort()}))
        .sort((a, b) => a.url.localeCompare(b.url)),
    reactRoutes: reactRoutes.slice(0, maxItems),
    notes: [
        "CSS is intentionally expanded for design work; minification belongs in packaging.",
        "External image URLs in seed/preset data can become production payload if seeded.",
        "React bundles are route-specific; review the largest route bundles before adding more editor code.",
    ],
};

if (json) {
    console.log(JSON.stringify(report, null, 2));
} else {
    console.log("Modern Commerce performance report");
    console.log("==================================");
    console.log("");
    console.log("Top assets");
    for (const asset of report.topAssets) {
        console.log(`${asset.kb.toString().padStart(7)} KB  ${asset.path}`);
    }
    console.log("");
    console.log("Generated CSS");
    for (const asset of report.css) {
        console.log(`${asset.kb.toString().padStart(7)} KB  ${asset.path}`);
    }
    console.log("");
    console.log("Largest React/ESM route bundles");
    for (const route of report.reactRoutes) {
        const size = route.kb > 0 ? `${route.kb} KB` : "missing";
        console.log(`${size.toString().padStart(10)}  ${route.component}  ${route.routeFile}`);
    }
    console.log("");
    console.log(`External non-license URLs: ${report.externalUrls.length}`);
    for (const item of report.externalUrls.slice(0, maxItems)) {
        console.log(`- ${item.url}`);
        console.log(`  ${item.files.join(", ")}`);
    }
    console.log("");
    for (const note of report.notes) {
        console.log(`Note: ${note}`);
    }
}

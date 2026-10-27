#!/usr/bin/env node
/**
 * This file is part of Moodle and is licensed under the
 * GNU General Public License, version 3 or later.
 *
 * You may redistribute and modify it under the terms of the GPL.
 * See the plugin root LICENSE file for complete terms.
 *
 * Modern Commerce Bootstrap Icons audit.
 *
 * @module     local_moderncommerce/audit_bootstrap_icons
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import fs from "node:fs";
import path from "node:path";
import process from "node:process";

const root = process.cwd();
const pluginRoot = path.join(root, "public/local/moderncommerce");
const iconScss = path.join(pluginRoot, "styles/scss/thirdparty/bootstrap-icons/_bootstrap-icons.scss");
const fontDir = path.join(pluginRoot, "styles/scss/thirdparty/bootstrap-icons/fonts");
const json = process.argv.includes("--json");

const sourceExtensions = new Set([".php", ".mustache", ".js", ".ts", ".tsx"]);
const ignoredIconMatches = new Set(["bi-prefixed"]);
const ignoredSegments = new Set([
    ".git",
    "amd/build",
    "js/esm/build",
    "node_modules",
    "releases",
    "styles/bundles",
    "styles/scss/thirdparty",
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

const walk = (dir) => {
    const files = [];
    for (const entry of fs.readdirSync(dir, {withFileTypes: true})) {
        const full = path.join(dir, entry.name);
        if (shouldIgnore(full)) {
            continue;
        }
        if (entry.isDirectory()) {
            files.push(...walk(full));
        } else if (sourceExtensions.has(path.extname(full))) {
            files.push(full);
        }
    }
    return files;
};

const extractIconClasses = (content) => {
    const icons = new Set();
    const iconPattern = /\bbi-[a-z0-9-]+\b/g;
    let match;
    while ((match = iconPattern.exec(content)) !== null) {
        if (!ignoredIconMatches.has(match[0])) {
            icons.add(match[0]);
        }
    }
    return icons;
};

const available = extractIconClasses(fs.readFileSync(iconScss, "utf8"));
const files = walk(pluginRoot);
const used = new Map();

for (const file of files) {
    const icons = extractIconClasses(fs.readFileSync(file, "utf8"));
    for (const icon of icons) {
        if (!used.has(icon)) {
            used.set(icon, new Set());
        }
        used.get(icon).add(toRelative(file));
    }
}

const fontFiles = fs.existsSync(fontDir)
    ? fs.readdirSync(fontDir)
        .filter((file) => /\.(woff2?|ttf|otf)$/i.test(file))
        .map((file) => {
            const full = path.join(fontDir, file);
            return {
                file: toRelative(full),
                kb: Math.round(fs.statSync(full).size / 102.4) / 10,
            };
        })
    : [];

const usedIcons = [...used.keys()].sort();
const missing = usedIcons.filter((icon) => !available.has(icon));
const summary = {
    availableIcons: available.size,
    usedIcons: usedIcons.length,
    missingIcons: missing,
    fontFiles,
    icons: usedIcons.map((icon) => ({
        icon,
        files: [...used.get(icon)].sort(),
    })),
    note: "Use this report to decide whether a subset Bootstrap Icons font is worth maintaining.",
};

if (json) {
    console.log(JSON.stringify(summary, null, 2));
} else {
    console.log("Modern Commerce Bootstrap Icons audit");
    console.log("======================================");
    console.log(`Available Bootstrap Icons: ${summary.availableIcons}`);
    console.log(`Used Bootstrap Icons: ${summary.usedIcons}`);
    for (const font of fontFiles) {
        console.log(`${font.file}: ${font.kb} KB`);
    }
    if (missing.length > 0) {
        console.log("");
        console.log(`Missing icon definitions: ${missing.join(", ")}`);
    }
    console.log("");
    for (const item of summary.icons) {
        console.log(`- ${item.icon} (${item.files.length} files)`);
    }
    console.log("");
    console.log(summary.note);
}

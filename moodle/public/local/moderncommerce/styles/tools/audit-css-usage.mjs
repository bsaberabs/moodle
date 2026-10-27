#!/usr/bin/env node
/**
 * This file is part of Moodle and is licensed under the
 * GNU General Public License, version 3 or later.
 *
 * You may redistribute and modify it under the terms of the GPL.
 * See the plugin root LICENSE file for complete terms.
 *
 * Modern Commerce CSS usage audit.
 *
 * @module     local_moderncommerce/audit_css_usage
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import fs from "node:fs";
import path from "node:path";
import process from "node:process";

const root = process.cwd();
const pluginRoot = path.join(root, "public/local/moderncommerce");
const scssRoot = path.join(pluginRoot, "styles/scss");
const limitArg = process.argv.find((arg) => arg.startsWith("--limit="));
const limit = limitArg ? Math.max(1, Number.parseInt(limitArg.split("=")[1], 10) || 80) : 80;
const json = process.argv.includes("--json");

const sourceExtensions = new Set([".php", ".mustache", ".js", ".ts", ".tsx"]);
const styleExtensions = new Set([".scss"]);
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
const classPrefixes = [
    "acf-",
    "ccp_",
    "icon-",
    "learner-",
    "local-moderncommerce-",
    "mc-",
    "mcg-",
    "mui-",
    "mw-",
];
const dynamicPatterns = [
    /--active$/,
    /--busy$/,
    /--danger$/,
    /--disabled$/,
    /--error$/,
    /--hidden$/,
    /--info$/,
    /--loading$/,
    /--open$/,
    /--primary$/,
    /--selected$/,
    /--success$/,
    /--warning$/,
    /^mc-toast--/,
    /^mc-badge-/,
    /^mc-button--/,
    /^mc-btn-/,
    /^mc-status-/,
    /^mcg-/,
];

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

const extractClasses = (content) => {
    const classes = new Set();
    const classPattern = /(?<![A-Za-z0-9_-])\.([_A-Za-z][-_A-Za-z0-9]*)/g;
    let match;
    while ((match = classPattern.exec(content)) !== null) {
        const classname = match[1];
        if (classPrefixes.some((prefix) => classname.startsWith(prefix))) {
            classes.add(classname);
        }
    }
    return classes;
};

const styleFiles = walk(scssRoot, (file) => styleExtensions.has(path.extname(file)));
const sourceFiles = walk(pluginRoot, (file) => sourceExtensions.has(path.extname(file)));

const defined = new Map();
for (const file of styleFiles) {
    const content = fs.readFileSync(file, "utf8");
    for (const classname of extractClasses(content)) {
        if (!defined.has(classname)) {
            defined.set(classname, new Set());
        }
        defined.get(classname).add(toRelative(file));
    }
}

const sourceCorpus = sourceFiles
    .map((file) => fs.readFileSync(file, "utf8"))
    .join("\n");

const candidates = [];
for (const [classname, files] of defined.entries()) {
    if (dynamicPatterns.some((pattern) => pattern.test(classname))) {
        continue;
    }
    if (!sourceCorpus.includes(classname)) {
        candidates.push({
            class: classname,
            definedIn: [...files].sort(),
        });
    }
}

candidates.sort((a, b) => a.class.localeCompare(b.class));

const summary = {
    definedClasses: defined.size,
    sourceFiles: sourceFiles.length,
    candidateCount: candidates.length,
    candidates: candidates.slice(0, limit),
    note: "Candidates are not deletions. Review dynamic React/PHP classes before removing CSS.",
};

if (json) {
    console.log(JSON.stringify(summary, null, 2));
} else {
    console.log("Modern Commerce CSS usage audit");
    console.log("===============================");
    console.log(`Defined plugin classes: ${summary.definedClasses}`);
    console.log(`Scanned source files: ${summary.sourceFiles}`);
    console.log(`Likely-unused candidates: ${summary.candidateCount}`);
    console.log("");
    for (const candidate of summary.candidates) {
        console.log(`- .${candidate.class}`);
        console.log(`  ${candidate.definedIn.join(", ")}`);
    }
    if (summary.candidateCount > summary.candidates.length) {
        console.log("");
        console.log(`Showing first ${summary.candidates.length}. Re-run with --limit=${summary.candidateCount} for all candidates.`);
    }
    console.log("");
    console.log(summary.note);
}

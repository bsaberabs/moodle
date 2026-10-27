#!/usr/bin/env node
/**
 * This file is part of Moodle and is licensed under the
 * GNU General Public License, version 3 or later.
 *
 * You may redistribute and modify it under the terms of the GPL.
 * See the plugin root LICENSE file for complete terms.
 *
 * Modern Commerce documentation checker.
 *
 * @module     local_moderncommerce/check_docs
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const start = process.cwd();
const pluginRoot = resolvePluginRoot(start);
const docsRoot = path.join(pluginRoot, 'docs');
const mkdocsPath = path.join(pluginRoot, 'mkdocs.yml');
const helpCatalogPath = path.join(pluginRoot, 'classes', 'docs', 'admin_help_catalog.php');
const failures = [];

function resolvePluginRoot(cwd) {
    if (fs.existsSync(path.join(cwd, 'version.php')) && fs.existsSync(path.join(cwd, 'docs'))) {
        return cwd;
    }

    const nested = path.join(cwd, 'public', 'local', 'moderncommerce');
    if (fs.existsSync(path.join(nested, 'version.php')) && fs.existsSync(path.join(nested, 'docs'))) {
        return nested;
    }

    return cwd;
}

function read(file) {
    return fs.readFileSync(file, 'utf8');
}

function relative(file) {
    return path.relative(pluginRoot, file).replaceAll(path.sep, '/');
}

function withoutCodeBlocks(markdown) {
    return markdown.replace(/```[\s\S]*?```/g, '');
}

function addFailure(message) {
    failures.push(message);
}

function collectMkdocsTargets() {
    if (!fs.existsSync(mkdocsPath)) {
        addFailure('Missing mkdocs.yml.');
        return [];
    }

    const content = read(mkdocsPath);
    const targets = new Set();
    const navPattern = /:\s*([A-Za-z0-9_./-]+\.md)\s*$/gm;

    for (const match of content.matchAll(navPattern)) {
        targets.add(match[1]);
    }

    for (const target of targets) {
        const file = path.join(docsRoot, target);
        if (!fs.existsSync(file)) {
            addFailure(`mkdocs nav target is missing: ${target}`);
        }
    }

    return [...targets];
}

function checkMarkdownFile(file) {
    if (!fs.existsSync(file)) {
        addFailure(`Documentation file is missing: ${relative(file)}`);
        return;
    }

    const markdown = withoutCodeBlocks(read(file));

    if (/\blocal_coursecommerce\b|Course Commerce/.test(markdown)) {
        addFailure(`${relative(file)} contains stale Course Commerce naming.`);
    }

    const linkPattern = /\[[^\]]+\]\((?!https?:\/\/|mailto:|#)([^)\s]+\.md(?:#[^)]+)?)\)/g;
    for (const match of markdown.matchAll(linkPattern)) {
        const rawTarget = decodeURI(match[1].split('#')[0]);
        const resolved = rawTarget.startsWith('/')
            ? path.join(pluginRoot, rawTarget.slice(1))
            : path.resolve(path.dirname(file), rawTarget);

        if (!fs.existsSync(resolved)) {
            addFailure(`${relative(file)} links to a missing file: ${match[1]}`);
        }
    }
}

const mkdocsTargets = collectMkdocsTargets();
const helpCatalogTargets = collectHelpCatalogTargets();
const filesToCheck = new Set([
    path.join(pluginRoot, 'README.md'),
    ...mkdocsTargets.map((target) => path.join(docsRoot, target)),
    ...helpCatalogTargets.map((target) => path.join(docsRoot, target)),
]);

for (const file of filesToCheck) {
    checkMarkdownFile(file);
}

if (failures.length > 0) {
    console.error('Modern Commerce docs check failed:');
    for (const failure of failures) {
        console.error(`- ${failure}`);
    }
    process.exit(1);
}

console.log(`Modern Commerce docs check passed (${filesToCheck.size} files, ${mkdocsTargets.length} nav entries).`);

function collectHelpCatalogTargets() {
    if (!fs.existsSync(helpCatalogPath)) {
        return [];
    }

    const content = read(helpCatalogPath);
    const targets = new Set();
    const filePattern = /'file'\s*=>\s*'([^']+\.md)'/g;

    for (const match of content.matchAll(filePattern)) {
        const target = match[1];
        targets.add(target);
        if (!fs.existsSync(path.join(docsRoot, target))) {
            addFailure(`admin help catalog target is missing: ${target}`);
        }
    }

    return [...targets];
}

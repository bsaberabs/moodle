#!/usr/bin/env node
// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Guard against the string-audit regressions fixed during localisation.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import fs from "node:fs";
import path from "node:path";
import {fileURLToPath} from "node:url";

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(scriptDir, "..");
const errors = [];

const read = (relativePath) => fs.readFileSync(path.join(pluginRoot, relativePath), "utf8");

const lineNumber = (text, index) => text.slice(0, index).split(/\r?\n/).length;

const collectFiles = (dir, extensions, files = []) => {
    for (const entry of fs.readdirSync(dir, {withFileTypes: true})) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            collectFiles(full, extensions, files);
            continue;
        }
        if (extensions.has(path.extname(entry.name))) {
            files.push(full);
        }
    }
    return files;
};

const relative = (fullPath) => path.relative(pluginRoot, fullPath).replace(/\\/g, "/");

const checkLanguageFile = () => {
    const text = read("lang/en/local_moderncommerce.php");
    const pattern = /^\$string\['((?:\\.|[^'\\])*)'\]\s*=\s*'((?:\\.|[^'\\])*)';\r?\n?/gms;
    const keys = [];
    let match = pattern.exec(text);
    while (match) {
        keys.push({key: match[1], index: match.index});
        match = pattern.exec(text);
    }

    const seen = new Map();
    for (const item of keys) {
        if (seen.has(item.key)) {
            errors.push(
                `lang/en/local_moderncommerce.php:${lineNumber(text, item.index)} duplicate key '${item.key}'`
            );
        }
        seen.set(item.key, item.index);
    }

    for (let i = 1; i < keys.length; i++) {
        if (keys[i].key.toLowerCase() < keys[i - 1].key.toLowerCase()) {
            errors.push(
                `lang/en/local_moderncommerce.php:${lineNumber(text, keys[i].index)} key '${keys[i].key}' is out of order after '${keys[i - 1].key}'`
            );
            break;
        }
    }

    return keys.length;
};

const checkFrontendStrings = () => {
    const srcRoot = path.join(pluginRoot, "js/esm/src");
    const files = collectFiles(srcRoot, new Set([".ts", ".tsx"]));
    const literalAttribute = /(aria-label|loadingLabel|placeholder)=["']([A-Z][^"']{2,})["']/g;
    const englishFallback = /\bt\(\s*["'][^"']+["']\s*,\s*["'][A-Z][^"']*["']/g;
    const browserLocale =
        /new Intl\.(?:NumberFormat|DateTimeFormat)\(\s*(?:\)|undefined\b)|toLocaleDateString\(\s*(?:\)|undefined\b)/g;

    for (const file of files) {
        const text = fs.readFileSync(file, "utf8");
        for (const [name, pattern] of [
            ["literal JSX/TSX label attribute", literalAttribute],
            ["English t(key, fallback) call", englishFallback],
            ["browser-default locale formatter", browserLocale],
        ]) {
            pattern.lastIndex = 0;
            let match = pattern.exec(text);
            while (match) {
                errors.push(`${relative(file)}:${lineNumber(text, match.index)} ${name}: ${match[0]}`);
                match = pattern.exec(text);
            }
        }
    }

    return files.length;
};

const keyCount = checkLanguageFile();
const frontendCount = checkFrontendStrings();

if (errors.length > 0) {
    console.error("Modern Commerce string audit guard failed:");
    for (const error of errors) {
        console.error(`- ${error}`);
    }
    process.exit(1);
}

console.log(`Modern Commerce string audit guard passed (${keyCount} lang keys, ${frontendCount} TS/TSX files).`);

#!/usr/bin/env node
/**
 * This file is part of Moodle and is licensed under the
 * GNU General Public License, version 3 or later.
 *
 * You may redistribute and modify it under the terms of the GPL.
 * See the plugin root LICENSE file for complete terms.
 *
 * Modern Commerce performance smoke test.
 *
 * @module     local_moderncommerce/performance_smoke
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import http from "node:http";
import https from "node:https";
import fs from "node:fs";
import path from "node:path";
import process from "node:process";

const root = process.cwd();
const configPath = path.join(root, "config.php");
const routeArgs = process.argv.filter((arg) => arg.startsWith("/local/moderncommerce/"));
const baseArg = process.argv.find((arg) => arg.startsWith("--base="));
const defaultRoutes = [
    "/local/moderncommerce/index.php",
    "/local/moderncommerce/about.php",
    "/local/moderncommerce/course_details.php?id=1",
    "/local/moderncommerce/cart.php",
    "/local/moderncommerce/checkout.php",
    "/local/moderncommerce/learner/index.php",
    "/local/moderncommerce/admin/index.php",
    "/local/moderncommerce/admin/gallery.php",
];

const readDefaultBase = () => {
    if (!fs.existsSync(configPath)) {
        return "http://localhost";
    }
    const config = fs.readFileSync(configPath, "utf8");
    const match = config.match(/\$CFG->wwwroot\s*=\s*['"]([^'"]+)['"]/);
    return match ? match[1] : "http://localhost";
};

const base = (baseArg ? baseArg.slice("--base=".length) : readDefaultBase()).replace(/\/$/, "");
const routes = routeArgs.length > 0 ? routeArgs : defaultRoutes;

const fetchUrl = (url, redirectDepth = 0) => new Promise((resolve) => {
    const client = url.startsWith("https:") ? https : http;
    const request = client.get(url, (response) => {
        const status = response.statusCode || 0;
        const location = response.headers.location;
        if ([301, 302, 303, 307, 308].includes(status) && location && redirectDepth < 4) {
            const next = new URL(location, url).toString();
            response.resume();
            fetchUrl(next, redirectDepth + 1).then(resolve);
            return;
        }
        const chunks = [];
        response.on("data", (chunk) => chunks.push(chunk));
        response.on("end", () => {
            const body = Buffer.concat(chunks);
            resolve({
                url,
                status,
                contentType: response.headers["content-type"] || "",
                bytes: body.length,
                body: body.toString("utf8"),
            });
        });
    });
    request.on("error", (error) => {
        resolve({
            url,
            status: 0,
            contentType: error.message,
            bytes: 0,
            body: "",
        });
    });
    request.setTimeout(15000, () => {
        request.destroy(new Error("Timed out after 15000ms"));
    });
});

const stylesheetUrls = (html, pageUrl) => {
    const links = [];
    const linkPattern = /<link\b[^>]*href=["']([^"']+\.css[^"']*)["'][^>]*>/gi;
    let match;
    while ((match = linkPattern.exec(html)) !== null) {
        const href = match[1].replace(/&amp;/g, "&");
        if (href.includes("/local/moderncommerce/")) {
            links.push(new URL(href, pageUrl).toString());
        }
    }
    return [...new Set(links)];
};

const rows = [];
for (const route of routes) {
    const pageUrl = `${base}${route}`;
    const page = await fetchUrl(pageUrl);
    const cssLinks = stylesheetUrls(page.body, pageUrl);
    const cssAssets = [];
    for (const href of cssLinks) {
        const asset = await fetchUrl(href);
        cssAssets.push({
            href,
            status: asset.status,
            kb: Math.round(asset.bytes / 102.4) / 10,
        });
    }
    rows.push({
        route,
        status: page.status,
        pageKb: Math.round(page.bytes / 102.4) / 10,
        cssCount: cssAssets.length,
        cssKb: Math.round(cssAssets.reduce((sum, asset) => sum + asset.kb, 0) * 10) / 10,
        cssAssets,
    });
}

console.log(`Modern Commerce performance smoke: ${base}`);
console.log("==============================================");
for (const row of rows) {
    console.log(`${row.status.toString().padStart(3)} ${row.pageKb.toString().padStart(7)} KB page  ${row.cssCount} CSS / ${row.cssKb} KB  ${row.route}`);
    for (const asset of row.cssAssets) {
        const name = new URL(asset.href).pathname.replace("/local/moderncommerce/", "");
        console.log(`      ${asset.status} ${asset.kb.toString().padStart(7)} KB  ${name}`);
    }
}

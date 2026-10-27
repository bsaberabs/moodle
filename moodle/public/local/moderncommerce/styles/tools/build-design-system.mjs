#!/usr/bin/env node
/**
 * This file is part of Moodle and is licensed under the
 * GNU General Public License, version 3 or later.
 *
 * You may redistribute and modify it under the terms of the GPL.
 * See the plugin root LICENSE file for complete terms.
 *
 * Modern Commerce design-system compiler.
 *
 * @module     local_moderncommerce/build_design_system
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import sass from "sass";

const root = process.cwd();
const checkOnly = process.argv.includes("--check");
const pluginRootCandidate = path.join(root, "public/local/moderncommerce");
const moderncommerceRoot = fs.existsSync(path.join(root, "version.php"))
    && fs.existsSync(path.join(root, "styles/scss"))
    ? root
    : pluginRootCandidate;
const scssRoot = path.join(moderncommerceRoot, "styles/scss");
const stylesRoot = path.join(moderncommerceRoot, "styles");
const bundlesRoot = path.join(stylesRoot, "bundles");
const moodleHeader = `/**
 * This file is part of Moodle and is licensed under the
 * GNU General Public License, version 3 or later.
 *
 * You may redistribute and modify it under the terms of the GPL.
 * See the plugin root LICENSE file for complete terms.
 *
 * Compiled Modern Commerce styles.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
`;
const entries = [
    {
        label: "Modern Commerce core design system CSS",
        input: path.join(scssRoot, "moderncommerce-design-system.scss"),
        output: path.join(stylesRoot, "design-system.css"),
    },
    {
        label: "Modern Commerce admin CSS",
        input: path.join(scssRoot, "moderncommerce-admin.scss"),
        output: path.join(bundlesRoot, "admin.css"),
    },
    {
        label: "Modern Commerce learner CSS",
        input: path.join(scssRoot, "moderncommerce-learner.scss"),
        output: path.join(bundlesRoot, "learner.css"),
    },
    {
        label: "Modern Commerce storefront CSS",
        input: path.join(scssRoot, "moderncommerce-storefront.scss"),
        output: path.join(bundlesRoot, "storefront.css"),
    },
    {
        label: "Modern Commerce catalog CSS",
        input: path.join(scssRoot, "moderncommerce-catalog.scss"),
        output: path.join(bundlesRoot, "catalog.css"),
    },
    {
        label: "Modern Commerce public page CSS",
        input: path.join(scssRoot, "moderncommerce-public.scss"),
        output: path.join(bundlesRoot, "public.css"),
    },
    {
        label: "Modern Commerce course detail CSS",
        input: path.join(scssRoot, "moderncommerce-course-detail.scss"),
        output: path.join(bundlesRoot, "course-detail.css"),
    },
    {
        label: "Modern Commerce advanced features CSS",
        input: path.join(scssRoot, "moderncommerce-advanced-features.scss"),
        output: path.join(bundlesRoot, "advanced-features.css"),
    },
    {
        label: "Modern Commerce admin branding CSS",
        input: path.join(scssRoot, "moderncommerce-admin-branding.scss"),
        output: path.join(bundlesRoot, "admin-branding.css"),
    },
    {
        label: "Modern Commerce contact dashboard CSS",
        input: path.join(scssRoot, "moderncommerce-contact-dashboard.scss"),
        output: path.join(bundlesRoot, "contact-dashboard.css"),
    },
    {
        label: "Modern Commerce icon browser CSS",
        input: path.join(scssRoot, "moderncommerce-icon-browser.scss"),
        output: path.join(bundlesRoot, "icon-browser.css"),
    },
    {
        label: "Modern Commerce component showcase CSS",
        input: path.join(scssRoot, "moderncommerce-component-showcase.scss"),
        output: path.join(bundlesRoot, "component-showcase.css"),
    },
    {
        label: "Modern Commerce admin gallery CSS",
        input: path.join(scssRoot, "moderncommerce-admin-gallery.scss"),
        output: path.join(bundlesRoot, "admin-gallery.css"),
    },
    {
        label: "Modern Commerce admin help CSS",
        input: path.join(scssRoot, "moderncommerce-admin-help.scss"),
        output: path.join(bundlesRoot, "admin-help.css"),
    },
    {
        label: "Modern Commerce global CSS",
        input: path.join(scssRoot, "moderncommerce-global.scss"),
        output: path.join(moderncommerceRoot, "styles.css"),
    },
];
const oldNamespace = `c${"c"}p`;
const oldClassPrefix = `${oldNamespace}-`;
const oldTokenPrefix = `--${oldNamespace}`;
const oldButtonPrefix = `btn-${oldNamespace}`;

const assertFile = (file) => {
    if (!fs.existsSync(file)) {
        throw new Error(`Missing file: ${file}`);
    }
};

const validateCss = (css) => {
    const failures = [];

    if (css.includes(oldClassPrefix) || css.includes(oldTokenPrefix) || css.includes(oldButtonPrefix)) {
        failures.push("Compiled CSS contains the old design-system namespace.");
    }

    if (/--mc-([a-z0-9-]+):\s*var\(--mc-\1\)/i.test(css)) {
        failures.push("Compiled CSS contains a self-referential mc token.");
    }

    let balance = 0;
    let minBalance = 0;
    for (const char of css) {
        if (char === "{") {
            balance++;
        } else if (char === "}") {
            balance--;
            minBalance = Math.min(minBalance, balance);
        }
    }

    if (balance !== 0 || minBalance < 0) {
        failures.push(`Compiled CSS has imbalanced braces: balance=${balance}, min=${minBalance}.`);
    }

    if (failures.length > 0) {
        throw new Error(failures.join("\n"));
    }
};

const compileEntry = (entry) => {
    assertFile(entry.input);

    const result = sass.renderSync({
        file: entry.input,
        sourceMap: false,
        outputStyle: "expanded",
    });
    const css = moodleHeader + result.css.toString();

    validateCss(css);

    return css;
};

const compiled = entries.map((entry) => ({
    ...entry,
    css: compileEntry(entry),
}));

if (checkOnly) {
    for (const entry of compiled) {
        assertFile(entry.output);
        const current = fs.readFileSync(entry.output, "utf8");

        if (current !== entry.css) {
            throw new Error(`${path.relative(root, entry.output)} is not up to date. Run this script without --check.`);
        }
    }

    console.log("Modern Commerce generated CSS is up to date.");
    process.exit(0);
}

for (const entry of compiled) {
    fs.mkdirSync(path.dirname(entry.output), {recursive: true});
    fs.writeFileSync(entry.output, entry.css, "utf8");
    console.log(`${entry.label} written to ${path.relative(root, entry.output)}.`);
}

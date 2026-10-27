<?php
// This file is part of Moodle and is licensed under the
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Admin help documentation catalog.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\docs;

/**
 * Curates and renders packaged Modern Commerce documentation for the admin help center.
 */
class admin_help_catalog {
    /**
     * Documentation group metadata.
     *
     * @return array<int, array{id: string, title: string, icon: string}>
     */
    public static function groups(): array {
        return [
            ['id' => 'start', 'title' => 'Start here', 'icon' => 'bi-compass'],
            ['id' => 'commerce', 'title' => 'Commerce workflows', 'icon' => 'bi-bag-check'],
            ['id' => 'storefront', 'title' => 'Storefront design', 'icon' => 'bi-layout-text-window-reverse'],
            ['id' => 'operations', 'title' => 'Operations', 'icon' => 'bi-activity'],
            ['id' => 'release', 'title' => 'Release readiness', 'icon' => 'bi-box-seam'],
            ['id' => 'reference', 'title' => 'Reference', 'icon' => 'bi-journal-code'],
        ];
    }

    /**
     * Documentation files exposed in the admin help center.
     *
     * @return array<int, array{id: string, group: string, title: string, summary: string, icon: string, file: string}>
     */
    public static function documents(): array {
        return [
            [
                'id' => 'overview',
                'group' => 'start',
                'title' => 'Documentation overview',
                'summary' => 'Audience, standards, version target, and the documentation map.',
                'icon' => 'bi-journal-text',
                'file' => 'README.md',
            ],
            [
                'id' => 'installation',
                'group' => 'start',
                'title' => 'Installation',
                'summary' => 'Requirements, plugin install, Composer dependencies, cron, and first verification.',
                'icon' => 'bi-download',
                'file' => 'installation.md',
            ],
            [
                'id' => 'demo-role-logins',
                'group' => 'start',
                'title' => 'Demo role logins',
                'summary' => 'Preview usernames, password, role coverage, seeding command, and cleanup command.',
                'icon' => 'bi-person-badge',
                'file' => 'demo-role-logins.md',
            ],
            [
                'id' => 'role-access',
                'group' => 'start',
                'title' => 'Role access guide',
                'summary' => 'Role-by-role access matrix, extension steps, and custom role creation guidance.',
                'icon' => 'bi-shield-check',
                'file' => 'role-access.md',
            ],
            [
                'id' => 'first-run',
                'group' => 'start',
                'title' => 'First run and demo data',
                'summary' => 'Install defaults, seed data, audit, refresh, and safe reset workflows.',
                'icon' => 'bi-play-circle',
                'file' => 'first-run.md',
            ],
            [
                'id' => 'products-pricing',
                'group' => 'commerce',
                'title' => 'Products and pricing',
                'summary' => 'Course products, bundles, programs, inventory, price tiers, sale prices, and tax.',
                'icon' => 'bi-tags',
                'file' => 'products-and-pricing.md',
            ],
            [
                'id' => 'programs-learning-paths',
                'group' => 'commerce',
                'title' => 'Programs and learning paths',
                'summary' => 'Program access, prerequisites, completion policies, learner progress, conversion, and certificates.',
                'icon' => 'bi-signpost-split',
                'file' => 'programs-and-learning-paths.md',
            ],
            [
                'id' => 'payments',
                'group' => 'commerce',
                'title' => 'Payments',
                'summary' => 'Gateway setup, callbacks, webhooks, refunds, and payment troubleshooting checkpoints.',
                'icon' => 'bi-credit-card-2-front',
                'file' => 'payments.md',
            ],
            [
                'id' => 'orders-reports',
                'group' => 'commerce',
                'title' => 'Orders and reports',
                'summary' => 'Orders, invoices, refunds, payment events, reports, wishlists, and audit logs.',
                'icon' => 'bi-receipt',
                'file' => 'orders-and-reports.md',
            ],
            [
                'id' => 'coupons-keys',
                'group' => 'commerce',
                'title' => 'Coupons and enrolment keys',
                'summary' => 'Coupon targeting, usage limits, course keys, bundle keys, and redemption.',
                'icon' => 'bi-ticket-perforated',
                'file' => 'coupons-and-keys.md',
            ],
            [
                'id' => 'subscriptions',
                'group' => 'commerce',
                'title' => 'Subscriptions',
                'summary' => 'Plans, features, access rules, subscription keys, trials, renewals, and expiry tasks.',
                'icon' => 'bi-arrow-repeat',
                'file' => 'subscriptions.md',
            ],
            [
                'id' => 'storefront',
                'group' => 'storefront',
                'title' => 'Storefront pages',
                'summary' => 'Public pages, page zones, widgets, presets, media, and global storefront elements.',
                'icon' => 'bi-shop',
                'file' => 'storefront.md',
            ],
            [
                'id' => 'widgets',
                'group' => 'storefront',
                'title' => 'Storefront widgets',
                'summary' => 'Widget types, resolver data sources, lifecycle, editing notes, and developer hooks.',
                'icon' => 'bi-collection',
                'file' => 'reference/widgets.md',
            ],
            [
                'id' => 'notifications',
                'group' => 'operations',
                'title' => 'Notifications and email templates',
                'summary' => 'Email template library, placeholders, notification queue, contact messages, and delivery checks.',
                'icon' => 'bi-bell',
                'file' => 'notifications.md',
            ],
            [
                'id' => 'operations',
                'group' => 'operations',
                'title' => 'Operations',
                'summary' => 'Cron, cache, CSS builds, demo data, package checks, diagnostics, and support posture.',
                'icon' => 'bi-sliders',
                'file' => 'operations.md',
            ],
            [
                'id' => 'troubleshooting',
                'group' => 'operations',
                'title' => 'Troubleshooting',
                'summary' => 'Payments, webhooks, cron, entitlements, notifications, cached assets, and demo data.',
                'icon' => 'bi-life-preserver',
                'file' => 'troubleshooting.md',
            ],
            [
                'id' => 'upgrade-notes',
                'group' => 'release',
                'title' => 'Upgrade notes',
                'summary' => 'Compatibility, upgrade steps, post-upgrade verification, data notes, and rollback.',
                'icon' => 'bi-arrow-up-circle',
                'file' => 'upgrade-notes.md',
            ],
            [
                'id' => 'release-packaging',
                'group' => 'release',
                'title' => 'Release packaging',
                'summary' => 'Preflight checks, CSS policy, ZIP build, release automation, and archive inspection.',
                'icon' => 'bi-box-seam',
                'file' => 'release-packaging.md',
            ],
            [
                'id' => 'plugin-directory',
                'group' => 'release',
                'title' => 'Moodle Plugins Directory',
                'summary' => 'Metadata, packaging, code quality, security, database, task, and UX checklist.',
                'icon' => 'bi-patch-check',
                'file' => 'moodle-plugin-directory.md',
            ],
            [
                'id' => 'cli',
                'group' => 'reference',
                'title' => 'CLI reference',
                'summary' => 'Demo data, targeted seed commands, email test command, and inspection helpers.',
                'icon' => 'bi-terminal',
                'file' => 'reference/cli.md',
            ],
            [
                'id' => 'settings-reference',
                'group' => 'reference',
                'title' => 'Settings reference',
                'summary' => 'Admin settings groups and the operational impact of each configuration area.',
                'icon' => 'bi-gear',
                'file' => 'reference/settings.md',
            ],
            [
                'id' => 'capabilities-reference',
                'group' => 'reference',
                'title' => 'Capabilities reference',
                'summary' => 'System capabilities, default manager access, and capability ownership map.',
                'icon' => 'bi-shield-lock',
                'file' => 'reference/capabilities.md',
            ],
            [
                'id' => 'scheduled-tasks-reference',
                'group' => 'reference',
                'title' => 'Scheduled tasks reference',
                'summary' => 'Cron task names, responsibilities, and operational notes.',
                'icon' => 'bi-clock-history',
                'file' => 'reference/scheduled-tasks.md',
            ],
            [
                'id' => 'web-services-reference',
                'group' => 'reference',
                'title' => 'Web services reference',
                'summary' => 'AJAX/web service groups, public surface, admin surface, and security notes.',
                'icon' => 'bi-plug',
                'file' => 'reference/web-services.md',
            ],
            [
                'id' => 'database-reference',
                'group' => 'reference',
                'title' => 'Database reference',
                'summary' => 'Tables for products, orders, payments, storefront, notifications, and subscriptions.',
                'icon' => 'bi-database',
                'file' => 'reference/database.md',
            ],
        ];
    }

    /**
     * Render all exposed Markdown documents.
     *
     * @return array<int, array>
     */
    public static function rendered_documents(): array {
        $documents = self::documents();
        $pathindex = [];

        foreach ($documents as $document) {
            $pathindex[self::normalise_path($document['file'])] = $document['id'];
        }

        $rendered = [];
        foreach ($documents as $document) {
            $file = self::normalise_path($document['file']);
            if (!self::markdown_exists($file)) {
                continue;
            }
            $markdown = self::read_markdown($file);
            $markdown = self::rewrite_markdown_links($markdown, $file, $pathindex);
            $html = format_text($markdown, FORMAT_MARKDOWN, [
                'context' => \context_system::instance(),
                'trusted' => false,
                'noclean' => false,
                'filter' => false,
            ]);

            $rendered[] = $document + [
                'html' => $html,
                'sourceurl' => (new \moodle_url('/local/moderncommerce/docs/' . $file))->out(false),
                'searchtext' => self::search_text($document, $html),
            ];
        }

        return $rendered;
    }

    /**
     * Read a whitelisted Markdown file.
     *
     * @param string $file Normalised docs-relative file path.
     * @return string
     */
    private static function read_markdown(string $file): string {
        global $CFG;

        $path = $CFG->dirroot . '/local/moderncommerce/docs/' . $file;
        if (!is_readable($path)) {
            throw new \moodle_exception('filenotfound', 'error', '', $file);
        }

        return (string) file_get_contents($path);
    }

    /**
     * Check whether a whitelisted Markdown file is available.
     *
     * @param string $file Normalised docs-relative file path.
     * @return bool
     */
    private static function markdown_exists(string $file): bool {
        global $CFG;

        $path = $CFG->dirroot . '/local/moderncommerce/docs/' . $file;

        return is_readable($path);
    }

    /**
     * Rewrite local Markdown links to in-app help-center hash links.
     *
     * @param string $markdown Markdown source.
     * @param string $sourcefile Docs-relative source file.
     * @param array<string, string> $pathindex Map of docs-relative file to help document id.
     * @return string
     */
    private static function rewrite_markdown_links(string $markdown, string $sourcefile, array $pathindex): string {
        return (string) preg_replace_callback(
            '/\[(?<label>[^\]]+)\]\((?<target>[^)]+)\)/',
            static function (array $matches) use ($sourcefile, $pathindex): string {
                $target = trim((string) $matches['target']);
                if (
                    $target === '' ||
                    $target[0] === '#' ||
                    preg_match('/^(https?:|mailto:|tel:)/i', $target)
                ) {
                    return $matches[0];
                }

                [$targetpath] = explode('#', $target, 2);
                if (!preg_match('/\.md$/i', $targetpath)) {
                    return $matches[0];
                }

                $sourcebase = dirname($sourcefile);
                $candidate = ($sourcebase === '.' ? '' : $sourcebase . '/') . rawurldecode($targetpath);
                $resolved = self::normalise_path($candidate);
                if (!isset($pathindex[$resolved])) {
                    return $matches[0];
                }

                return '[' . $matches['label'] . '](#' . $pathindex[$resolved] . ')';
            },
            $markdown
        );
    }

    /**
     * Normalize a docs-relative path without allowing traversal outside docs.
     *
     * @param string $path Raw path.
     * @return string
     */
    private static function normalise_path(string $path): string {
        $path = str_replace('\\', '/', $path);
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * Build normalized search text for a rendered document.
     *
     * @param array $document Document metadata.
     * @param string $html Rendered HTML.
     * @return string
     */
    private static function search_text(array $document, string $html): string {
        $text = implode(' ', [
            (string) $document['title'],
            (string) $document['summary'],
            (string) $document['group'],
            strip_tags($html),
        ]);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return \core_text::strtolower(trim($text));
    }
}

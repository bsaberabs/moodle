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
 * Canonical registry of storefront page zones.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront;

/**
 * Defines the page types and the zone slugs each page exposes.
 *
 * Mirror of the zone slugs the React storefront anchors render. Keeping the list
 * here means new zones are declared in one place for both PHP and the bundle.
 */
class zones {
    /** @var string The Modern Commerce catalog / storefront home page. */
    const PAGE_CATALOG = 'catalog';
    /** @var string Public About / Trust page. */
    const PAGE_ABOUT = 'about';
    /** @var string Public Support page. */
    const PAGE_SUPPORT = 'support';
    /** @var string Public Terms page. */
    const PAGE_TERMS = 'terms';
    /** @var string Public Privacy page. */
    const PAGE_PRIVACY = 'privacy';
    /** @var string Public Refund / Cancellation Policy page. */
    const PAGE_REFUND = 'refund-policy';
    /**
     * @var string Site-wide scope. Widgets placed here render on EVERY storefront page
     * (e.g. a global footer). Not a navigable page of its own - it is merged into each
     * real page's render by page_builder.
     */
    const PAGE_GLOBAL = 'global';

    // Catalog page zones (top to bottom).
    /** @var string Sitewide announcement / countdown band. */
    const HOME_ANNOUNCE = 'home_announce';
    /** @var string Hero slider band, above the catalog search hero. */
    const HOME_HERO = 'home_hero';
    /** @var string Trust badge strip below the hero. */
    const HOME_BELOWHERO = 'home_belowhero';
    /** @var string Featured / recommended band before the catalog grid. */
    const HOME_FEATURED = 'home_featured';
    /** @var string The core catalog grid + filters surface. */
    const CATALOG_MAIN = 'catalog_main';
    /** @var string Promo slot inside the catalog filter sidebar. */
    const CATALOG_SIDEBAR = 'catalog_sidebar';
    /** @var string Interstitial slot inside the catalog grid. */
    const CATALOG_INGRID = 'catalog_ingrid';
    /** @var string Testimonials / instructors band after the grid. */
    const HOME_SOCIAL = 'home_social';
    /** @var string Newsletter / lead capture band at the foot of the page. */
    const HOME_FOOTER = 'home_footer';

    // Public content page zones (top to bottom).
    /** @var string Page hero / opening offer. */
    const PAGE_HERO = 'page_hero';
    /** @var string Primary page content. */
    const PAGE_MAIN = 'page_main';
    /** @var string Supporting side content. */
    const PAGE_ASIDE = 'page_aside';
    /** @var string Final page CTA / footer content. */
    const PAGE_FOOTER = 'page_footer';

    // Global (site-wide) zones - injected at the top and bottom of every page's render.
    /** @var string Site-wide band rendered above every page's own zones. */
    const GLOBAL_TOP = 'global_top';
    /** @var string Site-wide band rendered below every page's own zones (e.g. a footer). */
    const GLOBAL_BOTTOM = 'global_bottom';

    /**
     * Zones exposed by the 'content' page template, in render order.
     *
     * @return string[]
     */
    public static function content_zones(): array {
        return [
            self::PAGE_HERO,
            self::PAGE_MAIN,
            self::PAGE_ASIDE,
            self::PAGE_FOOTER,
        ];
    }

    /**
     * Zones exposed by the 'landing' page template, in render order.
     *
     * Deliberately omits CATALOG_MAIN, CATALOG_SIDEBAR and CATALOG_INGRID: those are
     * the real catalog grid surface and its slots, and would render a duplicate
     * product grid on a page that is not the catalog.
     *
     * @return string[]
     */
    public static function landing_zones(): array {
        return [
            self::HOME_ANNOUNCE,
            self::HOME_HERO,
            self::HOME_BELOWHERO,
            self::HOME_FEATURED,
            self::HOME_SOCIAL,
            self::HOME_FOOTER,
        ];
    }

    /**
     * Zone slugs available for a given page type, in render order.
     *
     * Built-in pages resolve from the static map. Anything else is looked up as an
     * admin-created custom page and resolves to its template's zone set.
     *
     * @param string $pagetype Page type.
     * @return string[]
     */
    public static function for_page(string $pagetype): array {
        $catalogzones = [
            self::HOME_ANNOUNCE,
            self::HOME_HERO,
            self::HOME_BELOWHERO,
            self::HOME_FEATURED,
            self::CATALOG_MAIN,
            self::CATALOG_SIDEBAR,
            self::CATALOG_INGRID,
            self::HOME_SOCIAL,
            self::HOME_FOOTER,
        ];
        $publicpagezones = self::content_zones();
        $globalzones = [
            self::GLOBAL_TOP,
            self::GLOBAL_BOTTOM,
        ];

        $map = [
            self::PAGE_CATALOG => $catalogzones,
            self::PAGE_ABOUT => $publicpagezones,
            self::PAGE_SUPPORT => $publicpagezones,
            self::PAGE_TERMS => $publicpagezones,
            self::PAGE_PRIVACY => $publicpagezones,
            self::PAGE_REFUND => $publicpagezones,
            self::PAGE_GLOBAL => $globalzones,
        ];

        if (isset($map[$pagetype])) {
            return $map[$pagetype];
        }

        $custom = storepage_service::by_slug($pagetype);
        if ($custom === null) {
            return [];
        }

        return (string)$custom->template === storepage_service::TEMPLATE_LANDING
            ? self::landing_zones()
            : self::content_zones();
    }

    /**
     * Every zone slug known to the storefront, across all templates.
     *
     * @return string[]
     */
    public static function all_zones(): array {
        return array_values(array_unique(array_merge(
            self::for_page(self::PAGE_CATALOG),
            self::content_zones(),
            self::landing_zones(),
            [self::GLOBAL_TOP, self::GLOBAL_BOTTOM]
        )));
    }

    /**
     * The widget scopes that ship with the plugin.
     *
     * Kept separate from pages() so callers that must not touch admin-created pages -
     * notably seed::reset_widgets(), which deletes every widget it matches - stay
     * scoped to the built-ins.
     *
     * @return string[]
     */
    public static function builtin_pages(): array {
        return [
            self::PAGE_CATALOG,
            self::PAGE_ABOUT,
            self::PAGE_SUPPORT,
            self::PAGE_TERMS,
            self::PAGE_PRIVACY,
            self::PAGE_REFUND,
            self::PAGE_GLOBAL,
        ];
    }

    /**
     * All known widget scopes (the navigable pages plus the site-wide global scope).
     *
     * Used by is_page() to validate the editor/builder `page` parameter. The global
     * scope is NOT a navigable page - it is merged into every real page's render.
     * Includes admin-created custom pages.
     *
     * @return string[]
     */
    public static function pages(): array {
        return array_merge(self::builtin_pages(), storepage_service::slugs());
    }

    /**
     * Whether a page type is known.
     *
     * @param string $pagetype Page type.
     * @return bool
     */
    public static function is_page(string $pagetype): bool {
        return in_array($pagetype, self::pages(), true);
    }

    /**
     * Human-readable label for a zone slug.
     *
     * @param string $slug Zone slug.
     * @return string
     */
    public static function zone_label(string $slug): string {
        $key = 'widget_zone_' . $slug;
        if (get_string_manager()->string_exists($key, 'local_moderncommerce')) {
            return get_string($key, 'local_moderncommerce');
        }
        return ucwords(str_replace('_', ' ', $slug));
    }

    /**
     * Human-readable label for a page type.
     *
     * @param string $pagetype Page type.
     * @return string
     */
    public static function page_label(string $pagetype): string {
        $key = 'widget_pagetype_' . $pagetype;
        if (get_string_manager()->string_exists($key, 'local_moderncommerce')) {
            return get_string($key, 'local_moderncommerce');
        }

        $custom = storepage_service::by_slug($pagetype);
        if ($custom !== null) {
            return format_string($custom->title);
        }

        return ucwords($pagetype);
    }
}

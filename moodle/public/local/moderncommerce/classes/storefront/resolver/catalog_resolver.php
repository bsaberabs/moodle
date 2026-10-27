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
 * Resolver for the core catalog grid widget.
 *
 * Emits the configuration the React catalog app needs to fetch and render the
 * product grid, merging the placed widget's own settings over the store-wide
 * catalog display defaults from commerce_settings_service.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\style_controls;

/**
 * Builds the catalog grid configuration payload (labels are supplied in React).
 *
 * The placed widget's own settings (edited from the storefront side panel) are the
 * single source of truth for catalog display; there is no longer a store-wide
 * fallback in commerce settings.
 */
class catalog_resolver implements widget_resolver {
    /**
     * Build the catalog configuration.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (unused).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();

        // A category tile deep-links as ?categoryid=N. The page passes it through the render
        // context, since this resolver runs inside a web service call and cannot read the
        // storefront's own query string.
        $urlcategoryid = max(0, (int)($context['categoryid'] ?? 0));

        // Title: widget setting, then the generic catalog label.
        $title = trim((string)($settings['title'] ?? ''));
        if ($title === '') {
            $title = get_string('catalog', 'local_moderncommerce');
        }

        // Items per page: widget setting (fallback 12).
        $perpage = (int)($settings['perpage'] ?? 0);
        if ($perpage <= 0) {
            $perpage = 12;
        }

        // Sidebar position: widget setting (fallback left).
        $sidebarposition = (string)($settings['sidebarposition'] ?? '');
        if (!in_array($sidebarposition, ['left', 'right'], true)) {
            $sidebarposition = 'left';
        }

        // Background colour: widget setting (raw; React sanitises).
        $bgcolor = (string)($settings['bgcolor'] ?? '');

        // Margins: widget settings.
        $margintop = max(0, (int)($settings['margintop'] ?? 0));
        $marginbottom = max(0, (int)($settings['marginbottom'] ?? 0));
        $paddingtop = max(0, (int)($settings['paddingtop'] ?? 0));
        $paddingbottom = max(0, (int)($settings['paddingbottom'] ?? 0));
        $paddingleft = max(0, (int)($settings['paddingleft'] ?? 0));
        $paddingright = max(0, (int)($settings['paddingright'] ?? 0));

        return [
            'method' => 'local_moderncommerce_get_catalog',
            'cartmethod' => 'local_moderncommerce_update_cart',
            'wishlistmethod' => 'local_moderncommerce_update_learner_wishlist',
            'displaysettings' => [
                'title' => $title,
                'showheader' => self::truthy($settings['showheader'] ?? true),
                'perpage' => $perpage,
                'sidebarposition' => $sidebarposition,
                'bgcolor' => style_controls::colour($bgcolor),
                'herobgcolor' => style_controls::colour($settings['herobgcolor'] ?? ''),
                'herobordercolor' => style_controls::colour($settings['herobordercolor'] ?? ''),
                'heroradius' => style_controls::number($settings['heroradius'] ?? 8, 8, 0, 96),
                'eyebrowcolor' => style_controls::colour($settings['eyebrowcolor'] ?? ''),
                'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
                'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
                'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
                'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
                'accentcolor' => style_controls::colour($settings['accentcolor'] ?? ''),
                'heropanelbgcolor' => style_controls::colour($settings['heropanelbgcolor'] ?? ''),
                'heropanelbordercolor' => style_controls::colour($settings['heropanelbordercolor'] ?? ''),
                'heropaneltextcolor' => style_controls::colour($settings['heropaneltextcolor'] ?? ''),
                'heropanelaccentcolor' => style_controls::colour($settings['heropanelaccentcolor'] ?? ''),
                'heropanelvaluecolor' => style_controls::colour($settings['heropanelvaluecolor'] ?? ''),
                'heropanelvaluefontsize' => style_controls::number($settings['heropanelvaluefontsize'] ?? 0, 0, 0, 96),
                'cardbgcolor' => style_controls::colour($settings['cardbgcolor'] ?? ''),
                'cardbordercolor' => style_controls::colour($settings['cardbordercolor'] ?? ''),
                'cardborderwidth' => style_controls::number($settings['cardborderwidth'] ?? 1, 1, 0, 24),
                'cardradius' => style_controls::number($settings['cardradius'] ?? 8, 8, 0, 96),
                'cardfooterbgcolor' => style_controls::colour($settings['cardfooterbgcolor'] ?? ''),
                'cardtitlecolor' => style_controls::colour($settings['cardtitlecolor'] ?? ''),
                'cardtitlefontsize' => style_controls::number($settings['cardtitlefontsize'] ?? 0, 0, 0, 96),
                'cardtextcolor' => style_controls::colour($settings['cardtextcolor'] ?? ''),
                'cardmetabgcolor' => style_controls::colour($settings['cardmetabgcolor'] ?? ''),
                'cardmetatextcolor' => style_controls::colour($settings['cardmetatextcolor'] ?? ''),
                'ratingcolor' => style_controls::colour($settings['ratingcolor'] ?? ''),
                'ratingtextcolor' => style_controls::colour($settings['ratingtextcolor'] ?? ''),
                'originalpricecolor' => style_controls::colour($settings['originalpricecolor'] ?? ''),
                'buttoncolor' => style_controls::colour($settings['buttoncolor'] ?? ''),
                'buttontextcolor' => style_controls::colour($settings['buttontextcolor'] ?? ''),
                'buttonradius' => style_controls::number($settings['buttonradius'] ?? 0, 0, 0, 96),
                'badgebgcolor' => style_controls::colour($settings['badgebgcolor'] ?? ''),
                'badgebordercolor' => style_controls::colour($settings['badgebordercolor'] ?? ''),
                'badgetextcolor' => style_controls::colour($settings['badgetextcolor'] ?? ''),
                'badgeradius' => style_controls::number($settings['badgeradius'] ?? 6, 6, 0, 96),
                'badgefontsize' => style_controls::number($settings['badgefontsize'] ?? 0, 0, 0, 48),
                'coursebadgebgcolor' => style_controls::colour($settings['coursebadgebgcolor'] ?? ''),
                'coursebadgebordercolor' => style_controls::colour($settings['coursebadgebordercolor'] ?? ''),
                'coursebadgetextcolor' => style_controls::colour($settings['coursebadgetextcolor'] ?? ''),
                'programbadgebgcolor' => style_controls::colour($settings['programbadgebgcolor'] ?? ''),
                'programbadgebordercolor' => style_controls::colour($settings['programbadgebordercolor'] ?? ''),
                'programbadgetextcolor' => style_controls::colour($settings['programbadgetextcolor'] ?? ''),
                'bundlebadgebgcolor' => style_controls::colour($settings['bundlebadgebgcolor'] ?? ''),
                'bundlebadgebordercolor' => style_controls::colour($settings['bundlebadgebordercolor'] ?? ''),
                'bundlebadgetextcolor' => style_controls::colour($settings['bundlebadgetextcolor'] ?? ''),
                'filterbgcolor' => style_controls::colour($settings['filterbgcolor'] ?? ''),
                'filterbordercolor' => style_controls::colour($settings['filterbordercolor'] ?? ''),
                'filterborderwidth' => style_controls::number($settings['filterborderwidth'] ?? 1, 1, 0, 24),
                'filterradius' => style_controls::number($settings['filterradius'] ?? 8, 8, 0, 96),
                'filtertitlecolor' => style_controls::colour($settings['filtertitlecolor'] ?? ''),
                'filtertextcolor' => style_controls::colour($settings['filtertextcolor'] ?? ''),
                'inputbgcolor' => style_controls::colour($settings['inputbgcolor'] ?? ''),
                'inputbordercolor' => style_controls::colour($settings['inputbordercolor'] ?? ''),
                'inputtextcolor' => style_controls::colour($settings['inputtextcolor'] ?? ''),
                'placeholdercolor' => style_controls::colour($settings['placeholdercolor'] ?? ''),
                'tabbgcolor' => style_controls::colour($settings['tabbgcolor'] ?? ''),
                'tabbordercolor' => style_controls::colour($settings['tabbordercolor'] ?? ''),
                'tabtextcolor' => style_controls::colour($settings['tabtextcolor'] ?? ''),
                'tabactivebgcolor' => style_controls::colour($settings['tabactivebgcolor'] ?? ''),
                'tabactivetextcolor' => style_controls::colour($settings['tabactivetextcolor'] ?? ''),
                'pricecolor' => style_controls::colour($settings['pricecolor'] ?? ''),
                'paddingtop' => $paddingtop,
                'paddingbottom' => $paddingbottom,
                'paddingleft' => $paddingleft,
                'paddingright' => $paddingright,
                'margintop' => $margintop,
                'marginbottom' => $marginbottom,
            ],
            'initialfilters' => [
                'search' => '',
                'coursetype' => (string)($settings['coursetype'] ?? ''),
                // A category tile links in as ?categoryid=N, so the URL beats the widget's own
                // configured category. The client re-reads the query string on mount as well.
                'categoryid' => $urlcategoryid > 0
                    ? $urlcategoryid
                    : max(0, (int)($settings['categoryid'] ?? 0)),
                'level' => '',
                'minprice' => 0,
                'maxprice' => 0,
                'sort' => 'popular',
                'page' => 0,
                'perpage' => $perpage,
            ],
        ];
    }

    /**
     * Interpret a stored checkbox setting as a boolean.
     *
     * @param mixed $value Raw setting value.
     * @return bool
     */
    private static function truthy($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return $value !== '' && $value !== '0' && strtolower($value) !== 'false';
        }

        return !empty($value);
    }
}

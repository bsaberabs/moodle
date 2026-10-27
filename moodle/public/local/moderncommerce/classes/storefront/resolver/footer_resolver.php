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
 * Resolver for the site-wide storefront footer widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use context_system;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\logo_source;
use local_moderncommerce\storefront\style_controls;
use moodle_url;

/**
 * Builds the footer widget payload (brand/contact column, link columns, app badges, social, copyright).
 */
class footer_resolver implements widget_resolver {
    /**
     * Build the footer payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context.
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        global $SITE;

        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();

        $copyright = strtr((string) ($settings['copyright'] ?? '© {year} {sitename}'), [
            '{year}' => date('Y'),
            '{sitename}' => format_string($SITE->fullname),
        ]);

        // Logo: resolve the chosen source (active theme logo / core Moodle logo /
        // a specific theme logo), falling back to the widget's own upload.
        $logosource = (string) ($settings['logosource'] ?? 'theme');
        $logo = logo_source::resolve($logosource);
        if ($logo === '') {
            $logo = self::url((string) ($settings['logo'] ?? ''));
        }

        return [
            'style' => self::style((string) ($settings['style'] ?? 'default')),
            'mode' => ($settings['mode'] ?? 'light') === 'dark' ? 'dark' : 'light',
            'logo' => $logo,
            'logoheight' => max(0, (int) ($settings['logoheight'] ?? 42)),
            'bgcolor' => style_controls::colour($settings['bgcolor'] ?? ''),
            'panelbgcolor' => style_controls::colour($settings['panelbgcolor'] ?? ''),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'linkcolor' => style_controls::colour($settings['linkcolor'] ?? ''),
            'iconbgcolor' => style_controls::colour($settings['iconbgcolor'] ?? ''),
            'iconcolor' => style_controls::colour($settings['iconcolor'] ?? ''),
            'inputbgcolor' => style_controls::colour($settings['inputbgcolor'] ?? ''),
            'inputbordercolor' => style_controls::colour($settings['inputbordercolor'] ?? ''),
            'inputtextcolor' => style_controls::colour($settings['inputtextcolor'] ?? ''),
            'buttoncolor' => style_controls::colour($settings['buttoncolor'] ?? ''),
            'buttontextcolor' => style_controls::colour($settings['buttontextcolor'] ?? ''),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 0),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 0),
            'brandname' => self::clean((string) ($settings['brandname'] ?? ''), $ctx),
            'description' => self::clean((string) ($settings['description']
                ?? get_string('p1_footer_description', 'local_moderncommerce')), $ctx),
            'address' => self::lines((string) ($settings['address'] ?? ''), $ctx),
            'phone' => self::clean((string) ($settings['phone'] ?? ''), $ctx),
            'email' => self::clean((string) ($settings['email'] ?? ''), $ctx),
            'languagelabel' => self::clean((string) ($settings['languagelabel']
                ?? get_string('p1_footer_language_english', 'local_moderncommerce')), $ctx),
            'subscribeplaceholder' => self::clean((string) ($settings['subscribeplaceholder']
                ?? get_string('p1_footer_subscribeplaceholder', 'local_moderncommerce')), $ctx),
            'compliancelabel' => self::clean((string) ($settings['compliancelabel']
                ?? get_string('p1_footer_compliancelabel', 'local_moderncommerce')), $ctx),
            'columns' => self::columns((array) ($settings['columns'] ?? []), $ctx),
            'appstitle' => self::clean((string) ($settings['appstitle'] ?? ''), $ctx),
            'googleplayurl' => self::url((string) ($settings['googleplayurl'] ?? '')),
            'appstoreurl' => self::url((string) ($settings['appstoreurl'] ?? '')),
            'social' => self::social((array) ($settings['social'] ?? []), $ctx),
            'copyright' => $copyright,
            'labels' => self::labels(),
        ];
    }

    /**
     * Restrict footer style to supported layouts.
     *
     * @param string $style Raw style.
     * @return string
     */
    private static function style(string $style): string {
        $style = strtolower(trim($style));
        return in_array($style, ['default', 'modern-classical', 'enterprise-navy'], true) ? $style : 'default';
    }

    /**
     * Build the link columns, parsing each column's "Label | URL" textarea into links.
     *
     * @param array $rows Raw column rows.
     * @param \context $ctx Context.
     * @return array
     */
    private static function columns(array $rows, \context $ctx): array {
        $columns = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = self::clean((string) ($row['title'] ?? ''), $ctx);
            $links = self::parse_links((string) ($row['links'] ?? ''), $ctx);
            if ($title === '' && empty($links)) {
                continue;
            }
            $columns[] = ['title' => $title, 'links' => $links];
        }
        return $columns;
    }

    /**
     * Parse a "Label | URL" per-line textarea into a list of link descriptors.
     *
     * @param string $raw Raw textarea value.
     * @param \context $ctx Context.
     * @return array
     */
    private static function parse_links(string $raw, \context $ctx): array {
        $links = [];
        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line, 2);
            $label = self::clean(trim($parts[0]), $ctx);
            if ($label === '') {
                continue;
            }
            $links[] = [
                'label' => $label,
                'url' => self::url(isset($parts[1]) ? trim($parts[1]) : '#'),
            ];
        }
        return $links;
    }

    /**
     * Build the social link list.
     *
     * @param array $rows Raw social rows.
     * @param \context $ctx Context.
     * @return array
     */
    private static function social(array $rows, \context $ctx): array {
        $social = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $url = self::url((string) ($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $social[] = [
                'icon' => self::icon((string) ($row['icon'] ?? 'link-45deg')),
                'url' => $url,
                'label' => self::clean((string) ($row['label'] ?? ''), $ctx),
            ];
        }
        return $social;
    }

    /**
     * Split a textarea into trimmed, non-empty, cleaned lines.
     *
     * @param string $raw Raw textarea value.
     * @param \context $ctx Context.
     * @return string[]
     */
    private static function lines(string $raw, \context $ctx): array {
        $lines = [];
        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $lines[] = self::clean($line, $ctx);
            }
        }
        return $lines;
    }

    /**
     * Clean display text.
     *
     * @param string $value Raw text.
     * @param \context $ctx Context.
     * @return string
     */
    private static function clean(string $value, \context $ctx): string {
        return format_string(trim($value), true, ['context' => $ctx, 'escape' => false]);
    }

    /**
     * Normalise a Bootstrap icon value (strip a leading "bi-"/"bi ").
     *
     * @param string $value Raw icon.
     * @return string
     */
    private static function icon(string $value): string {
        $icon = preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', trim($value)));
        return $icon !== '' ? $icon : 'link-45deg';
    }

    /**
     * Normalise URLs (leave external URLs intact, resolve site-relative ones).
     *
     * @param string $value Raw URL.
     * @return string
     */
    private static function url(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (
            preg_match('#^(https?:)?//#i', $value) || $value === '#' || str_starts_with($value, 'mailto:')
                || str_starts_with($value, 'tel:')
        ) {
            return $value;
        }
        if ($value[0] === '/') {
            return (new moodle_url($value))->out(false);
        }
        return $value;
    }

    /**
     * Labels used by fallback footer content in React.
     *
     * @return array<string, string>
     */
    private static function labels(): array {
        $keys = [
            'aboutstore',
            'bestsellingcourses',
            'blog',
            'bundlesandoffers',
            'careers',
            'certificates',
            'company',
            'contact',
            'contactsupport',
            'cookiepreferences',
            'corporatetraining',
            'coursemarketplace',
            'emailaddress',
            'emailsubscription',
            'events',
            'getitnow',
            'helparticles',
            'helpportal',
            'learningguides',
            'logo',
            'newprogrammes',
            'nowavailable',
            'partners',
            'platform',
            'popularlinks',
            'privacypolicy',
            'programmecatalog',
            'refundpolicy',
            'resources',
            'resourcelibrary',
            'scholarships',
            'security',
            'sitemap',
            'socialmedia',
            'subscribe',
            'subscribeplaceholder',
            'successstories',
            'support',
            'teamlearning',
            'termsofuse',
            'webinars',
        ];
        $labels = [];
        foreach ($keys as $key) {
            $labels[$key] = get_string('p1_footer_' . $key, 'local_moderncommerce');
        }

        return $labels;
    }
}

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
 * Builds a synthetic "every widget in every style" showcase for the admin gallery.
 *
 * The gallery reuses the exact same React storefront renderer the public pages use.
 * Rather than read placed widgets from the database, this builder synthesises one
 * in-memory {@see widget} instance per widget type per style variant, feeds it through
 * the real resolver (so the render payload shape always matches the live contract), and
 * returns the standard zone/widget envelope the React app already understands.
 *
 * Data-driven widgets (slider slides, category tiles, product carousels/catalog) cannot
 * be resolved from synthetic settings alone, so they are populated with fixed demo data:
 * slider/categories are built directly, and product widgets point at the demo catalog
 * web service instead of the live one.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront;

use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\resolver\belief_resolver;
use local_moderncommerce\storefront\resolver\breadcrumb_resolver;
use local_moderncommerce\storefront\resolver\catalog_resolver;
use local_moderncommerce\storefront\resolver\contactcards_resolver;
use local_moderncommerce\storefront\resolver\content_resolver;
use local_moderncommerce\storefront\resolver\countdown_resolver;
use local_moderncommerce\storefront\resolver\cta_resolver;
use local_moderncommerce\storefront\resolver\faq_resolver;
use local_moderncommerce\storefront\resolver\featured_resolver;
use local_moderncommerce\storefront\resolver\featuregrid_resolver;
use local_moderncommerce\storefront\resolver\footer_resolver;
use local_moderncommerce\storefront\resolver\instructors_resolver;
use local_moderncommerce\storefront\resolver\leadermessage_resolver;
use local_moderncommerce\storefront\resolver\learningpromise_resolver;
use local_moderncommerce\storefront\resolver\mediastorycarousel_resolver;
use local_moderncommerce\storefront\resolver\newsletter_resolver;
use local_moderncommerce\storefront\resolver\partners_resolver;
use local_moderncommerce\storefront\resolver\policy_resolver;
use local_moderncommerce\storefront\resolver\subscriptionplans_resolver;
use local_moderncommerce\storefront\resolver\supportform_resolver;
use local_moderncommerce\storefront\resolver\testimonials_resolver;
use local_moderncommerce\storefront\resolver\trustbadges_resolver;
use local_moderncommerce\storefront\resolver\videohero_resolver;

/**
 * Synthesises the admin widget gallery (every widget type, every style) with demo data.
 */
class gallery_builder {
    /** @var string Web service the gallery's product widgets fetch demo items from. */
    private const DEMO_CATALOG_METHOD = 'local_moderncommerce_get_demo_catalog';

    /** @var string Pagetype/zone slug the synthetic instances are tagged with. */
    private const SCOPE = 'gallery';

    /**
     * Ordered list of widget types shown in the gallery.
     *
     * @return string[]
     */
    public static function order(): array {
        return [
            'slider', 'videohero', 'breadcrumb', 'featured', 'related', 'categories',
            'trustbadges', 'partners', 'countdown', 'testimonials', 'instructors', 'newsletter',
            'content', 'mediastorycarousel', 'leadermessage', 'featuregrid', 'subscriptionplans',
            'learningpromise', 'belief', 'policy',
            'faq', 'cta', 'supportform', 'contactcards', 'footer', 'catalog',
        ];
    }

    /**
     * Lightweight per-type metadata for the gallery page (headings + jump nav).
     *
     * @return array<int, array{type: string, label: string, anchor: string, stylelabels: string[]}>
     */
    public static function showcase(): array {
        $specs = self::specs();
        $out = [];
        foreach (self::order() as $type) {
            if (empty($specs[$type])) {
                continue;
            }
            $stylelabels = [];
            foreach ($specs[$type] as $variant) {
                $stylelabels[] = (string)($variant['stylelabel'] ?? '');
            }
            $stylelabels = array_values(array_filter($stylelabels));
            $out[] = [
                'type' => $type,
                'label' => widget_types::label($type),
                'anchor' => 'mcg-' . $type,
                'stylelabels' => $stylelabels,
                'hasstyles' => count($stylelabels) > 1,
                'stylesummary' => implode(' · ', $stylelabels),
            ];
        }
        return $out;
    }

    /**
     * Build the resolved gallery zones for one widget type (or all when empty).
     *
     * Mirrors {@see page_builder::build()} output: a list of
     * ['slug' => string, 'widgets' => array[]]. One zone per widget type, each holding
     * one widget envelope per style variant in display order.
     *
     * @param string $type Single widget type to build, or '' for every type.
     * @return array
     */
    public static function build(string $type = ''): array {
        $specs = self::specs();
        $types = $type !== '' ? [$type] : self::order();

        $result = [];
        $id = 1;
        foreach ($types as $current) {
            if (empty($specs[$current])) {
                continue;
            }
            $widgets = [];
            foreach ($specs[$current] as $variant) {
                $widgets[] = self::envelope($id++, $current, $variant);
            }
            if (!empty($widgets)) {
                $result[] = ['slug' => $current, 'widgets' => $widgets];
            }
        }

        return $result;
    }

    /**
     * Turn one variant spec into the standard React widget envelope.
     *
     * @param int $id Synthetic widget id (stable within a single response).
     * @param string $type Widget type key.
     * @param array $variant Variant spec from {@see specs()}.
     * @return array
     */
    private static function envelope(int $id, string $type, array $variant): array {
        $title = (string)($variant['title'] ?? '');
        $subtitle = (string)($variant['subtitle'] ?? '');
        $settings = (array)($variant['settings'] ?? []);

        if (array_key_exists('direct', $variant) && is_array($variant['direct'])) {
            // Data-driven widget the resolver cannot synthesise (slider, categories): use fixed demo data.
            $payload = $variant['direct'];
        } else {
            $payload = self::resolve($type, $id, $title, $subtitle, $settings, (array)($variant['context'] ?? []));
        }

        // Product widgets fetch live items client-side; redirect them at the demo catalog service.
        if (in_array($type, ['featured', 'related', 'catalog'], true) && isset($payload['method'])) {
            $payload['method'] = self::DEMO_CATALOG_METHOD;
        }

        return [
            'id' => $id,
            'type' => $type,
            'sortorder' => $id,
            'title' => $title,
            'subtitle' => $subtitle,
            'settings' => $settings !== [] ? json_encode($settings) : '{}',
            'styleconfig' => preset_service::encode_object((array)($variant['styleconfig'] ?? [])),
            'data' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'bg' => (string)($variant['bg'] ?? ''),
            'spacingtop' => (int)($variant['spacingtop'] ?? 0),
            'spacingbottom' => (int)($variant['spacingbottom'] ?? 0),
        ];
    }

    /**
     * Resolve a synthetic widget instance through its real resolver.
     *
     * @param string $type Widget type key.
     * @param int $id Synthetic id.
     * @param string $title Widget title.
     * @param string $subtitle Widget subtitle.
     * @param array $settings Demo settings.
     * @param array $context Extra render context (e.g. ['hostpage' => 'about']).
     * @return array Resolved data payload.
     */
    private static function resolve(
        string $type,
        int $id,
        string $title,
        string $subtitle,
        array $settings,
        array $context
    ): array {
        $resolver = self::resolver_for($type);
        if ($resolver === null) {
            return [];
        }

        $instance = new widget(0, (object)[
            'type' => $type,
            'zone' => self::SCOPE,
            'pagetype' => self::SCOPE,
            'sortorder' => $id,
            'title' => $title,
            'subtitle' => $subtitle,
            'enabled' => 1,
            'audience' => 'all',
            'settings' => json_encode($settings),
            'styleconfig' => '',
            'timestart' => 0,
            'timeend' => 0,
        ]);

        $context += ['pagetype' => self::SCOPE, 'hostpage' => 'about'];
        return $resolver->resolve($instance, $context);
    }

    /**
     * Map a widget type to a fresh resolver instance.
     *
     * @param string $type Widget type key.
     * @return \local_moderncommerce\storefront\resolver\widget_resolver|null
     */
    private static function resolver_for(string $type) {
        $map = [
            'featured' => featured_resolver::class,
            'related' => featured_resolver::class,
            'trustbadges' => trustbadges_resolver::class,
            'partners' => partners_resolver::class,
            'countdown' => countdown_resolver::class,
            'testimonials' => testimonials_resolver::class,
            'instructors' => instructors_resolver::class,
            'newsletter' => newsletter_resolver::class,
            'videohero' => videohero_resolver::class,
            'breadcrumb' => breadcrumb_resolver::class,
            'mediastorycarousel' => mediastorycarousel_resolver::class,
            'leadermessage' => leadermessage_resolver::class,
            'featuregrid' => featuregrid_resolver::class,
            'subscriptionplans' => subscriptionplans_resolver::class,
            'catalog' => catalog_resolver::class,
            'content' => content_resolver::class,
            'learningpromise' => learningpromise_resolver::class,
            'belief' => belief_resolver::class,
            'policy' => policy_resolver::class,
            'faq' => faq_resolver::class,
            'cta' => cta_resolver::class,
            'supportform' => supportform_resolver::class,
            'contactcards' => contactcards_resolver::class,
            'footer' => footer_resolver::class,
        ];
        if (!isset($map[$type])) {
            return null;
        }
        $class = $map[$type];
        return new $class();
    }

    /**
     * The full demo matrix: every widget type mapped to its style variants.
     *
     * @return array<string, array<int, array>>
     */
    private static function specs(): array {
        return [
            'slider' => self::slider_variants(),
            'videohero' => self::videohero_variants(),
            'breadcrumb' => self::breadcrumb_variants(),
            'featured' => self::featured_variants(),
            'related' => self::related_variants(),
            'categories' => self::categories_variants(),
            'trustbadges' => self::trustbadges_variants(),
            'partners' => self::partners_variants(),
            'countdown' => self::countdown_variants(),
            'testimonials' => self::testimonials_variants(),
            'instructors' => self::instructors_variants(),
            'newsletter' => self::newsletter_variants(),
            'content' => self::content_variants(),
            'mediastorycarousel' => self::mediastory_variants(),
            'leadermessage' => self::leadermessage_variants(),
            'featuregrid' => self::featuregrid_variants(),
            'subscriptionplans' => self::subscriptionplans_variants(),
            'learningpromise' => self::learningpromise_variants(),
            'belief' => self::belief_variants(),
            'policy' => self::policy_variants(),
            'faq' => self::faq_variants(),
            'cta' => self::cta_variants(),
            'supportform' => self::supportform_variants(),
            'contactcards' => self::contactcards_variants(),
            'footer' => self::footer_variants(),
            'catalog' => self::catalog_variants(),
        ];
    }

    // Demo content helpers.

    /**
     * Build a free, hotlink-friendly Unsplash image URL (Unsplash License).
     *
     * @param string $photo Unsplash photo id (the `photo-...` slug).
     * @param int $w Target width in pixels.
     * @param int $h Target height in pixels.
     * @return string
     */
    private static function img(string $photo, int $w, int $h): string {
        return 'https://images.unsplash.com/photo-' . $photo
            . '?auto=format&fit=crop&q=80&w=' . $w . '&h=' . $h;
    }

    /**
     * Build a square, face-cropped Unsplash avatar URL.
     *
     * @param string $photo Unsplash portrait photo id.
     * @return string
     */
    private static function avatar(string $photo): string {
        return 'https://images.unsplash.com/photo-' . $photo
            . '?auto=format&fit=crop&crop=faces&q=80&w=240&h=240';
    }

    /**
     * Build a self-contained wordmark placeholder for the partner logo gallery.
     *
     * Real partner logos are admin uploads, so the gallery cannot show one. An inline
     * SVG data URI keeps the demo offline-safe and neutral rather than borrowing a
     * real company mark.
     *
     * @param string $name Company name to render as the wordmark.
     * @param string $colour Wordmark fill colour.
     * @return string
     */
    private static function logo(string $name, string $colour): string {
        // Widest bold Helvetica caps measure ~0.78em (browser-measured); pad so nothing clips.
        $fontsize = 15;
        $width = 44 + (int) round(strlen($name) * $fontsize * 0.78);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' 60"'
            . ' width="' . $width . '" height="60" preserveAspectRatio="xMidYMid meet">'
            . '<circle cx="14" cy="30" r="9" fill="' . $colour . '" opacity="0.85"/>'
            . '<text x="28" y="35" font-family="Helvetica,Arial,sans-serif" font-size="' . $fontsize . '"'
            . ' font-weight="700" letter-spacing="0.5" fill="' . $colour . '">'
            . htmlspecialchars($name, ENT_XML1) . '</text>'
            . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Six demo partner wordmarks, enough for the carousel arrows to appear.
     *
     * @return array
     */
    private static function demo_logos(): array {
        return [
            ['image' => self::logo('NORTH', '#1f2a44'), 'name' => 'Northwind Systems', 'linkurl' => ''],
            ['image' => self::logo('AURORA', '#2563eb'), 'name' => 'Aurora Labs', 'linkurl' => ''],
            ['image' => self::logo('MERIDIAN', '#0f766e'), 'name' => 'Meridian Group', 'linkurl' => ''],
            ['image' => self::logo('KESTREL', '#7c3aed'), 'name' => 'Kestrel Digital', 'linkurl' => ''],
            ['image' => self::logo('LUMEN', '#b45309'), 'name' => 'Lumen Institute', 'linkurl' => ''],
            ['image' => self::logo('VANTAGE', '#be123c'), 'name' => 'Vantage Partners', 'linkurl' => 'https://example.org'],
        ];
    }

    /**
     * Three demo hero slides, reused across every slider design.
     *
     * @return array
     */
    private static function demo_slides(): array {
        return [
            [
                'id' => 1,
                'image' => self::img('1522202176988-66273c2fd55f', 1600, 640),
                'bgcolor' => 'var(--mc-secondary)',
                'heading' => 'Build skills you can use this week',
                'subheading' => 'Find practical courses, bundles, and programmes with secure checkout and instant access.',
                'ctalabel' => 'Browse the catalog',
                'ctaurl' => '#',
                'ctastyle' => 'light',
            ],
            [
                'id' => 2,
                'image' => self::img('1552664730-d307ca884978', 1600, 640),
                'bgcolor' => 'var(--mc-primary)',
                'heading' => 'Follow a complete learning path',
                'subheading' => 'Bundle related courses into a focused route from beginner to confident practitioner.',
                'ctalabel' => 'View bundles',
                'ctaurl' => '#',
                'ctastyle' => 'primary',
            ],
            [
                'id' => 3,
                'image' => self::img('1516321318423-f06f85e504b3', 1600, 640),
                'bgcolor' => 'var(--mc-secondary)',
                'heading' => 'Earn proof of progress',
                'subheading' => 'Choose certificate-ready learning and keep every purchase in your learner dashboard.',
                'ctalabel' => 'Explore programmes',
                'ctaurl' => '#',
                'ctastyle' => 'primary',
            ],
        ];
    }

    /**
     * Slider: overlay / split / card designs.
     *
     * @return array
     */
    private static function slider_variants(): array {
        $variants = [];
        $designs = ['overlay' => 'Overlay', 'split' => 'Split', 'card' => 'Card'];
        foreach ($designs as $design => $label) {
            $variants[] = [
                'stylelabel' => $label,
                'title' => 'Hero slider',
                'direct' => [
                    'slides' => self::demo_slides(),
                    'autoplay' => false,
                    'interval' => 6000,
                    'showarrows' => true,
                    'showdots' => true,
                    'design' => $design,
                    'buttoncolor' => 'var(--mc-primary)',
                    'buttontextcolor' => 'var(--mc-text-inverse)',
                    'buttonfontsize' => 16,
                    'buttonradius' => 8,
                ],
            ];
        }
        return $variants;
    }

    /**
     * Video hero (single appearance).
     *
     * @return array
     */
    private static function videohero_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'Video hero',
            'settings' => [
                'heading' => 'A learning store built|for momentum',
                'subtext' => 'ModernCommerce helps learners discover, buy, and start practical courses, '
                    . 'programmes, and bundles without friction.',
                'btn_primary_label' => 'Browse courses',
                'btn_primary_url' => '#',
                'btn_secondary_label' => 'Contact support',
                'btn_secondary_url' => '#',
                'bgcolor' => 'var(--mc-secondary)',
                'accentcolor' => 'var(--mc-accent)',
                'primarybuttoncolor' => 'var(--mc-surface)',
                'primarybuttontextcolor' => 'var(--mc-secondary)',
                'secondarybuttoncolor' => 'var(--mc-primary)',
                'secondarybuttontextcolor' => 'var(--mc-text-inverse)',
                'infocardbgcolor' => 'var(--mc-surface)',
                'infoiconbgcolor' => 'var(--mc-primary-light)',
                'infoiconcolor' => 'var(--mc-primary)',
                'infoheadingcolor' => 'var(--mc-text)',
                'infoheadingfontsize' => 18,
                'infotextcolor' => 'var(--mc-text-muted)',
                'video_source' => 'url',
                'video_url' => '',
                'video_poster' => self::img('1522202176988-66273c2fd55f', 1200, 720),
                'video_title' => 'ModernCommerce learning store',
                'infoitems' => [
                    [
                        'icon' => 'mortarboard',
                        'title' => 'Practical courses',
                        'text' => 'Buy focused learning you can start right away.',
                    ],
                    [
                        'icon' => 'collection',
                        'title' => 'Bundles and programmes',
                        'text' => 'Save with curated paths that connect related skills.',
                    ],
                    [
                        'icon' => 'patch-check',
                        'title' => 'Proof of progress',
                        'text' => 'Use certificates and records where available.',
                    ],
                ],
                'quote_text' => 'Premium learning should be easy to compare, purchase, and begin.',
                'quote_author' => 'ModernCommerce',
            ],
        ]];
    }

    /**
     * Breadcrumb / page-title banner: imagehero / clean / gradient / pastel / illustration.
     *
     * @return array
     */
    private static function breadcrumb_variants(): array {
        $styles = [
            'imagehero' => 'Image hero',
            'clean' => 'Clean minimal',
            'gradient' => 'Gradient media',
            'pastel' => 'Pastel band',
            'illustration' => 'Illustrated',
        ];
        $variants = [];
        foreach ($styles as $style => $label) {
            $isdark = $style === 'imagehero' || $style === 'gradient';
            $variants[] = [
                'stylelabel' => $label,
                'title' => 'About our learning store',
                'settings' => [
                    'style' => $style,
                    'title' => 'About our learning store',
                    'subtitle' => 'Find the right course, buy securely, and start learning with confidence.',
                    'homelabel' => 'Home',
                    'homeurl' => '#',
                    'sectionlabel' => 'Company',
                    'sectionurl' => '#',
                    'alignment' => 'center',
                    'bgcolor' => $isdark ? 'var(--mc-secondary)' : 'var(--mc-surface-alt)',
                    'overlaycolor' => 'var(--mc-secondary)',
                    'overlayopacity' => 68,
                    'gradientstart' => 'var(--mc-secondary)',
                    'gradientend' => 'var(--mc-primary)',
                    'textcolor' => $isdark ? 'var(--mc-text-inverse)' : 'var(--mc-text)',
                    'breadcrumbcolor' => $isdark ? 'var(--mc-primary-light)' : 'var(--mc-primary)',
                    'titlecolor' => $isdark ? 'var(--mc-text-inverse)' : 'var(--mc-secondary)',
                    'subtitlecolor' => $isdark ? 'var(--mc-primary-light)' : 'var(--mc-text-muted)',
                    'accentcolor' => 'var(--mc-accent)',
                    'breadcrumbfontsize' => 14,
                    'titlefontsize' => 44,
                    'subtitlefontsize' => 18,
                    'backgroundsource' => 'url',
                    'backgroundimage' => self::img('1552664730-d307ca884978', 1600, 520),
                    'paddingtop' => 92,
                    'paddingbottom' => 92,
                ],
            ];
        }
        return $variants;
    }

    /**
     * Shared product-list demo settings.
     *
     * @param string $layout 'carousel' or 'grid'.
     * @return array
     */
    private static function product_list_settings(string $layout): array {
        return [
            'layout' => $layout,
            'align' => 'left',
            'navposition' => 'topright',
            'coursetype' => '',
            'categoryid' => 0,
            'sort' => 'popular',
            'perpage' => 8,
            'columns' => 4,
            'buttoncolor' => 'var(--mc-primary)',
            'buttontextcolor' => 'var(--mc-text-inverse)',
            'cardbgcolor' => 'var(--mc-surface)',
            'cardbordercolor' => 'var(--mc-border)',
            'cardborderwidth' => 1,
        ];
    }

    /**
     * Featured products: carousel / grid layouts.
     *
     * @return array
     */
    private static function featured_variants(): array {
        return [
            [
                'stylelabel' => 'Carousel',
                'title' => 'Popular courses this week',
                'subtitle' => 'High-intent courses learners are buying now.',
                'settings' => self::product_list_settings('carousel'),
            ],
            [
                'stylelabel' => 'Grid',
                'title' => 'Popular courses this week',
                'subtitle' => 'High-intent courses learners are buying now.',
                'settings' => self::product_list_settings('grid'),
            ],
        ];
    }

    /**
     * Related products (shares the featured renderer).
     *
     * @return array
     */
    private static function related_variants(): array {
        return [
            [
                'stylelabel' => 'Grid',
                'title' => 'Build a complete path',
                'subtitle' => 'Save time with bundles and programmes that group related skills.',
                'settings' => self::product_list_settings('grid') + ['coursetype' => 'Bundle', 'columns' => 3, 'align' => 'center'],
            ],
            [
                'stylelabel' => 'Carousel',
                'title' => 'Build a complete path',
                'subtitle' => 'Save time with bundles and programmes that group related skills.',
                'settings' => self::product_list_settings('carousel') + ['coursetype' => 'Bundle', 'columns' => 3],
            ],
        ];
    }

    /**
     * Category tiles: minimal / colourful / carousel. Built directly (live counts need real categories).
     *
     * @return array
     */
    private static function categories_variants(): array {
        $demo = [
            ['name' => 'Business', 'icon' => 'briefcase', 'count' => 24, 'color' => '#4f46e5'],
            ['name' => 'Technology', 'icon' => 'cpu', 'count' => 38, 'color' => '#2563eb'],
            ['name' => 'Design', 'icon' => 'palette', 'count' => 17, 'color' => '#db2777'],
            ['name' => 'Marketing', 'icon' => 'megaphone', 'count' => 21, 'color' => '#f59e0b'],
            ['name' => 'Data & AI', 'icon' => 'graph-up', 'count' => 29, 'color' => '#7c3aed'],
            ['name' => 'Wellbeing', 'icon' => 'heart-pulse', 'count' => 12, 'color' => '#475569'],
        ];

        $variants = [];
        foreach (['minimal' => 'Minimal', 'colourful' => 'Colourful', 'carousel' => 'Carousel'] as $style => $label) {
            // Colourful and carousel both use the per-tile colour; minimal uses a single icon colour.
            $usetilecolour = in_array($style, ['colourful', 'carousel'], true);
            $tiles = [];
            $i = 1;
            foreach ($demo as $cat) {
                $tiles[] = [
                    'id' => $i++,
                    'name' => $cat['name'],
                    'count' => $cat['count'],
                    'url' => '#',
                    'icon' => $cat['icon'],
                    'color' => $usetilecolour ? $cat['color'] : '',
                ];
            }
            $direct = [
                'categories' => $tiles,
                'showcount' => true,
                'style' => $style,
                'iconcolor' => 'var(--mc-primary)',
                'bgcolor' => 'var(--mc-surface)',
                'titlecolor' => 'var(--mc-text)',
                'titlefontsize' => 32,
                'subtitlecolor' => 'var(--mc-text-muted)',
                'subtitlefontsize' => 17,
                'cardbgcolor' => 'var(--mc-surface)',
                'cardtextcolor' => 'var(--mc-text)',
                'cardtextfontsize' => 18,
                'cardradius' => 8,
                'iconbgcolor' => 'var(--mc-primary-light)',
                'iconsize' => 26,
                'countcolor' => 'var(--mc-text-muted)',
                'countfontsize' => 14,
                'paddingtop' => 54,
                'paddingbottom' => 34,
                'labels' => ['courses' => 'courses'],
            ];
            if ($style === 'carousel') {
                // Show 4 of the 6 demo categories per view so the prev/next arrows appear.
                $direct['visiblecards'] = 4;
            }
            $variants[] = [
                'stylelabel' => $label,
                'title' => 'Choose your learning direction',
                'subtitle' => 'Browse by goals, topics, and career stage.',
                'direct' => $direct,
            ];
        }
        return $variants;
    }

    /**
     * Partner logos: one variant per layout mode.
     *
     * @return array
     */
    private static function partners_variants(): array {
        $logos = self::demo_logos();
        $variants = [];

        foreach (['grid' => 'Grid', 'carousel' => 'Carousel', 'marquee' => 'Marquee'] as $style => $label) {
            $variants[] = [
                'stylelabel' => $label,
                'settings' => [
                    'style' => $style,
                    'heading' => 'Trusted Technical Partners',
                    'logos' => $logos,
                    'visiblelogos' => 4,
                    'speed' => 30,
                    'pauseonhover' => true,
                    'greyscale' => false,
                    'logoheight' => 40,
                    'logogap' => 40,
                    'bgcolor' => 'var(--mc-surface)',
                    'headingcolor' => 'var(--mc-text)',
                    'headingfontsize' => 28,
                    'paddingtop' => 48,
                    'paddingbottom' => 48,
                ],
            ];
        }

        return $variants;
    }

    /**
     * Trust badges strip (single appearance).
     *
     * @return array
     */
    private static function trustbadges_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'Buy with confidence',
            'settings' => [
                'badges' => [
                    ['icon' => 'shield-check', 'label' => 'Secure checkout', 'sublabel' => 'Protected payment flow'],
                    ['icon' => 'unlock', 'label' => 'Instant access', 'sublabel' => 'Start after payment'],
                    ['icon' => 'patch-check', 'label' => 'Certificate-ready', 'sublabel' => 'Proof where available'],
                    ['icon' => 'headset', 'label' => 'Human support', 'sublabel' => 'Help for access and billing'],
                ],
                'bgcolor' => 'var(--mc-surface)',
                'titlecolor' => 'var(--mc-text)',
                'titlefontsize' => 24,
                'cardbgcolor' => 'var(--mc-surface)',
                'cardbordercolor' => 'var(--mc-border)',
                'cardborderwidth' => 1,
                'cardradius' => 8,
                'iconbgcolor' => 'var(--mc-primary-light)',
                'iconcolor' => 'var(--mc-primary)',
                'iconsize' => 26,
                'labelcolor' => 'var(--mc-text)',
                'labelfontsize' => 16,
                'sublabelcolor' => 'var(--mc-text-muted)',
                'sublabelfontsize' => 14,
                'paddingtop' => 32,
                'paddingbottom' => 28,
            ],
        ]];
    }

    /**
     * Countdown bar (single appearance).
     *
     * @return array
     */
    private static function countdown_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'Countdown',
            'settings' => [
                'heading' => 'Enrollment offer ends soon',
                'endtime' => time() + (14 * DAYSECS),
                'expiredmessage' => 'The featured offer has ended. Browse the latest course deals.',
                'ctalabel' => 'Explore offers',
                'ctaurl' => '#',
                'bgcolor' => 'var(--mc-secondary)',
                'textcolor' => 'var(--mc-primary-light)',
                'headingcolor' => 'var(--mc-text-inverse)',
                'headingfontsize' => 18,
                'timerbgcolor' => 'var(--mc-primary-active)',
                'timernumbercolor' => 'var(--mc-accent)',
                'timernumberfontsize' => 24,
                'timerlabelcolor' => 'var(--mc-primary-light)',
                'timerlabelfontsize' => 12,
                'buttoncolor' => 'var(--mc-primary)',
                'buttontextcolor' => 'var(--mc-text-inverse)',
                'expiredbgcolor' => 'var(--mc-secondary)',
                'expiredtextcolor' => 'var(--mc-primary-light)',
                'paddingtop' => 14,
                'paddingbottom' => 14,
            ],
        ]];
    }

    /**
     * Testimonials grid (single appearance).
     *
     * @return array
     */
    private static function testimonials_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'Learners use this store to move faster',
            'subtitle' => 'Realistic proof points for course buyers, teams, and career changers.',
            'settings' => [
                'bgcolor' => 'var(--mc-page-bg)',
                'titlecolor' => 'var(--mc-text)',
                'subtitlecolor' => 'var(--mc-text-muted)',
                'cardbgcolor' => 'var(--mc-surface)',
                'cardbordercolor' => 'var(--mc-border)',
                'cardborderwidth' => 1,
                'cardradius' => 8,
                'ratingcolor' => 'var(--mc-accent)',
                'quotecolor' => 'var(--mc-text)',
                'avatarbgcolor' => 'var(--mc-primary-light)',
                'avatarcolor' => 'var(--mc-primary)',
                'namecolor' => 'var(--mc-text)',
                'rolecolor' => 'var(--mc-text-muted)',
                'paddingtop' => 74,
                'paddingbottom' => 30,
                'testimonials' => [
                    [
                        'quote' => 'The catalog made it easy to compare courses and bundles. I bought the right '
                            . 'path and started learning the same day.',
                        'author' => 'Amara O.',
                        'role' => 'Product Designer',
                        'rating' => 5,
                    ],
                    [
                        'quote' => 'The bundle pricing and certificates gave our team a clear way to train '
                            . 'without chasing separate invoices.',
                        'author' => 'David K.',
                        'role' => 'Operations Lead',
                        'rating' => 5,
                    ],
                    [
                        'quote' => 'The buying process felt clear, and support helped me choose a programme '
                            . 'that matched my goal.',
                        'author' => 'Grace T.',
                        'role' => 'Marketing Manager',
                        'rating' => 5,
                    ],
                ],
            ],
        ]];
    }

    /**
     * Instructor spotlight (single appearance).
     *
     * @return array
     */
    private static function instructors_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'Learn from practitioners',
            'subtitle' => 'Instructor-led content designed around practical outcomes.',
            'settings' => [
                'bgcolor' => 'var(--mc-page-bg)',
                'titlecolor' => 'var(--mc-text)',
                'subtitlecolor' => 'var(--mc-text-muted)',
                'cardbgcolor' => 'var(--mc-surface)',
                'cardbordercolor' => 'var(--mc-border)',
                'cardborderwidth' => 1,
                'cardradius' => 8,
                'avatarbgcolor' => 'var(--mc-primary-light)',
                'avatarcolor' => 'var(--mc-primary)',
                'namecolor' => 'var(--mc-text)',
                'rolecolor' => 'var(--mc-primary)',
                'biocolor' => 'var(--mc-text-muted)',
                'paddingtop' => 20,
                'paddingbottom' => 72,
                'instructors' => [
                    ['name' => 'Dr. Amara Obi', 'role' => 'Data and analytics instructor',
                        'bio' => 'Builds practical analytics courses around dashboards, decisions, and business reporting.',
                        'photosource' => 'url', 'photourl' => self::avatar('1438761681033-6461ffad8d80')],
                    ['name' => 'James Whitfield', 'role' => 'Product design mentor',
                        'bio' => 'Teaches UX, product thinking, and portfolio-ready design workflows for modern teams.',
                        'photosource' => 'url', 'photourl' => self::avatar('1500648767791-00dcc994a43e')],
                    ['name' => 'Priya Menon', 'role' => 'Marketing strategist',
                        'bio' => 'Creates courses on positioning, campaigns, content systems, and measurable growth.',
                        'photosource' => 'url', 'photourl' => self::avatar('1534528741775-53994a69daeb')],
                ],
            ],
        ]];
    }

    /**
     * Newsletter / lead capture (single appearance).
     *
     * @return array
     */
    private static function newsletter_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'Newsletter',
            'settings' => [
                'heading' => 'Get course drops, bundles, and programme launches',
                'description' => 'One useful email when new learning paths, certificates, and seasonal offers go live.',
                'placeholder' => 'you@example.com',
                'buttonlabel' => 'Subscribe',
                'successmessage' => 'Thanks for subscribing. Check your inbox to confirm.',
                'bgcolor' => 'var(--mc-page-bg)',
                'panelbgcolor' => 'var(--mc-secondary)',
                'panelbordercolor' => 'var(--mc-secondary)',
                'panelborderwidth' => 1,
                'panelradius' => 8,
                'panelpaddingtop' => 44,
                'panelpaddingright' => 44,
                'panelpaddingbottom' => 44,
                'panelpaddingleft' => 44,
                'titlecolor' => 'var(--mc-text-inverse)',
                'titlefontsize' => 30,
                'textcolor' => 'var(--mc-primary-light)',
                'textfontsize' => 16,
                'inputbgcolor' => 'var(--mc-surface)',
                'inputbordercolor' => 'var(--mc-primary-border)',
                'inputtextcolor' => 'var(--mc-text)',
                'placeholdercolor' => 'var(--mc-text-muted)',
                'buttoncolor' => 'var(--mc-primary)',
                'buttontextcolor' => 'var(--mc-text-inverse)',
                'buttonradius' => 8,
                'paddingtop' => 54,
                'paddingbottom' => 54,
            ],
        ]];
    }

    /**
     * Content section: card / centered / split layouts.
     *
     * @return array
     */
    private static function content_variants(): array {
        $styles = ['split' => 'Split', 'card' => 'Card', 'centered' => 'Centered'];
        $variants = [];
        foreach ($styles as $layout => $label) {
            $variants[] = [
                'stylelabel' => $label,
                'title' => 'Learning that fits around real life',
                'subtitle' => 'Benefits of buying courses through a focused learning store.',
                'settings' => [
                    'eyebrow' => 'Benefits of our courses',
                    'icon' => 'mortarboard',
                    'layout' => $layout,
                    'mediaposition' => $layout === 'split' ? 'left' : 'right',
                    'body' => 'We bring course discovery, checkout, enrolment, receipts, support, and learner '
                        . 'access into one Moodle-native commerce experience.',
                    'benefits' => [
                        [
                            'number' => '01',
                            'title' => 'Interactive learning',
                            'text' => 'Choose practical courses and programmes designed around useful outcomes.',
                        ],
                        [
                            'number' => '02',
                            'title' => 'Personalized paths',
                            'text' => 'Compare single courses, bundles, and programmes before you buy.',
                        ],
                        [
                            'number' => '03',
                            'title' => 'Flexible access',
                            'text' => 'Start after checkout and return through your learner dashboard.',
                        ],
                    ],
                    'imagesource' => 'url',
                    'imageurl' => self::img('1516321318423-f06f85e504b3', 720, 520),
                    'imagefile' => '',
                    'image' => self::img('1516321318423-f06f85e504b3', 720, 520),
                    'ctalabel' => 'Explore the catalog',
                    'ctaurl' => '#',
                    'bgcolor' => 'var(--mc-surface)',
                    'panelbgcolor' => 'var(--mc-surface)',
                    'panelbordercolor' => 'var(--mc-border)',
                    'titlecolor' => 'var(--mc-text)',
                    'titlefontsize' => 42,
                    'subtitlecolor' => 'var(--mc-primary)',
                    'textcolor' => 'var(--mc-text-muted)',
                    'benefitnumbercolor' => 'var(--mc-primary)',
                    'benefittitlecolor' => 'var(--mc-text)',
                    'benefittextcolor' => 'var(--mc-text-muted)',
                    'benefitbordercolor' => 'var(--mc-border)',
                    'buttoncolor' => 'var(--mc-primary)',
                    'buttontextcolor' => 'var(--mc-text-inverse)',
                    'buttonradius' => 8,
                    'mediaradius' => 18,
                    'cardradius' => 8,
                    'paddingtop' => 88,
                    'paddingbottom' => 88,
                    'paddingleft' => 20,
                    'paddingright' => 20,
                ],
            ];
        }
        return $variants;
    }

    /**
     * Media + story carousel: media left / media right.
     *
     * @return array
     */
    private static function mediastory_variants(): array {
        $slides = [
            [
                'heading' => 'From discovery to access',
                'subheading' => 'Learners can compare products, purchase securely, and move straight into the courses they bought.',
                'mediatype' => 'image', 'mediasource' => 'url',
                'imageurl' => self::img('1552664730-d307ca884978', 800, 600),
                'imagefile' => '',
                'videourl' => '',
                'videofile' => '',
                'posterurl' => '',
                'posterimage' => '',
                'alt' => 'Course team planning a learning programme',
            ],
            [
                'heading' => 'A store shaped around outcomes',
                'subheading' => 'Courses, bundles, and programmes give buyers different levels of commitment '
                    . 'without hiding the value proposition.',
                'mediatype' => 'image', 'mediasource' => 'url',
                'imageurl' => self::img('1497366754035-f200968a6e72', 800, 600),
                'imagefile' => '',
                'videourl' => '',
                'videofile' => '',
                'posterurl' => '',
                'posterimage' => '',
                'alt' => 'Learner studying online from a desk',
            ],
        ];
        $variants = [];
        foreach (['left' => 'Media left', 'right' => 'Media right'] as $pos => $label) {
            $variants[] = [
                'stylelabel' => $label,
                'title' => 'Media story',
                'settings' => [
                    'mediaposition' => $pos,
                    'bgcolor' => 'var(--mc-surface)',
                    'cardbgcolor' => 'var(--mc-surface)',
                    'cardbordercolor' => 'var(--mc-border)',
                    'cardborderwidth' => 1,
                    'cardradius' => 8,
                    'titlecolor' => 'var(--mc-text)',
                    'titlefontsize' => 36,
                    'textcolor' => 'var(--mc-text-muted)',
                    'textfontsize' => 17,
                    'iconcolor' => 'var(--mc-text-inverse)',
                    'iconbgcolor' => 'var(--mc-primary)',
                    'mediaradius' => 16,
                    'paddingtop' => 68,
                    'paddingbottom' => 84,
                    'navicon' => 'arrow-right',
                    'slides' => $slides,
                ],
            ];
        }
        return $variants;
    }

    /**
     * Centered learning-promise statement (single appearance).
     *
     * @return array
     */
    private static function learningpromise_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'Skills are the key to unlocking potential',
            'settings' => [
                'body' => 'Whether someone is learning a new tool, changing career direction, or training a team, '
                    . 'the buying experience should make the next step obvious and trustworthy.',
                'bgcolor' => 'var(--mc-page-bg)',
                'headingcolor' => 'var(--mc-secondary)',
                'headingfontsize' => 42,
                'textcolor' => 'var(--mc-text-muted)',
                'textfontsize' => 18,
                'paddingtop' => 72,
                'paddingbottom' => 72,
            ],
        ]];
    }

    /**
     * Feature grid: one entry per card treatment, alternating 3-up and 4-up so
     * the gallery demonstrates both densities.
     *
     * @return array
     */
    private static function featuregrid_variants(): array {
        $cards = [
            [
                'icon' => 'mortarboard-fill',
                'title' => 'Accredited courses',
                'text' => 'Standardised training tracks built for clinic and community operations.',
                'iconcolor' => '#1e7d47',
                'accentcolor' => '#1e7d47',
            ],
            [
                'icon' => 'award-fill',
                'title' => 'E-certificates',
                'text' => 'Download securely verified certificates as soon as you pass.',
                'iconcolor' => '#e8a33d',
                'accentcolor' => '#e8a33d',
            ],
            [
                'icon' => 'phone-fill',
                'title' => 'Mobile optimised',
                'text' => 'Bandwidth-efficient rendering built for remote teams on local networks.',
                'iconcolor' => '#0f8ea8',
                'accentcolor' => '#0f8ea8',
            ],
            [
                'icon' => 'people-fill',
                'title' => 'Guided cohorts',
                'text' => 'Run scheduled intakes with facilitators and shared progress reporting.',
                'iconcolor' => '#7c3aed',
                'accentcolor' => '#7c3aed',
            ],
        ];

        $treatments = [
            'topbar' => ['Top bar', 3],
            'tile' => ['Icon tile', 4],
            'bordered' => ['Bordered', 3],
            'plain' => ['Plain', 4],
        ];

        $variants = [];
        foreach ($treatments as $style => [$label, $columns]) {
            $variants[] = [
                'stylelabel' => $label,
                'title' => 'Built for frontline teams',
                'subtitle' => 'Everything your workforce needs to train, certify, and stay current.',
                'settings' => [
                    'style' => $style,
                    'columns' => (string) $columns,
                    'showheader' => 1,
                    'centercards' => 1,
                    // 4-up shows all four demo cards; 3-up drops the last for a full row.
                    'cards' => $columns === 4 ? $cards : array_slice($cards, 0, 3),
                    'bgcolor' => 'var(--mc-page-bg)',
                    'cardbgcolor' => 'var(--mc-surface)',
                    'cardradius' => 12,
                    'iconsize' => 40,
                    'paddingtop' => 72,
                    'paddingbottom' => 72,
                ],
            ];
        }

        return $variants;
    }

    /**
     * Subscription plans: one entry per card treatment.
     *
     * Previews the site's real plans wherever any exist, so the gallery shows the
     * same catalogue, prices, and feature matrix an admin has actually configured
     * (including anything created by the subscription seeder). Only a site with no
     * plans at all falls back to the synthetic cards below, which keeps the gallery
     * from rendering an empty band on a fresh install.
     *
     * @return array
     */
    private static function subscriptionplans_variants(): array {
        $treatments = [
            'elevated' => 'Elevated',
            'bordered' => 'Bordered',
            'minimal' => 'Minimal',
        ];

        $title = 'Simple pricing for every learner';
        $subtitle = 'Choose a plan and switch or cancel whenever your goals change.';

        if (self::has_subscription_plans()) {
            $variants = [];
            foreach ($treatments as $style => $label) {
                $variants[] = [
                    'stylelabel' => $label,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    // No 'direct' key: the real resolver runs and reads live plans.
                    'settings' => [
                        'style' => $style,
                        'columns' => 3,
                        'billingcycle' => 'both',
                        'defaultcycle' => 'monthly',
                        'savingslabel' => 'Save 2 months',
                        'maxplans' => 3,
                        'ctalabel' => 'Choose plan',
                        'ctaurl' => '#',
                        'featuredlabel' => 'Most popular',
                        'bgcolor' => 'var(--mc-page-bg)',
                        'titlecolor' => 'var(--mc-text)',
                        'titlefontsize' => 34,
                        // Explicit tone rather than --mc-text-muted, which sits a
                        // shade under 4.5:1 on the gallery's tinted panel.
                        'subtitlecolor' => '#556070',
                        'subtitlefontsize' => 17,
                        'cardbgcolor' => 'var(--mc-surface)',
                        'cardbordercolor' => 'var(--mc-border)',
                        'cardradius' => 12,
                        'accentcolor' => 'var(--mc-primary)',
                        'plannamecolor' => 'var(--mc-text)',
                        'plannamefontsize' => 19,
                        'pricecolor' => 'var(--mc-text)',
                        'pricefontsize' => 40,
                        'cyclecolor' => 'var(--mc-text-muted)',
                        'textcolor' => 'var(--mc-text-muted)',
                        'textfontsize' => 15,
                        'featurecolor' => 'var(--mc-text)',
                        'featureiconcolor' => 'var(--mc-primary)',
                        'buttoncolor' => 'var(--mc-primary)',
                        'buttontextcolor' => 'var(--mc-text-inverse)',
                        'buttonradius' => 8,
                        'paddingtop' => 72,
                        'paddingbottom' => 72,
                    ],
                ];
            }
            return $variants;
        }

        return self::subscriptionplans_placeholder_variants($treatments, $title, $subtitle);
    }

    /**
     * Whether this site has any subscription plan to preview.
     *
     * @return bool
     */
    private static function has_subscription_plans(): bool {
        global $DB;

        try {
            return $DB->record_exists('local_moderncommerce_subscription_plans', ['status' => 'active']);
        } catch (\Throwable $e) {
            // Table missing on a partially upgraded site: fall back to demo cards.
            return false;
        }
    }

    /**
     * Synthetic plan cards for sites with no subscription plans configured.
     *
     * @param array<string, string> $treatments Style key to display label.
     * @param string $title Section heading.
     * @param string $subtitle Section subheading.
     * @return array
     */
    private static function subscriptionplans_placeholder_variants(
        array $treatments,
        string $title,
        string $subtitle
    ): array {
        $features = static function (array $names): array {
            return array_map(
                static fn(string $name): array => ['name' => $name, 'icon' => 'check-circle'],
                $names
            );
        };

        $plans = [
            [
                'id' => 1,
                'name' => 'Starter',
                'code' => 'starter',
                'description' => 'For individual learners getting started.',
                'billingcycle' => 'monthly',
                'isfree' => false,
                'displayprice' => '$19',
                'haspromo' => false,
                'displayregularprice' => '',
                'trialdays' => 14,
                'featured' => false,
                'features' => $features([
                    'Access to all starter courses',
                    'Completion certificates',
                    'Learner dashboard',
                ]),
            ],
            [
                'id' => 2,
                'name' => 'Professional',
                'code' => 'professional',
                'description' => 'For learners building a full career path.',
                'billingcycle' => 'monthly',
                'isfree' => false,
                'displayprice' => '$39',
                'haspromo' => true,
                'displayregularprice' => '$49',
                'trialdays' => 14,
                'featured' => true,
                'features' => $features([
                    'Everything in Starter',
                    'All bundles and programmes',
                    'Priority support',
                    'Downloadable resources',
                ]),
            ],
            [
                'id' => 3,
                'name' => 'Team',
                'code' => 'team',
                'description' => 'For organisations training a whole group.',
                'billingcycle' => 'monthly',
                'isfree' => false,
                'displayprice' => '$99',
                'haspromo' => false,
                'displayregularprice' => '',
                'trialdays' => 0,
                'featured' => false,
                'features' => $features([
                    'Everything in Professional',
                    'Up to 25 seats',
                    'Team progress reporting',
                    'Invoice billing',
                ]),
            ],
        ];

        // Both cycles so the placeholder demonstrates the monthly/yearly switch too.
        $yearly = array_map(static function (array $plan): array {
            $plan['billingcycle'] = 'yearly';
            $plan['trialdays'] = $plan['trialdays'] ?? 0;
            return $plan;
        }, $plans);
        $yearly[0]['displayprice'] = '$190';
        $yearly[1]['displayprice'] = '$390';
        $yearly[1]['displayregularprice'] = '$468';
        $yearly[1]['haspromo'] = true;
        $yearly[2]['displayprice'] = '$990';

        $base = [
            'plans' => $plans,
            'cycles' => [
                ['key' => 'monthly', 'label' => 'Monthly', 'plans' => $plans],
                ['key' => 'yearly', 'label' => 'Yearly', 'plans' => $yearly],
            ],
            'defaultcycle' => 'monthly',
            'showcycleswitch' => true,
            'savingslabel' => 'Save 2 months',
            'cyclegrouplabel' => get_string('billingcycle', 'local_moderncommerce'),
            'columns' => 3,
            'showheader' => true,
            'showfeatures' => true,
            'ctalabel' => 'Choose plan',
            'ctaurl' => '#',
            'featuredlabel' => 'Most popular',
            'bgcolor' => 'var(--mc-page-bg)',
            'titlecolor' => 'var(--mc-text)',
            'titlefontsize' => 34,
            'subtitlecolor' => '#556070',
            'subtitlefontsize' => 17,
            'cardbgcolor' => 'var(--mc-surface)',
            'cardbordercolor' => 'var(--mc-border)',
            'cardborderwidth' => 1,
            'cardradius' => 12,
            'accentcolor' => 'var(--mc-primary)',
            'plannamecolor' => 'var(--mc-text)',
            'plannamefontsize' => 19,
            'pricecolor' => 'var(--mc-text)',
            'pricefontsize' => 40,
            'cyclecolor' => 'var(--mc-text-muted)',
            'textcolor' => 'var(--mc-text-muted)',
            'textfontsize' => 15,
            'featurecolor' => 'var(--mc-text)',
            'featureiconcolor' => 'var(--mc-primary)',
            'buttoncolor' => 'var(--mc-primary)',
            'buttontextcolor' => 'var(--mc-text-inverse)',
            'buttonradius' => 8,
            'paddingtop' => 72,
            'paddingbottom' => 72,
        ];

        $variants = [];
        foreach ($treatments as $style => $label) {
            $variants[] = [
                'stylelabel' => $label,
                'title' => $title,
                'subtitle' => $subtitle,
                'direct' => $base + [
                    'style' => $style,
                    'title' => $title,
                    'subtitle' => $subtitle,
                ],
            ];
        }

        return $variants;
    }

    /**
     * Leader message: one entry per design treatment, alternating the portrait
     * side so the gallery shows both layouts.
     *
     * @return array
     */
    private static function leadermessage_variants(): array {
        $message = '<p>On behalf of the management and staff, I am delighted to welcome you to our official '
            . 'learning portal. This platform represents a critical milestone in our commitment to '
            . 'strengthening skills across the organisation.</p>'
            . '<p>Through this portal you will access accredited, self-paced modules designed to build the '
            . 'competencies that matter most in your role. Welcome, and let us build something better '
            . 'together.</p>';

        $base = [
            'message' => $message,
            'showeyebrow' => 1,
            'eyebrow' => 'From the leadership',
            'showtitle' => 1,
            'showphoto' => 1,
            'photosource' => 'url',
            'photourl' => self::img('1560250097-0b93528c311a', 900, 1100),
            'showattribution' => 1,
            'leadername' => 'Dr Amina Bello',
            'leaderrole' => 'Executive Director / CEO',
            'showaccent' => 1,
            'paddingtop' => 72,
            'paddingbottom' => 72,
        ];

        $treatments = [
            'elevated' => ['Elevated editorial', 'right'],
            'overlay' => ['Portrait card', 'left'],
            'quote' => ['Quote-led', 'right'],
            'flat' => ['Flat', 'left'],
        ];

        $variants = [];
        foreach ($treatments as $style => [$label, $position]) {
            $variants[] = [
                'stylelabel' => $label,
                'title' => 'Welcome message from the ED/CEO',
                'settings' => $base + [
                    'style' => $style,
                    'mediaposition' => $position,
                ],
            ];
        }

        return $variants;
    }

    /**
     * Full-width belief band (single appearance).
     *
     * @return array
     */
    private static function belief_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'We believe',
            'subtitle' => 'Learning is the source of human progress.',
            'settings' => [
                'bgcolor' => 'var(--mc-secondary)',
                'titlecolor' => 'var(--mc-text-inverse)',
                'titlefontsize' => 38,
                'subtitlecolor' => 'var(--mc-primary-light)',
                'subtitlefontsize' => 18,
                'iconcolor' => 'var(--mc-accent)',
                'iconsize' => 26,
                'textcolor' => 'var(--mc-primary-light)',
                'textfontsize' => 17,
                'labelcolor' => 'var(--mc-text-inverse)',
                'labelfontsize' => 20,
                'paddingtop' => 76,
                'paddingbottom' => 76,
                'items' => [
                    ['icon' => 'globe2', 'text' => 'The right course can move a learner from curiosity to practical capability.'],
                    [
                        'icon' => 'people',
                        'text' => 'Learning stores work best when discovery, checkout, and support feel connected.',
                    ],
                    [
                        'icon' => 'graph-up-arrow',
                        'text' => 'Bundles and programmes help learners see a path instead of isolated products.',
                    ],
                    [
                        'icon' => 'bank',
                        'text' => 'Trusted commerce creates room for better teaching, clearer records, and better outcomes.',
                    ],
                ],
                'closing' => 'ModernCommerce exists to make that path simple, credible, and ready to use.',
            ],
        ]];
    }

    /**
     * Structured policy sections (single appearance).
     *
     * @return array
     */
    private static function policy_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'Course purchase terms',
            'subtitle' => 'Starter guidance for digital learning purchases.',
            'settings' => [
                'effectivedate' => 'Effective from purchase date',
                'bgcolor' => 'var(--mc-page-bg)',
                'cardbgcolor' => 'var(--mc-surface)',
                'cardbordercolor' => 'var(--mc-border)',
                'cardborderwidth' => 1,
                'cardradius' => 8,
                'titlecolor' => 'var(--mc-text)',
                'subtitlecolor' => 'var(--mc-text-muted)',
                'labelcolor' => 'var(--mc-secondary)',
                'textcolor' => 'var(--mc-text-muted)',
                'paddingtop' => 34,
                'paddingbottom' => 54,
                'sections' => [
                    [
                        'heading' => 'Purchases and account access',
                        'body' => 'Courses, programmes, bundles, and subscriptions are digital learning products '
                            . 'connected to the learner account used at checkout.',
                        'bullets' => "Access is granted after successful payment.\n"
                            . "Some products can include time limits, prerequisites, certificate rules, "
                            . "or subscription conditions.\n"
                            . "Buyers should keep account credentials secure.",
                    ],
                    [
                        'heading' => 'Payments, coupons, and subscriptions',
                        'body' => 'Prices, discounts, taxes, fees, and currency display follow the store settings '
                            . 'shown at checkout.',
                        'bullets' => "Coupons and promotions can change before checkout is completed.\n"
                            . "Subscription products renew according to the cycle shown at purchase.\n"
                            . "Payment references and order records are retained for support and audit purposes.",
                    ],
                    [
                        'heading' => 'Learning content and acceptable use',
                        'body' => 'Course content, programme structure, and included resources may be improved over time.',
                        'bullets' => "Sharing paid access outside the purchasing account is not permitted.\n"
                            . "Certificates may depend on completion rules set by the course.\n"
                            . "Support requests should include the order number and purchase email.",
                    ],
                ],
            ],
        ]];
    }

    /**
     * FAQ accordion (single appearance).
     *
     * @return array
     */
    private static function faq_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'Frequently asked questions',
            'subtitle' => 'Everything you need to know before you buy',
            'settings' => [
                'bgcolor' => 'var(--mc-page-bg)',
                'titlecolor' => 'var(--mc-text)',
                'subtitlecolor' => 'var(--mc-text-muted)',
                'itembgcolor' => 'var(--mc-surface)',
                'itembordercolor' => 'var(--mc-border)',
                'cardborderwidth' => 1,
                'cardradius' => 8,
                'questioncolor' => 'var(--mc-text)',
                'answercolor' => 'var(--mc-text-muted)',
                'iconcolor' => 'var(--mc-primary)',
                'paddingtop' => 40,
                'paddingbottom' => 40,
                'items' => [
                    ['question' => 'How soon do I get access after paying?',
                        'answer' => 'Immediately. Enrolment is automatic the moment your payment succeeds.'],
                    ['question' => 'Can I buy more than one course at once?',
                        'answer' => "Yes.\n\nAdd as many courses or bundles to your cart as you like and check "
                            . "out in a single payment."],
                    ['question' => 'Do you offer refunds?',
                        'answer' => 'Refunds follow our published refund policy, which depends on product type '
                            . 'and access used.'],
                ],
            ],
        ]];
    }

    /**
     * Call-to-action band: primary / quiet / success tones.
     *
     * @return array
     */
    private static function cta_variants(): array {
        $tones = ['primary' => 'Primary', 'quiet' => 'Quiet', 'success' => 'Success'];
        $variants = [];
        foreach ($tones as $tone => $label) {
            $variants[] = [
                'stylelabel' => $label,
                'title' => 'Call to action',
                'settings' => [
                    'heading' => 'Ready to choose your next course?',
                    'text' => 'Browse the catalog or ask support for help choosing the best path for your goal.',
                    'tone' => $tone,
                    'primarylabel' => 'Browse courses',
                    'primaryurl' => '#',
                    'secondarylabel' => 'Contact support',
                    'secondaryurl' => '#',
                    'bgcolor' => $tone === 'primary' ? 'var(--mc-primary)' : 'var(--mc-surface)',
                    'titlecolor' => $tone === 'primary' ? 'var(--mc-text-inverse)' : 'var(--mc-text)',
                    'textcolor' => $tone === 'primary' ? 'var(--mc-primary-light)' : 'var(--mc-text-muted)',
                    'primarybuttoncolor' => $tone === 'primary' ? 'var(--mc-surface)' : 'var(--mc-primary)',
                    'primarybuttontextcolor' => $tone === 'primary'
                        ? 'var(--mc-primary-active)' : 'var(--mc-text-inverse)',
                    'secondarybuttoncolor' => $tone === 'primary' ? 'var(--mc-primary)' : 'var(--mc-primary-light)',
                    'secondarybuttontextcolor' => $tone === 'primary'
                        ? 'var(--mc-text-inverse)' : 'var(--mc-primary-active)',
                    'buttonradius' => 8,
                    'cardradius' => 8,
                    'paddingtop' => $tone === 'primary' ? 54 : 48,
                    'paddingbottom' => 54,
                ],
            ];
        }
        return $variants;
    }

    /**
     * Support request form (single appearance).
     *
     * @return array
     */
    private static function supportform_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'How can we help?',
            'subtitle' => 'Send us your order, access, payment, refund, or subscription question.',
            'settings' => [
                'heading' => 'How can we help?',
                'description' => 'Share the product name, order number, purchase email, and what you expected to happen.',
                'buttonlabel' => 'Send support request',
                'emailbuttonlabel' => 'Email support',
                'messagelabel' => 'Message',
                'messageplaceholder' => 'Tell us what happened and which course, programme, bundle, or order is affected.',
                'bgcolor' => 'var(--mc-page-bg)',
                'cardbgcolor' => 'var(--mc-surface)',
                'cardbordercolor' => 'var(--mc-border)',
                'cardborderwidth' => 1,
                'cardradius' => 8,
                'titlecolor' => 'var(--mc-text)',
                'textcolor' => 'var(--mc-text-muted)',
                'formlabelcolor' => 'var(--mc-text)',
                'inputbgcolor' => 'var(--mc-surface)',
                'inputbordercolor' => 'var(--mc-border)',
                'inputtextcolor' => 'var(--mc-text)',
                'buttoncolor' => 'var(--mc-primary)',
                'buttontextcolor' => 'var(--mc-text-inverse)',
                'secondarybuttoncolor' => 'var(--mc-primary-light)',
                'secondarybuttontextcolor' => 'var(--mc-primary-active)',
                'buttonradius' => 8,
                'paddingtop' => 34,
                'paddingbottom' => 64,
            ],
        ]];
    }

    /**
     * Contact / help cards (single appearance).
     *
     * @return array
     */
    private static function contactcards_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'Fast support paths',
            'subtitle' => 'Choose the option closest to your issue.',
            'settings' => [
                'bgcolor' => 'var(--mc-surface)',
                'titlecolor' => 'var(--mc-text)',
                'subtitlecolor' => 'var(--mc-text-muted)',
                'cardbgcolor' => 'var(--mc-surface)',
                'cardbordercolor' => 'var(--mc-border)',
                'cardborderwidth' => 1,
                'cardradius' => 8,
                'iconbgcolor' => 'var(--mc-primary-light)',
                'iconcolor' => 'var(--mc-primary)',
                'labelcolor' => 'var(--mc-text)',
                'textcolor' => 'var(--mc-text-muted)',
                'linkcolor' => 'var(--mc-primary)',
                'paddingtop' => 34,
                'paddingbottom' => 64,
                'cards' => [
                    ['icon' => 'credit-card', 'title' => 'Payment issue',
                        'text' => 'Failed charge, duplicate payment, payment confirmation, or checkout error.',
                        'linklabel' => 'Use the form', 'linkurl' => '#'],
                    ['icon' => 'unlock', 'title' => 'Access issue',
                        'text' => 'Paid but cannot open a course, bundle, programme, or subscription product.',
                        'linklabel' => 'Use the form', 'linkurl' => '#'],
                    ['icon' => 'receipt', 'title' => 'Invoice or receipt',
                        'text' => 'Need proof of purchase, billing details, or tax information.',
                        'linklabel' => 'Use the form', 'linkurl' => '#'],
                    ['icon' => 'arrow-counterclockwise', 'title' => 'Refund or cancellation',
                        'text' => 'Ask about eligibility, subscription renewal, or access after refund.',
                        'linklabel' => 'Read refund policy', 'linkurl' => '#'],
                ],
            ],
        ]];
    }

    /**
     * Footer: default / modern-classical / enterprise-navy.
     *
     * @return array
     */
    private static function footer_variants(): array {
        $columns = [
            ['title' => 'Explore', 'links' => "Catalog | #\nAbout | #\nFeatured courses | #"],
            ['title' => 'Products', 'links' => "Courses | #\nBundles | #\nProgrammes | #"],
            ['title' => 'Support', 'links' => "Contact support | #\nRefund policy | #\nLearner dashboard | #"],
            ['title' => 'Legal', 'links' => "Terms | #\nPrivacy | #\nRefunds | #"],
        ];
        $social = [
            ['icon' => 'linkedin', 'url' => '#', 'label' => 'LinkedIn'],
            ['icon' => 'twitter-x', 'url' => '#', 'label' => 'X'],
            ['icon' => 'youtube', 'url' => '#', 'label' => 'YouTube'],
            ['icon' => 'instagram', 'url' => '#', 'label' => 'Instagram'],
        ];
        $base = [
            'logosource' => 'theme',
            'logoheight' => 48,
            'brandname' => 'ModernCommerce',
            'description' => 'Discover practical courses, programmes, and bundles with secure checkout, '
                . 'instant access, and learner support.',
            'address' => '',
            'phone' => '',
            'email' => '',
            'languagelabel' => 'English',
            'subscribeplaceholder' => 'Email address',
            'compliancelabel' => 'Secure learning commerce',
            'bgcolor' => 'var(--mc-secondary)',
            'panelbgcolor' => 'var(--mc-secondary)',
            'titlecolor' => 'var(--mc-text-inverse)',
            'textcolor' => 'var(--mc-primary-light)',
            'linkcolor' => 'var(--mc-primary-light)',
            'iconbgcolor' => 'var(--mc-primary)',
            'iconcolor' => 'var(--mc-text-inverse)',
            'inputbgcolor' => 'var(--mc-surface)',
            'inputbordercolor' => 'var(--mc-primary-border)',
            'inputtextcolor' => 'var(--mc-text)',
            'buttoncolor' => 'var(--mc-primary)',
            'buttontextcolor' => 'var(--mc-text-inverse)',
            'paddingtop' => 72,
            'paddingbottom' => 34,
            'columns' => $columns,
            'appstitle' => 'Get the app',
            'googleplayurl' => '#',
            'appstoreurl' => '#',
            'social' => $social,
            'copyright' => '(c) {year} {sitename}. All rights reserved.',
        ];

        $styles = [
            'enterprise-navy' => ['label' => 'Enterprise Navy', 'mode' => 'dark'],
            'default' => ['label' => 'Default', 'mode' => 'dark'],
            'modern-classical' => ['label' => 'Modern Classical', 'mode' => 'light'],
        ];
        $variants = [];
        foreach ($styles as $style => $meta) {
            $variants[] = [
                'stylelabel' => $meta['label'],
                'title' => 'Footer',
                'settings' => ['style' => $style, 'mode' => $meta['mode']] + $base,
            ];
        }
        return $variants;
    }

    /**
     * Full catalog grid (single appearance), fed by the demo catalog service.
     *
     * @return array
     */
    private static function catalog_variants(): array {
        return [[
            'stylelabel' => 'Default',
            'title' => 'Course catalog',
            'settings' => [
                'title' => 'Explore the full catalog',
                'showheader' => true,
                'perpage' => 12,
                'sidebarposition' => 'left',
                'bgcolor' => 'var(--mc-page-bg)',
                'herobgcolor' => 'var(--mc-secondary)',
                'herobordercolor' => 'var(--mc-secondary)',
                'heroradius' => 8,
                'eyebrowcolor' => 'var(--mc-accent)',
                'titlecolor' => 'var(--mc-text-inverse)',
                'titlefontsize' => 34,
                'textcolor' => 'var(--mc-primary-light)',
                'textfontsize' => 17,
                'accentcolor' => 'var(--mc-accent)',
                'heropanelbgcolor' => 'var(--mc-secondary)',
                'heropanelbordercolor' => 'var(--mc-primary-border)',
                'heropaneltextcolor' => 'var(--mc-primary-light)',
                'heropanelaccentcolor' => 'var(--mc-accent)',
                'heropanelvaluecolor' => 'var(--mc-text-inverse)',
                'heropanelvaluefontsize' => 24,
                'cardbgcolor' => 'var(--mc-surface)',
                'cardbordercolor' => 'var(--mc-border)',
                'cardborderwidth' => 1,
                'cardradius' => 8,
                'cardfooterbgcolor' => 'var(--mc-surface-alt)',
                'cardtitlecolor' => 'var(--mc-text)',
                'cardtitlefontsize' => 19,
                'cardtextcolor' => 'var(--mc-text-muted)',
                'cardmetabgcolor' => 'var(--mc-primary-light)',
                'cardmetatextcolor' => 'var(--mc-primary-active)',
                'ratingcolor' => 'var(--mc-accent)',
                'ratingtextcolor' => 'var(--mc-text)',
                'originalpricecolor' => 'var(--mc-text-muted)',
                'buttoncolor' => 'var(--mc-primary)',
                'buttontextcolor' => 'var(--mc-text-inverse)',
                'buttonradius' => 8,
                'badgebgcolor' => 'var(--mc-accent)',
                'badgebordercolor' => 'var(--mc-accent)',
                'badgetextcolor' => 'var(--mc-secondary)',
                'badgeradius' => 6,
                'badgefontsize' => 12,
                'coursebadgebgcolor' => 'var(--mc-primary-light)',
                'coursebadgebordercolor' => 'var(--mc-primary-border)',
                'coursebadgetextcolor' => 'var(--mc-primary-active)',
                'programbadgebgcolor' => 'var(--mc-primary)',
                'programbadgebordercolor' => 'var(--mc-primary)',
                'programbadgetextcolor' => 'var(--mc-text-inverse)',
                'bundlebadgebgcolor' => 'var(--mc-secondary)',
                'bundlebadgebordercolor' => 'var(--mc-secondary)',
                'bundlebadgetextcolor' => 'var(--mc-text-inverse)',
                'filterbgcolor' => 'var(--mc-surface)',
                'filterbordercolor' => 'var(--mc-border)',
                'filterborderwidth' => 1,
                'filterradius' => 8,
                'filtertitlecolor' => 'var(--mc-text)',
                'filtertextcolor' => 'var(--mc-text-muted)',
                'inputbgcolor' => 'var(--mc-surface)',
                'inputbordercolor' => 'var(--mc-border)',
                'inputtextcolor' => 'var(--mc-text)',
                'placeholdercolor' => 'var(--mc-text-muted)',
                'tabbgcolor' => 'var(--mc-surface)',
                'tabbordercolor' => 'var(--mc-border)',
                'tabtextcolor' => 'var(--mc-text)',
                'tabactivebgcolor' => 'var(--mc-primary)',
                'tabactivetextcolor' => 'var(--mc-text-inverse)',
                'pricecolor' => 'var(--mc-secondary)',
                'paddingtop' => 34,
                'paddingbottom' => 70,
                'paddingleft' => 16,
                'paddingright' => 16,
                'margintop' => 0,
                'marginbottom' => 0,
            ],
        ]];
    }
}

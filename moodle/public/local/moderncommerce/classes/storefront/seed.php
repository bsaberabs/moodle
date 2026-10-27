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
 * Seeds the default storefront widgets for the Modern Commerce public store.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront;

// phpcs:disable moodle.Files.LineLength -- Seed copy keeps bundled storefront content auditable.

use context_system;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\persistent\widget_slide;

/**
 * Idempotently seeds storefront widgets, with an optional full storefront widget reset.
 */
class seed {
    /** @var string Main storefront route. */
    private const CATALOG_URL = '/local/moderncommerce/index.php';
    /** @var string About page route. */
    private const ABOUT_URL = '/local/moderncommerce/about.php';
    /** @var string Support page route. */
    private const SUPPORT_URL = '/local/moderncommerce/support.php';
    /** @var string Terms page route. */
    private const TERMS_URL = '/local/moderncommerce/terms.php';
    /** @var string Privacy page route. */
    private const PRIVACY_URL = '/local/moderncommerce/privacy.php';
    /** @var string Refund policy page route. */
    private const REFUND_URL = '/local/moderncommerce/refund-policy.php';

    /** @var string Shared media: collaborative learning. */
    private const IMG_LEARNERS = 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1800&q=80';
    /** @var string Shared media: strategy workshop. */
    private const IMG_WORKSHOP = 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1800&q=80';
    /** @var string Shared media: online learning. */
    private const IMG_ONLINE = 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1800&q=80';
    /** @var string Shared media: desk learning. */
    private const IMG_DESK = 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1800&q=80';
    /** @var string Shared media: support desk. */
    private const IMG_SUPPORT = 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1800&q=80';

    /**
     * Seed all default widgets.
     *
     * Without reset this is safe to call repeatedly: each widget is only created once per
     * (type, zone, pagetype). With reset it removes only known storefront widgets first.
     *
     * @param bool $reset Whether to overwrite the full storefront widget layout.
     */
    public static function run(bool $reset = false): void {
        if ($reset) {
            self::reset_widgets();
        }

        self::global_widgets();
        self::catalog_page();
        self::about_page();
        self::support_page();
        self::terms_page();
        self::privacy_page();
        self::refund_page();
    }

    /**
     * Seed site-wide global bands.
     */
    private static function global_widgets(): void {
        self::widget('breadcrumb', zones::GLOBAL_TOP, 0, '', '', [
            'style' => 'gradient',
            'subtitle' => 'Find the right course, buy securely, and start learning with confidence.',
            'homelabel' => 'Home',
            'homeurl' => self::CATALOG_URL,
            'sectionlabel' => '',
            'sectionurl' => '',
            'excludedpages' => zones::PAGE_CATALOG,
            'backgroundsource' => 'url',
            'backgroundimage' => self::IMG_WORKSHOP,
            'backgroundfile' => '',
            'bgcolor' => 'var(--mc-secondary)',
            'overlaycolor' => 'var(--mc-secondary)',
            'overlayopacity' => 68,
            'gradientstart' => 'var(--mc-secondary)',
            'gradientend' => 'var(--mc-primary)',
            'textcolor' => 'var(--mc-text-inverse)',
            'breadcrumbcolor' => 'var(--mc-primary-light)',
            'titlecolor' => 'var(--mc-text-inverse)',
            'subtitlecolor' => 'var(--mc-primary-light)',
            'accentcolor' => 'var(--mc-accent)',
            'breadcrumbfontsize' => 14,
            'titlefontsize' => 44,
            'subtitlefontsize' => 18,
            'alignment' => 'center',
            'paddingtop' => 92,
            'paddingbottom' => 92,
        ], zones::PAGE_GLOBAL);

        self::widget('footer', zones::GLOBAL_BOTTOM, 0, 'ModernCommerce footer', '', [
            'style' => 'enterprise-navy',
            'logosource' => 'theme',
            'logoheight' => 48,
            'brandname' => 'ModernCommerce',
            'description' => 'Discover practical courses, programmes, and bundles with secure checkout, instant access, and learner support.',
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
            'columns' => [
                [
                    'title' => 'Explore',
                    'links' => "Catalog | " . self::CATALOG_URL . "\nAbout | " . self::ABOUT_URL . "\nFeatured courses | " . self::CATALOG_URL . "#catalog",
                ],
                [
                    'title' => 'Products',
                    'links' => "Courses | " . self::CATALOG_URL . "?coursetype=Course\nBundles | " . self::CATALOG_URL . "?coursetype=Bundle\nProgrammes | " . self::CATALOG_URL . "?coursetype=Program",
                ],
                [
                    'title' => 'Support',
                    'links' => "Contact support | " . self::SUPPORT_URL . "\nRefund policy | " . self::REFUND_URL . "\nLearner dashboard | /local/moderncommerce/learner/dashboard.php",
                ],
                [
                    'title' => 'Legal',
                    'links' => "Terms | " . self::TERMS_URL . "\nPrivacy | " . self::PRIVACY_URL . "\nRefunds | " . self::REFUND_URL,
                ],
            ],
            'social' => [
                ['icon' => 'linkedin', 'url' => '#', 'label' => 'LinkedIn'],
                ['icon' => 'twitter-x', 'url' => '#', 'label' => 'X'],
                ['icon' => 'youtube', 'url' => '#', 'label' => 'YouTube'],
                ['icon' => 'instagram', 'url' => '#', 'label' => 'Instagram'],
            ],
            'copyright' => '(c) {year} {sitename}. All rights reserved.',
        ], zones::PAGE_GLOBAL);
    }

    /**
     * Seed the catalog / storefront home page.
     */
    private static function catalog_page(): void {
        self::widget('countdown', zones::HOME_ANNOUNCE, 0, 'Enrollment offer', '', [
            'heading' => 'Enrollment offer ends soon',
            'endtime' => time() + (14 * DAYSECS),
            'expiredmessage' => 'The featured offer has ended. Browse the latest course deals.',
            'ctalabel' => 'Explore offers',
            'ctaurl' => self::CATALOG_URL,
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
        ]);

        $heroid = self::widget('slider', zones::HOME_HERO, 0, 'Storefront hero', '', [
            'autoplay' => true,
            'interval' => 6500,
            'showarrows' => true,
            'showdots' => true,
            'design' => 'overlay',
            'buttoncolor' => 'var(--mc-primary)',
            'buttontextcolor' => 'var(--mc-text-inverse)',
            'buttonfontsize' => 16,
            'buttonradius' => 8,
        ]);
        if ($heroid > 0 && !widget_slide::record_exists_select('instanceid = ?', [$heroid])) {
            self::slides($heroid, [
                [
                    'image' => self::IMG_LEARNERS,
                    'heading' => 'Build skills you can use this week',
                    'subheading' => 'Find practical courses, bundles, and programmes with secure checkout and instant access.',
                    'ctalabel' => 'Browse the catalog',
                    'ctaurl' => self::CATALOG_URL,
                    'ctastyle' => 'light',
                    'bgcolor' => 'var(--mc-secondary)',
                ],
                [
                    'image' => self::IMG_WORKSHOP,
                    'heading' => 'Follow a complete learning path',
                    'subheading' => 'Bundle related courses into a focused route from beginner to confident practitioner.',
                    'ctalabel' => 'View bundles',
                    'ctaurl' => self::CATALOG_URL . '?coursetype=Bundle',
                    'ctastyle' => 'primary',
                    'bgcolor' => 'var(--mc-primary)',
                ],
                [
                    'image' => self::IMG_ONLINE,
                    'heading' => 'Earn proof of progress',
                    'subheading' => 'Choose certificate-ready learning and keep every purchase in your learner dashboard.',
                    'ctalabel' => 'Explore programmes',
                    'ctaurl' => self::CATALOG_URL . '?coursetype=Program',
                    'ctastyle' => 'primary',
                    'bgcolor' => 'var(--mc-secondary)',
                ],
            ]);
        }

        self::widget('trustbadges', zones::HOME_BELOWHERO, 0, '', '', [
            'title' => 'Buy with confidence',
            'bgcolor' => 'var(--mc-surface)',
            'titlecolor' => 'var(--mc-text)',
            'titlefontsize' => 24,
            'cardbgcolor' => 'var(--mc-surface)',
            'cardbordercolor' => 'var(--mc-border)',
            'cardborderwidth' => 1,
            'cardradius' => 8,
            'iconbgcolor' => 'var(--mc-primary-light)',
            'iconcolor' => 'var(--mc-primary)',
            'labelcolor' => 'var(--mc-text)',
            'sublabelcolor' => 'var(--mc-text-muted)',
            'paddingtop' => 32,
            'paddingbottom' => 28,
            'badges' => [
                ['icon' => 'shield-check', 'label' => 'Secure checkout', 'sublabel' => 'Protected payment flow'],
                ['icon' => 'unlock', 'label' => 'Instant access', 'sublabel' => 'Start after payment'],
                ['icon' => 'patch-check', 'label' => 'Certificate-ready', 'sublabel' => 'Proof where available'],
                ['icon' => 'headset', 'label' => 'Human support', 'sublabel' => 'Help for access and billing'],
            ],
        ]);

        self::widget('categories', zones::HOME_FEATURED, 0, 'Choose your learning direction', 'Browse by goals, topics, and career stage.', [
            'style' => 'minimal',
            'title' => 'Choose your learning direction',
            'subtitle' => 'Browse by goals, topics, and career stage.',
            'showcount' => true,
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
            'iconcolor' => 'var(--mc-primary)',
            'iconsize' => 26,
            'countcolor' => 'var(--mc-text-muted)',
            'countfontsize' => 14,
            'paddingtop' => 54,
            'paddingbottom' => 34,
        ]);

        self::widget('featured', zones::HOME_FEATURED, 1, 'Popular courses this week', 'High-intent courses learners are buying now.', [
            'coursetype' => '',
            'categoryid' => 0,
            'sort' => 'popular',
            'perpage' => 8,
            'layout' => 'carousel',
            'columns' => 4,
            'align' => 'left',
            'navposition' => 'topright',
            'buttoncolor' => 'var(--mc-primary)',
            'buttontextcolor' => 'var(--mc-text-inverse)',
            'cardbgcolor' => 'var(--mc-surface)',
            'cardbordercolor' => 'var(--mc-border)',
            'cardborderwidth' => 1,
        ]);

        self::widget('related', zones::HOME_FEATURED, 2, 'Build a complete path', 'Save time with bundles and programmes that group related skills.', [
            'coursetype' => 'Bundle',
            'categoryid' => 0,
            'sort' => 'popular',
            'perpage' => 6,
            'layout' => 'grid',
            'columns' => 3,
            'align' => 'center',
            'buttoncolor' => 'var(--mc-primary)',
            'buttontextcolor' => 'var(--mc-text-inverse)',
            'cardbgcolor' => 'var(--mc-surface)',
            'cardbordercolor' => 'var(--mc-border)',
            'cardborderwidth' => 1,
        ]);

        // Appended after the existing home sections: self::widget() never renumbers
        // rows it already created, so reusing an in-use sortorder would collide on a
        // site seeded before this widget existed.
        self::widget(
            'featuregrid',
            zones::HOME_FEATURED,
            3,
            'Built for your whole workforce',
            'Everything your teams need to train, certify, and stay current.',
            [
                'style' => 'topbar',
                'columns' => '3',
                'showheader' => 1,
                'centercards' => 1,
                'cards' => [
                    [
                        'icon' => 'mortarboard-fill',
                        'title' => 'Accredited courses',
                        'text' => 'Standardised training tracks built around the competencies your roles need.',
                        'iconcolor' => '#1e7d47',
                        'accentcolor' => '#1e7d47',
                    ],
                    [
                        'icon' => 'award-fill',
                        'title' => 'E-certificates',
                        'text' => 'Learners download a securely verified certificate as soon as they pass.',
                        'iconcolor' => '#e8a33d',
                        'accentcolor' => '#e8a33d',
                    ],
                    [
                        'icon' => 'phone-fill',
                        'title' => 'Mobile optimised',
                        'text' => 'Bandwidth-efficient rendering so remote teams can learn on local networks.',
                        'iconcolor' => '#0f8ea8',
                        'accentcolor' => '#0f8ea8',
                    ],
                ],
                'bgcolor' => 'var(--mc-page-bg)',
                'titlecolor' => 'var(--mc-secondary)',
                'subtitlecolor' => 'var(--mc-text-muted)',
                'cardbgcolor' => 'var(--mc-surface)',
                'cardbordercolor' => 'var(--mc-border)',
                'cardborderwidth' => 1,
                'cardradius' => 12,
                'iconsize' => 40,
                'cardtitlecolor' => 'var(--mc-text)',
                'textcolor' => 'var(--mc-text-muted)',
                'paddingtop' => 72,
                'paddingbottom' => 72,
            ]
        );

        self::widget('catalog', zones::CATALOG_MAIN, 0, '', '', [
            'title' => 'Explore the full catalog',
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
        ]);

        self::widget('content', zones::CATALOG_SIDEBAR, 0, 'Need help choosing?', 'A short guide can save you hours of searching.', [
            'eyebrow' => 'Advisor tip',
            'icon' => 'compass',
            'layout' => 'card',
            'mediaposition' => 'right',
            'body' => "Tell us your goal, current level, and deadline. We will help you choose a course, bundle, or programme that fits the outcome you want.\n\nBest for teams, career changers, and learners comparing multiple paths.",
            'benefits' => [
                ['number' => '01', 'title' => 'Match your goal', 'text' => 'Pick courses based on outcomes, not only topic names.'],
                ['number' => '02', 'title' => 'Compare paths', 'text' => 'Understand when a bundle or programme is the better buy.'],
                ['number' => '03', 'title' => 'Start cleanly', 'text' => 'Get access and support before your first lesson.'],
            ],
            'imagesource' => 'url',
            'imageurl' => self::IMG_DESK,
            'ctalabel' => 'Contact support',
            'ctaurl' => self::SUPPORT_URL,
            'bgcolor' => 'var(--mc-surface)',
            'panelbgcolor' => 'var(--mc-surface)',
            'panelbordercolor' => 'var(--mc-border)',
            'titlecolor' => 'var(--mc-text)',
            'subtitlecolor' => 'var(--mc-text-muted)',
            'textcolor' => 'var(--mc-text-muted)',
            'iconbgcolor' => 'var(--mc-primary-light)',
            'iconcolor' => 'var(--mc-primary)',
            'buttoncolor' => 'var(--mc-primary)',
            'buttontextcolor' => 'var(--mc-text-inverse)',
            'buttonradius' => 8,
            'cardradius' => 8,
            'paddingtop' => 30,
            'paddingbottom' => 30,
            'paddingleft' => 20,
            'paddingright' => 20,
        ]);

        self::widget('cta', zones::CATALOG_INGRID, 0, '', '', [
            'heading' => 'Get new course drops and bundle offers',
            'text' => 'Join the list for new releases, certificate-ready programmes, and seasonal learning deals.',
            'primarylabel' => 'Subscribe',
            'primaryurl' => '#moderncommerce-newsletter',
            'secondarylabel' => 'Browse all products',
            'secondaryurl' => self::CATALOG_URL,
            'tone' => 'primary',
            'bgcolor' => 'var(--mc-secondary)',
            'titlecolor' => 'var(--mc-text-inverse)',
            'textcolor' => 'var(--mc-primary-light)',
            'primarybuttoncolor' => 'var(--mc-accent)',
            'primarybuttontextcolor' => 'var(--mc-secondary)',
            'secondarybuttoncolor' => 'var(--mc-secondary)',
            'secondarybuttontextcolor' => 'var(--mc-text-inverse)',
            'buttonradius' => 8,
            'cardradius' => 8,
            'paddingtop' => 44,
            'paddingbottom' => 44,
        ]);

        self::widget('testimonials', zones::HOME_SOCIAL, 0, 'Learners use this store to move faster', 'Realistic proof points for course buyers, teams, and career changers.', [
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
                    'quote' => 'The catalog made it easy to compare courses and bundles. I bought the right path and started learning the same day.',
                    'author' => 'Amara O.',
                    'role' => 'Product Designer',
                    'rating' => 5,
                ],
                [
                    'quote' => 'The bundle pricing and certificates gave our team a clear way to train without chasing separate invoices.',
                    'author' => 'David K.',
                    'role' => 'Operations Lead',
                    'rating' => 5,
                ],
                [
                    'quote' => 'The buying process felt clear, and support helped me choose a programme that matched my goal.',
                    'author' => 'Grace T.',
                    'role' => 'Marketing Manager',
                    'rating' => 5,
                ],
            ],
        ]);

        self::widget('instructors', zones::HOME_SOCIAL, 1, 'Learn from practitioners', 'Instructor-led content designed around practical outcomes.', [
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
                ['name' => 'Dr. Amara Obi', 'role' => 'Data and analytics instructor', 'bio' => 'Builds practical analytics courses around dashboards, decisions, and business reporting.', 'photosource' => 'url', 'photourl' => ''],
                ['name' => 'James Whitfield', 'role' => 'Product design mentor', 'bio' => 'Teaches UX, product thinking, and portfolio-ready design workflows for modern teams.', 'photosource' => 'url', 'photourl' => ''],
                ['name' => 'Priya Menon', 'role' => 'Marketing strategist', 'bio' => 'Creates courses on positioning, campaigns, content systems, and measurable growth.', 'photosource' => 'url', 'photourl' => ''],
            ],
        ]);

        self::widget('newsletter', zones::HOME_FOOTER, 0, 'Stay in the loop', '', [
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
        ]);
    }

    /**
     * Seed the About page.
     */
    private static function about_page(): void {
        self::widget('videohero', zones::PAGE_HERO, 0, 'About ModernCommerce learning', '', [
            'heading' => 'A learning store built|for momentum',
            'subtext' => 'ModernCommerce helps learners discover, buy, and start practical courses, programmes, and bundles without friction.',
            'btn_primary_label' => 'Browse courses',
            'btn_primary_url' => self::CATALOG_URL,
            'btn_secondary_label' => 'Contact support',
            'btn_secondary_url' => self::SUPPORT_URL,
            'bgcolor' => 'var(--mc-secondary)',
            'accentcolor' => 'var(--mc-accent)',
            'video_source' => 'url',
            'video_url' => '',
            'video_poster' => self::IMG_LEARNERS,
            'video_title' => 'ModernCommerce learning store',
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
            'infoitems' => [
                ['icon' => 'mortarboard', 'title' => 'Practical courses', 'text' => 'Buy focused learning you can start right away.'],
                ['icon' => 'collection', 'title' => 'Bundles and programmes', 'text' => 'Save with curated paths that connect related skills.'],
                ['icon' => 'patch-check', 'title' => 'Proof of progress', 'text' => 'Use certificates and records where available.'],
            ],
            'quote_text' => 'Premium learning should be easy to compare, purchase, and begin.',
            'quote_author' => 'ModernCommerce',
        ], zones::PAGE_ABOUT);

        self::widget('content', zones::PAGE_MAIN, 0, 'Learning that fits around real life', 'Benefits of buying courses through a focused learning store.', [
            'eyebrow' => 'Benefits of our courses',
            'icon' => 'mortarboard',
            'layout' => 'split',
            'mediaposition' => 'left',
            'body' => 'We bring course discovery, checkout, enrolment, receipts, support, and learner access into one Moodle-native commerce experience.',
            'benefits' => [
                ['number' => '01', 'title' => 'Interactive learning', 'text' => 'Choose practical courses and programmes designed around useful outcomes.'],
                ['number' => '02', 'title' => 'Personalized paths', 'text' => 'Compare single courses, bundles, and programmes before you buy.'],
                ['number' => '03', 'title' => 'Flexible access', 'text' => 'Start after checkout and return through your learner dashboard.'],
            ],
            'imagesource' => 'url',
            'imageurl' => self::IMG_ONLINE,
            'ctalabel' => 'Explore the catalog',
            'ctaurl' => self::CATALOG_URL,
            'bgcolor' => 'var(--mc-surface)',
            'panelbgcolor' => 'var(--mc-surface)',
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
            'paddingtop' => 88,
            'paddingbottom' => 88,
            'paddingleft' => 20,
            'paddingright' => 20,
        ], zones::PAGE_ABOUT);

        // Appended after the existing About sections rather than inserted among them:
        // self::widget() is idempotent and never renumbers rows it already created, so
        // taking an in-use sortorder would collide on any site seeded before this widget
        // existed. Admins can drag it anywhere from the storefront editor.
        self::widget(
            'leadermessage',
            zones::PAGE_MAIN,
            4,
            'Welcome message from the ED/CEO',
            '',
            [
                'style' => 'elevated',
                'mediaposition' => 'right',
                'showeyebrow' => 1,
                'eyebrow' => 'From the leadership',
                'showtitle' => 1,
                'message' => '<p>On behalf of the management and staff, I am delighted to welcome you to our '
                    . 'official learning portal. This platform represents a milestone in our commitment to '
                    . 'making high-quality training available to every member of our workforce.</p>'
                    . '<p>Here you will find accredited, self-paced modules built around the competencies '
                    . 'that matter most in your role. By developing these skills you are helping us raise '
                    . 'the standard of everything we deliver. Welcome, and let us build something better '
                    . 'together.</p>',
                'justifytext' => 0,
                'showphoto' => 1,
                'photosource' => 'url',
                'photourl' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a'
                    . '?auto=format&fit=crop&q=80&w=900&h=1100',
                'showattribution' => 1,
                'leadername' => 'Dr Amina Bello',
                'leaderrole' => 'Executive Director / CEO',
                'showsignature' => 0,
                'showcta' => 0,
                'showaccent' => 1,
                'bgcolor' => 'var(--mc-surface)',
                'accentcolor' => 'var(--mc-primary)',
                'eyebrowcolor' => 'var(--mc-primary)',
                'titlecolor' => 'var(--mc-secondary)',
                'titlefontsize' => 40,
                'textcolor' => 'var(--mc-text-muted)',
                'textfontsize' => 17,
                'namecolor' => 'var(--mc-text)',
                'rolecolor' => 'var(--mc-text-muted)',
                'mediaradius' => 18,
                'mediawidth' => 38,
                'paddingtop' => 80,
                'paddingbottom' => 80,
            ],
            zones::PAGE_ABOUT
        );

        self::widget('learningpromise', zones::PAGE_MAIN, 1, 'Skills are the key to unlocking potential', '', [
            'title' => 'Skills are the key to unlocking potential',
            'body' => 'Whether someone is learning a new tool, changing career direction, or training a team, the buying experience should make the next step obvious and trustworthy.',
            'bgcolor' => 'var(--mc-page-bg)',
            'headingcolor' => 'var(--mc-secondary)',
            'headingfontsize' => 42,
            'textcolor' => 'var(--mc-text-muted)',
            'textfontsize' => 18,
            'paddingtop' => 72,
            'paddingbottom' => 72,
        ], zones::PAGE_ABOUT);

        self::widget('mediastorycarousel', zones::PAGE_MAIN, 2, '', '', [
            'mediaposition' => 'left',
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
            'slides' => [
                [
                    'heading' => 'From discovery to access',
                    'subheading' => 'Learners can compare products, purchase securely, and move straight into the courses they bought.',
                    'mediatype' => 'image',
                    'mediasource' => 'url',
                    'imageurl' => self::IMG_WORKSHOP,
                    'imagefile' => '',
                    'videourl' => '',
                    'videofile' => '',
                    'posterurl' => '',
                    'posterimage' => '',
                    'alt' => 'Course team planning a learning programme',
                ],
                [
                    'heading' => 'A store shaped around outcomes',
                    'subheading' => 'Courses, bundles, and programmes give buyers different levels of commitment without hiding the value proposition.',
                    'mediatype' => 'image',
                    'mediasource' => 'url',
                    'imageurl' => self::IMG_DESK,
                    'imagefile' => '',
                    'videourl' => '',
                    'videofile' => '',
                    'posterurl' => '',
                    'posterimage' => '',
                    'alt' => 'Learner studying online from a desk',
                ],
            ],
        ], zones::PAGE_ABOUT);

        self::widget('belief', zones::PAGE_MAIN, 3, 'We believe', 'Learning is the source of human progress.', [
            'title' => 'We believe',
            'subtitle' => 'Learning is the source of human progress.',
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
                ['icon' => 'people', 'text' => 'Learning stores work best when discovery, checkout, and support feel connected.'],
                ['icon' => 'graph-up-arrow', 'text' => 'Bundles and programmes help learners see a path instead of isolated products.'],
                ['icon' => 'bank', 'text' => 'Trusted commerce creates room for better teaching, clearer records, and better outcomes.'],
            ],
            'closing' => 'ModernCommerce exists to make that path simple, credible, and ready to use.',
        ], zones::PAGE_ABOUT);

        self::widget('instructors', zones::PAGE_ASIDE, 0, 'Instructor spotlight', 'Meet the people behind practical course outcomes.', [
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
            'paddingtop' => 64,
            'paddingbottom' => 64,
            'instructors' => [
                ['name' => 'Maya Santos', 'role' => 'Learning experience lead', 'bio' => 'Designs course flows that help learners move from purchase to practice quickly.', 'photosource' => 'url', 'photourl' => ''],
                ['name' => 'Noah Kim', 'role' => 'Commerce product mentor', 'bio' => 'Teaches product, pricing, analytics, and customer experience for digital stores.', 'photosource' => 'url', 'photourl' => ''],
            ],
        ], zones::PAGE_ABOUT);

        self::widget('featured', zones::PAGE_FOOTER, 0, 'Popular courses and programmes', 'Continue exploring the learning catalog.', [
            'coursetype' => '',
            'categoryid' => 0,
            'sort' => 'popular',
            'perpage' => 6,
            'layout' => 'carousel',
            'columns' => 3,
            'align' => 'left',
            'buttoncolor' => 'var(--mc-primary)',
            'buttontextcolor' => 'var(--mc-text-inverse)',
            'cardbgcolor' => 'var(--mc-surface)',
            'cardbordercolor' => 'var(--mc-border)',
            'cardborderwidth' => 1,
        ], zones::PAGE_ABOUT);

        self::widget('cta', zones::PAGE_FOOTER, 1, '', '', [
            'heading' => 'Ready to choose your next course?',
            'text' => 'Browse the catalog or ask support for help choosing the best path for your goal.',
            'primarylabel' => 'Browse courses',
            'primaryurl' => self::CATALOG_URL,
            'secondarylabel' => 'Contact support',
            'secondaryurl' => self::SUPPORT_URL,
            'tone' => 'primary',
            'bgcolor' => 'var(--mc-primary)',
            'titlecolor' => 'var(--mc-text-inverse)',
            'textcolor' => 'var(--mc-primary-light)',
            'primarybuttoncolor' => 'var(--mc-surface)',
            'primarybuttontextcolor' => 'var(--mc-primary-active)',
            'secondarybuttoncolor' => 'var(--mc-primary)',
            'secondarybuttontextcolor' => 'var(--mc-text-inverse)',
            'buttonradius' => 8,
            'cardradius' => 8,
            'paddingtop' => 54,
            'paddingbottom' => 54,
        ], zones::PAGE_ABOUT);
    }

    /**
     * Seed the Support page.
     */
    private static function support_page(): void {
        self::widget('content', zones::PAGE_HERO, 0, 'Get help with your course purchase', 'Orders, access, invoices, refunds, subscriptions, and enrolment keys.', [
            'eyebrow' => 'Support',
            'icon' => 'life-preserver',
            'layout' => 'centered',
            'body' => 'Tell us what happened and include your order number or purchase email when possible. The clearer the first message, the faster support can help.',
            'ctalabel' => 'Send a request',
            'ctaurl' => '#moderncommerce-support-form',
            'bgcolor' => 'var(--mc-page-bg)',
            'panelbgcolor' => 'var(--mc-surface)',
            'panelbordercolor' => 'var(--mc-border)',
            'titlecolor' => 'var(--mc-secondary)',
            'subtitlecolor' => 'var(--mc-text-muted)',
            'textcolor' => 'var(--mc-text-muted)',
            'iconbgcolor' => 'var(--mc-primary-light)',
            'iconcolor' => 'var(--mc-primary)',
            'buttoncolor' => 'var(--mc-primary)',
            'buttontextcolor' => 'var(--mc-text-inverse)',
            'buttonradius' => 8,
            'paddingtop' => 76,
            'paddingbottom' => 42,
        ], zones::PAGE_SUPPORT);

        self::widget('supportform', zones::PAGE_MAIN, 0, 'How can we help?', 'Send us your order, access, payment, refund, or subscription question.', [
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
        ], zones::PAGE_SUPPORT);

        self::widget('contactcards', zones::PAGE_ASIDE, 0, 'Fast support paths', 'Choose the option closest to your issue.', [
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
                ['icon' => 'credit-card', 'title' => 'Payment issue', 'text' => 'Failed charge, duplicate payment, payment confirmation, or checkout error.', 'linklabel' => 'Use the form', 'linkurl' => '#moderncommerce-support-form'],
                ['icon' => 'unlock', 'title' => 'Access issue', 'text' => 'Paid but cannot open a course, bundle, programme, or subscription product.', 'linklabel' => 'Use the form', 'linkurl' => '#moderncommerce-support-form'],
                ['icon' => 'receipt', 'title' => 'Invoice or receipt', 'text' => 'Need proof of purchase, billing details, or tax information.', 'linklabel' => 'Use the form', 'linkurl' => '#moderncommerce-support-form'],
                ['icon' => 'arrow-counterclockwise', 'title' => 'Refund or cancellation', 'text' => 'Ask about eligibility, subscription renewal, or access after refund.', 'linklabel' => 'Read refund policy', 'linkurl' => self::REFUND_URL],
            ],
        ], zones::PAGE_SUPPORT);

        self::widget('faq', zones::PAGE_FOOTER, 0, 'Before you contact support', 'These details help support respond faster.', [
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
                ['question' => 'What should I include?', 'answer' => 'Include your order number, purchase email, product name, and a short description of the issue.'],
                ['question' => 'Can support grant course access?', 'answer' => 'Support can investigate completed orders and help confirm whether access should be available.'],
                ['question' => 'Where can I see refunds and terms?', 'answer' => 'Use the refund policy and terms pages for the starter rules used by this store.'],
            ],
        ], zones::PAGE_SUPPORT);

        self::widget('cta', zones::PAGE_FOOTER, 1, '', '', [
            'heading' => 'Still choosing a course?',
            'text' => 'Go back to the catalog and compare courses, bundles, and programmes before you buy.',
            'primarylabel' => 'Browse catalog',
            'primaryurl' => self::CATALOG_URL,
            'secondarylabel' => 'Read refund policy',
            'secondaryurl' => self::REFUND_URL,
            'tone' => 'quiet',
            'bgcolor' => 'var(--mc-surface)',
            'titlecolor' => 'var(--mc-text)',
            'textcolor' => 'var(--mc-text-muted)',
            'primarybuttoncolor' => 'var(--mc-primary)',
            'primarybuttontextcolor' => 'var(--mc-text-inverse)',
            'secondarybuttoncolor' => 'var(--mc-primary-light)',
            'secondarybuttontextcolor' => 'var(--mc-primary-active)',
            'buttonradius' => 8,
            'cardradius' => 8,
            'paddingtop' => 36,
            'paddingbottom' => 54,
        ], zones::PAGE_SUPPORT);
    }

    /**
     * Seed the Terms page.
     */
    private static function terms_page(): void {
        self::widget('content', zones::PAGE_HERO, 0, 'Terms for buying and accessing learning products', 'Know what to expect before buying digital courses, bundles, programmes, or subscriptions.', [
            'eyebrow' => 'Buyer terms',
            'icon' => 'file-text',
            'layout' => 'centered',
            'body' => 'These starter terms explain how purchases, learner accounts, course access, subscriptions, coupons, certificates, and support are handled.',
            'bgcolor' => 'var(--mc-page-bg)',
            'panelbgcolor' => 'var(--mc-surface)',
            'panelbordercolor' => 'var(--mc-border)',
            'titlecolor' => 'var(--mc-secondary)',
            'textcolor' => 'var(--mc-text-muted)',
            'iconbgcolor' => 'var(--mc-primary-light)',
            'iconcolor' => 'var(--mc-primary)',
            'paddingtop' => 76,
            'paddingbottom' => 36,
        ], zones::PAGE_TERMS);

        self::widget('policy', zones::PAGE_MAIN, 0, 'Course purchase terms', 'Starter guidance for digital learning purchases.', [
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
                    'body' => 'Courses, programmes, bundles, and subscriptions are digital learning products connected to the learner account used at checkout.',
                    'bullets' => "Access is granted after successful payment.\nSome products can include time limits, prerequisites, certificate rules, or subscription conditions.\nBuyers should keep account credentials secure.",
                ],
                [
                    'heading' => 'Payments, coupons, and subscriptions',
                    'body' => 'Prices, discounts, taxes, fees, and currency display follow the store settings shown at checkout.',
                    'bullets' => "Coupons and promotions can change before checkout is completed.\nSubscription products renew according to the cycle shown at purchase.\nPayment references and order records are retained for support and audit purposes.",
                ],
                [
                    'heading' => 'Learning content and acceptable use',
                    'body' => 'Course content, programme structure, and included resources may be improved over time.',
                    'bullets' => "Sharing paid access outside the purchasing account is not permitted.\nCertificates may depend on completion rules set by the course.\nSupport requests should include the order number and purchase email.",
                ],
            ],
        ], zones::PAGE_TERMS);

        self::widget('faq', zones::PAGE_ASIDE, 0, 'Before you buy', 'Common purchase questions.', [
            'bgcolor' => 'var(--mc-surface)',
            'itembgcolor' => 'var(--mc-surface)',
            'itembordercolor' => 'var(--mc-border)',
            'questioncolor' => 'var(--mc-text)',
            'answercolor' => 'var(--mc-text-muted)',
            'iconcolor' => 'var(--mc-primary)',
            'paddingtop' => 24,
            'paddingbottom' => 34,
            'items' => [
                ['question' => 'When does access begin?', 'answer' => 'Access usually begins after a successful payment and enrolment process.'],
                ['question' => 'Can course content change?', 'answer' => 'Course teams may improve lessons, resources, and programme structure over time.'],
                ['question' => 'Where do I get help?', 'answer' => 'Use the support page and include your order number or purchase email.'],
            ],
        ], zones::PAGE_TERMS);

        self::widget('cta', zones::PAGE_FOOTER, 0, '', '', [
            'heading' => 'Questions before you buy?',
            'text' => 'Contact support if you need help understanding access, subscriptions, refunds, or certificates.',
            'primarylabel' => 'Contact support',
            'primaryurl' => self::SUPPORT_URL,
            'secondarylabel' => 'Browse courses',
            'secondaryurl' => self::CATALOG_URL,
            'tone' => 'quiet',
            'bgcolor' => 'var(--mc-surface)',
            'titlecolor' => 'var(--mc-text)',
            'textcolor' => 'var(--mc-text-muted)',
            'primarybuttoncolor' => 'var(--mc-primary)',
            'primarybuttontextcolor' => 'var(--mc-text-inverse)',
            'secondarybuttoncolor' => 'var(--mc-primary-light)',
            'secondarybuttontextcolor' => 'var(--mc-primary-active)',
            'buttonradius' => 8,
            'cardradius' => 8,
            'paddingtop' => 48,
            'paddingbottom' => 54,
        ], zones::PAGE_TERMS);
    }

    /**
     * Seed the Privacy page.
     */
    private static function privacy_page(): void {
        self::widget('content', zones::PAGE_HERO, 0, 'Privacy for learning commerce', 'How buyer, learner, order, support, and learning-access data can be used.', [
            'eyebrow' => 'Privacy',
            'icon' => 'shield-lock',
            'layout' => 'centered',
            'body' => 'Commerce and learning work together here, so the store uses order data and learner access data to deliver purchases and support.',
            'bgcolor' => 'var(--mc-page-bg)',
            'panelbgcolor' => 'var(--mc-surface)',
            'panelbordercolor' => 'var(--mc-border)',
            'titlecolor' => 'var(--mc-secondary)',
            'textcolor' => 'var(--mc-text-muted)',
            'iconbgcolor' => 'var(--mc-primary-light)',
            'iconcolor' => 'var(--mc-primary)',
            'paddingtop' => 76,
            'paddingbottom' => 36,
        ], zones::PAGE_PRIVACY);

        self::widget('policy', zones::PAGE_MAIN, 0, 'Privacy for learning commerce', 'Starter privacy guidance for the store.', [
            'effectivedate' => 'Review this starter copy with your legal or privacy team',
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
                    'heading' => 'Data used to fulfil orders',
                    'body' => 'The store may use learner account details, purchase email, order records, invoice data, payment references, refund records, and support requests.',
                    'bullets' => "Process orders and receipts.\nGrant access to purchased courses, programmes, bundles, or subscriptions.\nSend transactional communication about purchases and access.",
                ],
                [
                    'heading' => 'Learning access data',
                    'body' => 'Course enrolment, access status, progress, grades, certificates, and activity records may be used to deliver learning and confirm entitlements.',
                    'bullets' => "Maintain learner dashboards.\nShow purchased access and certificate availability.\nSupport audit and troubleshooting.",
                ],
                [
                    'heading' => 'Payment processors and communication',
                    'body' => 'Enabled payment gateways can process payment details. The store keeps payment references and order records, not full card data.',
                    'bullets' => "Support messages are used to respond to buyer questions.\nTransactional emails may include receipts, access details, refund status, and account notices.\nRetention depends on store, legal, and Moodle site policies.",
                ],
            ],
        ], zones::PAGE_PRIVACY);

        self::widget('trustbadges', zones::PAGE_ASIDE, 0, '', '', [
            'title' => 'Trust and data handling',
            'bgcolor' => 'var(--mc-surface)',
            'titlecolor' => 'var(--mc-text)',
            'cardbgcolor' => 'var(--mc-surface)',
            'cardbordercolor' => 'var(--mc-border)',
            'cardborderwidth' => 1,
            'cardradius' => 8,
            'iconbgcolor' => 'var(--mc-primary-light)',
            'iconcolor' => 'var(--mc-primary)',
            'labelcolor' => 'var(--mc-text)',
            'sublabelcolor' => 'var(--mc-text-muted)',
            'paddingtop' => 24,
            'paddingbottom' => 34,
            'badges' => [
                ['icon' => 'shield-lock', 'label' => 'Protected checkout', 'sublabel' => 'Gateway-managed payments'],
                ['icon' => 'receipt', 'label' => 'Order records', 'sublabel' => 'Receipts and access history'],
                ['icon' => 'headset', 'label' => 'Support context', 'sublabel' => 'Messages help resolve issues'],
            ],
        ], zones::PAGE_PRIVACY);

        self::widget('cta', zones::PAGE_FOOTER, 0, '', '', [
            'heading' => 'Need a privacy contact?',
            'text' => 'Use support if you have questions about purchase, access, or communication records.',
            'primarylabel' => 'Contact support',
            'primaryurl' => self::SUPPORT_URL,
            'secondarylabel' => 'Read terms',
            'secondaryurl' => self::TERMS_URL,
            'tone' => 'quiet',
            'bgcolor' => 'var(--mc-surface)',
            'titlecolor' => 'var(--mc-text)',
            'textcolor' => 'var(--mc-text-muted)',
            'primarybuttoncolor' => 'var(--mc-primary)',
            'primarybuttontextcolor' => 'var(--mc-text-inverse)',
            'secondarybuttoncolor' => 'var(--mc-primary-light)',
            'secondarybuttontextcolor' => 'var(--mc-primary-active)',
            'buttonradius' => 8,
            'cardradius' => 8,
            'paddingtop' => 48,
            'paddingbottom' => 54,
        ], zones::PAGE_PRIVACY);
    }

    /**
     * Seed the Refund Policy page.
     */
    private static function refund_page(): void {
        self::widget('content', zones::PAGE_HERO, 0, 'Refunds and cancellations', 'Clear starter rules for refund requests, subscriptions, and access changes.', [
            'eyebrow' => 'Refund policy',
            'icon' => 'arrow-counterclockwise',
            'layout' => 'centered',
            'body' => 'This page explains how refund requests, subscription cancellations, and course access changes are handled for digital learning products.',
            'bgcolor' => 'var(--mc-page-bg)',
            'panelbgcolor' => 'var(--mc-surface)',
            'panelbordercolor' => 'var(--mc-border)',
            'titlecolor' => 'var(--mc-secondary)',
            'textcolor' => 'var(--mc-text-muted)',
            'iconbgcolor' => 'var(--mc-primary-light)',
            'iconcolor' => 'var(--mc-primary)',
            'paddingtop' => 76,
            'paddingbottom' => 36,
        ], zones::PAGE_REFUND);

        self::widget('policy', zones::PAGE_MAIN, 0, 'Refund request guidance', 'Starter buyer-facing refund and cancellation rules.', [
            'effectivedate' => 'Policy applies from purchase date unless your store overrides it',
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
                    'heading' => 'Eligibility',
                    'body' => 'Refund eligibility depends on product type, time since purchase, access usage, completion activity, certificate issue, and subscription rules.',
                    'bullets' => "Digital products may become non-refundable after substantial access.\nKey redemption, certificate issue, or completion can affect eligibility.\nSubscription cancellation stops future billing according to the plan terms.",
                ],
                [
                    'heading' => 'After a refund',
                    'body' => 'Course, programme, bundle, or subscription access can be changed after a full or partial refund.',
                    'bullets' => "Enrolment may be removed.\nIncluded bundle or programme access may change.\nCertificate access may change when access is revoked.",
                ],
                [
                    'heading' => 'How to request help',
                    'body' => 'Contact support with your order number, purchase email, product name, and reason for the request.',
                    'bullets' => "Refund settlement timing depends on the payment gateway and buyer bank.\nSupport may request extra information before processing.\nKeep your order receipt available.",
                ],
            ],
        ], zones::PAGE_REFUND);

        self::widget('faq', zones::PAGE_ASIDE, 0, 'Refund questions', 'Helpful answers before you contact support.', [
            'bgcolor' => 'var(--mc-surface)',
            'itembgcolor' => 'var(--mc-surface)',
            'itembordercolor' => 'var(--mc-border)',
            'questioncolor' => 'var(--mc-text)',
            'answercolor' => 'var(--mc-text-muted)',
            'iconcolor' => 'var(--mc-primary)',
            'paddingtop' => 24,
            'paddingbottom' => 34,
            'items' => [
                ['question' => 'Will I keep course access after a refund?', 'answer' => 'Access may be removed or adjusted after a refund, depending on the product and refund type.'],
                ['question' => 'How long does settlement take?', 'answer' => 'Refund settlement timing depends on the payment gateway and the buyer bank or payment provider.'],
                ['question' => 'What information should I send?', 'answer' => 'Send the order number, purchase email, product name, and reason for the request.'],
            ],
        ], zones::PAGE_REFUND);

        self::widget('cta', zones::PAGE_FOOTER, 0, '', '', [
            'heading' => 'Need refund help?',
            'text' => 'Send support your order number and purchase email so the team can review the request.',
            'primarylabel' => 'Contact support',
            'primaryurl' => self::SUPPORT_URL,
            'secondarylabel' => 'Browse courses',
            'secondaryurl' => self::CATALOG_URL,
            'tone' => 'primary',
            'bgcolor' => 'var(--mc-primary)',
            'titlecolor' => 'var(--mc-text-inverse)',
            'textcolor' => 'var(--mc-primary-light)',
            'primarybuttoncolor' => 'var(--mc-surface)',
            'primarybuttontextcolor' => 'var(--mc-primary-active)',
            'secondarybuttoncolor' => 'var(--mc-primary)',
            'secondarybuttontextcolor' => 'var(--mc-text-inverse)',
            'buttonradius' => 8,
            'cardradius' => 8,
            'paddingtop' => 54,
            'paddingbottom' => 54,
        ], zones::PAGE_REFUND);
    }

    /**
     * Delete only known storefront widget records and slider dependants.
     */
    private static function reset_widgets(): void {
        global $DB;

        // Scoped to the built-ins on purpose: zones::pages() also returns admin-created
        // custom pages, and this method deletes every widget it matches.
        [$pagesql, $pageparams] = $DB->get_in_or_equal(zones::builtin_pages(), SQL_PARAMS_NAMED, 'mcpage');
        $widgets = $DB->get_records_select(widget::TABLE, "pagetype {$pagesql}", $pageparams, '', 'id,type');
        if (empty($widgets)) {
            return;
        }

        $ids = [];
        $sliderids = [];
        foreach ($widgets as $record) {
            $id = (int) $record->id;
            $ids[] = $id;
            if ((string) $record->type === 'slider') {
                $sliderids[] = $id;
            }
        }

        $transaction = $DB->start_delegated_transaction();

        if (!empty($sliderids)) {
            [$slidersql, $sliderparams] = $DB->get_in_or_equal($sliderids, SQL_PARAMS_NAMED, 'mcslider');
            $slides = $DB->get_records_select(widget_slide::TABLE, "instanceid {$slidersql}", $sliderparams, '', 'id');
            if (!empty($slides)) {
                $fs = get_file_storage();
                $context = context_system::instance();
                foreach ($slides as $slide) {
                    $fs->delete_area_files($context->id, 'local_moderncommerce', 'slideimage', (int) $slide->id);
                }
            }
            $DB->delete_records_select(widget_slide::TABLE, "instanceid {$slidersql}", $sliderparams);
        }

        [$idsql, $idparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'mcwidget');
        $DB->delete_records_select(widget::TABLE, "id {$idsql}", $idparams);

        $transaction->allow_commit();
    }

    /**
     * Create a widget if one of the same (type, zone, pagetype) does not already exist.
     *
     * @param string $type Widget type.
     * @param string $zone Zone slug.
     * @param int $sortorder Order within the zone.
     * @param string $title Title.
     * @param string $subtitle Subtitle.
     * @param array $settings Type-specific settings.
     * @param string $pagetype Page type.
     * @param array $styleconfig Universal style config.
     * @return int The widget id (existing or newly created).
     */
    private static function widget(
        string $type,
        string $zone,
        int $sortorder,
        string $title,
        string $subtitle,
        array $settings,
        string $pagetype = zones::PAGE_CATALOG,
        array $styleconfig = []
    ): int {
        $existing = widget::get_record([
            'type' => $type,
            'zone' => $zone,
            'pagetype' => $pagetype,
        ]);
        if ($existing) {
            return (int) $existing->get('id');
        }

        $instance = new widget();
        $instance->set('type', $type);
        $instance->set('zone', $zone);
        $instance->set('pagetype', $pagetype);
        $instance->set('sortorder', $sortorder);
        $instance->set('title', $title);
        $instance->set('subtitle', $subtitle);
        $instance->set('enabled', 1);
        $instance->set('settings', json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if (!empty($styleconfig)) {
            $instance->set('styleconfig', json_encode($styleconfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        $instance->create();

        return (int) $instance->get('id');
    }

    /**
     * Create slides for a slider widget.
     *
     * @param int $instanceid Slider widget id.
     * @param array $slides List of slide field arrays.
     */
    private static function slides(int $instanceid, array $slides): void {
        $sortorder = 0;
        foreach ($slides as $data) {
            $slide = new widget_slide();
            $slide->set('instanceid', $instanceid);
            $slide->set('sortorder', $sortorder++);
            $slide->set('image', (string) ($data['image'] ?? ''));
            $slide->set('heading', (string) ($data['heading'] ?? ''));
            $slide->set('subheading', (string) ($data['subheading'] ?? ''));
            $slide->set('ctalabel', (string) ($data['ctalabel'] ?? ''));
            $slide->set('ctaurl', (string) ($data['ctaurl'] ?? ''));
            $slide->set('ctastyle', (string) ($data['ctastyle'] ?? 'primary'));
            $slide->set('bgcolor', (string) ($data['bgcolor'] ?? ''));
            $slide->set('enabled', 1);
            $slide->create();
        }
    }
}

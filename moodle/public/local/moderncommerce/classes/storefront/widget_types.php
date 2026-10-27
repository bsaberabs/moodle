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
 * Widget type metadata helpers.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront;

/**
 * Presentation helpers for widget types (labels, dedicated editor routing).
 */
class widget_types {
    /** @var string Slider widget type that uses the slide editor. */
    const SLIDER = 'slider';

    /** @var string Featured products carousel/grid. */
    const FEATURED = 'featured';

    /** @var string Related products carousel/grid (shares the featured editor). */
    const RELATED = 'related';

    /** @var string Trust badges strip. */
    const TRUSTBADGES = 'trustbadges';

    /** @var string Partner / client logo band. */
    const PARTNERS = 'partners';

    /** @var string Countdown bar. */
    const COUNTDOWN = 'countdown';

    /** @var string Testimonials grid. */
    const TESTIMONIALS = 'testimonials';

    /** @var string Category tiles. */
    const CATEGORIES = 'categories';

    /** @var string Instructor spotlight grid. */
    const INSTRUCTORS = 'instructors';

    /** @var string Newsletter / lead capture. */
    const NEWSLETTER = 'newsletter';

    /** @var string Split video hero (copy + click-to-play video panel + stat card). */
    const VIDEOHERO = 'videohero';

    /** @var string Side-by-side media and copy carousel. */
    const MEDIASTORYCAROUSEL = 'mediastorycarousel';

    /** @var string Site-wide modern breadcrumb/page-title banner. */
    const BREADCRUMB = 'breadcrumb';

    /** @var string Editable content section for public pages. */
    const CONTENT = 'content';

    /** @var string Centered learning promise statement. */
    const LEARNINGPROMISE = 'learningpromise';

    /** @var string Portrait + signed message from a named leader (ED/CEO, VC, Director). */
    const LEADERMESSAGE = 'leadermessage';

    /** @var string Responsive grid of icon feature/benefit cards (up to 12, 3 or 4 per row). */
    const FEATUREGRID = 'featuregrid';

    /** @var string Subscription plan pricing table (plans, prices, feature lists). */
    const SUBSCRIPTIONPLANS = 'subscriptionplans';

    /** @var string Structured policy sections for terms/privacy/refunds. */
    const POLICY = 'policy';

    /** @var string FAQ accordion/list for public pages. */
    const FAQ = 'faq';

    /** @var string Call-to-action band. */
    const CTA = 'cta';

    /** @var string Commerce support form. */
    const SUPPORTFORM = 'supportform';

    /** @var string Contact/help cards. */
    const CONTACTCARDS = 'contactcards';

    /** @var string Full-width About-page belief statement band. */
    const BELIEF = 'belief';

    /** @var string Site-wide multi-column storefront footer (logo/contact, link columns, apps, social). */
    const FOOTER = 'footer';

    /** @var string The core catalog grid (single, system-managed; not admin-addable). */
    const CATALOG = 'catalog';

    /**
     * All widget types that can be created from the admin UI, in display order.
     *
     * @return string[]
     */
    public static function all(): array {
        return [
            self::SLIDER,
            self::VIDEOHERO,
            self::BREADCRUMB,
            self::FEATURED,
            self::RELATED,
            self::CATEGORIES,
            self::TRUSTBADGES,
            self::PARTNERS,
            self::COUNTDOWN,
            self::TESTIMONIALS,
            self::INSTRUCTORS,
            self::NEWSLETTER,
            self::CONTENT,
            self::MEDIASTORYCAROUSEL,
            self::LEADERMESSAGE,
            self::FEATUREGRID,
            self::SUBSCRIPTIONPLANS,
            self::LEARNINGPROMISE,
            self::BELIEF,
            self::POLICY,
            self::FAQ,
            self::CTA,
            self::SUPPORTFORM,
            self::CONTACTCARDS,
            self::FOOTER,
        ];
    }

    /**
     * Human-readable label for a widget type.
     *
     * @param string $type Widget type key.
     * @return string
     */
    public static function label(string $type): string {
        $key = 'widget_type_' . $type;
        if (get_string_manager()->string_exists($key, 'local_moderncommerce')) {
            return get_string($key, 'local_moderncommerce');
        }
        return ucfirst($type);
    }

    /**
     * Whether a widget type has a dedicated management editor.
     *
     * @param string $type Widget type key.
     * @return bool
     */
    public static function has_editor(string $type): bool {
        return in_array($type, [
            self::SLIDER,
            self::FEATURED,
            self::RELATED,
            self::TRUSTBADGES,
            self::PARTNERS,
            self::COUNTDOWN,
            self::TESTIMONIALS,
            self::CATEGORIES,
            self::INSTRUCTORS,
            self::NEWSLETTER,
            self::VIDEOHERO,
            self::MEDIASTORYCAROUSEL,
            self::LEADERMESSAGE,
            self::FEATUREGRID,
            self::SUBSCRIPTIONPLANS,
            self::BREADCRUMB,
            self::CONTENT,
            self::LEARNINGPROMISE,
            self::BELIEF,
            self::POLICY,
            self::FAQ,
            self::CTA,
            self::SUPPORTFORM,
            self::CONTACTCARDS,
            self::FOOTER,
            self::CATALOG,
        ], true);
    }

    /**
     * Whether a widget type is rendered as a product carousel/grid.
     *
     * @param string $type Widget type key.
     * @return bool
     */
    public static function is_product_list(string $type): bool {
        return in_array($type, [self::FEATURED, self::RELATED], true);
    }
}

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
 * Assembles resolved widgets for a storefront page, grouped by zone.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront;

use local_moderncommerce\storefront\resolver\catalog_resolver;
use local_moderncommerce\storefront\resolver\belief_resolver;
use local_moderncommerce\storefront\resolver\breadcrumb_resolver;
use local_moderncommerce\storefront\resolver\categories_resolver;
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
use local_moderncommerce\storefront\resolver\slider_resolver;
use local_moderncommerce\storefront\resolver\subscriptionplans_resolver;
use local_moderncommerce\storefront\resolver\supportform_resolver;
use local_moderncommerce\storefront\resolver\testimonials_resolver;
use local_moderncommerce\storefront\resolver\trustbadges_resolver;
use local_moderncommerce\storefront\resolver\videohero_resolver;
use local_moderncommerce\persistent\widget;

/**
 * Builds the ordered zone -> widgets payload consumed by the React storefront.
 */
class page_builder {
    /**
     * Map of widget type to resolver class.
     *
     * @return array<string, class-string<\local_moderncommerce\storefront\resolver\widget_resolver>>
     */
    private static function resolvers(): array {
        return [
            'slider' => slider_resolver::class,
            'featured' => featured_resolver::class,
            'related' => featured_resolver::class,
            'trustbadges' => trustbadges_resolver::class,
            'partners' => partners_resolver::class,
            'countdown' => countdown_resolver::class,
            'testimonials' => testimonials_resolver::class,
            'categories' => categories_resolver::class,
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
    }

    /**
     * Build the resolved zones for a page.
     *
     * @param string $pagetype Page type (e.g. 'catalog').
     * @param array $context Page context (e.g. ['courseid' => 42]).
     * @param string $onlyzone Optional single zone slug to limit the build to.
     * @param bool $isloggedin Whether the visitor is an authenticated, non-guest user.
     * @return array List of ['slug' => string, 'widgets' => array[]].
     */
    public static function build(
        string $pagetype,
        array $context = [],
        string $onlyzone = '',
        bool $isloggedin = false
    ): array {
        $context['pagetype'] = $pagetype;

        $validzones = zones::for_page($pagetype);
        if (empty($validzones)) {
            return [];
        }
        if ($onlyzone !== '' && !in_array($onlyzone, $validzones, true)) {
            return [];
        }

        $filters = ['pagetype' => $pagetype, 'enabled' => 1];
        if ($onlyzone !== '') {
            $filters['zone'] = $onlyzone;
        }

        $instances = widget::get_records($filters, 'sortorder', 'ASC');
        $now = time();
        $resolvers = self::resolvers();

        $byzone = [];
        foreach ($instances as $instance) {
            $zone = $instance->get('zone');
            if (!in_array($zone, $validzones, true)) {
                continue;
            }
            if (!$instance->is_active($now, $isloggedin)) {
                continue;
            }

            $type = $instance->get('type');
            if (!isset($resolvers[$type])) {
                continue;
            }

            $resolver = new $resolvers[$type]();
            $payload = $resolver->resolve($instance, $context);

            $byzone[$zone][] = self::envelope($instance, $type, $payload);
        }

        // Emit zones in canonical page order, skipping empty ones.
        $result = [];
        foreach ($validzones as $slug) {
            if (!empty($byzone[$slug])) {
                $result[] = ['slug' => $slug, 'widgets' => $byzone[$slug]];
            }
        }

        return $result;
    }

    /**
     * Wrap a resolved payload in the common widget envelope returned to React.
     *
     * @param widget $instance The instance.
     * @param string $type Widget type.
     * @param array $payload Resolver data payload.
     * @return array
     */
    private static function envelope(widget $instance, string $type, array $payload): array {
        // Presets resolve at render time, so the envelope must carry the merged view --
        // the raw columns would drop preset styling for every widget linked to one.
        $style = $instance->get_resolved_style_array();
        $settings = json_encode(
            $instance->get_resolved_settings_array(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return [
            'id' => (int)$instance->get('id'),
            'type' => $type,
            'sortorder' => (int)$instance->get('sortorder'),
            'title' => (string)$instance->get('title'),
            'subtitle' => (string)$instance->get('subtitle'),
            'settings' => $settings !== '' && $settings !== 'false' ? $settings : '{}',
            'styleconfig' => json_encode($style, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            'data' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'bg' => (string)($style['bg'] ?? ''),
            'spacingtop' => (int)($style['spacingtop'] ?? 0),
            'spacingbottom' => (int)($style['spacingbottom'] ?? 0),
        ];
    }
}

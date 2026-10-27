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
 * Resolver for the subscription plans widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use context_system;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\style_controls;
use local_moderncommerce\subscription\api\feature_api;
use local_moderncommerce\subscription\api\plan_api;
use moodle_url;

/**
 * Builds a public pricing table from the live subscription plans.
 *
 * Plans are read server-side rather than fetched by the browser: every
 * subscription web service requires a login and the subscribe capability, and
 * this widget renders on public storefront pages where the visitor is usually a
 * guest. Only active plans are shown, which is what {@see plan_api::get_all()}
 * already returns.
 *
 * Prices are formatted through the site currency helper, so the per-plan
 * currency column is deliberately ignored — it goes stale when an admin changes
 * the site currency.
 */
class subscriptionplans_resolver implements widget_resolver {
    /** @var int Most plans an instance may render. */
    private const MAX_PLANS = 12;

    /** @var int Most features listed on a single plan card. */
    private const MAX_FEATURES = 20;

    /** @var string[] Supported card treatments. */
    private const STYLES = ['elevated', 'bordered', 'minimal'];

    /** @var int[] Supported desktop densities. */
    private const COLUMNS = [2, 3, 4];

    /**
     * Build the subscription plans payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (unused).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();

        $style = (string) ($settings['style'] ?? 'elevated');
        if (!in_array($style, self::STYLES, true)) {
            $style = 'elevated';
        }

        $columns = (int) ($settings['columns'] ?? 3);
        if (!in_array($columns, self::COLUMNS, true)) {
            $columns = 3;
        }

        $showfeatures = self::truthy($settings['showfeatures'] ?? true);
        $maxfeatures = style_controls::number($settings['maxfeatures'] ?? 10, 10, 0, self::MAX_FEATURES);
        $maxplans = style_controls::number($settings['maxplans'] ?? 4, 4, 1, self::MAX_PLANS);
        $highlight = self::truthy($settings['highlightfeatured'] ?? true);
        $showpromo = self::truthy($settings['showpromo'] ?? true);
        $showtrial = self::truthy($settings['showtrial'] ?? true);
        $showdescription = self::truthy($settings['showdescription'] ?? true);

        // Mode 'both' resolves each cycle into its own group so the browser can
        // switch between them without a round trip. The older '' value meant "no
        // filter" and is treated as 'both', so widgets saved before the switch
        // existed keep showing every plan.
        $mode = (string) ($settings['billingcycle'] ?? 'both');
        if (!in_array($mode, ['both', 'monthly', 'yearly'], true)) {
            $mode = 'both';
        }

        $wanted = $mode === 'both' ? ['monthly', 'yearly'] : [$mode];
        $groups = [];
        foreach ($wanted as $cycle) {
            $groups[$cycle] = $this->plans(
                $settings,
                $ctx,
                $maxplans,
                $showfeatures,
                $maxfeatures,
                $showpromo,
                $showtrial,
                $showdescription,
                $highlight,
                $cycle
            );
        }

        // A cycle with no plans would give the switch a dead tab, so drop it.
        $groups = array_filter($groups, static fn(array $plans): bool => !empty($plans));

        $default = (string) ($settings['defaultcycle'] ?? 'monthly');
        if (!isset($groups[$default])) {
            $default = (string) (array_key_first($groups) ?? 'monthly');
        }

        $cycles = [];
        foreach ($groups as $cycle => $plans) {
            $cycles[] = [
                'key' => $cycle,
                'label' => $cycle === 'yearly'
                    ? self::clean((string) ($settings['yearlylabel'] ?? 'Yearly'), $ctx)
                    : self::clean((string) ($settings['monthlylabel'] ?? 'Monthly'), $ctx),
                'plans' => $plans,
            ];
        }

        // Default straight to the single-plan checkout starter rather than the
        // plan chooser: the visitor has already picked a plan by clicking a card,
        // so sending them to subscribe.php would ask them to pick it again. The
        // plan code is appended per card by the React component.
        $ctaurl = style_controls::url($settings['ctaurl'] ?? '');
        if ($ctaurl === '') {
            $ctaurl = (new moodle_url('/local/moderncommerce/subscribe_plan.php'))->out(false);
        }

        return [
            'style' => $style,
            'columns' => $columns,
            // Every plan for the initially selected cycle. Kept alongside `cycles`
            // so the payload still reads correctly if the switch is not shown.
            'plans' => $groups[$default] ?? [],
            'cycles' => $cycles,
            'defaultcycle' => $default,
            // The switch only earns its place when there is more than one cycle.
            'showcycleswitch' => count($cycles) > 1,
            'savingslabel' => count($cycles) > 1
                ? self::clean((string) ($settings['savingslabel'] ?? ''), $ctx)
                : '',
            // Accessible name for the switch; screen readers announce the group
            // before the individual monthly/yearly tabs.
            'cyclegrouplabel' => get_string('billingcycle', 'local_moderncommerce'),

            'title' => self::clean((string) $instance->get('title'), $ctx),
            'subtitle' => self::clean((string) $instance->get('subtitle'), $ctx),
            'showheader' => self::truthy($settings['showheader'] ?? true),
            'showfeatures' => $showfeatures,
            'ctalabel' => self::clean((string) ($settings['ctalabel'] ?? 'Choose plan'), $ctx),
            'ctaurl' => $ctaurl,
            'featuredlabel' => $highlight
                ? self::clean((string) ($settings['featuredlabel'] ?? 'Most popular'), $ctx)
                : '',

            'bgcolor' => style_controls::colour($settings['bgcolor'] ?? ''),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'subtitlecolor' => style_controls::colour($settings['subtitlecolor'] ?? ''),
            'subtitlefontsize' => style_controls::number($settings['subtitlefontsize'] ?? 0, 0, 0, 96),
            'cardbgcolor' => style_controls::colour($settings['cardbgcolor'] ?? ''),
            'cardbordercolor' => style_controls::colour($settings['cardbordercolor'] ?? ''),
            'cardborderwidth' => style_controls::number($settings['cardborderwidth'] ?? 1, 1, 0, 24),
            'cardradius' => style_controls::number($settings['cardradius'] ?? 12, 12, 0, 96),
            'accentcolor' => style_controls::colour($settings['accentcolor'] ?? ''),
            'plannamecolor' => style_controls::colour($settings['plannamecolor'] ?? ''),
            'plannamefontsize' => style_controls::number($settings['plannamefontsize'] ?? 0, 0, 0, 96),
            'pricecolor' => style_controls::colour($settings['pricecolor'] ?? ''),
            'pricefontsize' => style_controls::number($settings['pricefontsize'] ?? 40, 40, 0, 96),
            'cyclecolor' => style_controls::colour($settings['cyclecolor'] ?? ''),
            'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'featurecolor' => style_controls::colour($settings['featurecolor'] ?? ''),
            'featureiconcolor' => style_controls::colour($settings['featureiconcolor'] ?? ''),
            'buttoncolor' => style_controls::colour($settings['buttoncolor'] ?? ''),
            'buttontextcolor' => style_controls::colour($settings['buttontextcolor'] ?? ''),
            'buttonradius' => style_controls::number($settings['buttonradius'] ?? 8, 8, 0, 96),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 72, 72),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 72, 72),
        ];
    }

    /**
     * Read, filter, and shape the plan cards.
     *
     * @param array $settings Widget settings.
     * @param \context $ctx Context used to clean display strings.
     * @param int $maxplans Cap on how many plans are returned.
     * @param bool $showfeatures Whether feature lists are read at all.
     * @param int $maxfeatures Cap on features per plan.
     * @param bool $showpromo Whether the struck-through regular price is exposed.
     * @param bool $showtrial Whether the trial day count is exposed.
     * @param bool $showdescription Whether the plan description is exposed.
     * @param bool $highlight Whether the featured flag is honoured.
     * @param string $cycle Billing cycle to read, or '' for every cycle.
     * @return array
     */
    private function plans(
        array $settings,
        \context $ctx,
        int $maxplans,
        bool $showfeatures,
        int $maxfeatures,
        bool $showpromo,
        bool $showtrial,
        bool $showdescription,
        bool $highlight,
        string $cycle = ''
    ): array {
        if (!class_exists(plan_api::class)) {
            return [];
        }

        if (!in_array($cycle, ['monthly', 'yearly'], true)) {
            $cycle = '';
        }

        $records = plan_api::get_all($cycle !== '' ? $cycle : null);
        if (empty($records)) {
            return [];
        }

        // Optional allow-list of plan codes, in the order the admin typed them.
        $codes = array_values(array_filter(array_map(
            static fn($code): string => trim((string) $code),
            explode(',', (string) ($settings['plancodes'] ?? ''))
        ), static fn(string $code): bool => $code !== ''));

        if (!empty($codes)) {
            $bycode = [];
            foreach ($records as $record) {
                $bycode[strtolower((string) $record->code)] = $record;
            }
            $filtered = [];
            foreach ($codes as $code) {
                $key = strtolower($code);
                if (isset($bycode[$key])) {
                    $filtered[] = $bycode[$key];
                }
            }
            $records = $filtered;
        }

        $plans = [];
        foreach ($records as $record) {
            $price = (float) plan_api::get_effective_price($record);
            $haspromo = $showpromo && plan_api::has_active_promo($record);

            $plans[] = [
                'id' => (int) $record->id,
                'name' => self::clean((string) $record->name, $ctx),
                'code' => (string) $record->code,
                'description' => $showdescription
                    ? self::clean((string) ($record->description ?? ''), $ctx)
                    : '',
                'billingcycle' => (string) ($record->billing_cycle ?? 'monthly'),
                'isfree' => $price <= 0,
                // Site currency only: the plan's own currency column goes stale.
                'displayprice' => self::price($price),
                'haspromo' => $haspromo,
                'displayregularprice' => $haspromo ? self::price((float) $record->price) : '',
                'trialdays' => $showtrial ? (int) ($record->trial_days ?? 0) : 0,
                'featured' => $highlight && !empty($record->featured),
                'features' => $showfeatures && $maxfeatures > 0
                    ? self::features((int) $record->id, $maxfeatures, $ctx)
                    : [],
            ];

            if (count($plans) >= $maxplans) {
                break;
            }
        }

        return $plans;
    }

    /**
     * Active features attached to a plan through the feature matrix.
     *
     * @param int $planid Plan id.
     * @param int $max Cap on features returned.
     * @param \context $ctx Context used to clean display strings.
     * @return array
     */
    private static function features(int $planid, int $max, \context $ctx): array {
        if (!class_exists(feature_api::class)) {
            return [];
        }

        $out = [];
        foreach (feature_api::get_plan_features($planid) as $feature) {
            $name = self::clean((string) ($feature->name ?? ''), $ctx);
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'icon' => style_controls::icon($feature->icon ?? '', 'check-circle'),
            ];
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * Format an amount in the site currency.
     *
     * @param float $amount Raw amount.
     * @return string
     */
    private static function price(float $amount): string {
        if (class_exists('\local_moderncommerce\services\pricing_service')) {
            return \local_moderncommerce\services\pricing_service::format_price($amount);
        }

        $currency = get_config('local_moderncommerce', 'primary_currency') ?: 'USD';
        return $currency . ' ' . number_format($amount, 2);
    }

    /**
     * Normalise a stored toggle to a boolean.
     *
     * Settings arrive from the editor as strings, so a plain cast is not enough:
     * the string '0' is truthy in PHP.
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

    /**
     * Clean a display string.
     *
     * @param string $value Raw text.
     * @param \context $ctx Context.
     * @return string
     */
    private static function clean(string $value, \context $ctx): string {
        return format_string(trim($value), true, ['context' => $ctx, 'escape' => false]);
    }
}

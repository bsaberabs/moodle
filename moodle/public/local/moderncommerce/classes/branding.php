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
 * Branding seed-colour registry and CSS builder for local_moderncommerce.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce;

/**
 * Single source of truth for the admin-configurable brand colours.
 *
 * Modelled on theme_modernui: the admin picks 8 SEED values (primary, secondary,
 * accent, surface, text, link, muted, radius) and every other --mc-* token is
 * DERIVED from them with CSS color-mix() — the same maths the SCSS source
 * (_tokens.scss) uses at compile time. Status colours (success/warning/danger/
 * info) are fixed and not part of the brand palette.
 */
class branding {
    /** @var array<int, string> Selectors that should receive runtime brand tokens. */
    private const RUNTIME_SCOPES = [
        ':root:root',
        '.path-local-moderncommerce.path-local-moderncommerce',
        '.local-moderncommerce-admin-shell.local-moderncommerce-admin-shell',
        '.local-moderncommerce-storefront.local-moderncommerce-storefront',
        '.mc-learner-shell.mc-learner-shell',
        '.mc-public-page.mc-public-page',
        '.mc-react-mount.mc-react-mount',
        '.mw-zone-render.mw-zone-render',
    ];


    /**
     * The seed colours. Each maps to its base --mc-* custom property, the UI
     * group it appears under, and the tokens derived from it. In a derived
     * expression the placeholder {c} is replaced with a reference to the seed
     * variable (e.g. var(--mc-primary)); other var(--mc-*) references resolve
     * against the same rule because build_css() always emits every seed.
     *
     * @return array<string, array{var: string, group: string, derived: array<string, string>}>
     */
    public static function get_seeds(): array {
        return [
            'brand_primary' => [
                'var' => '--mc-primary',
                'group' => 'brand',
                'derived' => [
                    '--mc-primary-hover' => 'color-mix(in srgb, {c}, black 12%)',
                    '--mc-primary-active' => 'color-mix(in srgb, {c}, black 24%)',
                    '--mc-primary-light' => 'color-mix(in srgb, {c}, white 90%)',
                    '--mc-primary-border' => 'color-mix(in srgb, {c}, white 65%)',
                    '--mc-focus-outline' => '{c}',
                    '--mc-focus-ring' => 'color-mix(in srgb, {c}, transparent 78%)',
                    '--mc-sidebar-active-border' => '{c}',
                    '--mc-sidebar-active-bg' => 'color-mix(in srgb, {c}, transparent 82%)',
                ],
            ],
            'brand_secondary' => [
                'var' => '--mc-secondary',
                'group' => 'brand',
                'derived' => [
                    '--mc-sidebar-bg' => '{c}',
                    '--mc-sidebar-footer-bg' => 'color-mix(in srgb, {c}, black 25%)',
                    '--mc-sidebar-text' => 'color-mix(in srgb, {c}, white 55%)',
                    '--mc-sidebar-text-hover' => 'color-mix(in srgb, {c}, white 90%)',
                    '--mc-sidebar-section-label' => 'color-mix(in srgb, {c}, white 35%)',
                ],
            ],
            'brand_accent' => [
                'var' => '--mc-accent',
                'group' => 'brand',
                'derived' => [
                    '--mc-accent-hover' => 'color-mix(in srgb, {c}, black 12%)',
                    '--mc-accent-text' => 'color-mix(in srgb, {c}, black 70%)',
                ],
            ],
            'brand_surface' => [
                'var' => '--mc-surface',
                'group' => 'surface',
                'derived' => [
                    '--mc-surface-raised' => '{c}',
                    '--mc-surface-alt' => 'color-mix(in srgb, {c}, var(--mc-text) 3%)',
                    '--mc-page-bg' => 'color-mix(in srgb, {c}, var(--mc-text) 4%)',
                ],
            ],
            'brand_text' => [
                'var' => '--mc-text',
                'group' => 'content',
                'derived' => [
                    '--mc-text-inverse' => 'var(--mc-surface)',
                    '--mc-border' => 'color-mix(in srgb, var(--mc-surface), {c} 14%)',
                    '--mc-border-light' => 'color-mix(in srgb, var(--mc-surface), {c} 10%)',
                    '--mc-border-subtle' => 'color-mix(in srgb, var(--mc-surface), {c} 6%)',
                    '--mc-border-strong' => 'color-mix(in srgb, var(--mc-surface), {c} 22%)',
                ],
            ],
            'brand_link' => [
                'var' => '--mc-link',
                'group' => 'content',
                'derived' => [
                    '--mc-link-hover' => 'color-mix(in srgb, {c}, black 20%)',
                ],
            ],
            'brand_muted' => [
                'var' => '--mc-text-muted',
                'group' => 'content',
                'derived' => [
                    '--mc-text-subtle' => 'color-mix(in srgb, {c}, var(--mc-surface) 35%)',
                ],
            ],
        ];
    }

    /**
     * Seed colours grouped for the settings UI (every seed is a colour picker).
     *
     * @return array<string, array<string, array{var: string, type: string}>>
     */
    public static function get_groups(): array {
        $groups = [];
        foreach (self::get_seeds() as $key => $seed) {
            $groups[$seed['group']][$key] = ['var' => $seed['var'], 'type' => 'colour'];
        }
        return $groups;
    }

    /**
     * Resolved derived declarations for a seed: the {c} placeholder replaced with
     * a reference to the seed variable.
     *
     * @param string $seedkey The seed setting key (e.g. brand_primary).
     * @return array<int, array{var: string, expr: string}>
     */
    public static function get_derived(string $seedkey): array {
        $seeds = self::get_seeds();
        if (!isset($seeds[$seedkey])) {
            return [];
        }
        $ref = 'var(' . $seeds[$seedkey]['var'] . ')';
        $out = [];
        foreach ($seeds[$seedkey]['derived'] as $var => $expr) {
            $out[] = ['var' => $var, 'expr' => str_replace('{c}', $ref, $expr)];
        }
        return $out;
    }

    /**
     * Build the inline CSS that applies the brand palette.
     *
     * Mirrors theme_modernui: the full palette is always emitted from the
     * effective seed values (admin override, else design default), so derived
     * tokens that reference other seeds (e.g. page-bg = mix(surface, text)) stay
     * consistent. Doubled selectors (specificity 0,2,0) beat the design system's
     * :root / .local-moderncommerce-admin-shell rules (0,1,0) regardless of the
     * injected <style>'s position in <head>.
     *
     * @return string CSS to inject (never empty in practice, since defaults are
     *                always present), or an empty string only if all are blank.
     */
    public static function build_css(): string {
        $defaults = self::get_defaults();
        $declarations = '';
        $resolved = [];

        foreach (self::get_seeds() as $key => $seed) {
            $value = self::sanitize_field('colour', (string) get_config('local_moderncommerce', $key));
            if ($value === '') {
                $value = (string) ($defaults[$key] ?? '');
            }
            if ($value === '') {
                continue;
            }
            $resolved[$key] = $value;
            $declarations .= $seed['var'] . ':' . $value . ';';
            foreach (self::get_derived($key) as $derived) {
                $declarations .= $derived['var'] . ':' . $derived['expr'] . ';';
            }
        }

        // Corner radius (a length, kept separate from the colour seeds).
        $radius = self::sanitize_field('length', (string) get_config('local_moderncommerce', 'brand_radius'));
        if ($radius === '') {
            $radius = (string) ($defaults['brand_radius'] ?? '');
        }
        if ($radius !== '') {
            $declarations .= '--mc-radius:' . $radius . ';';
        }
        $declarations .= self::bootstrap_variable_bridge($resolved);

        $css = '';
        if ($declarations !== '') {
            $css .= implode(',', self::RUNTIME_SCOPES) . '{'
                . $declarations . '}';
            $css .= self::bootstrap_component_bridge();
        }

        // Advanced raw custom CSS escape hatch (site-admin-only setting).
        $raw = self::sanitize_field('css', (string) get_config('local_moderncommerce', 'customcss'));
        if ($raw !== '') {
            $css .= "\n" . $raw;
        }

        return $css;
    }

    /**
     * Convert common Bootstrap variables used by legacy learner/public markup to
     * the active Modern Commerce brand palette.
     *
     * @param array<string, string> $resolved Effective brand seed values.
     * @return string CSS custom-property declarations.
     */
    private static function bootstrap_variable_bridge(array $resolved): string {
        $declarations = '--bs-primary:var(--mc-primary);'
            . '--bs-link-color:var(--mc-link);'
            . '--bs-link-hover-color:var(--mc-link-hover);'
            . '--bs-body-color:var(--mc-text);'
            . '--bs-dark:var(--mc-text);'
            . '--bs-secondary:var(--mc-text-muted);'
            . '--bs-light:var(--mc-surface-alt);'
            . '--bs-secondary-color:var(--mc-text-muted);'
            . '--bs-body-bg:var(--mc-page-bg);'
            . '--bs-border-color:var(--mc-border);'
            . '--bs-primary-bg-subtle:var(--mc-primary-light);'
            . '--bs-primary-border-subtle:var(--mc-primary-border);'
            . '--bs-primary-text-emphasis:var(--mc-primary-active);';

        $rgbfields = [
            'brand_primary' => '--bs-primary-rgb',
            'brand_link' => '--bs-link-color-rgb',
            'brand_text' => '--bs-dark-rgb',
            'brand_muted' => '--bs-secondary-rgb',
        ];
        foreach ($rgbfields as $key => $var) {
            $rgb = self::hex_to_rgb((string) ($resolved[$key] ?? ''));
            if ($rgb !== '') {
                $declarations .= $var . ':' . $rgb . ';';
            }
        }

        return $declarations;
    }

    /**
     * Override Bootstrap components on Modern Commerce pages where compiled
     * Bootstrap emits static colours instead of reading --bs-primary.
     *
     * @return string CSS rules.
     */
    private static function bootstrap_component_bridge(): string {
        $scope = '.path-local-moderncommerce';

        return $scope . ' .btn-primary{'
            . '--bs-btn-bg:var(--mc-primary);'
            . '--bs-btn-border-color:var(--mc-primary);'
            . '--bs-btn-hover-bg:var(--mc-primary-hover);'
            . '--bs-btn-hover-border-color:var(--mc-primary-hover);'
            . '--bs-btn-active-bg:var(--mc-primary-active);'
            . '--bs-btn-active-border-color:var(--mc-primary-active);'
            . '--bs-btn-color:var(--mc-text-inverse);'
            . '--bs-btn-hover-color:var(--mc-text-inverse);'
            . '--bs-btn-active-color:var(--mc-text-inverse);'
            . '}'
            . $scope . ' .btn-outline-primary{'
            . '--bs-btn-color:var(--mc-primary);'
            . '--bs-btn-border-color:var(--mc-primary);'
            . '--bs-btn-hover-bg:var(--mc-primary);'
            . '--bs-btn-hover-border-color:var(--mc-primary);'
            . '--bs-btn-hover-color:var(--mc-text-inverse);'
            . '--bs-btn-active-bg:var(--mc-primary-active);'
            . '--bs-btn-active-border-color:var(--mc-primary-active);'
            . '--bs-btn-active-color:var(--mc-text-inverse);'
            . '}'
            . $scope . ' .text-primary{color:var(--mc-primary)!important;}'
            . $scope . ' .bg-primary{background-color:var(--mc-primary)!important;}'
            . $scope . ' .border-primary{border-color:var(--mc-primary)!important;}'
            . $scope . ' .progress-bar{background-color:var(--mc-primary);}'
            . $scope . ' .page-item.active .page-link{'
            . 'background-color:var(--mc-primary);'
            . 'border-color:var(--mc-primary);'
            . '}';
    }

    /**
     * Resolve a runtime colour for storefront widgets.
     *
     * Blank values and legacy built-in defaults become CSS variables so widgets
     * follow the configured brand. Custom hex values remain explicit overrides.
     *
     * @param string $value Stored widget value.
     * @param string $token CSS variable/expression fallback.
     * @param array<int, string> $legacydefaults Built-in hex defaults to treat as blank.
     * @return string Safe CSS colour value.
     */
    public static function runtime_colour(string $value, string $token, array $legacydefaults = []): string {
        $value = trim($value);
        $legacydefaults = array_map('strtolower', $legacydefaults);
        if ($value === '' || in_array(strtolower($value), $legacydefaults, true)) {
            return $token;
        }
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
            return strtolower($value);
        }
        if (preg_match('/^var\(--mc-[a-z0-9-]+\)$/', $value)) {
            return $value;
        }

        return $token;
    }

    /**
     * Convert a hex colour to an "r,g,b" tuple for Bootstrap rgb variables.
     *
     * @param string $hex Sanitised hex value.
     * @return string
     */
    private static function hex_to_rgb(string $hex): string {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return '';
        }

        return hexdec(substr($hex, 0, 2)) . ','
            . hexdec(substr($hex, 2, 2)) . ','
            . hexdec(substr($hex, 4, 2));
    }

    /**
     * Flat map of every editable branding field to its value type.
     *
     * @return array<string, string> setting key => type ('colour'|'length'|'css').
     */
    public static function get_editable_fields(): array {
        $fields = [];
        foreach (array_keys(self::get_seeds()) as $key) {
            $fields[$key] = 'colour';
        }
        $fields['brand_radius'] = 'length';
        $fields['customcss'] = 'css';
        return $fields;
    }

    /**
     * Design-system default for each seed (the value shown in the editor and used
     * when the setting is empty). Mirrors the SCSS base palette in _tokens.scss.
     *
     * @return array<string, string> setting key => default CSS value.
     */
    public static function get_defaults(): array {
        return [
            'brand_primary' => '#7c3aed',
            'brand_secondary' => '#1e1b4b',
            'brand_accent' => '#f59e0b',
            'brand_surface' => '#ffffff',
            'brand_text' => '#0f172a',
            'brand_link' => '#7c3aed',
            'brand_muted' => '#64748b',
            'brand_radius' => '8px',
        ];
    }

    /**
     * Sanitise a stored or submitted field value according to its type.
     *
     * @param string $type One of 'colour', 'text', 'length', 'css'.
     * @param string $value Raw value.
     * @return string Sanitised value, or an empty string when blank/invalid.
     */
    public static function sanitize_field(string $type, string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        switch ($type) {
            case 'css':
                // Prevent breaking out of the inline <style> element.
                return str_ireplace('</style>', '', $value);
            case 'colour':
                // Accept #rgb / #rrggbb / #rrggbbaa only.
                return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)
                    ? strtolower($value)
                    : '';
            default:
                // Text (rgba/hsl) and lengths: whitelist safe CSS value characters.
                return trim((string) preg_replace('/[^#a-zA-Z0-9(),.%\s\-]/', '', $value));
        }
    }
}

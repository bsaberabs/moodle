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
 * Style-only widget preset helpers for Modern Commerce storefront widgets.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront;

/**
 * Central whitelist and sanitization service for style-only widget presets.
 */
class preset_service {
    /** @var string Preset table name. */
    public const TABLE = 'local_moderncommerce_widget_preset';

    /** @var string[] Type-specific settings keys that are visual enough for presets. */
    private const SETTINGS_KEYS = [
        'showheader', 'showquote', 'showcount', 'showarrows', 'showdots', 'showfeatures', 'showdescription',
        'showtrial', 'showpromo', 'highlightfeatured', 'showeyebrow', 'showtitle', 'justifytext', 'showphoto',
        'showattribution', 'showsignature', 'showcta', 'showaccent', 'autoplay', 'greyscale', 'pauseonhover',
        'centercards', 'style', 'design', 'layout', 'mode', 'align', 'alignment', 'navposition', 'columns',
        'tone', 'theme', 'mediaposition', 'sidebarposition', 'navicon',
        'bgcolor', 'textcolor', 'headingcolor', 'headingfontsize', 'accentcolor', 'overlaycolor', 'gradientstart',
        'gradientend', 'breadcrumbcolor', 'titlecolor', 'subtitlecolor', 'paddingtop', 'paddingbottom',
        'paddingleft', 'paddingright',
        'herobgcolor', 'herobordercolor', 'heroradius', 'eyebrowcolor', 'heropanelbgcolor',
        'heropanelbordercolor', 'heropaneltextcolor', 'heropanelaccentcolor',
        'heropanelvaluecolor', 'heropanelvaluefontsize',
        'cardradius', 'breadcrumbfontsize', 'titlefontsize', 'subtitlefontsize', 'overlayopacity',
        'primarybuttoncolor', 'primarybuttontextcolor', 'secondarybuttoncolor', 'secondarybuttontextcolor',
        'infocardbgcolor', 'infoiconbgcolor', 'infoiconcolor', 'infoheadingcolor', 'infoheadingfontsize',
        'infotextcolor', 'timerbgcolor', 'timernumbercolor', 'timernumberfontsize', 'timerlabelcolor',
        'timerlabelfontsize', 'buttoncolor', 'buttontextcolor', 'buttonfontsize', 'buttonradius',
        'expiredbgcolor', 'expiredtextcolor',
        'visiblecards', 'iconcolor', 'iconbgcolor', 'iconsize', 'cardbgcolor', 'cardbordercolor', 'cardborderwidth',
        'cardfooterbgcolor',
        'cardtitlecolor', 'cardtitlefontsize', 'cardtextcolor', 'cardtextfontsize', 'cardmetabgcolor',
        'cardmetatextcolor', 'labelcolor', 'labelfontsize', 'sublabelcolor', 'sublabelfontsize',
        'benefitnumbercolor', 'benefitnumberfontsize', 'benefittitlecolor', 'benefittitlefontsize',
        'benefittextcolor', 'benefittextfontsize', 'benefitbordercolor',
        'countcolor', 'countfontsize', 'margintop', 'marginbottom', 'logoheight', 'visiblelogos', 'speed', 'logogap',
        'panelbgcolor', 'panelbordercolor', 'panelborderwidth', 'panelradius', 'panelpaddingtop',
        'panelpaddingright', 'panelpaddingbottom', 'panelpaddingleft', 'inputbgcolor', 'inputbordercolor', 'inputtextcolor',
        'placeholdercolor', 'formlabelcolor', 'linkcolor', 'ratingcolor', 'ratingtextcolor', 'originalpricecolor',
        'avatarbgcolor', 'avatarcolor',
        'quotecolor', 'quotefontsize', 'textfontsize', 'namecolor', 'namefontsize', 'rolecolor', 'rolefontsize',
        'biocolor', 'biofontsize', 'mediaradius', 'questioncolor', 'answercolor', 'itembgcolor', 'eyebrowfontsize',
        'mediawidth', 'itembordercolor', 'pricecolor', 'badgebgcolor', 'badgebordercolor', 'badgetextcolor',
        'plannamecolor', 'plannamefontsize', 'pricefontsize', 'cyclecolor', 'featurecolor', 'featureiconcolor',
        'badgeradius', 'badgefontsize',
        'coursebadgebgcolor', 'coursebadgebordercolor', 'coursebadgetextcolor',
        'programbadgebgcolor', 'programbadgebordercolor', 'programbadgetextcolor',
        'bundlebadgebgcolor', 'bundlebadgebordercolor', 'bundlebadgetextcolor',
        'filterbgcolor', 'filterbordercolor', 'filterborderwidth', 'filterradius', 'filtertitlecolor', 'filtertextcolor',
        'tabbgcolor', 'tabbordercolor', 'tabtextcolor', 'tabactivebgcolor', 'tabactivetextcolor',
    ];

    /** @var string[] Settings keys that are booleans (stored 1/0), not text. */
    private const BOOLEAN_KEYS = [
        'showheader', 'showquote', 'showcount', 'showarrows', 'showdots', 'showfeatures', 'showdescription',
        'showtrial', 'showpromo', 'highlightfeatured', 'showeyebrow', 'showtitle', 'justifytext', 'showphoto',
        'showattribution', 'showsignature', 'showcta', 'showaccent', 'autoplay', 'greyscale', 'pauseonhover',
        'centercards',
    ];

    /** @var string[] Settings keys that must be sanitised as colours. */
    private const COLOUR_KEYS = [
        'bgcolor', 'textcolor', 'headingcolor', 'accentcolor', 'overlaycolor', 'gradientstart', 'gradientend',
        'breadcrumbcolor', 'titlecolor', 'subtitlecolor', 'primarybuttoncolor', 'primarybuttontextcolor',
        'herobgcolor', 'herobordercolor', 'eyebrowcolor', 'heropanelbgcolor', 'heropanelbordercolor',
        'heropaneltextcolor', 'heropanelaccentcolor', 'heropanelvaluecolor',
        'secondarybuttoncolor', 'secondarybuttontextcolor', 'infocardbgcolor', 'infoiconbgcolor', 'infoiconcolor',
        'infoheadingcolor', 'infotextcolor', 'timerbgcolor', 'timernumbercolor', 'timerlabelcolor',
        'buttoncolor', 'buttontextcolor', 'expiredbgcolor', 'expiredtextcolor', 'iconcolor', 'iconbgcolor',
        'cardbgcolor', 'cardbordercolor', 'cardfooterbgcolor', 'cardtitlecolor', 'cardtextcolor', 'cardmetabgcolor',
        'cardmetatextcolor', 'labelcolor', 'sublabelcolor', 'countcolor',
        'benefitnumbercolor', 'benefittitlecolor', 'benefittextcolor', 'benefitbordercolor',
        'panelbgcolor', 'panelbordercolor', 'inputbgcolor', 'inputbordercolor', 'inputtextcolor',
        'placeholdercolor', 'formlabelcolor', 'linkcolor', 'ratingcolor', 'ratingtextcolor', 'avatarbgcolor', 'avatarcolor',
        'quotecolor', 'namecolor', 'rolecolor', 'biocolor', 'questioncolor', 'answercolor', 'itembgcolor',
        'plannamecolor', 'cyclecolor', 'featurecolor', 'featureiconcolor', 'itembordercolor', 'pricecolor',
        'originalpricecolor', 'badgebgcolor', 'badgebordercolor',
        'badgetextcolor', 'coursebadgebgcolor', 'coursebadgebordercolor', 'coursebadgetextcolor',
        'programbadgebgcolor', 'programbadgebordercolor', 'programbadgetextcolor',
        'bundlebadgebgcolor', 'bundlebadgebordercolor', 'bundlebadgetextcolor',
        'filterbgcolor', 'filterbordercolor', 'filtertitlecolor', 'filtertextcolor', 'tabbgcolor',
        'tabbordercolor', 'tabtextcolor', 'tabactivebgcolor', 'tabactivetextcolor',
    ];

    /** @var string[] Settings keys that must be sanitised as pixel/numeric values. */
    private const NUMBER_KEYS = [
        'columns', 'paddingtop', 'paddingbottom', 'paddingleft', 'paddingright', 'heroradius', 'heropanelvaluefontsize',
        'cardradius', 'breadcrumbfontsize', 'titlefontsize',
        'subtitlefontsize', 'overlayopacity', 'infoheadingfontsize', 'headingfontsize',
        'timernumberfontsize', 'timerlabelfontsize', 'buttonfontsize', 'buttonradius',
        'visiblecards', 'iconsize', 'cardborderwidth', 'filterborderwidth', 'cardtitlefontsize',
        'cardtextfontsize', 'labelfontsize', 'sublabelfontsize',
        'benefitnumberfontsize', 'benefittitlefontsize', 'benefittextfontsize',
        'countfontsize', 'margintop', 'marginbottom', 'logoheight', 'visiblelogos', 'speed', 'logogap',
        'badgeradius', 'badgefontsize', 'panelborderwidth', 'panelradius',
        'panelpaddingtop', 'panelpaddingright', 'panelpaddingbottom', 'panelpaddingleft', 'filterradius',
        'quotefontsize', 'textfontsize',
        'namefontsize', 'rolefontsize', 'biofontsize', 'mediaradius', 'eyebrowfontsize', 'mediawidth',
        'plannamefontsize', 'pricefontsize',
    ];

    /** @var string[] Universal styleconfig keys accepted from presets. */
    private const STYLE_KEYS = [
        'bg', 'headingcolor', 'textcolor', 'accentcolor', 'headingfontsize', 'bodyfontsize',
        'spacingtop', 'spacingbottom', 'radius',
    ];

    /**
     * Sanitise a preset name.
     *
     * @param string $name Raw preset name.
     * @return string Clean preset name.
     */
    public static function preset_name(string $name): string {
        $name = trim(clean_param($name, PARAM_TEXT));
        return shorten_text($name, 120, true, '');
    }

    /**
     * Sanitise a widget type key.
     *
     * @param string $type Raw widget type.
     * @return string Clean widget type.
     */
    public static function widget_type(string $type): string {
        $type = clean_param(strtolower(trim($type)), PARAM_ALPHANUMEXT);
        $allowed = array_merge(widget_types::all(), [widget_types::CATALOG]);
        return in_array($type, $allowed, true) ? $type : '';
    }

    /**
     * Sanitise universal wrapper style configuration.
     *
     * @param array $raw Raw decoded style config.
     * @return array Clean style config.
     */
    public static function sanitize_styleconfig(array $raw): array {
        $out = [];
        foreach (self::STYLE_KEYS as $key) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }
            if (in_array($key, ['bg', 'headingcolor', 'textcolor', 'accentcolor'], true)) {
                $out[$key] = self::colour((string) $raw[$key]);
                continue;
            }
            $out[$key] = self::number($raw[$key], 0, in_array($key, ['spacingtop', 'spacingbottom'], true) ? 240 : 96);
        }
        return $out;
    }

    /**
     * Sanitise a type-specific settings patch for preset storage/application.
     *
     * @param array $raw Raw decoded settings patch.
     * @return array Clean patch.
     */
    public static function sanitize_settingspatch(array $raw): array {
        $out = [];
        foreach (self::SETTINGS_KEYS as $key) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }
            if (in_array($key, self::COLOUR_KEYS, true)) {
                $out[$key] = self::colour((string) $raw[$key]);
                continue;
            }
            if (in_array($key, self::BOOLEAN_KEYS, true)) {
                // Stored as 1/0; PARAM_ALPHANUMEXT would keep '0' as a truthy string.
                $out[$key] = self::boolean($raw[$key]) ? 1 : 0;
                continue;
            }
            if (in_array($key, self::NUMBER_KEYS, true)) {
                $max = 240;
                if ($key === 'columns' || $key === 'visiblecards' || $key === 'visiblelogos') {
                    $max = 6;
                } else if ($key === 'speed') {
                    $max = 240;
                } else if ($key === 'logogap') {
                    $max = 160;
                } else if ($key === 'mediawidth') {
                    $max = 60;
                } else if ($key === 'overlayopacity') {
                    $max = 100;
                } else if (str_ends_with($key, 'borderwidth')) {
                    $max = 24;
                } else if (strpos($key, 'fontsize') !== false || $key === 'iconsize') {
                    $max = 96;
                }
                $out[$key] = self::number($raw[$key], 0, $max);
                continue;
            }
            $out[$key] = clean_param((string) $raw[$key], PARAM_ALPHANUMEXT);
        }
        return $out;
    }

    /**
     * Extract style-only fields from an existing widget settings array.
     *
     * @param array $settings Full settings array.
     * @return array Style-only patch.
     */
    public static function extract_settingspatch(array $settings): array {
        return self::sanitize_settingspatch($settings);
    }

    /**
     * Apply a preset settings patch to a settings array.
     *
     * @param array $settings Current settings.
     * @param array $patch Style-only patch.
     * @return array Merged settings.
     */
    public static function apply_settingspatch(array $settings, array $patch): array {
        return array_merge($settings, self::sanitize_settingspatch($patch));
    }

    /**
     * Decode a JSON object string into an array.
     *
     * @param string $json JSON object.
     * @return array
     */
    public static function decode_object(string $json): array {

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Encode an associative settings array as a JSON object, including when empty.
     *
     * @param array $value Sanitised settings.
     * @return string JSON object string.
     */
    public static function encode_object(array $value): string {

        return json_encode($value === [] ? new \stdClass() : $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    /**
     * Normalise a DB preset record for external/API output.
     *
     * @param object $record DB record.
     * @return array
     */
    public static function export_record(object $record): array {
        return [
            'id' => (int) $record->id,
            'type' => (string) $record->type,
            'name' => (string) $record->name,
            'styleconfig' => (string) ($record->styleconfig ?? '{}'),
            'settingspatch' => (string) ($record->settingspatch ?? '{}'),
            'timemodified' => (int) ($record->timemodified ?? 0),
        ];
    }

    /**
     * Sanitise a CSS colour token accepted by widget presets.
     *
     * @param string $value Raw colour.
     * @return string Safe colour or blank.
     */
    private static function colour(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
            return strtolower($value);
        }
        if (preg_match('/^var\(--mc-[a-z0-9-]+\)$/', $value)) {
            return $value;
        }
        return '';
    }

    /**
     * Interpret a stored checkbox setting as a boolean.
     *
     * @param mixed $value Raw value.
     * @return bool
     */
    private static function boolean($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return $value !== '' && $value !== '0' && strtolower($value) !== 'false';
        }

        return !empty($value);
    }

    /**
     * Bound a numeric value for pixel-ish style controls.
     *
     * @param mixed $value Raw value.
     * @param int $min Minimum.
     * @param int $max Maximum.
     * @return int Clean value.
     */
    private static function number($value, int $min, int $max): int {
        return min($max, max($min, (int) round((float) $value)));
    }
}

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

namespace local_moderncommerce\notifications\local;

/**
 * Maps a notification category to its delivery policy.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_registry {
    /** @var string[] Ordered canonical notification categories (the single source of truth). */
    const CATEGORIES = ['transactional', 'reminder', 'dunning', 'celebratory', 'marketing', 'operational'];

    /**
     * Canonical categories as value/label options for selects (e.g. the template-type picker).
     *
     * @return array[] List of ['value' => string, 'label' => string].
     */
    public static function options(): array {
        $out = [];
        foreach (self::CATEGORIES as $cat) {
            $out[] = ['value' => $cat, 'label' => get_string('notifycategory_' . $cat, 'local_moderncommerce')];
        }
        return $out;
    }

    /**
     * Normalise an arbitrary type/category string to a canonical category.
     *
     * Legacy/free-text values (purchase, contact, enrollment, null, ...) collapse to
     * the default transactional category.
     *
     * @param string|null $value Raw value.
     * @return string Canonical category.
     */
    public static function normalise(?string $value): string {
        $value = strtolower(trim((string) $value));
        return in_array($value, self::CATEGORIES, true) ? $value : 'transactional';
    }

    /**
     * Delivery settings for a category.
     *
     * Returns: provider (in-app message provider), priority lane, default channels,
     * and whether marketing suppression applies.
     *
     * @param string $category One of notification::CAT_*.
     * @return array{provider:string, priority:string, channels:array, suppressible:bool}
     */
    public static function settings(string $category): array {
        $ch = ['email', 'inapp'];
        $map = [
            'transactional' => ['provider' => 'commerce', 'priority' => 'high', 'channels' => $ch, 'suppressible' => false],
            'reminder' => ['provider' => 'commerce', 'priority' => 'normal', 'channels' => $ch, 'suppressible' => false],
            'dunning' => ['provider' => 'commerce', 'priority' => 'high', 'channels' => $ch, 'suppressible' => false],
            'celebratory' => ['provider' => 'commerce', 'priority' => 'normal', 'channels' => $ch, 'suppressible' => false],
            'marketing' => ['provider' => 'commerce', 'priority' => 'low', 'channels' => $ch, 'suppressible' => true],
            'operational' => ['provider' => 'adminops', 'priority' => 'normal', 'channels' => $ch, 'suppressible' => false],
        ];

        return $map[$category] ?? $map['transactional'];
    }

    /**
     * The in-app message provider for a category.
     *
     * @param string $category Category.
     * @return string Provider name (commerce|adminops).
     */
    public static function provider(string $category): string {
        return self::settings($category)['provider'];
    }
}

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
 * Storefront widget editor, gallery, renderer, and preset contract tests.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce;

use advanced_testcase;
use local_moderncommerce\storefront\field_schema;
use local_moderncommerce\storefront\gallery_builder;
use local_moderncommerce\storefront\preset_service;
use local_moderncommerce\storefront\widget_types;

/**
 * Guards the contracts shared by the PHP widget layer and both React editors.
 *
 * @coversNothing
 */
final class widget_contract_test extends advanced_testcase {
    /**
     * Every supported widget must have one schema and one gallery section.
     */
    public function test_widget_inventory_and_gallery_are_complete(): void {
        $types = array_merge(widget_types::all(), [widget_types::CATALOG]);

        $this->assertSame($types, gallery_builder::order());
        foreach ($types as $type) {
            $schema = field_schema::for_type($type);
            $this->assertNotEmpty($schema, "Widget {$type} has no editor schema.");

            $names = array_column($schema, 'name');
            $this->assertSame(
                $names,
                array_values(array_unique($names)),
                "Widget {$type} has duplicate schema field names."
            );
        }
    }

    /**
     * The gallery must resolve every variant into the same envelope used by live pages.
     */
    public function test_gallery_builds_every_widget_type(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        foreach (gallery_builder::order() as $type) {
            $zones = gallery_builder::build($type);
            $this->assertCount(1, $zones, "Widget {$type} did not build a gallery zone.");
            $this->assertSame($type, $zones[0]['slug']);
            $this->assertNotEmpty($zones[0]['widgets'], "Widget {$type} has no gallery variants.");

            foreach ($zones[0]['widgets'] as $widget) {
                $this->assertSame($type, $widget['type']);
                $this->assertIsArray(json_decode($widget['settings'], true));
                $this->assertIsArray(json_decode($widget['styleconfig'], true));
                $this->assertIsArray(json_decode($widget['data'], true));
            }
        }
    }

    /**
     * Client and server preset allowlists must never drift apart.
     */
    public function test_react_and_php_preset_keys_match(): void {
        $reactsource = file_get_contents(__DIR__ . '/../js/esm/src/storefront.tsx');
        $this->assertIsString($reactsource);
        $start = strpos($reactsource, 'export const safePresetFieldNames = [');
        $end = strpos($reactsource, 'const safePresetKeys', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $block = substr($reactsource, $start, $end - $start);
        preg_match_all('/"([a-z][a-z0-9_]*)"/', $block, $matches);
        $reactkeys = array_values(array_unique($matches[1]));

        $reflection = new \ReflectionClass(preset_service::class);
        $phpkeys = $reflection->getReflectionConstant('SETTINGS_KEYS')->getValue();
        sort($reactkeys);
        sort($phpkeys);

        $this->assertSame($phpkeys, $reactkeys, 'React and PHP preset allowlists have drifted.');
    }

    /**
     * Every allowed key must survive sanitisation with its expected storage type.
     */
    public function test_every_preset_key_survives_sanitisation(): void {
        $reflection = new \ReflectionClass(preset_service::class);
        $settingskeys = $reflection->getReflectionConstant('SETTINGS_KEYS')->getValue();
        $booleankeys = $reflection->getReflectionConstant('BOOLEAN_KEYS')->getValue();
        $colourkeys = $reflection->getReflectionConstant('COLOUR_KEYS')->getValue();
        $numberkeys = $reflection->getReflectionConstant('NUMBER_KEYS')->getValue();

        foreach ($settingskeys as $key) {
            if (in_array($key, $colourkeys, true)) {
                $sample = '#123456';
            } else if (in_array($key, $booleankeys, true)) {
                $sample = true;
            } else if (in_array($key, $numberkeys, true)) {
                $sample = 5;
            } else {
                $sample = 'default';
            }

            $clean = preset_service::sanitize_settingspatch([$key => $sample]);
            $this->assertArrayHasKey($key, $clean, "Preset key {$key} was discarded.");
        }
    }

    /**
     * Presets must update visual values without replacing widget content.
     */
    public function test_preset_application_preserves_content(): void {
        $settings = [
            'title' => 'Original title',
            'body' => 'Original body',
            'ctaurl' => 'https://example.test/original',
            'items' => [['label' => 'Original row']],
            'bgcolor' => '#ffffff',
        ];
        $rawpatch = [
            'title' => 'Replacement title',
            'body' => 'Replacement body',
            'ctaurl' => 'https://example.test/replacement',
            'items' => [['label' => 'Replacement row']],
            'bgcolor' => '#123456',
            'cardradius' => 24,
        ];

        $merged = preset_service::apply_settingspatch($settings, $rawpatch);

        $this->assertSame('Original title', $merged['title']);
        $this->assertSame('Original body', $merged['body']);
        $this->assertSame('https://example.test/original', $merged['ctaurl']);
        $this->assertSame([['label' => 'Original row']], $merged['items']);
        $this->assertSame('#123456', $merged['bgcolor']);
        $this->assertSame(24, $merged['cardradius']);
    }

    /**
     * Empty style and patch payloads remain JSON objects, never arrays.
     */
    public function test_empty_preset_objects_keep_object_shape(): void {
        $this->assertSame('{}', preset_service::encode_object([]));
        $this->assertSame('{"bgcolor":"#123456"}', preset_service::encode_object(['bgcolor' => '#123456']));
    }
}

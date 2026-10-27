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
 * Release readiness tests for local_moderncommerce.
 *
 * @package    local_moderncommerce
 * @category   test
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_moderncommerce\payment\gateway_manager;
use local_moderncommerce\services\commerce_settings_service;

/**
 * Guards release metadata and stable-install behaviours.
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class release_readiness_test extends advanced_testcase {
    /**
     * Version metadata is ready for a stable Moodle 5.2 release.
     */
    public function test_version_metadata_is_stable(): void {
        global $CFG;

        $plugin = new stdClass();
        require($CFG->dirroot . '/local/moderncommerce/version.php');

        $this->assertSame('local_moderncommerce', $plugin->component);
        $this->assertGreaterThanOrEqual(2026042000, $plugin->requires);
        $this->assertSame([502, 502], $plugin->supported);
        $this->assertSame(MATURITY_STABLE, $plugin->maturity);
        $this->assertStringNotContainsString('beta', strtolower($plugin->release));
    }

    /**
     * Composer platform metadata matches the Moodle 5.2 PHP floor.
     */
    public function test_composer_requires_php_83_or_newer(): void {
        global $CFG;

        $composer = json_decode(
            file_get_contents($CFG->dirroot . '/local/moderncommerce/composer.json'),
            true
        );

        $this->assertSame('>=8.3', $composer['require']['php']);
    }

    /**
     * Open-source licence metadata and packaged notices agree.
     */
    public function test_open_source_licence_metadata_is_complete(): void {
        global $CFG;

        $pluginroot = $CFG->dirroot . '/local/moderncommerce';
        $composer = json_decode(file_get_contents($pluginroot . '/composer.json'), true);
        $licence = file_get_contents($pluginroot . '/LICENSE');

        $this->assertSame('GPL-3.0-or-later', $composer['license']);
        $this->assertStringContainsString('GNU GENERAL PUBLIC LICENSE', $licence);
        $this->assertStringContainsString('Public License v3.0 or later', $licence);
        $this->assertFileExists($pluginroot . '/thirdpartylibs.xml');
    }

    /**
     * Install and upgrade scripts must not silently change site content.
     */
    public function test_install_and_upgrade_do_not_seed_public_content(): void {
        global $CFG;

        $install = file_get_contents($CFG->dirroot . '/local/moderncommerce/db/install.php');
        $upgrade = file_get_contents($CFG->dirroot . '/local/moderncommerce/db/upgrade.php');

        $this->assertStringNotContainsString('enabledashboard', $install);
        $this->assertStringNotContainsString('storefront\\seed::run', $install);
        $this->assertStringNotContainsString('storefront\\seed::run', $upgrade);
    }

    /**
     * Admin settings validation clamps unsafe or invalid values.
     */
    public function test_admin_settings_are_normalized(): void {
        [$settings, $errors] = commerce_settings_service::validate_admin_settings([
            'primary_currency' => 'bad',
            'currency_position' => 'middle',
            'decimal_places' => 99,
            'thousand_separator' => '.',
            'decimal_separator' => '.',
            'support_email' => 'not-an-email',
            'invoice_prefix' => 'inv<script>',
            'receipt_prefix' => 'rcpt<script>',
            'payment_max_retries' => 99,
        ]);

        $this->assertSame('USD', $settings['primary_currency']);
        $this->assertSame('before', $settings['currency_position']);
        $this->assertSame(6, $settings['decimal_places']);
        $this->assertSame(',', $settings['decimal_separator']);
        $this->assertSame('INVSCRIPT', $settings['invoice_prefix']);
        $this->assertSame('RCPTSCRIPT', $settings['receipt_prefix']);
        $this->assertSame(10, $settings['payment_max_retries']);
        $this->assertArrayHasKey('primary_currency', $errors);
        $this->assertArrayHasKey('decimal_places', $errors);
        $this->assertArrayHasKey('support_email', $errors);
    }

    /**
     * Gateway IDs are stable and safe for registry lookups.
     */
    public function test_gateway_ids_are_normalized(): void {
        $this->assertSame('stripetest_1', gateway_manager::normalize_gateway_id(' Stripe Test_1! '));
        $this->assertSame('paypal', gateway_manager::normalize_gateway_id('PayPal'));
    }
}

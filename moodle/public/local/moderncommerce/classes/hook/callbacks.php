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
 * Hook callbacks for local_moderncommerce.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\hook;

use core\hook\output\before_http_headers;
use core\hook\output\before_footer_html_generation;
use core\hook\output\before_standard_head_html_generation;
use core_user\hook\extend_user_menu;
use core_user\hook\extend_default_homepage;
use core\hook\navigation\primary_extend;
use local_moderncommerce\services\access_service;
use local_moderncommerce\services\admin_access_service;
use local_moderncommerce\services\commerce_settings_service;
/**
 * Hook callbacks for Modern Commerce.
 */
class callbacks {
    /** @var string Public catalog URL used as the Moodle default homepage option. */
    private const CATALOG_HOMEPAGE = '/local/moderncommerce/index.php';

    /** @var string[] Allowed toast region positions. */
    private const NOTIFICATION_POSITIONS = [
        'top-left', 'top-center', 'top-right',
        'bottom-left', 'bottom-center', 'bottom-right',
    ];

    /** @var string Default toast region position. */
    private const NOTIFICATION_POSITION_DEFAULT = 'top-right';

    /** @var int Default toast auto-dismiss delay in milliseconds. */
    private const NOTIFICATION_AUTODISMISS_DEFAULT = 4000;

    /** @var string Core CSS bundle loaded on every Modern Commerce page. */
    private const STYLE_CORE = '/local/moderncommerce/styles/design-system.css';

    /** @var array<string, string> Generated route CSS bundles. */
    private const STYLE_BUNDLES = [
        'admin' => '/local/moderncommerce/styles/bundles/admin.css',
        'learner' => '/local/moderncommerce/styles/bundles/learner.css',
        'storefront' => '/local/moderncommerce/styles/bundles/storefront.css',
        'catalog' => '/local/moderncommerce/styles/bundles/catalog.css',
        'public' => '/local/moderncommerce/styles/bundles/public.css',
        'course-detail' => '/local/moderncommerce/styles/bundles/course-detail.css',
        'advanced-features' => '/local/moderncommerce/styles/bundles/advanced-features.css',
        'admin-branding' => '/local/moderncommerce/styles/bundles/admin-branding.css',
        'contact-dashboard' => '/local/moderncommerce/styles/bundles/contact-dashboard.css',
        'icon-browser' => '/local/moderncommerce/styles/bundles/icon-browser.css',
        'component-showcase' => '/local/moderncommerce/styles/bundles/component-showcase.css',
        'admin-gallery' => '/local/moderncommerce/styles/bundles/admin-gallery.css',
        'admin-help' => '/local/moderncommerce/styles/bundles/admin-help.css',
    ];

    /**
     * Callback for before_http_headers hook.
     *
     * Redirects standard signup page to CCP Email First Auth signup
     * when the CCP auth plugin is enabled.
     *
     * @param before_http_headers $hook The hook instance.
     */
    public static function redirect_signup_to_ccp(before_http_headers $hook): void {
        global $CFG;

        // Only process if we're on the standard signup page.
        $requesturi = $_SERVER['REQUEST_URI'] ?? '';
        $scriptname = $_SERVER['SCRIPT_NAME'] ?? '';

        // Check if this is the standard signup page.
        $issignuppage = (
            strpos($scriptname, '/login/signup.php') !== false ||
            strpos($requesturi, '/login/signup.php') !== false
        );

        if (!$issignuppage) {
            return;
        }

        // Check if CCP Email First Auth is enabled.
        if (!is_enabled_auth('ccp')) {
            return;
        }

        // Redirect to CCP signup page.
        $ccpsignupurl = new \moodle_url('/auth/ccp/signup.php');
        redirect($ccpsignupurl);
    }

    /**
     * Redirect anonymous front-page requests to the catalog when it is selected
     * as Moodle's default homepage.
     *
     * Core handles this setting for logged-in users, but anonymous visitors are
     * left on HOMEPAGE_SITE. This keeps public storefront behaviour consistent.
     *
     * @param before_http_headers $hook The hook instance.
     */
    public static function redirect_frontpage_to_catalog(before_http_headers $hook): void {

        global $CFG;
        if ((string)($CFG->defaulthomepage ?? '') !== self::CATALOG_HOMEPAGE) {
            return;
        }
        if (!self::is_frontpage_request()) {
            return;
        }

        $homepageurl = get_default_home_page_url();
        if ($homepageurl === null || $homepageurl->out_as_local_url(false) !== self::CATALOG_HOMEPAGE) {
            return;
        }

        redirect($homepageurl->out(false));
    }

    /**
     * Redirect the core Moodle dashboard to the Modern Commerce learner workspace.
     *
     * @param before_http_headers $hook The hook instance.
     */
    public static function redirect_my_to_learner_dashboard(before_http_headers $hook): void {
        global $PAGE;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $mydashboard = new \moodle_url('/my/index.php');
        if (!$PAGE->url->compare($mydashboard, URL_MATCH_BASE)) {
            return;
        }

        redirect(access_service::learner_dashboard_url());
    }

    /**
     * Check whether the current request is the Moodle site front page.
     *
     * @return bool
     */
    private static function is_frontpage_request(): bool {

        global $CFG;
        $requestpath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (!is_string($requestpath) || $requestpath === '') {
            return false;
        }

        $wwwrootpath = parse_url($CFG->wwwroot, PHP_URL_PATH);
        $basepath = is_string($wwwrootpath) ? '/' . trim($wwwrootpath, '/') : '';
        $basepath = rtrim($basepath, '/');
        $frontpaths = [
            $basepath === '' ? '/' : $basepath . '/', $basepath === '' ? '/index.php' : $basepath . '/index.php',
        ];
        if ($basepath !== '') {
            $frontpaths[] = $basepath;
        }

        return in_array($requestpath, $frontpaths, true);
    }

    /**
     * Callback to add learner dashboard link to user menu.
     *
     * @param extend_user_menu $hook The hook instance.
     */
    public static function add_learner_dashboard_link(extend_user_menu $hook): void {

        global $USER;
        // Only add for logged-in users.
        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Add My Account / Learner Dashboard link (no divider - core handles dividers).
        $myaccount = new \stdClass();
        $myaccount->itemtype = 'link';
        $myaccount->url = new \moodle_url('/local/moderncommerce/learner/index.php');
        $myaccount->title = get_string('myaccount', 'local_moderncommerce');
        $myaccount->titleidentifier = 'myaccount,local_moderncommerce';
        $myaccount->pix = 'i/dashboard';
        $hook->add_navitem($myaccount);

        // Add My Orders link.
        $myorders = new \stdClass();
        $myorders->itemtype = 'link';
        $myorders->url = new \moodle_url('/local/moderncommerce/learner/orders.php');
        $myorders->title = get_string('myorders', 'local_moderncommerce');
        $myorders->titleidentifier = 'myorders,local_moderncommerce';
        $myorders->pix = 'i/report';
        $hook->add_navitem($myorders);

        // Add a divider after our links to separate from Preferences.
        $divider = new \stdClass();
        $divider->itemtype = 'divider';
        $hook->add_navitem($divider);
    }

    /**
     * Add Modern Commerce catalog as a selectable default homepage.
     *
     * @param extend_default_homepage $hook The hook instance.
     */
    public static function extend_default_homepage(extend_default_homepage $hook): void {

        $hook->add_option(
            new \core\url(self::CATALOG_HOMEPAGE),
            new \core\lang_string('defaulthomepagecatalog', 'local_moderncommerce')
        );
    }

    /**
     * Customise the primary navigation: hide selected core nodes, add the learner
     * dashboard link for logged-in users, and add the admin link for managers.
     *
     * @param primary_extend $hook The hook instance.
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        $primaryview = $hook->get_primaryview();

        // Hide selected core primary navigation nodes (mirrors theme-level node hiding).
        // The hook fires after core has added the home/myhome/mycourses/siteadminnode nodes,
        // so we can find them by their node key and remove them.
        $hidden = get_config('local_moderncommerce', 'hideprimarynavitems');
        if (!empty($hidden)) {
            foreach (explode(',', $hidden) as $key) {
                $key = trim($key);
                if ($key === '') {
                    continue;
                }
                if ($node = $primaryview->find($key, null)) {
                    $node->remove();
                }
            }
        }

        // Add the learner dashboard link for all logged-in (non-guest) users.
        if (isloggedin() && !isguestuser()) {
            $learnerlabel = commerce_settings_service::resolve_navigation_label(
                'learnernavlabel',
                'learnernavlabel_default'
            );
            $primaryview->add(
                format_string($learnerlabel),
                access_service::learner_dashboard_url(),
                \navigation_node::TYPE_CUSTOM,
                null,
                'mclearnerdashboard'
            );
        }

        // Add the admin link for users who can access any Modern Commerce admin surface.
        $context = \context_system::instance();
        $adminurl = admin_access_service::resolve_landing_url($context);
        if ($adminurl !== null) {
            $adminlabel = commerce_settings_service::resolve_navigation_label(
                'adminnavlabel',
                'adminnavlabel_default'
            );
            $primaryview->add(
                format_string($adminlabel),
                $adminurl,
                \navigation_node::TYPE_CUSTOM,
                null,
                'ccpadmin'
            );
        }
    }

    /**
     * Inject merchant branding overrides and custom CSS into the page head on
     * Modern Commerce storefront and admin console pages.
     *
     * @param before_standard_head_html_generation $hook The hook instance.
     */
    public static function inject_custom_css(before_standard_head_html_generation $hook): void {
        global $PAGE;

        if (!self::is_moderncommerce_page()) {
            return;
        }

        // Moodle does not revision arbitrary plugin CSS paths automatically.
        // Tie the public URL to the installed plugin version so rebuilt
        // bundles cannot remain hidden behind a browser's stale cache.
        $cssrevision = (int)get_config('local_moderncommerce', 'version');
        foreach (self::get_style_urls_for_current_page() as $cssurl) {
            $PAGE->requires->css(new \moodle_url($cssurl, ['rev' => $cssrevision]));
        }

        $css = \local_moderncommerce\branding::build_css();
        if ($css === '') {
            return;
        }

        $hook->add_html('<style id="mc-custom-css">' . $css . '</style>');
    }

    /**
     * Initialise the floating toast system on Modern Commerce pages.
     *
     * This adopts Moodle's server-rendered #user-notifications alerts into the
     * Modern Commerce toast component and watches for notifications added later
     * by AJAX.
     *
     * @param before_footer_html_generation $hook The hook instance.
     */
    public static function initialise_floating_notifications(before_footer_html_generation $hook): void {
        global $PAGE;

        if (!self::is_moderncommerce_page()) {
            return;
        }

        $PAGE->requires->js_call_amd(
            'local_moderncommerce/floating_notifications',
            'init',
            [self::get_notification_config()]
        );
    }

    /**
     * Check whether the current request is a Modern Commerce page.
     *
     * @return bool
     */
    private static function is_moderncommerce_page(): bool {
        global $PAGE;

        if (!$PAGE->has_set_url()) {
            return false;
        }

        return strpos($PAGE->url->out_as_local_url(false), '/local/moderncommerce/') !== false;
    }

    /**
     * Return the generated CSS URLs needed for the current Modern Commerce route.
     *
     * The output stays centralized here so pages do not reintroduce scattered
     * $PAGE->requires->css() calls.
     *
     * @return string[]
     */
    private static function get_style_urls_for_current_page(): array {
        $route = self::get_current_moderncommerce_route();
        $urls = [self::STYLE_CORE];

        foreach (self::get_style_bundle_keys_for_route($route) as $bundlekey) {
            if (isset(self::STYLE_BUNDLES[$bundlekey])) {
                $urls[] = self::STYLE_BUNDLES[$bundlekey];
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Resolve the current Modern Commerce route path.
     *
     * @return string
     */
    private static function get_current_moderncommerce_route(): string {
        global $PAGE;

        if (!$PAGE->has_set_url()) {
            return '';
        }

        $localurl = $PAGE->url->out_as_local_url(false);
        $path = parse_url($localurl, PHP_URL_PATH);

        return is_string($path) ? $path : '';
    }

    /**
     * Map a Modern Commerce route to generated CSS bundle keys.
     *
     * @param string $route Route path from out_as_local_url(false).
     * @return string[]
     */
    private static function get_style_bundle_keys_for_route(string $route): array {
        $bundles = [];

        $add = static function (string $bundlekey) use (&$bundles): void {
            if (!in_array($bundlekey, $bundles, true)) {
                $bundles[] = $bundlekey;
            }
        };

        $isadmin = strpos($route, '/local/moderncommerce/admin/') === 0 ||
            in_array($route, [
                '/local/moderncommerce/icons_bootstrap.php',
                '/local/moderncommerce/pages.php',
                '/local/moderncommerce/styleguide.php',
            ], true);
        $learnerroutes = [
            '/local/moderncommerce/cart.php',
            '/local/moderncommerce/checkout.php',
            '/local/moderncommerce/order.php',
            '/local/moderncommerce/redeem.php',
            '/local/moderncommerce/redeem_bundle.php',
            '/local/moderncommerce/redeem_multiple.php',
            '/local/moderncommerce/subscribe.php',
            '/local/moderncommerce/success.php',
        ];
        $storefrontroutes = [
            '/local/moderncommerce/index.php',
            '/local/moderncommerce/about.php',
            // Dispatcher for every admin-created custom page.
            '/local/moderncommerce/page.php',
            '/local/moderncommerce/privacy.php',
            '/local/moderncommerce/refund-policy.php',
            '/local/moderncommerce/support.php',
            '/local/moderncommerce/terms.php',
        ];
        $detailroutes = [
            '/local/moderncommerce/course_details.php',
            '/local/moderncommerce/bundle_details.php',
        ];
        $publichelperroutes = [
            '/local/moderncommerce/checkout.php',
            '/local/moderncommerce/pricing.php',
            '/local/moderncommerce/success.php',
        ];
        $contactroutes = [
            '/local/moderncommerce/admin/contacts.php',
            '/local/moderncommerce/admin/pricing.php',
            '/local/moderncommerce/contact/reply.php',
        ];
        $iconroutes = [
            '/local/moderncommerce/icons_bootstrap.php',
        ];
        $showcaseroutes = [
            '/local/moderncommerce/admin/components.php',
            '/local/moderncommerce/styleguide.php',
        ];
        $advancedfeatureroutes = [
            '/local/moderncommerce/admin/course_advanced_features.php',
            '/local/moderncommerce/admin/advanced_bundle_features.php',
        ];

        if ($isadmin) {
            $add('admin');
        }

        if (strpos($route, '/local/moderncommerce/learner/') === 0 || in_array($route, $learnerroutes, true)) {
            $add('learner');
        }

        if (in_array($route, $storefrontroutes, true)) {
            $add('storefront');
            $add('catalog');
            $add('public');
        }

        if (in_array($route, $detailroutes, true)) {
            $add('storefront');
            $add('catalog');
            $add('course-detail');
        }

        if (in_array($route, $publichelperroutes, true)) {
            $add('public');
        }

        if ($route === '/local/moderncommerce/admin/gallery.php') {
            $add('storefront');
            $add('catalog');
            $add('admin-gallery');
        }

        if ($route === '/local/moderncommerce/admin/help/index.php') {
            $add('admin-help');
        }

        if ($route === '/local/moderncommerce/admin/branding.php') {
            $add('admin-branding');
        }

        if (in_array($route, $contactroutes, true)) {
            $add('contact-dashboard');
        }

        if (in_array($route, $iconroutes, true)) {
            $add('icon-browser');
        }

        if (in_array($route, $showcaseroutes, true)) {
            $add('component-showcase');
        }

        if (in_array($route, $advancedfeatureroutes, true)) {
            $add('advanced-features');
        }

        return $bundles;
    }

    /**
     * Resolve the admin-configured floating toast settings.
     *
     * @return array{position: string, autoDismissDelay: int}
     */
    private static function get_notification_config(): array {
        $position = (string) get_config('local_moderncommerce', 'notification_position');
        if (!in_array($position, self::NOTIFICATION_POSITIONS, true)) {
            $position = self::NOTIFICATION_POSITION_DEFAULT;
        }

        $delay = get_config('local_moderncommerce', 'notification_autodismiss');
        $delay = ($delay === false || $delay === '') ? self::NOTIFICATION_AUTODISMISS_DEFAULT : (int) $delay;

        return [
            'position' => $position,
            'autoDismissDelay' => max(0, $delay),
        ];
    }
}

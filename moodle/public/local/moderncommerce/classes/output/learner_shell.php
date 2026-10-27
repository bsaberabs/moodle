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

namespace local_moderncommerce\output;

/**
 * Learner shell helper for rendering the learner dashboard shell with navigation.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner_shell {
    /** @var string The active navigation key */
    private string $activenav;

    /** @var string Pre-rendered content HTML */
    private string $contenthtml;

    /** @var array Breadcrumb items */
    private array $breadcrumb;

    /**
     * Navigation items definition.
     * Centralized location for all learner dashboard menu items.
     *
     * Each item can have:
     * - label: Language string key (component defaults to local_moderncommerce)
     * - labelcomponent: Optional language string component override
     * - url: Relative URL from wwwroot
     * - icon: Bootstrap icon class
     * - plugin: (optional) Plugin component name - item only shows if plugin is installed
     */
    private const NAV_ITEMS = [
        'dashboard' => [
            'label' => 'dashboard',
            'url' => '/local/moderncommerce/learner/index.php#/dashboard',
            'icon' => 'bi-grid-1x2',
        ],
        'catalog' => [
            'label' => 'courselibrary',
            'url' => '/local/moderncommerce/learner/index.php#/library',
            'icon' => 'bi-search',
        ],
        'courses' => [
            'label' => 'mycourses',
            'url' => '/local/moderncommerce/learner/index.php#/courses',
            'icon' => 'bi-play-circle',
        ],
        'bundles' => [
            'label' => 'mybundles',
            'url' => '/local/moderncommerce/learner/index.php#/bundles',
            'icon' => 'bi-layers',
        ],
        'orders' => [
            'label' => 'myorders',
            'url' => '/local/moderncommerce/learner/index.php#/orders',
            'icon' => 'bi-bag-check',
        ],
        'wishlist' => [
            'label' => 'wishlist',
            'url' => '/local/moderncommerce/learner/index.php#/wishlist',
            'icon' => 'bi-heart',
        ],
        'certificates' => [
            'label' => 'mycertificates',
            'url' => '/local/moderncommerce/learner/index.php#/certificates',
            'icon' => 'bi-award',
        ],
        'subscriptions' => [
            'label' => 'mysubscriptions',
            'url' => '/local/moderncommerce/learner/index.php#/subscriptions',
            'icon' => 'bi-credit-card',
            'plugin' => 'local_moderncommerce',
        ],
    ];

    /**
     * Constructor.
     *
     * @param string $activenav The key of the active navigation item (e.g., 'dashboard', 'orders')
     */
    public function __construct(string $activenav = '') {
        $this->activenav = $activenav;
        $this->contenthtml = '';
        $this->breadcrumb = [];
    }

    /**
     * Set the main content HTML.
     *
     * @param string $contenthtml
     * @return self
     */
    public function set_content(string $contenthtml): self {
        $this->contenthtml = $contenthtml;
        return $this;
    }

    /**
     * Set breadcrumb items.
     *
     * @param array $items Array of ['label' => string, 'url' => string, 'isactive' => bool]
     * @return self
     */
    public function set_breadcrumb(array $items): self {
        $this->breadcrumb = $items;
        return $this;
    }

    /**
     * Add a breadcrumb item.
     *
     * @param string $label
     * @param string $url
     * @param bool $isactive
     * @return self
     */
    public function add_breadcrumb(string $label, string $url = '', bool $isactive = false): self {
        $this->breadcrumb[] = [
            'label' => $label,
            'url' => $url,
            'isactive' => $isactive,
        ];
        return $this;
    }

    /**
     * Check if a plugin is installed and enabled.
     *
     * @param string $pluginname The full component name (e.g., 'local_moderncommerce')
     * @return bool
     */
    private static function is_plugin_available(string $pluginname): bool {
        static $cache = [];

        if (!isset($cache[$pluginname])) {
            $pluginman = \core_plugin_manager::instance();
            $plugininfo = $pluginman->get_plugin_info($pluginname);
            $cache[$pluginname] = ($plugininfo !== null && $plugininfo->is_installed_and_upgraded());
        }

        return $cache[$pluginname];
    }

    /**
     * Check whether the optional learner subscription integration is available.
     *
     * @return bool
     */
    public static function subscriptions_available(): bool {
        return self::is_plugin_available('local_moderncommerce');
    }

    /**
     * Build the navigation data for the template.
     *
     * @return array Navigation items
     */
    public function get_navigation(): array {
        global $CFG;

        $items = [];
        foreach (self::NAV_ITEMS as $itemkey => $item) {
            // Check if this item requires a plugin that isn't installed.
            if (!empty($item['plugin']) && !self::is_plugin_available($item['plugin'])) {
                continue; // Skip this item.
            }

            // Get the label from language string.
            $component = $item['labelcomponent'] ?? 'local_moderncommerce';
            $label = get_string($item['label'], $component);

            $items[] = [
                'key' => $itemkey,
                'label' => $label,
                'url' => $CFG->wwwroot . $item['url'],
                'icon' => $item['icon'],
                'active' => ($itemkey === $this->activenav),
            ];
        }

        return $items;
    }

    /**
     * Build the legacy nav.* active flags for backward compatibility.
     *
     * @return array
     */
    public function get_nav_active_flags(): array {
        $flags = [];
        foreach (self::NAV_ITEMS as $itemkey => $item) {
            // Check if this item requires a plugin that isn't installed.
            if (!empty($item['plugin']) && !self::is_plugin_available($item['plugin'])) {
                continue; // Skip this item.
            }
            $flags[$itemkey . 'active'] = ($itemkey === $this->activenav);
        }
        return $flags;
    }

    /**
     * Get user profile data for the sidebar.
     *
     * @return array
     */
    public function get_user_data(): array {
        global $USER, $PAGE;

        $userpicture = new \user_picture($USER);
        $userpicture->size = 100;

        // When the learner's journey began: account creation date, falling back to first access.
        $joined = (int) ($USER->timecreated ?: $USER->firstaccess);

        // Same gate the profile page uses, so the sidebar avatar editor is consistent.
        $editstate = \local_moderncommerce\services\learner_profile_service::get_profile_edit_state($USER);

        return [
            'id' => (int) $USER->id,
            'fullname' => fullname($USER),
            'initials' => self::initials(fullname($USER)),
            'avatarurl' => $userpicture->get_url($PAGE)->out(false),
            'membersince' => $joined > 0
                ? get_string('membersince', 'local_moderncommerce', userdate($joined, '%B %Y'))
                : '',
            'canedit' => !empty($editstate['canedit']),
            'editmessage' => (string) $editstate['message'],
        ];
    }

    /**
     * Generate initials from a full name.
     *
     * @param string $fullname Full name.
     * @return string Initials.
     */
    private static function initials(string $fullname): string {

        $parts = preg_split('/\s+/', trim($fullname));
        $initials = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $initials .= \core_text::strtoupper(\core_text::substr($part, 0, 1));
            }

            if (\core_text::strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : '?';
    }
    /**
     * Number of items in the current user's active cart.
     *
     * Drives the sidebar cart badge. Guests have no cart, so they always read zero.
     *
     * @return int
     */
    public static function get_cart_count(): int {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return 0;
        }

        return \local_moderncommerce\api\cart_api::get_cart_count((int) $USER->id);
    }

    /**
     * Get user stats for the sidebar.
     *
     * @return array
     */
    public function get_user_stats(): array {
        global $CFG, $USER, $DB;

        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/enrollib.php');

        // Get enrolled courses.
        $enrolledcourses = enrol_get_my_courses('id', 'visible DESC, fullname ASC');

        // Count completed courses.
        $completedcourses = 0;
        foreach ($enrolledcourses as $course) {
            $completion = new \completion_info($course);
            if ($completion->is_enabled() && $completion->is_course_complete($USER->id)) {
                $completedcourses++;
            }
        }

        // Get certificate count from Course Certificate records when available.
        $certificatescount = 0;
        if (
            $DB->record_exists('modules', ['name' => 'coursecertificate'])
            && $DB->get_manager()->table_exists(new \xmldb_table('tool_certificate_issues'))
        ) {
            $certificatescount = $DB->count_records_select(
                'tool_certificate_issues',
                'userid = :userid AND archived = :archived',
                [
                    'userid' => (int)$USER->id,
                    'archived' => 0,
                ]
            );
        } else if ($DB->get_manager()->table_exists(new \xmldb_table('customcert_issues'))) {
            $certificatescount = $DB->count_records('customcert_issues', ['userid' => $USER->id]);
        }

        return [
            'courses' => count($enrolledcourses),
            'completedcourses' => $completedcourses,
            'certificates' => $certificatescount,
        ];
    }

    /**
     * Get common React layout context for learner pages.
     *
     * @return array
     */
    public function get_react_layout_context(): array {
        return [
            'user' => $this->get_user_data(),
            'stats' => $this->get_user_stats(),
            'features' => [
                'subscriptions' => self::subscriptions_available(),
            ],
            // Seeds the sidebar cart badge so it is correct on first paint rather than
            // flashing in once the cart request resolves.
            'cartcount' => self::get_cart_count(),
            'avatar' => [
                'savemethod' => 'local_moderncommerce_save_learner_profile_picture',
            ],
            'labels' => [
                'accesslibrary' => get_string('accesslibrary', 'local_moderncommerce'),
                'bundleenrollmentkeys' => get_string('bundleenrollmentkeys', 'local_moderncommerce'),
                'calendar' => get_string('calendar', 'local_moderncommerce'),
                'cart' => get_string('cart', 'local_moderncommerce'),
                'cartitems' => get_string('cartitems', 'local_moderncommerce'),
                'checkout' => get_string('checkout', 'local_moderncommerce'),
                'courselibrary' => get_string('courselibrary', 'local_moderncommerce'),
                'dashboard' => get_string('dashboard', 'local_moderncommerce'),
                'doneactivities' => get_string('doneactivities', 'local_moderncommerce'),
                'donecourses' => get_string('donecourses', 'local_moderncommerce'),
                'learner' => get_string('learner', 'local_moderncommerce'),
                'learnernavigation' => get_string('learnernavigation', 'local_moderncommerce'),
                'learnerworkspace' => get_string('learnerworkspace', 'local_moderncommerce'),
                'mybundles' => get_string('mybundles', 'local_moderncommerce'),
                'mycertificates' => get_string('mycertificates', 'local_moderncommerce'),
                'mycourses' => get_string('mycourses', 'local_moderncommerce'),
                'mygrades' => get_string('mygrades', 'local_moderncommerce'),
                'myorders' => get_string('myorders', 'local_moderncommerce'),
                'myprofile' => get_string('myprofile', 'local_moderncommerce'),
                'ordersandinvoices' => get_string('ordersandinvoices', 'local_moderncommerce'),
                'redeemkeys' => get_string('redeemkeys', 'local_moderncommerce'),
                'subscriptions' => get_string('subscriptions', 'local_moderncommerce'),
                'welcomeback' => get_string('welcomeback', 'local_moderncommerce'),
                'wishlist' => get_string('wishlist', 'local_moderncommerce'),
            ],
        ];
    }

    /**
     * Get the template context for rendering.
     *
     * Note: We do NOT fetch notifications here. Let Moodle's native toast system
     * handle them via the layout template (learner.mustache includes the toast wrapper).
     * Fetching notifications here would consume them and prevent the core toast system
     * from working.
     *
     * @return array
     */
    public function get_template_context(): array {
        global $OUTPUT, $PAGE;

        $navigation = $this->get_navigation();

        return [
            'user' => $this->get_user_data(),
            'stats' => $this->get_user_stats(),
            'nav' => $this->get_nav_active_flags(),
            'navigation' => $navigation,
            'hasnavigation' => !empty($navigation),
            'breadcrumb' => !empty($this->breadcrumb) ? ['items' => $this->breadcrumb] : null,
            'contenthtml' => $this->contenthtml,
            'catalogurl' => (new \moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/library',
            'dashboardurl' => (new \moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/dashboard',
            'coursesurl' => (new \moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/courses',
            'ordersurl' => (new \moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/orders',
        ];
    }

    /**
     * Render the shell template.
     *
     * @param \renderer_base $output The renderer
     * @return string The rendered HTML
     */
    public function render(\renderer_base $output): string {
        return $output->render_from_template('local_moderncommerce/learner_shell', $this->get_template_context());
    }

    /**
     * Static factory method for quick usage.
     *
     * @param string $activenav The active navigation key
     * @return self
     */
    public static function create(string $activenav): self {
        return new self($activenav);
    }
}

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
 * Library functions for Modern Commerce
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Check whether another Moodle plugin is installed and upgraded.
 *
 * @param string $pluginname Full component name.
 * @return bool
 */
function local_moderncommerce_is_plugin_available(string $pluginname): bool {
    static $cache = [];

    if (!array_key_exists($pluginname, $cache)) {
        $pluginman = core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info($pluginname);
        $cache[$pluginname] = $plugininfo !== null && $plugininfo->is_installed_and_upgraded();
    }

    return $cache[$pluginname];
}

/**
 * Whether the subscription subsystem is available.
 *
 * Subscriptions are now part of Modern Commerce core, so this is always true.
 * Retained for backward compatibility with callers/templates.
 *
 * @return bool
 */
function local_moderncommerce_subscription_plugin_available(): bool {
    return true;
}

/**
 * Render a standalone fallback for optional subscription pages.
 *
 * @return string HTML.
 */
function local_moderncommerce_subscription_unavailable_html(): string {
    $html = html_writer::tag(
        'span',
        html_writer::tag('i', '', ['class' => 'bi bi-credit-card', 'aria-hidden' => 'true']),
        ['class' => 'mc-empty__icon']
    );
    $html .= html_writer::tag('p', get_string('subscriptionsunavailable', 'local_moderncommerce'), [
        'class' => 'mc-empty__title',
    ]);
    $html .= html_writer::tag('p', get_string('subscriptionsunavailable_desc', 'local_moderncommerce'), [
        'class' => 'mc-empty__desc',
    ]);
    $html .= html_writer::link(
        new moodle_url('/local/moderncommerce/index.php'),
        get_string('browsecourses', 'local_moderncommerce'),
        [
            'class' => 'mc-button btn-mc-primary',
            'data-mc-button' => 'primary',
        ]
    );

    return html_writer::div($html, 'mc-empty');
}

/**
 * Set a Modern Commerce breadcrumb trail using Moodle core navigation APIs.
 *
 * @param string $title Current page title.
 * @param array|string|moodle_url|null $parents Parent crumbs or a single parent URL.
 * @param string|null $parenttext Parent text when passing a single parent URL.
 */
function local_moderncommerce_set_breadcrumb(string $title, $parents = null, ?string $parenttext = null): void {
    global $PAGE;

    if (!is_array($parents)) {
        $parents = $parents ? [['text' => $parenttext ?? '', 'url' => $parents]] : [];
    }

    foreach ($parents as $parent) {
        $text = is_array($parent) ? ($parent['text'] ?? '') : '';
        $url = is_array($parent) ? ($parent['url'] ?? null) : null;
        if ($text === '') {
            continue;
        }
        if ($url && !$url instanceof moodle_url) {
            $url = new moodle_url($url);
        }
        $PAGE->navbar->add($text, $url ?: null);
    }

    $PAGE->navbar->add($title);
}

/**
 * Check whether a user is enrolled in a course.
 *
 * @param int $userid User ID.
 * @param int $courseid Course ID.
 * @return bool
 */
function local_moderncommerce_is_user_enrolled(int $userid, int $courseid): bool {
    if ($userid <= 0 || $courseid <= 0) {
        return false;
    }

    $context = context_course::instance($courseid, IGNORE_MISSING);
    return $context ? is_enrolled($context, $userid, '', true) : false;
}

/**
 * Get generated placeholder image URL.
 *
 * @param int $id Stable seed ID.
 * @return string
 */
function local_moderncommerce_get_placeholder_image_url(int $id = 0): string {
    global $OUTPUT;

    $image = $OUTPUT->get_generated_image_for_id($id ?: 1);
    return $image ?: '';
}

/**
 * Render a small cart dropdown without depending on a theme service.
 *
 * @param int $userid User ID.
 * @return string HTML.
 */
function local_moderncommerce_render_cart_dropdown_html(int $userid): string {
    $courseitems = \local_moderncommerce\api\cart_api::get_cart_items($userid);
    $bundleitems = \local_moderncommerce\api\cart_api::get_bundle_cart_items($userid);
    $items = array_values(array_merge($courseitems, $bundleitems));
    $carturl = new moodle_url('/local/moderncommerce/cart.php');
    $checkouturl = new moodle_url('/local/moderncommerce/checkout.php');

    if (empty($items)) {
        $html = html_writer::div(
            get_string('emptycart', 'local_moderncommerce'),
            'dropdown-item-text local-moderncommerce-cart-dropdown__empty'
        );
        $html .= html_writer::div(
            html_writer::link($carturl, get_string('viewcart', 'local_moderncommerce'), [
                'class' => 'mc-button local-moderncommerce-cart-dropdown__button',
                'data-mc-button' => 'secondary',
            ]),
            'dropdown-item-text local-moderncommerce-cart-dropdown__footer'
        );

        return html_writer::div(
            $html,
            'local-moderncommerce-cart-dropdown local-moderncommerce-cart-dropdown--empty'
        );
    }

    $html = html_writer::start_div('local-moderncommerce-cart-dropdown');
    foreach (array_slice($items, 0, 5) as $item) {
        $name = $item->coursename ?? $item->bundlename ?? $item->productname ?? get_string('item', 'local_moderncommerce');
        $price = isset($item->price) ? (float)$item->price : (float)($item->unitprice ?? 0);
        $html .= html_writer::div(
            html_writer::span(format_string($name), 'local-moderncommerce-cart-dropdown__name') .
            html_writer::span(
                \local_moderncommerce\services\pricing_service::format_price($price),
                'local-moderncommerce-cart-dropdown__price'
            ),
            'dropdown-item-text local-moderncommerce-cart-dropdown__item'
        );
    }
    if (count($items) > 5) {
        $html .= html_writer::div(
            get_string('moreitems', 'local_moderncommerce', count($items) - 5),
            'dropdown-item-text local-moderncommerce-cart-dropdown__more'
        );
    }
    $html .= html_writer::div(
        html_writer::link($carturl, get_string('viewcart', 'local_moderncommerce'), [
            'class' => 'mc-button local-moderncommerce-cart-dropdown__button',
            'data-mc-button' => 'secondary',
        ]) .
        html_writer::link($checkouturl, get_string('checkout', 'local_moderncommerce'), [
            'class' => 'mc-button local-moderncommerce-cart-dropdown__button',
            'data-mc-button' => 'primary',
        ]),
        'dropdown-item-text local-moderncommerce-cart-dropdown__footer'
    );
    $html .= html_writer::end_div();

    return $html;
}

/**
 * Render the Modern Commerce cart icon in Moodle's top-right navbar.
 *
 * Moodle core collects plugin navbar output through PLUGIN_render_navbar_output()
 * callbacks and prints it inside the user navigation area.
 *
 * @param renderer_base $output The page renderer.
 * @return string HTML.
 */
function local_moderncommerce_render_navbar_output(renderer_base $output): string {
    global $CFG, $USER;

    if (during_initial_install() || !empty($CFG->upgraderunning) || !isloggedin() || isguestuser()) {
        return '';
    }

    try {
        $userid = (int) $USER->id;
        $cartcount = \local_moderncommerce\api\cart_api::get_cart_count($userid);
        $carturl = new moodle_url('/local/moderncommerce/cart.php');
        $cartlabel = get_string('cart', 'local_moderncommerce');
        $cartposition = get_config('local_moderncommerce', 'navbar_cart_position') ?: 'first';
        if (!in_array($cartposition, ['first', 'last'], true)) {
            $cartposition = 'first';
        }
        $cartorder = $cartposition === 'last' ? 1000 : -1000;

        $icon = html_writer::tag('i', '', [
            'class' => 'icon fa-solid fa-bag-shopping mui-cart__trigger-icon',
            'aria-hidden' => 'true',
        ]);

        $badgeattributes = [
            'aria-hidden' => 'true',
            'data-region' => 'cart-count',
        ];
        if ($cartcount <= 0) {
            $badgeattributes['style'] = 'display: none;';
        }
        $badge = html_writer::span(
            (string)$cartcount,
            'count-container mui-cart__badge mc-cart-count cart-count',
            $badgeattributes
        );

        $trigger = html_writer::link(
            $carturl,
            $icon . $badge . html_writer::span($cartlabel, 'visually-hidden'),
            [
                'class' => 'nav-link icon-no-margin mui-cart__trigger',
                'data-bs-toggle' => 'dropdown',
                'data-bs-auto-close' => 'outside',
                'role' => 'button',
                'aria-expanded' => 'false',
                'aria-label' => $cartlabel,
                'title' => $cartlabel,
            ]
        );

        $menu = html_writer::div(
            local_moderncommerce_render_cart_dropdown_html($userid),
            'dropdown-menu dropdown-menu-end mui-cart__panel'
        );

        return html_writer::div($trigger . $menu, 'dropdown mui-cart local-moderncommerce-navbar-cart', [
            'data-navbar-cart-position' => $cartposition,
            'style' => 'order: ' . $cartorder . ';',
        ]);
    } catch (Throwable $e) {
        debugging('Modern Commerce navbar cart failed to render: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return '';
    }
}

/**
 * Serve files from local_moderncommerce.
 *
 * Supported areas:
 * - bundleimage: System context, itemid = bundle id, filepath '/', filename stored.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_moderncommerce_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    if (!in_array($filearea, ['bundleimage', 'slideimage', 'herovideo'], true)) {
        return false;
    }

    if (count($args) < 2) {
        return false;
    }

    $itemid = (int)array_shift($args);
    if ($itemid < 0) {
        return false;
    }

    if ($filearea === 'bundleimage') {
        $canmanage = isloggedin()
            && !isguestuser()
            && has_capability('local/moderncommerce:managecourses', $context);

        if (!$canmanage) {
            $bundle = $DB->get_record(
                'local_moderncommerce_products',
                ['id' => $itemid],
                'id, producttype, status, visible',
                IGNORE_MISSING
            );

            if (
                !$bundle
                || !in_array((string)$bundle->producttype, ['bundle', 'program'], true)
                || empty($bundle->visible)
                || (string)$bundle->status !== 'active'
            ) {
                return false;
            }
        }
    }
    // The 'slideimage' area is public storefront hero artwork — served to guests, no extra gate.

    $filename = array_pop($args);
    $filepath = '/';
    if (!empty($args)) {
        $filepath = '/' . implode('/', $args) . '/';
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_moderncommerce', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    \core\session\manager::write_close();
    send_stored_file($file, 60 * 60, 0, $forcedownload, $options);
}

/**
 * Build the curated Bootstrap icon catalog used by the searchable icon pickers.
 *
 * Values are normalised without the Bootstrap "bi-" prefix (e.g. "check-circle").
 * Seeds a handful of common icons, then folds in the shared category-icons.json catalog.
 *
 * @return array<int, array{value: string, label: string, domain: string, keywords: string}>
 */
function local_moderncommerce_icon_options(): array {
    $options = [];

    $addoption = static function (string $icon, string $label, string $domain = 'General') use (&$options): void {
        $icon = preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', trim($icon)));
        $icon = clean_param((string) $icon, PARAM_ALPHANUMEXT);
        if ($icon === '') {
            return;
        }
        $key = strtolower($icon);
        if (isset($options[$key])) {
            return;
        }
        $label = clean_param($label !== '' ? $label : ucwords(str_replace('-', ' ', $icon)), PARAM_TEXT);
        $domain = clean_param($domain !== '' ? $domain : 'General', PARAM_TEXT);
        $options[$key] = [
            'value' => $icon,
            'label' => $label,
            'domain' => $domain,
            'keywords' => trim($label . ' ' . $domain . ' ' . $icon),
        ];
    };

    $defaults = [
        ['check-circle', 'Check circle'], ['star', 'Star'], ['award', 'Award'], ['mortarboard', 'Mortarboard'],
        ['book', 'Book'], ['people', 'People'], ['shield-check', 'Shield check'], ['patch-check', 'Verified badge'],
        ['lightning-charge', 'Lightning charge'], ['clock-history', 'Clock history'], ['unlock', 'Unlock'],
        ['camera-video', 'Video'], ['headset', 'Headset'], ['graph-up-arrow', 'Progress chart'],
        ['collection', 'Collection'], ['infinity', 'Infinity'], ['laptop', 'Laptop'],
        ['credit-card-2-front', 'Credit card'], ['stripe', 'Stripe'], ['paypal', 'PayPal'],
    ];
    foreach ($defaults as $default) {
        $addoption($default[0], $default[1]);
    }

    $jsonpath = __DIR__ . '/category-icons.json';
    if (is_readable($jsonpath)) {
        $data = json_decode((string) file_get_contents($jsonpath), true);
        if (is_array($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    $addoption(
                        (string) ($item['icon'] ?? ''),
                        (string) ($item['label'] ?? ''),
                        (string) ($item['domain'] ?? 'General')
                    );
                }
            }
        }
    }

    uasort($options, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));
    return array_values($options);
}

/**
 * Get the best available image URL for a course.
 *
 * @param int $courseid Course ID
 * @return string Image URL
 */
function local_moderncommerce_get_course_image_url(int $courseid): string {
    try {
        $course = get_course($courseid);
        $coursecontext = context_course::instance($courseid, IGNORE_MISSING);

        if (!$coursecontext) {
            return '';
        }

        // Use the same approach as core Moodle's course web service.
        $courseimage = \core_course\external\course_summary_exporter::get_course_image($course);
        if (!$courseimage) {
            // Moodle's generated course SVG is served from the course context and
            // requires course access. Use a local data URI fallback for storefront
            // cards so authenticated buyers can see course images before enrolment.
            $courseimage = local_moderncommerce_get_placeholder_image_url($courseid);
        }

        return $courseimage;
    } catch (\Exception $e) {
        debugging('Error in local_moderncommerce_get_course_image_url: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return '';
    }
}

/**
 * Get bundle image URL
 *
 * @param int $bundleid Bundle ID
 * @return string Image URL
 */
function local_moderncommerce_get_bundle_image_url(int $bundleid): string {
    global $OUTPUT;

    $syscontext = \context_system::instance();
    $fs = get_file_storage();

    // Try to get the stored bundle image.
    $files = $fs->get_area_files(
        $syscontext->id,
        'local_moderncommerce',
        'bundleimage',
        $bundleid,
        'itemid, filepath, filename',
        false
    );

    if (!empty($files)) {
        $file = reset($files);
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    // Second fallback: generated pattern image.
    $generatedimage = $OUTPUT->get_generated_image_for_id($bundleid);
    if (!empty($generatedimage)) {
        return $generatedimage;
    }

    return local_moderncommerce_get_placeholder_image_url($bundleid);
}

/**
 * Get available core email templates.
 *
 * @return array Template options for select dropdown.
 */
function local_moderncommerce_get_available_templates() {
    global $DB;

    $templates = [];
    $templates[''] = get_string('none');

    $dbman = $DB->get_manager();
    if (!$dbman->table_exists(new xmldb_table('local_moderncommerce_emailtpl'))) {
        return $templates;
    }

    // Fetch all active templates.
    $sql = "SELECT id, name, template_type
            FROM {local_moderncommerce_emailtpl}
            WHERE status = 'active'
            ORDER BY template_type, name";

    $records = $DB->get_records_sql($sql);

    foreach ($records as $record) {
        $type = !empty($record->template_type) ? ucfirst($record->template_type) : 'General';
        $templates[$record->id] = $record->name . ' (' . $type . ')';
    }

    return $templates;
}

/**
 * Prepare placeholders for order confirmation email
 *
 * @param object $order Order record
 * @param array $items Order items
 * @param object $user User record
 * @return array Placeholder data
 */
function local_moderncommerce_prepare_order_placeholders($order, $items, $user) {
    global $CFG;

    $data = [];
    $site = get_site();
    $storesettings = \local_moderncommerce\services\commerce_settings_service::get_admin_settings();

    // User placeholders (standardized names).
    $data['firstname'] = $user->firstname;
    $data['lastname'] = $user->lastname;
    $data['fullname'] = fullname($user);
    $data['email'] = $user->email;

    // Order details.
    $data['order_number'] = $order->ordernumber;
    $data['order_date'] = userdate($order->timecreated);
    $data['order_status'] = get_string('status_' . $order->status, 'local_moderncommerce');
    $data['subtotal'] = local_moderncommerce_format_amount($order->subtotal);
    $data['discount'] = local_moderncommerce_format_amount($order->discount);
    $data['tax'] = local_moderncommerce_format_amount($order->tax);
    $data['order_total'] = local_moderncommerce_format_amount($order->total);
    $data['currency'] = $order->currency;
    $data['coupon_code'] = !empty($order->couponcode) ? $order->couponcode : '';

    // Course list.
    $data['courses_list'] = local_moderncommerce_format_course_list($items);
    $data['course_count'] = count($items);

    // Action links.
    $data['order_view_link'] = $CFG->wwwroot . '/local/moderncommerce/order.php?id=' . $order->id;
    $data['my_courses_link'] = $CFG->wwwroot . '/my/';

    // Site information.
    $data['sitename'] = $storesettings->businessname ?: format_string($site->fullname);
    $data['business_name'] = $data['sitename'];
    $data['support_email'] = $storesettings->supportemail;
    $data['support_url'] = $storesettings->supporturl;
    $data['siteurl'] = $CFG->wwwroot;

    return $data;
}

/**
 * Prepare placeholders for payment receipt email
 *
 * @param object $order Order record
 * @param object $transaction Transaction record
 * @param array $items Order items
 * @param object $user User record
 * @return array Placeholder data
 */
function local_moderncommerce_prepare_payment_placeholders($order, $transaction, $items, $user) {
    global $CFG;

    $data = [];
    $site = get_site();
    $storesettings = \local_moderncommerce\services\commerce_settings_service::get_admin_settings();

    // User placeholders (standardized names).
    $data['firstname'] = $user->firstname;
    $data['fullname'] = fullname($user);
    $data['email'] = $user->email;

    // Payment transaction.
    $amount = (float)($transaction->amount ?? $order->total ?? 0);
    $fee = (float)($transaction->fee ?? 0);
    $data['transaction_id'] = $transaction->gatewaytransactionid
        ?? ($transaction->transactionid ?? ($transaction->reference ?? ''));
    $data['payment_method'] = ucfirst((string)($transaction->gateway ?? ''));
    $data['payment_date'] = userdate($transaction->timecreated ?? time());
    $data['amount_paid'] = local_moderncommerce_format_amount($amount);
    $data['payment_fee'] = local_moderncommerce_format_amount($fee);
    $data['net_amount'] = local_moderncommerce_format_amount($transaction->netamount ?? max(0, $amount - $fee));
    $data['transaction_reference'] = $transaction->reference ?? '';

    // Order details.
    $data['order_number'] = $order->ordernumber;
    $data['order_total'] = local_moderncommerce_format_amount($order->total);
    $data['currency'] = $order->currency;

    // Courses purchased.
    $data['courses_list'] = local_moderncommerce_format_course_list($items);
    $data['course_count'] = count($items);

    // Access information.
    $data['access_courses_message'] = get_string('email_accesscourses', 'local_moderncommerce');
    $data['my_courses_link'] = $CFG->wwwroot . '/my/';

    // Invoice.
    $data['invoice_number'] = !empty($order->invoicenumber) ? $order->invoicenumber : '';
    $data['invoice_link'] = !empty($order->invoicepath) ? $CFG->wwwroot . '/local/moderncommerce/invoice.php?id=' . $order->id : '';

    // Site information.
    $data['sitename'] = $storesettings->businessname ?: format_string($site->fullname);
    $data['business_name'] = $data['sitename'];
    $data['support_email'] = $storesettings->supportemail;
    $data['support_url'] = $storesettings->supporturl;
    $data['siteurl'] = $CFG->wwwroot;

    return $data;
}

/**
 * Prepare placeholders for enrollment confirmation email
 *
 * @param object $user User record
 * @param object $course Course record
 * @param string $ordernumber Order number
 * @return array Placeholder data
 */
function local_moderncommerce_prepare_enrollment_placeholders($user, $course, $ordernumber) {
    global $CFG, $DB;

    $data = [];
    $site = get_site();
    $storesettings = \local_moderncommerce\services\commerce_settings_service::get_admin_settings();

    // User placeholders (standardized names).
    $data['firstname'] = $user->firstname;
    $data['fullname'] = fullname($user);
    $data['email'] = $user->email;

    // Course information.
    $data['course_name'] = $course->fullname;
    $data['course_code'] = $course->shortname;
    $data['course_summary'] = !empty($course->summary) ? strip_tags($course->summary) : '';
    $data['course_link'] = $CFG->wwwroot . '/course/view.php?id=' . $course->id;
    $data['course_startdate'] = $course->startdate > 0 ? userdate($course->startdate) : '';
    $data['course_enddate'] = $course->enddate > 0 ? userdate($course->enddate) : '';

    // Enrollment details.
    $data['order_number'] = $ordernumber;
    $data['enrollment_date'] = userdate(time());
    $data['enrollment_role'] = get_string('student', 'local_moderncommerce');

    // Course access.
    $buttonstyle = 'display:inline-block;padding:12px 24px;background:#17a2b8;color:white;' .
        'text-decoration:none;border-radius:4px;';
    $data['go_to_course_button'] = '<a href="' . $data['course_link'] . '" style="' . $buttonstyle . '">' .
        get_string('gotocourse', 'local_moderncommerce') . '</a>';
    $data['course_url'] = $data['course_link'];

    // Instructor (try to get primary teacher).
    $context = context_course::instance($course->id);
    $teachers = get_enrolled_users($context, 'moodle/course:update', 0, 'u.id, u.firstname, u.lastname, u.email', null, 0, 1);
    if (!empty($teachers)) {
        $teacher = reset($teachers);
        $data['instructor_name'] = fullname($teacher);
        $data['instructor_email'] = $teacher->email;
    } else {
        $data['instructor_name'] = '';
        $data['instructor_email'] = '';
    }

    // Site information.
    $data['sitename'] = $storesettings->businessname ?: format_string($site->fullname);
    $data['business_name'] = $data['sitename'];
    $data['support_email'] = $storesettings->supportemail;
    $data['support_url'] = $storesettings->supporturl;
    $data['siteurl'] = $CFG->wwwroot;

    return $data;
}

/**
 * Prepare placeholders for key redemption email
 *
 * @param object $user User record
 * @param object $course Course record
 * @param string $keycode Enrollment key
 * @return array Placeholder data
 */
function local_moderncommerce_prepare_key_placeholders($user, $course, $keycode) {
    global $CFG;

    $data = [];
    $site = get_site();
    $storesettings = \local_moderncommerce\services\commerce_settings_service::get_admin_settings();

    // User placeholders (standardized names).
    $data['firstname'] = $user->firstname;
    $data['fullname'] = fullname($user);
    $data['email'] = $user->email;

    // Key information.
    $data['enrollment_key'] = $keycode;
    $data['key_type'] = !empty($keycode)
        ? get_string('singleusekey', 'local_moderncommerce')
        : get_string('multiplecoursekey', 'local_moderncommerce');
    $data['redemption_date'] = userdate(time());

    // Course information.
    $data['course_name'] = $course->fullname;
    $data['course_code'] = $course->shortname;
    $data['course_summary'] = !empty($course->summary) ? strip_tags($course->summary) : '';
    $data['course_link'] = $CFG->wwwroot . '/course/view.php?id=' . $course->id;
    $data['course_startdate'] = $course->startdate > 0 ? userdate($course->startdate) : '';
    $data['course_enddate'] = $course->enddate > 0 ? userdate($course->enddate) : '';

    // Access information.
    $buttonstyle = 'display:inline-block;padding:12px 24px;background:#6f42c1;color:white;' .
        'text-decoration:none;border-radius:4px;';
    $data['go_to_course_button'] = '<a href="' . $data['course_link'] . '" style="' . $buttonstyle . '">' .
        get_string('gotocourse', 'local_moderncommerce') . '</a>';
    $data['course_url'] = $data['course_link'];
    $data['my_courses_link'] = $CFG->wwwroot . '/my/';

    // Site information.
    $data['sitename'] = $storesettings->businessname ?: format_string($site->fullname);
    $data['business_name'] = $data['sitename'];
    $data['support_email'] = $storesettings->supportemail;
    $data['support_url'] = $storesettings->supporturl;
    $data['siteurl'] = $CFG->wwwroot;

    return $data;
}

/**
 * Prepare placeholders for refund confirmation email
 *
 * @param object $order Order record
 * @param object $refund Refund record
 * @param object $user User record
 * @return array Placeholder data
 */
function local_moderncommerce_prepare_refund_placeholders($order, $refund, $user) {
    global $CFG, $DB;

    $data = [];
    $site = get_site();
    $storesettings = \local_moderncommerce\services\commerce_settings_service::get_admin_settings();

    // User placeholders (standardized names).
    $data['firstname'] = $user->firstname;
    $data['fullname'] = fullname($user);
    $data['email'] = $user->email;

    // Refund details.
    $data['refund_amount'] = local_moderncommerce_format_amount($refund->amount);
    $data['refund_reason'] = $refund->reason;
    $data['refund_date'] = userdate($refund->timecreated);
    $paymentmethod = \local_moderncommerce\api\order_api::get_payment_method((int) $order->id);
    $data['refund_method'] = $paymentmethod ?: get_string('originalpaymentmethod', 'local_moderncommerce');
    $data['refund_reference'] = !empty($refund->reference) ? $refund->reference : '';
    $data['processing_time'] = get_string('refundprocessingtime', 'local_moderncommerce');

    // Order information.
    $data['order_number'] = $order->ordernumber;
    $data['order_date'] = userdate($order->timecreated);
    $data['original_amount'] = local_moderncommerce_format_amount($order->total);

    // Courses affected.
    $items = \local_moderncommerce\api\order_api::get_order_items((int) $order->id);
    $data['courses_list'] = local_moderncommerce_format_course_list($items);
    $data['unenrollment_notice'] = get_string('unenrollmentnotice', 'local_moderncommerce');

    // Support.
    $data['contact_support_link'] = $storesettings->supporturl ?: ($CFG->wwwroot . '/local/moderncommerce/help.php');
    $data['support_email'] = $storesettings->supportemail;
    $data['support_url'] = $storesettings->supporturl;

    // Site information.
    $data['sitename'] = $storesettings->businessname ?: format_string($site->fullname);
    $data['business_name'] = $data['sitename'];
    $data['siteurl'] = $CFG->wwwroot;

    return $data;
}

/**
 * Format course list as HTML table for email
 *
 * @param array $items Order items
 * @return string HTML table
 */
function local_moderncommerce_format_course_list($items) {
    if (empty($items)) {
        return '';
    }

    $html = '<table style="width:100%;border-collapse:collapse;margin:20px 0;">';
    $html .= '<thead><tr>';
    $html .= '<th style="padding:10px;text-align:left;border-bottom:1px solid #ddd;background:#f0f0f0;">'
        . get_string('course', 'local_moderncommerce') . '</th>';
    $html .= '<th style="padding:10px;text-align:left;border-bottom:1px solid #ddd;background:#f0f0f0;">'
        . get_string('price', 'local_moderncommerce') . '</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    foreach ($items as $item) {
        if (!empty($item->bundleid)) {
            $itemname = $item->bundlename ?: $item->coursename ?: get_string('bundle', 'local_moderncommerce');
        } else {
            $itemname = $item->coursename ?: $item->itemname ?: get_string('course', 'local_moderncommerce');
        }

        $html .= '<tr>';
        $html .= '<td style="padding:10px;border-bottom:1px solid #ddd;">' . $itemname . '</td>';
        $html .= '<td style="padding:10px;border-bottom:1px solid #ddd;">'
            . local_moderncommerce_format_amount($item->total) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    return $html;
}

/**
 * Format a monetary amount for Modern Commerce emails and tables.
 *
 * This is a small wrapper around pricing_service::format_price() so that
 * placeholder helpers can format amounts consistently without duplicating
 * currency logic.
 *
 * @param float $amount Raw numeric amount
 * @param string|null $currency Optional currency code (falls back to primary_currency config)
 * @return string Formatted amount string (typically with currency symbol)
 */
function local_moderncommerce_format_amount($amount, $currency = null) {
    // Normalise nulls and non-numeric input.
    if ($amount === null || $amount === '') {
        $amount = 0;
    }

    // Always use the pricing_service helper (autoloaded by Moodle) so
    // formatting is fully centralised and respects all settings.
    return \local_moderncommerce\services\pricing_service::format_price((float)$amount, $currency, true);
}

/**
 * Get the dashboard navigation node for the primary menu.
 *
 * Themes can call this helper to add the Modern Commerce dashboard link
 * to primary navigation without knowing plugin internals.
 *
 * @param \moodle_page $page The current page object
 * @return array|null Navigation node array, or null if user shouldn't see it
 */
function local_moderncommerce_get_dashboard_nav_node($page) {
    global $USER;

    // Only for logged-in non-guest users.
    if (!isloggedin() || isguestuser()) {
        return null;
    }

    // Check if we're on the learner dashboard.
    $currenturl = $page->url->out_omit_querystring();
    $isactive = (strpos($currenturl, '/local/moderncommerce/learner/') !== false);

    // Create dashboard node.
    return [
        'title' => get_string('dashboard', 'local_moderncommerce'),
        'url' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false),
        'text' => get_string('dashboard', 'local_moderncommerce'),
        'icon' => null,
        'isactive' => $isactive,
        'key' => 'ccp_dashboard',
        'sort' => 'ccp_dashboard',
        'children' => [],
        'haschildren' => 0,
    ];
}

/**
 * Build the list of enabled public policy links shown at the point of payment.
 *
 * Terms, privacy, refund policy and support are legally meaningful at checkout, so
 * both the standalone checkout page and the learner app's in-app checkout render the
 * same set. Only pages the site has enabled are returned.
 *
 * @return array List of ['key' => string, 'label' => string, 'url' => string].
 */
function local_moderncommerce_public_links(): array {
    $linkmap = [
        'terms' => [
            'label' => get_string('terms', 'local_moderncommerce'),
            'route' => '/local/moderncommerce/terms.php',
        ],
        'privacy' => [
            'label' => get_string('privacy', 'local_moderncommerce'),
            'route' => '/local/moderncommerce/privacy.php',
        ],
        'refund-policy' => [
            'label' => get_string('refundpolicy', 'local_moderncommerce'),
            'route' => '/local/moderncommerce/refund-policy.php',
        ],
        'support' => [
            'label' => get_string('support', 'local_moderncommerce'),
            'route' => '/local/moderncommerce/support.php',
        ],
    ];

    $links = [];
    foreach ($linkmap as $key => $link) {
        if (\local_moderncommerce\output\public_page::is_enabled($key)) {
            $links[] = [
                'key' => $key,
                'label' => $link['label'],
                'url' => (new moodle_url($link['route']))->out(false),
            ];
        }
    }

    return $links;
}

/**
 * Build the React props (method names, ledger URLs, and labels) for the payment
 * gateways admin app. Shared by the standalone gateways page and the unified
 * settings console so both render the identical Gateways UI.
 *
 * @return array React props for the gateways_admin component.
 */
function local_moderncommerce_gateways_react_props(): array {
    return [
        'listMethodName' => 'local_moderncommerce_admin_list_gateways',
        'saveMethodName' => 'local_moderncommerce_admin_save_gateway',
        'webhooksUrl' => (new moodle_url('/local/moderncommerce/admin/webhooks.php'))->out(false),
        'paymentEventsUrl' => (new moodle_url('/local/moderncommerce/admin/payment_events.php'))->out(false),
        'webhookEventsUrl' => (new moodle_url('/local/moderncommerce/admin/webhook_events.php'))->out(false),
        'iconOptions' => local_moderncommerce_icon_options(),
        'labels' => [
            'title' => get_string('paymentgateways', 'local_moderncommerce'),
            'paymentgatewayssubtitle' => get_string('paymentgatewayssubtitle', 'local_moderncommerce'),
            'gatewayhealth' => get_string('gatewayhealth', 'local_moderncommerce'),
            'activecurrency' => get_string('activecurrency', 'local_moderncommerce'),
            'gateway' => get_string('gateway', 'local_moderncommerce'),
            'gatewayidentity' => get_string('gatewayidentity', 'local_moderncommerce'),
            'methodtype' => get_string('gatewaymethodtype', 'local_moderncommerce'),
            'mode' => get_string('mode', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'checkoutreadiness' => get_string('checkoutreadiness', 'local_moderncommerce'),
            'activecurrencysupport' => get_string('activecurrencysupport', 'local_moderncommerce'),
            'secretkey' => get_string('secretkey', 'local_moderncommerce'),
            'webhooksecret' => get_string('webhooksecret', 'local_moderncommerce'),
            'webhookstatus' => get_string('webhookstatus', 'local_moderncommerce'),
            'lastpaymentevent' => get_string('lastpaymentevent', 'local_moderncommerce'),
            'lastwebhookevent' => get_string('lastwebhookevent', 'local_moderncommerce'),
            'ready' => get_string('ready', 'local_moderncommerce'),
            'attention' => get_string('attention', 'local_moderncommerce'),
            'enabled' => get_string('enabled', 'local_moderncommerce'),
            'disabled' => get_string('disabled', 'local_moderncommerce'),
            'configured' => get_string('configured', 'local_moderncommerce'),
            'notconfigured' => get_string('notconfigured', 'local_moderncommerce'),
            'noactivity' => get_string('noactivity', 'local_moderncommerce'),
            'gatewayconfiguration' => get_string('gatewayconfiguration', 'local_moderncommerce'),
            'gatewayconfigurationdesc' => get_string('gatewayconfiguration_desc', 'local_moderncommerce'),
            'apikeys' => get_string('apikeys', 'local_moderncommerce'),
            'capabilities' => get_string('capabilities', 'local_moderncommerce'),
            'displayname' => get_string('gatewaydisplayname', 'local_moderncommerce'),
            'displayorder' => get_string('displayorder', 'local_moderncommerce'),
            'component' => get_string('component', 'local_moderncommerce'),
            'classname' => get_string('gatewayclassname', 'local_moderncommerce'),
            'icon' => get_string('gatewayicon', 'local_moderncommerce'),
            'live' => get_string('live', 'local_moderncommerce'),
            'publickey' => get_string('publickey', 'local_moderncommerce'),
            'merchantid' => get_string('merchantclientid', 'local_moderncommerce'),
            'supportedcurrencies' => get_string('supportedcurrencies', 'local_moderncommerce'),
            'ipwhitelist' => get_string('ipwhitelist', 'local_moderncommerce'),
            'testmode' => get_string('testmode', 'local_moderncommerce'),
            'supportswebhooks' => get_string('supportswebhooks', 'local_moderncommerce'),
            'supportsrefunds' => get_string('supportsrefunds', 'local_moderncommerce'),
            'supportsrecurring' => get_string('supportsrecurring', 'local_moderncommerce'),
            'keepblanksecret' => get_string('keepblanksecret', 'local_moderncommerce'),
            'edit' => get_string('editgateway', 'local_moderncommerce'),
            'settings' => get_string('settings', 'local_moderncommerce'),
            'close' => get_string('close', 'local_moderncommerce'),
            'save' => get_string('savechanges'),
            'cancel' => get_string('cancel'),
            'addcustomgateway' => get_string('addcustomgateway', 'local_moderncommerce'),
            'webhookconfiguration' => get_string('webhookconfiguration', 'local_moderncommerce'),
            'gatewayid' => get_string('gatewayid', 'local_moderncommerce'),
            'nogateways' => get_string('nogateways', 'local_moderncommerce'),
            'saved' => get_string('gatewaysaved', 'local_moderncommerce'),
            'nochanges' => get_string('nochanges', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'noresults' => get_string('noresults', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'viewdetails' => get_string('viewdetails', 'local_moderncommerce'),
            'paymentevents' => get_string('paymentevents', 'local_moderncommerce'),
            'webhookeventslog' => get_string('webhookeventslog', 'local_moderncommerce'),
        ],
    ];
}

/**
 * Build the React props (method names, settings URL, and labels) for the standalone
 * webhooks admin app. It can optionally include editable payment-security settings.
 *
 * @param bool $includesettings Whether to include editable commerce settings methods.
 * @return array React props for the webhooks_admin component.
 */
function local_moderncommerce_webhooks_react_props(bool $includesettings = true): array {
    $props = [
        'getWebhooksMethodName' => 'local_moderncommerce_admin_get_webhooks',
        'listEventsMethodName' => 'local_moderncommerce_admin_list_webhook_events',
        'gatewaysUrl' => (new moodle_url('/local/moderncommerce/admin/gateways.php'))->out(false),
        'settingsUrl' => (new moodle_url('/local/moderncommerce/admin/settings.php'))->out(false),
        'labels' => \local_moderncommerce\output\validation_labels::all() + [
            'title' => get_string('webhooks', 'local_moderncommerce'),
            'paymentsecurity' => get_string('paymentsecurity', 'local_moderncommerce'),
            'paymentsecuritydesc' => get_string('paymentsecurity_desc', 'local_moderncommerce'),
            'enablewebhookipwhitelist' => get_string('enablewebhookipwhitelist', 'local_moderncommerce'),
            'paymentmaxretries' => get_string('paymentmaxretries', 'local_moderncommerce'),
            'webhookurl' => get_string('webhookurl', 'local_moderncommerce'),
            'enabled' => get_string('enabled', 'local_moderncommerce'),
            'disabled' => get_string('disabled', 'local_moderncommerce'),
            'testmode' => get_string('testmode', 'local_moderncommerce'),
            'configured' => get_string('configured', 'local_moderncommerce'),
            'notconfigured' => get_string('notconfigured', 'local_moderncommerce'),
            'secretconfigured' => get_string('secretconfigured', 'local_moderncommerce'),
            'ipwhitelistactive' => get_string('ipwhitelistactive', 'local_moderncommerce'),
            'webhookevents' => get_string('webhookevents', 'local_moderncommerce'),
            'setupinstructions' => get_string('setupinstructions', 'local_moderncommerce'),
            'copy' => get_string('copyurl', 'local_moderncommerce'),
            'copied' => get_string('copied', 'local_moderncommerce'),
            'securitywarning' => get_string('securitywarning', 'local_moderncommerce'),
            'paymentgateways' => get_string('paymentgateways', 'local_moderncommerce'),
            'commercesettings' => get_string('commercesettings', 'local_moderncommerce'),
            'recentwebhookevents' => get_string('recentwebhookevents', 'local_moderncommerce'),
            'noevents' => get_string('noevents', 'local_moderncommerce'),
            'gateway' => get_string('gateway', 'local_moderncommerce'),
            'eventtype' => get_string('eventtype', 'local_moderncommerce'),
            'reference' => get_string('reference', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'date' => get_string('date', 'local_moderncommerce'),
            'signatureverified' => get_string('signatureverified', 'local_moderncommerce'),
            'attempts' => get_string('attempts', 'local_moderncommerce'),
            'lasterror' => get_string('lasterror', 'local_moderncommerce'),
            'allgateways' => get_string('allstatuses', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'view' => get_string('view', 'local_moderncommerce'),
            'close' => get_string('close', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'save' => get_string('savechanges'),
            'discard' => get_string('discardchanges', 'local_moderncommerce'),
            'unsaved' => get_string('unsavedchanges', 'local_moderncommerce'),
            'refresh' => get_string('refresh'),
            'previous' => get_string('previous'),
            'next' => get_string('next'),
            'loading' => get_string('loading', 'local_moderncommerce'),
        ],
    ];

    if ($includesettings) {
        $props['getSettingsMethodName'] = 'local_moderncommerce_admin_get_settings';
        $props['saveSettingsMethodName'] = 'local_moderncommerce_admin_save_settings';
    }

    return $props;
}

/**
 * Build the Core-vs-extended-add-ons capability comparison table.
 *
 * Shared by the admin add-ons page and the public pricing page. Core features
 * ship in the open-source Modern Commerce core; premium add-on features are
 * available separately as optional extensions.
 *
 * @return array Array of groups: [['grouptitle' => string, 'features' => [['label', 'corecheck', 'addoncheck']]]].
 */
function local_moderncommerce_addons_comparison(): array {
    // Group string key => [[feature string key, iscore], ...].
    $groups = [
        'cmp_grp_sell' => [
            ['cmp_f_catalog', true],
            ['cmp_f_cart', true],
            ['cmp_f_gateway', true],
            ['cmp_f_keys', true],
            ['cmp_f_autoenrol', true],
            ['cmp_f_bundles', true],
        ],
        'cmp_grp_money' => [
            ['cmp_f_orders', true],
            ['cmp_f_invoices', true],
            ['cmp_f_tax', true],
            ['cmp_f_currency', true],
            ['cmp_f_coupons', true],
            ['cmp_f_audit', true],
        ],
        'cmp_grp_learner' => [
            ['cmp_f_dashboard', true],
            ['cmp_f_emails', true],
            ['cmp_f_certs', true],
            ['cmp_f_reviews', true],
            ['cmp_f_contact', true],
        ],
        'cmp_grp_insight' => [
            ['cmp_f_reporting', true],
        ],
        'cmp_grp_scale' => [
            ['cmp_f_subs', false],
            ['cmp_f_subemails', false],
            ['cmp_f_emailtpl', true],
            ['cmp_f_notifier', false],
            ['cmp_f_reminders', false],
            ['cmp_f_modernnotify', false],
        ],
    ];

    $comparison = [];
    foreach ($groups as $groupkey => $features) {
        $rows = [];
        foreach ($features as [$featurekey, $iscore]) {
            $rows[] = [
                'label' => get_string($featurekey, 'local_moderncommerce'),
                // Core features ship as open-source software; premium add-on features are sold separately.
                'corecheck' => $iscore,
                'addoncheck' => !$iscore,
            ];
        }
        $comparison[] = [
            'grouptitle' => get_string($groupkey, 'local_moderncommerce'),
            'features' => $rows,
        ];
    }

    return $comparison;
}

// Subscription subsystem helpers absorbed from local_modernsubscription.

/**
 * Check if a user has an active subscription.
 *
 * @param int $userid User ID.
 * @return bool True if user has active subscription.
 */
function local_moderncommerce_subscription_has_active(int $userid): bool {
    return \local_moderncommerce\subscription\services\subscription_service::has_active_subscription($userid);
}

/**
 * Get a user's current subscription.
 *
 * @param int $userid User ID.
 * @return object|null Subscription object or null.
 */
function local_moderncommerce_subscription_get_current(int $userid): ?object {
    return \local_moderncommerce\subscription\api\subscription_api::get_active_for_user($userid);
}

/**
 * Check if a user has access to a specific course via subscription.
 *
 * @param int $userid User ID.
 * @param int $courseid Course ID.
 * @return bool True if user has subscription access to course.
 */
function local_moderncommerce_subscription_has_course_access(int $userid, int $courseid): bool {
    return \local_moderncommerce\subscription\services\access_service::has_course_access($userid, $courseid);
}

/**
 * Format subscription status for display.
 *
 * @param string $status Status code.
 * @return string Formatted status with badge HTML.
 */
function local_moderncommerce_subscription_format_status(string $status): string {
    $badges = [
        'active' => '<span class="badge bg-success">Active</span>',
        'trial' => '<span class="badge bg-info">Trial</span>',
        'grace' => '<span class="badge bg-warning text-dark">Grace Period</span>',
        'suspended' => '<span class="badge bg-danger">Suspended</span>',
        'cancelled' => '<span class="badge bg-secondary">Cancelled</span>',
        'expired' => '<span class="badge bg-dark">Expired</span>',
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

/**
 * Format billing cycle for display.
 *
 * @param string $cycle Billing cycle (monthly|yearly).
 * @return string Formatted cycle.
 */
function local_moderncommerce_subscription_format_cycle(string $cycle): string {
    return get_string('cycle_' . $cycle, 'local_moderncommerce');
}

/**
 * Prepare subscription email placeholders.
 *
 * @param object $subscription Subscription record.
 * @param object $plan Plan record.
 * @param object $user User record.
 * @return array Placeholder key-value pairs.
 */
function local_moderncommerce_subscription_prepare_placeholders(object $subscription, object $plan, object $user): array {
    $currencysymbol = \local_moderncommerce\services\pricing_service::get_currency_symbol($plan->currency);

    return [
        'firstname' => $user->firstname,
        'lastname' => $user->lastname,
        'fullname' => fullname($user),
        'email' => $user->email,
        'plan_name' => $plan->name,
        'plan_price' => $currencysymbol . number_format($plan->price, 2),
        'billing_cycle' => local_moderncommerce_subscription_format_cycle($plan->billing_cycle),
        'subscription_startdate' => userdate($subscription->start_date),
        'subscription_enddate' => userdate($subscription->end_date),
        'days_remaining' => max(0, ceil(($subscription->end_date - time()) / DAYSECS)),
        'renewal_url' => (new moodle_url('/local/moderncommerce/learner/subscription.php', [
            'id' => $subscription->id,
        ]))->out(false),
        'my_subscription_url' => (new moodle_url('/local/moderncommerce/learner/subscription.php'))->out(false),
    ];
}

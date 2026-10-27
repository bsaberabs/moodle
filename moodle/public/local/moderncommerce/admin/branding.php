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
 * Modern Commerce branding editor (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\branding;
use local_moderncommerce\output\admin_shell;

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:managesettings', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/branding.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('brandingpagetitle', 'local_moderncommerce'));

// Effective brand seed colours, so the email branding tab can default its email
// primary/header colours from the configured palette (else the design defaults).
$branddefaults = branding::get_defaults();
$brandprimary = branding::sanitize_field('colour', (string) get_config('local_moderncommerce', 'brand_primary'));
if ($brandprimary === '') {
    $brandprimary = (string) ($branddefaults['brand_primary'] ?? '');
}
$brandsecondary = branding::sanitize_field('colour', (string) get_config('local_moderncommerce', 'brand_secondary'));
if ($brandsecondary === '') {
    $brandsecondary = (string) ($branddefaults['brand_secondary'] ?? '');
}

$brandingreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/branding_admin',
    'id' => 'moderncommerce-branding-admin-app',
    'class' => 'local-moderncommerce-branding-admin',
    'props' => [
        'getMethodName' => 'local_moderncommerce_admin_get_branding',
        'saveMethodName' => 'local_moderncommerce_admin_save_branding',
        'getShellMethodName' => 'local_moderncommerce_email_get_shell',
        'saveShellMethodName' => 'local_moderncommerce_email_save_shell',
        'previewShellMethodName' => 'local_moderncommerce_email_preview_shell',
        'resetShellMethodName' => 'local_moderncommerce_email_reset_shell',
        'brandPrimary' => $brandprimary,
        'brandSecondary' => $brandsecondary,
        'tabLabels' => [
            'colours' => get_string('brandingtab_colours', 'local_moderncommerce'),
            'email' => get_string('brandingtab_email', 'local_moderncommerce'),
        ],
        'labels' => [
            'intro' => get_string('brandingpagedesc', 'local_moderncommerce'),
            'customcssdesc' => get_string('customcss_desc', 'local_moderncommerce'),
            'reset' => get_string('brandingreset', 'local_moderncommerce'),
            'resetall' => get_string('brandingresetall', 'local_moderncommerce'),
            'usingdefault' => get_string('brandingusingdefault', 'local_moderncommerce'),
            'preview' => get_string('brandingpreview', 'local_moderncommerce'),
            'previewbutton' => get_string('brandingpreviewbutton', 'local_moderncommerce'),
            'previewcard' => get_string('brandingpreviewcard', 'local_moderncommerce'),
            'previewbadge' => get_string('brandingpreviewbadge', 'local_moderncommerce'),
            'previewcatalog' => get_string('brandingpreviewcatalog', 'local_moderncommerce'),
            'previewmetric' => get_string('brandingpreviewmetric', 'local_moderncommerce'),
            'previewmuted' => get_string('brandingpreviewmuted', 'local_moderncommerce'),
            'previeworders' => get_string('brandingprevieworders', 'local_moderncommerce'),
            'previewstore' => get_string('brandingpreviewstore', 'local_moderncommerce'),
            'save' => get_string('savechanges'),
            'discard' => get_string('discardchanges', 'local_moderncommerce'),
            'unsaved' => get_string('unsavedchanges', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
        ],
        'emailLabels' => [
            'shell' => get_string('et_shell', 'local_moderncommerce'),
            'shellbuilder' => get_string('et_shellbuilder', 'local_moderncommerce'),
            'shellhelp' => get_string('et_shellhelp', 'local_moderncommerce'),
            'shelldesign' => get_string('et_shelldesign', 'local_moderncommerce'),
            'shellfooter' => get_string('et_shellfooter', 'local_moderncommerce'),
            'shellpreview' => get_string('et_shellpreview', 'local_moderncommerce'),
            'shelladvanced' => get_string('et_shelladvanced', 'local_moderncommerce'),
            'shellhtml' => get_string('et_shellhtml', 'local_moderncommerce'),
            'preview' => get_string('et_preview', 'local_moderncommerce'),
            'previewcontent' => get_string('et_previewcontent', 'local_moderncommerce'),
            'requiredtokenmissing' => get_string('et_requiredtokenmissing', 'local_moderncommerce'),
            'logourl' => get_string('et_logourl', 'local_moderncommerce'),
            'brandname' => get_string('et_brandname', 'local_moderncommerce'),
            'siteurl' => get_string('et_siteurl', 'local_moderncommerce'),
            'headerstyle' => get_string('et_headerstyle', 'local_moderncommerce'),
            'headerstylesplit' => get_string('et_headerstylesplit', 'local_moderncommerce'),
            'headerstylecentered' => get_string('et_headerstylecentered', 'local_moderncommerce'),
            'headerstylecompact' => get_string('et_headerstylecompact', 'local_moderncommerce'),
            'headerbg' => get_string('et_headerbg', 'local_moderncommerce'),
            'primarycolor' => get_string('et_primarycolor', 'local_moderncommerce'),
            'emailbg' => get_string('et_emailbg', 'local_moderncommerce'),
            'contentbg' => get_string('et_contentbg', 'local_moderncommerce'),
            'footerbg' => get_string('et_footerbg', 'local_moderncommerce'),
            'textcolor' => get_string('et_textcolor', 'local_moderncommerce'),
            'containerwidth' => get_string('et_containerwidth', 'local_moderncommerce'),
            'buttonradius' => get_string('et_buttonradius', 'local_moderncommerce'),
            'supportemail' => get_string('supportemail', 'local_moderncommerce'),
            'supporturl' => get_string('supporturl', 'local_moderncommerce'),
            'showsupport' => get_string('et_showsupport', 'local_moderncommerce'),
            'showunsubscribe' => get_string('et_showunsubscribe', 'local_moderncommerce'),
            'footertext' => get_string('et_footertext', 'local_moderncommerce'),
            'saveshell' => get_string('et_saveshell', 'local_moderncommerce'),
            'previewshell' => get_string('et_previewshell', 'local_moderncommerce'),
            'resetshell' => get_string('et_resetshell', 'local_moderncommerce'),
            'resetconfirm' => get_string('et_resetconfirm', 'local_moderncommerce'),
            'copied' => get_string('copied', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/branding', [
    'brandingreactconfig' => $brandingreactconfig,
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'branding',
    get_string('brandingpagetitle', 'local_moderncommerce'),
    $contenthtml,
    get_string('brandingpagedesc', 'local_moderncommerce')
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();

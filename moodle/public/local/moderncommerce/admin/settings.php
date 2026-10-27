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
 * Modern Commerce settings (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\output\admin_shell;
use local_moderncommerce\output\validation_labels;
use local_moderncommerce\services\certificate_template_service;

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:managesettings', $context);

$requestedtab = optional_param('tab', '', PARAM_ALPHANUMEXT);
$validtabs = [
    'store', 'currency', 'tax', 'documents', 'checkout', 'navigation',
    'notifications', 'reviews', 'products', 'certificates', 'contact_autoreply',
];
$initialtab = in_array($requestedtab, $validtabs, true) ? $requestedtab : 'store';

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/settings.php', ['tab' => $requestedtab]));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('commercesettings', 'local_moderncommerce'));
$PAGE->set_heading(get_string('commercesettings', 'local_moderncommerce'));

$settingsreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/settings_admin',
    'id' => 'moderncommerce-settings-admin-app',
    'class' => 'local-moderncommerce-settings-admin',
    'props' => [
        'getMethodName' => 'local_moderncommerce_admin_get_settings',
        'saveMethodName' => 'local_moderncommerce_admin_save_settings',
        'initialTab' => $initialtab,
        'certificateTemplatesUrl' => certificate_template_service::manage_url(),
        'contactEmails' => [
            'available' => true,
            'getMethodName' => 'local_moderncommerce_get_contact_email_settings',
            'saveMethodName' => 'local_moderncommerce_save_contact_email_settings',
            'labels' => [
                'title' => get_string('contactemailconfig', 'local_moderncommerce'),
                'loading' => get_string('loading', 'local_moderncommerce'),
                'recipients' => get_string('contactrecipients', 'local_moderncommerce'),
                'recipients_desc' => get_string('contactrecipients_desc', 'local_moderncommerce'),
                'autoreply' => get_string('contactautoreply', 'local_moderncommerce'),
                'autoreply_desc' => get_string('contactautoreply_desc', 'local_moderncommerce'),
                'adminnotify' => get_string('contactadminnotify', 'local_moderncommerce'),
                'adminnotify_desc' => get_string('contactadminnotify_desc', 'local_moderncommerce'),
                'enabled' => get_string('enabled', 'local_moderncommerce'),
                'template' => get_string('template', 'local_moderncommerce'),
                'none' => get_string('none'),
                'subject' => get_string('contactsubject', 'local_moderncommerce'),
                'body' => get_string('body', 'local_moderncommerce'),
                'placeholders' => get_string('placeholders', 'local_moderncommerce'),
                'insertplaceholder' => get_string('contactinsertplaceholder', 'local_moderncommerce'),
                'copied' => get_string('copied', 'local_moderncommerce'),
                'savechanges' => get_string('savechanges'),
                'unavailable' => get_string('contactunavailable', 'local_moderncommerce'),
                'unavailable_desc' => get_string('contactunavailable_desc', 'local_moderncommerce'),
                'templatefallbackhint' => get_string('templatefallbackhint', 'local_moderncommerce'),
                'templatecontentactive' => get_string('templatecontentactive', 'local_moderncommerce'),
                'templatecontentactive_desc' => get_string('templatecontentactive_desc', 'local_moderncommerce'),
                'customcontent' => get_string('customcontent', 'local_moderncommerce'),
                'customcontent_desc' => get_string('customcontent_desc', 'local_moderncommerce'),
                'customcontentwithouttemplate_desc' => get_string('customcontentwithouttemplate_desc', 'local_moderncommerce'),
                'customcontentactive' => get_string('customcontentactive', 'local_moderncommerce'),
                'customcontentactive_desc' => get_string('customcontentactive_desc', 'local_moderncommerce'),
                'nocontentselected' => get_string('nocontentselected', 'local_moderncommerce'),
                'nocontentselected_desc' => get_string('nocontentselected_desc', 'local_moderncommerce'),
                'usetemplatecontent' => get_string('usetemplatecontent', 'local_moderncommerce'),
                'templatepreview' => get_string('templatepreview', 'local_moderncommerce'),
                'templatepreview_desc' => get_string('templatepreview_desc', 'local_moderncommerce'),
                'customizefromtemplate' => get_string('customizefromtemplate', 'local_moderncommerce'),
                'loadingtemplate' => get_string('loadingtemplate', 'local_moderncommerce'),
                'emptytemplatefield' => get_string('emptytemplatefield', 'local_moderncommerce'),
                'saving' => get_string('saving', 'local_moderncommerce'),
            ],
        ],
        'labels' => validation_labels::all() + [
            'separatorsmustdiffer' => get_string('separatorsmustdiffer', 'local_moderncommerce'),
            // Tab labels.
            'tabStore' => get_string('tab_store', 'local_moderncommerce'),
            'tabCurrency' => get_string('tab_currency', 'local_moderncommerce'),
            'tabTax' => get_string('tab_tax', 'local_moderncommerce'),
            'tabDocuments' => get_string('tab_documents', 'local_moderncommerce'),
            'tabCheckout' => get_string('tab_checkout', 'local_moderncommerce'),
            'tabNavigation' => get_string('tab_navigation', 'local_moderncommerce'),
            'tabNotifications' => get_string('tab_notifications', 'local_moderncommerce'),
            'tabReviews' => get_string('tab_reviews', 'local_moderncommerce'),
            'tabProducts' => get_string('tab_products', 'local_moderncommerce'),
            'tabCertificates' => get_string('tab_certificates', 'local_moderncommerce'),
            'tabContactAutoreply' => get_string('contactautoreply', 'local_moderncommerce'),
            // Store identity.
            'storeidentity' => get_string('storeidentity', 'local_moderncommerce'),
            'storeidentitydesc' => get_string('storeidentity_desc', 'local_moderncommerce'),
            'businessname' => get_string('businessname', 'local_moderncommerce'),
            'supportemail' => get_string('supportemail', 'local_moderncommerce'),
            'supporturl' => get_string('supporturl', 'local_moderncommerce'),
            // Currency.
            'currencysettings' => get_string('currencysettings', 'local_moderncommerce'),
            'currencysettingsdesc' => get_string('currencysettings_desc', 'local_moderncommerce'),
            'primarycurrency' => get_string('primarycurrency', 'local_moderncommerce'),
            'currencyposition' => get_string('currencyposition', 'local_moderncommerce'),
            'decimalplaces' => get_string('decimalplaces', 'local_moderncommerce'),
            'thousandseparator' => get_string('thousandseparator', 'local_moderncommerce'),
            'decimalseparator' => get_string('decimalseparator', 'local_moderncommerce'),
            // Tax.
            'taxsettings' => get_string('taxsettings', 'local_moderncommerce'),
            'taxsettingsdesc' => get_string('taxsettings_desc', 'local_moderncommerce'),
            'taxmode' => get_string('taxmode', 'local_moderncommerce'),
            'defaulttaxrate' => get_string('defaulttaxrate', 'local_moderncommerce'),
            // Documents.
            'documentsettings' => get_string('documentsettings', 'local_moderncommerce'),
            'documentsettingsdesc' => get_string('documentsettings_desc', 'local_moderncommerce'),
            'invoiceprefix' => get_string('invoiceprefix', 'local_moderncommerce'),
            'receiptprefix' => get_string('receiptprefix', 'local_moderncommerce'),
            // Checkout fields.
            'checkoutfields' => get_string('checkoutfields', 'local_moderncommerce'),
            'checkoutfieldsdesc' => get_string('checkoutfields_desc', 'local_moderncommerce'),
            'contactinfoenabled' => get_string('contactinfoenabled', 'local_moderncommerce'),
            'phonefield' => get_string('phonefield', 'local_moderncommerce'),
            'addressfield' => get_string('addressfield', 'local_moderncommerce'),
            'cityfield' => get_string('cityfield', 'local_moderncommerce'),
            'statefield' => get_string('statefield', 'local_moderncommerce'),
            'countryfield' => get_string('countryfield', 'local_moderncommerce'),
            'zipcodefield' => get_string('zipcodefield', 'local_moderncommerce'),
            // Navigation.
            'navigationsettings' => get_string('navigationsettings', 'local_moderncommerce'),
            'navigationsettingsdesc' => get_string('navigationsettings_desc', 'local_moderncommerce'),
            'adminnavlabel' => get_string('adminnavlabel', 'local_moderncommerce'),
            'learnernavlabel' => get_string('learnernavlabel', 'local_moderncommerce'),
            'hideprimarynavitems' => get_string('hideprimarynavitems', 'local_moderncommerce'),
            'navbarcartposition' => get_string('navbarcartposition', 'local_moderncommerce'),
            // Notifications.
            'notificationsettings' => get_string('notificationsettings', 'local_moderncommerce'),
            'notificationsettingsdesc' => get_string('notificationsettings_desc', 'local_moderncommerce'),
            'notificationposition' => get_string('notificationposition', 'local_moderncommerce'),
            'notificationautodismiss' => get_string('notificationautodismiss', 'local_moderncommerce'),
            // Reviews.
            'reviewsettings' => get_string('reviewsettings', 'local_moderncommerce'),
            'reviewsettingsdesc' => get_string('reviewsettings_desc', 'local_moderncommerce'),
            'reviewsenabled' => get_string('reviewsenabled', 'local_moderncommerce'),
            // Product form fields.
            'productformsettings' => get_string('productformsettings', 'local_moderncommerce'),
            'productformsettingsdesc' => get_string('productformsettings_desc', 'local_moderncommerce'),
            'showskufield' => get_string('showskufield', 'local_moderncommerce'),
            'showslugfield' => get_string('showslugfield', 'local_moderncommerce'),
            'coursedetailsidebarposition' => get_string('coursedetailsidebarposition', 'local_moderncommerce'),
            'programcertificatesettings' => get_string('programcertificatesettings', 'local_moderncommerce'),
            'programcertificatesettingsdesc' => get_string('programcertificatesettings_desc', 'local_moderncommerce'),
            'defaultprogramcertificatetemplate' => get_string(
                'defaultprogramcertificatetemplate',
                'local_moderncommerce'
            ),
            'coursecertificateunavailableprogram' => get_string(
                'coursecertificateunavailableprogram',
                'local_moderncommerce'
            ),
            'coursecertificateoptionaldesc' => get_string(
                'coursecertificateoptionaldesc',
                'local_moderncommerce'
            ),
            'managecertificatetemplates' => get_string('managecertificatetemplates', 'local_moderncommerce'),
            // Controls.
            'save' => get_string('savechanges'),
            'discard' => get_string('discardchanges', 'local_moderncommerce'),
            'unsaved' => get_string('unsavedchanges', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'saving' => get_string('saving', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/settings', [
    'settingsreactconfig' => $settingsreactconfig,
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'global',
    get_string('commercesettings', 'local_moderncommerce'),
    $contenthtml,
    get_string('commercesettings_desc', 'local_moderncommerce')
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();

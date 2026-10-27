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
 * Shared renderer for the React email templates admin app.
 *
 * Modern Commerce owns the template content, the shared shell, and the
 * webservice endpoints consumed by this admin UI.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;

/**
 * Builds the email templates admin React mount used by admin/email_templates.php.
 */
class email_templates_page {
    /**
     * Render the email templates admin shell.
     *
     * @param object $output Page output renderer (or pre-header bootstrap renderer).
     * @param string $opentype Outgoing email type to open when provided.
     * @return string Rendered admin page HTML.
     */
    public static function render(object $output, string $opentype = ''): string {
        $config = json_encode([
            'component' => '@moodle/lms/local_moderncommerce/email_templates_admin',
            'id' => 'moderncommerce-email-templates-admin-app',
            'class' => 'local-moderncommerce-email-templates-admin',
            'props' => [
                'addonsUrl' => (new \moodle_url('/local/moderncommerce/admin/addons.php'))->out(false),
                'metadataMethodName' => 'local_moderncommerce_email_get_metadata',
                'listMethodName' => 'local_moderncommerce_email_list_templates',
                'getMethodName' => 'local_moderncommerce_email_get_template',
                'saveMethodName' => 'local_moderncommerce_email_save_template',
                'deleteMethodName' => 'local_moderncommerce_email_delete_template',
                'cloneMethodName' => 'local_moderncommerce_email_clone_template',
                'previewMethodName' => 'local_moderncommerce_email_preview_template',
                'notificationListMethodName' => 'local_moderncommerce_admin_list_emails',
                'notificationGetMethodName' => 'local_moderncommerce_admin_get_email',
                'notificationSaveMethodName' => 'local_moderncommerce_admin_save_email',
                'notificationOpenType' => $opentype,
                'notificationLabels' => [
                    'title' => get_string('et_outgoingemails', 'local_moderncommerce'),
                    'description' => get_string('et_outgoingemailsdesc', 'local_moderncommerce'),
                    'email' => get_string('email', 'local_moderncommerce'),
                    'purpose' => get_string('description', 'local_moderncommerce'),
                    'status' => get_string('status', 'local_moderncommerce'),
                    'modified' => get_string('et_modified', 'local_moderncommerce'),
                    'actions' => get_string('actions', 'local_moderncommerce'),
                    'showing' => get_string('showing', 'local_moderncommerce'),
                    'search' => get_string('search'),
                    'searchplaceholder' => 'Search emails',
                    'group' => 'Email group',
                    'allgroups' => 'All email groups',
                    'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
                    'perpage' => get_string('perpage', 'local_moderncommerce'),
                    'previous' => get_string('previous'),
                    'next' => get_string('next'),
                    'page' => get_string('page', 'local_moderncommerce'),
                    'enabled' => get_string('emailenabled', 'local_moderncommerce'),
                    'disabled' => get_string('emaildisabled', 'local_moderncommerce'),
                    'edit' => get_string('edit'),
                    'settings' => get_string('emailsettings', 'local_moderncommerce'),
                    'subject' => get_string('subject', 'local_moderncommerce'),
                    'body' => get_string('body', 'local_moderncommerce'),
                    'bodyvisual' => 'Visual editor',
                    'bodyhtml' => 'HTML',
                    'bodyeditormode' => 'Body editor mode',
                    'formattoolbar' => 'Formatting toolbar',
                    'formatbold' => 'Bold',
                    'formatitalic' => 'Italic',
                    'formatbulletlist' => 'Bulleted list',
                    'formatnumberedlist' => 'Numbered list',
                    'formatlink' => 'Link',
                    'formatunlink' => 'Remove link',
                    'formatclear' => 'Clear formatting',
                    'linkurl' => 'Link URL',
                    'template' => get_string('emailtemplate', 'local_moderncommerce'),
                    'templatebuiltin' => 'Built-in email content',
                    'templatehint' => 'Use the built-in content for this email, or choose a reusable template '
                        . 'as the starting point.',
                    'templateloaderror' => 'Could not load the selected template content.',
                    'placeholders' => get_string('placeholders', 'local_moderncommerce'),
                    'insertplaceholder' => get_string('insertplaceholder', 'local_moderncommerce'),
                    'copied' => get_string('copied', 'local_moderncommerce'),
                    'save' => get_string('savechanges'),
                    'cancel' => get_string('cancel'),
                    'saved' => get_string('changessaved'),
                    'loading' => get_string('loading', 'local_moderncommerce'),
                    'none' => get_string('none'),
                ],
                'labels' => [
                    'title' => get_string('emailtemplates', 'local_moderncommerce'),
                    'emails' => get_string('et_emails', 'local_moderncommerce'),
                    'templates' => get_string('et_templates', 'local_moderncommerce'),
                    'contentlibrary' => get_string('et_contentlibrary', 'local_moderncommerce'),
                    'contentlibrarydesc' => get_string('et_contentlibrarydesc', 'local_moderncommerce'),
                    'newtemplate' => get_string('et_newtemplate', 'local_moderncommerce'),
                    'search' => get_string('et_searchplaceholder', 'local_moderncommerce'),
                    'allcomponents' => get_string('et_allcomponents', 'local_moderncommerce'),
                    'alltypes' => get_string('et_alltypes', 'local_moderncommerce'),
                    'allstatuses' => get_string('et_allstatuses', 'local_moderncommerce'),
                    'name' => get_string('et_name', 'local_moderncommerce'),
                    'type' => get_string('et_type', 'local_moderncommerce'),
                    'component' => get_string('et_component', 'local_moderncommerce'),
                    'modified' => get_string('et_modified', 'local_moderncommerce'),
                    'status' => get_string('et_status', 'local_moderncommerce'),
                    'active' => get_string('active', 'local_moderncommerce'),
                    'inactive' => get_string('inactive', 'local_moderncommerce'),
                    'actions' => get_string('et_actions', 'local_moderncommerce'),
                    'templatekey' => get_string('et_templatekey', 'local_moderncommerce'),
                    'templatename' => get_string('et_templatename', 'local_moderncommerce'),
                    'templatetype' => get_string('et_templatetype', 'local_moderncommerce'),
                    'subject' => get_string('et_subject', 'local_moderncommerce'),
                    'body' => get_string('et_body', 'local_moderncommerce'),
                    'description' => get_string('et_description', 'local_moderncommerce'),
                    'keyhelp' => get_string('et_keyhelp', 'local_moderncommerce'),
                    'keyautohelp' => get_string('et_keyautohelp', 'local_moderncommerce'),
                    'placeholders' => get_string('et_placeholders', 'local_moderncommerce'),
                    'insertplaceholder' => get_string('et_insertplaceholder', 'local_moderncommerce'),
                    'preview' => get_string('et_preview', 'local_moderncommerce'),
                    'refreshpreview' => get_string('et_refreshpreview', 'local_moderncommerce'),
                    'locked' => get_string('et_locked', 'local_moderncommerce'),
                    'addonnotinstalled' => get_string('et_addonnotinstalled', 'local_moderncommerce'),
                    'addonnotinstalleddesc' => get_string('et_addonnotinstalleddesc', 'local_moderncommerce'),
                    'manageaddons' => get_string('et_manageaddons', 'local_moderncommerce'),
                    'requiredfields' => get_string('et_requiredfields', 'local_moderncommerce'),
                    'notemplates' => get_string('et_notemplates', 'local_moderncommerce'),
                    'confirmdelete' => get_string('et_confirmdelete', 'local_moderncommerce'),
                    'total' => get_string('et_total', 'local_moderncommerce'),
                    'components' => get_string('components', 'local_moderncommerce'),
                    'clone' => get_string('et_clone', 'local_moderncommerce'),
                    'edit' => get_string('edit'),
                    'delete' => get_string('delete'),
                    'save' => get_string('savechanges'),
                    'cancel' => get_string('cancel'),
                    'back' => get_string('back'),
                    'previous' => get_string('previous'),
                    'next' => get_string('next'),
                    'showing' => get_string('showing', 'local_moderncommerce'),
                    'page' => get_string('page', 'local_moderncommerce'),
                    'perpage' => get_string('perpage', 'local_moderncommerce'),
                    'copied' => get_string('copied', 'local_moderncommerce'),
                    'loading' => get_string('loading', 'local_moderncommerce'),
                ],
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $contenthtml = $output->render_from_template('local_moderncommerce/admin/email_templates', [
            'emailtemplatesreactconfig' => $config,
        ]);

        $actionshtml = admin_shell::action_group([
            [
                'type' => 'button',
                'label' => get_string('refresh'),
                'icon' => 'bi-arrow-clockwise',
                'attributes' => ['id' => 'moderncommerce-email-templates-refresh'],
            ],
        ]);

        return admin_shell::render_page(
            $output,
            'email',
            get_string('emailtemplates', 'local_moderncommerce'),
            $contenthtml,
            get_string('emailtemplates_desc', 'local_moderncommerce'),
            $actionshtml
        );
    }
}

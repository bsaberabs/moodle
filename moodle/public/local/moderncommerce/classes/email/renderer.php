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

namespace local_moderncommerce\email;

/**
 * Renders Modern Commerce email content through the global shell.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer {
    /** @var string Plugin component. */
    private const COMPONENT = 'local_moderncommerce';

    /** @var string Config key that stores the raw shell HTML. */
    private const SHELL_CONFIG = 'email_shell_html';

    /**
     * Default global shell used when no valid admin shell is configured.
     *
     * Colours default to the configured Modern Commerce brand palette: the
     * primary seed drives buttons and links, the secondary seed the header band,
     * so emails follow the storefront/admin branding without extra setup. Both
     * fall back to the design-system defaults when unset.
     *
     * @return string Raw shell HTML.
     */
    public static function default_shell(): string {
        $defaults = \local_moderncommerce\branding::get_defaults();
        $primary = \local_moderncommerce\branding::sanitize_field(
            'colour',
            (string) get_config(self::COMPONENT, 'brand_primary')
        );
        if ($primary === '') {
            $primary = (string) ($defaults['brand_primary'] ?? '#0f6cbf');
        }
        $secondary = \local_moderncommerce\branding::sanitize_field(
            'colour',
            (string) get_config(self::COMPONENT, 'brand_secondary')
        );
        if ($secondary === '') {
            $secondary = (string) ($defaults['brand_secondary'] ?? '#1f2937');
        }

        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    .mc-email-button, .mc-button, .btn {
      display:inline-block;
      padding:12px 28px;
      background:$primary;
      color:#ffffff !important;
      text-decoration:none;
      border-radius:6px;
      font-weight:700;
    }
  </style>
</head>
<body style="margin:0; padding:0; background:#f4f6f8; font-family:Segoe UI, Arial, sans-serif; color:#1f2937;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8; margin:0; padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="max-width:640px; background:#ffffff; border:1px solid #e5e7eb;
                      border-radius:10px; overflow:hidden;">
          <tr>
            <td style="padding:24px 28px; background:$secondary; color:#ffffff;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td>
                    <img src="{logo}" alt="{sitename}" style="max-width:180px; max-height:56px; height:auto; border:0;">
                  </td>
                  <td align="right" style="font-size:16px; font-weight:700;">{sitename}</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:28px; font-size:16px; line-height:1.6;">
              {content_html}
              {unsubscribe_html}
            </td>
          </tr>
          <tr>
            <td style="padding:20px 28px; background:#f9fafb; color:#6b7280; font-size:13px; line-height:1.5;">
              <p style="margin:0 0 6px 0;">This message was sent by {sitename}.</p>
              <p style="margin:0;">
                <a href="{siteurl}" style="color:$primary;">{siteurl}</a> |
                <a href="mailto:{supportemail}" style="color:$primary;">{supportemail}</a>
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * Get the active shell HTML, falling back to the bundled shell when invalid.
     *
     * @return string Shell HTML.
     */
    public static function get_shell_html(): string {
        $shell = (string) get_config(self::COMPONENT, self::SHELL_CONFIG);
        if (!self::is_valid_shell($shell)) {
            return self::default_shell();
        }
        return $shell;
    }

    /**
     * Persist a raw shell HTML string.
     *
     * @param string $html Raw shell HTML.
     * @throws \moodle_exception If the shell is missing required content token.
     */
    public static function save_shell_html(string $html): void {
        if (!self::is_valid_shell($html)) {
            throw new \moodle_exception('error_invalidemailshell', self::COMPONENT);
        }
        set_config(self::SHELL_CONFIG, $html, self::COMPONENT);
    }

    /**
     * Reset the configured shell to the bundled default.
     */
    public static function reset_shell_html(): void {
        set_config(self::SHELL_CONFIG, self::default_shell(), self::COMPONENT);
    }

    /**
     * Validate shell HTML.
     *
     * @param string $html Raw shell HTML.
     * @return bool
     */
    public static function is_valid_shell(string $html): bool {
        return trim($html) !== '' && strpos($html, '{content_html}') !== false;
    }

    /**
     * Render an active template by key.
     *
     * @param string $templatekey Template key.
     * @param array $data Placeholder data.
     * @param array $options Rendering options.
     * @return array{subject:string,html:string,plain:string,body:string}
     * @throws \moodle_exception When the template does not exist.
     */
    public static function render_template(string $templatekey, array $data = [], array $options = []): array {
        global $DB;

        $manager = new template_manager();
        $configkey = notification_catalog::config_key_for_template($templatekey);

        $template = null;
        if ($configkey !== '') {
            $templateid = (int) get_config(self::COMPONENT, $configkey . '_template');
            if ($templateid > 0) {
                if ($DB->get_manager()->table_exists(new \xmldb_table('local_moderncommerce_emailtpl'))) {
                    $selected = $DB->get_record('local_moderncommerce_emailtpl', [
                        'id' => $templateid,
                        'status' => 'active',
                    ]);
                    if ($selected) {
                        $template = $selected;
                    }
                }
            }
        }

        if (!$template && $DB->get_manager()->table_exists(new \xmldb_table('local_moderncommerce_emailtpl'))) {
            $template = $manager->get_template($templatekey);
        }
        if (!$template) {
            $template = notification_catalog::seeded_record($templatekey);
        }
        if (!$template) {
            throw new \moodle_exception('template_not_found', self::COMPONENT);
        }

        if ($configkey !== '') {
            $subject = get_config(self::COMPONENT, $configkey . '_subject');
            $body = get_config(self::COMPONENT, $configkey . '_body');
            if ($subject !== false && trim((string) $subject) !== '') {
                $template->subject = (string) $subject;
            }
            if ($body !== false && trim((string) $body) !== '') {
                $template->body = (string) $body;
            }
        }

        return self::render_record($template, $data, $options);
    }

    /**
     * Render a template record.
     *
     * @param \stdClass $template Template record.
     * @param array $data Placeholder data.
     * @param array $options Rendering options.
     * @return array{subject:string,html:string,plain:string,body:string}
     */
    public static function render_record(\stdClass $template, array $data = [], array $options = []): array {
        $subject = (string) ($template->subject ?? '');
        $body = (string) ($template->body ?? '');
        $marketing = ($template->template_type ?? '') === 'marketing';

        return self::render_subject_body($subject, $body, $data, $options + [
            'marketing' => $marketing,
        ]);
    }

    /**
     * Render arbitrary subject/body content through placeholders and optional shell.
     *
     * @param string $subject Subject template.
     * @param string $body Body template.
     * @param array $data Placeholder data.
     * @param array $options Rendering options.
     * @return array{subject:string,html:string,plain:string,body:string}
     */
    public static function render_subject_body(string $subject, string $body, array $data = [], array $options = []): array {
        $defaults = placeholder_engine::get_global_placeholder_values();
        $engine = new placeholder_engine();
        $subject = $engine->substitute_placeholders($subject, $data, $defaults);
        $inner = $engine->substitute_placeholders($body, $data, $defaults);

        $applyshell = $options['applyshell'] ?? true;
        $html = $applyshell ? self::render_shell($inner, $data, !empty($options['marketing']), $options['shell'] ?? null) : $inner;
        $plain = trim(html_to_text($html));
        if ($plain === '') {
            $plain = $subject;
        }

        return [
            'subject' => $subject,
            'html' => $html,
            'plain' => $plain,
            'body' => $inner,
        ];
    }

    /**
     * Render inner content inside the configured shell.
     *
     * @param string $contenthtml Inner rendered HTML.
     * @param array $data Placeholder data.
     * @param bool $marketing Whether the message is marketing.
     * @param string|null $shell Override shell HTML.
     * @return string Full HTML.
     */
    public static function render_shell(
        string $contenthtml,
        array $data = [],
        bool $marketing = false,
        ?string $shell = null
    ): string {
        $shell = $shell !== null ? $shell : self::get_shell_html();
        if (!self::is_valid_shell($shell)) {
            $shell = self::default_shell();
        }

        $unsubscribehtml = self::unsubscribe_html($data, $marketing);
        if ($unsubscribehtml !== '' && strpos($shell, '{unsubscribe_html}') === false) {
            $contenthtml .= $unsubscribehtml;
        }

        $engine = new placeholder_engine();
        $rendered = $engine->substitute_placeholders($shell, $data, placeholder_engine::get_global_placeholder_values());
        $rendered = str_replace('{content_html}', $contenthtml, $rendered);
        $rendered = str_replace('{unsubscribe_html}', $unsubscribehtml, $rendered);

        return $rendered;
    }

    /**
     * Build unsubscribe footer markup.
     *
     * @param array $data Placeholder data.
     * @param bool $marketing Whether this message is marketing.
     * @return string HTML.
     */
    private static function unsubscribe_html(array $data, bool $marketing): string {
        if (!$marketing || empty($data['unsubscribe_url'])) {
            return '';
        }
        $url = s((string) $data['unsubscribe_url']);
        return '<p style="font-size:12px; color:#6b7280; margin:24px 0 0 0;">'
            . 'Not interested in offers? <a href="' . $url . '" style="color:#6b7280;">Unsubscribe</a>.</p>';
    }
}

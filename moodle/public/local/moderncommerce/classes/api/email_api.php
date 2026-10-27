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

namespace local_moderncommerce\api;

use local_moderncommerce\email\renderer;

/**
 * Stable email rendering/sending facade for Modern Commerce and add-ons.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_api {
    /**
     * Render a template by key.
     *
     * @param string $templatekey Template key.
     * @param array $data Placeholder data.
     * @param array $options Renderer options.
     * @return array{subject:string,html:string,plain:string,body:string}
     */
    public static function render(string $templatekey, array $data = [], array $options = []): array {
        return renderer::render_template($templatekey, $data, $options);
    }

    /**
     * Render and send a template to a user.
     *
     * @param string $templatekey Template key.
     * @param int $userid Recipient user id.
     * @param array $data Placeholder data.
     * @param \stdClass|null $fromuser Sender user.
     * @param array $options Renderer options.
     * @return bool
     */
    public static function send(
        string $templatekey,
        int $userid,
        array $data,
        ?\stdClass $fromuser = null,
        array $options = []
    ): bool {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return false;
        }

        $rendered = self::render($templatekey, $data, $options);
        $fromuser = $fromuser ?: \core_user::get_noreply_user();

        return (bool) email_to_user(
            $user,
            $fromuser,
            $rendered['subject'],
            $rendered['plain'],
            $rendered['html']
        );
    }

    /**
     * Render and send arbitrary content to a user.
     *
     * @param \stdClass $user Recipient user.
     * @param string $subject Subject template.
     * @param string $body Body template.
     * @param array $data Placeholder data.
     * @param \stdClass|null $fromuser Sender user.
     * @param array $options Renderer options.
     * @return bool
     */
    public static function send_subject_body(
        \stdClass $user,
        string $subject,
        string $body,
        array $data = [],
        ?\stdClass $fromuser = null,
        array $options = []
    ): bool {
        $rendered = renderer::render_subject_body($subject, $body, $data, $options);
        $fromuser = $fromuser ?: \core_user::get_noreply_user();

        return (bool) email_to_user(
            $user,
            $fromuser,
            $rendered['subject'],
            $rendered['plain'],
            $rendered['html']
        );
    }
}

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

namespace local_moderncommerce\notifications\local\channel;

/**
 * Base class for outbound HTTP (webhook) channels — Slack, Teams.
 *
 * Endpoint channels post once per event to a configured URL (not to a person).
 * Uses Moodle's SSRF-safe \curl and optional HMAC signing. Adapted from the proven
 * local_modernenrolnotifier outbound channel.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class outbound_http_channel implements channel_interface {
    /**
     * Channel key segment used inside the config keys (e.g. 'slack' -> notify_slack_*).
     *
     * @return string
     */
    abstract protected function setting_prefix(): string;

    /**
     * Build the provider-specific JSON payload.
     *
     * @param string $subject Rendered subject.
     * @param string $plain Rendered plain-text body.
     * @param array $context Delivery context (includes 'row').
     * @return array
     */
    abstract protected function format_payload(string $subject, string $plain, array $context): array;

    #[\Override]
    public function is_endpoint(): bool {
        return true;
    }

    #[\Override]
    public function is_enabled(): bool {
        return (bool) get_config('local_moderncommerce', 'notify_' . $this->setting_prefix() . '_enabled')
            && $this->endpoint_url() !== '';
    }

    /**
     * Configured endpoint URL.
     *
     * @return string
     */
    protected function endpoint_url(): string {
        return trim((string) get_config('local_moderncommerce', 'notify_' . $this->setting_prefix() . '_url'));
    }

    /**
     * Optional HMAC signing secret.
     *
     * @return string
     */
    protected function secret(): string {
        return (string) get_config('local_moderncommerce', 'notify_' . $this->setting_prefix() . '_secret');
    }

    #[\Override]
    public function send(
        \stdClass $recipient,
        \stdClass $from,
        string $subject,
        string $plain,
        string $body,
        array $context
    ): bool {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $url = $this->endpoint_url();
        if ($url === '') {
            return false;
        }

        $json = json_encode($this->format_payload($subject, $plain, $context), JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $headers = ['Content-Type: application/json'];
        $secret = $this->secret();
        if ($secret !== '') {
            $headers[] = 'X-Moderncommerce-Signature: sha256=' . hash_hmac('sha256', $json, $secret);
        }

        // Moodle's curl wrapper enforces host security (SSRF protection) by default.
        $curl = new \curl();
        $curl->setHeader($headers);
        $curl->post($url, $json, ['CURLOPT_TIMEOUT' => 15, 'CURLOPT_CONNECTTIMEOUT' => 10]);

        if ($curl->get_errno()) {
            return false;
        }

        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        return $code >= 200 && $code < 300;
    }
}

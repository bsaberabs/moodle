<?php
// This file is part of Moodle - https://moodle.org/
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

namespace local_moderncommerce\hook;

/**
 * Lets another plugin supply the invoice or receipt of an order, for example one issued in a marketplace
 * seller's name and numbered by that seller.
 *
 * Oriskill addition (not in upstream Modern Commerce): dispatched by download_invoice.php once the viewer is
 * allowed to see the order. When no plugin provides a document, the store's own PDF is sent.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to supply the invoice or receipt of an order.')]
#[\core\attribute\tags('local_moderncommerce', 'order')]
final class order_document {

    /** @var string|null PDF content supplied by a plugin. */
    private ?string $content = null;

    /** @var string File name of that PDF. */
    private string $filename = '';

    /**
     * Constructor.
     *
     * @param object $order Order record.
     * @param string $type invoice or receipt.
     */
    public function __construct(
        /** @var object Order record. */
        public readonly object $order,
        /** @var string Document asked for: invoice or receipt. */
        public readonly string $type,
    ) {
    }

    /**
     * Send this PDF instead of the store's.
     *
     * @param string $filename File name.
     * @param string $content PDF content.
     */
    public function provide(string $filename, string $content): void {
        $this->filename = $filename;
        $this->content = $content;
    }

    /**
     * PDF supplied by a plugin, if any.
     *
     * @return array|null [file name, content]
     */
    public function get_document(): ?array {
        return $this->content === null ? null : [$this->filename, $this->content];
    }
}

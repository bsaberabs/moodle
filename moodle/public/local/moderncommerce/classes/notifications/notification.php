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

namespace local_moderncommerce\notifications;

/**
 * A notification event — the single value object producers build and hand to api::notify().
 *
 * Producers declare intent (event, category, template, recipient, placeholders); the
 * dispatcher resolves channels, priority and suppression from the category.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification {
    /** @var string Transactional category (receipts, confirmations). */
    const CAT_TRANSACTIONAL = 'transactional';
    /** @var string Reminder category (due-soon, pending nudges). */
    const CAT_REMINDER = 'reminder';
    /** @var string Dunning category (payment failures, retries). */
    const CAT_DUNNING = 'dunning';
    /** @var string Celebratory category (milestones, completions). */
    const CAT_CELEBRATORY = 'celebratory';
    /** @var string Marketing category (suppressible promotional sends). */
    const CAT_MARKETING = 'marketing';
    /** @var string Operational category (admin/store-ops alerts). */
    const CAT_OPERATIONAL = 'operational';

    /** @var string Producer component. */
    public $component;
    /** @var string Canonical event key. */
    public $eventkey;
    /** @var string Category. */
    public $category = self::CAT_TRANSACTIONAL;
    /** @var string|null Email-template key to render from. */
    public $templatekey = null;
    /** @var array Entity placeholder data. */
    public $placeholders = [];
    /** @var int[] Recipient user ids. */
    public $touserids = [];
    /** @var bool Send to store-ops admins. */
    public $toadmins = false;
    /** @var string|null Raw subject (when no template). */
    public $subject = null;
    /** @var string|null Raw body/summary (when no template). */
    public $summary = null;
    /** @var string|null Deep-link URL. */
    public $contexturl = null;
    /** @var int Related object id. */
    public $relatedid = 0;
    /** @var array|null Explicit channel override. */
    public $channels = null;
    /** @var string|null Explicit priority override. */
    public $priority = null;
    /** @var string|null Dedupe discriminator (e.g. '7d'); defaults to a daily bucket. */
    public $deduptag = null;

    /**
     * Build a notification for a producer component and event.
     *
     * @param string $component Producer component (e.g. local_moderncommerce).
     * @param string $eventkey Canonical event (e.g. order_paid).
     */
    public function __construct(string $component, string $eventkey) {
        $this->component = $component;
        $this->eventkey = $eventkey;
    }

    /**
     * Set the category.
     *
     * @param string $category One of the CAT_* constants.
     * @return self
     */
    public function category(string $category): self {
        $this->category = $category;
        return $this;
    }

    /**
     * Set the email-template key to render from.
     *
     * @param string $key Template key.
     * @return self
     */
    public function template(string $key): self {
        $this->templatekey = $key;
        return $this;
    }

    /**
     * Address a single learner/customer.
     *
     * @param int $userid Recipient user id.
     * @return self
     */
    public function to_user(int $userid): self {
        if ($userid > 0 && !in_array($userid, $this->touserids, true)) {
            $this->touserids[] = $userid;
        }
        return $this;
    }

    /**
     * Address several users.
     *
     * @param int[] $userids Recipient user ids.
     * @return self
     */
    public function to_users(array $userids): self {
        foreach ($userids as $id) {
            $this->to_user((int) $id);
        }
        return $this;
    }

    /**
     * Address store-operations admins (capability local/moderncommerce:receivenotificationops).
     *
     * @param bool $on Enable.
     * @return self
     */
    public function to_admins(bool $on = true): self {
        $this->toadmins = $on;
        return $this;
    }

    /**
     * Set entity placeholder data.
     *
     * @param array $data Placeholder key => value.
     * @return self
     */
    public function placeholders(array $data): self {
        $this->placeholders = $data + $this->placeholders;
        return $this;
    }

    /**
     * Set a raw subject (used when no template is given, e.g. ops alerts).
     *
     * @param string $subject Subject line (may contain placeholders).
     * @return self
     */
    public function subject(string $subject): self {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set a raw body/summary (used when no template is given).
     *
     * @param string $summary HTML/text body.
     * @return self
     */
    public function summary(string $summary): self {
        $this->summary = $summary;
        return $this;
    }

    /**
     * Set the deep-link URL.
     *
     * @param string $url Context URL.
     * @return self
     */
    public function context_url(string $url): self {
        $this->contexturl = $url;
        return $this;
    }

    /**
     * Set the related object id.
     *
     * @param int $id Object id.
     * @return self
     */
    public function related(int $id): self {
        $this->relatedid = $id;
        return $this;
    }

    /**
     * Override the resolved priority.
     *
     * @param string $priority high|normal|low.
     * @return self
     */
    public function priority(string $priority): self {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Override the resolved channels.
     *
     * @param array $channels Channel keys.
     * @return self
     */
    public function channels(array $channels): self {
        $this->channels = $channels;
        return $this;
    }

    /**
     * Set a dedupe discriminator so legitimate re-sends are not blocked.
     *
     * @param string $tag Discriminator (e.g. the reminder day '7d').
     * @return self
     */
    public function dedup_tag(string $tag): self {
        $this->deduptag = $tag;
        return $this;
    }
}

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

namespace local_moderncommerce\services;

use local_moderncommerce\api\order_api;

/**
 * Enrollment key redemption service.
 *
 * Resolves the courses a key grants (direct course targets plus product targets
 * expanded through product_courses), validates a key, and redeems it by enrolling
 * the buyer in every granted course.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class key_redemption_service {
    /**
     * Resolve every Moodle course ID a key grants access to.
     *
     * @param int $keyid Enrollment key ID.
     * @return int[] Distinct course IDs.
     */
    public static function get_target_courseids(int $keyid): array {
        global $DB;

        $courseids = [];

        // Direct course targets.
        $direct = $DB->get_fieldset_select(
            'local_moderncommerce_enrollkey_targets',
            'targetid',
            "enrollkeyid = :keyid AND includemode = 'include' AND targettype = 'course'",
            ['keyid' => $keyid]
        );
        foreach ($direct as $courseid) {
            $courseids[(int) $courseid] = true;
        }

        // Product targets (course product or bundle/program) expanded to included courses.
        $productids = $DB->get_fieldset_select(
            'local_moderncommerce_enrollkey_targets',
            'targetid',
            "enrollkeyid = :keyid AND includemode = 'include' AND targettype = 'product'",
            ['keyid' => $keyid]
        );
        $productids = array_values(array_unique(array_map('intval', $productids)));
        if (!empty($productids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($productids, SQL_PARAMS_NAMED, 'p');
            $rows = $DB->get_fieldset_sql(
                "SELECT courseid
                   FROM {local_moderncommerce_product_courses}
                  WHERE productid {$insql}
                    AND relationtype = 'included'",
                $inparams
            );
            foreach ($rows as $courseid) {
                $courseids[(int) $courseid] = true;
            }
        }

        return array_values(array_filter(array_keys($courseids), static function (int $id): bool {
            return $id > 1;
        }));
    }

    /**
     * Validate a key and preview what it grants for a user.
     *
     * @param string $keycode Key code.
     * @param int $userid User ID.
     * @return array ['valid' => bool, 'errorkey' => string|null, 'key' => \stdClass|null, 'courses' => array]
     */
    public static function preview(string $keycode, int $userid): array {
        global $DB;

        $key = $DB->get_record('local_moderncommerce_enrollkeys', ['keycode' => $keycode]);
        if (!$key) {
            return ['valid' => false, 'errorkey' => 'invalidkey', 'key' => null, 'courses' => []];
        }

        $errorkey = self::validation_error($key, $userid);
        $courses = self::describe_courses(self::get_target_courseids((int) $key->id), $userid);

        if ($errorkey === null && empty($courses)) {
            $errorkey = 'invalidkey';
        }

        return [
            'valid' => $errorkey === null,
            'errorkey' => $errorkey,
            'key' => $key,
            'courses' => $courses,
        ];
    }

    /**
     * Redeem a key: enrol the user in every granted course not already enrolled.
     *
     * @param string $keycode Key code.
     * @param int $userid User ID.
     * @param int|null $orderid Optional originating order ID.
     * @return array ['courses' => array, 'alreadyenrolled' => int]
     * @throws \moodle_exception When the key cannot be redeemed.
     */
    public static function redeem(string $keycode, int $userid, ?int $orderid = null): array {
        global $DB;

        $key = $DB->get_record('local_moderncommerce_enrollkeys', ['keycode' => $keycode]);
        if (!$key) {
            throw new \moodle_exception('invalidkey', 'local_moderncommerce');
        }

        $errorkey = self::validation_error($key, $userid);
        if ($errorkey !== null) {
            throw new \moodle_exception($errorkey, 'local_moderncommerce');
        }

        $courseids = self::get_target_courseids((int) $key->id);
        if (empty($courseids)) {
            throw new \moodle_exception('invalidkey', 'local_moderncommerce');
        }

        $now = time();
        $enrolled = [];
        $alreadyenrolled = 0;
        $firstcourse = null;

        foreach ($courseids as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course) {
                continue;
            }

            $coursecontext = \context_course::instance($course->id);
            if (is_enrolled($coursecontext, $userid)) {
                $alreadyenrolled++;
                continue;
            }

            enrolment_service::enrol_user_in_course($userid, $course->id, [
                'orderid' => $orderid ?: 0,
                'enrollkeyid' => (int) $key->id,
                'method' => 'key',
            ]);

            $DB->insert_record('local_moderncommerce_key_usage', (object) [
                'enrollkeyid' => (int) $key->id,
                'userid' => $userid,
                'orderid' => $orderid ?: null,
                'courseid' => (int) $course->id,
                'valueused' => 0,
                'ipaddress' => getremoteaddr(),
                'timeredeemed' => $now,
            ]);

            $enrolled[] = $course;
            if ($firstcourse === null) {
                $firstcourse = $course;
            }
        }

        if (empty($enrolled)) {
            // Every granted course was already enrolled.
            throw new \moodle_exception('alreadyenrolled', 'local_moderncommerce');
        }

        $DB->execute(
            "UPDATE {local_moderncommerce_enrollkeys}
                SET usedcount = usedcount + 1, timemodified = :now
              WHERE id = :id",
            ['now' => $now, 'id' => (int) $key->id]
        );

        if ($orderid && self::is_order_fulfilled((int) $orderid, $userid)) {
            $order = $DB->get_record('local_moderncommerce_orders', ['id' => (int) $orderid]);
            if ($order && $order->status === 'pending') {
                order_api::update_order_status((int) $orderid, 'paid');
            }
        }

        if ($firstcourse !== null) {
            try {
                $user = $DB->get_record('user', ['id' => $userid]);
                \local_moderncommerce\email_notifications::send_key_redemption($user, $firstcourse, $keycode);
            } catch (\Throwable $e) {
                debugging('Failed to send key redemption email: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        \local_moderncommerce\audit\audit_service::record('enrollment_key_redeemed', 'enrollment_key', (int) $key->id, [
            'actoruserid' => $userid,
            'subjectuserid' => $userid,
            'newdata' => [
                'keyid' => (int) $key->id,
                'keycode' => $key->keycode,
                'orderid' => $orderid ?: 0,
                'courseids' => array_map(static function ($course): int {
                    return (int) $course->id;
                }, $enrolled),
                'alreadyenrolled' => $alreadyenrolled,
            ],
            'source' => 'redeem',
        ]);

        return [
            'courses' => self::describe_courses(array_map(static function ($c): int {
                return (int) $c->id;
            }, $enrolled), $userid),
            'alreadyenrolled' => $alreadyenrolled,
        ];
    }

    /**
     * Return the language error key for an invalid key, or null when redeemable.
     *
     * @param \stdClass $key Key record.
     * @param int $userid User ID.
     * @return string|null
     */
    private static function validation_error(\stdClass $key, int $userid): ?string {
        global $DB;

        if ($key->status !== 'active') {
            return 'keynotactive';
        }
        if (!empty($key->expirydate) && $key->expirydate < time()) {
            return 'keyexpired';
        }
        if ((int) $key->maxuses > 0) {
            $usagecount = $DB->count_records('local_moderncommerce_key_usage', ['enrollkeyid' => $key->id]);
            if ($usagecount >= (int) $key->maxuses) {
                return 'keylimitreached';
            }
        }
        if ($DB->record_exists('local_moderncommerce_key_usage', ['enrollkeyid' => $key->id, 'userid' => $userid])) {
            return 'keyalreadyused';
        }

        return null;
    }

    /**
     * Describe a set of courses with enrolment status and URLs.
     *
     * @param int[] $courseids Course IDs.
     * @param int $userid User ID.
     * @return array
     */
    private static function describe_courses(array $courseids, int $userid): array {
        global $DB;

        $courses = [];
        foreach (array_unique($courseids) as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname');
            if (!$course) {
                continue;
            }
            $coursecontext = \context_course::instance($course->id);
            $courses[] = [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $coursecontext]),
                'alreadyenrolled' => is_enrolled($coursecontext, $userid),
                'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            ];
        }

        return $courses;
    }

    /**
     * Whether the user is enrolled in every course the order grants.
     *
     * @param int $orderid Order ID.
     * @param int $userid User ID.
     * @return bool
     */
    private static function is_order_fulfilled(int $orderid, int $userid): bool {
        $courseids = [];
        foreach (order_api::get_order_items($orderid) as $item) {
            if (!empty($item->courseid)) {
                $courseids[(int) $item->courseid] = true;
            }
            if (in_array((string) $item->itemtype, ['bundle', 'program'], true) && !empty($item->productid)) {
                foreach (self::product_courseids((int) $item->productid) as $courseid) {
                    $courseids[$courseid] = true;
                }
            }
        }

        $courseids = array_keys($courseids);
        if (empty($courseids)) {
            return false;
        }

        foreach ($courseids as $courseid) {
            if ($courseid <= 1) {
                continue;
            }
            if (!is_enrolled(\context_course::instance($courseid), $userid)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Included course IDs for a product.
     *
     * @param int $productid Product ID.
     * @return int[]
     */
    private static function product_courseids(int $productid): array {
        global $DB;

        return array_map('intval', $DB->get_fieldset_select(
            'local_moderncommerce_product_courses',
            'courseid',
            "productid = :productid AND relationtype = 'included'",
            ['productid' => $productid]
        ));
    }
}

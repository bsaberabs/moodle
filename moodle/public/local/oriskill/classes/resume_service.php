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

namespace local_oriskill;

use local_moderncommerce\api\order_api;
use local_secureandpay\gateway;
use local_secureandpay\hook\unmatched_notification;
use local_secureandpay\notification_handler;

/**
 * Payment schedules started on another platform (LearnyBox) that SecureAndPay goes on charging: taken over, so that
 * each instalment left is invoiced here and followed as usual (schedules, unpaid instalments, access).
 *
 * The schedules are imported from a file: who, which course, how many instalments, how many already paid. SecureAndPay
 * then notifies each charge: it goes to the imported schedule of that orderID, or to the only one still waiting for
 * its orderID with that e-mail address and amount whose next instalment SecureAndPay numbers it (a payment, of a known
 * amount and number). Any other charge is held for the team to attach to a schedule or to ignore: nothing is invoiced
 * for it. So is a charge of a schedule taken over that SecureAndPay numbers as an instalment paid before the takeover,
 * as one already counted here, as a later one than the next one counted here, or beyond the last one.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resume_service {

    /** Schedules taken over. */
    const TABLE = 'local_oriskill_resumed';

    /** Charges notified by SecureAndPay that no schedule explains. */
    const INBOX = 'local_oriskill_sapinbox';

    /** Rows read from one file. */
    const MAX_ROWS = 2000;

    /** Column names recognised for each field (lower case, without accents or punctuation). */
    const COLUMNS = [
        'email' => ['email', 'e mail', 'mail', 'courriel', 'adresse email', 'adresse e mail'],
        'firstname' => ['prenom', 'first name', 'firstname'],
        'lastname' => ['nom', 'nom de famille', 'last name', 'lastname'],
        'course' => ['formation', 'course', 'cours', 'produit', 'product', 'offre'],
        'count' => ['nombre d echeances', 'nombre echeances', 'nb echeances', 'echeances', 'nombre de paiements', 'count'],
        'paid' => ['echeances payees', 'payees', 'paiements effectues', 'deja paye', 'paid'],
        'amount' => ['montant de l echeance', 'montant echeance', 'montant', 'mensualite', 'amount'],
        'first' => ['premier versement', 'acompte', 'deposit', 'first'],
        'start' => ['date du premier paiement', 'premier paiement', 'date de commande', 'date de debut', 'date', 'start'],
        'reference' => ['reference secureandpay', 'reference de paiement', 'reference', 'orderid', 'order id', 'payid'],
    ];

    /** Columns a file must have. */
    const REQUIRED = ['email', 'count', 'paid', 'amount', 'start'];

    /** Most instalments of a schedule taken over. */
    const MAX_COUNT = 60;

    /**
     * SecureAndPay statuses a charge is attached on its own to a waiting schedule for: paid. A refusal or a stop would
     * tie the schedule to that orderID for good on the strength of an e-mail address alone: held for the team.
     */
    const MATCHED = ['00', '02'];

    /**
     * Why a charge of a schedule taken over is held, and whether the team can still apply it: number, a later instalment
     * than the next one (applied once checked); counted, one already counted here since the takeover; paidbefore, one
     * paid before the takeover; beyond, past the last instalment (never applied: counted and invoiced twice, or beyond).
     */
    const REASONS = ['number' => true, 'counted' => false, 'paidbefore' => false, 'beyond' => false];

    /** SecureAndPay statuses of a charge of a schedule taken over that is checked against it: paid or refused. */
    const CHECKED = ['00', '02', '05'];

    /** @var bool Held charges are being applied again: the hook leaves them alone. */
    protected static bool $replaying = false;

    /** @var bool The team applies a held charge whose number is not the next one: it is not held again for that. */
    protected static bool $forcing = false;

    /** @var array Trainers whose lock this request holds (creator id => true). */
    protected static array $locked = [];

    /** @var array|null Trainers' SecureAndPay accounts, read once per request: [creator, merchant]. */
    protected static ?array $merchants = null;

    // ----- Importing the schedules ---------------------------------------------------------------------------

    /**
     * Take over the schedules of a CSV file, one per row. A schedule already imported is left as it is, so the same
     * file can be imported again (a SecureAndPay reference it lacked is filled in).
     *
     * @param \stdClass $creator Trainer.
     * @param string $path File.
     * @param int $courseid Course of the rows when the file has no course column (0 for none).
     * @param int $cut Unpaid instalments before the access is cut (one of instalment_service::CUTS).
     * @param bool $enrol Give access to the course to the buyers who do not have it.
     * @return \stdClass {rows, created, skipped, failed, errors (of [row, email, error])}
     */
    public static function import(\stdClass $creator, string $path, int $courseid, int $cut, bool $enrol = true): \stdClass {
        \core_php_time_limit::raise();
        $handle = fopen($path, 'r');
        $first = $handle ? (string) fgets($handle) : '';
        if (trim($first) === '') {
            throw new \moodle_exception('import:empty', 'local_oriskill');
        }
        $first = contact_import::to_utf8(preg_replace('/^\xEF\xBB\xBF/', '', $first));
        $delimiter = contact_import::delimiter($first);
        $columns = self::columns(str_getcsv(rtrim($first, "\r\n"), $delimiter, '"', ''));
        if ($missing = array_diff(self::REQUIRED, array_keys($columns))) {
            fclose($handle);
            throw new \moodle_exception('resume:err_columns', 'local_oriskill', '',
                implode(', ', array_map(fn($field) => get_string('resume:col_' . $field, 'local_oriskill'), $missing)));
        }
        // The trainer's courses, by number, name and short name.
        $courses = [];
        foreach (seller_service::priced_courses($creator) as $id => $priced) {
            $courses['#' . $id] = $priced->course;
            $courses[\core_text::strtolower(trim($priced->course->fullname))] = $priced->course;
            $courses[\core_text::strtolower(trim($priced->course->shortname))] = $priced->course;
        }

        $result = (object) ['rows' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        while ($result->rows < self::MAX_ROWS && ($line = fgets($handle)) !== false) {
            // A quoted value may hold line breaks: read on until its closing quote.
            while (substr_count($line, '"') % 2 === 1 && ($next = fgets($handle)) !== false) {
                $line .= $next;
            }
            if (trim($line) === '') {
                continue;
            }
            $result->rows++;
            $cells = array_map('trim', str_getcsv(rtrim(contact_import::to_utf8($line), "\r\n"), $delimiter, '"', ''));
            $cell = fn(string $field) => isset($columns[$field]) ? (string) ($cells[$columns[$field]] ?? '') : '';
            $name = $cell('course');
            try {
                $made = self::create($creator, [
                    'email' => $cell('email'), 'firstname' => $cell('firstname'), 'lastname' => $cell('lastname'),
                    'course' => $name !== '' ? ($courses[\core_text::strtolower($name)] ?? $courses['#' . $name] ?? null)
                        : ($courses['#' . $courseid] ?? null),
                    'count' => (int) $cell('count'), 'paid' => (int) $cell('paid'), 'amount' => self::number($cell('amount')),
                    'first' => self::number($cell('first')), 'start' => contact_import::date($cell('start')),
                    'reference' => $cell('reference')], $cut, $enrol);
                $result->{$made ? 'created' : 'skipped'}++;
            } catch (\Throwable $e) {
                $result->failed++;
                if (count($result->errors) < contact_import::MAX_ERRORS) {
                    $result->errors[] = ['row' => $result->rows + 1, 'email' => \core_text::substr($cell('email'), 0, 100),
                        'error' => $e->getMessage()];
                }
            }
        }
        fclose($handle);
        log_service::add($creator, 'instalments_resumed', ['created' => $result->created, 'skipped' => $result->skipped,
            'failed' => $result->failed]);
        return $result;
    }

    /**
     * Field of each column of a file, from its name.
     *
     * @param string[] $headers Column names.
     * @return array field => column index
     */
    protected static function columns(array $headers): array {
        $columns = [];
        foreach ($headers as $index => $header) {
            $key = trim(preg_replace('/[^a-z0-9]+/', ' ', \core_text::strtolower(\core_text::specialtoascii($header))));
            foreach (self::COLUMNS as $field => $names) {
                if (!isset($columns[$field]) && in_array($key, $names, true)) {
                    $columns[$field] = $index;
                    break;
                }
            }
        }
        return $columns;
    }

    /**
     * An amount cell (1 249,50 € or 1249.50) as a number.
     *
     * @param string $value Cell.
     * @return float 0 when not a number.
     */
    protected static function number(string $value): float {
        $number = unformat_float(preg_replace('/[\s\x{00a0}\x{202f}€]/u', '', $value), true);
        return is_numeric($number) ? (float) $number : 0.0;
    }

    /**
     * Take over one schedule: an order of the course in the buyer's name, already paid for the instalments charged on
     * the other platform, and its follow-up. It is not a new sale: no receipt, commission, tag or webhook, and no
     * invoice for what was paid before.
     *
     * @param \stdClass $creator Trainer.
     * @param array $row email, firstname, lastname, course (one of the trainer's courses), count, paid, amount, first
     *                   (0 when equal), start (time of the first payment), reference ('' when unknown).
     * @param int $cut Unpaid instalments before the access is cut.
     * @param bool $enrol Give access to the course to a buyer who does not have it (an account is made if needed).
     * @return bool False when this schedule was taken over before (left as it is).
     */
    public static function create(\stdClass $creator, array $row, int $cut, bool $enrol = true): bool {
        global $CFG, $DB;

        $error = fn(string $key) => new \moodle_exception($key, 'local_oriskill');
        $email = \core_text::strtolower(trim((string) ($row['email'] ?? '')));
        if ($email === '' || \core_text::strlen($email) > 100 || !validate_email($email)) {
            throw $error('import:bademail');
        }
        if (!$course = $row['course'] ?? null) {
            throw $error('resume:err_course');
        }
        $count = (int) ($row['count'] ?? 0);
        $paid = (int) ($row['paid'] ?? 0);
        if ($count < 2 || $count > self::MAX_COUNT || $paid < 1 || $paid >= $count) {
            throw $error('resume:err_count');
        }
        $amount = round((float) ($row['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw $error('resume:err_amount');
        }
        $start = (int) ($row['start'] ?? 0);
        if ($start <= 0 || $start > time()) {
            throw $error('resume:err_start');
        }
        $first = round((float) ($row['first'] ?? 0), 2);
        $first = $first > 0 && abs($first - $amount) >= 0.01 ? $first : 0.0;
        $reference = trim((string) ($row['reference'] ?? ''));

        // One at a time per trainer, as the charges notified: the same file imported twice at once must not create a
        // schedule twice, and a charge held meanwhile must be there for replay() below.
        return self::locked((int) $creator->id, function() use ($CFG, $DB, $creator, $course, $row, $email, $count, $paid,
                $amount, $first, $start, $cut, $enrol, $reference, $error): bool {
            // The same buyer, course, number and amount of instalments: imported before.
            foreach ($DB->get_records(self::TABLE, ['creatorid' => $creator->id, 'email' => $email,
                    'courseid' => $course->id]) as $existing) {
                $schedule = $DB->get_record('local_oriskill_instalments', ['id' => $existing->instalmentid]);
                if ($schedule && (int) $schedule->count === $count && abs((float) $schedule->amount - $amount) < 0.005) {
                    if ($reference !== '' && $existing->reference === null) {
                        self::set_reference($existing, $reference);
                        self::replay($creator, $reference);
                    }
                    return false;
                }
            }
            if (\core_text::strlen($reference) > 100 || ($reference !== '' && $DB->record_exists(
                    'local_moderncommerce_payment_attempts', ['gateway' => gateway::GATEWAY, 'reference' => $reference]))) {
                throw $error('resume:err_reference');
            }
            $user = $DB->get_record_select('user', 'LOWER(email) = ? AND deleted = 0 AND mnethostid = ?',
                [$email, $CFG->mnet_localhost_id], '*', IGNORE_MULTIPLE);
            if (!$user && !$enrol) {
                throw $error('resume:err_noaccount');
            }

            $transaction = $DB->start_delegated_transaction();
            try {
                self::insert($creator, $course, $user ?: null, $email, $row, $count, $paid, $amount, $first, $start,
                    $cut, $enrol, $reference);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            if ($reference !== '') {
                // Charges of this schedule notified before it was imported.
                self::replay($creator, $reference);
            }
            return true;
        });
    }

    /**
     * Run under the trainer's lock, which the charges notified to the trainer's account and the team's actions share.
     * Taken once per request: a call made while it is held (a charge replayed during an import) runs at once.
     *
     * @param int $creatorid Trainer.
     * @param callable $callback What to run.
     * @return mixed What it returns.
     */
    protected static function locked(int $creatorid, callable $callback) {
        if (!empty(self::$locked[$creatorid])) {
            return $callback();
        }
        $lock = \core\lock\lock_config::get_lock_factory('local_oriskill_resume')->get_lock('creator' . $creatorid, 30);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        self::$locked[$creatorid] = true;
        try {
            return $callback();
        } finally {
            unset(self::$locked[$creatorid]);
            $lock->release();
        }
    }

    /**
     * Write a schedule taken over, once create() has checked it (inside its transaction).
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course of the trainer.
     * @param \stdClass|null $user Buyer's account, if there is one.
     * @param string $email Buyer's e-mail address, lower case.
     * @param array $row Row of the file (firstname, lastname).
     * @param int $count Instalments in all.
     * @param int $paid Instalments paid on the other platform.
     * @param float $amount Each instalment.
     * @param float $first First instalment when it differs, else 0.
     * @param int $start Time of the first payment.
     * @param int $cut Unpaid instalments before the access is cut.
     * @param bool $enrol Give access to the course to a buyer who does not have it.
     * @param string $reference SecureAndPay orderID, '' when unknown.
     */
    protected static function insert(\stdClass $creator, \stdClass $course, ?\stdClass $user, string $email, array $row,
            int $count, int $paid, float $amount, float $first, int $start, int $cut, bool $enrol, string $reference): void {
        global $DB;

        if ($enrol && !($user && is_enrolled(\context_course::instance($course->id), $user, '', false))) {
            // As for an import of members: no e-mail unless the trainer's access e-mail is on for imports.
            [$user] = access_mail::with_trigger('import', fn() => learning_service::offer_access($course, $email,
                trim((string) ($row['firstname'] ?? '')) ?: strstr($email, '@', true),
                trim((string) ($row['lastname'] ?? '')) ?: '-', 0, 0, false));
        }
        $order = order_api::create_course_order((int) $user->id, (int) $course->id,
            round(($first ?: $amount) + $amount * ($count - 1), 2), ['instalments' => ['count' => $count,
                'amount' => $amount] + ($first ? ['first' => $first] : []) + ['resumed' => true, 'paidbefore' => $paid]]);
        // Whoever imports the file did not bring this sale.
        $DB->delete_records('local_oriskill_referrals', ['orderid' => $order->id]);
        $now = time();
        // Paid since the first instalment, on the other platform: written as such, without the order paid event
        // (and its receipt, commissions, tags and webhooks).
        $DB->update_record('local_moderncommerce_orders', (object) ['id' => $order->id, 'status' => 'paid',
            'customeremail' => $email, 'timecreated' => $start, 'timepaid' => $start, 'timemodified' => $now]);
        if ($DB->get_manager()->table_exists('local_moderncommerce_order_operational')) {
            $DB->set_field('local_moderncommerce_order_operational', 'paymentstatus', 'paid', ['orderid' => $order->id]);
        }
        // Monthly from the first payment: the next instalments and the unpaid ones are counted from it.
        $instalmentid = $DB->insert_record('local_oriskill_instalments', (object) ['orderid' => $order->id,
            'userid' => $user->id, 'courseid' => $course->id, 'count' => $count, 'amount' => $amount,
            'firstamount' => $first, 'paid' => $paid, 'extrapaid' => 0,
            'cutafter' => in_array($cut, instalment_service::CUTS, true) ? $cut : 1, 'status' => 'active',
            'latesince' => 0, 'timecreated' => $start, 'timemodified' => $now]);
        $resumed = (object) ['creatorid' => $creator->id, 'orderid' => $order->id, 'instalmentid' => $instalmentid,
            'userid' => $user->id, 'courseid' => $course->id, 'email' => $email, 'reference' => null,
            'paidbefore' => $paid, 'timecreated' => $now, 'timemodified' => $now];
        $resumed->id = $DB->insert_record(self::TABLE, $resumed);
        if ($reference !== '') {
            self::set_reference($resumed, $reference);
        }
    }

    // ----- Charges notified by SecureAndPay ------------------------------------------------------------------

    /**
     * SecureAndPay notified a charge no order here was paid with. Signed by a trainer's account, it goes to the schedule
     * taken over with that orderID, or to the only one still waiting for its orderID with that e-mail address and
     * amount whose next instalment it is; otherwise it is held for the team. Not signed by a trainer: left alone
     * (rejected as before).
     *
     * @param unmatched_notification $hook Hook.
     */
    public static function unmatched_notification(unmatched_notification $hook): void {
        global $DB;

        $reference = trim((string) ($hook->data['PAYID'] ?? ''));
        // An orderID an order here was paid with (its order number did not agree): rejected as before.
        if (self::$replaying || $reference === '' || \core_text::strlen($reference) > 100 || $DB->record_exists(
                'local_moderncommerce_payment_attempts', ['gateway' => gateway::GATEWAY, 'reference' => $reference])) {
            return;
        }
        if (!$creator = self::signing_creator($hook)) {
            return;
        }
        // One at a time per trainer: two charges of a schedule notified together must not both attach it.
        self::locked((int) $creator->id, function() use ($DB, $hook, $creator, $reference) {
            $resumed = $DB->get_record(self::TABLE, ['creatorid' => $creator->id, 'reference' => $reference], '*',
                IGNORE_MULTIPLE) ?: self::waiting_schedule($creator, $hook->data);
            if (!$resumed) {
                self::hold($creator, $hook->data, $hook->rawdata, $hook->signature, $reference);
                $hook->set_outcome('held');
                return;
            }
            if ($resumed->reference === null) {
                self::set_reference($resumed, $reference);
                // Charges of this orderID held before, oldest first, ahead of this one: none is left behind, and this
                // one is then checked against the instalments they paid.
                self::replay($creator, $reference);
            }
            $hook->set_order(order_api::get_order((int) $resumed->orderid));
        });
    }

    /**
     * Trainer whose SecureAndPay account signed a notification.
     *
     * @param unmatched_notification $hook Hook.
     * @return \stdClass|null
     */
    protected static function signing_creator(unmatched_notification $hook): ?\stdClass {
        global $DB;

        if (trim($hook->signature) === '') {
            return null;
        }
        // The accounts are read once per request (a replay goes through several notifications).
        if (self::$merchants === null) {
            self::$merchants = [];
            foreach ($DB->get_records_select('local_oriskill_creators', 'sap_key IS NOT NULL') as $creator) {
                try {
                    if ($merchant = seller_service::merchant($creator)) {
                        self::$merchants[] = [$creator, $merchant];
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }
        foreach (self::$merchants as [$creator, $merchant]) {
            if ($hook->signed_by($merchant)) {
                return $creator;
            }
        }
        return null;
    }

    /**
     * The only schedule of the trainer still waiting for its orderID that a charge can belong to: a payment of a known
     * amount, same e-mail address, same instalment amount, still running, and numbered by SecureAndPay as the
     * schedule's next instalment. A charge SecureAndPay does not number is held: the e-mail address and the amount alone
     * do not tie a schedule to an orderID for good.
     *
     * @param \stdClass $creator Trainer.
     * @param array $data Notification.
     * @return \stdClass|null
     */
    protected static function waiting_schedule(\stdClass $creator, array $data): ?\stdClass {
        global $DB;

        $email = \core_text::strtolower(trim((string) ($data['MailClient'] ?? '')));
        if ($email === '' || !in_array((string) ($data['TransStatus'] ?? ''), self::MATCHED, true)
                || !is_numeric($data['PayAmount'] ?? null)) {
            return null;
        }
        $amount = round((float) $data['PayAmount'] / 100, 2);
        // The instalment as SecureAndPay numbers it (echeanceNumber, the first payment being number 1), 0 when not told.
        $number = is_numeric($data['echeanceNumber'] ?? null) ? max(0, (int) $data['echeanceNumber']) : 0;
        if ($number <= 0) {
            return null;
        }
        $found = [];
        foreach ($DB->get_records(self::TABLE, ['creatorid' => $creator->id, 'email' => $email, 'reference' => null])
                as $resumed) {
            $schedule = $DB->get_record('local_oriskill_instalments', ['id' => $resumed->instalmentid]);
            if (!$schedule || in_array($schedule->status, ['completed', 'cancelled'], true)
                    || abs((float) $schedule->amount - $amount) >= 0.005) {
                continue;
            }
            $next = (int) $schedule->paid + (int) $schedule->extrapaid + 1;
            // Beyond the last instalment, or another one than the next: not this schedule's charge, even when it is the
            // only schedule of the buyer (the file may be wrong): the team decides.
            if ($next <= (int) $schedule->count && $number === $next) {
                $found[] = $resumed;
            }
        }
        return count($found) === 1 ? $found[0] : null;
    }

    /**
     * Keep a charge, once, for the team to attach, apply or ignore.
     *
     * @param \stdClass $creator Trainer whose account signed it.
     * @param array $data Notification, decoded.
     * @param string $rawdata Base64 'data' field as posted.
     * @param string $signature 'signature' field as posted.
     * @param string $reference SecureAndPay orderID.
     * @param string|null $reason Why a charge of a schedule taken over is held (one of REASONS), null for a charge no
     *                            schedule explains.
     */
    protected static function hold(\stdClass $creator, array $data, string $rawdata, string $signature, string $reference,
            ?string $reason = null): void {
        global $DB;

        $key = ['creatorid' => $creator->id, 'reference' => $reference,
            'bankid' => \core_text::substr((string) ($data['banqueId'] ?? ''), 0, 64),
            'transstatus' => \core_text::substr((string) ($data['TransStatus'] ?? ''), 0, 5)];
        if ($existing = $DB->get_record(self::INBOX, $key, 'id, state, outcome', IGNORE_MULTIPLE)) {
            if ($existing->state === 'held' && $reason !== null && $existing->outcome !== $reason) {
                // Held again on replay, for this reason now.
                $DB->update_record(self::INBOX, (object) ['id' => $existing->id, 'outcome' => $reason,
                    'timemodified' => time()]);
            }
            return;
        }
        $now = time();
        $DB->insert_record(self::INBOX, (object) ($key + [
            'amount' => is_numeric($data['PayAmount'] ?? null) ? round((float) $data['PayAmount'] / 100, 2) : 0,
            'email' => \core_text::substr(trim((string) ($data['MailClient'] ?? '')), 0, 255),
            'buyer' => \core_text::substr(trim(($data['PrenomClient'] ?? '') . ' ' . ($data['NomClient'] ?? '')), 0, 255),
            'label' => \core_text::substr(trim((string) ($data['InfosCommande'] ?? '')), 0, 255),
            'rawdata' => $rawdata, 'signature' => \core_text::substr($signature, 0, 128), 'state' => 'held',
            'resumedid' => 0, 'outcome' => $reason, 'handledby' => 0, 'timecreated' => $now, 'timemodified' => $now]));
    }

    /**
     * A charge (paid or refused) of a schedule taken over, verified and not recorded yet: held for the team, rather
     * than applied, when SecureAndPay numbers it as an instalment paid before the takeover or already counted here
     * (never applied: it would be counted and invoiced twice), as a later one than the next one counted here (applied
     * once the team checked it), or when it goes beyond the last instalment (never applied).
     *
     * Notified directly (not replayed under the trainer's lock), the whole charge is handled again under that lock, as
     * the replays and the team's actions are: two charges notified together are then checked one after the other
     * against the instalments counted, never both against the same count.
     *
     * @param \local_secureandpay\hook\resumed_charge $hook Hook.
     */
    public static function resumed_charge(\local_secureandpay\hook\resumed_charge $hook): void {
        global $DB;

        $resumed = $DB->get_record(self::TABLE, ['orderid' => $hook->order->id]);
        $schedule = $resumed ? $DB->get_record('local_oriskill_instalments', ['id' => $resumed->instalmentid]) : null;
        $creator = $resumed ? $DB->get_record('local_oriskill_creators', ['id' => $resumed->creatorid]) : null;
        if (!$schedule || !$creator) {
            return;
        }
        if (empty(self::$locked[(int) $creator->id])) {
            // The notification is verified, decided and applied again, from the start, under the lock (the invoices'
            // lock is taken inside it, never the other way round): its outcome is this one's.
            $hook->set_outcome(self::locked((int) $creator->id,
                fn() => notification_handler::handle($hook->rawdata, $hook->signature)));
            return;
        }
        $reason = self::reason($resumed, $schedule, $hook->number(),
            in_array((string) ($hook->data['TransStatus'] ?? ''), self::MATCHED, true), $hook->paid);
        if ($reason === null || ($reason === 'number' && self::$forcing)) {
            return;
        }
        self::hold($creator, $hook->data, $hook->rawdata, $hook->signature, (string) $hook->data['PAYID'], $reason);
        $hook->set_outcome('held');
    }

    /**
     * Why a charge (paid or refused) of a schedule taken over is not applied as the next instalment.
     *
     * @param \stdClass $resumed Schedule taken over.
     * @param \stdClass $schedule Its instalments (local_oriskill_instalments).
     * @param int $number The instalment as SecureAndPay numbers it, 0 when not told.
     * @param bool $paying A payment (else a refusal).
     * @param int $paid Instalments counted as paid so far (those paid before the takeover included).
     * @return string|null One of REASONS, null when it is applied.
     */
    protected static function reason(\stdClass $resumed, \stdClass $schedule, int $number, bool $paying, int $paid): ?string {
        // The next instalment: instalments caught up through a payment link (extrapaid) count too.
        $next = $paid + (int) $schedule->extrapaid + 1;
        if ($number && $number <= (int) $resumed->paidbefore) {
            return 'paidbefore';
        }
        if ($number > (int) $schedule->count || ($paying && $next > (int) $schedule->count)) {
            return 'beyond';
        }
        if ($number && $number < $next) {
            // Counted here already, since the takeover: applying it would count and invoice that instalment twice.
            return 'counted';
        }
        return $number > $next ? 'number' : null;
    }

    /**
     * The instalment a held charge is, as SecureAndPay numbers it.
     *
     * @param \stdClass $held Held charge (with its notification).
     * @return int 0 when the notification does not tell.
     */
    protected static function held_number(\stdClass $held): int {
        $data = json_decode((string) base64_decode((string) $held->rawdata, true), true);
        return is_numeric($data['echeanceNumber'] ?? null) ? max(0, (int) $data['echeanceNumber']) : 0;
    }

    /**
     * An instalment of a schedule taken over was paid: when SecureAndPay numbers it otherwise than counted here (applied
     * by the team although the number differed), the team is told in the log. Nothing is corrected: the invoice keeps
     * the number counted here.
     *
     * @param \local_secureandpay\event\instalment_paid $event Event.
     */
    public static function instalment_paid(\local_secureandpay\event\instalment_paid $event): void {
        global $DB;

        $number = (int) ($event->other['number'] ?? 0);
        if (!$number || !($resumed = $DB->get_record(self::TABLE, ['orderid' => $event->objectid]))) {
            return;
        }
        $paid = (int) $event->other['paid'] + (int) $DB->get_field('local_oriskill_instalments', 'extrapaid',
            ['id' => $resumed->instalmentid]);
        if ($number !== $paid && ($creator = $DB->get_record('local_oriskill_creators', ['id' => $resumed->creatorid]))) {
            log_service::add($creator, 'instalments_mismatch', ['number' => $number, 'paid' => $paid,
                'count' => (int) $event->other['count']], (int) $resumed->courseid, (int) $resumed->userid);
        }
    }

    /**
     * Give a schedule its SecureAndPay orderID: the charges notified with it then find the schedule's order.
     *
     * @param \stdClass $resumed Schedule taken over, without an orderID.
     * @param string $reference SecureAndPay orderID.
     */
    protected static function set_reference(\stdClass $resumed, string $reference): void {
        global $DB;

        gateway::resume_instalments($DB->get_record('local_moderncommerce_orders', ['id' => $resumed->orderid], '*',
            MUST_EXIST), $reference);
        $DB->update_record(self::TABLE, (object) ['id' => $resumed->id, 'reference' => $reference,
            'timemodified' => time()]);
        $resumed->reference = $reference;
    }

    /**
     * Apply the held charges of an orderID, oldest first, now that a schedule has it. A charge that cannot be applied
     * stays held, and so does one that is still not the next instalment, unless the team applies that very charge.
     *
     * @param \stdClass $creator Trainer.
     * @param string $reference SecureAndPay orderID.
     * @param int $force Held charge the team applies although its number is not the next one (0 for none): only it is
     *                   applied then.
     * @return int Charges applied.
     */
    protected static function replay(\stdClass $creator, string $reference, int $force = 0): int {
        global $DB, $USER;

        $resumedid = (int) $DB->get_field(self::TABLE, 'id', ['creatorid' => $creator->id, 'reference' => $reference],
            IGNORE_MULTIPLE);
        $applied = 0;
        $conditions = ['creatorid' => $creator->id, 'reference' => $reference, 'state' => 'held'] +
            ($force ? ['id' => $force] : []);
        foreach ($DB->get_records(self::INBOX, $conditions, 'timecreated ASC, id ASC') as $held) {
            self::$replaying = true;
            self::$forcing = $force > 0;
            try {
                $outcome = notification_handler::handle((string) $held->rawdata, (string) $held->signature);
            } catch (\Throwable $e) {
                debugging('SecureAndPay charge held: ' . $e->getMessage(), DEBUG_DEVELOPER);
                continue;
            } finally {
                self::$replaying = false;
                self::$forcing = false;
            }
            if ($outcome === 'held') {
                // Still not the next instalment (or never to be applied): it stays held, with its reason.
                continue;
            }
            // Dealt with: the notification itself is not kept any longer.
            $DB->update_record(self::INBOX, (object) ['id' => $held->id, 'state' => 'applied', 'resumedid' => $resumedid,
                'outcome' => \core_text::substr($outcome, 0, 100), 'rawdata' => null, 'signature' => null,
                'handledby' => isloggedin() && !isguestuser() ? (int) $USER->id : 0, 'timemodified' => time()]);
            $applied++;
        }
        return $applied;
    }

    // ----- The team's actions and lists ----------------------------------------------------------------------

    /**
     * Charges held for the trainer's team, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function held(\stdClass $creator): array {
        global $DB;
        $held = $DB->get_records(self::INBOX, ['creatorid' => $creator->id, 'state' => 'held'], 'timecreated DESC, id DESC');
        foreach ($held as $charge) {
            // The instalment as SecureAndPay numbers it, 0 when the notification does not tell.
            $charge->number = self::held_number($charge);
            // The schedule that has its orderID already (it is applied to that one, or ignored), 0 when none has it.
            $charge->scheduleid = (int) $DB->get_field(self::TABLE, 'id', ['creatorid' => $creator->id,
                'reference' => $charge->reference], IGNORE_MULTIPLE);
            // Why it is held (one of REASONS), '' for a charge no schedule explains.
            $charge->reason = array_key_exists((string) $charge->outcome, self::REASONS) ? (string) $charge->outcome : '';
            // Whether the team can apply it: never an instalment paid before the takeover or counted here already, nor
            // one beyond the last.
            $charge->applicable = $charge->reason === '' || self::REASONS[$charge->reason];
        }
        return $held;
    }

    /**
     * Number of charges held for the trainer's team.
     *
     * @param \stdClass $creator Trainer.
     * @return int
     */
    public static function held_count(\stdClass $creator): int {
        global $DB;
        return $DB->count_records(self::INBOX, ['creatorid' => $creator->id, 'state' => 'held']);
    }

    /**
     * Schedules taken over by a trainer, newest first, with the buyer and where the payments stand.
     *
     * @param \stdClass $creator Trainer.
     * @param int $limit Most schedules read.
     * @return array of {id, reference, paidbefore, orderid, userid, courseid, email, count, amount, paid, extrapaid,
     *               status, timecreated (first payment), ordernumber, currency, coursename, user}
     */
    public static function schedules(\stdClass $creator, int $limit = 300): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT r.id, r.reference, r.paidbefore, r.orderid, r.userid, r.courseid, r.email, i.count, i.amount, i.paid,
                    i.extrapaid, i.status, i.timecreated, o.ordernumber, o.currency, c.fullname AS coursename
               FROM {" . self::TABLE . "} r
               JOIN {local_oriskill_instalments} i ON i.id = r.instalmentid
               JOIN {local_moderncommerce_orders} o ON o.id = r.orderid
               JOIN {course} c ON c.id = r.courseid
              WHERE r.creatorid = ?
           ORDER BY r.id DESC", [$creator->id], 0, $limit);
        foreach ($records as $record) {
            $record->user = \core_user::get_user($record->userid) ?: null;
        }
        return $records;
    }

    /**
     * Schedules a held charge can be attached to: still running and waiting for their orderID.
     *
     * @param \stdClass $creator Trainer.
     * @return array schedule id => buyer, course and instalments
     */
    public static function attachable(\stdClass $creator): array {
        $options = [];
        foreach (self::schedules($creator, 1000) as $s) {
            if ($s->reference === null && !in_array($s->status, ['completed', 'cancelled'], true)) {
                // html_writer::select() does not escape the labels.
                $options[$s->id] = s($s->user ? fullname($s->user) : $s->email) . ' · ' . format_string($s->coursename) .
                    ' · ' . $s->count . ' × ' . format_float((float) $s->amount, 2) . ' ' . s($s->currency);
            }
        }
        return $options;
    }

    /**
     * Attach a held charge to a schedule: the schedule takes its orderID, and every held charge of that orderID is
     * applied (invoice of an instalment paid, unpaid case of a refused one).
     *
     * @param \stdClass $creator Trainer.
     * @param int $heldid Held charge.
     * @param int $resumedid Schedule taken over.
     * @return int Charges applied.
     */
    public static function attach(\stdClass $creator, int $heldid, int $resumedid): int {
        global $DB;

        // Under the lock of the charges notified: one notified meanwhile is held before the replay, or applied after.
        return self::locked((int) $creator->id, function() use ($DB, $creator, $heldid, $resumedid): int {
            $held = $DB->get_record(self::INBOX, ['id' => $heldid, 'creatorid' => $creator->id, 'state' => 'held'], '*',
                MUST_EXIST);
            $resumed = $DB->get_record(self::TABLE, ['id' => $resumedid, 'creatorid' => $creator->id], '*', MUST_EXIST);
            if ($resumed->reference !== null && $resumed->reference !== $held->reference) {
                throw new \moodle_exception('resume:err_hasreference', 'local_oriskill');
            }
            // Checked against this schedule first, as SecureAndPay numbers it, with the rules of resumed_charge(): an
            // instalment paid before the takeover, counted here already, or beyond the last one is refused before the
            // schedule takes the orderID for good. (The replay below only counts more instalments: what is refused now
            // would be refused then too.)
            $schedule = $DB->get_record('local_oriskill_instalments', ['id' => $resumed->instalmentid], '*', MUST_EXIST);
            if (in_array((string) $held->transstatus, self::CHECKED, true)) {
                $reason = self::reason($resumed, $schedule, self::held_number($held),
                    in_array((string) $held->transstatus, self::MATCHED, true),
                    gateway::instalments_paid($DB->get_record('local_moderncommerce_orders', ['id' => $resumed->orderid],
                        '*', MUST_EXIST)));
                if ($reason !== null && !self::REASONS[$reason]) {
                    throw new \moodle_exception('resume:err_' . $reason, 'local_oriskill');
                }
            }
            if ($resumed->reference === null) {
                self::set_reference($resumed, (string) $held->reference);
            }
            // Every held charge of the orderID, oldest first, as SecureAndPay numbers them; then this one, chosen by the
            // team, even if its number is not the next one.
            $applied = self::replay($creator, (string) $held->reference);
            $reason = $DB->get_field(self::INBOX, 'outcome', ['id' => $held->id, 'state' => 'held']);
            if ($reason !== false && ($reason === null || !empty(self::REASONS[$reason]))) {
                $applied += self::replay($creator, (string) $held->reference, (int) $held->id);
                $reason = $DB->get_field(self::INBOX, 'outcome', ['id' => $held->id, 'state' => 'held']);
            }
            if ($reason !== false) {
                // This charge is still held: never applied (paid before the takeover, beyond the last instalment), or
                // it could not be (SecureAndPay account or key changed).
                throw new \moodle_exception(isset(self::REASONS[(string) $reason]) && !self::REASONS[$reason]
                    ? 'resume:err_' . $reason : 'resume:err_replay', 'local_oriskill');
            }
            log_service::add($creator, 'instalments_attached', ['reference' => $held->reference],
                (int) $resumed->courseid, (int) $resumed->userid);
            return $applied;
        });
    }

    /**
     * Apply a held charge whose orderID a schedule taken over has already (it was held for its number, or notified
     * while the schedule was being given its orderID).
     *
     * @param \stdClass $creator Trainer.
     * @param int $heldid Held charge.
     * @return int Charges applied.
     */
    public static function apply(\stdClass $creator, int $heldid): int {
        global $DB;

        $held = $DB->get_record(self::INBOX, ['id' => $heldid, 'creatorid' => $creator->id, 'state' => 'held'], '*',
            MUST_EXIST);
        $resumedid = (int) $DB->get_field(self::TABLE, 'id', ['creatorid' => $creator->id, 'reference' => $held->reference],
            IGNORE_MULTIPLE);
        if (!$resumedid) {
            throw new \moodle_exception('resume:err_noschedule', 'local_oriskill');
        }
        return self::attach($creator, $heldid, $resumedid);
    }

    /**
     * Ignore a held charge: it is neither invoiced nor followed here.
     *
     * @param \stdClass $creator Trainer.
     * @param int $heldid Held charge.
     */
    public static function ignore(\stdClass $creator, int $heldid): void {
        global $DB, $USER;

        // Under the lock of the charges notified and of the team's actions: a charge applied meanwhile (attached,
        // replayed) is no longer held when read here, and is not marked ignored after being invoiced.
        self::locked((int) $creator->id, function() use ($DB, $USER, $creator, $heldid): void {
            $held = $DB->get_record(self::INBOX, ['id' => $heldid, 'creatorid' => $creator->id, 'state' => 'held'], '*',
                MUST_EXIST);
            $DB->update_record(self::INBOX, (object) ['id' => $held->id, 'state' => 'ignored', 'rawdata' => null,
                'signature' => null, 'handledby' => (int) $USER->id, 'timemodified' => time()]);
            log_service::add($creator, 'instalments_ignored', ['reference' => $held->reference,
                'amount' => format_float((float) $held->amount, 2)]);
        });
    }
}

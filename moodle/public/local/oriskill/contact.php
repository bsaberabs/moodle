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

/**
 * One contact of the trainer's CRM: identity, tags and state on the left; infos, courses and transactions on the right.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\contact_service as cs;
use local_oriskill\creator_service;
use local_oriskill\log_service;
use local_moderncommerce\services\pricing_service;
use local_oriskill\team_service;

$id = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'infos', PARAM_ALPHA);
$tabs = ['infos', 'activity', 'emails', 'courses', 'transactions'];
$tab = in_array($tab, $tabs, true) ? $tab : 'infos';
$url = new moodle_url('/local/oriskill/contact.php', ['id' => $id, 'tab' => $tab]);
$creator = creator_service::require_current_creator($url, 'menu:contacts');
$contact = cs::require_contact($creator, $id);
$canchange = team_service::can_change(creator_service::current_role(), '/local/oriskill/contacts.php');

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    if (!$canchange) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    $message = get_string('changessaved');
    $type = \core\output\notification::NOTIFY_SUCCESS;
    try {
        switch ($action) {
            case 'save':
                $data = [];
                foreach (cs::FIELDS as $field) {
                    $data[$field] = optional_param($field, '', PARAM_NOTAGS);
                }
                $data['consentdirect'] = optional_param('consentdirect', 0, PARAM_BOOL);
                $data['consentpartners'] = optional_param('consentpartners', 0, PARAM_BOOL);
                cs::update($contact, $data);
                $labels = ['firstname' => get_string('firstname'), 'lastname' => get_string('lastname'),
                    'phone' => get_string('phone'), 'address' => get_string('address'),
                    'postcode' => get_string('postcode', 'local_oriskill'), 'city' => get_string('city'),
                    'country' => get_string('country'),
                    'consentdirect' => get_string('contacts:consentdirect', 'local_oriskill'),
                    'consentpartners' => get_string('contacts:consentpartners', 'local_oriskill')];
                $changes = [];
                foreach ($labels as $field => $label) {
                    $old = (string) $contact->$field;
                    $new = trim((string) $data[$field]);
                    if (str_starts_with($field, 'consent')) {
                        [$old, $new] = [get_string($old ? 'yes' : 'no'), get_string($new ? 'yes' : 'no')];
                    } else if ($field === 'country') {
                        $countries = get_string_manager()->get_list_of_countries();
                        [$old, $new] = [$countries[$old] ?? $old, $countries[$new] ?? $new];
                    }
                    $changes[$label] = [$old === '' ? '–' : $old, $new === '' ? '–' : $new];
                }
                log_service::add($creator, 'contact_saved', ['email' => $contact->email], null, null, $changes);
                break;
            case 'addtags':
                $tags = cs::parse_tags(required_param('tags', PARAM_RAW_TRIMMED));
                cs::add_tags($creator, [$contact->id], $tags);
                log_service::add($creator, 'contacts_addtags', ['count' => 1, 'tags' => implode(', ', $tags)]);
                break;
            case 'removetag':
                $tag = required_param('tag', PARAM_RAW_TRIMMED);
                cs::remove_tag($creator, [$contact->id], $tag);
                log_service::add($creator, 'contacts_removetag', ['count' => 1, 'tags' => $tag]);
                break;
            case 'disable':
            case 'enable':
                $was = get_string($contact->status === 'disabled' ? 'contacts:status_disabled' : 'contacts:status_active', 'local_oriskill');
                cs::set_disabled($creator, $contact, $action === 'disable');
                log_service::add($creator, 'contacts_' . $action, ['count' => 1], null, null,
                    [$contact->email => [$was, get_string($action === 'disable' ? 'contacts:status_disabled'
                        : 'contacts:status_active', 'local_oriskill')]]);
                break;
            case 'sendmail':
                \local_oriskill\email_log::send_direct($creator, $contact, required_param('subject', PARAM_TEXT),
                    required_param('message', PARAM_RAW));
                log_service::add($creator, 'contact_mailed', ['email' => $contact->email]);
                $message = get_string('email:sent', 'local_oriskill');
                break;
            case 'resendmail':
                if (!\local_oriskill\email_log::resend($creator, required_param('emailid', PARAM_INT))) {
                    throw new moodle_exception('email:notsent', 'local_oriskill');
                }
                log_service::add($creator, 'contact_mailresent', ['email' => $contact->email]);
                $message = get_string('email:sent', 'local_oriskill');
                break;
            case 'resetpassword':
                $account = $contact->userid ? core_user::get_user($contact->userid) : null;
                if (!$account || $account->deleted || $account->auth !== 'manual' || is_siteadmin($account)) {
                    throw new moodle_exception('email:nopassword', 'local_oriskill');
                }
                // A new password by e-mail, to change at the next login (the e-mail belongs to this trainer).
                \local_oriskill\email_log::for_creator((int) $creator->id, 'transactional',
                    fn() => reset_password_and_mail($account));
                set_user_preference('auth_forcepasswordchange', 1, $account);
                log_service::add($creator, 'contact_password', ['email' => $contact->email]);
                $message = get_string('email:passwordsent', 'local_oriskill');
                break;
            case 'delete':
                cs::delete($creator, [$contact->id]);
                log_service::add($creator, 'contacts_delete', ['count' => 1], null, null,
                    [get_string('change:deleted', 'local_oriskill') => [$contact->email, '–']]);
                redirect(new moodle_url('/local/oriskill/contacts.php'), get_string('contacts:deleted', 'local_oriskill'),
                    null, $type);
        }
    } catch (moodle_exception $e) {
        $message = $e->getMessage();
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    redirect($url, $message, null, $type);
}

$name = trim($contact->firstname . ' ' . $contact->lastname) ?: $contact->email;
$user = $contact->userid ? core_user::get_user($contact->userid) : null;
$tags = cs::tags_of([$contact->id])[$contact->id] ?? [];
$courses = cs::courses($creator, $contact);
$invoices = cs::invoices($creator, $contact);
$dateformat = get_string('strftimedate', 'langconfig');
$hidden = fn(string $action) =>
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
$post = fn(string $inner, array $attributes = []) => html_writer::tag('form', $inner,
    ['method' => 'post', 'action' => $url->out(false)] + $attributes);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/contacts.php', s($name));
echo html_writer::div(html_writer::link(new moodle_url('/local/oriskill/contacts.php'),
    '<i class="fa fa-arrow-left me-1" aria-hidden="true"></i>' . get_string('contacts:back', 'local_oriskill')), 'mb-3');

// Identity column.
$initials = core_text::strtoupper(core_text::substr($contact->firstname ?: $contact->email, 0, 1) .
    core_text::substr($contact->lastname, 0, 1));
$lastaccess = $user && $user->lastaccess ? get_string('contacts:lastactive', 'local_oriskill', format_time(time() - $user->lastaccess))
    : get_string('contacts:neveractive', 'local_oriskill');
$tagchips = '';
foreach ($tags as $tag) {
    $tagchips .= html_writer::span(s($tag) . ($canchange ? $post($hidden('removetag') .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tag', 'value' => $tag]) .
        html_writer::tag('button', '×', ['type' => 'submit', 'class' => 'btn btn-link btn-sm p-0 ms-1 text-reset',
            'title' => get_string('contacts:removetag', 'local_oriskill', s($tag))]), ['class' => 'd-inline']) : ''),
        'oriskill-badge me-1 mb-1');
}
$identity = html_writer::div(html_writer::span(s($initials), 'oriskill-initials is-large'), 'mb-2') .
    html_writer::div(s($name), 'oriskill-course-title') .
    html_writer::div(get_string('contacts:source_' . $contact->source, 'local_oriskill'), 'small text-muted mb-2') .
    html_writer::div(html_writer::link('mailto:' . $contact->email, s($contact->email)), 'text-break small') .
    ($contact->phone !== '' ? html_writer::div(s($contact->phone), 'small') : '') .
    html_writer::div($lastaccess, 'small text-muted mt-1') .
    html_writer::div(html_writer::span(get_string('contacts:status_' . $contact->status, 'local_oriskill'),
        'oriskill-badge ' . ($contact->status === 'active' ? 'is-good' : 'is-warn')) .
        ($canchange ? ' ' . $post($hidden($contact->status === 'active' ? 'disable' : 'enable') .
            html_writer::tag('button', get_string($contact->status === 'active' ? 'contacts:disable' : 'contacts:enable', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary']),
            ['class' => 'd-inline', 'onsubmit' => $contact->status === 'active' ? 'return confirm(' .
                json_encode(get_string('contacts:disable_confirm', 'local_oriskill')) . ')' : null]) : ''),
        'my-3') .
    html_writer::div(get_string('contacts:tags', 'local_oriskill'), 'oriskill-stat-label') .
    html_writer::div($tagchips ?: html_writer::span('–', 'text-muted'), 'mb-2') .
    ($canchange ? $post($hidden('addtags') . html_writer::div(
        html_writer::empty_tag('input', ['type' => 'text', 'name' => 'tags', 'required' => 'required',
            'class' => 'form-control form-control-sm', 'placeholder' => get_string('contacts:tags_help', 'local_oriskill'),
            'aria-label' => get_string('contacts:addtags', 'local_oriskill')]) .
        html_writer::tag('button', '+', ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-primary',
            'title' => get_string('contacts:addtags', 'local_oriskill')]), 'd-flex gap-1')) : '') .
    ($canchange ? html_writer::tag('details', html_writer::tag('summary', '<i class="fa fa-envelope me-1" aria-hidden="true"></i>' .
        get_string('email:write', 'local_oriskill'), ['class' => 'small fw-semibold']) .
        $post($hidden('sendmail') .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => 'subject', 'required' => 'required', 'maxlength' => 200,
                'class' => 'form-control form-control-sm mt-2', 'placeholder' => get_string('subject'), 'aria-label' => get_string('subject')]) .
            html_writer::tag('textarea', '', ['name' => 'message', 'required' => 'required', 'rows' => 5,
                'class' => 'form-control form-control-sm mt-1', 'placeholder' => get_string('message', 'message'),
                'aria-label' => get_string('message', 'message')]) .
            html_writer::tag('button', get_string('email:send', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-sm btn-primary mt-1'])),
        ['class' => 'mt-3']) : '') .
    ($canchange && $user && !$user->deleted && $user->auth === 'manual' && !is_siteadmin($user) ? $post($hidden('resetpassword') .
        html_writer::tag('button', '<i class="fa fa-key me-1" aria-hidden="true"></i>' . get_string('email:newpassword', 'local_oriskill'),
            ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary mt-2 w-100']),
        ['onsubmit' => 'return confirm(' . json_encode(get_string('email:newpassword_confirm', 'local_oriskill')) . ')']) : '') .
    ($canchange ? html_writer::div($post($hidden('delete') . html_writer::tag('button', get_string('contacts:delete', 'local_oriskill'),
        ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']), ['onsubmit' => 'return confirm(' .
        json_encode(get_string('contacts:delete_confirm', 'local_oriskill')) . ')']), 'mt-4') : '');

// Tabs, a dot on those with data.
$emails = \local_oriskill\email_log::for_contact($creator, $contact);
$hasdata = ['infos' => true, 'activity' => false, 'emails' => (bool) $emails, 'courses' => (bool) $courses,
    'transactions' => (bool) $invoices];
$nav = '';
foreach ($tabs as $name) {
    $nav .= html_writer::tag('li', html_writer::link(new moodle_url($url, ['tab' => $name]),
        get_string('contacts:tab_' . $name, 'local_oriskill') . ($hasdata[$name] && $name !== 'infos'
            ? html_writer::span('', 'oriskill-dot ms-1') : ''),
        ['class' => 'nav-link' . ($tab === $name ? ' active' : '')]), ['class' => 'nav-item']);
}
$content = '';
if ($tab === 'infos') {
    $facts = '';
    foreach ([
        [get_string('contacts:ltv', 'local_oriskill'), pricing_service::format_price((float) $contact->ltv, 'EUR')],
        [get_string('contacts:created', 'local_oriskill'), userdate($contact->timecreated, $dateformat)],
        [get_string('contacts:courses', 'local_oriskill'), count($courses)],
        [get_string('contacts:account', 'local_oriskill'), get_string($contact->userid ? 'yes' : 'no')],
    ] as [$label, $value]) {
        $facts .= html_writer::div(html_writer::div($label, 'oriskill-stat-label') . html_writer::div(s($value), 'fw-semibold'));
    }
    $input = fn(string $field, string $label) => html_writer::div(
        html_writer::tag('label', $label, ['class' => 'form-label small mb-1', 'for' => 'c-' . $field]) .
        html_writer::empty_tag('input', ['type' => 'text', 'name' => $field, 'id' => 'c-' . $field, 'value' => $contact->$field,
            'class' => 'form-control form-control-sm', 'disabled' => $canchange ? null : 'disabled']), 'col-sm-6');
    $check = fn(string $field) => html_writer::div(
        html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => $field, 'value' => 1, 'id' => 'c-' . $field,
            'class' => 'form-check-input', 'checked' => $contact->$field ? 'checked' : null,
            'disabled' => $canchange ? null : 'disabled']) .
        html_writer::tag('label', get_string('contacts:' . $field, 'local_oriskill'), ['for' => 'c-' . $field,
            'class' => 'form-check-label']), 'form-check');
    $content = html_writer::div($facts, 'oriskill-facts mb-4 border-0 pt-0') .
        $post($hidden('save') .
            html_writer::div($input('firstname', get_string('firstname')) . $input('lastname', get_string('lastname')) .
                $input('phone', get_string('phone')) . $input('address', get_string('address')) .
                $input('postcode', get_string('postcode', 'local_oriskill')) . $input('city', get_string('city')) .
                $input('country', get_string('country')), 'row g-2 mb-3') .
            html_writer::div(get_string('contacts:consents', 'local_oriskill'), 'oriskill-stat-label') .
            $check('consentdirect') . $check('consentpartners') .
            ($contact->consentdate ? html_writer::div(get_string('contacts:consentdate', 'local_oriskill',
                userdate($contact->consentdate)), 'small text-muted mt-1') : '') .
            ($canchange ? html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary mt-3'])
                : ''));
} else if ($tab === 'activity') {
    // Day by day, to the second.
    $days = [];
    foreach (cs::activity($creator, $contact) as $event) {
        $days[userdate($event->time, get_string('strftimedaydate', 'langconfig'))][] = $event;
    }
    foreach ($days as $day => $events) {
        $items = '';
        foreach ($events as $event) {
            $items .= html_writer::tag('li', html_writer::span('<i class="fa ' . $event->icon . ' fa-fw" aria-hidden="true"></i>',
                'oriskill-activity-icon' . ($event->tone ? ' is-' . $event->tone : '')) .
                html_writer::div(html_writer::div(s($event->text)) .
                    html_writer::div(userdate($event->time, '%H:%M:%S'), 'small text-muted'), 'flex-grow-1'),
                ['class' => 'd-flex gap-2 align-items-start mb-2']);
        }
        $content .= html_writer::div(html_writer::div(s($day), 'oriskill-stat-label mb-2') .
            html_writer::tag('ul', $items, ['class' => 'list-unstyled mb-0']), 'mb-3');
    }
} else if ($tab === 'emails') {
    // Preview of one of the trainer's e-mails, shown as it was sent (in a frame without scripts).
    $emailid = optional_param('emailid', 0, PARAM_INT);
    if ($emailid && isset($emails[$emailid]) && (int) $emails[$emailid]->creatorid === (int) $creator->id) {
        $sent = \local_oriskill\email_log::require_own($creator, $emailid);
        $html = preg_replace('~<img[^>]+emailopen\.php[^>]*>~i', '', (string) $sent->bodyhtml);
        $content .= html_writer::div(
            html_writer::div(html_writer::span(s($sent->subject), 'fw-semibold') . ' ' .
                html_writer::link(new moodle_url($url), get_string('closebuttontitle'), ['class' => 'btn btn-sm btn-outline-secondary']),
                'd-flex justify-content-between align-items-center gap-2 mb-2') .
            ($html !== '' ? html_writer::tag('iframe', '', ['srcdoc' => $html, 'sandbox' => '', 'title' => s($sent->subject),
                'style' => 'width: 100%; height: 28rem; border: 1px solid var(--ori-line); border-radius: .5rem; background: #fff'])
                : html_writer::tag('pre', s((string) $sent->bodytext), ['class' => 'small p-3 border rounded bg-light',
                    'style' => 'white-space: pre-wrap; max-height: 28rem; overflow: auto'])) .
            ($sent->attachment !== '' ? html_writer::div('<i class="fa fa-paperclip me-1" aria-hidden="true"></i>' .
                s($sent->attachment), 'small text-muted mt-1') : ''),
            'mb-4');
    }
    if (!$emails) {
        $content .= html_writer::div(get_string('email:none', 'local_oriskill'), 'text-muted');
    } else {
        $table = new html_table();
        $table->attributes['class'] = 'table align-middle';
        $table->head = [get_string('email:type', 'local_oriskill'), get_string('subject'), get_string('email:sentat', 'local_oriskill'),
            get_string('email:openedat', 'local_oriskill')];
        $format = get_string('strftimedatetimeshort', 'langconfig');
        foreach ($emails as $sent) {
            $own = (int) $sent->creatorid === (int) $creator->id;
            $actions = '';
            if ($own) {
                $actions = html_writer::link(new moodle_url($url, ['emailid' => $sent->id]),
                    '<i class="fa fa-eye" aria-hidden="true"></i>', ['class' => 'btn btn-sm btn-outline-secondary',
                        'title' => get_string('email:preview', 'local_oriskill'), 'aria-label' => get_string('email:preview', 'local_oriskill')]);
                if ($canchange && $contact->userid) {
                    $actions .= ' ' . $post($hidden('resendmail') . html_writer::empty_tag('input', ['type' => 'hidden',
                        'name' => 'emailid', 'value' => $sent->id]) . html_writer::tag('button', get_string('email:resend', 'local_oriskill'),
                        ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary']), ['class' => 'd-inline']);
                }
            }
            $table->data[] = [
                html_writer::span(get_string('email:kind_' . ($own ? $sent->kind : 'platform'), 'local_oriskill'),
                    'oriskill-badge' . ($sent->kind === 'marketing' && $own ? ' is-warn' : '')),
                html_writer::div(s($sent->subject), '', ['style' => 'white-space: normal; min-width: 14rem']) .
                    ($sent->attachment !== '' ? html_writer::div('<i class="fa fa-paperclip me-1" aria-hidden="true"></i>' .
                        s($sent->attachment), 'small text-muted') : '') .
                    ($sent->status === 'failed' ? html_writer::span(get_string('email:failed', 'local_oriskill'), 'oriskill-badge is-bad') : '') .
                    ($actions ? html_writer::div($actions, 'mt-1') : ''),
                userdate($sent->timecreated, $format),
                $sent->timeopened ? userdate($sent->timeopened, $format) . ($sent->opens > 1
                    ? html_writer::div(get_string('email:opens', 'local_oriskill', (int) $sent->opens), 'small text-muted') : '')
                    : html_writer::span(get_string('email:notopened', 'local_oriskill'), 'text-muted'),
            ];
        }
        $content .= html_writer::div(get_string('email:help', 'local_oriskill'), 'small text-muted mb-2') .
            html_writer::div(html_writer::table($table), 'table-responsive');
    }
} else if ($tab === 'courses') {
    if (!$courses) {
        $content = html_writer::div(get_string('contacts:nocourses', 'local_oriskill'), 'text-muted');
    } else {
        $table = new html_table();
        $table->attributes['class'] = 'table align-middle';
        $table->head = [get_string('course'), get_string('learner:since', 'local_oriskill'), get_string('learner:access', 'local_oriskill'),
            get_string('learner:progress', 'local_oriskill'), ''];
        foreach ($courses as $course) {
            $table->data[] = [
                s(format_string($course->fullname)),
                userdate($course->timestart, $dateformat),
                html_writer::span(get_string($course->active ? 'learner:active' : 'learner:suspended', 'local_oriskill'),
                    'oriskill-badge ' . ($course->active ? 'is-good' : 'is-warn')),
                $course->progress === null ? '–' : round($course->progress) . ' %',
                html_writer::link(new moodle_url('/local/oriskill/learner.php', ['courseid' => $course->id,
                    'userid' => $contact->userid]), get_string('learner:details', 'local_oriskill'),
                    ['class' => 'btn btn-sm btn-outline-primary']),
            ];
        }
        $content = html_writer::div(html_writer::table($table), 'table-responsive');
    }
} else {
    $paid = array_sum(array_map(fn($i) => $i->type === 'credit' ? -$i->total : $i->total, $invoices));
    $count = count(array_filter($invoices, fn($i) => $i->type === 'invoice'));
    $content = html_writer::div(html_writer::div(get_string('contacts:collected', 'local_oriskill'), 'oriskill-stat-label') .
        html_writer::div(pricing_service::format_price((float) $paid, 'EUR'), 'oriskill-course-title') .
        html_writer::div(get_string('contacts:invoicecount', 'local_oriskill', $count), 'small text-muted'), 'mb-3');
    if ($invoices) {
        $table = new html_table();
        $table->attributes['class'] = 'table align-middle';
        $table->head = [get_string('date'), get_string('contacts:document', 'local_oriskill'),
            get_string('contacts:product', 'local_oriskill'), get_string('contacts:amount', 'local_oriskill'), ''];
        foreach ($invoices as $invoice) {
            $data = json_decode($invoice->data, true) ?: [];
            $lines = array_map(fn($l) => $l['label'] ?? $l['name'] ?? '', $data['lines'] ?? []);
            $table->data[] = [
                userdate($invoice->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                s($invoice->number) . ($invoice->type === 'credit' ? ' ' . html_writer::span(get_string('contacts:credit', 'local_oriskill'),
                    'oriskill-badge is-warn') : ''),
                s(implode(', ', array_filter($lines))),
                ($invoice->type === 'credit' ? '-' : '') . pricing_service::format_price((float) $invoice->total, $invoice->currency),
                html_writer::link(new moodle_url('/local/oriskill/invoice.php', ['id' => $invoice->id]),
                    '<i class="fa fa-file-pdf me-1" aria-hidden="true"></i>PDF', ['class' => 'btn btn-sm btn-outline-secondary']),
            ];
        }
        $content .= html_writer::div(html_writer::table($table), 'table-responsive');
    }
}
echo html_writer::div(
    html_writer::div(html_writer::div($identity, 'oriskill-card'), 'col-lg-4 col-xl-3') .
    html_writer::div(html_writer::div(html_writer::tag('ul', $nav, ['class' => 'nav nav-tabs mb-3']) . $content, 'oriskill-card'),
        'col-lg-8 col-xl-9'),
    'row g-3');

echo creator_service::space_end();
echo $OUTPUT->footer();

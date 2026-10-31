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
 * Affiliation of the trainer: campaigns, affiliates and commissions.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\affiliate_service;
use local_oriskill\campaign_service as cs;
use local_oriskill\creator_service;
use local_oriskill\log_service;
use local_oriskill\stats_service;
use local_oriskill\team_service;

$tab = optional_param('tab', 'campaigns', PARAM_ALPHA);
$tab = in_array($tab, ['campaigns', 'affiliates', 'commissions'], true) ? $tab : 'campaigns';
$url = new moodle_url('/local/oriskill/affiliation.php', ['tab' => $tab]);
$creator = creator_service::require_current_creator($url, 'menu:affiliation');
$canchange = team_service::can_change(creator_service::current_role(), '/local/oriskill/affiliation.php');
$filter = optional_param('filter', 'valid', PARAM_ALPHA);
$money = fn(array $amounts) => stats_service::money($amounts);
$date = fn(int $time) => userdate($time, get_string('strftimedatetimeshort', 'langconfig'));

// Every affiliate of the platform, to choose those of a private campaign or of a commission added by hand.
$allaffiliates = [];
foreach ($DB->get_records_sql("SELECT a.id, a.userid, a.code FROM {local_oriskill_affiliates} a WHERE a.status = 'active'")
        as $affiliate) {
    if ($user = core_user::get_user($affiliate->userid)) {
        $allaffiliates[$affiliate->id] = fullname($user) . ' (' . $user->email . ')';
    }
}
core_collator::asort($allaffiliates);

// A campaign: new or edited.
$edit = optional_param('edit', -1, PARAM_INT);
if ($tab === 'campaigns' && $edit >= 0) {
    $forms = array_map('format_string', $DB->get_records_menu('local_oriskill_orderforms', ['creatorid' => $creator->id],
        'title', 'id, title'));
    $editurl = new moodle_url($url, ['edit' => $edit]);
    $mform = new \local_oriskill\form\campaign_form($editurl, ['forms' => $forms, 'affiliates' => $allaffiliates]);
    if ($edit) {
        $campaign = cs::require_own($creator, $edit);
        $mform->set_data((array) $campaign + ['commission' => format_float((float) $campaign->commission, 2, true, true),
            'level2' => (float) $campaign->level2 ? format_float((float) $campaign->level2, 2, true, true) : '',
            'affiliateids' => cs::members($campaign)]);
    } else {
        $mform->set_data(['id' => 0, 'enabled' => 1, 'trackingdays' => 30, 'guaranteedays' => 14]);
    }
    if ($mform->is_cancelled()) {
        redirect($url);
    }
    if (($data = $mform->get_data()) && $canchange) {
        $data->commission = unformat_float($data->commission, true);
        $data->level2 = unformat_float((string) $data->level2, true) ?: 0;
        $data->affiliateids = $data->affiliateids ?? [];
        $saved = cs::save($creator, $data);
        log_service::add($creator, 'campaign_saved', ['name' => $saved->name, 'terms' => cs::describe($saved)]);
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Actions on the commissions.
if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    if (!$canchange) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    try {
        if ($action === 'markpaid') {
            affiliate_service::mark_paid(required_param('commissionid', PARAM_INT), (int) $creator->id);
            $message = get_string('changessaved');
        } else if ($action === 'addcommission') {
            $amount = unformat_float(required_param('amount', PARAM_RAW_TRIMMED), true);
            cs::add_manual($creator, required_param('affiliateid', PARAM_INT), (float) $amount,
                required_param('label', PARAM_TEXT), optional_param('campaignid', 0, PARAM_INT));
            log_service::add($creator, 'commission_added', ['amount' => pricing_service::format_price((float) $amount,
                stats_service::currency())]);
            $message = get_string('campaign:added', 'local_oriskill');
        }
        redirect(new moodle_url($url, ['filter' => $filter]), $message ?? '', null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Commissions of the trainer's sales (valid: pending, due or paid; invalid: cancelled).
$commissions = array_filter(affiliate_service::commissions(['sellerid' => $creator->id]),
    fn($c) => $filter === 'invalid' ? $c->status === 'cancelled' : $c->status !== 'cancelled');
$campaignnames = $DB->get_records_menu(cs::TABLE, ['creatorid' => $creator->id], '', 'id, name');
if ($tab === 'commissions' && optional_param('export', 0, PARAM_BOOL)) {
    $rows = [[get_string('date'), get_string('campaign:campaign', 'local_oriskill'), get_string('campaign:affiliate', 'local_oriskill'),
        get_string('email'), get_string('campaign:level', 'local_oriskill'), get_string('stats:product', 'local_oriskill'),
        get_string('campaign:base', 'local_oriskill'), get_string('commissioncol', 'local_oriskill'),
        get_string('journal:currency', 'local_oriskill'), get_string('status')]];
    foreach ($commissions as $c) {
        $rows[] = [userdate($c->timecreated, '%Y-%m-%d %H:%M'), $campaignnames[$c->campaignid ?? 0] ?? '', fullname($c),
            (string) $DB->get_field('user', 'email', ['id' => $c->affiliateuserid]), (int) ($c->level ?? 1), $c->itemname,
            format_float((float) $c->base, 2, false), format_float((float) $c->amount, 2, false), $c->currency,
            get_string('commission:' . $c->status, 'local_oriskill')];
    }
    \core\dataformat::download_data('commissions-' . userdate(time(), '%Y%m%d'), 'csv', array_shift($rows), $rows);
    exit;
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/affiliation.php', get_string('menu:affiliation', 'local_oriskill'));
$tabs = '';
foreach (['campaigns', 'affiliates', 'commissions'] as $key) {
    $tabs .= html_writer::tag('li', html_writer::link(new moodle_url('/local/oriskill/affiliation.php', ['tab' => $key]),
        get_string('campaign:tab_' . $key, 'local_oriskill'), ['class' => 'nav-link' . ($key === $tab ? ' active' : '')]),
        ['class' => 'nav-item']);
}
echo html_writer::tag('ul', $tabs, ['class' => 'nav nav-tabs mb-3']);

if ($tab === 'campaigns' && $edit >= 0) {
    echo html_writer::div($mform->render(), 'oriskill-card');
} else if ($tab === 'campaigns') {
    echo html_writer::div(
        html_writer::tag('p', get_string('campaign:intro', 'local_oriskill'), ['class' => 'text-muted mb-0']) .
        ($canchange ? html_writer::link(new moodle_url($url, ['edit' => 0]), '<i class="fa fa-plus me-1" aria-hidden="true"></i>' .
            get_string('campaign:new', 'local_oriskill'), ['class' => 'btn btn-primary']) : ''),
        'd-flex flex-wrap justify-content-between align-items-center gap-3 mb-3');
    $campaigns = cs::campaigns($creator);
    if (!$campaigns) {
        echo html_writer::div('<i class="fa fa-bullhorn" aria-hidden="true"></i>' . get_string('campaign:none', 'local_oriskill'),
            'oriskill-card oriskill-empty');
    } else {
        $table = new html_table();
        $table->attributes['class'] = 'table align-middle';
        $table->head = [get_string('campaign:campaign', 'local_oriskill'), get_string('status'),
            get_string('campaign:formid', 'local_oriskill'), get_string('commissioncol', 'local_oriskill'),
            get_string('campaign:clicks', 'local_oriskill'), get_string('courses:sales', 'local_oriskill'),
            get_string('campaign:conversion', 'local_oriskill'), get_string('campaign:totalcommissions', 'local_oriskill')];
        foreach ($campaigns as $campaign) {
            $figures = cs::figures($campaign);
            $table->data[] = [
                html_writer::link(new moodle_url($url, ['edit' => $campaign->id]), s($campaign->name), ['class' => 'fw-semibold']) .
                    html_writer::div(get_string('campaign:visibility_' . $campaign->visibility, 'local_oriskill'), 'small text-muted'),
                html_writer::span(get_string($campaign->enabled ? 'courses:on' : 'courses:off', 'local_oriskill'),
                    'oriskill-badge ' . ($campaign->enabled ? 'is-good' : '')),
                $campaign->formid ? format_string((string) $DB->get_field('local_oriskill_orderforms', 'title',
                    ['id' => $campaign->formid])) : get_string('campaign:allforms', 'local_oriskill'),
                s(cs::describe($campaign)),
                $figures->clicks,
                $figures->sales,
                $figures->clicks ? format_float(100 * $figures->sales / $figures->clicks, 1, true, true) . ' %' : '–',
                $money($figures->amount),
            ];
        }
        echo html_writer::div(html_writer::table($table), 'oriskill-card table-responsive');
    }
    if (!cs::uses_campaigns($creator)) {
        echo html_writer::div(get_string('campaign:legacy', 'local_oriskill', format_float((float) $creator->affiliaterate, 2,
            true, true)), 'small text-muted mt-2');
    }
} else if ($tab === 'affiliates') {
    $affiliates = cs::affiliates($creator);
    if (!$affiliates) {
        echo html_writer::div('<i class="fa fa-user-group" aria-hidden="true"></i>' .
            get_string('campaign:noaffiliatesyet', 'local_oriskill'), 'oriskill-card oriskill-empty');
    } else {
        $table = new html_table();
        $table->attributes['class'] = 'table align-middle';
        $table->head = [get_string('campaign:affiliate', 'local_oriskill'), get_string('status'),
            get_string('stat:clicks', 'local_oriskill'), get_string('menu:commissions', 'local_oriskill'),
            get_string('campaign:totalcommissions', 'local_oriskill'), get_string('campaign:balance', 'local_oriskill'),
            get_string('campaign:since', 'local_oriskill')];
        foreach ($affiliates as $affiliate) {
            $table->data[] = [
                s($affiliate->name) . html_writer::div(s($affiliate->email), 'small text-muted'),
                html_writer::span(get_string($affiliate->status === 'active' ? 'courses:on' : 'courses:off', 'local_oriskill'),
                    'oriskill-badge ' . ($affiliate->status === 'active' ? 'is-good' : '')),
                (int) $affiliate->clicks,
                $affiliate->commissions,
                $money($affiliate->amount),
                html_writer::tag('strong', $money($affiliate->balance)),
                userdate($affiliate->timecreated, get_string('strftimedate', 'langconfig')),
            ];
        }
        echo html_writer::div(html_writer::table($table), 'oriskill-card table-responsive');
    }
} else {
    $filters = '';
    foreach (['valid', 'invalid'] as $key) {
        $filters .= html_writer::link(new moodle_url($url, ['filter' => $key]), get_string('campaign:filter_' . $key, 'local_oriskill'),
            ['class' => 'btn btn-sm ' . ($key === $filter ? 'btn-primary' : 'btn-outline-secondary')]);
    }
    echo html_writer::div(html_writer::div($filters, 'd-flex gap-2') .
        html_writer::link(new moodle_url($url, ['filter' => $filter, 'export' => 1]),
            '<i class="fa fa-download me-1" aria-hidden="true"></i>' . get_string('campaign:export', 'local_oriskill'),
            ['class' => 'btn btn-sm btn-outline-secondary']), 'd-flex flex-wrap justify-content-between gap-2 mb-3');
    if ($commissions) {
        $table = new html_table();
        $table->attributes['class'] = 'table align-middle';
        $table->head = [get_string('date'), get_string('campaign:campaign', 'local_oriskill'),
            get_string('campaign:affiliate', 'local_oriskill'), get_string('stats:product', 'local_oriskill'),
            get_string('commissioncol', 'local_oriskill'), get_string('status'), ''];
        foreach ($commissions as $c) {
            $table->data[] = [
                $date((int) $c->timecreated),
                s($campaignnames[$c->campaignid ?? 0] ?? '–') . ((int) ($c->level ?? 1) === 2
                    ? html_writer::div(get_string('campaign:level2badge', 'local_oriskill'), 'small text-muted') : ''),
                s(fullname($c)),
                s($c->itemname),
                pricing_service::format_price((float) $c->amount, $c->currency),
                get_string('commission:' . $c->status, 'local_oriskill'),
                $c->status === 'approved' && $canchange ? html_writer::tag('form',
                    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'markpaid']) .
                    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'commissionid', 'value' => $c->id]) .
                    html_writer::tag('button', get_string('markpaid', 'local_oriskill'), ['type' => 'submit',
                        'class' => 'btn btn-sm btn-outline-primary']), ['method' => 'post', 'action' => $url->out(false)]) : '',
            ];
        }
        echo html_writer::div(html_writer::table($table), 'oriskill-card table-responsive mb-3');
    } else {
        echo html_writer::div(get_string('nocommissions', 'local_oriskill'), 'oriskill-card text-muted mb-3');
    }
    // A commission added by hand.
    if ($canchange && $allaffiliates) {
        echo html_writer::div(html_writer::tag('h3', get_string('campaign:add', 'local_oriskill'), ['class' => 'h6']) .
            html_writer::tag('form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addcommission']) .
                html_writer::select($allaffiliates, 'affiliateid', '', ['' => get_string('campaign:affiliate', 'local_oriskill')],
                    ['class' => 'form-select', 'required' => 'required', 'style' => 'max-width: 20rem']) .
                html_writer::select($campaignnames, 'campaignid', 0, [0 => get_string('campaign:nocampaign', 'local_oriskill')],
                    ['class' => 'form-select', 'style' => 'max-width: 14rem']) .
                html_writer::empty_tag('input', ['type' => 'text', 'name' => 'label', 'required' => 'required',
                    'class' => 'form-control', 'placeholder' => get_string('campaign:label', 'local_oriskill'),
                    'style' => 'max-width: 16rem']) .
                html_writer::empty_tag('input', ['type' => 'text', 'name' => 'amount', 'required' => 'required',
                    'class' => 'form-control', 'placeholder' => get_string('amountcol', 'local_oriskill'), 'style' => 'max-width: 8rem']) .
                html_writer::tag('button', get_string('add'), ['type' => 'submit', 'class' => 'btn btn-primary']),
                ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-flex flex-wrap gap-2']), 'oriskill-card');
    }
}
echo creator_service::space_end();
echo $OUTPUT->footer();

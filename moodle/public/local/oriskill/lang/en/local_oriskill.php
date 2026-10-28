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
 * English strings.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aboutcreator'] = 'About';
$string['applicationsent'] = 'Your application has been sent. You will receive an email once it has been reviewed.';
$string['applicationstatus:pending'] = 'Your application is being reviewed. You will receive an email once it has been reviewed.';
$string['applicationstatus:rejected'] = 'Your application was not accepted. Contact support for more information.';
$string['applicationstatus:suspended'] = 'Your trainer space is suspended. Contact support for more information.';
$string['amountcol'] = 'Amount';
$string['approve'] = 'Approve';
$string['blocker:inactive'] = 'Your trainer space is not active.';
$string['blocker:nopayment'] = 'Your courses cannot be bought yet: enter your SecureAndPay account in <a href="{$a->payment}">Payments</a>.';
$string['blocker:nosubscription'] = 'Your courses cannot be bought yet: a trainer subscription is required. <a href="{$a->plan}">Subscribe</a>.';
$string['buyercol'] = 'Buyer';
$string['comparecol'] = 'Crossed-out price ({$a})';
$string['creatorplancode'] = 'Trainer subscription plan code';
$string['creatorplancode_desc'] = 'Code of the Modern Commerce subscription plan a trainer must hold to sell. Leave empty to let every approved trainer sell.';
$string['becometrainer'] = 'Become a trainer';
$string['becometrainer_intro'] = 'Sell your courses on Oriskill: you get your own course space and a public showcase page. Tell us about yourself; we review every application.';
$string['createcourse'] = 'Create a course';
$string['creatorcourses'] = 'Courses';
$string['creatorscategory'] = 'Trainers';
$string['editshowcase'] = 'My trainer space';
$string['error:alreadyapplied'] = 'You have already applied.';
$string['error:invalidprices'] = 'These prices were not saved (the price must be positive, the crossed-out price higher): {$a}';
$string['error:mixedsellers'] = 'your cart contains courses from several sellers ({$a}). Each seller is paid separately: buy their courses in separate orders.';
$string['error:sellercannotsell'] = 'the courses of {$a} cannot be bought at the moment.';
$string['error:slugreserved'] = 'This address is reserved. Choose another one.';
$string['error:slugshape'] = 'Use 3 to 30 lowercase letters, digits and single hyphens, for example jane-smith.';
$string['error:slugtaken'] = 'This address is already taken.';
$string['field:bio'] = 'Presentation';
$string['field:displayname'] = 'Name shown to learners';
$string['field:logo'] = 'Logo or photo';
$string['field:motivation'] = 'Your project';
$string['field:sapkey'] = 'SecureAndPay signing key';
$string['field:sapkey_keep'] = 'A key is saved (encrypted). Leave empty to keep it.';
$string['field:sapsiteid'] = 'SecureAndPay site ID (IdSite)';
$string['field:saptestmode'] = 'Test mode';
$string['field:saptestmode_desc'] = 'Use the SecureAndPay test environment (no real payment)';
$string['field:slug'] = 'Showcase address';
$string['field:slug_help'] = 'Your showcase will be at oriskill.com/f/your-address (and later your-address.oriskill.com). Lowercase letters, digits and hyphens only; it cannot be changed afterwards.';
$string['field:tagline'] = 'Tagline';
$string['mail:approved_body'] = 'Hello,

Your trainer space "{$a->name}" is open.

Your public showcase: {$a->url}
Create your first course: {$a->courseurl}

The Oriskill team';
$string['mail:approved_subject'] = 'Your Oriskill trainer space is open';
$string['mail:newapplication_body'] = 'A new trainer application from "{$a->name}" is waiting for review: {$a->url}';
$string['mail:newapplication_subject'] = 'New trainer application: {$a->name}';
$string['managecreators'] = 'Trainers';
$string['managecreators_intro'] = 'Review trainer applications and manage their spaces. Applications are made from {$a}.';
$string['menu:profile'] = 'My showcase';
$string['mycourses'] = 'My courses';
$string['nocourses'] = 'No course on sale yet.';
$string['nocoursesyet'] = 'You have no course yet. <a href="{$a}">Create your first course</a>.';
$string['nocreators'] = 'No trainer application yet.';
$string['nosales'] = 'No sale yet.';
$string['onsale'] = 'On sale';
$string['ordercol'] = 'Order';
$string['oriskill:apply'] = 'Apply for a trainer space';
$string['oriskill:managecreators'] = 'Manage trainer applications and spaces';
$string['paymentsettings'] = 'Payments';
$string['paymentsettings_intro'] = 'Your course sales are paid directly into your own SecureAndPay account.';
$string['paymentsettings_support'] = 'Before your first sale, ask SecureAndPay support (webmaster@secureandpay.com) to declare the site {$a->site} and the notification address {$a->notify} on your account.';
$string['platformseller'] = 'Oriskill';
$string['pluginname'] = 'Oriskill trainers';
$string['pricecol'] = 'Price ({$a})';
$string['pricing'] = 'Prices';
$string['pricing_intro'] = 'Set the price of each course and tick "On sale" to offer it on your showcase and in the catalog.';
$string['privacy:metadata:creators'] = 'Trainer space of a user.';
$string['privacy:metadata:creators:bio'] = 'Public presentation.';
$string['privacy:metadata:creators:displayname'] = 'Name shown to learners.';
$string['privacy:metadata:creators:motivation'] = 'Application message.';
$string['privacy:metadata:creators:sapkey'] = 'SecureAndPay signing key of the trainer, stored encrypted.';
$string['privacy:metadata:creators:sapsiteid'] = 'SecureAndPay site ID of the trainer.';
$string['privacy:metadata:creators:status'] = 'Status of the trainer space.';
$string['privacy:metadata:creators:tagline'] = 'Public tagline.';
$string['privacy:metadata:creators:userid'] = 'Trainer account.';
$string['privacy:metadata:logo'] = 'Logo or photo of the trainer space.';
$string['reactivate'] = 'Reactivate';
$string['reject'] = 'Reject';
$string['sales'] = 'Sales';
$string['sales_intro'] = 'Sales of your courses, paid into your SecureAndPay account.';
$string['salepaid'] = 'Paid';
$string['salerefunded'] = 'Refunded';
$string['salestotal'] = '{$a->count} sale(s), {$a->total} collected';
$string['showcasenotfound'] = 'This trainer page does not exist.';
$string['showcasetitle'] = '{$a} – Online courses';
$string['status:active'] = 'Active';
$string['status:pending'] = 'To review';
$string['status:rejected'] = 'Rejected';
$string['status:suspended'] = 'Suspended';
$string['submitapplication'] = 'Send my application';
$string['suspend'] = 'Suspend';
$string['viewshowcase'] = 'View my showcase';

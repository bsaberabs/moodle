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
$string['ac:apikey'] = 'API key';
$string['ac:apiurl'] = 'API address';
$string['ac:apiurl_help'] = 'In ActiveCampaign: Settings > Developer. Example: https://your-account.api-us1.com';
$string['ac:badurl'] = 'Invalid address. Copy the one in Settings > Developer, for example https://your-account.api-us1.com';
$string['ac:coursetags'] = 'Tag per course';
$string['ac:coursetags_desc'] = 'Also add a tag named after each course bought';
$string['ac:enabled'] = 'Enable';
$string['ac:enabled_desc'] = 'Send the buyers of my courses to ActiveCampaign';
$string['ac:intro'] = 'After each payment, the buyer is added (or updated) in your ActiveCampaign account, subscribed to the chosen list and tagged. You can start your automations from there. Calls run in the background and are retried if ActiveCampaign does not answer.';
$string['ac:lasterror'] = '✗ Last call failed ({$a->time}, {$a->email}): {$a->error}. It will be retried automatically.';
$string['ac:lastok'] = '✓ Last call succeeded: {$a->email} ({$a->time})';
$string['ac:list'] = 'List';
$string['ac:list_help'] = 'Number of the list buyers are subscribed to. Click "Test" to choose it by name.';
$string['ac:nolist'] = 'None (tags only)';
$string['ac:notset'] = 'account not set up';
$string['ac:tags'] = 'Tags on every purchase';
$string['ac:tags_help'] = 'Comma-separated, for example: customer, course. Tags that do not exist yet are created.';
$string['ac:test'] = 'Save and test';
$string['ac:testfailed'] = 'Could not connect to ActiveCampaign: {$a}';
$string['ac:testok'] = 'Connected: {$a} list(s) found, choose yours below.';
$string['ac:unpaidtag'] = 'Tag on unpaid instalment';
$string['ac:unpaidtag_help'] = 'Added when an instalment of a payment in instalments is refused (leave empty to send nothing).';
$string['appearance'] = 'Showcase appearance';
$string['applicationsent'] = 'Your application has been sent. You will receive an email once it has been reviewed.';
$string['applicationstatus:pending'] = 'Your application is being reviewed. You will receive an email once it has been reviewed.';
$string['applicationstatus:rejected'] = 'Your application was not accepted. Contact support for more information.';
$string['applicationstatus:suspended'] = 'Your trainer space is suspended. Contact support for more information.';
$string['amountcol'] = 'Amount';
$string['approve'] = 'Approve';
$string['billing:incomplete'] = 'Complete these details: your invoices must state them (name or company name, address, registration number).';
$string['billing:intro'] = 'These details appear on the invoices and credit notes issued from now on. Documents already issued do not change.';
$string['billing:legaladdress'] = 'Address';
$string['billing:legalname'] = 'Name or company name';
$string['billing:legalname_help'] = 'The seller stated on your invoices: your company, or your own name as a sole trader. Your showcase name is shown too.';
$string['billing:notes'] = 'Additional mentions';
$string['billing:notes_help'] = 'Printed at the bottom of each invoice: training provider registration number, payment terms, etc.';
$string['billing:novat'] = 'Not subject to VAT';
$string['billing:siret'] = 'Registration number (SIRET)';
$string['billing:title'] = 'Billing details';
$string['billing:vatnumber'] = 'VAT number';
$string['billing:vatnumberrequired'] = 'Enter your VAT number.';
$string['billing:vatrate'] = 'VAT';
$string['billing:vatrate_help'] = 'Your prices include VAT. If you are subject to VAT, the invoice shows the amount excluding VAT and the VAT at your rate; otherwise it states "VAT not applicable, art. 293 B of the CGI".';
$string['billing:vatrate_value'] = 'Subject to VAT, rate {$a} %';
$string['blocker:inactive'] = 'Your trainer space is not active.';
$string['blocker:nopayment'] = 'Your courses cannot be bought yet: set up at least one payment method (SecureAndPay, Stripe or PayPal) in <a href="{$a->payment}">Payments</a>.';
$string['blocker:nosubscription'] = 'Your courses cannot be bought yet: a trainer subscription is required. <a href="{$a->plan}">Subscribe</a>.';
$string['buyercol'] = 'Buyer';
$string['capture:accessdays'] = 'Access length (days)';
$string['capture:accessdays_help'] = 'Days of access to the free courses; 0 = no limit.';
$string['capture:buttondefault'] = 'Get free access';
$string['capture:consent'] = 'I agree to receive e-mails from {$a} (unsubscribe at any time).';
$string['capture:consentcol'] = 'Agrees to e-mails';
$string['capture:courses'] = 'Free courses';
$string['capture:doneexisting'] = 'The courses are added to your account: log in to reach them.';
$string['capture:donenew'] = 'Your account is created: your login details were just sent to you by e-mail.';
$string['capture:expired'] = 'The page expired: send the form again.';
$string['capture:export'] = 'Sign-ups (CSV)';
$string['capture:intro'] = 'Pages offering one or more of your courses in exchange for an e-mail address: the visitor gets an account and access, then you can send them on to one of your order forms. Sign-ups who agree are sent to ActiveCampaign and your webhooks.';
$string['capture:invalid'] = 'Enter your first name, last name and a valid e-mail address.';
$string['capture:legal'] = 'Your data is used to create your access; it is not sold.';
$string['capture:new'] = 'New capture page';
$string['capture:none'] = 'No capture page yet.';
$string['capture:noredirect'] = 'None';
$string['capture:open'] = 'Open the page';
$string['capture:redirect'] = 'Order form offered next';
$string['capture:redirect_help'] = 'After sign-up, a button leads to this order form (for example the full course).';
$string['capture:signups'] = 'Sign-ups';
$string['capture:slug'] = 'Page address';
$string['capture:slug_help'] = 'End of the page address: on your domain, your-domain.com/p/address. Lowercase letters, digits and hyphens.';
$string['capture:thanks'] = 'You\'re in!';
$string['comparecol'] = 'Crossed-out price ({$a})';
$string['coupon:active'] = 'Active';
$string['coupon:allforms'] = 'All my order forms';
$string['coupon:amount'] = 'Amount (€)';
$string['coupon:applied'] = 'Code {$a->code} applied: -{$a->discount}.';
$string['coupon:apply'] = 'Apply';
$string['coupon:badcode'] = '3 to 40 characters: letters, digits, hyphens.';
$string['coupon:baddates'] = 'The end must be after the start.';
$string['coupon:badvalue'] = 'Enter a positive discount (under 100 % for a percentage).';
$string['coupon:code'] = 'Code';
$string['coupon:code_help'] = 'What the buyer types, for example BACK2026. Upper and lower case are the same.';
$string['coupon:ended'] = 'Ended';
$string['coupon:expired'] = 'This promo code is not valid on this date.';
$string['coupon:field'] = 'Promo code';
$string['coupon:form'] = 'Valid on';
$string['coupon:inactive'] = 'Disabled';
$string['coupon:instalments'] = 'Payment in instalments';
$string['coupon:instalments_desc'] = 'Also valid when paying in instalments (the instalments are recalculated)';
$string['coupon:live'] = 'Active';
$string['coupon:maxuses'] = 'Maximum uses';
$string['coupon:maxuses_help'] = 'Paid orders with this code at most; 0 = no limit.';
$string['coupon:new'] = 'New promo code';
$string['coupon:notinstalments'] = 'This promo code is not valid with payment in instalments.';
$string['coupon:percent'] = 'Percentage (%)';
$string['coupon:remove'] = 'Remove';
$string['coupon:taken'] = 'You already have the same code.';
$string['coupon:type'] = 'Discount type';
$string['coupon:unknown'] = 'This promo code does not exist for this offer.';
$string['coupon:usedup'] = 'This promo code has already been used as many times as allowed.';
$string['coupon:uses'] = 'Uses';
$string['coupon:validfrom'] = 'Valid from';
$string['coupon:validity'] = 'Validity';
$string['coupon:validuntil'] = 'Valid until';
$string['coupon:value'] = 'Discount';
$string['coupons:intro'] = 'Codes your buyers type at the order step of your order forms: a percentage or an amount off, on all your forms or one, with a number of uses and dates if needed. The discount shows on the invoice.';
$string['coupons:none'] = 'No promo code yet.';
$string['creatorplancode'] = 'Trainer subscription plan code';
$string['creatorplancode_desc'] = 'Code of the Modern Commerce subscription plan a trainer must hold to sell. Leave empty to let every approved trainer sell.';
$string['becometrainer'] = 'Become a trainer';
$string['becometrainer_intro'] = 'Sell your courses on Oriskill: you get your own course space and a public showcase page. Tell us about yourself; we review every application.';
$string['createcourse'] = 'Create a course';
$string['creatorcourses'] = 'Courses';
$string['creatorscategory'] = 'Trainers';
$string['credit:amount'] = 'Amount incl. VAT';
$string['credit:confirm'] = 'Issue the credit note';
$string['credit:confirmtext'] = 'A credit note cannot be deleted. Issue it?';
$string['credit:issue'] = 'Credit note…';
$string['credit:issued'] = 'Credit note {$a} issued and sent to the buyer.';
$string['credit:line'] = 'Credit note on invoice {$a}';
$string['credit:mail_body'] = 'Hello,

{$a->trainer} issued credit note {$a->number} of {$a->amount} on invoice {$a->invoice}.

Download it here: {$a->url}';
$string['credit:mail_subject'] = 'Credit note {$a->number} – {$a->trainer}';
$string['credit:ofinvoice'] = 'On invoice {$a->number} of {$a->date}';
$string['credit:ofinvoiceshort'] = 'on {$a}';
$string['credit:reason'] = 'Reason (optional)';
$string['credit:refunded'] = 'order refunded';
$string['credit:title'] = 'Credit note';
$string['domainnotready'] = 'The domain {$a->domain} is not linked to Oriskill yet. At your registrar, create an A record for {$a->domain} and for www.{$a->domain} pointing to {$a->ip}. It can take a few hours to apply.';
$string['domainready'] = 'The domain {$a->domain} is linked to Oriskill: your showcase is available there.';
$string['editshowcase'] = 'My trainer space';
$string['error:activecampaign'] = 'ActiveCampaign error: {$a}';
$string['error:alreadyapplied'] = 'You have already applied.';
$string['error:alreadyhascourses'] = 'You already have access to all the courses of this offer.';
$string['error:alreadymember'] = 'This person is already in the team (or already invited).';
$string['error:capturelimit'] = 'Too many sign-ups from this browser. Try again later.';
$string['error:color'] = 'Enter a colour as #rrggbb, for example #7c3aed.';
$string['error:coupon'] = 'This promo code is not valid.';
$string['error:creditamount'] = 'Invalid credit note amount: it must be positive and at most what the invoice still shows.';
$string['error:domainshape'] = 'This domain name is not valid. Example: my-academy.com';
$string['error:domainsite'] = 'Choose your own domain name, not an Oriskill address.';
$string['error:domaintaken'] = 'This domain name is already linked to another showcase.';
$string['error:formslug'] = 'Use 3 to 60 lowercase letters, digits and single hyphens, for example excel-course.';
$string['error:formslugtaken'] = 'You already have an order form at this address.';
$string['error:formunavailable'] = 'This order form is not available at the moment.';
$string['error:instalments'] = 'Choose one of the offered numbers of instalments and a positive total price.';
$string['error:invalidprices'] = 'These prices were not saved (the price must be positive, the crossed-out price higher): {$a}';
$string['error:mixedsellers'] = 'your cart contains courses from several sellers ({$a}). Each seller is paid separately: buy their courses in separate orders.';
$string['error:noinstalments'] = 'This course cannot be paid in instalments at the moment.';
$string['error:nooffer'] = 'Add at least one complete offer (name, courses, price) before publishing.';
$string['error:notallowed'] = 'Your role in this team does not give access to this page.';
$string['error:notsubscription'] = 'This subscription is already cancelled.';
$string['error:offercourses'] = 'Choose at least one course.';
$string['error:offerprice'] = 'Enter a positive price.';
$string['error:sellercannotsell'] = 'the courses of {$a} cannot be bought at the moment.';
$string['error:slugreserved'] = 'This address is reserved. Choose another one.';
$string['error:slugshape'] = 'Use 3 to 30 lowercase letters, digits and single hyphens, for example jane-smith.';
$string['error:slugtaken'] = 'This address is already taken.';
$string['error:webhook'] = 'Enter an https address and at least one event.';
$string['error:webhookdelivery'] = 'The webhook address answered {$a}.';
$string['field:accentcolor'] = 'Accent colour';
$string['field:accentcolor_help'] = 'Used for call-to-action buttons and heading underlines.';
$string['field:banner'] = 'Banner';
$string['field:banner_help'] = 'Wide image shown at the top of your showcase, behind your name (1600 × 500 pixels recommended). Without a banner, the top of the showcase uses your main colour.';
$string['field:bio'] = 'Presentation';
$string['field:customdomain'] = 'Custom domain name';
$string['field:customdomain_help'] = 'Optional. Your showcase will also be available at this address (for example my-academy.com), as well as at oriskill.com/your-address. At your registrar, point the domain and www to the IP address shown below this field once saved. The HTTPS certificate is created automatically.';
$string['field:displayname'] = 'Name shown to learners';
$string['field:fontstyle'] = 'Typeface';
$string['field:logo'] = 'Logo or photo';
$string['field:motivation'] = 'Your project';
$string['field:primarycolor'] = 'Main colour';
$string['field:primarycolor_help'] = 'Your brand colour: top of the showcase, log in button, prices. The colour of the text on it (white or black) is chosen automatically to stay readable.';
$string['field:sapkey'] = 'SecureAndPay signing key';
$string['field:sapkey_keep'] = 'A key is saved (encrypted). Leave empty to keep it.';
$string['field:sapsiteid'] = 'SecureAndPay site ID (IdSite)';
$string['field:saptestmode'] = 'Test mode';
$string['field:saptestmode_desc'] = 'Use the SecureAndPay test environment (no real payment)';
$string['field:slug'] = 'Showcase address';
$string['field:slug_help'] = 'Your showcase will be at oriskill.com/your-address. Lowercase letters, digits and hyphens only; it cannot be changed afterwards.';
$string['field:tagline'] = 'Tagline';
$string['font:classic'] = 'Classic (Helvetica)';
$string['font:elegant'] = 'Elegant (serif)';
$string['font:modern'] = 'Modern';
$string['group:instalments'] = 'Paid in {$a} instalments';
$string['group:paidatonce'] = 'Paid at once';
$string['group:subscription'] = 'Subscribers';
$string['instalmentmail:cancelled_learner'] = 'Hello {$a->learner},

Your subscription to "{$a->course}" is cancelled: nothing more will be charged. Your access stays open until {$a->deadline}.';
$string['instalmentmail:cancelled_subject'] = 'Subscription cancelled: {$a->course}';
$string['instalmentmail:cancelled_trainer'] = 'The subscription of {$a->learner} to "{$a->course}" is cancelled ({$a->paid} months paid). Their access stays open until {$a->deadline}.

Learner page: {$a->url}';
$string['instalmentmail:cancelledmanual_learner'] = 'Hello {$a->learner},

Your request to cancel the subscription to "{$a->course}" is recorded. Your access stays open until {$a->deadline}.';
$string['instalmentmail:cancelledmanual_subject'] = 'Cancellation to confirm at SecureAndPay: {$a->course}';
$string['instalmentmail:cancelledmanual_trainer'] = 'The subscription of {$a->learner} to "{$a->course}" was cancelled on Oriskill, but SecureAndPay did not confirm that charges stopped. Stop it in your SecureAndPay account to avoid another charge.

Learner page: {$a->url}';
$string['instalmentmail:failed_learner'] = 'Hello {$a->learner},

The instalment for your course "{$a->course}" could not be charged ({$a->paid} of {$a->count} paid).

Check your card or contact {$a->trainer}. If the payment is not settled by {$a->deadline}, your access to the course will be suspended; it is restored as soon as a charge succeeds.';
$string['instalmentmail:failed_subject'] = 'Unpaid instalment: {$a->course}';
$string['instalmentmail:failed_trainer'] = 'The instalment of {$a->learner} for "{$a->course}" was refused ({$a->paid} of {$a->count} paid). Unless it is settled by {$a->deadline}, their access will be suspended automatically.

Learner page: {$a->url}';
$string['instalmentmail:restored_learner'] = 'Hello {$a->learner},

We received your payment: your access to the course "{$a->course}" is restored.';
$string['instalmentmail:restored_subject'] = 'Access restored: {$a->course}';
$string['instalmentmail:restored_trainer'] = 'The payment of {$a->learner} for "{$a->course}" was received: their access is restored.

Learner page: {$a->url}';
$string['instalmentmail:stopped_learner'] = 'Hello {$a->learner},

The instalments for your course "{$a->course}" were stopped with {$a->paid} of {$a->count} paid. Contact {$a->trainer} to pay the balance; unless it is settled by {$a->deadline}, your access will be suspended.';
$string['instalmentmail:stopped_subject'] = 'Payment interrupted: {$a->course}';
$string['instalmentmail:stopped_trainer'] = 'The instalments of {$a->learner} for "{$a->course}" were stopped at SecureAndPay ({$a->paid} of {$a->count} paid). Unless it is settled by {$a->deadline}, their access will be suspended automatically.

Learner page: {$a->url}';
$string['instalmentmail:suspended_learner'] = 'Hello {$a->learner},

As a payment is missing, your access to the course "{$a->course}" is suspended. It is restored once the payment is received. Contact {$a->trainer} if needed.';
$string['instalmentmail:suspended_subject'] = 'Access suspended: {$a->course}';
$string['instalmentmail:suspended_trainer'] = 'The access of {$a->learner} to "{$a->course}" was suspended: an instalment has been unpaid for {$a->days} days. You can reactivate it yourself from their page: {$a->url}';
$string['instalments:available'] = 'This course can be paid in {$a->count} instalments: {$a->count} × {$a->displayamount}';
$string['instalments:buytitle'] = 'Pay {$a} in instalments';
$string['instalments:choose'] = 'Pay in instalments';
$string['instalments:countcol'] = 'In instalments';
$string['instalments:howitworks'] = 'You pay the first instalment now and get access to the course right away. The {$a->count} instalments of {$a->displayamount} are then charged automatically every month to the same card, then the charges stop.';
$string['instalments:intro'] = 'Payment in instalments: choose the number of monthly instalments and the total price paid that way (the same as the full price or a little more). SecureAndPay charges the learner\'s card every month. If an instalment is unpaid, the learner and you are told, and their access is suspended after {$a->days} days without payment.';
$string['instalments:needsap'] = 'To offer payment in instalments, <a href="{$a->payment}">set up your SecureAndPay account</a>: it charges the instalments every month.';
$string['instalments:none'] = 'No';
$string['instalments:orshort'] = 'or {$a->count} × {$a->displayamount}';
$string['instalments:pay'] = 'Pay the 1st instalment ({$a->displayamount})';
$string['instalments:payatonce'] = 'Pay at once';
$string['instalments:preview'] = '{$a->count} × {$a->amount}';
$string['instalments:summary'] = '{$a->count} monthly instalments of {$a->displayamount}, {$a->displaytotal} in all.';
$string['instalments:times'] = '{$a} times';
$string['instalments:totalcol'] = 'Total price in instalments ({$a})';
$string['instalmentstatus:active'] = '{$a->paid}/{$a->count} instalments paid';
$string['instalmentstatus:completed'] = 'Paid in {$a->count} instalments';
$string['instalmentstatus:late'] = 'Unpaid instalment ({$a->paid}/{$a->count})';
$string['instalmentstatus:stopped'] = 'Instalments stopped ({$a->paid}/{$a->count})';
$string['instalmentstatus:suspended'] = 'Suspended for non-payment ({$a->paid}/{$a->count})';
$string['invoice:billedto'] = 'Billed to';
$string['invoice:date'] = 'Date';
$string['invoice:description'] = 'Description';
$string['invoice:discount'] = 'Discount';
$string['invoice:instalments'] = 'Payable in {$a->count} monthly instalments of {$a->amount}.';
$string['invoice:linetotal'] = 'Amount incl. VAT';
$string['invoice:novat'] = 'VAT not applicable, art. 293 B of the CGI.';
$string['invoice:number'] = 'No.';
$string['invoice:order'] = 'Order';
$string['invoice:paid'] = 'Paid';
$string['invoice:quantity'] = 'Qty';
$string['invoice:siret'] = 'SIRET: {$a}';
$string['invoice:subscription'] = 'Monthly subscription of {$a->amount} – month {$a->period}. Can be cancelled at any time.';
$string['invoice:title'] = 'Invoice';
$string['invoice:total'] = 'Total';
$string['invoice:totalexcl'] = 'Total excl. VAT';
$string['invoice:totalincl'] = 'Total incl. VAT';
$string['invoice:unitprice'] = 'Unit price incl. VAT';
$string['invoice:vat'] = 'VAT {$a} %';
$string['invoice:vatnumber'] = 'VAT number: {$a}';
$string['invoices:client'] = 'Client';
$string['invoices:documents'] = 'Invoices and credit notes';
$string['invoices:intro'] = 'Your sales are paid to your own account: invoices are issued in your name, numbered in your own continuous sequence (F-000001…). A credit note (AV-000001…) cancels all or part of an invoice, for example after a refund; it is issued automatically when the store refunds an order.';
$string['invoices:none'] = 'No invoice yet: one is issued for each paid sale.';
$string['learner:payment'] = 'Payment';
$string['log:access_addgroup'] = 'Group "{$a->name}" created in "{$a->course}"';
$string['log:access_deletegroup'] = 'Group deleted in "{$a->course}"';
$string['log:access_rules'] = 'Module release rules changed in "{$a->course}"';
$string['log:activecampaign_saved'] = 'ActiveCampaign settings changed (enabled: {$a->state})';
$string['log:affiliaterate_set'] = 'Affiliate commission set to {$a->rate} %';
$string['log:automatic'] = 'Automatic';
$string['log:billing_saved'] = 'Billing details changed';
$string['log:capturepage_saved'] = 'Capture page "{$a->title}" saved ({$a->status})';
$string['log:coupon_saved'] = 'Promo code {$a->code} saved';
$string['log:credit_issued'] = 'Credit note {$a->number} issued on invoice {$a->invoice} ({$a->user})';
$string['log:empty'] = 'Nothing recorded yet.';
$string['log:instalments_off'] = 'Payment in instalments removed for "{$a->course}"';
$string['log:instalments_set'] = '"{$a->course}" offered in {$a->count} instalments ({$a->total} in all)';
$string['log:intro'] = 'Changes made in the trainer space, by you or your team, and Oriskill\'s automatic actions.';
$string['log:learner_offer'] = 'Access offered to {$a->user} for "{$a->course}"';
$string['log:learner_paidrestored'] = 'Access of {$a->user} to "{$a->course}" restored after payment';
$string['log:learner_reactivate'] = 'Access of {$a->user} to "{$a->course}" reactivated';
$string['log:learner_remove'] = 'Access of {$a->user} to "{$a->course}" removed';
$string['log:learner_setend'] = 'End of access of {$a->user} to "{$a->course}": {$a->date}';
$string['log:learner_setgroup'] = 'Group of {$a->user} changed in "{$a->course}"';
$string['log:learner_suspend'] = 'Access of {$a->user} to "{$a->course}" suspended';
$string['log:learner_unpaidsuspended'] = 'Access of {$a->user} to "{$a->course}" suspended for non-payment';
$string['log:member_invited'] = '{$a->email} invited as {$a->role}';
$string['log:member_joined'] = '{$a->user} joined the team as {$a->role}';
$string['log:member_removed'] = '{$a->email} removed from the team';
$string['log:member_role'] = 'Role of {$a->email}: {$a->from} → {$a->role}';
$string['log:orderform_saved'] = 'Order form "{$a->title}" saved ({$a->status})';
$string['log:payment_saved'] = 'Payment account {$a->gateway} changed';
$string['log:price_set'] = 'Price of "{$a->course}": {$a->price} (on sale: {$a->onsale})';
$string['log:refund_done'] = 'Refund of {$a->amount} confirmed by SecureAndPay ({$a->user}): credit note {$a->number}';
$string['log:refund_requested'] = 'Refund of {$a->amount} asked of SecureAndPay (invoice {$a->invoice}, {$a->user})';
$string['log:showcase_saved'] = 'Showcase changed';
$string['log:subscription_cancelled'] = 'Subscription of {$a->user} to "{$a->course}" cancelled';
$string['log:webhook_added'] = 'Webhook added: {$a->url}';
$string['log:webhook_removed'] = 'Webhook removed: {$a->url}';
$string['log:what'] = 'Action';
$string['log:who'] = 'By';
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
$string['menu:capturepages'] = 'Capture pages';
$string['menu:coupons'] = 'Promo codes';
$string['menu:integrations'] = 'Integrations';
$string['menu:invoices'] = 'Invoices';
$string['menu:log'] = 'Log';
$string['menu:orderforms'] = 'Order forms';
$string['menu:profile'] = 'My showcase';
$string['menu:schedules'] = 'Payment schedules';
$string['menu:team'] = 'Team';
$string['mycourses'] = 'My courses';
$string['nocourses'] = 'No course on sale yet.';
$string['nocoursesyet'] = 'You have no course yet. <a href="{$a}">Create your first course</a>.';
$string['nocreators'] = 'No trainer application yet.';
$string['nosales'] = 'No sale yet.';
$string['onsale'] = 'On sale';
$string['ordercol'] = 'Order';
$string['orderform:acceptterms'] = 'I accept the {$a}';
$string['orderform:accessopen'] = 'Your courses are open:';
$string['orderform:addoffer'] = 'Add an offer';
$string['orderform:address'] = 'Address';
$string['orderform:bump'] = 'Extra at the order step';
$string['orderform:bump_intro'] = 'Offered as a box to tick at the order step, on top of the chosen offer (for example "Add the Pro module"). Its price is added to the order, also when paying in instalments. Leave the name empty to offer nothing.';
$string['orderform:buttondefault'] = 'Order now';
$string['orderform:buttonlabel'] = 'Button text';
$string['orderform:cannotsell'] = 'To take the orders of your order forms, <a href="{$a}">set up a payment method</a> (SecureAndPay, Stripe or PayPal). Until then, offers are shown without an order button.';
$string['orderform:choose'] = 'Choose your plan';
$string['orderform:description'] = 'Presentation';
$string['orderform:draft'] = 'Draft';
$string['orderform:extraadd'] = 'Yes, add {$a->label} for {$a->price}';
$string['orderform:extraincluded'] = 'including {$a}';
$string['orderform:extralabel'] = 'Offer name';
$string['orderform:extraprice'] = 'Price incl. VAT';
$string['orderform:extratext'] = 'Presentation text';
$string['orderform:headline'] = 'Headline';
$string['orderform:image'] = 'Header image';
$string['orderform:loginfirst'] = 'To order, log in or create your account: you will reach your courses with it.';
$string['orderform:new'] = 'New order form';
$string['orderform:noinstalments'] = 'Payment in instalments of your offers is only shown with a SecureAndPay account, the only one that charges the instalments: <a href="{$a}">set it up</a> to offer it.';
$string['orderform:notpublished'] = 'Not published';
$string['orderform:offer'] = 'Offer';
$string['orderform:offeractags'] = 'ActiveCampaign tags of the offer';
$string['orderform:offeractags_help'] = 'Added to the buyers of this offer, besides the tags set in Integrations. Comma-separated.';
$string['orderform:offercourses'] = 'Courses opened after payment';
$string['orderform:offerdetails'] = 'What is included (one point per line)';
$string['orderform:offerhighlight'] = 'Highlight ("Recommended")';
$string['orderform:offerinstaltotal'] = 'Total price in instalments (empty = same price)';
$string['orderform:offerlabel'] = 'Offer name';
$string['orderform:offerprice'] = 'Price paid at once, incl. VAT';
$string['orderform:offers'] = 'Offers';
$string['orderform:offers_intro'] = 'Each offer opens one or more of your courses on payment. It can also be paid in instalments (monthly charges by SecureAndPay). Leave the name empty to remove an offer.';
$string['orderform:offersubprice'] = 'Monthly subscription incl. VAT (empty = not offered)';
$string['orderform:offersubprice_help'] = 'Also offers this offer as a subscription: SecureAndPay charges the subscriber\'s card every month until they cancel; access stays open until the end of the month paid. Requires a SecureAndPay account.';
$string['orderform:orders'] = 'Paid orders';
$string['orderform:page'] = 'Page';
$string['orderform:pay'] = 'Pay {$a}';
$string['orderform:published'] = 'Published';
$string['orderform:recap'] = 'Your order with {$a}:';
$string['orderform:recommended'] = 'Recommended';
$string['orderform:refresh'] = 'Refresh';
$string['orderform:slug'] = 'Form address';
$string['orderform:slug_help'] = 'End of the order form address: on your domain, your-domain.com/bdc/address. Lowercase letters, digits and hyphens.';
$string['orderform:termsmissing'] = 'Accept the terms and conditions to order.';
$string['orderform:termsrequired'] = 'Have the terms and conditions accepted before payment';
$string['orderform:thanks'] = 'Thank you for your order!';
$string['orderform:thankyou'] = 'Thank-you message';
$string['orderform:thankyou_help'] = 'Shown to the buyer after payment, with the links to their courses.';
$string['orderform:title'] = 'Title';
$string['orderform:upsell'] = 'Offer after payment';
$string['orderform:upsell_intro'] = 'Shown on the thank-you page, right after payment. With SecureAndPay, the buyer\'s card (or Apple Pay, Google Pay, Samsung Pay) is already saved: they only confirm. Leave the name empty to offer nothing.';
$string['orderform:upsellcard'] = 'Your payment method is already saved: you only need to confirm.';
$string['orderform:upsellno'] = 'No thanks, go to my courses';
$string['orderform:upselltitle'] = 'An offer for new learners only';
$string['orderform:upsellyes'] = 'Yes, add it for {$a}';
$string['orderform:waiting'] = 'Your payment is being confirmed by SecureAndPay. Your courses open as soon as it is confirmed (usually within seconds); you will also receive an e-mail.';
$string['orderforms:intro'] = 'Your sales pages: one or more offers, paid at once or in instalments, opening your courses on payment. Share their address or attach them to your domain.';
$string['orderforms:none'] = 'No order form yet.';
$string['oriskill:apply'] = 'Apply for a trainer space';
$string['oriskill:managecreators'] = 'Manage trainer applications and spaces';
$string['paymentsettings'] = 'Payments';
$string['paymentsettings_intro'] = 'Your course sales are paid directly into your own accounts. Turn on the payment methods you want to offer your buyers: at least one is needed to sell.';
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
$string['refund:action'] = 'Refund…';
$string['refund:confirm'] = 'Ask for the refund';
$string['refund:confirmtext'] = 'Ask SecureAndPay for this refund? It is paid back to the buyer\'s card once validated.';
$string['refund:help'] = 'The amount is paid back to the buyer\'s card once SecureAndPay validates it; the credit note is issued automatically then.';
$string['refund:requested'] = 'Refund request sent to SecureAndPay. The credit note is issued as soon as it confirms the refund.';
$string['refund:status_refunded'] = 'Refunded: {$a}';
$string['refund:status_refused'] = 'Refund refused: {$a}';
$string['refund:status_requested'] = 'Refund asked: {$a} (waiting for SecureAndPay)';
$string['reject'] = 'Reject';
$string['role:admin'] = 'Administrator';
$string['role:admin_desc'] = 'the whole trainer space except the payment accounts: courses, prices, learners, sales, invoices, showcase, team and log.';
$string['role:owner'] = 'Trainer (owner)';
$string['role:support'] = 'Support / sales';
$string['role:support_desc'] = 'learners (viewing and managing access), sales and invoices; does not change courses or prices.';
$string['role:teaching'] = 'Teaching';
$string['role:teaching_desc'] = 'courses (editing), videos, learners and module release.';
$string['sales'] = 'Sales';
$string['sales_intro'] = 'Sales of your courses, paid into your SecureAndPay account.';
$string['salepaid'] = 'Paid';
$string['salerefunded'] = 'Refunded';
$string['salestotal'] = '{$a->count} sale(s), {$a->total} collected';
$string['schedules:all'] = 'All';
$string['schedules:amounts'] = 'Collected';
$string['schedules:collected'] = 'collected in instalments';
$string['schedules:collectedamount'] = '{$a} collected';
$string['schedules:estimate'] = 'Next instalment estimated one month after the previous one, from the first payment. SecureAndPay charges automatically; each payment or refusal updates this page.';
$string['schedules:intro'] = 'Purchases paid in instalments and monthly subscriptions: where each schedule stands, what was collected and what is left. A refused instalment makes the learner late; without payment after {$a} days their access is suspended, then restored on the next payment.';
$string['schedules:latesince'] = 'unpaid since {$a}';
$string['schedules:next'] = 'Next instalment';
$string['schedules:none'] = 'No payment schedule for this filter.';
$string['schedules:paidof'] = '{$a->paid} / {$a->count} × {$a->amount}';
$string['schedules:progress'] = 'Instalments';
$string['schedules:remainingamount'] = '{$a} left';
$string['schedules:running'] = 'running schedules';
$string['schedules:status_active'] = 'Up to date';
$string['schedules:status_cancelled'] = 'Cancelled';
$string['schedules:status_completed'] = 'Completed';
$string['schedules:status_late'] = 'Late';
$string['schedules:status_stopped'] = 'Stopped';
$string['schedules:status_suspended'] = 'Suspended';
$string['schedules:tocollect'] = 'left to collect';
$string['schedules:unpaid'] = 'unpaid';
$string['schedules:what'] = 'Course or offer';
$string['showcase:legal'] = 'Legal information';
$string['showcase:login'] = 'Log in';
$string['showcase:mylearning'] = 'My courses';
$string['showcase:privacy'] = 'Privacy';
$string['showcase:seecourses'] = 'See the courses';
$string['showcase:terms'] = 'Terms and conditions';
$string['showcasenotfound'] = 'This trainer page does not exist.';
$string['showcasetitle'] = '{$a} – Online courses';
$string['stats:checkouts'] = 'Order steps';
$string['stats:conversion'] = 'Conversion';
$string['stats:days'] = '{$a} days';
$string['stats:extras'] = 'Extra taken in {$a->bumps} order(s) out of {$a->orders}; offer after payment accepted {$a->upsells} time(s) ({$a->upsellrevenue}, included in the revenue).';
$string['stats:instalments'] = 'of which in instalments';
$string['stats:note'] = 'Visitors counted once a day; robots, you and your team are not counted. Conversion = paid orders per 100 visitors. Revenue incl. VAT, refunds deducted.';
$string['stats:orders'] = 'Paid orders';
$string['stats:perday'] = 'Day by day';
$string['stats:peroffer'] = 'Per offer';
$string['stats:revenue'] = 'Revenue';
$string['stats:title'] = 'Statistics';
$string['stats:views'] = 'Visitors';
$string['stats:views30'] = 'Visitors (30 d)';
$string['status:active'] = 'Active';
$string['status:pending'] = 'To review';
$string['status:rejected'] = 'Rejected';
$string['status:suspended'] = 'Suspended';
$string['submitapplication'] = 'Send my application';
$string['subscription:cancel'] = 'Cancel';
$string['subscription:cancelconfirm'] = 'Cancel this subscription? Nothing more will be charged; your access stays open until {$a}.';
$string['subscription:cancelconfirmtrainer'] = 'Cancel this learner\'s subscription? Charges stop; their access stays open until the end of the month paid.';
$string['subscription:cancelled'] = 'Subscription cancelled. Your access stays open until {$a}.';
$string['subscription:cancelledmanual'] = 'Cancellation recorded (access until {$a}). SecureAndPay did not confirm that charges stopped: the trainer is told.';
$string['subscription:cancelledtrainer'] = 'Subscription cancelled: access until {$a}.';
$string['subscription:howitworks'] = 'You pay {$a} now and reach the courses right away. The same amount is then charged automatically every month until you cancel (from "My subscriptions", at any time); access then stays open until the end of the month paid.';
$string['subscription:mine'] = 'My subscriptions';
$string['subscription:months'] = '{$a} months paid';
$string['subscription:none'] = 'You have no subscription.';
$string['subscription:orshort'] = 'or {$a}/month';
$string['subscription:permonth'] = '{$a} a month';
$string['subscription:since'] = 'since {$a}';
$string['subscription:summary'] = '{$a} a month, cancel at any time';
$string['substatus:active'] = 'Subscribed ({$a->paid} months paid)';
$string['substatus:cancelled'] = 'Cancelled – access until {$a->until}';
$string['substatus:late'] = 'Charge refused';
$string['substatus:stopped'] = 'Charges stopped';
$string['substatus:suspended'] = 'Suspended for non-payment';
$string['suspend'] = 'Suspend';
$string['task:activecampaignsync'] = 'Send a buyer to ActiveCampaign';
$string['task:creditrefunds'] = 'Issue credit notes for refunded orders';
$string['task:suspendunpaid'] = 'Suspend access for unpaid instalments';
$string['task:webhookdeliver'] = 'Send an event to a trainer\'s webhook';
$string['team:accept'] = 'Join the team';
$string['team:active'] = 'Active';
$string['team:cancelinvite'] = 'Cancel the invitation';
$string['team:intro'] = 'Invite collaborators to your trainer space. Each logs in with their own Oriskill account, with the rights of their role; their actions are recorded in the log.';
$string['team:invalidlink'] = 'This invitation link is no longer valid (already used, cancelled or expired). Ask the trainer to send a new invitation.';
$string['team:invite'] = 'Invite a collaborator';
$string['team:invite_help'] = 'The person receives an e-mail with a link valid for {$a} days; they create their Oriskill account if they have none.';
$string['team:invited'] = 'Invitation sent to {$a}.';
$string['team:invitedon'] = 'invited on {$a}';
$string['team:joinas'] = 'You will join the team with your account: {$a}.';
$string['team:joined'] = 'You are now in the team of {$a}.';
$string['team:joinintro'] = '{$a->trainer} invites you to their team on Oriskill, as {$a->role}: {$a->desc}';
$string['team:jointitle'] = 'Team invitation';
$string['team:mail_body'] = 'Hello,

{$a->inviter} invites you to join the team of {$a->trainer} on Oriskill, with the role {$a->role}.

To accept, open this link (valid for {$a->days} days); you can create your Oriskill account if you have none:
{$a->url}';
$string['team:mail_subject'] = 'Invitation to the team of {$a->trainer}';
$string['team:pending'] = 'Invitation pending';
$string['team:person'] = 'Person';
$string['team:remove'] = 'Remove';
$string['team:remove_confirm'] = 'Remove this person from the team? They immediately lose access to your trainer space.';
$string['team:removed'] = 'Removed from the team.';
$string['team:resend'] = 'Send again';
$string['team:role'] = 'Role';
$string['team:send'] = 'Invite';
$string['team:since'] = 'since {$a}';
$string['team:switchto'] = 'Switch to the space of {$a}';
$string['team:workingin'] = 'Space of {$a->trainer} – your role: {$a->role}.';
$string['viewshowcase'] = 'View my showcase';

// Affiliation.
$string['affiliate_intro'] = 'Recommend Oriskill courses and earn a commission on every sale you bring: {$a->rate} % on Oriskill courses, and the rate chosen by each trainer on theirs.';
$string['affiliate_terms'] = 'Share your link: a visitor who follows it and buys within {$a->days} days earns you a commission. It becomes due {$a->validation} days after the purchase, once the refund period is over, and is cancelled if the order is refunded. Your own purchases and your own courses earn nothing.';
$string['affiliatecode'] = 'Code';
$string['affiliatecol'] = 'Affiliate';
$string['affiliatecommissions'] = 'Affiliate commissions';
$string['affiliatecommissions_intro'] = 'Commissions earned by affiliates. Oriskill pays those of its own courses; trainers pay those of their courses from their Sales page. Users join the programme from {$a}.';
$string['affiliatedisabled'] = 'The affiliate programme is closed for the moment.';
$string['affiliateenabled'] = 'Affiliate programme open';
$string['affiliateenabled_desc'] = 'Lets any user join the programme and earn commissions on the sales they bring.';
$string['affiliatelink'] = 'Your link';
$string['affiliatelink_desc'] = 'Share it on your website, social networks or emails. Adding ?ref=your code to any Oriskill address works too, for example a trainer\'s showcase.';
$string['affiliateprogram'] = 'Affiliate programme';
$string['affiliaterate'] = 'Affiliate commission (%)';
$string['affiliaterate_desc'] = 'Share of the price you pay to the affiliate who brought a sale of your courses (0 = your courses are not offered to affiliates). Commissions are due {$a->days} days after the sale and listed on your Sales page. Affiliates join at {$a->link}.';
$string['affiliates'] = 'Affiliates';
$string['affiliatesuspended'] = 'Your affiliate account is suspended: your links no longer earn commissions. Contact support for more information.';
$string['commission:approved'] = 'Due';
$string['commission:cancelled'] = 'Cancelled';
$string['commission:paid'] = 'Paid';
$string['commission:pending'] = 'Pending';
$string['commissioncol'] = 'Commission';
$string['commissions_explained'] = 'Pending: within the {$a->validation}-day refund period. Due: to be paid to you. Cancelled: the order was refunded.';
$string['commissionsowed'] = 'Affiliate commissions';
$string['commissionsowed_intro'] = 'These sales were brought by affiliates. Once due, pay each commission using the details given by the affiliate, then mark it paid.';
$string['commissionstotal'] = 'Total: {$a}';
$string['cookiedays'] = 'Referral duration (days)';
$string['cookiedays_desc'] = 'How long a visitor who followed an affiliate link stays attributed to that affiliate. The last link followed wins.';
$string['joinaffiliate'] = 'Become an affiliate';
$string['markpaid'] = 'Mark paid';
$string['mycommissions'] = 'My commissions';
$string['noaffiliates'] = 'No affiliate yet.';
$string['nocommissions'] = 'No commission yet.';
$string['nopromotablecourses'] = 'No course is open to affiliates at the moment.';
$string['oriskill:manageaffiliates'] = 'Manage affiliates and their commissions';
$string['payoutdetails'] = 'Payout details';
$string['payoutdetails_desc'] = 'How you want to be paid (IBAN, PayPal address...). Only shown to whoever owes you a commission.';
$string['platformrate'] = 'Commission on Oriskill courses (%)';
$string['platformrate_desc'] = 'Rate paid by Oriskill on its own courses and plans. Trainers set their own rate for their courses.';
$string['privacy:metadata:affiliates'] = 'Affiliate account of a user.';
$string['privacy:metadata:affiliates:clicks'] = 'Number of visits through the affiliate\'s links.';
$string['privacy:metadata:affiliates:code'] = 'Referral code.';
$string['privacy:metadata:affiliates:payoutdetails'] = 'How the affiliate wants to be paid.';
$string['privacy:metadata:affiliates:userid'] = 'Affiliate account.';
$string['privacy:metadata:commissions'] = 'Commissions earned by an affiliate.';
$string['privacy:metadata:commissions:affiliateid'] = 'Affiliate.';
$string['privacy:metadata:commissions:amount'] = 'Commission amount.';
$string['privacy:metadata:commissions:orderid'] = 'Order that earned the commission.';
$string['privacy:metadata:commissions:status'] = 'Commission status.';
$string['privacy:metadata:cookie'] = 'Referral code of the affiliate whose link the visitor followed.';
$string['promotablecourses'] = 'Courses to recommend';
$string['ratecol'] = 'Rate';
$string['sellercol'] = 'Owed by';
$string['stat:clicks'] = 'Visits';
$string['stat:orders'] = 'Orders';
$string['task:approvecommissions'] = 'Validate affiliate commissions after the refund period';
$string['visitor_haveaccount'] = 'I already have an account';
$string['visitor_hint'] = 'To continue, create your Oriskill account or log in: you will then come back to this page.';
$string['visitor_signup'] = 'Create my account';
$string['mfarecommended'] = 'Protect your trainer account, which decides where your sales are paid: <a href="{$a}">turn on two-factor authentication</a>. A code from your phone will be asked at each login, on top of your password.';
$string['course:open'] = 'Open';
$string['course:settings'] = 'Settings';
$string['courses_intro'] = 'Create your courses, add your lessons and videos, then set their price to put them on sale.';
$string['coursestatus:draft'] = 'Draft';
$string['coursestatus:hidden'] = 'Hidden from learners';
$string['coursestatus:noprice'] = 'No price';
$string['dashboard:hello'] = 'Hello {$a}!';
$string['dashboard:intro'] = 'Here is the activity of your trainer space.';
$string['dashboard:latestsales'] = 'Latest sales';
$string['dashboard:steps'] = 'Getting started ({$a->done}/{$a->total})';
$string['learnerscount'] = '{$a} learner(s)';
$string['menu:dashboard'] = 'Dashboard';
$string['menu:videos'] = 'Add videos';
$string['seeall'] = 'See all';
$string['stat:courses'] = 'course(s), {$a} on sale';
$string['stat:learners'] = 'enrolled learner(s)';
$string['stat:revenue'] = 'collected';
$string['stat:sales'] = 'sale(s)';
$string['step:course'] = 'Create your first course';
$string['step:course_action'] = 'Create a course';
$string['step:mfa'] = 'Protect your account with two-factor authentication';
$string['step:mfa_action'] = 'Turn it on';
$string['step:onsale'] = 'Put a course on sale';
$string['step:onsale_action'] = 'Set prices';
$string['step:payment'] = 'Set up at least one payment method (SecureAndPay, Stripe or PayPal)';
$string['step:payment_action'] = 'Set up payments';
$string['step:plan'] = 'Subscribe to the trainer plan';
$string['step:plan_action'] = 'Subscribe';
$string['step:profile'] = 'Complete your showcase (tagline and presentation)';
$string['step:profile_action'] = 'Edit my showcase';
$string['videos:intro'] = 'Two ways to add a video to a lesson: upload it to Oriskill, or embed a YouTube or Vimeo video.';
$string['videos:link'] = '<p>Handy for very long videos or videos already online.</p>
<ol>
<li>Publish the video on YouTube as <strong>"Unlisted"</strong>, or on Vimeo with embedding restricted to oriskill.com (paid Vimeo plan).</li>
<li>Copy the video address.</li>
<li>In your lesson (a <strong>Page</strong>), click the editor\'s <strong>Multimedia</strong> button and paste the address in <strong>"Or add via URL"</strong>. You can also paste the address alone on a line: the player appears automatically.</li>
</ol>
<p class="text-muted small">Note: an "unlisted" YouTube video can still be watched by anyone who has the link. For paid content, prefer uploading to Oriskill, or Vimeo with a domain restriction.</p>';
$string['videos:link_title'] = 'Embed a YouTube or Vimeo video';
$string['videos:tips'] = '<ul>
<li><strong>Format</strong>: MP4 (H.264 video, AAC audio), played by all browsers and phones. Other formats (MOV, AVI...) may not play.</li>
<li><strong>Quality and size</strong>: 720p or 1080p is enough. The free HandBrake software greatly reduces a video\'s size with no visible loss ("Fast 1080p30" preset). Limit per file: {$a->maxsize}.</li>
<li><strong>Length</strong>: prefer 5 to 15 minute videos, one per topic, rather than one long video.</li>
<li><strong>Accessibility</strong>: add subtitles (.vtt file) from the Multimedia button, and a short written summary under the video.</li>
<li><strong>Tracking</strong>: in the page settings, add the completion condition "View the activity" to follow progress.</li>
</ul>';
$string['videos:tips_title'] = 'Tips for good videos';
$string['videos:upload'] = '<p>Recommended for your paid courses: only your enrolled learners can watch the video.</p>
<ol>
<li>Open your course and turn on <strong>Edit mode</strong> (top right).</li>
<li>In the section you want, click <strong>"Add an activity or resource"</strong>, then choose <strong>Page</strong>.</li>
<li>In the page content, click the editor\'s <strong>Multimedia</strong> button (film icon), then <strong>"Browse repositories..."</strong> and <strong>"Upload a file"</strong>: choose your video.</li>
<li>Add a title and a few lines of explanation, then click <strong>"Save and display"</strong>.</li>
</ol>
<p class="text-muted small">Maximum size per video: {$a->maxsize}.</p>';
$string['videos:upload_title'] = 'Upload the video to Oriskill';
$string['access_intro'] = 'Choose when each module opens and for whom. A locked module stays visible to the learner, with the condition to meet to open it.';
$string['accessremoved'] = 'Access removed.';
$string['backtolearners'] = 'Back to the learners of "{$a}"';
$string['course:access'] = 'Access';
$string['course:learners'] = 'Learners';
$string['error:groupname'] = 'Enter a group name.';
$string['error:newlearnername'] = 'This address has no account yet: enter the learner\'s first and last name to create it.';
$string['error:nopreviouscompletion'] = 'the previous module has no activity with completion tracking';
$string['error:purchasedaccess'] = 'This access was purchased: you can suspend it, but not remove it.';
$string['error:rules'] = 'Some rules were not saved: {$a}';
$string['error:usernametaken'] = 'An account already uses this username with another e-mail address.';
$string['exportcsv'] = 'Export (CSV)';
$string['group:add'] = 'Add group';
$string['group:delete_confirm'] = 'Delete the group "{$a}"? Learners keep their access to the course.';
$string['group:name_placeholder'] = 'Name of the new group';
$string['groups_intro'] = 'Groups let you reserve modules for some learners (for example "Premium" or "March session") and follow them together. Put a learner in a group from the Learners page.';
$string['learner'] = 'Learner';
$string['learner:access'] = 'Access';
$string['learner:active'] = 'Active';
$string['learner:completed'] = 'Completed';
$string['learner:details'] = 'Details';
$string['learner:expired'] = 'Expired';
$string['learner:lastaccess'] = 'Last access';
$string['learner:nogroup'] = 'No group';
$string['learner:progress'] = 'Progress';
$string['learner:reactivate'] = 'Reactivate';
$string['learner:remove'] = 'Remove';
$string['learner:remove_confirm'] = 'Remove the access offered to {$a}?';
$string['learner:since'] = 'Enrolled on';
$string['learner:sincedate'] = 'Enrolled on {$a}';
$string['learner:suspend'] = 'Suspend';
$string['learner:suspended'] = 'Suspended';
$string['learner:unlimited'] = 'Unlimited';
$string['learner:until'] = 'Access ends';
$string['learner:until_help'] = 'Empty: unlimited access.';
$string['menu:access'] = 'Access & release';
$string['menu:learners'] = 'Learners';
$string['module:locked'] = 'Locked';
$string['module:open'] = 'Open';
$string['nolearners'] = 'No learners in this course yet.';
$string['nomodules'] = 'This course has no module yet.';
$string['offer_help'] = 'If the learner has no account yet, enter their first and last name: the account is created and their login details are e-mailed to them. An offered access does not go through the store.';
$string['offeraccess'] = 'Offer access';
$string['offered'] = 'Access offered to {$a}.';
$string['offered:newaccount'] = 'Account created and access offered to {$a}: their login details were e-mailed to them.';
$string['rule:custom'] = 'Keep the custom rule (set in Moodle)';
$string['rule:custom_edit'] = 'See the rule';
$string['rule:date'] = 'From';
$string['rule:days'] = 'After';
$string['rule:days_after'] = 'day(s) of enrolment';
$string['rule:groups'] = 'Reserved for groups';
$string['rule:groups_help'] = 'No box ticked: all learners.';
$string['rule:nogroups'] = 'To reserve this module for some learners, first create groups at the bottom of the page.';
$string['rule:open'] = 'Open from enrolment';
$string['rule:previous'] = 'When the previous module is completed';
$string['rule:previous_first'] = '(first module)';
$string['rule:previous_none'] = '(the previous module has no activity with completion tracking)';
$string['rule:previous_stale'] = 'Activities were added to the previous module: save to include them.';
$string['rule:when'] = 'When does the module open?';
$string['source:offered'] = 'Offered';
$string['source:purchase'] = 'Purchased';
$string['stat:avgprogress'] = 'average progress';
$string['stat:completed'] = 'course(s) completed';
$string['stat:learnersactive'] = 'active access(es)';
$string['stat:learnerstotal'] = 'learner(s)';
$string['state:done'] = 'Done';
$string['state:failed'] = 'Not passed';
$string['state:notracking'] = 'Not tracked';
$string['state:todo'] = 'To do';
$string['learner:grade'] = 'Grade';
$string['picker:course'] = 'Course';
$string['gw:enabled_desc'] = 'Your buyers can pay for your courses with this method.';
$string['gw:howto'] = 'How do I get this information?';
$string['gw:keep'] = 'Already saved: leave empty to keep it.';
$string['gw:paypal:desc'] = 'Payment with a PayPal account or a card, paid into your PayPal Business account.';
$string['gw:paypal:enabled'] = 'Offer PayPal';
$string['gw:paypal:howto'] = '<ol>
<li>Log in to <strong>developer.paypal.com</strong> with your PayPal Business account, open <strong>Apps &amp; Credentials</strong> and create an app (<em>Sandbox</em> to test, <em>Live</em> to sell).</li>
<li>Copy the app\'s <strong>Client ID</strong> and <strong>Secret</strong> below.</li>
<li>In the app, add a <strong>webhook</strong> with the address:<br><code>{$a->webhook}</code><br>and the events <code>CHECKOUT.ORDER.APPROVED</code>, <code>PAYMENT.CAPTURE.COMPLETED</code>, <code>PAYMENT.CAPTURE.DENIED</code> and <code>PAYMENT.CAPTURE.REFUNDED</code>.</li>
<li>Copy the <strong>Webhook ID</strong> shown by PayPal below.</li>
</ol>';
$string['gw:paypal:publickey'] = 'Client ID';
$string['gw:paypal:publickey_invalid'] = 'Invalid Client ID.';
$string['gw:paypal:secretkey'] = 'Secret';
$string['gw:paypal:secretkey_invalid'] = 'Invalid secret.';
$string['gw:paypal:testmode'] = 'Sandbox';
$string['gw:paypal:testmode_desc'] = 'PayPal "Sandbox" app: no real payments, for testing.';
$string['gw:paypal:webhooksecret'] = 'Webhook ID';
$string['gw:paypal:webhooksecret_invalid'] = 'Invalid webhook ID.';
$string['gw:secureandpay:desc'] = 'Card payment, paid into your SecureAndPay account.';
$string['gw:stripe:desc'] = 'Card payment (and Apple Pay / Google Pay depending on your account), paid into your Stripe account.';
$string['gw:stripe:enabled'] = 'Offer Stripe';
$string['gw:stripe:howto'] = '<ol>
<li>Log in to <strong>dashboard.stripe.com</strong>. To test, turn on <strong>test mode</strong> (switch at the top).</li>
<li>In <strong>Developers → API keys</strong>, copy the <strong>secret key</strong> (<code>sk_test_...</code> in test, <code>sk_live_...</code> in production) below.</li>
<li>In <strong>Developers → Webhooks</strong>, add an endpoint with the address:<br><code>{$a->webhook}</code><br>and the events <code>checkout.session.completed</code>, <code>charge.succeeded</code>, <code>charge.failed</code> and <code>charge.refunded</code>.</li>
<li>Open the endpoint and copy its <strong>signing secret</strong> (<code>whsec_...</code>) below.</li>
</ol>';
$string['gw:stripe:publickey'] = 'Publishable key (optional)';
$string['gw:stripe:publickey_invalid'] = 'Publishable key expected: pk_test_... in test mode, pk_live_... otherwise.';
$string['gw:stripe:secretkey'] = 'Secret key';
$string['gw:stripe:secretkey_invalid'] = 'Secret key expected: sk_test_... (or rk_test_...) in test mode, sk_live_... (or rk_live_...) otherwise.';
$string['gw:stripe:testmode'] = 'Test mode';
$string['gw:stripe:testmode_desc'] = 'Stripe test keys: no real payments, for testing.';
$string['gw:stripe:webhooksecret'] = 'Webhook signing secret';
$string['gw:stripe:webhooksecret_invalid'] = 'The signing secret starts with whsec_.';
$string['gwstate:incomplete'] = 'Incomplete';
$string['gwstate:off'] = 'Off';
$string['gwstate:on'] = 'On';
$string['navgroup:account'] = 'Settings';
$string['navgroup:courses'] = 'Courses';
$string['navgroup:learners'] = 'Learner follow-up';
$string['navgroup:overview'] = 'Overview';
$string['navgroup:public'] = 'Online';
$string['webhook:add'] = 'Add the webhook';
$string['webhook:event_access_suspended'] = 'Access suspended for non-payment';
$string['webhook:event_instalment_failed'] = 'Instalment refused';
$string['webhook:event_instalment_paid'] = 'Instalment paid';
$string['webhook:event_instalments_stopped'] = 'Schedule stopped';
$string['webhook:event_lead_created'] = 'New sign-up (capture page)';
$string['webhook:event_order_paid'] = 'Order paid';
$string['webhook:event_refund_done'] = 'Refund';
$string['webhook:intro'] = 'Oriskill sends your sales events as JSON to the addresses you choose (Zapier, Make, your own tools): learner, order, courses, offer and instalments. Each call carries the X-Oriskill-Event and X-Oriskill-Signature headers ("sha256=" followed by the HMAC-SHA256 of the body with the address\'s secret) so you can check it comes from Oriskill. Failed calls are retried automatically.';
$string['webhook:lasterror'] = '✗ Last call failed ({$a->event}, {$a->time}): {$a->error}. It will be retried automatically.';
$string['webhook:lastok'] = '✓ Last call succeeded ({$a->event}, {$a->time})';
$string['webhook:removeconfirm'] = 'Remove this webhook? No event will be sent to it any more.';
$string['webhook:showsecret'] = 'Signing secret';
$string['webhook:test'] = 'Send a test';
$string['webhook:testok'] = 'Test event sent and accepted by the address.';
$string['webhook:title'] = 'Webhooks';
$string['webhook:url'] = 'Receiving address (https)';

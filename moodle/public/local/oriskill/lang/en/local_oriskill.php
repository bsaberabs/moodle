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
$string['field:slug_help'] = 'Your showcase will be at oriskill.com/your-address. Lowercase letters, digits and hyphens only; it cannot be changed afterwards.';
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
$string['step:payment'] = 'Enter your SecureAndPay payment account';
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

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
 * Chaînes françaises.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aboutcreator'] = 'Présentation';
$string['applicationsent'] = 'Votre candidature a bien été envoyée. Vous recevrez un e-mail dès qu\'elle aura été étudiée.';
$string['applicationstatus:pending'] = 'Votre candidature est en cours d\'étude. Vous recevrez un e-mail dès qu\'elle aura été traitée.';
$string['applicationstatus:rejected'] = 'Votre candidature n\'a pas été retenue. Contactez le support pour plus d\'informations.';
$string['applicationstatus:suspended'] = 'Votre espace formateur est suspendu. Contactez le support pour plus d\'informations.';
$string['amountcol'] = 'Montant';
$string['approve'] = 'Valider';
$string['blocker:inactive'] = 'Votre espace formateur n\'est pas actif.';
$string['blocker:nopayment'] = 'Vos formations ne peuvent pas encore être achetées : renseignez votre compte SecureAndPay dans <a href="{$a->payment}">Paiements</a>.';
$string['blocker:nosubscription'] = 'Vos formations ne peuvent pas encore être achetées : un abonnement formateur est nécessaire. <a href="{$a->plan}">S\'abonner</a>.';
$string['buyercol'] = 'Acheteur';
$string['comparecol'] = 'Prix barré ({$a})';
$string['creatorplancode'] = 'Code de la formule d\'abonnement formateur';
$string['creatorplancode_desc'] = 'Code de la formule d\'abonnement ModernCommerce qu\'un formateur doit détenir pour vendre. Laissez vide pour que tout formateur validé puisse vendre.';
$string['becometrainer'] = 'Devenir formateur';
$string['becometrainer_intro'] = 'Vendez vos formations sur Oriskill : vous disposez de votre propre espace de cours et d\'une vitrine publique. Présentez-vous ; chaque candidature est étudiée.';
$string['createcourse'] = 'Créer une formation';
$string['creatorcourses'] = 'Formations';
$string['creatorscategory'] = 'Formateurs';
$string['editshowcase'] = 'Mon espace formateur';
$string['error:alreadyapplied'] = 'Vous avez déjà envoyé une candidature.';
$string['error:invalidprices'] = 'Ces prix n\'ont pas été enregistrés (le prix doit être positif, le prix barré supérieur) : {$a}';
$string['error:mixedsellers'] = 'votre panier contient des formations de plusieurs vendeurs ({$a}). Chaque vendeur est payé séparément : achetez leurs formations dans des commandes distinctes.';
$string['error:sellercannotsell'] = 'les formations de {$a} ne peuvent pas être achetées pour le moment.';
$string['error:slugreserved'] = 'Cette adresse est réservée. Choisissez-en une autre.';
$string['error:slugshape'] = 'Utilisez de 3 à 30 lettres minuscules, chiffres et tirets simples, par exemple jean-dupont.';
$string['error:slugtaken'] = 'Cette adresse est déjà prise.';
$string['field:bio'] = 'Présentation';
$string['field:displayname'] = 'Nom affiché aux apprenants';
$string['field:logo'] = 'Logo ou photo';
$string['field:motivation'] = 'Votre projet';
$string['field:sapkey'] = 'Clé de signature SecureAndPay';
$string['field:sapkey_keep'] = 'Une clé est enregistrée (chiffrée). Laissez vide pour la conserver.';
$string['field:sapsiteid'] = 'Identifiant du site SecureAndPay (IdSite)';
$string['field:saptestmode'] = 'Mode test';
$string['field:saptestmode_desc'] = 'Utiliser l\'environnement de test SecureAndPay (aucun paiement réel)';
$string['field:slug'] = 'Adresse de la vitrine';
$string['field:slug_help'] = 'Votre vitrine sera à l\'adresse oriskill.com/votre-adresse. Lettres minuscules, chiffres et tirets uniquement ; elle ne pourra plus être modifiée.';
$string['field:tagline'] = 'Accroche';
$string['mail:approved_body'] = 'Bonjour,

Votre espace formateur « {$a->name} » est ouvert.

Votre vitrine publique : {$a->url}
Créez votre première formation : {$a->courseurl}

L\'équipe Oriskill';
$string['mail:approved_subject'] = 'Votre espace formateur Oriskill est ouvert';
$string['mail:newapplication_body'] = 'Une nouvelle candidature de formateur, « {$a->name} », attend votre validation : {$a->url}';
$string['mail:newapplication_subject'] = 'Nouvelle candidature de formateur : {$a->name}';
$string['managecreators'] = 'Formateurs';
$string['managecreators_intro'] = 'Étudiez les candidatures de formateurs et gérez leurs espaces. Les candidatures se font depuis {$a}.';
$string['menu:profile'] = 'Ma vitrine';
$string['mycourses'] = 'Mes formations';
$string['nocourses'] = 'Aucune formation en vente pour le moment.';
$string['nocoursesyet'] = 'Vous n\'avez pas encore de formation. <a href="{$a}">Créez votre première formation</a>.';
$string['nocreators'] = 'Aucune candidature de formateur pour le moment.';
$string['nosales'] = 'Aucune vente pour le moment.';
$string['onsale'] = 'En vente';
$string['ordercol'] = 'Commande';
$string['oriskill:apply'] = 'Candidater pour un espace formateur';
$string['oriskill:managecreators'] = 'Gérer les candidatures et les espaces formateurs';
$string['paymentsettings'] = 'Paiements';
$string['paymentsettings_intro'] = 'Les ventes de vos formations sont versées directement sur votre propre compte SecureAndPay.';
$string['paymentsettings_support'] = 'Avant votre première vente, demandez au support SecureAndPay (webmaster@secureandpay.com) de déclarer le site {$a->site} et l\'adresse de notification {$a->notify} sur votre compte.';
$string['platformseller'] = 'Oriskill';
$string['pluginname'] = 'Formateurs Oriskill';
$string['pricecol'] = 'Prix ({$a})';
$string['pricing'] = 'Prix';
$string['pricing_intro'] = 'Fixez le prix de chaque formation et cochez « En vente » pour la proposer sur votre vitrine et dans le catalogue.';
$string['privacy:metadata:creators'] = 'Espace formateur d\'un utilisateur.';
$string['privacy:metadata:creators:bio'] = 'Présentation publique.';
$string['privacy:metadata:creators:displayname'] = 'Nom affiché aux apprenants.';
$string['privacy:metadata:creators:motivation'] = 'Message de candidature.';
$string['privacy:metadata:creators:sapkey'] = 'Clé de signature SecureAndPay du formateur, stockée chiffrée.';
$string['privacy:metadata:creators:sapsiteid'] = 'Identifiant du site SecureAndPay du formateur.';
$string['privacy:metadata:creators:status'] = 'Statut de l\'espace formateur.';
$string['privacy:metadata:creators:tagline'] = 'Accroche publique.';
$string['privacy:metadata:creators:userid'] = 'Compte du formateur.';
$string['privacy:metadata:logo'] = 'Logo ou photo de l\'espace formateur.';
$string['reactivate'] = 'Réactiver';
$string['reject'] = 'Refuser';
$string['sales'] = 'Ventes';
$string['sales_intro'] = 'Les ventes de vos formations, versées sur votre compte SecureAndPay.';
$string['salepaid'] = 'Payée';
$string['salerefunded'] = 'Remboursée';
$string['salestotal'] = '{$a->count} vente(s), {$a->total} encaissés';
$string['showcasenotfound'] = 'Cette page formateur n\'existe pas.';
$string['showcasetitle'] = '{$a} – Formations en ligne';
$string['status:active'] = 'Actif';
$string['status:pending'] = 'À étudier';
$string['status:rejected'] = 'Refusé';
$string['status:suspended'] = 'Suspendu';
$string['submitapplication'] = 'Envoyer ma candidature';
$string['suspend'] = 'Suspendre';
$string['viewshowcase'] = 'Voir ma vitrine';

// Affiliation.
$string['affiliate_intro'] = 'Recommandez les formations Oriskill et touchez une commission sur chaque vente que vous apportez : {$a->rate} % sur les formations Oriskill, et le taux choisi par chaque formateur sur les siennes.';
$string['affiliate_terms'] = 'Partagez votre lien : un visiteur qui le suit et achète dans les {$a->days} jours vous rapporte une commission. Elle devient due {$a->validation} jours après l\'achat, une fois le délai de remboursement passé, et elle est annulée si la commande est remboursée. Vos propres achats et vos propres formations ne rapportent rien.';
$string['affiliatecode'] = 'Code';
$string['affiliatecol'] = 'Affilié';
$string['affiliatecommissions'] = 'Commissions d\'affiliation';
$string['affiliatecommissions_intro'] = 'Commissions gagnées par les affiliés. Oriskill verse celles de ses propres formations ; les formateurs versent celles de leurs formations depuis leur page Ventes. Les utilisateurs rejoignent le programme depuis {$a}.';
$string['affiliatedisabled'] = 'Le programme d\'affiliation est fermé pour le moment.';
$string['affiliateenabled'] = 'Programme d\'affiliation ouvert';
$string['affiliateenabled_desc'] = 'Permet à tout utilisateur de rejoindre le programme et de toucher des commissions sur les ventes qu\'il apporte.';
$string['affiliatelink'] = 'Votre lien';
$string['affiliatelink_desc'] = 'Partagez-le sur votre site, vos réseaux sociaux ou vos e-mails. Ajouter ?ref=votre code à n\'importe quelle adresse Oriskill fonctionne aussi, par exemple la vitrine d\'un formateur.';
$string['affiliateprogram'] = 'Programme d\'affiliation';
$string['affiliaterate'] = 'Commission des affiliés (%)';
$string['affiliaterate_desc'] = 'Part du prix que vous versez à l\'affilié qui a apporté une vente de vos formations (0 = vos formations ne sont pas proposées aux affiliés). Les commissions sont dues {$a->days} jours après la vente et figurent dans votre page Ventes. Les affiliés s\'inscrivent sur {$a->link}.';
$string['affiliates'] = 'Affiliés';
$string['affiliatesuspended'] = 'Votre compte d\'affilié est suspendu : vos liens ne rapportent plus de commission. Contactez le support pour plus d\'informations.';
$string['commission:approved'] = 'Due';
$string['commission:cancelled'] = 'Annulée';
$string['commission:paid'] = 'Versée';
$string['commission:pending'] = 'En attente';
$string['commissioncol'] = 'Commission';
$string['commissions_explained'] = 'En attente : délai de remboursement de {$a->validation} jours en cours. Due : à vous verser. Annulée : la commande a été remboursée.';
$string['commissionsowed'] = 'Commissions d\'affiliation';
$string['commissionsowed_intro'] = 'Ces ventes ont été apportées par des affiliés. Une fois due, versez chaque commission avec les coordonnées indiquées par l\'affilié, puis marquez-la comme versée.';
$string['commissionstotal'] = 'Total : {$a}';
$string['cookiedays'] = 'Durée de suivi (jours)';
$string['cookiedays_desc'] = 'Pendant combien de temps un visiteur venu par un lien d\'affilié reste attribué à cet affilié. Le dernier lien suivi l\'emporte.';
$string['joinaffiliate'] = 'Devenir affilié';
$string['markpaid'] = 'Marquer versée';
$string['mycommissions'] = 'Mes commissions';
$string['noaffiliates'] = 'Aucun affilié pour le moment.';
$string['nocommissions'] = 'Aucune commission pour le moment.';
$string['nopromotablecourses'] = 'Aucune formation n\'est ouverte aux affiliés pour le moment.';
$string['oriskill:manageaffiliates'] = 'Gérer les affiliés et leurs commissions';
$string['payoutdetails'] = 'Coordonnées de versement';
$string['payoutdetails_desc'] = 'Comment vous souhaitez être payé (IBAN, adresse PayPal…). Visible uniquement par qui vous doit une commission.';
$string['platformrate'] = 'Commission sur les formations Oriskill (%)';
$string['platformrate_desc'] = 'Taux versé par Oriskill sur ses propres formations et formules. Les formateurs fixent leur propre taux pour leurs formations.';
$string['privacy:metadata:affiliates'] = 'Compte d\'affilié d\'un utilisateur.';
$string['privacy:metadata:affiliates:clicks'] = 'Nombre de visites venues par les liens de l\'affilié.';
$string['privacy:metadata:affiliates:code'] = 'Code d\'affiliation.';
$string['privacy:metadata:affiliates:payoutdetails'] = 'Comment l\'affilié souhaite être payé.';
$string['privacy:metadata:affiliates:userid'] = 'Compte de l\'affilié.';
$string['privacy:metadata:commissions'] = 'Commissions gagnées par un affilié.';
$string['privacy:metadata:commissions:affiliateid'] = 'Affilié.';
$string['privacy:metadata:commissions:amount'] = 'Montant de la commission.';
$string['privacy:metadata:commissions:orderid'] = 'Commande à l\'origine de la commission.';
$string['privacy:metadata:commissions:status'] = 'Statut de la commission.';
$string['privacy:metadata:cookie'] = 'Code de l\'affilié dont le visiteur a suivi le lien.';
$string['promotablecourses'] = 'Formations à recommander';
$string['ratecol'] = 'Taux';
$string['sellercol'] = 'Due par';
$string['stat:clicks'] = 'Visites';
$string['stat:orders'] = 'Commandes';
$string['task:approvecommissions'] = 'Valider les commissions d\'affiliation après le délai de remboursement';
$string['visitor_haveaccount'] = 'J\'ai déjà un compte';
$string['visitor_hint'] = 'Pour continuer, créez votre compte Oriskill ou connectez-vous : vous reviendrez ensuite sur cette page.';
$string['visitor_signup'] = 'Créer mon compte';

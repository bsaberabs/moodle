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
$string['appearance'] = 'Apparence de la vitrine';
$string['applicationsent'] = 'Votre candidature a bien été envoyée. Vous recevrez un e-mail dès qu\'elle aura été étudiée.';
$string['applicationstatus:pending'] = 'Votre candidature est en cours d\'étude. Vous recevrez un e-mail dès qu\'elle aura été traitée.';
$string['applicationstatus:rejected'] = 'Votre candidature n\'a pas été retenue. Contactez le support pour plus d\'informations.';
$string['applicationstatus:suspended'] = 'Votre espace formateur est suspendu. Contactez le support pour plus d\'informations.';
$string['amountcol'] = 'Montant';
$string['approve'] = 'Valider';
$string['billing:incomplete'] = 'Complétez ces informations : elles doivent figurer sur vos factures (nom ou raison sociale, adresse, SIRET).';
$string['billing:intro'] = 'Ces informations apparaissent sur les factures et avoirs émis à partir de maintenant. Les documents déjà émis ne changent pas.';
$string['billing:legaladdress'] = 'Adresse';
$string['billing:legalname'] = 'Nom ou raison sociale';
$string['billing:legalname_help'] = 'Le vendeur indiqué sur vos factures : votre entreprise, ou votre nom si vous êtes micro-entrepreneur. Le nom de votre vitrine est aussi affiché.';
$string['billing:notes'] = 'Mentions complémentaires';
$string['billing:notes_help'] = 'Imprimées en bas de chaque facture : numéro de déclaration d\'activité de formation, conditions de paiement, etc.';
$string['billing:novat'] = 'Non assujetti à la TVA (franchise en base)';
$string['billing:siret'] = 'SIRET';
$string['billing:title'] = 'Informations de facturation';
$string['billing:vatnumber'] = 'Numéro de TVA intracommunautaire';
$string['billing:vatnumberrequired'] = 'Indiquez votre numéro de TVA.';
$string['billing:vatrate'] = 'TVA';
$string['billing:vatrate_help'] = 'Vos prix s\'entendent TTC. Si vous êtes assujetti, la facture détaille le montant HT et la TVA à votre taux ; sinon elle porte la mention « TVA non applicable, art. 293 B du CGI ».';
$string['billing:vatrate_value'] = 'Assujetti, taux {$a} %';
$string['blocker:inactive'] = 'Votre espace formateur n\'est pas actif.';
$string['blocker:nopayment'] = 'Vos formations ne peuvent pas encore être achetées : configurez au moins un moyen de paiement (SecureAndPay, Stripe ou PayPal) dans <a href="{$a->payment}">Paiements</a>.';
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
$string['credit:amount'] = 'Montant TTC';
$string['credit:confirm'] = 'Émettre l\'avoir';
$string['credit:confirmtext'] = 'Un avoir ne peut pas être supprimé. L\'émettre ?';
$string['credit:issue'] = 'Avoir…';
$string['credit:issued'] = 'Avoir {$a} émis et envoyé à l\'acheteur.';
$string['credit:line'] = 'Avoir sur la facture {$a}';
$string['credit:mail_body'] = 'Bonjour,

{$a->trainer} a émis l\'avoir {$a->number} de {$a->amount} sur la facture {$a->invoice}.

Téléchargez-le ici : {$a->url}';
$string['credit:mail_subject'] = 'Avoir {$a->number} – {$a->trainer}';
$string['credit:ofinvoice'] = 'Sur la facture {$a->number} du {$a->date}';
$string['credit:ofinvoiceshort'] = 'sur {$a}';
$string['credit:reason'] = 'Motif (facultatif)';
$string['credit:refunded'] = 'commande remboursée';
$string['credit:title'] = 'Avoir';
$string['domainnotready'] = 'Le domaine {$a->domain} n\'est pas encore relié à Oriskill. Chez votre registraire, créez un enregistrement A pour {$a->domain} et pour www.{$a->domain} vers {$a->ip}. La prise en compte peut demander quelques heures.';
$string['domainready'] = 'Le domaine {$a->domain} est bien relié à Oriskill : votre vitrine y est accessible.';
$string['editshowcase'] = 'Mon espace formateur';
$string['error:alreadyapplied'] = 'Vous avez déjà envoyé une candidature.';
$string['error:color'] = 'Indiquez une couleur au format #rrggbb, par exemple #7c3aed.';
$string['error:creditamount'] = 'Montant d\'avoir non valide : il doit être positif et ne pas dépasser le montant restant de la facture.';
$string['error:domainshape'] = 'Ce nom de domaine n\'est pas valide. Exemple : mon-academie.com';
$string['error:domainsite'] = 'Choisissez votre propre nom de domaine, pas une adresse d\'Oriskill.';
$string['error:domaintaken'] = 'Ce nom de domaine est déjà relié à une autre vitrine.';
$string['error:instalments'] = 'Choisissez un nombre d\'échéances proposé et un prix total positif.';
$string['error:invalidprices'] = 'Ces prix n\'ont pas été enregistrés (le prix doit être positif, le prix barré supérieur) : {$a}';
$string['error:mixedsellers'] = 'votre panier contient des formations de plusieurs vendeurs ({$a}). Chaque vendeur est payé séparément : achetez leurs formations dans des commandes distinctes.';
$string['error:noinstalments'] = 'Cette formation n\'est pas proposée en plusieurs fois pour le moment.';
$string['error:sellercannotsell'] = 'les formations de {$a} ne peuvent pas être achetées pour le moment.';
$string['error:slugreserved'] = 'Cette adresse est réservée. Choisissez-en une autre.';
$string['error:slugshape'] = 'Utilisez de 3 à 30 lettres minuscules, chiffres et tirets simples, par exemple jean-dupont.';
$string['error:slugtaken'] = 'Cette adresse est déjà prise.';
$string['field:accentcolor'] = 'Couleur d\'accent';
$string['field:accentcolor_help'] = 'Utilisée pour les boutons d\'appel à l\'action et les soulignements de titres.';
$string['field:banner'] = 'Bannière';
$string['field:banner_help'] = 'Image large affichée en haut de votre vitrine, derrière votre nom (1600 × 500 pixels conseillés). Sans bannière, le haut de la vitrine prend votre couleur principale.';
$string['field:bio'] = 'Présentation';
$string['field:customdomain'] = 'Nom de domaine personnalisé';
$string['field:customdomain_help'] = 'Facultatif. Votre vitrine sera aussi accessible à cette adresse (par exemple mon-academie.com), en plus de oriskill.com/votre-adresse. Chez votre registraire, faites pointer le domaine et www vers l\'adresse IP indiquée sous ce champ après l\'enregistrement. Le certificat HTTPS est créé automatiquement.';
$string['field:displayname'] = 'Nom affiché aux apprenants';
$string['field:fontstyle'] = 'Police';
$string['field:logo'] = 'Logo ou photo';
$string['field:motivation'] = 'Votre projet';
$string['field:primarycolor'] = 'Couleur principale';
$string['field:primarycolor_help'] = 'Couleur de votre marque : haut de la vitrine, bouton de connexion, prix. La couleur du texte posé dessus (blanc ou noir) est choisie automatiquement pour rester lisible.';
$string['field:sapkey'] = 'Clé de signature SecureAndPay';
$string['field:sapkey_keep'] = 'Une clé est enregistrée (chiffrée). Laissez vide pour la conserver.';
$string['field:sapsiteid'] = 'Identifiant du site SecureAndPay (IdSite)';
$string['field:saptestmode'] = 'Mode test';
$string['field:saptestmode_desc'] = 'Utiliser l\'environnement de test SecureAndPay (aucun paiement réel)';
$string['field:slug'] = 'Adresse de la vitrine';
$string['field:slug_help'] = 'Votre vitrine sera à l\'adresse oriskill.com/votre-adresse. Lettres minuscules, chiffres et tirets uniquement ; elle ne pourra plus être modifiée.';
$string['field:tagline'] = 'Accroche';
$string['font:classic'] = 'Classique (Helvetica)';
$string['font:elegant'] = 'Élégante (avec empattements)';
$string['font:modern'] = 'Moderne';
$string['group:instalments'] = 'Paiement en {$a} fois';
$string['group:paidatonce'] = 'Paiement comptant';
$string['instalmentmail:failed_learner'] = 'Bonjour {$a->learner},

L\'échéance de votre formation « {$a->course} » n\'a pas pu être prélevée ({$a->paid} échéance(s) sur {$a->count} payée(s)).

Vérifiez votre carte bancaire ou contactez {$a->trainer}. Si le paiement n\'est pas régularisé d\'ici le {$a->deadline}, votre accès à la formation sera suspendu ; il sera rétabli dès le prochain prélèvement réussi.';
$string['instalmentmail:failed_subject'] = 'Échéance impayée : {$a->course}';
$string['instalmentmail:failed_trainer'] = 'L\'échéance de {$a->learner} pour « {$a->course} » a été refusée ({$a->paid} sur {$a->count} payée(s)). Sans régularisation d\'ici le {$a->deadline}, son accès sera suspendu automatiquement.

Fiche de l\'élève : {$a->url}';
$string['instalmentmail:restored_learner'] = 'Bonjour {$a->learner},

Nous avons bien reçu votre paiement : votre accès à la formation « {$a->course} » est rétabli.';
$string['instalmentmail:restored_subject'] = 'Accès rétabli : {$a->course}';
$string['instalmentmail:restored_trainer'] = 'Le paiement de {$a->learner} pour « {$a->course} » a été reçu : son accès est rétabli.

Fiche de l\'élève : {$a->url}';
$string['instalmentmail:stopped_learner'] = 'Bonjour {$a->learner},

Les prélèvements de votre formation « {$a->course} » ont été arrêtés alors que {$a->paid} échéance(s) sur {$a->count} ont été payées. Contactez {$a->trainer} pour régler le solde ; sans régularisation d\'ici le {$a->deadline}, votre accès sera suspendu.';
$string['instalmentmail:stopped_subject'] = 'Paiement interrompu : {$a->course}';
$string['instalmentmail:stopped_trainer'] = 'Les prélèvements de {$a->learner} pour « {$a->course} » ont été arrêtés chez SecureAndPay ({$a->paid} sur {$a->count} payée(s)). Sans régularisation d\'ici le {$a->deadline}, son accès sera suspendu automatiquement.

Fiche de l\'élève : {$a->url}';
$string['instalmentmail:suspended_learner'] = 'Bonjour {$a->learner},

Faute de paiement, votre accès à la formation « {$a->course} » est suspendu. Il sera rétabli dès réception du paiement. Contactez {$a->trainer} si besoin.';
$string['instalmentmail:suspended_subject'] = 'Accès suspendu : {$a->course}';
$string['instalmentmail:suspended_trainer'] = 'L\'accès de {$a->learner} à « {$a->course} » a été suspendu : échéance impayée depuis {$a->days} jours. Vous pouvez le réactiver vous-même depuis sa fiche : {$a->url}';
$string['instalments:available'] = 'Cette formation est disponible en {$a->count} fois : {$a->count} × {$a->displayamount}';
$string['instalments:buytitle'] = 'Payer {$a} en plusieurs fois';
$string['instalments:choose'] = 'Payer en plusieurs fois';
$string['instalments:countcol'] = 'En plusieurs fois';
$string['instalments:howitworks'] = 'Vous payez la première échéance maintenant et accédez tout de suite à la formation. Les {$a->count} échéances de {$a->displayamount} sont ensuite prélevées automatiquement chaque mois sur la même carte, puis les prélèvements s\'arrêtent.';
$string['instalments:intro'] = 'Paiement en plusieurs fois : choisissez le nombre d\'échéances mensuelles et le prix total payé ainsi (égal au prix comptant ou un peu plus élevé). La carte de l\'élève est prélevée chaque mois par SecureAndPay. En cas d\'échéance impayée, l\'élève et vous êtes prévenus, et son accès est suspendu au bout de {$a->days} jours sans régularisation.';
$string['instalments:needsap'] = 'Pour proposer le paiement en plusieurs fois, <a href="{$a->payment}">configurez votre compte SecureAndPay</a> : c\'est lui qui prélève les échéances chaque mois.';
$string['instalments:none'] = 'Non';
$string['instalments:orshort'] = 'ou {$a->count} × {$a->displayamount}';
$string['instalments:pay'] = 'Payer la 1re échéance ({$a->displayamount})';
$string['instalments:payatonce'] = 'Payer en une fois';
$string['instalments:preview'] = '{$a->count} × {$a->amount}';
$string['instalments:summary'] = '{$a->count} mensualités de {$a->displayamount}, soit {$a->displaytotal} au total.';
$string['instalments:times'] = '{$a} fois';
$string['instalments:totalcol'] = 'Prix total en plusieurs fois ({$a})';
$string['instalmentstatus:active'] = '{$a->paid}/{$a->count} échéances payées';
$string['instalmentstatus:completed'] = 'Payé en {$a->count} fois';
$string['instalmentstatus:late'] = 'Échéance impayée ({$a->paid}/{$a->count})';
$string['instalmentstatus:stopped'] = 'Prélèvements arrêtés ({$a->paid}/{$a->count})';
$string['instalmentstatus:suspended'] = 'Suspendu pour impayé ({$a->paid}/{$a->count})';
$string['invoice:billedto'] = 'Facturé à';
$string['invoice:date'] = 'Date';
$string['invoice:description'] = 'Désignation';
$string['invoice:discount'] = 'Remise';
$string['invoice:instalments'] = 'Payable en {$a->count} échéances mensuelles de {$a->amount}.';
$string['invoice:linetotal'] = 'Montant TTC';
$string['invoice:novat'] = 'TVA non applicable, art. 293 B du CGI.';
$string['invoice:number'] = 'N°';
$string['invoice:order'] = 'Commande';
$string['invoice:paid'] = 'Acquittée';
$string['invoice:quantity'] = 'Qté';
$string['invoice:siret'] = 'SIRET : {$a}';
$string['invoice:title'] = 'Facture';
$string['invoice:total'] = 'Total';
$string['invoice:totalexcl'] = 'Total HT';
$string['invoice:totalincl'] = 'Total TTC';
$string['invoice:unitprice'] = 'Prix unitaire TTC';
$string['invoice:vat'] = 'TVA {$a} %';
$string['invoice:vatnumber'] = 'TVA intracommunautaire : {$a}';
$string['invoices:client'] = 'Client';
$string['invoices:documents'] = 'Factures et avoirs';
$string['invoices:intro'] = 'Vous encaissez vos ventes sur votre propre compte : les factures sont émises à votre nom, avec une numérotation continue qui vous est propre (F-000001…). Un avoir (AV-000001…) annule tout ou partie d\'une facture, par exemple après un remboursement ; il est émis automatiquement quand une commande est remboursée par la boutique.';
$string['invoices:none'] = 'Aucune facture pour le moment : elles sont émises à chaque vente payée.';
$string['learner:payment'] = 'Paiement';
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
$string['menu:invoices'] = 'Factures';
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
$string['paymentsettings_intro'] = 'Les ventes de vos formations sont versées directement sur vos propres comptes. Activez les moyens de paiement que vous souhaitez proposer à vos acheteurs : au moins un est nécessaire pour vendre.';
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
$string['showcase:legal'] = 'Informations légales';
$string['showcase:login'] = 'Se connecter';
$string['showcase:mylearning'] = 'Mes formations';
$string['showcase:privacy'] = 'Confidentialité';
$string['showcase:seecourses'] = 'Voir les formations';
$string['showcase:terms'] = 'Conditions générales';
$string['showcasenotfound'] = 'Cette page formateur n\'existe pas.';
$string['showcasetitle'] = '{$a} – Formations en ligne';
$string['status:active'] = 'Actif';
$string['status:pending'] = 'À étudier';
$string['status:rejected'] = 'Refusé';
$string['status:suspended'] = 'Suspendu';
$string['submitapplication'] = 'Envoyer ma candidature';
$string['suspend'] = 'Suspendre';
$string['task:creditrefunds'] = 'Émettre les avoirs des commandes remboursées';
$string['task:suspendunpaid'] = 'Suspendre les accès des échéances impayées';
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
$string['mfarecommended'] = 'Protégez votre compte formateur, qui décide où sont versées vos ventes : <a href="{$a}">activez la double authentification</a>. Un code de votre téléphone vous sera demandé à chaque connexion, en plus du mot de passe.';
$string['course:open'] = 'Ouvrir';
$string['course:settings'] = 'Paramètres';
$string['courses_intro'] = 'Créez vos formations, ajoutez vos leçons et vos vidéos, puis fixez leur prix pour les mettre en vente.';
$string['coursestatus:draft'] = 'Brouillon';
$string['coursestatus:hidden'] = 'Masquée aux apprenants';
$string['coursestatus:noprice'] = 'Sans prix';
$string['dashboard:hello'] = 'Bonjour {$a} !';
$string['dashboard:intro'] = 'Voici l\'activité de votre espace formateur.';
$string['dashboard:latestsales'] = 'Dernières ventes';
$string['dashboard:steps'] = 'Pour bien démarrer ({$a->done}/{$a->total})';
$string['learnerscount'] = '{$a} élève(s)';
$string['menu:dashboard'] = 'Tableau de bord';
$string['menu:videos'] = 'Ajouter des vidéos';
$string['seeall'] = 'Tout voir';
$string['stat:courses'] = 'formation(s), dont {$a} en vente';
$string['stat:learners'] = 'élève(s) inscrit(s)';
$string['stat:revenue'] = 'encaissés';
$string['stat:sales'] = 'vente(s)';
$string['step:course'] = 'Créer votre première formation';
$string['step:course_action'] = 'Créer une formation';
$string['step:mfa'] = 'Protéger votre compte par la double authentification';
$string['step:mfa_action'] = 'L\'activer';
$string['step:onsale'] = 'Mettre une formation en vente';
$string['step:onsale_action'] = 'Fixer les prix';
$string['step:payment'] = 'Configurer au moins un moyen de paiement (SecureAndPay, Stripe ou PayPal)';
$string['step:payment_action'] = 'Configurer les paiements';
$string['step:plan'] = 'Souscrire l\'abonnement formateur';
$string['step:plan_action'] = 'S\'abonner';
$string['step:profile'] = 'Compléter votre vitrine (accroche et présentation)';
$string['step:profile_action'] = 'Modifier ma vitrine';
$string['videos:intro'] = 'Deux façons d\'ajouter une vidéo à une leçon : la déposer sur Oriskill, ou intégrer une vidéo YouTube ou Vimeo.';
$string['videos:link'] = '<p>Pratique pour les vidéos très longues ou déjà en ligne.</p>
<ol>
<li>Publiez la vidéo sur YouTube en <strong>« Non répertoriée »</strong>, ou sur Vimeo en limitant son intégration au domaine oriskill.com (offre Vimeo payante).</li>
<li>Copiez l\'adresse de la vidéo.</li>
<li>Dans votre leçon (une <strong>Page</strong>), cliquez sur le bouton <strong>Multimédia</strong> de l\'éditeur et collez l\'adresse dans <strong>« Ou ajouter par URL »</strong>. Vous pouvez aussi coller l\'adresse seule sur une ligne : le lecteur s\'affiche automatiquement.</li>
</ol>
<p class="text-muted small">Attention : une vidéo YouTube « non répertoriée » reste visible par toute personne qui a le lien. Pour un contenu payant, préférez le dépôt sur Oriskill, ou Vimeo avec restriction de domaine.</p>';
$string['videos:link_title'] = 'Intégrer une vidéo YouTube ou Vimeo';
$string['videos:tips'] = '<ul>
<li><strong>Format</strong> : MP4 (vidéo H.264, son AAC), lu par tous les navigateurs et téléphones. Les autres formats (MOV, AVI…) peuvent ne pas être lus.</li>
<li><strong>Qualité et poids</strong> : 720p ou 1080p suffisent. Le logiciel gratuit HandBrake réduit fortement le poids d\'une vidéo sans perte visible (préréglage « Fast 1080p30 »). Limite par fichier : {$a->maxsize}.</li>
<li><strong>Durée</strong> : préférez des vidéos de 5 à 15 minutes, une par notion, plutôt qu\'une longue vidéo.</li>
<li><strong>Accessibilité</strong> : ajoutez des sous-titres (fichier .vtt) depuis le bouton Multimédia, et un court résumé écrit sous la vidéo.</li>
<li><strong>Suivi</strong> : dans les paramètres de la page, ajoutez la condition d\'achèvement « Afficher l\'activité » pour suivre la progression.</li>
</ul>';
$string['videos:tips_title'] = 'Conseils pour des vidéos réussies';
$string['videos:upload'] = '<p>Recommandé pour vos formations payantes : seuls vos apprenants inscrits peuvent voir la vidéo.</p>
<ol>
<li>Ouvrez votre formation et activez le <strong>Mode édition</strong> (en haut à droite).</li>
<li>Dans la section voulue, cliquez sur <strong>« Ajouter une activité ou ressource »</strong>, puis choisissez <strong>Page</strong>.</li>
<li>Dans le contenu de la page, cliquez sur le bouton <strong>Multimédia</strong> de l\'éditeur (icône de film), puis sur <strong>« Parcourir les dépôts… »</strong> et <strong>« Déposer un fichier »</strong> : choisissez votre vidéo.</li>
<li>Ajoutez un titre et quelques lignes d\'explication, puis cliquez sur <strong>« Enregistrer et afficher »</strong>.</li>
</ol>
<p class="text-muted small">Taille maximale par vidéo : {$a->maxsize}.</p>';
$string['videos:upload_title'] = 'Déposer la vidéo sur Oriskill';
$string['access_intro'] = 'Choisissez quand chaque module s\'ouvre et pour qui. Un module verrouillé reste visible par l\'élève, avec la condition à remplir pour l\'ouvrir.';
$string['accessremoved'] = 'Accès retiré.';
$string['backtolearners'] = 'Retour aux élèves de « {$a} »';
$string['course:access'] = 'Accès';
$string['course:learners'] = 'Élèves';
$string['error:groupname'] = 'Indiquez un nom de groupe.';
$string['error:newlearnername'] = 'Cette adresse n\'a pas encore de compte : indiquez le prénom et le nom de l\'élève pour le créer.';
$string['error:nopreviouscompletion'] = 'le module précédent n\'a aucune activité avec suivi d\'achèvement';
$string['error:purchasedaccess'] = 'Cet accès a été acheté : vous pouvez le suspendre, mais pas le retirer.';
$string['error:rules'] = 'Certaines règles n\'ont pas été enregistrées : {$a}';
$string['error:usernametaken'] = 'Un compte utilise déjà cet identifiant avec une autre adresse e-mail.';
$string['exportcsv'] = 'Exporter (CSV)';
$string['group:add'] = 'Ajouter le groupe';
$string['group:delete_confirm'] = 'Supprimer le groupe « {$a} » ? Les élèves gardent leur accès à la formation.';
$string['group:name_placeholder'] = 'Nom du nouveau groupe';
$string['groups_intro'] = 'Les groupes servent à réserver des modules à certains élèves (par exemple « Premium » ou « Session de mars ») et à les suivre ensemble. Placez un élève dans un groupe depuis la page Élèves.';
$string['learner'] = 'Élève';
$string['learner:access'] = 'Accès';
$string['learner:active'] = 'Actif';
$string['learner:completed'] = 'Terminée';
$string['learner:details'] = 'Fiche';
$string['learner:expired'] = 'Expiré';
$string['learner:lastaccess'] = 'Dernier accès';
$string['learner:nogroup'] = 'Aucun groupe';
$string['learner:progress'] = 'Progression';
$string['learner:reactivate'] = 'Réactiver';
$string['learner:remove'] = 'Retirer';
$string['learner:remove_confirm'] = 'Retirer l\'accès offert à {$a} ?';
$string['learner:since'] = 'Inscrit le';
$string['learner:sincedate'] = 'Inscrit le {$a}';
$string['learner:suspend'] = 'Suspendre';
$string['learner:suspended'] = 'Suspendu';
$string['learner:unlimited'] = 'Illimité';
$string['learner:until'] = 'Fin d\'accès';
$string['learner:until_help'] = 'Vide : accès illimité.';
$string['menu:access'] = 'Accès & déblocage';
$string['menu:learners'] = 'Élèves';
$string['module:locked'] = 'Verrouillé';
$string['module:open'] = 'Ouvert';
$string['nolearners'] = 'Aucun élève dans cette formation pour le moment.';
$string['nomodules'] = 'Cette formation n\'a pas encore de module.';
$string['offer_help'] = 'Si l\'élève n\'a pas encore de compte, indiquez son prénom et son nom : le compte est créé et ses identifiants lui sont envoyés par e-mail. Un accès offert ne passe pas par la boutique.';
$string['offeraccess'] = 'Offrir un accès';
$string['offered'] = 'Accès offert à {$a}.';
$string['offered:newaccount'] = 'Compte créé et accès offert à {$a} : ses identifiants lui ont été envoyés par e-mail.';
$string['rule:custom'] = 'Garder la règle personnalisée (définie dans Moodle)';
$string['rule:custom_edit'] = 'Voir la règle';
$string['rule:date'] = 'À partir du';
$string['rule:days'] = 'Après';
$string['rule:days_after'] = 'jour(s) d\'inscription';
$string['rule:groups'] = 'Réservé aux groupes';
$string['rule:groups_help'] = 'Aucune case cochée : tous les élèves.';
$string['rule:nogroups'] = 'Pour réserver ce module à certains élèves, créez d\'abord des groupes en bas de la page.';
$string['rule:open'] = 'Ouvert dès l\'inscription';
$string['rule:previous'] = 'Quand le module précédent est terminé';
$string['rule:previous_first'] = '(premier module)';
$string['rule:previous_none'] = '(le module précédent n\'a aucune activité avec suivi d\'achèvement)';
$string['rule:previous_stale'] = 'Des activités ont été ajoutées au module précédent : enregistrez pour les inclure.';
$string['rule:when'] = 'Quand le module s\'ouvre-t-il ?';
$string['source:offered'] = 'Offert';
$string['source:purchase'] = 'Acheté';
$string['stat:avgprogress'] = 'progression moyenne';
$string['stat:completed'] = 'formation(s) terminée(s)';
$string['stat:learnersactive'] = 'accès actif(s)';
$string['stat:learnerstotal'] = 'élève(s)';
$string['state:done'] = 'Terminé';
$string['state:failed'] = 'Non réussi';
$string['state:notracking'] = 'Sans suivi';
$string['state:todo'] = 'À faire';
$string['learner:grade'] = 'Note';
$string['picker:course'] = 'Formation';
$string['gw:enabled_desc'] = 'Vos acheteurs peuvent payer vos formations avec ce moyen.';
$string['gw:howto'] = 'Comment obtenir ces informations ?';
$string['gw:keep'] = 'Déjà enregistré : laissez vide pour le conserver.';
$string['gw:paypal:desc'] = 'Paiement par compte PayPal ou carte, versé sur votre compte PayPal Business.';
$string['gw:paypal:enabled'] = 'Proposer PayPal';
$string['gw:paypal:howto'] = '<ol>
<li>Connectez-vous sur <strong>developer.paypal.com</strong> avec votre compte PayPal Business, ouvrez <strong>Apps &amp; Credentials</strong> et créez une application (choisissez <em>Sandbox</em> pour tester, <em>Live</em> pour vendre).</li>
<li>Copiez le <strong>Client ID</strong> et le <strong>Secret</strong> de l\'application ci-dessous.</li>
<li>Dans l\'application, ajoutez un <strong>webhook</strong> avec l\'adresse :<br><code>{$a->webhook}</code><br>et les événements <code>CHECKOUT.ORDER.APPROVED</code>, <code>PAYMENT.CAPTURE.COMPLETED</code>, <code>PAYMENT.CAPTURE.DENIED</code> et <code>PAYMENT.CAPTURE.REFUNDED</code>.</li>
<li>Copiez le <strong>Webhook ID</strong> affiché par PayPal ci-dessous.</li>
</ol>';
$string['gw:paypal:publickey'] = 'Client ID';
$string['gw:paypal:publickey_invalid'] = 'Client ID non valide.';
$string['gw:paypal:secretkey'] = 'Secret';
$string['gw:paypal:secretkey_invalid'] = 'Secret non valide.';
$string['gw:paypal:testmode'] = 'Sandbox';
$string['gw:paypal:testmode_desc'] = 'Application PayPal « Sandbox » : aucun vrai paiement, pour tester.';
$string['gw:paypal:webhooksecret'] = 'Webhook ID';
$string['gw:paypal:webhooksecret_invalid'] = 'Webhook ID non valide.';
$string['gw:secureandpay:desc'] = 'Paiement par carte bancaire, versé sur votre compte SecureAndPay.';
$string['gw:stripe:desc'] = 'Paiement par carte bancaire (et Apple Pay / Google Pay selon votre compte), versé sur votre compte Stripe.';
$string['gw:stripe:enabled'] = 'Proposer Stripe';
$string['gw:stripe:howto'] = '<ol>
<li>Connectez-vous sur <strong>dashboard.stripe.com</strong>. Pour tester, activez le <strong>mode test</strong> (interrupteur en haut).</li>
<li>Dans <strong>Développeurs → Clés API</strong>, copiez la <strong>clé secrète</strong> (<code>sk_test_…</code> en test, <code>sk_live_…</code> en production) ci-dessous.</li>
<li>Dans <strong>Développeurs → Webhooks</strong>, ajoutez un endpoint avec l\'adresse :<br><code>{$a->webhook}</code><br>et les événements <code>checkout.session.completed</code>, <code>charge.succeeded</code>, <code>charge.failed</code> et <code>charge.refunded</code>.</li>
<li>Ouvrez l\'endpoint créé et copiez son <strong>secret de signature</strong> (<code>whsec_…</code>) ci-dessous.</li>
</ol>';
$string['gw:stripe:publickey'] = 'Clé publiable (facultative)';
$string['gw:stripe:publickey_invalid'] = 'Clé publiable attendue : pk_test_… en mode test, pk_live_… sinon.';
$string['gw:stripe:secretkey'] = 'Clé secrète';
$string['gw:stripe:secretkey_invalid'] = 'Clé secrète attendue : sk_test_… (ou rk_test_…) en mode test, sk_live_… (ou rk_live_…) sinon.';
$string['gw:stripe:testmode'] = 'Mode test';
$string['gw:stripe:testmode_desc'] = 'Clés de test Stripe : aucun vrai paiement, pour tester.';
$string['gw:stripe:webhooksecret'] = 'Secret de signature du webhook';
$string['gw:stripe:webhooksecret_invalid'] = 'Le secret de signature commence par whsec_.';
$string['gwstate:incomplete'] = 'Incomplet';
$string['gwstate:off'] = 'Désactivé';
$string['gwstate:on'] = 'Actif';
$string['navgroup:account'] = 'Paramètres';
$string['navgroup:courses'] = 'Formations';
$string['navgroup:learners'] = 'Suivi des élèves';
$string['navgroup:overview'] = 'Pilotage';
$string['navgroup:public'] = 'En ligne';

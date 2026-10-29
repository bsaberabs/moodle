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
$string['stat:learners'] = 'apprenant(s) inscrit(s)';
$string['stat:revenue'] = 'encaissés';
$string['stat:sales'] = 'vente(s)';
$string['step:course'] = 'Créer votre première formation';
$string['step:course_action'] = 'Créer une formation';
$string['step:mfa'] = 'Protéger votre compte par la double authentification';
$string['step:mfa_action'] = 'L\'activer';
$string['step:onsale'] = 'Mettre une formation en vente';
$string['step:onsale_action'] = 'Fixer les prix';
$string['step:payment'] = 'Renseigner votre compte de paiement SecureAndPay';
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

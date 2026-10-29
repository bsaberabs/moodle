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
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cancelinsecureandpay_body'] = 'L\'abonnement de {$a->user} ({$a->email}) a été résilié sur Oriskill, mais SecureAndPay n\'a pas confirmé l\'arrêt des prélèvements (réponse : {$a->answer}). Arrêtez l\'abonnement dont l\'orderID est {$a->reference} dans le back-office SecureAndPay.';
$string['cancelinsecureandpay_subject'] = 'Action requise : arrêter l\'abonnement SecureAndPay {$a->reference}';
$string['cancelledatsecureandpay'] = 'Abonnement arrêté chez SecureAndPay ; l\'accès est conservé jusqu\'à la fin de la période payée.';
$string['cardwillbesaved'] = 'Votre carte sera conservée de façon sécurisée par SecureAndPay (Oriskill n\'y a jamais accès), pour les prochaines échéances ou vos prochains achats.';
$string['continuetopayment'] = 'Continuer vers le paiement';
$string['invalidamount'] = 'Le montant de la commande n\'est pas valide pour SecureAndPay.';
$string['invalidnotification'] = 'Notification SecureAndPay non valide.';
$string['invalidsignature'] = 'Signature SecureAndPay non valide.';
$string['lastpaymentreceived'] = 'Dernière des {$a} échéances payée : l\'abonnement se termine à la fin de cette période.';
$string['notconfigured'] = 'SecureAndPay n\'est pas configuré : saisissez l\'identifiant du site et la clé dans les paramètres de la passerelle de paiement.';
$string['oneclick'] = 'Paiement en un clic (upsell)';
$string['oneclick_desc'] = 'Le premier achat enregistre la carte de l\'acheteur chez SecureAndPay ; les achats suivants la réutilisent, l\'acheteur n\'a plus qu\'à confirmer le paiement. L\'acheteur est informé avant de payer que sa carte sera conservée.';
$string['paymentrefused'] = 'Cette commande ne peut pas être payée : {$a}';
$string['planpayments'] = 'Nombre de prélèvements par formule';
$string['planpayments_cycle_monthly'] = 'mois';
$string['planpayments_cycle_yearly'] = 'an';
$string['planpayments_fixed'] = '{$a->payments} prélèvements de {$a->price}, un par {$a->cycle}, puis l\'accès s\'arrête.';
$string['planpayments_intro'] = 'Pour chaque formule d\'abonnement payée avec SecureAndPay, indiquez le nombre de prélèvements : laissé vide, l\'abonnement est prélevé jusqu\'à sa résiliation ; après le dernier prélèvement, l\'accès s\'arrête à la fin de la période payée. Pour une formule avec essai, le montant de l\'essai est payé à l\'inscription (vide ou 0 : essai gratuit, ou un montant symbolique comme 1 €) ; il ne compte pas parmi les prélèvements. Les changements valent pour les nouveaux abonnements : les échéanciers déjà en cours chez SecureAndPay ne changent pas.';
$string['planpayments_invalid'] = 'Valeur non valide pour : {$a}. Le nombre de prélèvements est un entier de 1 à 999 (ou vide) ; le montant de l\'essai va de 0 au prix de la formule.';
$string['planpayments_none'] = 'Aucune formule d\'abonnement pour le moment.';
$string['planpayments_number'] = 'Nombre de prélèvements';
$string['planpayments_plan'] = 'Formule';
$string['planpayments_price'] = 'Prix';
$string['planpayments_summary'] = 'Ce que paie le client';
$string['planpayments_trial'] = 'Essai gratuit';
$string['planpayments_trialprice'] = 'Montant de l\'essai';
$string['planpayments_unlimited'] = '{$a->price} par {$a->cycle}, jusqu\'à la résiliation.';
$string['planpayments_unlimited_short'] = 'Illimité';
$string['planpayments_withpaidtrial'] = '{$a->trial} jours pour {$a->trialprice}, puis';
$string['planpayments_withtrial'] = '{$a->trial} jours gratuits, puis';
$string['pluginname'] = 'SecureAndPay';
$string['privacy:metadata:preference:card'] = 'Référence (jeton) de la carte conservée par SecureAndPay pour le paiement en un clic, et sa date d\'expiration.';
$string['privacy:metadata:secureandpay'] = 'Pour encaisser le paiement, les informations de l\'acheteur et de la commande sont envoyées à SecureAndPay.';
$string['privacy:metadata:secureandpay:address'] = 'Adresse postale.';
$string['privacy:metadata:secureandpay:email'] = 'Adresse e-mail.';
$string['privacy:metadata:secureandpay:firstname'] = 'Prénom.';
$string['privacy:metadata:secureandpay:lastname'] = 'Nom.';
$string['privacy:metadata:secureandpay:order'] = 'Référence de la commande, montant et articles achetés.';
$string['privacy:metadata:secureandpay:phone'] = 'Numéro de téléphone.';
$string['redirecting'] = 'Vous êtes redirigé vers la page de paiement sécurisée SecureAndPay…';
$string['savedcardexpiry'] = 'Carte enregistrée chez SecureAndPay, expire en {$a} (AAMM)';
$string['schedulenotstopped'] = 'SecureAndPay n\'a pas confirmé l\'arrêt de l\'échéancier {$a->reference} (réponse : {$a->answer}) : les administrateurs ont été prévenus.';
$string['schedulestopped'] = 'Échéancier {$a->reference} arrêté chez SecureAndPay.';
$string['trialorder'] = '{$a} — essai gratuit';
$string['trialwithcard'] = 'Essais gratuits avec carte enregistrée';
$string['trialwithcard_desc'] = 'L\'essai gratuit d\'une formule commence par l\'enregistrement de la carte chez SecureAndPay pour 0 € ; SecureAndPay prélève le prix de la formule à la fin de l\'essai. Si désactivé, l\'essai démarre sans moyen de paiement et se termine sans être converti.';
$string['unsupportedcurrency'] = 'SecureAndPay n\'accepte pas la devise {$a}. Utilisez EUR, USD ou GBP.';

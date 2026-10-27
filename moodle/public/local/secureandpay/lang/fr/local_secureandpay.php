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

$string['cancelinsecureandpay_body'] = 'L\'abonnement de {$a->user} ({$a->email}) a été résilié dans Moodle. SecureAndPay continuera de le prélever tant qu\'il n\'est pas arrêté dans le back-office SecureAndPay : arrêtez l\'abonnement dont l\'orderID est {$a->reference}.';
$string['cancelinsecureandpay_subject'] = 'Action requise : arrêter l\'abonnement SecureAndPay {$a->reference}';
$string['cancelledatsecureandpay'] = 'Abonnement arrêté chez SecureAndPay ; l\'accès est conservé jusqu\'à la fin de la période payée.';
$string['cardwillbesaved'] = 'Votre carte sera conservée de façon sécurisée par SecureAndPay (Moodle n\'y a jamais accès), pour les prochaines échéances ou vos prochains achats.';
$string['continuetopayment'] = 'Continuer vers le paiement';
$string['invalidamount'] = 'Le montant de la commande n\'est pas valide pour SecureAndPay.';
$string['invalidnotification'] = 'Notification SecureAndPay non valide.';
$string['invalidsignature'] = 'Signature SecureAndPay non valide.';
$string['notconfigured'] = 'SecureAndPay n\'est pas configuré : saisissez l\'identifiant du site et la clé dans les paramètres de la passerelle de paiement.';
$string['oneclick'] = 'Paiement en un clic (upsell)';
$string['oneclick_desc'] = 'Le premier achat enregistre la carte de l\'acheteur chez SecureAndPay ; les achats suivants la réutilisent, l\'acheteur n\'a plus qu\'à confirmer le paiement. L\'acheteur est informé avant de payer que sa carte sera conservée.';
$string['pluginname'] = 'SecureAndPay';
$string['privacy:metadata:secureandpay'] = 'Pour encaisser le paiement, les informations de l\'acheteur et de la commande sont envoyées à SecureAndPay.';
$string['privacy:metadata:secureandpay:address'] = 'Adresse postale.';
$string['privacy:metadata:secureandpay:email'] = 'Adresse e-mail.';
$string['privacy:metadata:secureandpay:firstname'] = 'Prénom.';
$string['privacy:metadata:secureandpay:lastname'] = 'Nom.';
$string['privacy:metadata:secureandpay:order'] = 'Référence de la commande, montant et articles achetés.';
$string['privacy:metadata:secureandpay:phone'] = 'Numéro de téléphone.';
$string['privacy:metadata:preference:card'] = 'Référence (jeton) de la carte conservée par SecureAndPay pour le paiement en un clic, et sa date d\'expiration.';
$string['redirecting'] = 'Vous êtes redirigé vers la page de paiement sécurisée SecureAndPay…';
$string['savedcardexpiry'] = 'Carte enregistrée chez SecureAndPay, expire en {$a} (AAMM)';
$string['trialorder'] = '{$a} — essai gratuit';
$string['trialwithcard'] = 'Essais gratuits avec carte enregistrée';
$string['trialwithcard_desc'] = 'L\'essai gratuit d\'une formule commence par l\'enregistrement de la carte chez SecureAndPay pour 0 € ; SecureAndPay prélève le prix de la formule à la fin de l\'essai. Si désactivé, l\'essai démarre sans moyen de paiement et se termine sans être converti.';
$string['unsupportedcurrency'] = 'SecureAndPay n\'accepte pas la devise {$a}. Utilisez EUR, USD ou GBP.';

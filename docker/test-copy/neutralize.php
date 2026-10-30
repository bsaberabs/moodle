<?php
// Copie de test, après chaque recopie de la production : bandeau visible sur toutes les pages, comptes Stripe /
// PayPal réels des formateurs retirés (SecureAndPay passe tout seul en mode test, voir MOODLE_STAGING).
define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');

if (empty($CFG->oriskill_staging)) {
    fwrite(STDERR, "MOODLE_STAGING absent : ce n'est pas la copie de test, rien n'est fait.\n");
    exit(1);
}
set_config('additionalhtmltopofbody', '<div style="background:#b91c1c;color:#fff;text-align:center;padding:6px 12px;' .
    'font:600 14px/1.4 system-ui,sans-serif;position:relative;z-index:2000">COPIE DE TEST d\'Oriskill : rien n\'est réel ici ' .
    '(paiements en mode test, e-mails retenus dans la boîte de test). ' .
    '<a href="/mailpit/" style="color:#fff;text-decoration:underline">Voir les e-mails envoyés</a></div>');
$removed = $DB->count_records('local_oriskill_gateways', ['testmode' => 0]);
$DB->delete_records('local_oriskill_gateways', ['testmode' => 0]);
purge_all_caches();
echo "copie de test prête (comptes Stripe/PayPal réels retirés : $removed)\n";

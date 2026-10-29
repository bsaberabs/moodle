<?php
// Réglages de sécurité et RGPD, une seule fois : ensuite, l'administrateur peut les changer dans
// Administration du site, ils ne sont plus touchés.
//
// - Connexion : compte bloqué 30 minutes après 10 échecs en 30 minutes (le message d'erreur reste neutre et
//   « Mot de passe oublié » ne révèle pas si l'adresse est inscrite : réglages Moodle par défaut).
// - Application mobile : l'application Moodle officielle (gratuite) se connecte à la plateforme.
// - RGPD : chaque utilisateur peut demander l'export ou la suppression de ses données depuis son profil ;
//   l'export est approuvé automatiquement, la suppression reste validée par un administrateur.
define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');
require_once($CFG->libdir . '/adminlib.php');

if (get_config('local_oriskill', 'securityconfigured')) {
    exit(0);
}
set_config('lockoutthreshold', 10);
set_config('lockoutwindow', 30 * MINSECS);
set_config('lockoutduration', 30 * MINSECS);

(new admin_setting_enablemobileservice('enablemobilewebservice', '', '', 0))->write_setting('1');

set_config('contactdataprotectionofficer', 1, 'tool_dataprivacy');
set_config('automaticdataexportapproval', 1, 'tool_dataprivacy');

set_config('securityconfigured', 1, 'local_oriskill');
echo "[moodle] sécurité : blocage après 10 échecs, application mobile, demandes RGPD (export et suppression)\n";

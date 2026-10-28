<?php
// Active la double authentification (2FA), une seule fois : ensuite, les réglages de
// Administration > Plugins > Authentification multifacteur restent ceux de l'administrateur.
//
// Chaque utilisateur l'active pour son compte (Préférences > Authentification multifacteur) avec une
// application d'authentification ou une clé de sécurité / clé d'accès. Dès qu'elle est activée, elle lui est
// demandée à chaque connexion ; un compte sans 2FA se connecte comme avant (facteur « MFA facultative »).
// Le code par e-mail, actif par défaut dans Moodle, est désactivé : il serait demandé à tous les clients.
define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');

use tool_mfa\plugininfo\factor;

if (get_config('local_oriskill', 'mfaconfigured')) {
    exit(0);
}
factor::enable_plugin('email', 0);
foreach (['totp', 'webauthn', 'nosetup'] as $name) {
    factor::enable_plugin($name, 1);
}
set_config('enabled', 1, 'tool_mfa');
set_config('mfaconfigured', 1, 'local_oriskill');
echo "[moodle] double authentification activée (application ou clé de sécurité, au choix de chaque utilisateur)\n";

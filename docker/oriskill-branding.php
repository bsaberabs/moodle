<?php
// Retire la marque Moodle des écrans vus par les utilisateurs, à chaque démarrage.
//
// 1. Textes : surcharge locale des chaînes de Moodle (paquets fr_local et en_local, le mécanisme officiel de
//    « Personnalisation de la langue »). Seules les chaînes absentes sont ajoutées : une chaîne modifiée par un
//    administrateur dans Personnalisation de la langue est conservée.
// 2. Logos : le logo Oriskill (docker/branding/) devient le logo du site (boutique, e-mails, page de connexion),
//    le logo compact (barre de navigation) et l'icône d'onglet. Un fichier déposé par un administrateur dans
//    Apparence > Logos n'est jamais remplacé : seuls une zone vide ou un fichier posé par ce script le sont.
define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');

$strings = [
    'fr' => [
        'loginwelcometitle' => 'Bienvenue sur Oriskill',
        'loginpaneltagline' => 'Des formations pratiques en IA, numérique et compétences pro',
        'loginstatusers_value' => '24 h/24',
        'loginstatusers' => 'accès à vos formations',
        'loginstatsites_value' => '100 %',
        'loginstatsites' => 'en ligne, à votre rythme',
        'loginstatcountries_value' => 'Certificat',
        'loginstatcountries' => 'de réussite',
        'poweredbymoodle' => '© Oriskill',
        'cookiesenabled_help' => 'Ce site utilise deux cookies.

Le cookie de session est indispensable : il vous garde connecté pendant votre navigation. Il est supprimé lors de la déconnexion ou de la fermeture du navigateur.

Le second, facultatif, mémorise votre nom d’utilisateur pour pré-remplir le formulaire de connexion.',
        'cookiesenabled_help_html' => 'Ce site utilise deux cookies.<br/><br/>Le cookie de session est indispensable : il vous garde connecté pendant votre navigation. Il est supprimé lors de la déconnexion ou de la fermeture du navigateur.<br/><br/>Le second, facultatif, mémorise votre nom d’utilisateur pour pré-remplir le formulaire de connexion.',
        'cookiesenabledonlysession_help' => 'Ce site utilise un cookie de session, indispensable pour rester connecté pendant votre navigation. Il est supprimé lors de la déconnexion ou de la fermeture du navigateur.',
    ],
    'en' => [
        'loginwelcometitle' => 'Welcome to Oriskill',
        'loginpaneltagline' => 'Practical courses in AI, digital and professional skills',
        'loginstatusers_value' => '24/7',
        'loginstatusers' => 'access to your courses',
        'loginstatsites_value' => '100%',
        'loginstatsites' => 'online, at your own pace',
        'loginstatcountries_value' => 'Certificate',
        'loginstatcountries' => 'of completion',
        'poweredbymoodle' => '© Oriskill',
        'cookiesenabled_help' => 'This site uses two cookies.

The session cookie is essential: it keeps you logged in while you browse. It is deleted when you log out or close your browser.

The second one is optional: it remembers your username to pre-fill the login form.',
        'cookiesenabled_help_html' => 'This site uses two cookies.<br/><br/>The session cookie is essential: it keeps you logged in while you browse. It is deleted when you log out or close your browser.<br/><br/>The second one is optional: it remembers your username to pre-fill the login form.',
        'cookiesenabledonlysession_help' => 'This site uses a session cookie, essential to stay logged in while you browse. It is deleted when you log out or close your browser.',
    ],
];

$changed = 0;
foreach ($strings as $lang => $overrides) {
    $dir = $CFG->langlocalroot . '/' . $lang . '_local';
    $file = $dir . '/moodle.php';
    $string = [];
    if (is_readable($file)) {
        include($file);
    }
    $missing = array_diff_key($overrides, $string);
    if (!$missing) {
        continue;
    }
    $string = $string + $missing;
    ksort($string);
    check_dir_exists($dir);
    $php = "<?php\n// Personnalisation locale des chaînes de Moodle (Oriskill).\n\ndefined('MOODLE_INTERNAL') || die();\n\n";
    foreach ($string as $key => $value) {
        $php .= '$string[' . var_export($key, true) . '] = ' . var_export($value, true) . ";\n";
    }
    file_put_contents($file, $php);
    $changed += count($missing);
}
if ($changed) {
    get_string_manager()->reset_caches();
    echo "[moodle] marque Oriskill : $changed texte(s) personnalisé(s)\n";
}

// Zone de Apparence > Logos => fichier fourni. L'empreinte de chaque fichier posé est retenue (local_oriskill,
// brandfile_<zone>) pour le reconnaître et le mettre à jour quand le logo change.
$brandfiles = [
    'logo' => 'oriskill-logo.png',
    // Barre de navigation : le logo horizontal complet reste lisible à sa hauteur (environ 35 px)
    'logocompact' => 'oriskill-logo.png',
    'favicon' => 'oriskill-icon.png',
];
$fs = get_file_storage();
$context = context_system::instance();
$installed = [];
foreach ($brandfiles as $area => $filename) {
    $source = __DIR__ . '/branding/' . $filename;
    if (!is_readable($source)) {
        continue;
    }
    $current = $fs->get_area_files($context->id, 'core_admin', $area, 0, 'sortorder, id', false);
    $current = reset($current);
    $mine = get_config('local_oriskill', 'brandfile_' . $area);
    // L'icône provisoire des versions précédentes de ce script (favicon.png, sans empreinte) est remplacée aussi.
    $provisional = $area === 'favicon' && $mine === false && $current && $current->get_filename() === 'favicon.png';
    if ($current && $current->get_contenthash() !== $mine && !$provisional) {
        continue;
    }
    $contenthash = sha1_file($source);
    if ($current && $current->get_contenthash() === $contenthash && $current->get_filename() === $filename) {
        continue;
    }
    $fs->delete_area_files($context->id, 'core_admin', $area);
    $file = $fs->create_file_from_pathname(['contextid' => $context->id, 'component' => 'core_admin',
        'filearea' => $area, 'itemid' => 0, 'filepath' => '/', 'filename' => $filename], $source);
    set_config($area, '/' . $filename, 'core_admin');
    set_config('brandfile_' . $area, $file->get_contenthash(), 'local_oriskill');
    $installed[] = $area;
}
// Page de connexion : le logo occuperait toute la largeur du formulaire. Ajouté une seule fois au SCSS du thème,
// à la suite de ce qu'un administrateur y a saisi.
$scssmarker = '/* oriskill-logo */';
$scss = (string) get_config('theme_boost', 'scss');
if (!str_contains($scss, $scssmarker)) {
    set_config('scss', ltrim($scss . "\n" . $scssmarker . "\n#logoimage { max-height: 72px; width: auto; }\n"), 'theme_boost');
    $installed[] = 'taille sur la page de connexion';
}
if ($installed) {
    theme_reset_all_caches();
    echo "[moodle] marque Oriskill : logo installé (" . implode(', ', $installed) . ")\n";
}

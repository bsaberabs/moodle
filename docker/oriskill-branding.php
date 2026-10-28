<?php
// Retire la marque Moodle des écrans vus par les utilisateurs, à chaque démarrage.
//
// 1. Textes : surcharge locale des chaînes de Moodle (paquets fr_local et en_local, le mécanisme officiel de
//    « Personnalisation de la langue »). Seules les chaînes absentes sont ajoutées : une chaîne modifiée par un
//    administrateur dans Personnalisation de la langue est conservée.
// 2. Icône d'onglet : un monogramme Oriskill provisoire remplace le « M » de Moodle, seulement si aucune icône
//    n'a été déposée dans Apparence > Logos.
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

// Icône d'onglet provisoire : un « O » blanc sur le violet de la boutique (#7c3aed).
if ((string) get_config('core_admin', 'favicon') === '' && function_exists('imagecreatetruecolor')) {
    $size = 128;
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    $violet = imagecolorallocate($img, 0x7c, 0x3a, 0xed);
    $white = imagecolorallocate($img, 255, 255, 255);
    $r = 28;
    imagefilledrectangle($img, $r, 0, $size - $r, $size, $violet);
    imagefilledrectangle($img, 0, $r, $size, $size - $r, $violet);
    foreach ([[$r, $r], [$size - $r, $r], [$r, $size - $r], [$size - $r, $size - $r]] as [$cx, $cy]) {
        imagefilledellipse($img, $cx, $cy, 2 * $r, 2 * $r, $violet);
    }
    imagefilledellipse($img, $size / 2, $size / 2, 82, 82, $white);
    imagefilledellipse($img, $size / 2, $size / 2, 46, 46, $violet);
    ob_start();
    imagepng($img);
    $png = ob_get_clean();

    $fs = get_file_storage();
    $context = context_system::instance();
    $fs->delete_area_files($context->id, 'core_admin', 'favicon');
    $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'core_admin', 'filearea' => 'favicon',
        'itemid' => 0, 'filepath' => '/', 'filename' => 'favicon.png'], $png);
    set_config('favicon', '/favicon.png', 'core_admin');
    theme_reset_all_caches();
    echo "[moodle] marque Oriskill : icône d'onglet provisoire installée\n";
}

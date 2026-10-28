<?php
// Applique le contenu français Oriskill (oriskill-storefront-fr.json) à la vitrine ModernCommerce.
// ModernCommerce crée ses widgets avec un texte de démonstration anglais à l'installation. Seuls les
// widgets et diapositives jamais modifiés par un administrateur (timemodified = timecreated) sont mis
// à jour, et seuls leurs champs de texte : couleurs, liens et images restent ceux d'origine.
define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');

$dbman = $DB->get_manager();
if (!$dbman->table_exists('local_moderncommerce_widget')) {
    exit(0);
}
$content = json_decode((string) file_get_contents(__DIR__ . '/oriskill-storefront-fr.json'), true);

/**
 * Replace the values at the given paths, only where the path already exists.
 *
 * @param array $settings Widget settings.
 * @param array $values Path ("a/0/b") => value.
 * @return array
 */
function oriskill_apply_paths(array $settings, array $values): array {
    foreach ($values as $path => $value) {
        $node = &$settings;
        foreach (explode('/', $path) as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                unset($node);
                continue 2;
            }
            $node = &$node[$part];
        }
        $node = $value;
        unset($node);
    }
    return $settings;
}

$updated = 0;
foreach ($DB->get_records('local_moderncommerce_widget') as $widget) {
    $key = "{$widget->pagetype}|{$widget->zone}|{$widget->type}|{$widget->sortorder}";

    // Diapositives d'une bannière : traitées même si la bannière elle-même a été réglée.
    foreach ($content['slides'][$key] ?? [] as $text) {
        $slide = $DB->get_record('local_moderncommerce_widget_slide',
            ['instanceid' => $widget->id, 'sortorder' => $text['sortorder']]);
        if (!$slide || (int) $slide->timemodified !== (int) $slide->timecreated || $slide->heading === $text['heading']) {
            continue;
        }
        $DB->update_record('local_moderncommerce_widget_slide', (object) ['id' => $slide->id,
            'heading' => $text['heading'], 'subheading' => $text['subheading'], 'ctalabel' => $text['ctalabel']]);
        $updated++;
    }

    $text = $content['widgets'][$key] ?? null;
    if (!$text || (int) $widget->timemodified !== (int) $widget->timecreated) {
        continue;
    }
    $settings = json_decode((string) $widget->settings, true) ?: [];
    $update = [
        'title' => $text['title'] ?? $widget->title,
        'subtitle' => $text['subtitle'] ?? $widget->subtitle,
        'settings' => json_encode(oriskill_apply_paths($settings, $text['settings'] ?? []),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        // Exemples de personnes fictives (témoignages, formateurs, dirigeant) : masqués jusqu'à un vrai contenu.
        'enabled' => !empty($text['disable']) ? 0 : (int) $widget->enabled,
    ];
    if ($update['title'] === $widget->title && $update['subtitle'] === $widget->subtitle
            && json_decode($update['settings'], true) == $settings && $update['enabled'] === (int) $widget->enabled) {
        continue;
    }
    $DB->update_record('local_moderncommerce_widget', (object) (['id' => $widget->id] + $update));
    $updated++;
}

// Menu du haut : « Tableau de bord » (core) mène au même espace apprenant que « Mon apprentissage » (ajouté par
// ModernCommerce). Masqué une seule fois : le réglage reste ensuite celui de l'administrateur.
if (!get_config('local_oriskill', 'navconfigured')) {
    if ((string) get_config('local_moderncommerce', 'hideprimarynavitems') === '') {
        set_config('hideprimarynavitems', 'myhome', 'local_moderncommerce');
        $updated++;
    }
    set_config('navconfigured', 1, 'local_oriskill');
}

if ($updated > 0) {
    cache_helper::purge_all();
    echo "[moodle] vitrine Oriskill en français : $updated élément(s) mis à jour\n";
}

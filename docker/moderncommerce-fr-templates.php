<?php
// Met en français les modèles d'e-mails ModernCommerce enregistrés en base.
// - modèles issus des chaînes de langue (emailtpl_*, subscriptiontpl_*) : relus en langue fr ;
// - modèles dont le texte anglais est écrit en dur dans demo_seed.php : texte français ci-dessous.
// Seuls les modèles jamais modifiés par un administrateur (timemodified = timecreated) sont touchés,
// ce qui permet de lancer ce script à chaque démarrage.
define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');

if (!$DB->get_manager()->table_exists('local_moderncommerce_emailtpl')) {
    exit(0);
}

force_current_language('fr');
$sm = get_string_manager();
$updated = $skipped = $nostring = 0;

foreach ($DB->get_records('local_moderncommerce_emailtpl') as $tpl) {
    if ((int)$tpl->timemodified !== (int)$tpl->timecreated) {
        $skipped++;
        continue;
    }
    $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($tpl->template_key));
    $prefix = null;
    foreach (['emailtpl_' . $slug . '_', 'subscriptiontpl_' . $slug . '_'] as $candidate) {
        if ($sm->string_exists($candidate . 'subject', 'local_moderncommerce')) {
            $prefix = $candidate;
            break;
        }
    }
    if ($prefix === null) {
        $nostring++;
        continue;
    }
    $update = (object)['id' => $tpl->id];
    foreach (['name', 'subject', 'body'] as $field) {
        if ($sm->string_exists($prefix . $field, 'local_moderncommerce')) {
            $value = get_string($prefix . $field, 'local_moderncommerce');
            if ($value !== $tpl->$field) {
                $update->$field = $value;
            }
        }
    }
    if (count((array)$update) > 1) {
        // Garder timemodified = timecreated : le modèle reste considéré comme « d'origine ».
        $DB->update_record('local_moderncommerce_emailtpl', $update);
        $updated++;
    }
}

$fr = [
    'moderncommerce_purchase_student_confirmation' => [
        'Confirmation d\'achat (apprenant)',
        'Votre commande n°{order_number} est confirmée',
        '<h2>Merci pour votre achat, {firstname} !</h2><p>Votre commande <strong>n°{order_number}</strong> du {order_date} pour <strong>{course_name}</strong> est confirmée.</p><p>Montant : <strong>{order_total}</strong></p><p>Votre reçu est disponible <a href="{order_view_link}">ici</a>.</p><p>Bon apprentissage,<br>{sitename}</p>',
    ],
    'moderncommerce_purchase_instructor_notification' => [
        'Nouvelle inscription (formateur)',
        'Nouvelle inscription : {fullname} dans {course_name}',
        '<h2>Nouvelle inscription</h2><p>{fullname} s\'est inscrit à <strong>{course_name}</strong>.</p><p>Commande <strong>n°{order_number}</strong> du {order_date}.</p><p><a href="{course_link}">Voir le cours</a></p>',
    ],
    'moderncommerce_purchase_admin_high_value' => [
        'Commande importante (administrateur)',
        'Commande importante n°{order_number} — {order_total}',
        '<h2>Commande d\'un montant élevé</h2><p>Montant de la commande <strong>n°{order_number}</strong> : <strong>{order_total}</strong>.</p><p>Apprenant : {fullname} — Cours : {course_name} — Date : {order_date}</p><p><a href="{order_view_link}">Examiner la commande</a></p>',
    ],
    'moderncommerce_enrollment_student_confirmation' => [
        'Confirmation d\'inscription (apprenant)',
        'Vous êtes inscrit à {course_name}',
        '<h2>Bienvenue dans {course_name}</h2><p>Bonjour {firstname}, vous êtes maintenant inscrit.</p><p>Date d\'inscription : {enrollment_date}</p><p><a href="{course_link}">Accéder au cours</a></p>',
    ],
    'moderncommerce_enrollment_welcome' => [
        'E-mail de bienvenue dans le cours',
        'Bienvenue dans {course_name}',
        '<div style="text-align:center;margin-bottom:20px;"><img src="{logo}" alt="{sitename}" style="max-width:200px;height:auto;" /></div><h2 style="color:#333;">Bienvenue dans {course_name} !</h2><p>Bonjour {firstname},</p><p>Félicitations ! Vous êtes bien inscrit à <strong>{course_name}</strong>. Nous sommes ravis de vous compter parmi nos apprenants.</p><div style="text-align:center;margin:30px 0;"><a href="{course_link}" style="display:inline-block;padding:15px 40px;background:#667eea;color:#fff;text-decoration:none;border-radius:5px;font-weight:bold;">Accéder à mon cours</a></div><p>Pour toute question, écrivez-nous à <a href="mailto:{supportemail}">{supportemail}</a>.</p><p>Cordialement,<br><strong>L\'équipe {sitename}</strong></p>',
    ],
    'moderncommerce_refund_student_processed' => [
        'Remboursement effectué (apprenant)',
        'Remboursement effectué pour {course_name}',
        '<h2>Votre remboursement a été effectué</h2><p>Cours : <strong>{course_name}</strong></p><p>Montant : <strong>{refund_amount}</strong></p><p>Date : {refund_date} — Commande n°{order_number}</p><p>Le montant peut mettre quelques jours à apparaître sur votre compte.</p>',
    ],
    'subscription_activation' => [
        'Abonnement activé',
        '{sitename} : abonnement activé',
        '<h2>Votre abonnement est actif</h2><p>Bonjour {firstname}, votre formule <strong>{plan_name}</strong> ({billing_cycle}) est maintenant active.</p><p>Fin de l\'accès : {subscription_enddate}</p><pre>{courses_list}</pre>',
    ],
    'contact_autoreply' => [
        'Réponse automatique au contact',
        'Nous avons bien reçu votre message — {sitename}',
        '<h2>Merci de nous avoir contactés, {fullname} !</h2><p>Nous avons bien reçu votre message et vous répondrons dans les meilleurs délais.</p><p><strong>Votre message :</strong></p><blockquote>{message}</blockquote><p>Cordialement,<br>{sitename}</p>',
    ],
    'contact_admin_notify' => [
        'Notification de contact (administrateur)',
        'Nouveau message de contact de {fullname}',
        '<h2>Nouveau message de contact</h2><p><strong>De :</strong> {fullname} ({email})</p><p><strong>Sujet :</strong> {subject}</p><p><strong>Message :</strong></p><blockquote>{message}</blockquote>',
    ],
];

$done = 0;
foreach ($fr as $key => [$name, $subject, $body]) {
    $tpl = $DB->get_record('local_moderncommerce_emailtpl', ['template_key' => $key]);
    if (!$tpl || (int)$tpl->timemodified !== (int)$tpl->timecreated || $tpl->subject === $subject) {
        continue;
    }
    // Ne remplacer que si le modèle d'origine utilise les mêmes variables que la traduction.
    preg_match_all('/\{[a-z_]+\}/', $tpl->subject . $tpl->body, $before);
    preg_match_all('/\{[a-z_]+\}/', $subject . $body, $after);
    sort($before[0]);
    sort($after[0]);
    if ($before[0] !== $after[0]) {
        echo "[moodle] modèle $key : variables différentes de la traduction, laissé tel quel\n";
        continue;
    }
    $DB->update_record('local_moderncommerce_emailtpl', (object)['id' => $tpl->id, 'name' => $name, 'subject' => $subject, 'body' => $body]);
    $done++;
}

if ($updated + $done > 0) {
    cache_helper::purge_all();
    echo "[moodle] modèles d'e-mails passés en français : " . ($updated + $done) . "\n";
}

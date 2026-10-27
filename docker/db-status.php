<?php
// Affiche l'état de la base : "down", "empty" (Moodle non installé) ou "installed".

mysqli_report(MYSQLI_REPORT_OFF);
$db = mysqli_init();
$db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
$ok = @$db->real_connect(
    getenv('MOODLE_DB_HOST') ?: 'db',
    getenv('MOODLE_DB_USER') ?: 'moodle',
    getenv('MOODLE_DB_PASSWORD') ?: '',
    getenv('MOODLE_DB_NAME') ?: 'moodle',
    (int) (getenv('MOODLE_DB_PORT') ?: 3306)
);

if (!$ok) {
    echo "down";
    exit(0);
}

$result = $db->query("SHOW TABLES LIKE 'mdl\\_config'");
echo ($result && $result->num_rows > 0) ? "installed" : "empty";

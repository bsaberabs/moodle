<?php
// Configuration Moodle lue depuis les variables d'environnement du conteneur.

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('MOODLE_DB_HOST') ?: 'db';
$CFG->dbname    = getenv('MOODLE_DB_NAME') ?: 'moodle';
$CFG->dbuser    = getenv('MOODLE_DB_USER') ?: 'moodle';
$CFG->dbpass    = getenv('MOODLE_DB_PASSWORD') ?: '';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = [
    'dbpersist' => 0,
    'dbport'    => getenv('MOODLE_DB_PORT') ?: 3306,
    'dbsocket'  => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
];

$CFG->wwwroot   = rtrim(getenv('MOODLE_WWWROOT') ?: 'http://localhost:8080', '/');
$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02777;

// Apache est configuré avec FallbackResource /r.php (docker/apache-moodle.conf)
$CFG->routerconfigured = true;

// Derrière un reverse proxy HTTPS (Nginx, Traefik, Caddy...)
if (getenv('MOODLE_SSLPROXY') === 'true') {
    $CFG->sslproxy = true;
}

require_once(__DIR__ . '/lib/setup.php');

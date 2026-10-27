<?php
// This file is part of Moodle and is licensed under the
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
 * Inspect bundle program fields and certificate templates.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

$bundleid = null;
foreach ($argv as $arg) {
    if (preg_match('/^--id=(\d+)$/', $arg, $m)) {
        $bundleid = (int)$m[1];
        break;
    }
}

global $DB;

// List certificate templates.
if ($DB->get_manager()->table_exists('tool_certificate_templates')) {
    $templates = $DB->get_records_sql('SELECT id, name FROM {tool_certificate_templates} ORDER BY name');
    echo "Certificate templates (" . count($templates) . ")\n";
    foreach ($templates as $t) {
        echo " - ID: {$t->id} | Name: {$t->name}\n";
    }
} else {
    echo "tool_certificate_templates table not found. Is tool_coursecertificate installed?\n";
}

echo "\n";

if ($bundleid) {
    $bundle = $DB->get_record_select(
        'local_moderncommerce_products',
        'id = :id AND producttype IN (:bundle, :program)',
        ['id' => $bundleid, 'bundle' => 'bundle', 'program' => 'program']
    );
    if (!$bundle) {
        echo "Bundle {$bundleid} not found.\n";
        exit(1);
    }
    $coursecount = $DB->count_records('local_moderncommerce_product_courses', [
        'productid' => $bundle->id, 'relationtype' => 'included',
    ]);
    echo "Bundle #{$bundle->id}: {$bundle->name}\n";
    echo " - producttype: {$bundle->producttype}\n";
    echo " - included courses: {$coursecount}\n";
} else {
    $bundles = $DB->get_records_sql("
        SELECT p.id,
               p.name,
               p.producttype,
               COUNT(pc.id) AS coursecount
          FROM {local_moderncommerce_products} p
     LEFT JOIN {local_moderncommerce_product_courses} pc
            ON pc.productid = p.id
           AND pc.relationtype = 'included'
         WHERE p.producttype IN ('bundle', 'program')
      GROUP BY p.id, p.name, p.producttype
      ORDER BY p.id DESC");
    echo "Bundles (" . count($bundles) . ")\n";
    foreach ($bundles as $b) {
        echo " - #{$b->id}: {$b->name} | type={$b->producttype} | courses={$b->coursecount}\n";
    }
}

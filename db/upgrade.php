<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    auth_shibboleth_link
 * @copyright  2019 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_auth_shibboleth_link_upgrade($oldversion = 0) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2023120400) {
        // Define field idpfirstname to be added to auth_shibboleth_link.
        $table = new xmldb_table('auth_shibboleth_link');
        $field = new xmldb_field('idpfirstname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'idpusername');

        // Conditionally launch add field idpfirstname.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field idplastname to be added to auth_shibboleth_link.
        $field = new xmldb_field('idplastname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'idpfirstname');

        // Conditionally launch add field idplastname.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field idpemail to be added to auth_shibboleth_link.
        $field = new xmldb_field('idpemail', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'idplastname');

        // Conditionally launch add field idpemail.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Shibboleth_link savepoint reached.
        upgrade_plugin_savepoint(true, 2023120400, 'auth', 'shibboleth_link');
    }

    return true;
}

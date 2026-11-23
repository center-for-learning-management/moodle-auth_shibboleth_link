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
 * Database upgrade steps for auth_shibboleth_link.
 *
 * @package    auth_shibboleth_link
 * @copyright  2019 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executes database upgrades for auth_shibboleth_link.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool True on success.
 */
function xmldb_auth_shibboleth_link_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2021040700) {
        $table = new xmldb_table('auth_shibboleth_link');
        $field1 = new xmldb_field('idpfirstname', XMLDB_TYPE_CHAR, '250', null, null, null, null, 'idpusername');
        $field2 = new xmldb_field('idplastname', XMLDB_TYPE_CHAR, '250', null, null, null, null, 'idpfirstname');
        $field3 = new xmldb_field('idpemail', XMLDB_TYPE_CHAR, '250', null, null, null, null, 'idplastname');

        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
        if (!$dbman->field_exists($table, $field3)) {
            $dbman->add_field($table, $field3);
        }

        upgrade_plugin_savepoint(true, 2021040700, 'auth', 'shibboleth_link');
    }

    if ($oldversion < 2026102600) {
        $table = new xmldb_table('auth_shibboleth_link');

        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('source', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'usermodified');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026102600, 'auth', 'shibboleth_link');
    }

    return true;
}

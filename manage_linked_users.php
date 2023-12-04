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

require_once("../../config.php");

$context = context_system::instance();
$action = optional_param('action', '', PARAM_TEXT);

$PAGE->set_context($context);
$PAGE->set_heading(get_string('manage_linked_users', 'auth_shibboleth_link'));
$PAGE->set_title(get_string('manage_linked_users', 'auth_shibboleth_link'));
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_url('/auth/shibboleth_link/manage_linked_users.php');

require_login();

$linked_users = $DB->get_records('auth_shibboleth_link', ['userid' => $USER->id], 'lastseen DESC');

if ($action == 'unlink') {
    $link_id = required_param('link_id', PARAM_INT);
    if (!$linked_users[$link_id]) {
        throw new \moodle_exception('not allowed');
    }

    $DB->delete_records('auth_shibboleth_link', ['id' => $link_id]);

    redirect($PAGE->url);
}

echo $OUTPUT->header();

if (!$linked_users) {
    echo get_string('manage_linked_users:no_linked_users', 'auth_shibboleth_link');
} else {
    echo $OUTPUT->render_from_template('auth_shibboleth_link/manage_linked_users', [
        'linked_users' => array_values(array_map(function($linked_user) {
            $linked_user->idp = preg_replace('!/.*!', '', preg_replace('!^https?://!', '', $linked_user->idp));
            $linked_user->created = userdate($linked_user->created);
            $linked_user->lastseen = userdate($linked_user->lastseen);
            return $linked_user;
        }, $linked_users)),
    ]);
}

echo $OUTPUT->footer();

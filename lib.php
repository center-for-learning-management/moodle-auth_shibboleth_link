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
 * Core navigation callbacks for auth_shibboleth_link.
 *
 * @package    auth_shibboleth_link
 * @copyright  2019 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds a link to manage connected Shibboleth accounts in the user profile navigation.
 *
 * @param core_user\output\myprofile\tree $tree Navigation tree.
 * @param stdClass $user User object.
 * @param bool $iscurrentuser True if viewing own profile.
 * @param stdClass|null $course Course object if applicable.
 * @return void
 */
function auth_shibboleth_link_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if ($iscurrentuser) {
        $url = new moodle_url('/auth/shibboleth_link/manage_linked_users.php');
        $node = new core_user\output\myprofile\node(
            'loginactivity',
            'shibboleth_link',
            get_string('manage_linked_users', 'auth_shibboleth_link'),
            null,
            $url
        );
        $tree->add_node($node);
    }
}

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

defined('MOODLE_INTERNAL') || die();

/**
 * Extend users profile
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user User object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 * @return bool
 */
function auth_shibboleth_link_myprofile_navigation($tree, $user, $iscurrentuser, $course) {
    if ($iscurrentuser) {
        // var_dump(get_class_methods($tree));
        $category = $tree->categories['loginactivity'];

        $node = new \core_user\output\myprofile\node('loginactivity', 'manage_linked_users', get_string('manage_linked_users', 'auth_shibboleth_link'), null, new \moodle_url('/auth/shibboleth_link/manage_linked_users.php'));
        $category->add_node($node);
    }
}

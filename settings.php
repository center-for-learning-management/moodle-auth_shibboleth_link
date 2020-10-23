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
 * @copyright  2019 Zentrum für Lernmanagement (www.lernmangement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die;
if ($ADMIN->fulltree) {
    $settings->add(
        new admin_setting_configtext(
            'auth_shibboleth_link/loginpath',
            get_string('settings:loginpath', 'auth_shibboleth_link'),
            get_string('settings:loginpath:description', 'auth_shibboleth_link'),
            '/Shibboleth.sso',
            PARAM_TEXT
        )
    );
    $settings->add(
        new admin_setting_configtext(
            'auth_shibboleth_link/update_profile_always',
            get_string('settings:update_profile_always', 'auth_shibboleth_link'),
            get_string('settings:update_profile_always:description', 'auth_shibboleth_link'),
            'institution,department',
            PARAM_TEXT
        )
    );
    $settings->add(
        new admin_setting_configtext(
            'auth_shibboleth_link/update_profile_shibbonly',
            get_string('settings:update_profile_shibbonly', 'auth_shibboleth_link'),
            get_string('settings:update_profile_shibbonly:description', 'auth_shibboleth_link'),
            'firstname,lastname,email',
            PARAM_TEXT
        )
    );
    $settings->add(
        new admin_setting_configtext(
            'auth_shibboleth_link/hooks',
            get_string('settings:hooks', 'auth_shibboleth_link'),
            get_string('settings:hooks:description', 'auth_shibboleth_link'),
            '',
            PARAM_TEXT
        )
    );
}

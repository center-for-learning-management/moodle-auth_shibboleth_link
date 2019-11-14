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
 * @package   auth_shibboleth_link
 * @copyright 2019 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @author    Robert Schrenk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Shibboleth Link';


$string['auth:createaccount'] = 'Create account';
$string['auth:createaccount:description'] = 'Create a new account for your shibboleth login.';
$string['auth:linkaccount'] = 'Link current account';
$string['auth:linkaccount:description'] = 'Link the shibboleth login with the account you are currently using.';
$string['auth:linkotheraccount'] = 'Link another account';
$string['auth:linkotheraccount:description'] = 'Link the shibboleth login with another account.';
$string['auth:shibboleth:welcome'] = 'Hello {$a->firstname} {$a->lastname}!<br /><br />How would you like to use this login on our site?';
$string['auth:warning:usergone'] = 'Your shibboleth account has been linked to a user account that does not exist anymore. Therefore it has been unlinked.';

$string['cachedef_userinfo'] = 'Holds the userinfo after shibboleth';

$string['privacy:metadata:db'] = 'Stores the username from the Identity Provider.';
$string['privacy:metadata:db:idp'] = 'The Identity Provider';
$string['privacy:metadata:db:idpusername'] = 'The username from the Identity Provider';
$string['privacy:metadata:db:userid'] = 'Your userid within this Moodle-instance.';

$string['settings:loginpath'] = 'Login Path';
$string['settings:loginpath:description'] = 'Login Path that Shibboleth uses to launch the authentication';

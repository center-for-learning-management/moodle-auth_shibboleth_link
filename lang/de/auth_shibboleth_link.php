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

$string['pluginname'] = 'Shibboleth Verbindung';


$string['auth:createaccount'] = 'Konto anlegen';
$string['auth:createaccount:description'] = 'Ein neues Konto mit diesem Zugang anlegen.';
$string['auth:createaccount:error'] = 'Leider konnte das Konto nicht angelegt werden.';
$string['auth:createaccount:success'] = 'Konto erstellt.';
$string['auth:createaccount:userexists'] = 'Entschuldigung, aber ein/e Nutzer/in mit diesem Benutzernamen existiert bereits. Falls Sie das sind, nutzen Sie bitte die Option "Mit bestehendem Konto anmelden" und melden Sie sich mit diesem Konto an.';
$string['auth:linkaccount'] = 'Aktuelles Konto verbinden';
$string['auth:linkaccount:description'] = 'Verbinden Sie Ihren Login mit jenem Konto, mit dem Sie gerade angemeldet sind.';
$string['auth:linkotheraccount'] = 'Bestehendes Konto verbinden';
$string['auth:linkotheraccount:description'] = 'Verbinden Sie ein anderes, bestehendes Konto mit dem Login.';
$string['auth:replacelink'] = 'Bestehende Verbindungen zwischen diesem Login und lokalen Konten löschen.';
$string['auth:shibboleth:welcome'] = 'Willkommen';
$string['auth:shibboleth:welcome:question'] = 'Womit möchten Sie diese Zugangsmethode verknüpfen?';
$string['auth:warning:usergone'] = 'Ihr Shibboleth-Zugang war mit einem Konto verbunden, das nicht mehr existiert. Daher wurde die Verbindung gelöscht.';
$string['auth:warning:userreplaced'] = 'Wie von Ihnen beauftragt wurde die Verbindung dieses Zugangs zu lokalen Konten gelöscht.';
$string['auth:warning:userreplacenotallowed'] = 'Der Zugriff auf dieses Konto ist ausschließlich über Shibboleth möglich. Daher kann diese Verbindung nicht gelöst werden!';

$string['cachedef_userinfo'] = 'Hält Benutzerinformationen im Cache.';

$string['privacy:metadata:db'] = 'Verknüpft Zugänge eines Identity Providers mit lokalen Konten.';
$string['privacy:metadata:db:idp'] = 'The Identity Provider';
$string['privacy:metadata:db:idpusername'] = 'Der Benutzername vom Identity Provider';
$string['privacy:metadata:db:userid'] = 'Die Benutzer-ID in diesem Moodle.';

$string['settings:loginpath'] = 'Login Pfad';
$string['settings:loginpath:description'] = 'Der Loginpfad, mit dem Shibboleth aufgerufen wird, um die Authentifizierung zu starten.';

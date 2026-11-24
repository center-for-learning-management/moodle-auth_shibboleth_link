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
 * Shibboleth front-channel and SOAP back-channel logout handler.
 *
 * Based on /auth/shibboleth/logout.php to support linked accounts.
 *
 * @package    auth_shibboleth_link
 * @copyright  2019 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once("../../config.php");

require_once($CFG->dirroot . "/auth/shibboleth/auth.php");

$action = optional_param('action', '', PARAM_ALPHA);
$redirect = optional_param('return', '', PARAM_URL);

// Do not trust the Host header or PHP_SELF (client controlled): build our own URL from wwwroot.
$selfurl = $CFG->wwwroot . '/auth/shibboleth_link/logout.php';

// If the shibboleth plugin is not enabled, throw an exception.
if (!is_enabled_auth('shibboleth')) {
    throw new moodle_exception(get_string('pluginnotenabled', 'auth', 'shibboleth'));
}

// Front channel logout.
$inputstream = file_get_contents("php://input");
if ($action == 'logout' && !empty($redirect)) {
    // ATTENTION: HERE IS THE DIFFERENCE TO THE DEFAULT logout.php!
    $link = $DB->get_record('auth_shibboleth_link', ['userid' => $USER->id]);
    if (isloggedin($USER) && ($USER->auth == 'shibboleth' || !empty($link->id))) {
        // Logout user from application.
        require_logout();
    }

    // Validate the return URL to prevent open redirects (same check as login.php).
    $redirecturl = new moodle_url($redirect);
    if (!$redirecturl->is_local_url()) {
        throw new moodle_exception('invalid_wantsurl');
    }

    // Finally, send user to the return URL.
    redirect($redirecturl);
} else if (!empty($inputstream)) {
    // Back channel logout.
    // Set SOAP header.
    $server = new SoapServer($selfurl . '/LogoutNotification.wsdl');
    $server->addFunction("LogoutNotification");
    $server->handle();
} else {
    // Return WSDL.
    header('Content-Type: text/xml');

    echo <<<WSDL
<?xml version ="1.0" encoding ="UTF-8" ?>
<definitions name="LogoutNotification"
  targetNamespace="urn:mace:shibboleth:2.0:sp:notify"
  xmlns:notify="urn:mace:shibboleth:2.0:sp:notify"
  xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
  xmlns="http://schemas.xmlsoap.org/wsdl/">

<!--
This page either has to be called with the GET arguments 'action' and 'return' via
a redirect from the Shibboleth Service Provider logout handler (front-channel
logout) or via a SOAP request by a Shibboleth Service Provider (back-channel
logout).
Because neither of these two variants seems to be the case, the WSDL file for
the web service is returned.

For more information see:
- https://wiki.shibboleth.net/confluence/display/SHIB2/NativeSPLogoutInitiator
- https://wiki.shibboleth.net/confluence/display/SHIB2/NativeSPNotify
-->

    <types>
       <schema targetNamespace="urn:mace:shibboleth:2.0:sp:notify"
           xmlns="http://www.w3.org/2001/10/XMLSchema"
           xmlns:notify="urn:mace:shibboleth:2.0:sp:notify">

            <simpleType name="string">
                <restriction base="string">
                    <minLength value="1"/>
                </restriction>
            </simpleType>

            <element name="OK" type="notify:OKType"/>
            <complexType name="OKType">
                <sequence/>
            </complexType>

        </schema>
    </types>

    <message name="getLogoutNotificationRequest">
        <part name="SessionID" type="notify:string" />
    </message>

    <message name="getLogoutNotificationResponse" >
        <part name="OK"/>
    </message>

    <portType name="LogoutNotificationPortType">
        <operation name="LogoutNotification">
            <input message="getLogoutNotificationRequest"/>
            <output message="getLogoutNotificationResponse"/>
        </operation>
    </portType>

    <binding name="LogoutNotificationBinding" type="notify:LogoutNotificationPortType">
        <soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http"/>
        <operation name="LogoutNotification">
            <soap:operation soapAction="urn:xmethods-logout-notification#LogoutNotification"/>
        </operation>
    </binding>

    <service name="LogoutNotificationService">
          <port name="LogoutNotificationPort" binding="notify:LogoutNotificationBinding">
            <soap:address location="{$selfurl}"/>
          </port>
    </service>
</definitions>
WSDL;
    exit;
}

/**
 * Handles SOAP Back-channel logout notification.
 *
 * @param string $spsessionid SP-provided Shibboleth Session ID
 * @return SoapFault|void SoapFault or void if everything was fine
 */
function LogoutNotification($spsessionid) { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseFunction
    $sessionclass = \core\session\manager::get_handler_class();
    switch ($sessionclass) {
        case '\core\session\file':
            return \auth_shibboleth\helper::logout_file_session($spsessionid);
        case '\core\session\database':
            return \auth_shibboleth\helper::logout_db_session($spsessionid);
        default:
            throw new moodle_exception("Shibboleth logout not implemented for '$sessionclass'");
    }
}

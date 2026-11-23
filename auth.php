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
 * Authentication plugin class for auth_shibboleth_link.
 *
 * @package    auth_shibboleth_link
 * @copyright  2019 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/auth/shibboleth/auth.php');

/**
 * Shibboleth_link authentication plugin.
 *
 * @package    auth_shibboleth_link
 * @copyright  2019 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_plugin_shibboleth_link extends auth_plugin_shibboleth {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->authtype = 'shibboleth_link';
        $this->config = get_config('auth_shibboleth');
    }

    /**
     * Hook for login page.
     */
    public function loginpage_hook() {
        // We prevent a call to our parent class.
        return;
    }

    /**
     * Hook for logout page.
     */
    public function logoutpage_hook() {
        // We prevent a call to our parent class,
        // otherwise we would double the url-modification.
        return;
    }

    /**
     * Will always return false, as direct login is not intended.
     *
     * @param string $username The username (with system magic quotes)
     * @param string $password The password (with system magic quotes)
     * @return bool Authentication success or failure.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function user_login($username, $password) {
        return false;
    }

    /**
     * Return a list of identity providers to display on the login page.
     *
     * @param string $wantsurl The requested URL.
     * @return array List of arrays with keys url, iconurl and name.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function loginpage_idp_list($wantsurl) {
        $config = get_config('auth_shibboleth');
        $result = [];

        // Before displaying the button check that Shibboleth is set-up correctly.
        if (empty($config->user_attribute)) {
            return $result;
        }

        $idps = explode("\n", get_config('auth_shibboleth', 'organization_selection'));
        if (count($idps) > 0) {
            $idpx = explode(",", $idps[0]);
            $idp = trim($idpx[0]);
        } else {
            $idp = null;
        }

        $url = new moodle_url('/auth/shibboleth_link/login.php', ['idp' => $idp]);

        $config->login_name = 'Bildungsportal';
        $iconurl = new moodle_url('/local/eduvidual/pix/logo_bip-32x32.png');

        $result[] = ['url' => $url, 'iconurl' => $iconurl, 'name' => $config->login_name];
        return $result;
    }
}

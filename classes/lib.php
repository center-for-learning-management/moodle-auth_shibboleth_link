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

namespace auth_shibboleth_link;

defined('MOODLE_INTERNAL') || die;

class lib {
    public static $ACTION_CREATE = 1;
    public static $ACTION_LINK_OTHER = 2;
    public static $ACTION_LINK_CURRENT = 3;

    private static $datahash = '';

    public static function check_hooks() {
        global $CFG;
        $idpparams = \auth_shibboleth_link\lib::link_data_from_cache();
        $hooks = explode(';', get_config('auth_shibboleth_link', 'hooks'));
        foreach ($hooks as $hook) {
            if (!empty($hook) && file_exists($CFG->dirroot . '/' . $hook)) {
                require_once($CFG->dirroot . '/' . $hook);
            }
        }

    }
    /**
     * Check status after complete_user_login
     * @param doredirect true if we should do the redirect, false if we just return the url.
     * @return the url.
     */
    public static function check_login($doredirect = true) {
        global $CFG, $SESSION, $USER;
        self::check_hooks();
        if (\user_not_fully_set_up($USER, true)) {
            $urltogo = $CFG->wwwroot.'/user/edit.php?id='.$USER->id.'&amp;course='.SITEID;
            // We don't delete $SESSION->wantsurl yet, so we get there later

        } else if (isset($SESSION->wantsurl) and (strpos($SESSION->wantsurl, $CFG->wwwroot) === 0)) {
            $urltogo = $SESSION->wantsurl;    /// Because it's an address in this site
            unset($SESSION->wantsurl);

        } else {
            $urltogo = $CFG->wwwroot.'/';      /// Go to the standard home page
            unset($SESSION->wantsurl);         /// Just in case
        }

        /// Go to my-moodle page instead of homepage if defaulthomepage enabled
        if (!\has_capability('moodle/site:config', \context_system::instance()) and !empty($CFG->defaulthomepage) && $CFG->defaulthomepage == HOMEPAGE_MY and !isguestuser()) {
            if ($urltogo == $CFG->wwwroot or $urltogo == $CFG->wwwroot.'/' or $urltogo == $CFG->wwwroot.'/index.php') {
                $urltogo = $CFG->wwwroot.'/my/';
            }
        }
        $sessionkey = '';
        if (isset($_SERVER['Shib-Session-ID'])) {
            $sessionkey = $_SERVER['Shib-Session-ID'];
        } else {
            foreach ($_COOKIE AS $name => $value) {
                if (preg_match('/_shibsession_/i', $name)) {
                    $sessionkey = $value;
                }
            }
        }
        $SESSION->shibboleth_session_id = $sessionkey;
        if ($doredirect) \redirect($urltogo);
        else return $urltogo;
    }
    /**
     * Create some unique hash out of idpparams.
     * @return the hash
     */
    public static function datahash($idpparams = array()) {
        if (!empty(\optional_param('datahash', '', PARAM_RAW))) self::$datahash = \optional_param('datahash', '', PARAM_RAW);
        if (empty(self::$datahash) && !empty($idpparams)) self::$datahash = md5(json_encode($idpparams));
        return self::$datahash;
    }
    /**
     * Retrieves idp and idpusername from cache
     * @return array containing data.
     */
    public static function link_data_from_cache() {
        // Attention, we use cache_store::MODE_APPLICATION that shares data through all users.
        // This is necessary to have the data persisting login/logout. Therefore we include the data_hash into the name.
        $cache = \cache::make('auth_shibboleth_link', 'userinfo');
        return json_decode($cache->get('json_' . self::datahash()), true);
    }
    /**
     * Retrieves idp and idpusername from $_SERVER-var
     * @return array containing data.
     */
    public static function link_data_from_server() {
        global $_SERVER;
        $pluginconfig   = \get_config('auth_shibboleth');
        $shibbolethauth = \get_auth_plugin('shibboleth');

        $ar = array(
            'idp' => $_SERVER['Shib-Identity-Provider'],
            'idpusername' => strtolower($_SERVER[$pluginconfig->user_attribute]),
            'userinfo' => $shibbolethauth->get_userinfo(strtolower($_SERVER[$pluginconfig->user_attribute])),
        );
        // Attach all info from $_SERVER in case we need it later.
        foreach ($_SERVER as $key => $value) {
            if (empty($ar['userinfo'][$key])) {
                $ar['userinfo'][$key] = $value;
            }
        }
        return $ar;
    }
    /**
     * Stores idpparams to cache.
     * @param $idpparams array containing params.
     */
    public static function link_data_store_cache($idpparams) {
        // Attention, we use cache_store::MODE_APPLICATION that shares data through all users.
        // This is necessary to have the data persisting login/logout. Therefore we include datahash into the name.
        $cache = \cache::make('auth_shibboleth_link', 'userinfo');
        $cache->set('json_' . self::datahash($idpparams), json_encode($idpparams, JSON_NUMERIC_CHECK));
    }
    /**
     * Retrieves a link based on params
     * @param $idpparams array containing 'idp' and 'idpusername'.
     * @return the db entry of the link.
     */
    public static function link_get($idpparams) {
        global $DB;
        $sql = "SELECT *
                    FROM {auth_shibboleth_link}
                    WHERE idp = ?
                        AND (
                            idpusername = ?
                            OR
                            idpusername = ?
                        )";
        $dbparams = [
            $idpparams['idp'],
            $idpparams['idpusername'],
            ltrim($idpparams['idpusername'], '0')
        ];
        return $DB->get_record_sql($sql, $dbparams);
    }
    /**
     * Sets the used time of a link.
     * @param $link
     */
    public static function link_log_used($link) {
        global $DB;
        $link->lastseen = time();
        $DB->update_record('auth_shibboleth_link', $link);
    }
    /**
     * Get current idpparams from cache and create a link to the current user.
     * @return the id of the link-entry.
     */
    public static function link_store($user = 0) {
        global $DB, $USER;
        if (empty($user)) $user = $USER;
        $idpparams = self::link_data_from_cache();
        $link = $DB->get_record('auth_shibboleth_link', array('idp' => $idpparams['idp'], 'idpusername' => $idpparams['idpusername']));
        if (!empty($link->id)) {
            $link->userid = $user->id;
            $link->lastseen = time();
            $DB->update_record('auth_shibboleth_link', $link);
            return $link->id;
        } else {
            $link = array(
                'created' => time(),
                'idp' => $idpparams['idp'],
                'idpusername' => $idpparams['idpusername'],
                'lastseen' => time(),
                'userid' => $user->id,
            );
            return $DB->insert_record('auth_shibboleth_link', $link);
        }
    }
}

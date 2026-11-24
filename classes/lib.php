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
 * Helper library for auth_shibboleth_link.
 *
 * @package    auth_shibboleth_link
 * @copyright  2019 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_shibboleth_link;

/**
 * Helper class for managing Shibboleth identity links and post-login redirects.
 *
 * @package    auth_shibboleth_link
 * @copyright  2019 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lib {
    /** @var int Action code to create a new local account. */
    public const ACTION_CREATE = 1;
    /** @var int Action code to link to another existing local account. */
    public const ACTION_LINK_OTHER = 2;
    /** @var int Action code to link to the currently logged-in local account. */
    public const ACTION_LINK_CURRENT = 3;

    /** @var int Link modified by user self-service. */
    public const SOURCE_USER = 0;
    /** @var int Link created by automatic matching. */
    public const SOURCE_AUTOMATCH = 1;
    /** @var int Link modified by a manager. */
    public const SOURCE_MANAGER = 2;
    /** @var int Link created via import. */
    public const SOURCE_IMPORT = 3;

    /**
     * Loads any configured post-login hook scripts.
     *
     * @return void
     */
    public static function check_hooks() {
        global $CFG;
        $idpparams = self::idpparams_get(); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        $hooks = explode(';', get_config('auth_shibboleth_link', 'hooks'));
        foreach ($hooks as $hook) {
            $hook = trim($hook);
            if ($hook === '') {
                continue;
            }
            // Only allow php files inside dirroot (no path traversal).
            $path = realpath($CFG->dirroot . '/' . $hook);
            $root = realpath($CFG->dirroot);
            if (
                $path !== false &&
                $root !== false &&
                strpos($path, $root . DIRECTORY_SEPARATOR) === 0 &&
                substr($path, -4) === '.php' &&
                is_file($path)
            ) {
                require_once($path);
            }
        }
    }

    /**
     * Check status after complete_user_login.
     *
     * @param bool $doredirect True if we should perform the redirect, false to return the URL.
     * @return string|void The target URL if $doredirect is false.
     */
    public static function check_login($doredirect = true) {
        global $CFG, $SESSION, $USER;
        self::check_hooks();
        if (\user_not_fully_set_up($USER, true)) {
            $urltogo = $CFG->wwwroot . '/user/edit.php?id=' . $USER->id . '&amp;course=' . SITEID;
            // We do not delete $SESSION->wantsurl yet, so we get there later.
        } else if (isset($SESSION->wantsurl) && (new \moodle_url($SESSION->wantsurl))->is_local_url()) {
            $urltogo = $SESSION->wantsurl; // Because it is an address in this site.
            unset($SESSION->wantsurl);
        } else {
            $urltogo = $CFG->wwwroot . '/'; // Go to the standard home page.
            unset($SESSION->wantsurl); // Just in case.
        }

        // Go to my-moodle page instead of homepage if defaulthomepage enabled.
        if (
            !\has_capability('moodle/site:config', \context_system::instance()) &&
            !empty($CFG->defaulthomepage) &&
            $CFG->defaulthomepage == HOMEPAGE_MY &&
            !isguestuser()
        ) {
            if ($urltogo == $CFG->wwwroot || $urltogo == $CFG->wwwroot . '/' || $urltogo == $CFG->wwwroot . '/index.php') {
                $urltogo = $CFG->wwwroot . '/my/';
            }
        }
        $sessionkey = '';
        if (isset($_SERVER['Shib-Session-ID'])) {
            $sessionkey = $_SERVER['Shib-Session-ID'];
        } else {
            foreach ($_COOKIE as $name => $value) {
                if (preg_match('/_shibsession_/i', $name)) {
                    $sessionkey = $value;
                }
            }
        }
        $SESSION->shibboleth_session_id = $sessionkey;
        if ($doredirect) {
            \redirect($urltogo);
        } else {
            return $urltogo;
        }
    }

    /**
     * Retrieves idp and idpusername from the session.
     *
     * @return array Containing IdP parameters.
     */
    public static function idpparams_get() {
        global $SESSION;
        // Die Daten liegen in der Session statt in einem Application-Cache, der über einen Hash in der URL
        // angesprochen wird, damit niemand anderer die SSO-Identität übernehmen kann.
        // https://github.com/center-for-learning-management/eduvidual-src/issues/3105.
        return $SESSION->auth_shibboleth_link_idpparams ?? [];
    }

    /**
     * Retrieves idp and idpusername from $_SERVER variables.
     *
     * @return array Containing IdP parameters and userinfo.
     */
    public static function idpparams_from_server() {
        $pluginconfig = \get_config('auth_shibboleth');
        $shibbolethauth = \get_auth_plugin('shibboleth');

        $ar = [
            'idp' => $_SERVER['Shib-Identity-Provider'],
            'idpusername' => $_SERVER[$pluginconfig->user_attribute],
            'userinfo' => $shibbolethauth->get_userinfo($_SERVER[$pluginconfig->user_attribute]),
        ];
        // Attach all info from $_SERVER in case we need it later.
        foreach ($_SERVER as $key => $value) {
            if (empty($ar['userinfo'][$key])) {
                $ar['userinfo'][$key] = $value;
            }
        }
        return $ar;
    }

    /**
     * Stores idpparams to the session.
     *
     * @param array $idpparams Array containing IdP parameters.
     * @return void
     */
    public static function idpparams_store($idpparams) {
        global $SESSION;
        // Die $SESSION übersteht complete_user_login(), nur nach require_logout() muss neu gespeichert werden.
        $SESSION->auth_shibboleth_link_idpparams = $idpparams;
    }

    /**
     * Retrieves a link record based on IdP parameters.
     *
     * @param array $idpparams Array containing 'idp' and 'idpusername'.
     * @return \stdClass|false The database record of the link, or false if not found.
     */
    public static function link_get($idpparams) {
        global $DB;

        // Compare idpusername case sensitive!
        $idpusernamewhere = $DB->sql_equal('idpusername', '?');
        $trimmedcolwhere = $DB->sql_equal("TRIM(LEADING '0' FROM idpusername)", '?');
        $sql = "SELECT *
                    FROM {auth_shibboleth_link}
                    WHERE idp = ?
                        AND (
                            {$idpusernamewhere}
                            OR
                            {$trimmedcolwhere}
                        )";
        $dbparams = [
            $idpparams['idp'],
            $idpparams['idpusername'],
            ltrim($idpparams['idpusername'], '0'),
        ];

        return $DB->get_record_sql($sql, $dbparams);
    }

    /**
     * Updates the last-used timestamp and cached user profile attributes of a link.
     *
     * @param \stdClass $link The auth_shibboleth_link database record.
     * @param array $idpparams Array containing IdP parameters and userinfo.
     * @return void
     */
    public static function link_log_used($link, $idpparams) {
        global $DB;
        $link->lastseen = time();
        $link->idpfirstname = $idpparams['userinfo']['firstname'] ?? $link->idpfirstname;
        $link->idplastname = $idpparams['userinfo']['lastname'] ?? $link->idplastname;
        $link->idpemail = $idpparams['userinfo']['email'] ?? $link->idpemail;
        $DB->update_record('auth_shibboleth_link', $link);
    }

    /**
     * Get current idpparams from the session and create or update a link to the given user.
     *
     * @param \stdClass|int $user User object, or 0 to use global $USER.
     * @return int|bool The ID of the link entry.
     */
    public static function link_store($user = 0) {
        global $DB, $USER;
        if (empty($user)) {
            $user = $USER;
        }
        $idpparams = self::idpparams_get();

        $link = $DB->get_record_select(
            'auth_shibboleth_link',
            'idp=? AND ' . $DB->sql_equal('idpusername', '?'),
            [$idpparams['idp'], $idpparams['idpusername']]
        );

        if (!empty($link->id)) {
            $link->userid = $user->id;
            $link->usermodified = $user->id;
            $link->source = static::SOURCE_USER;
            $DB->update_record('auth_shibboleth_link', $link);

            static::link_log_used($link, $idpparams);

            return $link->id;
        } else {
            $link = [
                'created' => time(),
                'idp' => $idpparams['idp'],
                'idpusername' => $idpparams['idpusername'],
                'idpfirstname' => $idpparams['userinfo']['firstname'],
                'idplastname' => $idpparams['userinfo']['lastname'],
                'idpemail' => $idpparams['userinfo']['email'],
                'lastseen' => time(),
                'userid' => $user->id,
                'usermodified' => $user->id,
                'source' => static::SOURCE_USER,
            ];
            return $DB->insert_record('auth_shibboleth_link', $link);
        }
    }
}

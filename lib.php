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

    // Werte für auth_shibboleth_link.source - wer hat den Link zuletzt geändert.
    public const SOURCE_USER = 0;
    public const SOURCE_AUTOMATCH = 1;
    public const SOURCE_MANAGER = 2;
    public const SOURCE_IMPORT = 3;

    private static $datahash = '';

    public static function check_hooks() {
        global $CFG;
        $idpparams = self::link_data_from_cache();
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
            $urltogo = $CFG->wwwroot . '/user/edit.php?id=' . $USER->id . '&amp;course=' . SITEID;
            // We don't delete $SESSION->wantsurl yet, so we get there later
        } else if (isset($SESSION->wantsurl) and (strpos($SESSION->wantsurl, $CFG->wwwroot) === 0)) {
            $urltogo = $SESSION->wantsurl;    /// Because it's an address in this site
            unset($SESSION->wantsurl);
        } else {
            $urltogo = $CFG->wwwroot . '/';      /// Go to the standard home page
            unset($SESSION->wantsurl);         /// Just in case
        }

        /// Go to my-moodle page instead of homepage if defaulthomepage enabled
        if (!\has_capability('moodle/site:config', \context_system::instance()) and !empty($CFG->defaulthomepage) && $CFG->defaulthomepage == HOMEPAGE_MY and !isguestuser()) {
            if ($urltogo == $CFG->wwwroot or $urltogo == $CFG->wwwroot . '/' or $urltogo == $CFG->wwwroot . '/index.php') {
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
        } else { return $urltogo;
        }
    }

    /**
     * Session key under which we remember the datahash that THIS browser
     * session legitimately obtained via a real Shibboleth SSO round trip.
     */
    private const SESSION_DATAHASH_KEY = 'auth_shibboleth_link_datahash';

    /**
     * Create/verify a session-bound token used to key into the (shared,
     * application-scoped) userinfo cache.
     *
     * SECURITY: the cache is MODE_APPLICATION, i.e. shared across every user
     * on the site, so the hash used to key into it must never be trusted
     * purely because a client supplied it - anyone could then read or claim
     * another user's cached SSO identity. We therefore:
     *   1. Generate the hash ourselves from cryptographically random bytes
     *      (not a deterministic digest of guessable IdP data), and
     *   2. Bind it to the Moodle $SESSION of the browser that actually
     *      performed the SSO login, and
     *   3. Only ever accept a client-supplied 'datahash' GET param if it
     *      matches the value this same session previously generated.
     *
     * @param array $idpparams params freshly received from the IdP (only
     *              passed when we are about to cache new data).
     * @return string the (validated) hash, or '' if none is available/valid.
     */
    public static function datahash($idpparams = []) {
        global $SESSION;

        // Already resolved earlier in this request - reuse it.
        if (!empty(self::$datahash)) {
            return self::$datahash;
        }

        $requested = \optional_param('datahash', '', PARAM_ALPHANUM);
        $sessionhash = $SESSION->{self::SESSION_DATAHASH_KEY} ?? '';

        if (!empty($requested) && !empty($sessionhash) && \hash_equals($sessionhash, $requested)) {
            // The client gave us back exactly the token we handed to THIS
            // session earlier - safe to use it to look up the cache entry.
            self::$datahash = $sessionhash;
        } else if (!empty($idpparams)) {
            // We are about to store fresh SSO data for this session: mint a
            // new unguessable token and remember it against this session.
            self::$datahash = \bin2hex(\random_bytes(32));
            $SESSION->{self::SESSION_DATAHASH_KEY} = self::$datahash;
        }

        return self::$datahash;
    }

    /**
     * Retrieves idp and idpusername from cache
     * @return array containing data.
     */
    public static function link_data_from_cache() {
        // Attention, we use cache_store::MODE_APPLICATION that shares data through all users.
        // This is necessary to have the data persisting login/logout. Therefore we include the data_hash into the name.
        // The hash itself is validated in self::datahash() and is never trusted purely because
        // it was supplied on the URL - see the security note there.
        $hash = self::datahash();
        if (empty($hash)) {
            return [];
        }
        $cache = \cache::make('auth_shibboleth_link', 'userinfo');
        $raw = $cache->get('json_' . $hash);
        if ($raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Explicitly (re-)bind an already-validated datahash to the current
     * session. Used when we intentionally destroy/replace the session (e.g.
     * logout as part of the "link to a different account" flow) and need to
     * carry the validated token over to the fresh session, the same way
     * $SESSION->wantsurl is carried over.
     *
     * IMPORTANT: only ever call this with a hash that has already been
     * validated by self::datahash() against the *previous* session - never
     * with a raw, unverified client-supplied value.
     *
     * @param string $hash
     */
    public static function bind_session_datahash($hash) {
        global $SESSION;
        self::$datahash = $hash;
        $SESSION->{self::SESSION_DATAHASH_KEY} = $hash;
    }

    /**
     * Removes a (used) entry from the cache and forgets the session binding,
     * so the datahash cannot be replayed to link/create another account.
     */
    public static function link_data_forget_cache() {
        global $SESSION;
        $hash = self::datahash();
        if (!empty($hash)) {
            $cache = \cache::make('auth_shibboleth_link', 'userinfo');
            $cache->delete('json_' . $hash);
        }
        unset($SESSION->{self::SESSION_DATAHASH_KEY});
        self::$datahash = '';
    }

    /**
     * Retrieves idp and idpusername from $_SERVER-var
     * @return array containing data.
     */
    public static function link_data_from_server() {
        global $_SERVER;
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

        // compare idpusername case sensitive!
        $idpusername_where = $DB->sql_equal('idpusername', '?');
        $sql = "SELECT *
                    FROM {auth_shibboleth_link}
                    WHERE idp = ?
                        AND (
                            {$idpusername_where}
                            OR
                            {$idpusername_where}
                        )";
        $dbparams = [
            $idpparams['idp'],
            $idpparams['idpusername'],
            ltrim($idpparams['idpusername'], '0'),
        ];

        return $DB->get_record_sql($sql, $dbparams);
    }

    /**
     * Sets the used time of a link.
     * @param $link
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
     * Get current idpparams from cache and create a link to the current user.
     * @return the id of the link-entry.
     */
    public static function link_store($user = 0) {
        global $DB, $USER;
        if (empty($user)) {
            $user = $USER;
        }
        $idpparams = self::link_data_from_cache();

        if (empty($idpparams['idp']) || empty($idpparams['idpusername'])) {
            // No (validated) SSO data available for this session - nothing to link.
            return 0;
        }

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

            // Single-use: prevent the same datahash being replayed to link/create again.
            self::link_data_forget_cache();

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
            $id = $DB->insert_record('auth_shibboleth_link', $link);

            // Single-use: prevent the same datahash being replayed to link/create again.
            self::link_data_forget_cache();

            return $id;
        }
    }
}

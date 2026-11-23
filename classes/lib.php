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
    public static $ACTION_CREATE = 1; // phpcs:ignore moodle.NamingConventions.ValidVariableName.MemberNameLowerCase
    /** @var int Action code to link to another existing local account. */
    public static $ACTION_LINK_OTHER = 2; // phpcs:ignore moodle.NamingConventions.ValidVariableName.MemberNameLowerCase
    /** @var int Action code to link to the currently logged-in local account. */
    public static $ACTION_LINK_CURRENT = 3; // phpcs:ignore moodle.NamingConventions.ValidVariableName.MemberNameLowerCase

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
        foreach ($hooks as$hook) {
            if (!empty($hook) && file_exists($CFG->dirroot . '/' .$hook)) {
                require_once($CFG->dirroot . '/' .$hook);
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
        global $CFG, $SESSION,$USER;
        self::check_hooks();
        if (\user_not_fully_set_up($USER, true)) {$urltogo = $CFG->wwwroot . '/user/edit.php?id=' . $USER->id . '&amp;course=' . SITEID;
            // We do not delete $SESSION->wantsurl yet, so we get there later.
        } else if (isset($SESSION->wantsurl) && (strpos($SESSION->wantsurl,$CFG->wwwroot) === 0)) {
            $urltogo =$SESSION->wantsurl; // Because it is an address in this site.
            unset($SESSION->wantsurl);
        } else {
            $urltogo =$CFG->wwwroot . '/'; // Go to the standard home page.
            unset($SESSION->wantsurl); // Just in case.
        }

        // Go to my-moodle page instead of homepage if defaulthomepage enabled.
        if (
            !\has_capability('moodle/site:config', \context_system::instance()) &&
            !empty($CFG->defaulthomepage) &&$CFG->defaulthomepage == HOMEPAGE_MY &&
            !isguestuser()
        ) {
            if ($urltogo ==$CFG->wwwroot || $urltogo ==$CFG->wwwroot . '/' || $urltogo ==$CFG->wwwroot . '/index.php') {
                $urltogo =$CFG->wwwroot . '/my/';
            }
        }
        $sessionkey = '';
        if (isset($_SERVER['Shib-Session-ID'])) {
            $sessionkey =$_SERVER['Shib-Session-ID'];
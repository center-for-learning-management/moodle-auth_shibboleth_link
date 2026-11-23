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
 * Shibboleth link login and account linking handler.
 *
 * Mainly taken from auth/shibboleth/index.php. Designed to be redirected from moodle/login/index.php.
 *
 * @package    auth_shibboleth_link
 * @copyright  2019 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing
require('../../config.php');
require_once($CFG->dirroot . '/auth/shibboleth_link/classes/lib.php');

// Inject mocked Shibboleth SP $_SERVER attributes exclusively during Behat UAT runs.
if (defined('BEHAT_SITE_RUNNING')) {
    $mockserver = get_config('auth_shibboleth_link', 'behat_mock_server');
    if (!empty($mockserver)) {
        $decoded = json_decode($mockserver, true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) {
                $_SERVER[$k] = $v;
            }
        }
    }
}

$linkorcreate = optional_param('linkorcreate', 0, PARAM_INT);
$replacelink = optional_param('replacelink', 0, PARAM_INT);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_heading(get_string('pluginname', 'auth_shibboleth_link'));
$PAGE->set_title(get_string('pluginname', 'auth_shibboleth_link'));
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_url('/auth/shibboleth_link/index.php', ['linkorcreate' => $linkorcreate]);

$idpparams = \auth_shibboleth_link\lib::idpparams_get();
if ($linkorcreate > 0 && !empty($idpparams['idp'])) {
    $link = \auth_shibboleth_link\lib::link_get($idpparams);
    switch ($linkorcreate) {
        case \auth_shibboleth_link\lib::$actioncreate: // Create new account.
            // Test if a user with that email already exists.
            $email = strtolower($idpparams['userinfo']['email']);
            if (!$email) {
                // TODO: email not set -> "pseudo" random.
                $email = floor((microtime(true) - 1689000000) * 1000) . '@a.eduvidual.at';
            }

            $testuser = $DB->get_record_select('user', 'deleted=0 AND (username=? OR email=?)', [$email, $email]);

            if ($testuser && $testuser->deleted == 0) {
                // User already exists, use email address with firstname suffix.
                $email = str_replace('@', '+' . strtolower($idpparams['userinfo']['firstname']) . '@', $email);

                $testuser = $DB->get_record_select('user', 'deleted=0 AND username=?', [$email]);
                if ($testuser) {
                    echo $OUTPUT->header();
                    echo $OUTPUT->render_from_template('auth_shibboleth_link/alert', [
                        'content' => get_string('auth:createaccount:userexists_link', 'auth_shibboleth_link'),
                        'type' => 'warning',
                        'url' => $CFG->wwwroot . '/auth/shibboleth_link/index.php?linkorcreate=' .
                            \auth_shibboleth_link\lib::$actionlinkother,
                    ]);
                    echo $OUTPUT->footer();
                    exit;
                }
            }

            // Create a user with the information from shibboleth.
            require_once($CFG->dirroot . '/user/lib.php');
            $idpparams['userinfo']['email'] = $email;
            $idpparams['userinfo']['confirmed'] = 1;
            $idpparams['userinfo']['mnethostid'] = 1;
            $idpparams['userinfo']['username'] = strtolower($email);
            $idpparams['userinfo']['password'] = generate_password();

            $userid = \user_create_user($idpparams['userinfo']);
            if (!empty($userid)) {
                $user = core_user::get_user($userid, '*', IGNORE_MISSING);
                $user->auth = 'manual';
                $DB->update_record('user', $user);
                // Herkunft des Accounts festhalten: Selbst-Anlage nach SSO-Login.
                set_user_preference('local_eduvidual_created_source', 'shibboleth_link', $userid);
                complete_user_login($user);
                \auth_shibboleth_link\lib::link_store($user);
                $urltogo = \auth_shibboleth_link\lib::check_login(false);
                redirect(
                    $urltogo,
                    get_string('auth:createaccount:success', 'auth_shibboleth_link'),
                    0,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } else {
                echo $OUTPUT->header();
                echo $OUTPUT->render_from_template('auth_shibboleth_link/alert', [
                    'content' => get_string('auth:createaccount:error', 'auth_shibboleth_link'),
                    'type' => 'warning',
                    'url' => $CFG->wwwroot . '/login/index.php',
                ]);
                echo $OUTPUT->footer();
            }
            break;

        case \auth_shibboleth_link\lib::$actionlinkcurrent: // Link current logged-in account.
            if (isloggedin() && !isguestuser()) {
                // Link user.
                \auth_shibboleth_link\lib::link_store();
                // Check user status and redirect.
                \auth_shibboleth_link\lib::check_login();
            } else {
                // We logged out in the meanwhile. Go to login page.
                $SESSION->wantsurl = $CFG->wwwroot . '/auth/shibboleth_link/index.php?linkorcreate=' .
                    \auth_shibboleth_link\lib::$actionlinkcurrent;
                // Redirect to normal login.
                redirect($CFG->wwwroot . '/login/index.php');
            }
            break;

        case \auth_shibboleth_link\lib::$actionlinkother: // Link another existing account.
            // Capture wantsurl before the session is destroyed by logout.
            $wantsurl = $SESSION->wantsurl ?? '';
            if (isloggedin() && !isguestuser()) {
                require_logout();
            }
            // Session was recreated by require_logout(), store SSO params again.
            \auth_shibboleth_link\lib::idpparams_store($idpparams);
            // Store this page as wantsurl.
            $SESSION->wantsurl = $CFG->wwwroot . '/auth/shibboleth_link/index.php?linkorcreate=' .
                \auth_shibboleth_link\lib::$actionlinkcurrent;
            // Redirect to normal login.
            redirect($CFG->wwwroot . '/login/index.php');
            break;
    }
    die();
}

$pluginconfig = get_config('auth_shibboleth');
$shibbolethauth = get_auth_plugin('shibboleth');

// Check whether Shibboleth is configured properly.
if (empty($pluginconfig->user_attribute)) {
    throw new \moodle_exception('shib_not_set_up_error', 'auth_shibboleth');
}

// If we can find the Shibboleth attribute, save it in session and return to main login page.
if (!empty($_SERVER[$pluginconfig->user_attribute])) {
    // Shibboleth auto-login.
    $idpparams = \auth_shibboleth_link\lib::idpparams_from_server();
    // Immediately store data to cache.
    \auth_shibboleth_link\lib::idpparams_store($idpparams);
    $link = \auth_shibboleth_link\lib::link_get($idpparams);
    $asklinkorcreate = true; // Triggers if user has a decision to link account.
    $msgs = [];
    if (!empty($link->userid)) {
        \auth_shibboleth_link\lib::link_log_used($link, $idpparams);

        $user = core_user::get_user($link->userid, '*', IGNORE_MISSING);

        if ($replacelink === 1) {
            if (!empty($user) && $user->auth == 'shibboleth') {
                // Sorry we can not do this!
                $msgs[] = [
                    'type' => 'danger',
                    'content' => get_string('auth:warning:userreplacenotallowed', 'auth_shibboleth_link'),
                ];
                $asklinkorcreate = false;
            } else {
                $DB->delete_records('auth_shibboleth_link', ['id' => $link->id]);
                unset($link);
                $msgs[] = [
                    'type' => 'success',
                    'content' => get_string('auth:warning:userreplaced', 'auth_shibboleth_link'),
                ];
            }
        }
        if (!empty($user) && $user->deleted == 1) {
            // Show an error that the linked account has gone.
            $DB->delete_records('auth_shibboleth_link', ['id' => $link->id]);
            unset($link);
            $msgs[] = [
                'type' => 'danger',
                'content' => get_string('auth:warning:usergone', 'auth_shibboleth_link'),
            ];
        }

        if (!empty($user->id) && !empty($link) && $user->id == $link->userid && $user->deleted == 0) {
            complete_user_login($user);
            \auth_shibboleth_link\lib::check_login(count($msgs) == 0);
        }
    }

    $email = $idpparams['userinfo']['email'] ?? '';
    $testuser = $DB->get_record_select('user', 'deleted=0 AND (username=? OR email=?)', [$email, $email]);

    // If we are here then the login did not work. Either no user at all, or it has gone.
    // Store the user info from shibboleth in the cache.
    \auth_shibboleth_link\lib::idpparams_store($idpparams);
    // Ask user if we should create an account or link to an existing one!
    $PAGE->set_heading(get_string('auth:linkaccount', 'auth_shibboleth_link'));
    $PAGE->set_title(get_string('auth:linkaccount', 'auth_shibboleth_link'));
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('auth_shibboleth_link/link_or_create', [
        'userexists' => (bool)$testuser,
        'asklinkorcreate' => $asklinkorcreate,
        'idpusername' => $idpparams['idpusername'],
        'isloggedin' => (isloggedin() && !isguestuser($USER)) ? 1 : 0,
        'msgs' => $msgs,
        'userdata' => [$idpparams['userinfo']],
        'userfullname' => \fullname($USER),
        'wwwroot' => $CFG->wwwroot,
    ]);
    echo $OUTPUT->footer();
} else if (!empty($_SERVER['HTTP_SHIB_APPLICATION_ID']) || !empty($_SERVER['Shib-Application-ID'])) {
    // If we can find any (user independent) Shibboleth attributes but no user
    // attributes we probably didn't receive any user attributes.
    $attrs = sprintf(
        "'%s', '%s', '%s' and '%s'",
        $pluginconfig->user_attribute ?? '',
        $pluginconfig->field_map_firstname ?? '',
        $pluginconfig->field_map_lastname ?? '',
        $pluginconfig->field_map_email ?? ''
    );
    throw new \moodle_exception('shib_no_attributes_error', 'auth_shibboleth', '', $attrs);
} else {
    throw new \moodle_exception('shib_not_set_up_error', 'auth_shibboleth');
}

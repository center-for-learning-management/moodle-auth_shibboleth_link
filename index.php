<?php

// Mainly taken from auth/shibboleth/index.php

// Designed to be redirected from moodle/login/index.php

require('../../config.php');
require_once($CFG->dirroot . '/auth/shibboleth_link/classes/lib.php');

$datahash = optional_param('datahash', '', PARAM_RAW);
$linkorcreate = optional_param('linkorcreate', 0, PARAM_INT);
$replacelink = optional_param('replacelink', 0, PARAM_INT);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_heading(get_string('pluginname', 'auth_shibboleth_link'));
$PAGE->set_title(get_string('pluginname', 'auth_shibboleth_link'));
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_url('/auth/shibboleth_link/index.php', array('datahash' => $datahash, 'linkorcreate' => $linkorcreate));

$idpparams = \auth_shibboleth_link\lib::link_data_from_cache();
if ($linkorcreate > 0 && !empty($idpparams['idp'])) {
    $link = \auth_shibboleth_link\lib::link_get($idpparams);
    switch($linkorcreate) {
        case \auth_shibboleth_link\lib::$ACTION_CREATE: // == 1
            // Test if a user with that username already exists.
            $testuser = $DB->get_record('user', array('deleted' => 0, 'username' => $idpparams['idpusername']));
            if (!empty($testuser->id) && $testuser->deleted == 0) {
                // If that user is a shibboleth_link-account we use it.
                if ($testuser->auth == 'shibboleth') {
                    $user = core_user::get_user($testuser->id);
                    complete_user_login($user);
                    \auth_shibboleth_link\lib::link_store();
                    \auth_shibboleth_link\lib::check_login();
                    echo $OUTPUT->header();
                    echo $OUTPUT->render_from_template('auth_shibboleth_link/alert', array(
                        'content' => get_string('auth:createaccount:success', 'auth_shibboleth_link'),
                        'type' => 'success',
                        'url' => $CFG->wwwroot . '/my',
                    ));
                    echo $OUTPUT->footer();
                } else {
                    echo $OUTPUT->header();
                    echo $OUTPUT->render_from_template('auth_shibboleth_link/alert', array(
                        'content' => get_string('auth:createaccount:userexists', 'auth_shibboleth_link'),
                        'type' => 'warning',
                        'url' => $CFG->wwwroot . '/auth/shibboleth_link/index.php?datahash=' . $datahash . '&linkorcreate=' . \auth_shibboleth_link\lib::$ACTION_LINK_OTHER,
                    ));
                    echo $OUTPUT->footer();
                }
            } else {
                // Create a user with the information from shibboleth.
                require_once($CFG->dirroot . '/user/lib.php');
                $idpparams['userinfo']['confirmed'] = 1;
                $idpparams['userinfo']['mnethostid'] = 1;
                $idpparams['userinfo']['username'] = strtolower($idpparams['userinfo']['username']);
                $userid = \user_create_user($idpparams['userinfo']);
                if (!empty($userid)) {
                    $user = core_user::get_user($userid, '*', IGNORE_MISSING);
                    $user->auth = 'shibboleth';
                    $DB->update_record('user', $user);
                    complete_user_login($user);
                    \auth_shibboleth_link\lib::link_store($user);
                    $urltogo = \auth_shibboleth_link\lib::check_login(false);
                    redirect($urltogo, get_string('auth:createaccount:success', 'auth_shibboleth_link'), 0, \core\output\notification::NOTIFY_SUCCESS);
                    echo $OUTPUT->header();
                    echo $OUTPUT->render_from_template('auth_shibboleth_link/alert', array(
                        'content' => get_string('auth:createaccount:success', 'auth_shibboleth_link'),
                        'type' => 'success',
                        'url' => $CFG->wwwroot . '/my',
                    ));
                    echo $OUTPUT->footer();
                } else {
                    echo $OUTPUT->header();
                    echo $OUTPUT->render_from_template('auth_shibboleth_link/alert', array(
                        'content' => get_string('auth:createaccount:error', 'auth_shibboleth_link'),
                        'type' => 'warning',
                        'url' => $CFG->wwwroot . '/login/index.php',
                    ));
                    echo $OUTPUT->footer();
                }
            }
        break;
        case \auth_shibboleth_link\lib::$ACTION_LINK_CURRENT: // == 3
            if (isloggedin() && !isguestuser()) {
                // Link user.
                \auth_shibboleth_link\lib::link_store();
                // Check user status and redirect.
                \auth_shibboleth_link\lib::check_login();
            } else {
                // We logged out in the meanwhile. Go to login page.
                $SESSION->wantsurl = $CFG->wwwroot . '/auth/shibboleth_link/index.php?datahash=' . $datahash . '&linkorcreate=' . \auth_shibboleth_link\lib::$ACTION_LINK_CURRENT;
                // Redirect to normal login.
                redirect($CFG->wwwroot . '/login/index.php');
            }
        break;
        case \auth_shibboleth_link\lib::$ACTION_LINK_OTHER: // == 2
            // Capture wantsurl before the session is destroyed by logout.
            $wantsurl = $SESSION->wantsurl;
            if (isloggedin() && !isguestuser()) {
                require_logout();
            }
            \auth_shibboleth_link\lib::link_data_store_cache($idpparams);
            // @TODO we will loose the current SESSION->wantsurl here! we should cache it somehow!
            // Store this page as wantsurl.
            $SESSION->wantsurl = $CFG->wwwroot . '/auth/shibboleth_link/index.php?datahash=' . $datahash . '&linkorcreate=' . \auth_shibboleth_link\lib::$ACTION_LINK_CURRENT;
            // Redirect to normal login.
            redirect($CFG->wwwroot . '/login/index.php');
        break;
    }
    die();
}

$pluginconfig   = get_config('auth_shibboleth');
$shibbolethauth = get_auth_plugin('shibboleth');

// Check whether Shibboleth is configured properly
if (empty($pluginconfig->user_attribute)) {
    print_error('shib_not_set_up_error', 'auth_shibboleth');
 }

/// If we can find the Shibboleth attribute, save it in session and return to main login page
if (!empty($_SERVER[$pluginconfig->user_attribute])) {    // Shibboleth auto-login
    $idpparams = \auth_shibboleth_link\lib::link_data_from_server();
    // immediately store data to cache.
    \auth_shibboleth_link\lib::link_data_store_cache($idpparams);
    $link = \auth_shibboleth_link\lib::link_get($idpparams);
    $asklinkorcreate = true; // Triggers if user has a decision to link account.
    $msgs = array();
    if (!empty($link->userid)) {
        \auth_shibboleth_link\lib::link_log_used($link);

        $user = core_user::get_user($link->userid, '*', IGNORE_MISSING);

        if ($replacelink === 1) {
            if ($user->auth == 'shibboleth') {
                // Sorry we can not do this!
                $msgs[] = array(
                    'type' => 'danger',
                    'content' => get_string('auth:warning:userreplacenotallowed', 'auth_shibboleth_link'),
                );
                $asklinkorcreate = false;
            } else {
                $DB->delete_records('auth_shibboleth_link', array('id' => $link->id));
                unset($link);
                $msgs[] = array(
                    'type' => 'success',
                    'content' => get_string('auth:warning:userreplaced', 'auth_shibboleth_link'),
                );
            }
        }
        if ($user->deleted == 1) {
            // Show an error that the linked account has gone.
            $DB->delete_records('auth_shibboleth_link', array('id' => $link->id));
            unset($link);
            $msgs[] = array(
                'type' => 'danger',
                'content' => get_string('auth:warning:usergone', 'auth_shibboleth_link'),
            );
        }

        if (!empty($user->id) && $user->id == $link->userid && $user->deleted == 0) {
            complete_user_login($user);
            \auth_shibboleth_link\lib::check_login(count($msgs) == 0);
        }
    } else {
        // Normally this should not happen.
        // We check here if there is already a shibboleth_link-account with that username.
        $user = $DB->get_record('user', array('auth' => 'shibboleth', 'deleted' => 0, 'username' => $idpparams['idpusername']));
        if (!empty($user->id) && $user->deleted == 0) {
            $useparams = explode(',', get_config('auth_shibboleth_link', 'update_profile_always'));
            if ($user->auth == 'shibboleth') {
                $useparams = array_merge($useparams, explode(',', get_config('auth_shibboleth_link', 'update_profile_shibbonly')));
            }
            foreach($idpparams['userinfo'] AS $field => $value) {
                if (in_array($field, $useparams)) {
                    $user->{$field} = $value;
                }
            }
            $DB->update_record('user', $user);

            $user = core_user::get_user($user->id);
            complete_user_login($user);
            \auth_shibboleth_link\lib::check_login();
        }
    }

    // If we are here then the login did not work. Either no user at all, or it has gone.
    // Store the user info from shibboleth in the cache.
    \auth_shibboleth_link\lib::link_data_store_cache($idpparams);
    // Ask user if we should create an account or link to an existing one!
    $PAGE->set_heading(get_string('auth:linkaccount', 'auth_shibboleth_link'));
    $PAGE->set_title(get_string('auth:linkaccount', 'auth_shibboleth_link'));
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('auth_shibboleth_link/link_or_create', array(
        'asklinkorcreate' => $asklinkorcreate,
        'datahash' => \auth_shibboleth_link\lib::datahash($idpparams),
        'idpusername' => $idpparams['idpusername'],
        'isloggedin' => (isloggedin() && !isguestuser($USER)) ? 1 : 0,
        'msgs' => $msgs,
        'userdata' => array($idpparams['userinfo']),
        'userfullname' => \fullname($USER),
        'wwwroot' => $CFG->wwwroot,
    ));
    echo $OUTPUT->footer();

}
// If we can find any (user independent) Shibboleth attributes but no user
// attributes we probably didn't receive any user attributes
elseif (!empty($_SERVER['HTTP_SHIB_APPLICATION_ID']) || !empty($_SERVER['Shib-Application-ID'])) {
    print_error('shib_no_attributes_error', 'auth_shibboleth' , '', '\''.$pluginconfig->user_attribute.'\', \''.$pluginconfig->field_map_firstname.'\', \''.$pluginconfig->field_map_lastname.'\' and \''.$pluginconfig->field_map_email.'\'');
} else {
    print_error('shib_not_set_up_error', 'auth_shibboleth');
}

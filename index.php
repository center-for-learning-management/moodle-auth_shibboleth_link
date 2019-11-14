<?php

// Mainly taken from auth/shibboleth/index.php

// Designed to be redirected from moodle/login/index.php

require('../../config.php');
require_once($CFG->dirroot . '/auth/shibboleth_link/lib.php');

$datahash = optional_param('datahash', '', PARAM_RAW);
$linkorcreate = optional_param('linkorcreate', 0, PARAM_INT);
$replacelink = optional_param('replacelink', 0, PARAM_INT);
$context = context_system::instance();
$PAGE->set_url('/auth/shibboleth_link/index.php', array('datahash' => $datahash, 'linkorcreate' => $linkorcreate));
$PAGE->set_context($context);

$idpparams = \auth_shibboleth_link\lib::link_data_from_cache();
if ($linkorcreate > 0 && !empty($idpparams['idp'])) {
    $link = \auth_shibboleth_link\lib::link_get($idpparams);
    switch($linkorcreate) {
        case \auth_shibboleth_link\lib::$ACTION_CREATE: // == 1
            // Test if a user with that username already exists.
            $testuser = $DB->get_record('user', array('username' => $idpparams['userinfo']['username']));
            if (!empty($testuser->id)) {
                $PAGE->set_context(\context_system::instance());
                $PAGE->set_heading(get_string('error'));
                $PAGE->set_title(get_string('error'));
                echo $OUTPUT->header();
                echo $OUTPUT->render_from_template('auth_shibboleth_link/alert', array(
                    'content' => get_string('auth:createaccount:userexists', 'auth_shibboleth_link'),
                    'type' => 'warning',
                    'url' => $CFG->wwwroot . '/auth/shibboleth_link/index.php?datahash=' . $datahash . '&linkorcreate=' . \auth_shibboleth_link\lib::$ACTION_LINK_OTHER,
                ));
                echo $OUTPUT->footer();
            } else {
                // Create a user with the information from shibboleth.
                require_once($CFG->dirroot . '/user/lib.php');
                $idpparams['userinfo']['confirmed'] = 1;
                $idpparams['userinfo']['mnethostid'] = 1;
                $userid = \user_create_user($idpparams['userinfo']);
                if (!empty($userid)) {
                    $user = $DB->get_record('user', array('id' => $userid));
                    $user->auth = 'shibboleth_link';
                    $DB->update_record('user', $user);
                    \complete_user_login($user);
                    \auth_shibboleth_link\lib::link_store();
                    \auth_shibboleth_link\lib::check_login();
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
    $link = \auth_shibboleth_link\lib::link_get($idpparams);

    $msgs = array();
    if (!empty($link->userid)) {
        \auth_shibboleth_link\lib::link_log_used($link);

        $user = core_user::get_user($link->userid, '*', IGNORE_MISSING);
        if ($replacelink === 1) {
            if ($user->auth == 'shibboleth_link') {
                $msgs[] = array(
                    'type' => 'warning',
                    'content' => get_string('auth:warning:userreplacenotallowed', 'auth_shibboleth_link'),
                );
            } else {
                $DB->delete_records('auth_shibboleth_link', array('id' => $link->id));
                unset($link);
                $msgs[] = array(
                    'type' => 'warning',
                    'content' => get_string('auth:warning:userreplaced', 'auth_shibboleth_link'),
                );
            }
        } else {
            // Show an error that the linked account has gone.
            $DB->delete_records('auth_shibboleth_link', array('id' => $link->id));
            unset($link);
            $msgs[] = array(
                'type' => 'warning',
                'content' => get_string('auth:warning:usergone', 'auth_shibboleth_link'),
            );
        }
        if (!empty($user->id) && $user->id == $link->userid) {
            complete_user_login($user);
            \auth_shibboleth_link\lib::check_login();
        }
    }

    // If we are here then the login did not work. Either no user at all, or it has gone.
    // Store the user info from shibboleth in the cache.
    \auth_shibboleth_link\lib::link_data_store_cache($idpparams);
    // Ask user if we should create an account or link to an existing one!
    $PAGE->set_context(context_system::instance());
    $PAGE->set_pagelayout('dashboard');
    $PAGE->set_heading(get_string('auth:linkaccount', 'auth_shibboleth_link'));
    $PAGE->set_title(get_string('auth:linkaccount', 'auth_shibboleth_link'));
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('auth_shibboleth_link/link_or_create', array(
        'datahash' => \auth_shibboleth_link\lib::datahash($idpparams),
        'idpusername' => $idpparams['idpusername'],
        'isloggedin' => (isloggedin() && !isguestuser($USER)) ? 1 : 0,
        'msgs' => $msgs,
        'userdata' => array($idpparams['userinfo']),
        'userfullname' => \fullname($USER),
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

<?php

// Mainly taken from auth/shibboleth/index.php

// Designed to be redirected from moodle/login/index.php

require('../../config.php');
require_once($CFG->dirroot . '/auth/shibboleth_link/lib.php');

$linkorcreate = optional_param('linkorcreate', 0, PARAM_INT);
$context = context_system::instance();
$PAGE->set_url('/auth/shibboleth_link/index.php', array('linkorcreate' => $linkorcreate));
$PAGE->set_context($context);


if ($linkorcreate > 0) {
    $idpparams = \auth_shibboleth_link\lib::link_data_from_cache();
    $link = \auth_shibboleth_link\lib::link_get($idpparams);
    switch($linkorcreate) {
        case \auth_shibboleth_link\lib::$ACTION_CREATE:
            // Create a user with the information from shibboleth.
            echo "Would create user based on this information:";
            print_r($idpparams);
            die();

            // create user
            $user = new stdClass(); // This should be the user object after creation.
            complete_user_login($user);
            \auth_shibboleth_link\lib::check_login();
        break;
        case \auth_shibboleth_link\lib::$ACTION_LINK_CURRENT:
            if (isloggedin() && !isguestuser()) {
                // Link user.
                \auth_shibboleth_link\lib::link_store();
                // Check user status and redirect.
                \auth_shibboleth_link\lib::check_login();
            } else {
                // We logged out in the meanwhile. Go to login page.
                $SESSION->wantsurl = $CFG->wwwroot . '/auth/shibboleth_link/index.php?linkorcreate=' . \auth_shibboleth_link\lib::$ACTION_LINK_CURRENT;
                // Redirect to normal login.
                redirect($CFG->wwwroot . '/login/index.php');
            }
        break;
        case \auth_shibboleth_link\lib::$ACTION_LINK_OTHER:
            // Capture wantsurl before the session is destroyed by logout.
            $wantsurl = $SESSION->wantsurl;
            if (isloggedin() && !isguestuser()) {
                require_logout();
            }
            // @TODO we will loose the current SESSION->wantsurl here! we should cache it somehow!
            // Store this page as wantsurl.
            $SESSION->wantsurl = $CFG->wwwroot . '/auth/shibboleth_link/index.php?linkorcreate=' . \auth_shibboleth_link\lib::$ACTION_LINK_CURRENT;
            // Redirect to normal login.
            redirect($CFG->wwwroot . '/login/index.php');
        break;
    }
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
        if (!empty($user->id) && $user->id == $link->userid) {
            complete_user_login($user);
            \auth_shibboleth_link\lib::check_login();
        } else {
            // Show an error that the linked account has gone.
            $DB->delete_records('auth_shibboleth_link', $idpparams);
            $msgs[] = array(
                'type' => 'warning',
                'content' => get_string('auth:warning:usergone', 'auth_shibboleth_link'),
            );
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
        'idpusername' => $idpparams['idpusername'],
        'isloggedin' => (isloggedin() && !isguestuser($USER)) ? 1 : 0,
        'msgs' => $msgs,
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

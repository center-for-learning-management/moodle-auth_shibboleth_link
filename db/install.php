<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_auth_shibboleth_link_install() {
    global $DB;

    if ($DB->get_dbfamily() === 'mysql') {
        $DB->execute("ALTER TABLE {auth_shibboleth_link}
            MODIFY idp VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
            MODIFY idpusername VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT ''");
    }

    return true;
}

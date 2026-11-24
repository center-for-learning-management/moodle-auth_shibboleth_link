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
 * PHPUnit tests for \auth_shibboleth_link\lib.
 *
 * @package    auth_shibboleth_link
 * @copyright  2026 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_shibboleth_link;

/**
 * Unit tests for helper methods in \auth_shibboleth_link\lib.
 *
 * @package    auth_shibboleth_link
 * @copyright  2026 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \auth_shibboleth_link\lib
 */
final class lib_test extends \advanced_testcase {
    /**
     * Test storing and retrieving IdP parameters in $SESSION.
     */
    public function test_idpparams_store_and_get(): void {
        $this->resetAfterTest();

        $this->assertSame([], lib::idpparams_get());

        $params = [
            'idp' => 'https://bildung.gv.at/idp/shibboleth',
            'idpusername' => '00123456@bildung.gv.at',
            'userinfo' => [
                'firstname' => 'Max',
                'lastname' => 'Mustermann',
                'email' => 'max@example.at',
            ],
        ];
        lib::idpparams_store($params);

        $this->assertSame($params, lib::idpparams_get());
    }

    /**
     * Test creating, retrieving (including leading-zero matching), updating, and logging a link.
     */
    public function test_link_store_get_and_log_used(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Max', 'lastname' => 'Mustermann']);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Erika', 'lastname' => 'Musterfrau']);

        $params = [
            'idp' => 'https://bildung.gv.at/idp/shibboleth',
            'idpusername' => '987654',
            'userinfo' => [
                'firstname' => 'Max',
                'lastname' => 'Mustermann',
                'email' => 'max@example.at',
            ],
        ];
        lib::idpparams_store($params);

        // Create initial link for user1.
        $linkid = lib::link_store($user1);
        $this->assertNotEmpty($linkid);

        // Retrieve using exact idpusername.
        $fetched = lib::link_get($params);
        $this->assertNotFalse($fetched);
        $this->assertEquals($user1->id, $fetched->userid);
        $this->assertEquals('Max', $fetched->idpfirstname);
        $this->assertEquals(lib::SOURCE_USER, $fetched->source);

        // Retrieve when stored value has leading zeros stripped or vice versa.
        $trimmedparams = $params;
        $trimmedparams['idpusername'] = '0000987654';
        $fetchedtrimmed = lib::link_get($trimmedparams);
        $this->assertNotFalse($fetchedtrimmed);
        $this->assertEquals($linkid, $fetchedtrimmed->id);

        // Re-link the same IdP identity to user2 (updates existing record rather than duplicating).
        $params['userinfo']['firstname'] = 'Erika';
        $params['userinfo']['lastname'] = 'Musterfrau';
        $params['userinfo']['email'] = 'erika@example.at';
        lib::idpparams_store($params);

        $updatedid = lib::link_store($user2);
        $this->assertEquals($linkid, $updatedid);
        $this->assertEquals(1, $DB->count_records('auth_shibboleth_link'));

        $updated = lib::link_get($params);
        $this->assertEquals($user2->id, $updated->userid);
        $this->assertEquals('Erika', $updated->idpfirstname);
        $this->assertEquals('erika@example.at', $updated->idpemail);
    }

    /**
     * Test reading Shibboleth attributes from $_SERVER via idpparams_from_server().
     */
    public function test_idpparams_from_server(): void {
        $this->resetAfterTest();

        set_config('user_attribute', 'HTTP_SAMLSUBJECTID', 'auth_shibboleth');
        set_config('field_map_firstname', 'HTTP_GIVENNAME', 'auth_shibboleth');
        set_config('field_map_lastname', 'HTTP_SN', 'auth_shibboleth');
        set_config('field_map_email', 'HTTP_MAIL', 'auth_shibboleth');

        $origserver = $_SERVER;
        try {
            $_SERVER['Shib-Identity-Provider'] = 'https://bildung.gv.at/idp/shibboleth';
            $_SERVER['HTTP_SAMLSUBJECTID'] = 'testuser@bildung.gv.at';
            $_SERVER['HTTP_GIVENNAME'] = 'Georg';
            $_SERVER['HTTP_SN'] = 'Glas';
            $_SERVER['HTTP_MAIL'] = 'georg@example.at';

            $result = lib::idpparams_from_server();

            $this->assertEquals('https://bildung.gv.at/idp/shibboleth', $result['idp']);
            $this->assertEquals('testuser@bildung.gv.at', $result['idpusername']);
            $this->assertEquals('Georg', $result['userinfo']['firstname']);
            $this->assertEquals('Glas', $result['userinfo']['lastname']);
            $this->assertEquals('georg@example.at', $result['userinfo']['email']);
        } finally {
            $_SERVER = $origserver;
        }
    }

    /**
     * Test check_login() redirect URL calculation and Shibboleth session ID capture.
     */
    public function test_check_login_url_resolution(): void {
        global $CFG, $SESSION;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'test@example.at',
        ]);
        $this->setUser($user);

        $origserver = $_SERVER;
        try {
            $mocksessionid = 'mocksession123';
            $_SERVER['Shib-Session-ID'] = $mocksessionid;

            // Case 1: $SESSION->wantsurl inside wwwroot is honored and cleared.
            $SESSION->wantsurl = $CFG->wwwroot . '/course/view.php?id=42';
            $url = lib::check_login(false);
            $this->assertEquals($CFG->wwwroot . '/course/view.php?id=42', $url);
            $this->assertFalse(property_exists($SESSION, 'wantsurl'));
            $this->assertEquals($mocksessionid, $SESSION->shibboleth_session_id);

            // Case 2: External wantsurl is ignored and defaults to site root or /my/.
            $SESSION->wantsurl = 'https://evil.example.org/phish';
            set_config('defaulthomepage', HOMEPAGE_SITE);
            $url = lib::check_login(false);
            $this->assertEquals($CFG->wwwroot . '/', $url);

            // Case 3: HOMEPAGE_MY redirects to /my/.
            set_config('defaulthomepage', HOMEPAGE_MY);
            $url = lib::check_login(false);
            $this->assertEquals($CFG->wwwroot . '/my/', $url);
        } finally {
            $_SERVER = $origserver;
        }
    }

    /**
     * Data provider of malicious or external redirect URLs that must be rejected (#18).
     *
     * @return array[]
     */
    public static function invalid_logout_redirect_provider(): array {
        return [
            'empty string' => [''],
            'external https domain' => ['https://evil.example.org/phish'],
            'external http domain' => ['http://evil.example.org/phish'],
            'protocol-relative URL' => ['//evil.example.org/phish'],
            'triple-slash protocol-relative' => ['///evil.example.org/phish'],
            'javascript scheme' => ['javascript:alert(1)'],
            'newline header injection' => ["https://example.com/\r\nSet-Cookie: evil=1"],
        ];
    }

    /**
     * Test that validate_logout_redirect() rejects external/malicious URLs (#18).
     *
     * @dataProvider invalid_logout_redirect_provider
     * @param string $badurl
     */
    public function test_validate_logout_redirect_rejects_external_urls(string $badurl): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        lib::validate_logout_redirect($badurl);
    }

    /**
     * Test that validate_logout_redirect() accepts valid local URLs (#18).
     */
    public function test_validate_logout_redirect_accepts_local_urls(): void {
        global $CFG;
        $this->resetAfterTest();

        $relative = lib::validate_logout_redirect('/login/index.php');
        $this->assertEquals($CFG->wwwroot . '/login/index.php', $relative->out(false));

        $absolute = lib::validate_logout_redirect($CFG->wwwroot . '/my/');
        $this->assertEquals($CFG->wwwroot . '/my/', $absolute->out(false));
    }

    /**
     * Test that link_unlink() deletes only the owner's link and blocks IDOR attempts (#17).
     */
    public function test_link_unlink_ownership_and_deletion(): void {
        global $DB;
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $attacker = $this->getDataGenerator()->create_user();

        lib::idpparams_store([
            'idp' => 'https://bildung.gv.at/idp/shibboleth',
            'idpusername' => 'owner.user@bildung.gv.at',
            'userinfo' => [
                'firstname' => 'Owner',
                'lastname' => 'User',
                'email' => 'owner@example.at',
            ],
        ]);
        $linkid = lib::link_store($owner);

        // Attacker cannot unlink owner's record.
        try {
            lib::link_unlink($linkid, $attacker->id);
            $this->fail('Expected moodle_exception when unlinking another user\'s link.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('not allowed', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('auth_shibboleth_link', ['id' => $linkid]));

        // Owner can unlink their own record.
        lib::link_unlink($linkid, $owner->id);
        $this->assertFalse($DB->record_exists('auth_shibboleth_link', ['id' => $linkid]));
    }
}

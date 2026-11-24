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
 * PHPUnit tests for auth_plugin_shibboleth_link and privacy provider.
 *
 * @package    auth_shibboleth_link
 * @copyright  2026 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_shibboleth_link;

use core_privacy\local\metadata\collection;

/**
 * Unit tests for auth_plugin_shibboleth_link and privacy metadata.
 *
 * @package    auth_shibboleth_link
 * @copyright  2026 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \auth_plugin_shibboleth_link
 * @covers     \auth_shibboleth_link\privacy\provider
 */
final class auth_plugin_test extends \advanced_testcase {
    /**
     * Test auth plugin instantiation, direct login rejection, and IdP list generation.
     */
    public function test_auth_plugin_behavior(): void {
        $this->resetAfterTest();

        $auth = get_auth_plugin('shibboleth_link');
        $this->assertInstanceOf(\auth_plugin_shibboleth_link::class, $auth);
        $this->assertFalse($auth->user_login('anyuser', 'anypassword'));

        // Empty user_attribute returns empty IdP list.
        unset_config('user_attribute', 'auth_shibboleth');
        $this->assertSame([], $auth->loginpage_idp_list('/'));

        // Configured user_attribute and organization_selection returns Bildungsportal IdP entry.
        set_config('user_attribute', 'HTTP_SAMLSUBJECTID', 'auth_shibboleth');
        set_config(
            'organization_selection',
            "https://bildung.gv.at/idp/shibboleth, Bildungsportal\nhttps://other.idp/shib, Other",
            'auth_shibboleth'
        );

        $idps = $auth->loginpage_idp_list('/');
        $this->assertCount(1, $idps);
        $this->assertEquals('Bildungsportal', $idps[0]['name']);
        $this->assertStringContainsString('/auth/shibboleth_link/login.php', $idps[0]['url']->out(false));
        $this->assertEquals('https://bildung.gv.at/idp/shibboleth', $idps[0]['url']->get_param('idp'));
    }

    /**
     * Test that privacy metadata registers the auth_shibboleth_link table without missing lang strings.
     */
    public function test_privacy_metadata(): void {
        $collection = new collection('auth_shibboleth_link');
        $result = \auth_shibboleth_link\privacy\provider::get_metadata($collection);

        $items = $result->get_collection();
        $this->assertCount(1, $items);
        $this->assertEquals('auth_shibboleth_link', $items[0]->get_name());
    }

    /**
     * Guard against regressions in security-sensitive entrypoints (#17, #20).
     */
    public function test_security_invariants_in_entrypoints(): void {
        global $CFG;
        $plugindir = $CFG->dirroot . '/auth/shibboleth_link';

        // #20: Ensure no raw header('Location') calls exist; Moodle's redirect() must be used.
        $phpfiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($plugindir));
        foreach ($phpfiles as $file) {
            if ($file->getExtension() !== 'php' || strpos($file->getPathname(), '/tests/') !== false) {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            $this->assertDoesNotMatchRegularExpression(
                '/header\s*\(\s*[\'"]Location\s*:/i',
                $code,
                'Raw header("Location:") found in ' . $file->getFilename() . '; use redirect() instead (#20).'
            );
        }

        // #17: Ensure manage_linked_users.php enforces require_sesskey() on unlink.
        $managecode = file_get_contents($plugindir . '/manage_linked_users.php');
        $this->assertStringContainsString('require_sesskey();', $managecode);
    }
}

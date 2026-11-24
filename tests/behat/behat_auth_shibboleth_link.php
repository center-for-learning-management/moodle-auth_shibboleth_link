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
 * Behat step definitions for simulating Shibboleth SAML logins in auth_shibboleth_link.
 *
 * @package    auth_shibboleth_link
 * @copyright  2026 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;

/**
 * Step definitions to mock Shibboleth SP $_SERVER attributes during Behat UAT.
 *
 * @package    auth_shibboleth_link
 * @copyright  2026 Zentrum für Lernmanagement (http://www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_auth_shibboleth_link extends behat_base {
    /**
     * Configures simulated Shibboleth SP $_SERVER variables for subsequent requests.
     *
     * @Given /^the Shibboleth SP provides the following attributes:$/
     * @param TableNode $table Key-value table of $_SERVER attributes.
     */
    public function the_shibboleth_sp_provides_the_following_attributes(TableNode $table): void {
        $attrs = [];
        foreach ($table->getRowsHash() as $key => $value) {
            $attrs[$key] = $value;
        }
        set_config('behat_mock_server', json_encode($attrs), 'auth_shibboleth_link');
    }

    /**
     * Navigates the browser to the Shibboleth link callback endpoint.
     *
     * @When /^I complete the Shibboleth login callback$/
     */
    public function i_complete_the_shibboleth_login_callback(): void {
        $this->getSession()->visit($this->locate_path('/auth/shibboleth_link/index.php'));
    }
}

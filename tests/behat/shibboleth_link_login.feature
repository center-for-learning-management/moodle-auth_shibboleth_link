@auth @auth_shibboleth_link
Feature: Shibboleth link account creation, linking, and error handling
  In order to log in via an external Shibboleth Identity Provider
  As a user
  I need to be able to create a new account, link an existing account, and see clear errors if attributes are missing

  Background:
    Given the following config values are set as admin:
      | config              | value                             | plugin          |
      | user_attribute      | HTTP_SAMLSUBJECTID                | auth_shibboleth |
      | field_map_firstname | HTTP_GIVENNAME                    | auth_shibboleth |
      | field_map_lastname  | HTTP_SN                           | auth_shibboleth |
      | field_map_email     | HTTP_MAIL                         | auth_shibboleth |
      | auth                | shibboleth,shibboleth_link,manual |                 |
    And the following "users" exist:
      | username | password | firstname | lastname   | email            |
      | existing | existing | Erika     | Musterfrau | erika@example.at |

  @javascript
  Scenario: First-time Shibboleth user creates a new local account and auto-logs in on next visit
    Given the Shibboleth SP provides the following attributes:
      | Shib-Identity-Provider | https://bildung.gv.at/idp/shibboleth |
      | Shib-Session-ID        | mocksession001                       |
      | HTTP_SAMLSUBJECTID     | max.mustermann@bildung.gv.at         |
      | HTTP_GIVENNAME         | Max                                  |
      | HTTP_SN                | Mustermann                           |
      | HTTP_MAIL              | max@example.at                       |
    When I complete the Shibboleth login callback
    Then I should see "Max Mustermann"
    And I should see "Create account"
    When I follow "Create account"
    Then I should see "Account created."
    And I should see "Max Mustermann"
    # Log out and verify second SSO login skips the prompt and logs straight in.
    When I log out
    And I complete the Shibboleth login callback
    Then I should not see "Create account"
    And I should see "Max Mustermann"

  @javascript
  Scenario: Shibboleth user links identity to an existing Moodle account and can unlink it
    Given the Shibboleth SP provides the following attributes:
      | Shib-Identity-Provider | https://bildung.gv.at/idp/shibboleth |
      | Shib-Session-ID        | mocksession002                       |
      | HTTP_SAMLSUBJECTID     | erika.linked@bildung.gv.at           |
      | HTTP_GIVENNAME         | Erika                                |
      | HTTP_SN                | Musterfrau                           |
      | HTTP_MAIL              | erika.idp@bildung.gv.at              |
    And I log in as "existing"
    When I complete the Shibboleth login callback
    Then I should see "Link current account"
    When I follow "Link current account"
    And I am on the "Manage linked users" "auth_shibboleth_link > Manage" page
    Then I should see "bildung.gv.at"
    And I should see "erika.idp@bildung.gv.at"
    # Verify forged unlink without a valid sesskey is blocked (#17).
    And visiting "/auth/shibboleth_link/manage_linked_users.php?action=unlink&link_id=1&sesskey=invalidsesskey" should display the error "invalidsesskey"
    When I am on the "Manage linked users" "auth_shibboleth_link > Manage" page
    Then I should see "erika.idp@bildung.gv.at"
    # Unlink legitimately via the POST form with valid sesskey.
    When I click on "Unlink" "button" in the "erika.idp@bildung.gv.at" "table_row"
    And I confirm the dialogue
    Then I should not see "erika.idp@bildung.gv.at"

  @javascript
  Scenario: Front-channel logout rejects external return URLs to prevent open redirects
    Given I log in as "existing"
    Then visiting "/auth/shibboleth_link/logout.php?action=logout&return=https://evil.example.org/phish" should display the error "invalid_wantsurl"

  @javascript
  Scenario: Missing Shibboleth user attribute renders Moodle exception instead of fatal error
    Given the Shibboleth SP provides the following attributes:
      | Shib-Application-ID | default |
    Then the Shibboleth login callback should display the error "HTTP_SAMLSUBJECTID"

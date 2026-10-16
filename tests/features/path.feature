Feature: Path and URL assertions

  Scenario: Paths and query parameters are asserted
    When I request "/data.json?filter=recent&page=2"
    Then the path should be "/data.json"
    And the path should not be "/other.json"
    And current url should have the "filter" parameter
    And current url should have the "filter" parameter with the "recent" value
    And current url should not have the "missing" parameter
    And current url should not have the "filter" parameter with the "old" value

  Scenario: The front page path is recognized
    When I request "/"
    Then the path should be "<front>"

  Scenario: History navigation goes back
    When I request "/data.json"
    And I request "/index.html"
    And I go back
    Then the path should be "/data.json"

  Scenario: Basic authentication grants access
    Given the basic authentication with the username "user" and the password "pass"
    When I request "/auth"
    Then the response status should be 200

  Scenario: A redirect is followed
    When I request "/redirect"
    Then the response status should be 200
    And the response should be in JSON format

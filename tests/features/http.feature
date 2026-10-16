Feature: HTTP response assertions

  Scenario: A JSON response is asserted
    When I request "/data.json"
    Then the response status should be 200
    And the response should be in JSON format
    And the JSON path "$.user.name" should exist
    And the JSON path "$.user.name" should be equal to "Ada"
    And the JSON path "$.user.roles[0]" should be equal to "admin"

  Scenario: A missing route reports its status
    When I request "/missing"
    Then the response status should be 404

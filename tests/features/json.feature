Feature: JSON assertions

  Scenario: A fixture file supplies the response JSON
    Given the response JSON from the file "data.json"
    Then the response should be in JSON format
    And the JSON path "$.user.name" should exist
    And the JSON path "$.user.email" should contain "example.com"
    And the JSON path "$.user.email" should not contain "other.org"
    And the JSON path "$.user.email" should match "/^[^@]+@example\.com$/"
    And the JSON path "$.user.name" should not match "/^admin@/"
    And the JSON path "$.missing" should not exist
    And the JSON path "$.user.name" should not be equal to "Grace"
    And the JSON path "$.nothing" should be null
    And the JSON path "$.active" should be true
    And the JSON path "$.archived" should be false
    And the JSON path "$.user.roles" should have 2 elements
    And the JSON path "$.user.name" should have 1 element

  Scenario: Inline content supplies the response JSON
    Given the response JSON content is the following:
      """
      {"user": {"name": "Ada", "roles": ["admin"]}}
      """
    Then the response should be in JSON format
    And the JSON path "$.user.roles[0]" should be equal to "admin"

  Scenario: A JSON Schema validates the document
    Given the response JSON from the file "data.json"
    Then the response should match the following JSON schema:
      """
      {"type": "object", "required": ["user"], "properties": {"user": {"type": "object", "required": ["name"]}}}
      """
    And the response should match the JSON schema in the file "user.schema.json"

  Scenario: A non-JSON response is reported
    Given the response JSON content is the following:
      """
      <html><body>Hello</body></html>
      """
    Then the response should not be in JSON format

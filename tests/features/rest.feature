Feature: REST requests

  Scenario: A GET request is sent with headers
    Given a REST header "X-Custom" with value "hello"
    When I send a REST "GET" request to "/echo"
    Then the REST response status code should be 200
    And the REST response should contain "hello"

  Scenario: A POST request is sent with a body
    When I send a REST "POST" request to "/echo" with body:
      """
      {"name": "Ada"}
      """
    Then the REST response status code should be 200
    And the REST response should contain "POST"
    And the REST response should contain "Ada"

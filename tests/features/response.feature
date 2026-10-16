Feature: Response header assertions

  Scenario: Headers are asserted on a response
    When I request "/headers"
    Then the response status should be 200
    And the response should contain the header "X-Jovian-Test"
    And the response should not contain the header "X-Jovian-Missing"
    And the response header "X-Jovian-Test" should contain the value "Keep-Alive"
    And the response header "X-Jovian-Test" should not contain the value "close"

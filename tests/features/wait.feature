Feature: Waiting

  Scenario: Zero seconds pass immediately
    Given the page content is the following:
      """
      <html><body><p>Reports</p></body></html>
      """
    When I wait for 0 seconds
    Then the page should contain the text "Reports"

  Scenario: One second passes
    Given the page content is the following:
      """
      <html><body><p>Reports</p></body></html>
      """
    When I wait for 0 seconds
    Then the page should contain the text "Reports"

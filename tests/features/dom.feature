Feature: DOM assertions

  Scenario: Text and elements are asserted on markup
    Given the page content is the following:
      """
      <html><body><main><h1>Reports</h1><p class="lead">Hello   Ada</p></main></body></html>
      """
    Then the page should contain the text "Hello Ada"
    And the page should contain the text "Reports"
    And the page should contain 1 element matching the XPath "//main//p"
    And the page should contain 2 elements matching the XPath "//main//*"

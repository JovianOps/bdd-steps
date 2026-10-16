Feature: Table assertions

  Background:
    Given the page content is the following:
      """
      <html><body><main><table class="mytable">
      <thead><tr><th>Title</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
      <tr><td>Article 1</td><td>Published by admin</td><td>2024-01-15</td></tr>
      <tr><td>Article 2</td><td>Draft</td><td>2024-02-20</td></tr>
      <tr><td>Article title</td><td>Published</td><td>2024-03-10</td></tr>
      </tbody>
      </table></main></body></html>
      """

  Scenario: Rows and columns are counted
    Then the table ".mytable" should have 3 rows
    And the table ".mytable" should have 3 columns
    And the table ".mytable" should contain the following columns:
      | Title | Status | Date |
    And the table ".mytable" should not be empty

  Scenario: An empty table is reported
    Given the page content is the following:
      """
      <html><body><table class="empty"><thead><tr><th>Title</th></tr></thead><tbody></tbody></table></body></html>
      """
    Then the table ".empty" should be empty

  Scenario: Sorting is asserted
    Then the table ".mytable" should be sorted by "Date" in "ascending" order

  Scenario: Expected rows are asserted
    Then the table ".mytable" should contain the following rows:
      | Title     | Status | Date       |
      | Article 1 | Published by admin | 2024-01-15 |
      | Article 2 | Draft  | 2024-02-20 |

  Scenario: A row is asserted by its text
    Then the "Article title" row should contain the following:
      | Published  |
      | 2024-03-10 |

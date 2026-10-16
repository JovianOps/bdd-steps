Feature: XML assertions

  Scenario: A fixture file supplies the response XML
    Given the response content from the file "data.xml"
    Then the response should be in XML format
    And the XML element "//book" should exist
    And the XML element "/library/book[@id='123']" should exist
    And the XML element "//nonexistent" should not exist
    And the XML element "//title" should be equal to "The Great Adventure"
    And the XML element "//title" should not be equal to "Wrong Title"
    And the XML element "//description" should contain "sample book"
    And the XML element "//description" should not contain "another story"
    And the XML attribute "id" on element "/library/book[1]" should exist
    And the XML attribute "missing" on element "/library/book[1]" should not exist
    And the XML attribute "id" on element "/library/book[1]" should be equal to "123"
    And the XML attribute "id" on element "/library/book[1]" should not be equal to "999"
    And the XML attribute "id" on element "/library/book[1]" should contain "12"
    And the XML attribute "id" on element "/library/book[1]" should not contain "99"
    And the XML element "//book" should have 2 elements
    And the XML should use the namespace "http://example.com/book"
    And the XML should not use the namespace "http://example.com/other"

  Scenario: Inline content supplies the response XML
    Given the response content is the following:
      """
      <?xml version="1.0"?><root><item>value</item></root>
      """
    Then the response should be in XML format
    And the XML element "//item" should be equal to "value"

  Scenario: Malformed XML is reported
    Given the response content is the following:
      """
      <root><unclosed>
      """
    Then the response should not be in XML format

  Scenario: An XSD schema validates the document
    Given the response content is the following:
      """
      <?xml version="1.0"?><library><book id="1"><title>T</title><author>A</author><description>D</description></book></library>
      """
    Then the response should match the following XSD schema:
      """
      <?xml version="1.0"?>
      <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
        <xs:element name="library">
          <xs:complexType><xs:sequence>
            <xs:element name="book" maxOccurs="unbounded">
              <xs:complexType><xs:sequence>
                <xs:element name="title" type="xs:string"/>
                <xs:element name="author" type="xs:string"/>
                <xs:element name="description" type="xs:string"/>
              </xs:sequence>
              <xs:attribute name="id" type="xs:string" use="required"/>
              </xs:complexType>
            </xs:element>
          </xs:sequence></xs:complexType>
        </xs:element>
      </xs:schema>
      """
    And the response should match the XSD schema in the file "book.xsd"

  Scenario: A DTD validates the document
    Given the response content is the following:
      """
      <?xml version="1.0"?><library><book><title>T</title></book></library>
      """
    Then the response should match the following DTD:
      """
      <!ELEMENT library (book+)>
      <!ELEMENT book (title)>
      <!ELEMENT title (#PCDATA)>
      """

  Scenario: A RelaxNG schema validates the document
    Given the response content is the following:
      """
      <?xml version="1.0"?><library><book><title>T</title></book></library>
      """
    Then the response should match the following RelaxNG schema:
      """
      <grammar xmlns="http://relaxng.org/ns/structure/1.0">
        <start><element name="library">
          <oneOrMore><element name="book">
            <element name="title"><text/></element>
          </element></oneOrMore>
        </element></start>
      </grammar>
      """

  Scenario: Feeds are validated structurally
    Given the response content from the file "feed-rss.xml"
    Then the response should be a valid RSS feed
    Given the response content from the file "feed-atom.xml"
    Then the response should be a valid Atom feed

# netscape-bookmark-parser
[![license](https://img.shields.io/github/license/kafene/netscape-bookmark-parser.svg?style=flat-square)](https://opensource.org/licenses/MIT)

This repo is forked from [kafene/netscape-bookmark-parser](https://github.com/kafene/netscape-bookmark-parser) and added nested output support and OO data object.

## About
This library provides a generic `NetscapeBookmarkParser` class that is able
of parsing Netscape bookmark export files.

The motivations behind developing this parser are the following:
- the [Netscape format](https://msdn.microsoft.com/en-us/library/aa753582%28v=vs.85%29.aspx)
  has a very loose specification:
  no [DTD](https://en.wikipedia.org/wiki/Document_type_definition)
  nor [XSL stylesheet](https://en.wikipedia.org/wiki/XSL)
  to constrain how data is formatted
- software and web services export bookmarks using a wild variety of attribute
  names and values
- using standard SAX or DOM parsers is thus not straightforward.

How it works:
- the input bookmark file is trimmed and sanitized to improve parsing results
- the resulting data is then parsed using [PCRE](http://www.pcre.org/) patterns
  to match attributes and values corresponding to the most likely:
    - attribute names: `description` vs. `note`, `tags` vs. `labels`, `date` vs. `time`, etc.
    - data formats: `comma,separated,tags` vs. `space separated labels`,
      UNIX epochs vs. human-readable dates, newlines & carriage returns, etc.
- an associative array containing all successfully parsed links with their
  attributes is returned

## Example
Script:
```php
<?php
require_once 'NetscapeBookmarkParser.php';

$parser = new NetscapeBookmarkParser();
$bookmarks = $parser->parseFile('./tests/input/netscape_basic.htm');
var_dump($bookmarks);
```

Output:
```
object(Folder)#273 (3) {
  ["title"]=>
  string(4) "Root"
  ["parent"]=>
  NULL
  ["content"]=>
  array(1) {
    [0]=>
    object(Folder)#279 (3) {
      ["title"]=>
      string(13) "Bookmarks bar"
      ["parent"]=>
      *RECURSION*
      ["content"]=>
      array(8) {
        [0]=>
        object(Page)#277 (6) {
          ["uri"]=>
          string(25) "https://private.tld"
          ["title"]=>
          string(9) "Secret stuff"
          ["note"]=>
          string(0) "Super-secret stuff you're not supposed to know about"
          ["tags"]=>
          string(13) "bookmarks bar"
          ["time"]=>
          int(1563348673)
          ["pub"]=>
          string(1) "0"
        }
        [0]=>
        object(Page)#277 (6) {
          ["uri"]=>
          string(25) "http://public.tld"
          ["title"]=>
          string(9) "Public stuff"
          ["note"]=>
          string(0) ""
          ["tags"]=>
          string(13) "bookmarks bar"
          ["time"]=>
          int(1563348673)
          ["pub"]=>
          string(1) "0"
        }
      }
    }
  }
}
```

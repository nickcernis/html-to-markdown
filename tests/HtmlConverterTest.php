<?php

declare(strict_types=1);

namespace League\HTMLToMarkdown\Test;

use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\Converter\StrikethroughConverter;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\Environment;
use League\HTMLToMarkdown\HtmlConverter;
use PHPUnit\Framework\TestCase;

class HtmlConverterTest extends TestCase
{
    /**
     * @param array<string, mixed> $options
     * @param ConverterInterface[] $converters
     */
    private function assertHtmlGivesMarkdown(string $html, string $expectedMarkdown, array $options = [], array $converters = []): void
    {
        $markdown = new HtmlConverter($options);
        foreach ($converters as $converter) {
            $markdown->getEnvironment()->addConverter($converter);
        }

        $result = $markdown->convert($html);
        $this->assertSame($expectedMarkdown, $result);
    }

    public function testEmptyInput(): void
    {
        $this->assertHtmlGivesMarkdown('', '');
        $this->assertHtmlGivesMarkdown('     ', '');
    }

    public function testPlainText(): void
    {
        $this->assertHtmlGivesMarkdown('test', 'test');
        $this->assertHtmlGivesMarkdown('<p>test</p>', 'test');

        //expected result is in the comment for better readability
        $this->assertHtmlGivesMarkdown('<p>*test*</p>', '\\*test\\*'); // \*test\*
        $this->assertHtmlGivesMarkdown('<p>_test_</p>', '\\_test\\_'); // \_test\_
        $this->assertHtmlGivesMarkdown('<p>\\*test\\*</p>', '\\\\\\*test\\\\\\*'); // \\\*test\\\*
        $this->assertHtmlGivesMarkdown('<p>test[test]</p>', 'test\\[test\\]'); // test\[test\]

        // Markdown-like syntax in <div> text should be preserved as-is - no escaping
        $this->assertHtmlGivesMarkdown('<div>_test_</div>', '<div>_test_</div>');
        $this->assertHtmlGivesMarkdown('<div>*test*</div>', '<div>*test*</div>');

        $this->assertHtmlGivesMarkdown('<p>\ ` * _ { } [ ] ( ) &gt; > # + - . !</p>', '\\\\ ` \* \_ { } \[ \] ( ) &gt; &gt; # + - . !');
    }

    public function testLineBreaks(): void
    {
        $this->assertHtmlGivesMarkdown("test\nanother line", 'test another line');
        $this->assertHtmlGivesMarkdown("<p>test\nanother line</p>", 'test another line');
        $this->assertHtmlGivesMarkdown("<p>test<br>\nanother line</p>", "test  \nanother line");
        $this->assertHtmlGivesMarkdown("<p>test<br>\n another line</p>", "test  \n another line");
        $this->assertHtmlGivesMarkdown("<p>test<br>\n<em>another</em> line</p>", "test  \n*another* line");
        $this->assertHtmlGivesMarkdown('<p>test<br>another line</p>', "test  \nanother line");
        $this->assertHtmlGivesMarkdown('<p>test<br/>another line</p>', "test  \nanother line");
        $this->assertHtmlGivesMarkdown('<p>test<br />another line</p>', "test  \nanother line");
        $this->assertHtmlGivesMarkdown('<p>test<br  />another line</p>', "test  \nanother line");
        $this->assertHtmlGivesMarkdown('<p>test<br>another line</p>', "test\nanother line", ['hard_break' => true]);
        $this->assertHtmlGivesMarkdown('<p>test<br/>another line</p>', "test\nanother line", ['hard_break' => true]);
        $this->assertHtmlGivesMarkdown('<p>test<br />another line</p>', "test\nanother line", ['hard_break' => true]);
        $this->assertHtmlGivesMarkdown('<p>test<br  />another line</p>', "test\nanother line", ['hard_break' => true]);
        $this->assertHtmlGivesMarkdown('<p>foo</p><table><tr><td>bar</td></tr></table><p>baz</p>', "foo\n\n<table><tr><td>bar</td></tr></table>\n\nbaz");
    }

    public function testHeaders(): void
    {
        $this->assertHtmlGivesMarkdown('<h1>Test</h1>', "Test\n====");
        $this->assertHtmlGivesMarkdown('<h2>Test</h2>', "Test\n----");
        $this->assertHtmlGivesMarkdown('<blockquote><h1>Test</h1></blockquote>', '> # Test');
        $this->assertHtmlGivesMarkdown('<blockquote><h2>Test</h2></blockquote>', '> ## Test');
        $this->assertHtmlGivesMarkdown('<h3>Test</h3>', '### Test');
        $this->assertHtmlGivesMarkdown('<h4>Test</h4>', '#### Test');
        $this->assertHtmlGivesMarkdown('<h5>Test</h5>', '##### Test');
        $this->assertHtmlGivesMarkdown('<h6>Test</h6>', '###### Test');
        $this->assertHtmlGivesMarkdown('<h1></h1>', '');
        $this->assertHtmlGivesMarkdown('<h2></h2>', '');
        $this->assertHtmlGivesMarkdown('<h3></h3>', '');
        $this->assertHtmlGivesMarkdown('<h1># Test</h1>', "\# Test\n=======");
        $this->assertHtmlGivesMarkdown('<h1># Test #</h1>', "\# Test #\n=========");
        $this->assertHtmlGivesMarkdown('<h3>Mismatched Tags</h4>', '### Mismatched Tags');
    }

    public function testSpans(): void
    {
        $this->assertHtmlGivesMarkdown('<em>Test</em>', '*Test*');
        $this->assertHtmlGivesMarkdown('<i>Test</i>', '*Test*');
        $this->assertHtmlGivesMarkdown('<strong>Test</strong>', '**Test**');
        $this->assertHtmlGivesMarkdown('<b>Test</b>', '**Test**');
        $this->assertHtmlGivesMarkdown('<em>Test</em>', '*Test*', ['italic_style' => '*']);
        $this->assertHtmlGivesMarkdown('<em>Italic</em> and a <strong>bold</strong>', '*Italic* and a __bold__', ['italic_style' => '*', 'bold_style' => '__']);
        $this->assertHtmlGivesMarkdown('<i>Test</i>', '_Test_', ['italic_style' => '_']);
        $this->assertHtmlGivesMarkdown('<strong>Test</strong>', '__Test__', ['bold_style' => '__']);
        $this->assertHtmlGivesMarkdown('<b>Test</b>', '__Test__', ['bold_style' => '__']);
        $this->assertHtmlGivesMarkdown('<span>Test</span>', '<span>Test</span>');
        $this->assertHtmlGivesMarkdown('<b>Bold</b> <i>Italic</i>', '**Bold** *Italic*');
        $this->assertHtmlGivesMarkdown('<b>Bold</b><i>Italic</i>', '**Bold***Italic*');
        $this->assertHtmlGivesMarkdown('<em>This is <strong>a test</strong></em>', '*This is **a test***');
        $this->assertHtmlGivesMarkdown('<em>This is </em><strong>a </strong>test', '*This is* **a** test');
        $this->assertHtmlGivesMarkdown('Emphasis with no<em> </em>text<strong> preserves</strong> spaces.', 'Emphasis with no text **preserves** spaces.');
        $this->assertHtmlGivesMarkdown("Emphasis discards<em> \n</em>line breaks", 'Emphasis discards line breaks');
        $this->assertHtmlGivesMarkdown('Emphasis preserves<em><br/></em>HTML breaks', "Emphasis preserves  \nHTML breaks");
    }

    public function testConsecutiveSpans(): void
    {
        $this->assertHtmlGivesMarkdown('<em>Foo</em><em>Bar</em>', '*FooBar*');
        $this->assertHtmlGivesMarkdown('<i>Foo</i><i>Bar</i>', '*FooBar*');
        $this->assertHtmlGivesMarkdown('<em>Foo</em><i>Bar</i><em>Foo</em>', '*FooBarFoo*');
        $this->assertHtmlGivesMarkdown('<strong>Foo</strong><strong>Bar</strong>', '**FooBar**');
        $this->assertHtmlGivesMarkdown('<b>Foo</b><b>Bar</b>', '**FooBar**');
        $this->assertHtmlGivesMarkdown('<strong>Foo</strong><b>Bar</b><strong>Foo</strong>', '**FooBarFoo**');
        $this->assertHtmlGivesMarkdown('<em>Foo</em> <em>Bar</em>', '*Foo* *Bar*');
        $this->assertHtmlGivesMarkdown('<strong>Foo</strong> <strong>Bar</strong>', '**Foo** **Bar**');
        $this->assertHtmlGivesMarkdown('<strong>Foo</strong><b>Bar</b><em>Foo</em>', '**FooBar***Foo*');
    }

    public function testNesting(): void
    {
        $this->assertHtmlGivesMarkdown('<span><span>Test</span></span>', '<span><span>Test</span></span>');
    }

    public function testScript(): void
    {
        $this->assertHtmlGivesMarkdown("<script>alert('test');</script>", "<script>alert('test');</script>");
    }

    public function testImage(): void
    {
        $this->assertHtmlGivesMarkdown('<img src="/path/img.jpg" alt="alt text" title="Title" />', '![alt text](/path/img.jpg "Title")');
    }

    public function testAnchor(): void
    {
        $this->assertHtmlGivesMarkdown('<a href="http://modernnerd.net">http://modernnerd.net</a>', '<http://modernnerd.net>');
        $this->assertHtmlGivesMarkdown('<a href="http://modernnerd.net" title="Title">Modern Nerd</a>', '[Modern Nerd](http://modernnerd.net "Title")');
        $this->assertHtmlGivesMarkdown('<a href="http://modernnerd.net" title="Title">Modern Nerd</a> <a href="http://modernnerd.net" title="Title">Modern Nerd</a>', '[Modern Nerd](http://modernnerd.net "Title") [Modern Nerd](http://modernnerd.net "Title")');
        $this->assertHtmlGivesMarkdown('<a href="http://modernnerd.net"><h3>Modern Nerd</h3></a>', '[### Modern Nerd](http://modernnerd.net)');
        $this->assertHtmlGivesMarkdown('The <a href="http://modernnerd.net">Modern Nerd </a>(MN)', 'The [Modern Nerd ](http://modernnerd.net)(MN)');
        $this->assertHtmlGivesMarkdown('<a href="http://modernnerd.net/" title="Title"><img src="/path/img.jpg" alt="alt text" title="Title"/></a>', '[![alt text](/path/img.jpg "Title")](http://modernnerd.net/ "Title")');
        $this->assertHtmlGivesMarkdown('<a href="http://modernnerd.net/" title="Title"><img src="/path/img.jpg" alt="alt text" title="Title"/> Test</a>', '[![alt text](/path/img.jpg "Title") Test](http://modernnerd.net/ "Title")');

        // Placeholder links and fragment identifiers
        $this->assertHtmlGivesMarkdown('<a>Test</a>', '<a>Test</a>');
        $this->assertHtmlGivesMarkdown('<a href="">Test</a>', '<a href="">Test</a>');
        $this->assertHtmlGivesMarkdown('<a href="#nerd" title="Title">Test</a>', '[Test](#nerd "Title")');
        $this->assertHtmlGivesMarkdown('<a href="#nerd">Test</a>', '[Test](#nerd)');

        // Strip placeholder links
        $this->assertHtmlGivesMarkdown('<a>Test</a>', 'Test', ['strip_placeholder_links' => true]);
        $this->assertHtmlGivesMarkdown('<a href="">Test</a>', 'Test', ['strip_placeholder_links' => true]);
        $this->assertHtmlGivesMarkdown('<a href="#nerd" title="Title">Test</a>', '[Test](#nerd "Title")', ['strip_placeholder_links' => true]);
        $this->assertHtmlGivesMarkdown('<a href="#nerd">Test</a>', '[Test](#nerd)', ['strip_placeholder_links' => true]);

        // Autolinking
        $this->assertHtmlGivesMarkdown('<a href="test">test</a>', '[test](test)');
        $this->assertHtmlGivesMarkdown('<a href="google.com">google.com</a>', '[google.com](google.com)');
        $this->assertHtmlGivesMarkdown('<a href="https://www.google.com">https://www.google.com</a>', '<https://www.google.com>');
        $this->assertHtmlGivesMarkdown('<a href="ftp://files.example.com">ftp://files.example.com</a>', '<ftp://files.example.com>');
        $this->assertHtmlGivesMarkdown('<a href="mailto:test@example.com">test@example.com</a>', '<test@example.com>');
        $this->assertHtmlGivesMarkdown('<a href="mailto:test+foo@example.bar-baz.com">test+foo@example.bar-baz.com</a>', '<test+foo@example.bar-baz.com>');

        // Autolinking can be toggled off
        $this->assertHtmlGivesMarkdown('<a href="https://www.google.com">https://www.google.com</a>', '[https://www.google.com](https://www.google.com)', ['use_autolinks' => false]);
        $this->assertHtmlGivesMarkdown('<a href="https://www.google.com">Google</a>', '[Google](https://www.google.com)', ['use_autolinks' => false]);
        $this->assertHtmlGivesMarkdown('<a href="google.com">google.com</a>', '[google.com](google.com)', ['use_autolinks' => false]);
    }

    public function testHorizontalRule(): void
    {
        $this->assertHtmlGivesMarkdown('<hr>', '---');
        $this->assertHtmlGivesMarkdown('<hr/>', '---');
        $this->assertHtmlGivesMarkdown('<hr />', '---');
        $this->assertHtmlGivesMarkdown('<hr  />', '---');
        $this->assertHtmlGivesMarkdown('<p>Test</p><hr>', "Test\n\n---");
    }

    public function testLists(): void
    {
        $this->assertHtmlGivesMarkdown('<ul><li>Item A</li><li>Item B</li><li>Item C</li></ul>', "- Item A\n- Item B\n- Item C");
        $this->assertHtmlGivesMarkdown('<ul><li>   Item A</li><li>   Item B</li></ul>', "- Item A\n- Item B");
        $this->assertHtmlGivesMarkdown('<ul><li>  <h3> Item A</h3><p>Description</p></li><li>   Item B</li></ul>', "- ###  Item A\n    \n    Description\n- Item B");
        $this->assertHtmlGivesMarkdown('<ul><li>First</li><li>Second</li></ul>', "* First\n* Second", ['list_item_style' => '*']);
        $this->assertHtmlGivesMarkdown('<ol><li>Item A</li><li>Item B</li></ol>', "1. Item A\n2. Item B");
        $this->assertHtmlGivesMarkdown("<ol>\n    <li>Item A</li>\n    <li>Item B</li>\n</ol>", "1. Item A\n2. Item B");
        $this->assertHtmlGivesMarkdown('<ol><li>   Item A</li><li>   Item B</li></ol>', "1. Item A\n2. Item B");
        $this->assertHtmlGivesMarkdown('<ol><li>  <h3> Item A</h3><p>Description</p></li><li>   Item B</li></ol>', "1. ###  Item A\n    \n    Description\n2. Item B");
        $this->assertHtmlGivesMarkdown('<ol start="120"><li>Item A</li><li>Item B</li></ol>', "120. Item A\n121. Item B");
        $this->assertHtmlGivesMarkdown('<ul><li>first item of first list</li><li>second item of first list</li></ul><ul><li>first item of second list</li></ul>', "- first item of first list\n- second item of first list\n\n* first item of second list", ['list_item_style_alternate' => '*']);
    }

    public function testNestedLists(): void
    {
        $this->assertHtmlGivesMarkdown('<ul><li>Item A</li><li>Item B<ul><li>Nested A</li><li>Nested B</li></ul></li><li>Item C</li></ul>', "- Item A\n- Item B\n    - Nested A\n    - Nested B\n- Item C");
        $this->assertHtmlGivesMarkdown('<ul><li>   Item A<ol><li>Nested A</li></ol></li><li>   Item B</li></ul>', "- Item A\n    1. Nested A\n- Item B");
        $this->assertHtmlGivesMarkdown('<ol><li>Item A<ul><li>Nested A</li></ul></li><li>Item B</li></ol>', "1. Item A\n    - Nested A\n2. Item B");
    }

    public function testComplexNestedLists(): void
    {
        $this->assertHtmlGivesMarkdown("<ul>\n<li>Item A</li>\n<li>Item B\n<ul>\n<li>Nested A</li>\n<li>Nested B\n<ul>\n<li>Subnested A</li>\n<li>Subnested B</li>\n</ul>\n</li>\n</ul>\n</li>\n<li>Item C</li>\n</ul>", "- Item A\n- Item B \n    - Nested A\n    - Nested B \n        - Subnested A\n        - Subnested B\n- Item C");
        $this->assertHtmlGivesMarkdown("<ul>\n<li>Item A</li>\n<li><h2>Item B</h2>\n<p>Paragraph Item B</p>\n<ul>\n<li>Nested A</li>\n<li>Nested B\n<ul>\n<li>Subnested A</li>\n<li>Subnested B</li>\n</ul>\n</li>\n</ul>\n</li>\n<li>Item C</li>\n</ul>", "* Item A\n* ## Item B\n    \n    Paragraph Item B\n    \n    \n    * Nested A\n    * Nested B \n        * Subnested A\n        * Subnested B\n* Item C", ['list_item_style' => '*', 'header_style' => 'atx']);
        $this->assertHtmlGivesMarkdown("<ul>\n<li>Item A</li>\n<li><h2>Item B</h2>\n<p>Paragraph Item B</p>\n<ul>\n<li>Nested A</li>\n<li><h2>Nested B</h2>\n<p>Paragraph Nested B</p>\n<ul>\n<li>Subnested A</li>\n<li>Subnested B\n<ul>\n<li>Subsubnested A</li>\n<li><h2>Subsubnested B</h2>\n<p>Paragraph Subsubnested B</p>\n<ul>\n<li>Subsubsubnested A</li>\n<li>Subsubsubnested B</li>\n</ul>\n</li>\n</ul>\n</li>\n</ul>\n</li>\n</ul>\n</li>\n<li>Item C</li>\n</ul>", "* Item A\n* ## Item B\n    \n    Paragraph Item B\n    \n    \n    * Nested A\n    * ## Nested B\n        \n        Paragraph Nested B\n        \n        \n        * Subnested A\n        * Subnested B \n            * Subsubnested A\n            * ## Subsubnested B\n                \n                Paragraph Subsubnested B\n                \n                \n                * Subsubsubnested A\n                * Subsubsubnested B\n* Item C", ['list_item_style' => '*', 'header_style' => 'atx']);
        $this->assertHtmlGivesMarkdown("<ul>\n<li>Item A</li>\n<li>Item B\n<ul>\n<li>Nested A</li>\n<li>Nested B\n<ul>\n<li>Subnested A</li>\n<li>Subnested B\n<ul>\n<li>Subsubnested A</li>\n<li>Subsubnested B                                            \n<ul>\n<li>Subsubsubnested A</li>\n<li>Subsubsubnested B</li>\n</ul>\n</li>\n</ul>\n</li>\n</ul>\n</li>\n</ul>\n</li>\n<li>Item C</li>\n</ul>", "* Item A\n* Item B \n    * Nested A\n    * Nested B \n        * Subnested A\n        * Subnested B \n            * Subsubnested A\n            * Subsubnested B \n                * Subsubsubnested A\n                * Subsubsubnested B\n* Item C", ['list_item_style' => '*', 'header_style' => 'atx']);
    }

    public function testListLikeThingsWhichArentLists(): void
    {
        $this->assertHtmlGivesMarkdown('<p>120.<p>', '120\.');
        $this->assertHtmlGivesMarkdown('<p>120. <p>', '120\.');
        $this->assertHtmlGivesMarkdown('<p>120.00<p>', '120.00');
        $this->assertHtmlGivesMarkdown('<p>120.00 USD<p>', '120.00 USD');
    }

    public function testTables(): void
    {
        $opt  = [];
        $conv = [new TableConverter()];
        $this->assertHtmlGivesMarkdown('<table><tr><th>A</th></tr></table>', "| A |\n|---|", $opt, $conv);
        $this->assertHtmlGivesMarkdown('<table><tr><td>A</td></tr></table>', "| A |\n|---|", $opt, $conv);
        $this->assertHtmlGivesMarkdown('<table><tr><th>A</th><th>B</th></tr><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></table>', "| A | B |\n|---|---|\n| a | b |\n| c | d |", $opt, $conv);
        $this->assertHtmlGivesMarkdown('<table><tr><th>A</th><th>B</th></tr><tr><td>a</td><td><code>foo</code></td></tr></table>', "| A | B |\n|---|---|\n| a | `foo` |", $opt, $conv);
        $this->assertHtmlGivesMarkdown('<table><tr><th>A</th><th>B</th></tr><tr><td>a</td><td><em>foo</em>bar</td></tr></table>', "| A | B |\n|---|---|\n| a | *foo*bar |", $opt, $conv);
        $this->assertHtmlGivesMarkdown('<table><tr><th>A</th><th>B</th></tr><tr><td>a</td><td><p>foo</p>bar</td></tr></table>', "| A | B |\n|---|---|\n| a | foo  bar |", $opt, $conv);
        $this->assertHtmlGivesMarkdown('<table><tr><td><a href="http://example.com">link</a></td></tr></table>', "| [link](http://example.com) |\n|---|", $opt, $conv);
        $this->assertHtmlGivesMarkdown('<table><tr><th>A</th></tr><tr><td>a | b</td></tr></table>', "| A |\n|---|\n| a \\| b |", $opt, $conv);
        $this->assertHtmlGivesMarkdown('<table><tr><th>A</th></tr><tr><td>a | b</td></tr></table>', "| A |\n|---|\n| a ][ b |", ['table_pipe_escape' => ']['], $conv);
        $this->assertHtmlGivesMarkdown('<table><caption>Cap</caption><tr><th>A</th></tr></table>', "Cap\n| A |\n|---|", ['table_caption_side' => 'top'], $conv);
        $this->assertHtmlGivesMarkdown('<table><caption>Cap</caption><tr><th>A</th></tr></table>', "| A |\n|---|\nCap", ['table_caption_side' => 'bottom'], $conv);
        $this->assertHtmlGivesMarkdown('<table><caption>Cap</caption><tr><th>A</th></tr></table>', "| A |\n|---|", ['table_caption_side' => null], $conv);
        $this->assertHtmlGivesMarkdown('<table><tr><th align="left">A</th></tr></table>', "| A |\n|:--|", $opt, $conv);
        $this->assertHtmlGivesMarkdown('<table><tr><th align="right">A</th></tr></table>', "| A |\n|--:|", $opt, $conv);
        $this->assertHtmlGivesMarkdown('<table><tr><th align="center">A</th></tr></table>', "| A |\n|:-:|", $opt, $conv);
        $this->assertHtmlGivesMarkdown('<table><tr><th align="wrong">A</th></tr></table>', "| A |\n|---|", $opt, $conv);
        $html = <<<'EOT'
<table>
    <colgroup>
        <col>
        <col span="2" class="batman">
        <col span="2" class="flash">
    </colgroup>
    <thead>
        <tr>
            <td> </td>
            <th scope="col">Batman</th>
            <th scope="col">Robin</th>
            <th scope="col">The Flash</th>
            <th scope="col">Kid Flash</th>
        </tr>
    </thead>
    <tr>
        <th scope="row">Skill</th>
        <td>Smarts</td>
        <td>Dex, acrobat</td>
        <td>Super speed</td>
        <td>Super speed</td>
    </tr>
</table>
EOT;
        $this->assertHtmlGivesMarkdown($html, "|  | Batman | Robin | The Flash | Kid Flash |\n|---|---|---|---|---|\n| Skill | Smarts | Dex, acrobat | Super speed | Super speed |", $opt, $conv);
    }

    public function testCodeSamples(): void
    {
        $this->assertHtmlGivesMarkdown('<code>&lt;p&gt;Some sample HTML&lt;/p&gt;</code>', '`<p>Some sample HTML</p>`');
        $this->assertHtmlGivesMarkdown("<code>\n&lt;p&gt;Some sample HTML&lt;/p&gt;\n&lt;p&gt;And another line&lt;/p&gt;\n</code>", '`<p>Some sample HTML</p><p>And another line</p>`');
        $this->assertHtmlGivesMarkdown('<code>`</code>', '```');
        $this->assertHtmlGivesMarkdown('<code>test</code>', '`test`');
        $this->assertHtmlGivesMarkdown('<code>test `` test</code>', '`test `` test`');
        $this->assertHtmlGivesMarkdown('<code>test` `test</code>', "```\ntest` `test\n```");
        $this->assertHtmlGivesMarkdown("<p><code>\n&lt;p&gt;Some sample HTML&lt;/p&gt;\n&lt;p&gt;And another line&lt;/p&gt;\n</code></p><p>Paragraph after code.</p>", "`<p>Some sample HTML</p><p>And another line</p>`\n\nParagraph after code.");
        $this->assertHtmlGivesMarkdown("<p><code>\n#sidebar h1 {\n    font-size: 1.5em;\n    font-weight: bold;\n}\n</code></p>", '`#sidebar h1 {    font-size: 1.5em;    font-weight: bold;}`');
        $this->assertHtmlGivesMarkdown("<p><code>#sidebar h1 {\n    font-size: 1.5em;\n    font-weight: bold;\n}\n</code></p>", '`#sidebar h1 {    font-size: 1.5em;    font-weight: bold;}`');
        $this->assertHtmlGivesMarkdown('<pre><code>&lt;p&gt;Some sample HTML&lt;/p&gt;</code></pre>', "```\n<p>Some sample HTML</p>\n```");
        $this->assertHtmlGivesMarkdown('<pre><code class="language-php">&lt;?php //Some php code ?&gt;</code></pre>', "```php\n<?php //Some php code ?>\n```");
        $this->assertHtmlGivesMarkdown("<pre><code class=\"language-php\">&lt;?php //Some multiline php code\n\$myVar = 2; ?&gt;</code></pre>", "```php\n<?php //Some multiline php code\n\$myVar = 2; ?>\n```");
        $this->assertHtmlGivesMarkdown("<pre><code>&lt;p&gt;Multiline HTML&lt;/p&gt;\n&lt;p&gt;Here's the second line&lt;/p&gt;</code></pre>", "```\n<p>Multiline HTML</p>\n<p>Here's the second line</p>\n```");
        $this->assertHtmlGivesMarkdown("<pre><code>&lt;p&gt;Multiline HTML&lt;/p&gt;\n&lt;p&gt;Here's the second line&lt;/p&gt;</code></pre>\n<p>line</p>", "```\n<p>Multiline HTML</p>\n<p>Here's the second line</p>\n```\n\nline");
    }

    public function testPreformat(): void
    {
        $this->assertHtmlGivesMarkdown("<pre>test\ntest\r\ntest</pre>", "```\ntest\ntest\ntest\n```");
        $this->assertHtmlGivesMarkdown("<pre>test\ntest\r\ntest\n</pre>", "```\ntest\ntest\ntest\n```");
        $this->assertHtmlGivesMarkdown("<pre>test\n\ttab\r\n</pre>", "```\ntest\n\ttab\n```");
        $this->assertHtmlGivesMarkdown('<pre>  one line with spaces  </pre>', "```\n  one line with spaces  \n```");
        $this->assertHtmlGivesMarkdown('<pre></pre>', "```\n```");
        $this->assertHtmlGivesMarkdown('<pre></pre><pre></pre>', "```\n```\n\n```\n```");
        $this->assertHtmlGivesMarkdown("<pre>\n</pre>", "```\n\n```");
        $this->assertHtmlGivesMarkdown("<pre>foo\n</pre>", "```\nfoo\n```");
        $this->assertHtmlGivesMarkdown("<pre>\nfoo</pre>", "```\n\nfoo\n```");
        $this->assertHtmlGivesMarkdown("<pre>\nfoo\n</pre>", "```\n\nfoo\n```");
        $this->assertHtmlGivesMarkdown("<pre>\n\n</pre>", "```\n\n\n```");
        $this->assertHtmlGivesMarkdown("<pre>\n\n\n</pre>", "```\n\n\n\n```");
        $this->assertHtmlGivesMarkdown("<pre>\n</pre><pre>\n</pre>", "```\n\n```\n\n```\n\n```");
        $this->assertHtmlGivesMarkdown("<pre>one\ntwo\r\nthree</pre>\n<p>line</p>", "```\none\ntwo\nthree\n```\n\nline");
        $this->assertHtmlGivesMarkdown("<pre class='some-class'>test with attributes</pre>", "```\ntest with attributes\n```");
    }

    public function testBlockquotes(): void
    {
        $this->assertHtmlGivesMarkdown('<blockquote>Something I said?</blockquote>', '> Something I said?');
        $this->assertHtmlGivesMarkdown('<blockquote><blockquote>Something I said?</blockquote></blockquote>', '> > Something I said?');
        $this->assertHtmlGivesMarkdown('<blockquote><p>Something I said?</p><p>Why, yes it was!</p></blockquote>', "> Something I said?\n> \n> Why, yes it was!");
    }

    public function testMalformedHtml(): void
    {
        $this->assertHtmlGivesMarkdown('<code><p>Some sample HTML</p></code>', '`<p>Some sample HTML</p>`'); // Invalid HTML, but should still work
        $this->assertHtmlGivesMarkdown('<strong><em>Strong italic</strong> Regular text', '***Strong italic*** Regular text'); // Missing closing </em>
    }

    public function testHtml5TagsArePreserved(): void
    {
        $this->assertHtmlGivesMarkdown('<article>Some stuff</article>', '<article>Some stuff</article>');
    }

    public function testStripUnmarkdownable(): void
    {
        $this->assertHtmlGivesMarkdown('<span>Span</span>', 'Span', ['strip_tags' => true]);
    }

    public function testStripComments(): void
    {
        $this->assertHtmlGivesMarkdown('<p>Test</p><!-- Test comment -->', 'Test');
        $this->assertHtmlGivesMarkdown('<!-- Test comment --><p>Test</p>', 'Test');
        $this->assertHtmlGivesMarkdown('<p>Test</p><!-- Test comment -->', 'Test', ['strip_tags' => true]);
        $this->assertHtmlGivesMarkdown('<!-- Test comment --><p>Test</p>', 'Test', ['strip_tags' => true]);
    }

    public function testPreserveComments(): void
    {
        $this->assertHtmlGivesMarkdown('<p>Test</p><!-- more -->', "Test\n\n<!-- more -->", ['preserve_comments' => ['more']]);
        $this->assertHtmlGivesMarkdown('<p>Test</p><!-- Test comment --><!-- more -->', "Test\n\n<!-- more -->", ['preserve_comments' => ['more']]);
        $this->assertHtmlGivesMarkdown('<!-- Test comment --><p>Test</p><!-- Test comment -->', "<!-- Test comment -->Test\n\n<!-- Test comment -->", ['preserve_comments' => true]);
    }

    public function testPreserveCommentOrder(): void
    {
        $this->assertHtmlGivesMarkdown('<!-- 1 --><!-- 2 --><p>Test</p><!-- 3 -->', "<!-- 1 --><!-- 2 -->Test\n\n<!-- 3 -->", ['preserve_comments' => true]);
    }

    public function testPreserveWhitespace(): void
    {
        $this->assertHtmlGivesMarkdown('<a href="google.com">google.com</a> <code>test</code>', '[google.com](google.com) `test`');
    }

    public function testDeleteBlankP(): void
    {
        $this->assertHtmlGivesMarkdown('<p></p>', '');
        $this->assertHtmlGivesMarkdown('<p></p>', '', ['strip_tags' => true]);
    }

    public function testDivs(): void
    {
        $this->assertHtmlGivesMarkdown('<div>Hello</div><div>World</div>', '<div>Hello</div><div>World</div>');
        $this->assertHtmlGivesMarkdown('<div>Hello</div><div>World</div>', "Hello\n\nWorld", ['strip_tags' => true]);
        $this->assertHtmlGivesMarkdown("<div>Hello</div>\n<div>World</div>", "Hello\n\nWorld", ['strip_tags' => true]);
        $this->assertHtmlGivesMarkdown('<p>Paragraph</p><div>Hello</div><div>World</div>', "Paragraph\n\nHello\n\nWorld", ['strip_tags' => true]);
    }

    public function testRemoveNodes(): void
    {
        $this->assertHtmlGivesMarkdown('<div>Hello</div><div>World</div>', '', ['remove_nodes' => 'div']);
        $this->assertHtmlGivesMarkdown('<p>Hello</p><span>World</span>', '', ['remove_nodes' => 'p span']);
    }

    public function testHtmlEntities(): void
    {
        $this->assertHtmlGivesMarkdown('<p>&amp;euro;</p>', '&amp;euro;');
        $this->assertHtmlGivesMarkdown('<code>&lt;p&gt;Some sample HTML&lt;/p&gt;</code>', '`<p>Some sample HTML</p>`');
    }

    public function testSetOption(): void
    {
        $markdown = new HtmlConverter();
        $markdown->getConfig()->setOption('strip_tags', true);
        $result = $markdown->convert('<span>Strip</span>');

        $this->assertEquals('Strip', $result);
    }

    public function testInvoke(): void
    {
        $markdown = new HtmlConverter();
        $markdown->getConfig()->setOption('strip_tags', true);
        $result = $markdown('<span>Strip</span>');

        $this->assertEquals('Strip', $result);
    }

    public function testSanitization(): void
    {
        $html     = '<pre><code>&lt;script type = "text/javascript"&gt; function startTimer() { var tim = window.setTimeout("hideMessage()", 5000) } &lt;/head&gt; &lt;body&gt;</code></pre>';
        $markdown = '```' . "\n" . '<script type = "text/javascript"> function startTimer() { var tim = window.setTimeout("hideMessage()", 5000) } </head> <body>' . "\n```";
        $this->assertHtmlGivesMarkdown($html, $markdown);
        $this->assertHtmlGivesMarkdown('<p>&gt; &gt; Look at me! &lt; &lt;</p>', '&gt; &gt; Look at me! &lt; &lt;');
        $this->assertHtmlGivesMarkdown('<p>&gt; &gt; <b>Look</b> at me! &lt; &lt;<br />&gt; Just look at me!</p>', "&gt; &gt; **Look** at me! &lt; &lt;  \n&gt; Just look at me!");
        $this->assertHtmlGivesMarkdown('<p>Foo<br>--<br>Bar<br>Foo--</p>', "Foo  \n\\--  \nBar  \nFoo--");
        $this->assertHtmlGivesMarkdown('<ul><li>Foo<br>- Bar</li></ul>', "- Foo  \n    \\- Bar");
        $this->assertHtmlGivesMarkdown('Foo<br />* Bar', "Foo  \n\\* Bar");
        $this->assertHtmlGivesMarkdown("<p>123456789) Foo and 1234567890) Bar!</p>\n<p>1. Platz in 'Das große Backen'</p>", "123456789\\) Foo and 1234567890) Bar!\n\n1\\. Platz in 'Das große Backen'");
        $this->assertHtmlGivesMarkdown("<p>\n+ Siri works well for TV and movies<br>\n- No 4K support\n</p>", "\+ Siri works well for TV and movies  \n\- No 4K support");
        $this->assertHtmlGivesMarkdown('<p>You forgot the &lt;!--more--&gt; tag!</p>', 'You forgot the &lt;!--more--&gt; tag!');
    }

    public function testInstatiationWithEnvironment(): void
    {
        $markdown = new HtmlConverter(new Environment([]));

        $htmlH3 = '<h3>Test</h3>';
        $result = $markdown->convert($htmlH3);
        $this->assertEquals($htmlH3, $result);

        $htmlH4 = '<h4>Test</h4>';
        $result = $markdown->convert($htmlH4);
        $this->assertEquals($htmlH4, $result);
    }

    public function testStrikethrough(): void
    {
        $opt  = [];
        $conv = [new StrikethroughConverter()];
        $this->assertHtmlGivesMarkdown('<strike>Some Text</strike>', '~~Some Text~~', $opt, $conv);
        $this->assertHtmlGivesMarkdown('<del>Some Text</del>', '~~Some Text~~', $opt, $conv);
        $this->assertHtmlGivesMarkdown('<del>Some</del><del> Text</del>', '~~Some Text~~', $opt, $conv);
        $this->assertHtmlGivesMarkdown('<del>Some</del><strike> Text</strike>', '~~Some Text~~', $opt, $conv);
        $this->assertHtmlGivesMarkdown('<del>Some</del> <strike>Text</strike>', '~~Some~~ ~~Text~~', $opt, $conv);
    }
}

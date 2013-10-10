<?php
require_once dirname(__FILE__) . '/../HTML_To_Markdown.php';

class HTML_To_MarkdownTest extends PHPUnit_Framework_TestCase
{
    private function html_gives_markdown($html, $expected_markdown)
    {
        $markdown = new HTML_To_Markdown($html);
        $this->assertEquals($expected_markdown, $markdown->__toString());
    }

    public function test_plain_text()
    {
        $this->html_gives_markdown("test", "test");
        $this->html_gives_markdown("<p>test</p>", "test");
    }

    public function test_line_breaks()
    {
        $this->html_gives_markdown("test\nanother line", "test another line");
        $this->html_gives_markdown("<p>test\nanother line</p>", "test another line");
        $this->html_gives_markdown("<p>test<br>another line</p>", "test  \nanother line");
    }

    public function test_headers()
    {
        $this->html_gives_markdown("<h1>Test</h1>", "Test\n====");
        $this->html_gives_markdown("<h2>Test</h2>", "Test\n----");
        $this->html_gives_markdown("<h3>Test</h3>", "### Test");
        $this->html_gives_markdown("<h4>Test</h4>", "#### Test");
        $this->html_gives_markdown("<h5>Test</h5>", "##### Test");
        $this->html_gives_markdown("<h6>Test</h6>", "###### Test");
    }

    public function test_spans()
    {
        $this->html_gives_markdown("<em>Test</em>", "*Test*");
        $this->html_gives_markdown("<i>Test</i>", "*Test*");
        $this->html_gives_markdown("<strong>Test</strong>", "**Test**");
        $this->html_gives_markdown("<b>Test</b>", "**Test**");
        $this->html_gives_markdown("<span>Test</span>", "<span>Test</span>");
    }

    public function test_image()
    {
        $this->html_gives_markdown('<img src="/path/img.jpg" alt="alt text" title="Title" />', '![alt text](/path/img.jpg "Title")');
    }

    public function test_anchor()
    {
        $this->html_gives_markdown('<a href="http://modernnerd.net" title="Title">Modern Nerd</a>', '[Modern Nerd](http://modernnerd.net "Title")');
        $this->html_gives_markdown('<a href="http://modernnerd.net" title="Title">Modern Nerd</a> <a href="http://modernnerd.net" title="Title">Modern Nerd</a>', '[Modern Nerd](http://modernnerd.net "Title") [Modern Nerd](http://modernnerd.net "Title")');
    }

    public function test_lists()
    {
        $this->html_gives_markdown("<ul><li>Item A</li><li>Item B</li></ul>", "- Item A\n- Item B");
        $this->html_gives_markdown("<ul><li>   Item A</li><li>   Item B</li></ul>", "- Item A\n- Item B");
        $this->html_gives_markdown("<ol><li>Item A</li><li>Item B</li></ol>", "1. Item A\n2. Item B");
        $this->html_gives_markdown("<ol><li>   Item A</li><li>   Item B</li></ol>", "1. Item A\n2. Item B");
    }

    public function test_code_samples()
    {
        $this->html_gives_markdown("<code>&lt;p&gt;Some sample HTML&lt;/p&gt;</code>", "`<p>Some sample HTML</p>`");
        $this->html_gives_markdown("<code>\n&lt;p&gt;Some sample HTML&lt;/p&gt;\n&lt;p&gt;And another line&lt;/p&gt;\n</code>", "    <p>Some sample HTML</p>\n    <p>And another line</p>");
    }

    public function test_blockquotes()
    {
        $this->html_gives_markdown("<blockquote>Something I said?</blockquote>", "> Something I said?");
    }

    public function test_malformed_html()
    {
        $this->html_gives_markdown("<code><p>Some sample HTML</p></code>", "`<p>Some sample HTML</p>`"); // Invalid HTML, but should still work
        $this->html_gives_markdown("<strong><em>Strong italic</strong> Regular text", "***Strong italic*** Regular text"); // Missing closing </em>
    }

    public function test_html5_tags_are_preserved()
    {
        $this->html_gives_markdown("<article>Some stuff</article>", "<article>Some stuff</article>");
    }
}
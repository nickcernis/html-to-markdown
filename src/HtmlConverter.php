<?php

namespace League\HTMLToMarkdown;

/**
 * Class HtmlConverter
 *
 * A helper class to convert HTML to Markdown.
 *
 * @author Colin O'Dell <colinodell@gmail.com>
 * @author Nick Cernis <nick@cern.is>
 *
 * @link https://github.com/thephpleague/html-to-markdown/ Latest version on GitHub.
 *
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 */
class HtmlConverter
{
    /**
     * @var Environment
     */
    protected $environment;

    /**
     * @var array
     */
    protected $whiteTags = array();

    /**
     * @var string
     */
    protected $wildCard = '';

    /**
     * Constructor
     *
     * @param array $options Configuration options
     */
    public function __construct(array $options = array())
    {
        $defaults = array(
            'header_style'       => 'setext', // Set to 'atx' to output H1 and H2 headers as # Header1 and ## Header2
            'suppress_errors'    => true, // Set to false to show warnings when loading malformed HTML
            'strip_tags'         => false, // Set to true to strip tags that don't have markdown equivalents. N.B. Strips tags, not their content. Useful to clean MS Word HTML output.
            'bold_style'         => '**', // Set to '__' if you prefer the underlined style
            'italic_style'       => '_', // Set to '*' if you prefer the asterisk style
            'remove_nodes'       => '', // space-separated list of dom nodes that should be removed. example: 'meta style script'
            'white_tags'         => array(), // Array with allowed html tags
            'white_tag_wildcard' => '|', // Use a non common character
        );

        $this->environment = Environment::createDefaultEnvironment($defaults);

        $this->environment->getConfig()->merge($options);
    }

    /**
     * @return Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @return Configuration
     */
    public function getConfig()
    {
        return $this->environment->getConfig();
    }

    /**
     * Convert
     *
     * @see HtmlConverter::convert
     *
     * @param string $html
     *
     * @return string The Markdown version of the html
     */
    public function __invoke($html)
    {
        return $this->convert($html);
    }

    /**
     * Convert
     *
     * Loads HTML and passes to getMarkdown()
     *
     * @param $html
     *
     * @return string The Markdown version of the html
     */
    public function convert($html)
    {
        if (trim($html) === '') {
            return '';
        }

        $this->setWhiteTagVariables();

        $html = $this->escapeWhiteTags($html);

        $document = $this->createDOMDocument($html);

        // Work on the entire DOM tree (including head and body)
        if (!($root = $document->getElementsByTagName('html')->item(0))) {
            throw new \InvalidArgumentException('Invalid HTML was provided');
        }

        $rootElement = new Element($root);
        $this->convertChildren($rootElement);

        // Store the now-modified DOMDocument as a string
        $markdown = $document->saveHTML();

        $markdown = $this->sanitize($markdown);

        $markdown = $this->removeEscapedWhiteTags($markdown);

        return $markdown;
    }

    /**
     * Set the values for use after
     */
    protected function setWhiteTagVariables()
    {
        $this->whiteTags = $this->getConfig()->getOption('white_tags');
        $this->wildCard = $this->getConfig()->getOption('white_tag_wildcard');
    }

    /**
     * Add each "whiteTag" into <code> tags and add the "wildCard" before and after the "<code>" tag
     * for avoid convert into markdown and indentify them later
     *
     * @param string $html
     *
     * @return string
     */
    protected function escapeWhiteTags($html)
    {
        if (count($this->whiteTags) > 0) {
            foreach ($this->whiteTags as $whiteTag) {
                //Search and replace the "<openTag" for "wildCard<code><openTag"
                $openTag = $this->getOpenTag($whiteTag);
                $replaceTag = sprintf('%s<code>%s', $this->wildCard, $openTag);
                $html = str_replace($openTag, $replaceTag, $html);

                //Search and replace the "closeTag>" for "closeTag></code>wildCard"
                $closeTag = $this->getCloseTag($whiteTag);
                $replaceTag = sprintf('%s</code>%s', $closeTag, $this->wildCard);
                $html = str_replace($closeTag, $replaceTag, $html);
            }
        }

        return $html;
    }

    /**
     * @param string $tag
     *
     * @return string
     */
    protected function getOpenTag($tag)
    {
        return sprintf('<%s', $tag);
    }

    /**
     * @param string $tag
     *
     * @return string
     */
    protected function getCloseTag($tag)
    {
        return sprintf('%s>', $tag);
    }

    /**
     * @param string $html
     *
     * @return \DOMDocument
     */
    private function createDOMDocument($html)
    {
        $document = new \DOMDocument();

        if ($this->getConfig()->getOption('suppress_errors')) {
            // Suppress conversion errors (from http://bit.ly/pCCRSX)
            libxml_use_internal_errors(true);
        }

        // Hack to load utf-8 HTML (from http://bit.ly/pVDyCt)
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $document->encoding = 'UTF-8';

        if ($this->getConfig()->getOption('suppress_errors')) {
            libxml_clear_errors();
        }

        return $document;
    }

    /**
     * Convert Children
     *
     * Recursive function to drill into the DOM and convert each node into Markdown from the inside out.
     *
     * Finds children of each node and convert those to #text nodes containing their Markdown equivalent,
     * starting with the innermost element and working up to the outermost element.
     *
     * @param ElementInterface $element
     */
    private function convertChildren(ElementInterface $element)
    {
        // Don't convert HTML code inside <code> and <pre> blocks to Markdown - that should stay as HTML
        if ($element->isDescendantOf(array('pre', 'code'))) {
            return;
        }

        // If the node has children, convert those to Markdown first
        if ($element->hasChildren()) {
            foreach ($element->getChildren() as $child) {
                $this->convertChildren($child);
            }
        }

        // Now that child nodes have been converted, convert the original node
        $markdown = $this->convertToMarkdown($element);

        // Create a DOM text node containing the Markdown equivalent of the original node

        // Replace the old $node e.g. '<h3>Title</h3>' with the new $markdown_node e.g. '### Title'
        $element->setFinalMarkdown($markdown);
    }

    /**
     * Convert to Markdown
     *
     * Converts an individual node into a #text node containing a string of its Markdown equivalent.
     *
     * Example: An <h3> node with text content of 'Title' becomes a text node with content of '### Title'
     *
     * @param ElementInterface $element
     *
     * @return string The converted HTML as Markdown
     */
    protected function convertToMarkdown(ElementInterface $element)
    {
        $tag = $element->getTagName();

        // Strip nodes named in remove_nodes
        $tags_to_remove = explode(' ', $this->getConfig()->getOption('remove_nodes'));
        if (in_array($tag, $tags_to_remove)) {
            return false;
        }

        $converter = $this->environment->getConverterByTag($tag);

        return $converter->convert($element);
    }

    /**
     * @param string $markdown
     *
     * @return string
     */
    protected function sanitize($markdown)
    {
        $markdown = html_entity_decode($markdown, ENT_QUOTES, 'UTF-8');
        $markdown = preg_replace('/<!DOCTYPE [^>]+>/', '', $markdown); // Strip doctype declaration
        $unwanted = array('<html>', '</html>', '<body>', '</body>', '<head>', '</head>', '<?xml encoding="UTF-8">', '&#xD;');
        $markdown = str_replace($unwanted, '', $markdown); // Strip unwanted tags
        $markdown = trim($markdown, "\n\r\0\x0B");

        return $markdown;
    }

    /**
     * Remove the previously added <code> for the "whiteTags" marked by the "wildCard"
     * to return the "html" as the user typed
     *
     * @param string $markdown
     *
     * @return string
     */
    protected function removeEscapedWhiteTags($markdown)
    {
        if (count($this->whiteTags) > 0) {
            foreach ($this->whiteTags as $whiteTag) {
                //Search and replace the "wildCard`<openTag" for "<openTag"
                $openTag = $this->getOpenTag($whiteTag);
                $openEscapedTag = sprintf('%s`%s', $this->wildCard, $openTag);
                $markdown = str_replace($openEscapedTag, $openTag, $markdown);

                //Search and replace the "closeTag>`wildCard" for "closeTag>"
                $closeTag = $this->getCloseTag($whiteTag);
                $closeEscapedTag = sprintf('%s`%s', $closeTag, $this->wildCard);
                $markdown = str_replace($closeEscapedTag, $closeTag, $markdown);
            }
        }

        return $markdown;
    }
}

<?php

declare(strict_types=1);

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\ElementInterface;

class StrikethroughConverter implements ConverterInterface, ConfigurationAwareInterface
{
    /** @var Configuration */
    protected $config;

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['del', 'strike'];
    }

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        $value = $element->getValue();
        if (! \trim($value)) {
            return $value;
        }

        $prefix = \ltrim($value) !== $value ? ' ' : '';
        $suffix = \rtrim($value) !== $value ? ' ' : '';

        $previousTagName = null;
        if (($previous = $element->getPreviousSibling()) !== null) {
            $previousTagName = $previous->getTagName();
        }

        $nextTagName = null;
        if (($next = $element->getNextSibling()) !== null) {
            $nextTagName = $next->getTagName();
        }

        /* If this node is immediately preceded or followed by one of the same type don't emit
         * the start or end $style, respectively. This prevents <del>foo</del><del>bar</del> from
         * being converted to ~~foo~~~~bar~~ which is incorrect. We want ~~foobar~~ instead.
         */
        $preStyle  = \in_array($previousTagName, $this->getSupportedTags(), true) ? '' : '~~';
        $postStyle = \in_array($nextTagName, $this->getSupportedTags(), true) ? '' : '~~';

        return $prefix . $preStyle . \trim($value) . $postStyle . $suffix;
    }
}

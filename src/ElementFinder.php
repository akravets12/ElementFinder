<?php

declare(strict_types=1);

namespace Xparse\ElementFinder;

use Xparse\ElementFinder\Collection\ElementCollection;
use Xparse\ElementFinder\Collection\ObjectCollection;
use Xparse\ElementFinder\Collection\StringCollection;
use Xparse\ElementFinder\ElementFinder\Element;
use Xparse\ElementFinder\ElementFinder\ElementFinderModifierInterface;
use Xparse\ElementFinder\ElementFinder\RemoveElements;
use Xparse\ElementFinder\Helper\NodeHelper;
use Xparse\ElementFinder\Helper\StringHelper;
use Xparse\ExpressionTranslator\ExpressionTranslatorInterface;
use Xparse\ExpressionTranslator\XpathExpression;

/**
 * @author Ivan Scherbak <alotofall@gmail.com>
 */
class ElementFinder implements ElementFinderInterface
{

    /**
     * Html document type
     *
     * @var int
     */
    const DOCUMENT_HTML = 0;

    /**
     * Xml document type
     *
     * @var int
     */
    const DOCUMENT_XML = 1;

    /**
     * Current document type
     *
     * @var int
     */
    private $type;

    /**
     * @var \DOMDocument
     */
    private $dom;

    /**
     * @var \DomXPath
     */
    private $xpath;

    /**
     * @var ExpressionTranslatorInterface
     */
    private $expressionTranslator;

    /**
     * @var array
     */
    private $loadErrors = [];


    /**
     *
     *
     * Example:
     * new ElementFinder("<html><div>test </div></html>", ElementFinder::HTML);
     *
     * @param string $data
     * @param null|int $documentType
     * @param ExpressionTranslatorInterface|null $translator
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function __construct(string $data, int $documentType = null, ExpressionTranslatorInterface $translator = null)
    {
        if ('' === $data) {
            throw new \InvalidArgumentException('Expect not empty string');
        }
        $this->dom = new \DomDocument();
        $this->expressionTranslator = $translator ?? new XpathExpression();
        $this->dom->registerNodeClass(\DOMElement::class, Element::class);
        $this->type = $documentType ?? static::DOCUMENT_HTML;
        $this->setData($data);
    }


    public function __destruct()
    {
        unset($this->dom, $this->xpath);
    }


    public function __clone()
    {
        $this->dom = clone $this->dom;
        $this->xpath = new \DomXPath($this->dom);
    }

    /**
     * @param string $expression
     * @param bool $outerContent
     * @return StringCollection
     * @throws \Exception
     */
    final public function content(string $expression, bool $outerContent = false): StringCollection
    {
        $items = $this->query($expression);
        $result = [];
        foreach ($items as $node) {
            if ($outerContent) {
                $result[] = NodeHelper::getOuterContent($node);
            } else {
                $result[] = NodeHelper::getInnerContent($node);
            }
        }
        return new StringCollection($result);
    }


    /**
     * You can remove elements and attributes
     *
     * ```php
     * $html->remove("//span/@class");
     *
     * $html->remove("//input");
     * ```
     *
     * @param string $expression
     * @return ElementFinderInterface
     */
    final public function remove($expression): ElementFinderInterface
    {
        return $this->modify($expression, new RemoveElements());
    }


    /**
     * @param string $expression
     * @param ElementFinderModifierInterface $modifier
     * @return ElementFinderInterface
     */
    public function modify(string $expression, ElementFinderModifierInterface $modifier): ElementFinderInterface
    {
        $result = clone $this;
        $modifier->modify(
            $result->query($expression)
        );
        return $result;
    }

    /**
     * Get nodeValue of node
     *
     * @param string $expression
     * @return StringCollection
     * @throws \Exception
     */
    final public function value($expression): Collection\StringCollection
    {
        $items = $this->query($expression);
        $result = [];
        foreach ($items as $node) {
            $result[] = $node->nodeValue;
        }
        return new StringCollection($result);
    }


    /**
     * Return array of keys and values
     *
     * @param string $keyExpression
     * @param string $valueExpression
     * @throws \Exception
     * @return array
     */
    final public function keyValue(string $keyExpression, string $valueExpression): array
    {
        $keyNodes = $this->query($keyExpression);
        $valueNodes = $this->query($valueExpression);
        if ($keyNodes->length !== $valueNodes->length) {
            throw new \RuntimeException('Keys and values must have equal numbers of elements');
        }
        $result = [];
        foreach ($keyNodes as $index => $node) {
            $result[$node->nodeValue] = $valueNodes->item($index)->nodeValue;
        }
        return $result;
    }


    /**
     * @param string $expression
     * @param bool $outerHtml
     * @throws \Exception
     * @return ObjectCollection
     * @throws \InvalidArgumentException
     */
    final public function object($expression, $outerHtml = false): ObjectCollection
    {
        $type = $this->type;
        $items = $this->query($expression);
        $result = [];
        foreach ($items as $node) {
            /** @var \DOMElement $node */
            if ($outerHtml) {
                $html = NodeHelper::getOuterContent($node);
            } else {
                $html = NodeHelper::getInnerContent($node);
            }
            if (trim($html) === '') {
                $html = '<html data-document-is-empty></html>';
            }
            if ($this->type === static::DOCUMENT_XML and strpos($html, '<?xml') === false) {
                $html = '<root>' . $html . '</root>';
            }
            $result[] = new ElementFinder($html, $type, $this->expressionTranslator);
        }
        return new ObjectCollection($result);
    }


    /**
     * @param string $expression
     * @return ElementCollection
     * @throws \InvalidArgumentException
     */
    final public function element($expression): ElementCollection
    {
        $nodeList = $this->query($expression);
        $items = [];
        foreach ($nodeList as $item) {
            $items[] = clone $item;
        }
        return new ElementCollection($items);
    }


    /**
     * @return array
     */
    final public function getLoadErrors(): array
    {
        return $this->loadErrors;
    }


    /**
     * @param string $data
     * @return $this
     * @throws \Exception
     */
    private function setData($data): self
    {
        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader();

        if (static::DOCUMENT_HTML === $this->type) {
            $data = StringHelper::safeEncodeStr($data);
            $data = mb_convert_encoding($data, 'HTML-ENTITIES', 'UTF-8');
            $this->dom->loadHTML($data, LIBXML_NOCDATA & LIBXML_NOERROR);
        } elseif (static::DOCUMENT_XML === $this->type) {
            $this->dom->loadXML($data, LIBXML_NOCDATA & LIBXML_NOERROR);
        } else {
            throw new \InvalidArgumentException('Doc type not valid. use xml or html');
        }
        $this->loadErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($disableEntities);
        unset($this->xpath);
        $this->xpath = new \DomXPath($this->dom);
        return $this;
    }

    /**
     * @see element
     * Fetch nodes from document
     *
     * @param string $expression
     * @return \DOMNodeList
     */
    private function query($expression): \DOMNodeList
    {
        return $this->xpath->query(
            $this->expressionTranslator->convertToXpath($expression)
        );
    }
}

<?php

namespace LaravelAmp\Amp;

use DOMDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AMP
{
    private Response $response;
    private DOMDocument $document;

    private function __construct(Response $response)
    {
        $this->response = $response;

        $document = new DOMDocument();

        $document->loadHTML($this->response->content(), LIBXML_NOERROR);

        $this->document = $document;
    }

    public static function convert(Response $response): Response
    {
        return (new static($response))
            ->setAmpInHtml()
            ->setCharsetInHead()
            ->setScriptInHead()
            ->setCanonicalUrlInHead()
            ->setViewportInHead()
            ->setAmpBoilerplateInHead()
            ->response();
    }

    public function response(): Response
    {
        $this->response->setContent($this->document->saveHTML());

        return $this->response;
    }

    private function setAmpInHtml(): AMP
    {
        $tags = $this->document->getElementsByTagName('html');

        foreach ($tags as $tag) {
            $tag->appendChild($this->document->createAttribute('amp'));
        }

        return $this;
    }

    private function setCharsetInHead(): AMP
    {
        $tags = $this->document->getElementsByTagName('head');

        foreach ($tags->item(0)->childNodes as $node) {
            if ($node->nodeName === 'meta' && $node->hasAttribute('charset')) {
                $node->setAttribute('charset', 'utf-8');

                return $this;
            }
        }

        $element = $this->document->createElement('meta');

        $element->setAttribute('charset', 'utf-8');

        $tags->item(0)->appendChild($element);

        return $this;
    }

    private function setScriptInHead(): AMP
    {
        $tags = $this->document->getElementsByTagName('head');

        $element = $this->document->createElement('script');

        $element->setAttribute('src', 'https://cdn.ampproject.org/v0.js');

        $attribute = $this->document->createAttribute('async');

        $element->appendChild($attribute);

        $tags->item(0)->appendChild($element);

        return $this;
    }

    private function setCanonicalUrlInHead(): AMP
    {
        $tags = $this->document->getElementsByTagName('head');

        $element = $this->document->createElement('link');

        $element->setAttribute('rel', 'canonical');

        $element->setAttribute('href', str_replace('/amp', '', Request::capture()->url()));

        $tags->item(0)->appendChild($element);

        return $this;
    }

    private function setViewportInHead(): AMP
    {
        $tags = $this->document->getElementsByTagName('head');

        $viewport = 'width=device-width,minimum-scale=1,initial-scale=1';

        foreach ($tags->item(0)->childNodes as $node) {
            $is_needed_node = $node->nodeName === 'meta'
                && $node->hasAttribute('name')
                && $node->getAttribute('name') === 'viewport';

            if ($is_needed_node) {
                $node->setAttribute('content', $viewport);

                return $this;
            }
        }

        $element = $this->document->createElement('meta');

        $element->setAttribute('name', 'viewport');

        $element->setAttribute('content', $viewport);

        $tags->item(0)->appendChild($element);

        return $this;
    }

    private function setAmpBoilerplateInHead(): AMP
    {
        $tags = $this->document->getElementsByTagName('head');

        $style = $this->document->createElement('style');
        $style->appendChild($this->document->createAttribute('amp-boilerplate'));
        $style->appendChild($this->document->createTextNode("body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}"));

        $noscript = $this->document->createElement('noscript');

        $noscript_style = $this->document->createElement('style');
        $noscript_style->appendChild($this->document->createAttribute('amp-boilerplate'));
        $noscript_style->appendChild($this->document->createTextNode("body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}"));

        $noscript->appendChild($noscript_style);

        $tags->item(0)->appendChild($style);
        $tags->item(0)->appendChild($noscript);

        return $this;
    }
}

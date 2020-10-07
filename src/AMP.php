<?php

namespace LaravelAmp;

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

        $document = new DOMDocument( '1.0', 'utf-8');

        $document->loadHTML($this->response->content(), LIBXML_NOERROR);

        $this->document = $document;
    }

    public static function convert(Response $response): Response
    {
        return (new static($response))
            ->setAmpInHtml()
            ->removeCustomJavaScript()
            ->setCharsetInHead()
            ->setScriptInHead()
            ->setCanonicalUrlInHead()
            ->setViewportInHead()
            ->setAmpBoilerplateInHead()
            ->removeDeferFromStylesheets()
            ->cleanDivAttributes()
            ->cleanButtonAttributes()
            ->setImageTags()
            ->convertFormsToAmpForms()
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

    /**
     * This method removes any custom JavaScript from the HTML.
     *
     * There is a double loop in here, because removing the nodes directly causes errors.
     *
     * @return AMP
     */
    private function removeCustomJavaScript(): AMP
    {
        $tags = $this->document->getElementsByTagName('script');

        $whitelisted_script_types = ['application/ld+json'];

        $removable_scripts = [];

        foreach ($tags as $node) {
            $has_blocked_type = $node->hasAttribute('type')
                && !in_array($node->getAttribute('type'), $whitelisted_script_types);

            if (!$node->hasAttribute('type') || $has_blocked_type) {
                $removable_scripts[] = $node;
            }
        }

        foreach ($removable_scripts as $node) {
            $node->parentNode->removeChild($node);
        }

        return $this;
    }

    private function removeDeferFromStylesheets(): AMP
    {
        $tags = $this->document->getElementsByTagName('link');

        foreach ($tags as $node) {
            if ($node->hasAttribute('rel') && $node->getAttribute('rel') === 'stylesheet') {
                $node->removeAttribute('defer');
            }
        }

        return $this;
    }

    private function cleanDivAttributes(): AMP
    {
        $tags = $this->document->getElementsByTagName('div');

        $allowed_attributes = ['class', 'id', 'style'];

        $this->removeBlockedAttributesFromNodes($tags, $allowed_attributes);

        return $this;
    }

    private function cleanButtonAttributes(): AMP
    {
        $tags = $this->document->getElementsByTagName('button');

        $allowed_attributes = ['class', 'id', 'style', 'type'];

        $this->removeBlockedAttributesFromNodes($tags, $allowed_attributes);

        return $this;
    }

    private function setImageTags(): AMP
    {
        $blocked_attributes = ['loading'];

        $tags = $this->document->getElementsByTagName('img');

        while ($tags->length) {
            $img = $tags->item(0);

            $amp_img = $this->document->createElement('amp-img');

            foreach ($img->attributes as $attr) {
                if (!in_array($attr->nodeName, $blocked_attributes)) {
                    $attrName = $attr->nodeName;
                    $attrValue = $attr->nodeValue;
                    $amp_img->setAttribute($attrName, $attrValue);
                }
            }

            $amp_img->setAttribute('layout', 'responsive');

            $image_size = getimagesize($amp_img->getAttribute('src'));

            if ($image_size !== false) {
               $amp_img->setAttribute('width', $image_size[0]);
               $amp_img->setAttribute('height', $image_size[1]);
            }

            $img->parentNode->replaceChild($amp_img, $img);
        }

        return $this;
    }

    /**
     * @param \DOMNodeList $tags
     * @param array $allowed_attributes
     */
    private function removeBlockedAttributesFromNodes(\DOMNodeList $tags, array $allowed_attributes): void
    {
        foreach ($tags as $node) {

            $attributes = [];

            for ($i = 0; $i < $node->attributes->length; $i++) {
                if (!in_array($node->attributes->item($i)->nodeName, $allowed_attributes)) {
                    $attributes[] = $node->attributes->item($i)->nodeName;
                }
            }

            foreach ($attributes as $attribute) {
                $node->removeAttribute($attribute);
            }
        }
    }

    private function convertFormsToAmpForms(): AMP
    {
        $tags = $this->document->getElementsByTagName('form');

        if ($tags->length > 0) {
            $this->setAmpFormImport();
        }

        foreach ($tags as $form) {
            $form->setAttribute('action-xhr', $form->getAttribute('action'));
            $form->removeAttribute('action');
        }

        return $this;
    }

    private function setAmpFormImport()
    {
        $tags = $this->document->getElementsByTagName('head');

        $script = $this->document->createElement('script');
        $script->appendChild($this->document->createAttribute('async'));
        $script->setAttribute('custom-element', 'amp-form');
        $script->setAttribute('src', 'https://cdn.ampproject.org/v0/amp-form-0.1.js');

        $tags->item(0)->appendChild($script);
    }
}

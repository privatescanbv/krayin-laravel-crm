<?php

namespace App\Services\Mail;

use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Masterminds\HTML5;
use Throwable;

/**
 * Splits a raw HTML email body into "main" (new content) and "quoted"
 * (older/quoted thread history), so the frontend can render the history
 * collapsed behind a toggle without doing DOM parsing client-side.
 */
class EmailQuoteSplitter
{
    private const XHTML_NS = 'http://www.w3.org/1999/xhtml';

    /**
     * @var list<string>
     */
    private const FROM_MARKERS = ['van', 'from'];

    /**
     * @var list<string>
     */
    private const SENT_MARKERS = ['verzonden', 'sent'];

    /**
     * @var list<string>
     */
    private const TO_MARKERS = ['aan', 'to'];

    /**
     * @return array{main: string, quoted: string}
     */
    public function split(string $html): array
    {
        $trimmed = trim($html);

        if ($trimmed === '') {
            return ['main' => '', 'quoted' => ''];
        }

        try {
            $html5 = new HTML5;

            // Wrap in a full document (not loadHTMLFragment): XPath attribute
            // predicates ([@id], [@class]) do not reliably match against a
            // detached DOMDocumentFragment.
            $dom = $html5->loadHTML('<html><body>'.$html.'</body></html>');
            $body = $dom->getElementsByTagName('body')->item(0);

            if (! $body) {
                return ['main' => $html, 'quoted' => ''];
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('h', self::XHTML_NS);

            $target = $this->locateQuoteNode($xpath, $body);

            if ($target === null) {
                return ['main' => $html, 'quoted' => ''];
            }

            [$beforeNodes, $afterNodes] = $this->splitChildren($body->childNodes, $target);

            $main = trim($this->nodesToHtml($html5, $dom, $beforeNodes));
            $quoted = trim($this->nodesToHtml($html5, $dom, $afterNodes));

            if ($main === '') {
                // Quote marker was the very first node: nothing to collapse
                // behind it, treat as unsplit.
                return ['main' => $html, 'quoted' => ''];
            }

            return ['main' => $main, 'quoted' => $quoted];
        } catch (Throwable) {
            return ['main' => $html, 'quoted' => ''];
        }
    }

    /**
     * Locate the first node (in document order) that starts quoted
     * history, trying progressively looser heuristics.
     */
    private function locateQuoteNode(DOMXPath $xpath, DOMNode $body): ?DOMNode
    {
        // Tier 1: blockquote / Gmail / Yahoo / OWA reply-forward marker.
        $query = './/h:blockquote'
            ." | .//h:*[contains(concat(' ', normalize-space(@class), ' '), ' gmail_quote ')]"
            ." | .//h:*[@class and contains(@class, 'yahoo_quoted')]"
            ." | .//h:div[@id and contains(@id, 'divRplyFwdMsg')]";

        $nodes = $xpath->query($query, $body);

        if ($nodes !== false && $nodes->length > 0) {
            return $nodes->item(0);
        }

        // Tier 2: Outlook-desktop reply divider — a div/p whose inline style
        // contains "border-top" (typical Outlook pasted divider).
        $borderQuery = './/h:div[@style and contains(translate(@style, '
            ."'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'border-top')]"
            .' | .//h:p[@style and contains(translate(@style, '
            ."'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'border-top')]";

        $borderNodes = $xpath->query($borderQuery, $body);

        if ($borderNodes !== false && $borderNodes->length > 0) {
            return $borderNodes->item(0);
        }

        // Tier 3: plain-text Outlook-desktop header block with no
        // divider/blockquote at all.
        return $this->locateHeaderBlock($xpath, $body);
    }

    /**
     * Walk block-level (p/div) "leaf" elements (no nested p/div — this
     * avoids matching a wrapper that contains the whole body) in document
     * order. For each, test whether its own text starts with a "Van:"/
     * "From:" style line, and whether a "Verzonden:"/"Sent:" and "Aan:"/
     * "To:" line follow within the next few blocks. Returns the first
     * block whose own text begins the header (the split point).
     */
    private function locateHeaderBlock(DOMXPath $xpath, DOMNode $body): ?DOMNode
    {
        $all = $xpath->query('.//h:p | .//h:div', $body);

        if ($all === false) {
            return null;
        }

        $blocks = [];

        foreach ($all as $node) {
            $nested = $xpath->query('.//h:p | .//h:div', $node);
            if ($nested !== false && $nested->length > 0) {
                continue;
            }
            $blocks[] = $node;
        }

        usort($blocks, fn (DOMNode $a, DOMNode $b) => $this->compareDocumentOrder($a, $b));

        $count = count($blocks);

        foreach ($blocks as $i => $block) {
            $lines = $this->blockOwnLines($block);

            if ($lines === [] || ! $this->lineMatchesAny($lines[0], self::FROM_MARKERS)) {
                continue;
            }

            $window = $lines;
            for ($j = $i + 1; $j < min($i + 6, $count); $j++) {
                $window = array_merge($window, $this->blockOwnLines($blocks[$j]));
            }

            $hasSent = false;
            $hasTo = false;

            foreach ($window as $line) {
                $hasSent = $hasSent || $this->lineMatchesAny($line, self::SENT_MARKERS);
                $hasTo = $hasTo || $this->lineMatchesAny($line, self::TO_MARKERS);
            }

            if ($hasSent && $hasTo) {
                return $block;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $markers
     */
    private function lineMatchesAny(string $line, array $markers): bool
    {
        $pattern = '/^('.implode('|', $markers).')\s*:/iu';

        return (bool) preg_match($pattern, trim($line));
    }

    /**
     * Own text of a block, with <br> normalized to newlines so each header
     * field lands on its own line, split into trimmed non-empty lines.
     *
     * @return list<string>
     */
    private function blockOwnLines(DOMNode $block): array
    {
        $clone = $block->cloneNode(true);

        $brs = [];
        foreach ($clone->getElementsByTagName('br') as $br) {
            $brs[] = $br;
        }
        foreach ($brs as $br) {
            $br->parentNode?->replaceChild($clone->ownerDocument->createTextNode("\n"), $br);
        }

        $lines = explode("\n", $clone->textContent);
        $lines = array_map('trim', $lines);

        return array_values(array_filter($lines, fn ($line) => $line !== ''));
    }

    private function compareDocumentOrder(DOMNode $a, DOMNode $b): int
    {
        $position = $a->compareDocumentPosition($b);

        if ($position & DOMNode::DOCUMENT_POSITION_FOLLOWING) {
            return -1;
        }
        if ($position & DOMNode::DOCUMENT_POSITION_PRECEDING) {
            return 1;
        }

        return 0;
    }

    /**
     * Split $target's parent siblings (and, recursively, target's ancestor
     * chain up to $children's owner) into "everything before target" and
     * "target plus everything from target onward" — the PHP DOM equivalent
     * of the browser's Range/cloneContents split, since DOMDocument has no
     * Range API.
     *
     * @return array{0: list<DOMNode>, 1: list<DOMNode>}
     */
    private function splitChildren(DOMNodeList $children, DOMNode $target): array
    {
        $before = [];
        $after = [];
        $count = $children->length;

        for ($i = 0; $i < $count; $i++) {
            $child = $children->item($i);

            if ($child === $target) {
                for ($j = $i; $j < $count; $j++) {
                    $after[] = $children->item($j)->cloneNode(true);
                }

                return [$before, $after];
            }

            if ($this->nodeContains($child, $target)) {
                // Target is nested inside $child: recurse into $child's own
                // children, then re-wrap the (before/after) pieces in
                // shallow clones of $child so ancestor tag/attributes are
                // preserved on both sides.
                [$childBefore, $childAfter] = $this->splitChildren($child->childNodes, $target);

                $beforeShell = $child->cloneNode(false);
                foreach ($childBefore as $node) {
                    $beforeShell->appendChild($node);
                }
                $before[] = $beforeShell;

                $afterShell = $child->cloneNode(false);
                foreach ($childAfter as $node) {
                    $afterShell->appendChild($node);
                }
                $after[] = $afterShell;

                for ($j = $i + 1; $j < $count; $j++) {
                    $after[] = $children->item($j)->cloneNode(true);
                }

                return [$before, $after];
            }

            $before[] = $child->cloneNode(true);
        }

        return [$before, $after];
    }

    private function nodeContains(DOMNode $ancestor, DOMNode $node): bool
    {
        for ($current = $node; $current !== null; $current = $current->parentNode) {
            if ($current === $ancestor) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<DOMNode>  $nodes
     */
    private function nodesToHtml(HTML5 $html5, DOMDocument $ownerDoc, array $nodes): string
    {
        $html = '';

        foreach ($nodes as $node) {
            $frag = $ownerDoc->createDocumentFragment();
            $frag->appendChild($node);
            $html .= $html5->saveHTML($frag);
        }

        return $html;
    }
}

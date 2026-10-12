<?php

namespace Dynamic\ElementalTemplates\Tests\Admin;

use PHPUnit\Framework\TestCase;

/**
 * The admin screenshot capture script injects html2canvas from a CDN at runtime, inside the
 * CMS admin, with the editor's session and security token available to whatever it loads.
 *
 * That makes the injected tag the one place in this module where a third-party file executes
 * with admin privileges, so the tag has to be pinned: a `src` alone lets any change to the
 * CDN file run as the editor. These tests assert the pin survives edits to the shipped
 * `client/dist` artifact - which has no build step and no PHP-side renderer, so the file's
 * own text is the only thing the suite can reach. The integrity value is kept here as a
 * separate constant rather than read back out of the file, so a rewrite of either side is
 * caught; it is the sha384 of the cdnjs html2canvas 1.4.1 file that `src` points at.
 *
 * Extends plain TestCase rather than SapphireTest on purpose: nothing here touches the
 * SilverStripe kernel or the database, so a green run of this file does not depend on MySQL
 * being reachable.
 *
 * @see https://github.com/dynamic/silverstripe-elemental-templates/issues/100
 */
class ScreenshotCaptureSriTest extends TestCase
{
    /**
     * sha384 of https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js
     */
    private const EXPECTED_INTEGRITY = 'sha384-ZZ1pncU3bQe8y31yfZdMFdSpttDoPmOZg2wguVK9almUodir1PghgT0eY7Mrty8H';

    /**
     * The CDN script this module loads, kept in sync with the version pinned in `src`.
     */
    private const EXPECTED_SRC = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';

    /**
     * Returns the raw text of the shipped capture script.
     */
    private function getScriptSource(): string
    {
        $path = dirname(__DIR__, 2) . '/client/dist/js/screenshot-capture.js';
        $source = file_get_contents($path);

        $this->assertIsString($source, 'The shipped screenshot-capture.js must be readable by the test suite');

        return $source;
    }

    /**
     * The injected tag must carry the pinned integrity and a matching crossorigin attribute.
     *
     * Without `crossorigin="anonymous"` the browser fetches the CDN file as a non-CORS
     * request, cannot read its bytes to verify them, and refuses to load the script once an
     * integrity attribute is present - so the two attributes only work as a pair.
     */
    public function testInjectedScriptCarriesIntegrityAndCrossOrigin(): void
    {
        $source = $this->getScriptSource();

        $this->assertMatchesRegularExpression(
            '/script\.integrity\s*=\s*[\'"]' . preg_quote(self::EXPECTED_INTEGRITY, '/') . '[\'"]\s*;/',
            $source,
            'The html2canvas script tag must set the pinned sha384 integrity attribute'
        );

        $this->assertMatchesRegularExpression(
            '/script\.crossOrigin\s*=\s*[\'"]anonymous[\'"]\s*;/',
            $source,
            'The html2canvas script tag must set crossOrigin = "anonymous" so the integrity '
            . 'check is performed against a CORS fetch instead of failing the load'
        );
    }

    /**
     * Both attributes must be assigned before the tag is inserted into the document.
     *
     * Attributes present on the element at append time are what the browser uses; anything
     * assigned after `document.head.appendChild(script)` is too late to be enforced.
     */
    public function testIntegrityIsAssignedBeforeTheScriptIsAppended(): void
    {
        $source = $this->getScriptSource();

        $integrityAt = strpos($source, 'script.integrity');
        $crossOriginAt = strpos($source, 'script.crossOrigin');
        $appendAt = strpos($source, 'document.head.appendChild(script)');

        $this->assertIsInt($appendAt, 'The script must still be appended to document.head');
        $this->assertIsInt($integrityAt, 'script.integrity must be assigned');
        $this->assertIsInt($crossOriginAt, 'script.crossOrigin must be assigned');
        $this->assertLessThan(
            $appendAt,
            $integrityAt,
            'script.integrity must be assigned before document.head.appendChild(script)'
        );
        $this->assertLessThan(
            $appendAt,
            $crossOriginAt,
            'script.crossOrigin must be assigned before document.head.appendChild(script)'
        );
    }

    /**
     * The digest must be well formed, and must be paired with the version it was computed for.
     *
     * Nothing here can hash the CDN bytes (a test must not reach the network), so what this
     * pins is the pairing: the `src` is asserted to still be the 1.4.1 URL this constant was
     * computed from, and the literal is validated as a strict-base64 sha384 digest of the right
     * length rather than a truncated or mistyped string. `integrity` is useless if it was
     * computed for a different resource than `src`, so raising the html2canvas version means
     * recomputing EXPECTED_INTEGRITY from the new file - both constants change together or this
     * test fails.
     */
    public function testIntegrityMatchesThePinnedSourceAndIsAWellFormedSha384Digest(): void
    {
        $source = $this->getScriptSource();

        $this->assertStringContainsString(
            self::EXPECTED_SRC,
            $source,
            'The pinned integrity value belongs to ' . self::EXPECTED_SRC . '; the src must not drift '
            . 'away from it without recomputing the hash'
        );

        $this->assertMatchesRegularExpression(
            '/script\.integrity\s*=\s*[\'"]sha384-([A-Za-z0-9+\/]{64})[\'"]\s*;/',
            $source,
            'script.integrity must be a sha384 digest of 64 base64 characters'
        );

        $matches = [];
        preg_match('/script\.integrity\s*=\s*[\'"]sha384-([A-Za-z0-9+\/]{64})[\'"]\s*;/', $source, $matches);
        $this->assertNotEmpty($matches, 'script.integrity must match the sha384 form');

        $decoded = base64_decode($matches[1], true);
        $this->assertNotFalse($decoded, 'The integrity digest must decode as strict base64');
        $this->assertSame(48, strlen((string)$decoded), 'A sha384 digest decodes to exactly 48 bytes');

        // The only digest accepted here is the one this module pins for html2canvas 1.4.1.
        $this->assertSame(
            self::EXPECTED_INTEGRITY,
            'sha384-' . $matches[1],
            'The integrity literal in the shipped script must equal the pinned html2canvas 1.4.1 digest'
        );
    }

    /**
     * No remote resource other than the pinned html2canvas URL may be referenced, and nothing
     * may be pulled in through a dynamic import().
     *
     * A second, unguarded injection point would reintroduce the vulnerability while this file's
     * own pinned tag still passed the tests above. The match is deliberately wider than
     * ".js": absolute, protocol-relative (`//host/…`), and query-string-carrying URLs are all
     * caught, in single quotes, double quotes or a backtick template literal, as is any
     * `import()`/`require()` of a quoted module - those are the shapes that slip past a narrow
     * `\.js$` pattern. Adding a legitimate second remote later means updating this test on
     * purpose, alongside the pin it has to carry.
     */
    public function testNoOtherRemoteScriptIsInjectedUnguarded(): void
    {
        $source = $this->getScriptSource();

        $matches = [];
        preg_match_all(
            '/[`\'"]((?:https?:)?\/\/[^`\'"\s]+)[`\'"]/',
            $source,
            $matches
        );

        foreach ($matches[1] as $url) {
            $normalised = str_starts_with($url, '//') ? 'https:' . $url : $url;
            $this->assertSame(
                self::EXPECTED_SRC,
                $normalised,
                'Every remote resource referenced by screenshot-capture.js must be the pinned '
                . 'html2canvas URL; found an unpinned reference to ' . $url
            );
        }

        $this->assertMatchesRegularExpression(
            '/[`\'"](?:https?:)?\/\/[^`\'"\s]+[`\'"]/',
            $source,
            'screenshot-capture.js is expected to reference the pinned html2canvas URL; if that '
            . 'changed, EXPECTED_SRC/EXPECTED_INTEGRITY in this test need recomputing together'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\b(?:import|require)\s*\(\s*[\'"][^\'"]+[\'"]\s*\)/',
            $source,
            'screenshot-capture.js must not load code through a dynamic import()/require() of a '
            . 'quoted module: an inline CDN tag with an integrity attribute is the only remote '
            . 'code this module may load, and a dynamic import carries no pin'
        );
    }
}

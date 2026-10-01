<?php

namespace Dynamic\ElementalTemplates\Tests\Models;

use DOMDocument;
use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Tests for Template layout image thumbnail functionality.
 *
 * Every test that needs a thumbnail uses a real image file backed by
 * {@see TestAssetStore}: without a physical file ScaleWidth() returns null and
 * getLayoutImageThumbnail() legitimately returns empty HTML, which used to make
 * these tests silently skip instead of asserting anything.
 */
class TemplateLayoutImageTest extends SapphireTest
{
    protected static $fixture_file = __DIR__ . '/../fixtures.yml';

    /**
     * A valid 1x1 PNG, so the image backend has something real to scale.
     */
    private const TINY_PNG_BASE64 =
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        TestAssetStore::activate('TemplateLayoutImageTest');
    }

    protected function tearDown(): void
    {
        TestAssetStore::reset();

        parent::tearDown();
    }

    /**
     * Writes a real image file to the test asset store.
     *
     * @param string $filename
     * @return Image
     */
    private function createTestImage(string $filename = 'layout-image.png'): Image
    {
        $contents = (string) base64_decode(self::TINY_PNG_BASE64);

        $image = Image::create();
        $image->setFromString($contents, $filename);
        $image->write();

        $this->assertSame(
            $contents,
            $image->getString(),
            'The test image must be readable back from the test asset store'
        );

        return $image;
    }

    /**
     * Creates a template carrying the given title and a real layout image.
     *
     * @param string $title
     * @param string $filename
     * @return array{0: Template, 1: Image}
     */
    private function createTemplateWithImage(string $title, string $filename = 'layout-image.png'): array
    {
        $template = Template::create();
        $template->Title = $title;
        $template->write();

        $image = $this->createTestImage($filename);

        $template->LayoutImageID = $image->ID;
        $template->write();

        return [$template, $image];
    }

    /**
     * Parses the generated thumbnail HTML and returns the browser-decoded value
     * of the thumbnail <img>'s onclick attribute.
     *
     * Parsing (rather than string matching the raw markup) is what reproduces the
     * reported bug: the browser stops reading the attribute at the first `"` that
     * json_encode() emits, so only the decoded attribute shows the truncated
     * script.
     *
     * @param Template $template
     * @return string
     */
    private function getOnclickAttribute(Template $template): string
    {
        $html = $template->getLayoutImageThumbnail()->getValue();

        $this->assertNotSame('', $html, 'getLayoutImageThumbnail() must render a thumbnail for an existing image');

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $parsed = $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>'
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertTrue($parsed, 'Thumbnail HTML must parse: ' . $html);

        $images = $document->getElementsByTagName('img');

        $this->assertSame(1, $images->length, 'Exactly one <img> is expected in ' . $html);

        $onclick = $images->item(0)->attributes->getNamedItem('onclick');

        $this->assertNotNull($onclick, 'The thumbnail <img> must have an onclick attribute in ' . $html);

        return (string) $onclick->nodeValue;
    }

    /**
     * Regression test for #37: the json_encode() output interpolated into the
     * double-quoted onclick attribute used to terminate the attribute, so the
     * browser only saw the script up to `img.src=` and threw
     * "Unexpected end of input".
     */
    public function testGetLayoutImageThumbnailOnclickSurvivesQuotedValues()
    {
        $title = 'Test\'s "Template" with \'quotes\'';
        [$template, $image] = $this->createTemplateWithImage($title);

        $onclick = $this->getOnclickAttribute($template);

        // The full assignment statements must still be inside the attribute.
        $this->assertStringContainsString(
            'img.src=' . json_encode($image->getURL()) . ';',
            $onclick,
            'onclick must contain the complete img.src statement: ' . $onclick
        );
        $this->assertStringContainsString(
            'img.alt=' . json_encode($title) . ';',
            $onclick,
            'onclick must contain the complete img.alt statement: ' . $onclick
        );

        // And the rest of the handler must not be cut off (the reported symptom).
        $this->assertStringContainsString('event.stopPropagation()', $onclick);
        $this->assertStringContainsString('document.body.appendChild(overlay)', $onclick);
        $this->assertStringContainsString('window.__templateOverlay=overlay', $onclick);
    }

    /**
     * The raw markup must not contain a bare `"` inside the onclick attribute
     * value, which is what closed the attribute before the fix.
     */
    public function testGetLayoutImageThumbnailOnclickAttributeIsNotClosedEarly()
    {
        $title = 'Test\'s "Template" with \'quotes\'';
        [$template] = $this->createTemplateWithImage($title, 'quoted-image.png');

        $html = $template->getLayoutImageThumbnail()->getValue();

        $this->assertMatchesRegularExpression('/onclick="event\.stopPropagation\(\);/', $html);
        $this->assertStringContainsString('&quot;', $html);
        $this->assertStringNotContainsString('img.src="', $html);
        $this->assertStringNotContainsString('img.alt="', $html);
    }

    /**
     * Titles without quotes must still render a working handler.
     */
    public function testGetLayoutImageThumbnailOnclickForPlainTitle()
    {
        [$template, $image] = $this->createTemplateWithImage('Plain Template', 'plain-image.png');

        $onclick = $this->getOnclickAttribute($template);

        $this->assertStringContainsString('img.src=' . json_encode($image->getURL()) . ';', $onclick);
        $this->assertStringContainsString('img.alt="Plain Template";', $onclick);
    }

    /**
     * An empty title still produces complete JavaScript rather than a bare keyword.
     */
    public function testGetLayoutImageThumbnailOnclickForEmptyTitle()
    {
        [$template] = $this->createTemplateWithImage('', 'empty-title-image.png');

        $onclick = $this->getOnclickAttribute($template);

        $this->assertStringContainsString('img.alt="";', $onclick);
        $this->assertStringContainsString('document.body.appendChild(overlay)', $onclick);
    }

    /**
     * Test that getLayoutImageThumbnail returns empty DBHTMLText when no image exists
     */
    public function testGetLayoutImageThumbnailWithNoImage()
    {
        $template = Template::create();
        $template->Title = 'Test Template';
        $template->write();

        $result = $template->getLayoutImageThumbnail();

        $this->assertInstanceOf(DBHTMLText::class, $result);
        $this->assertEquals('', $result->getValue());
    }

    /**
     * Test that getLayoutImageThumbnail returns proper HTML structure
     */
    public function testGetLayoutImageThumbnailWithImage()
    {
        /** @var Template $template */
        $template = $this->objFromFixture(Template::class, 'template1');
        $template->LayoutImageID = $this->createTestImage('fixture-image.png')->ID;
        $template->write();

        $result = $template->getLayoutImageThumbnail();

        $this->assertInstanceOf(DBHTMLText::class, $result);
        $html = $result->getValue();

        // Check for expected HTML structure
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('role="button"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('aria-label=', $html);
        $this->assertStringContainsString('onclick=', $html);
        $this->assertStringContainsString('onkeydown=', $html);
    }

    /**
     * Test that title is properly escaped in HTML output
     */
    public function testGetLayoutImageThumbnailEscapesTitle()
    {
        $title = 'Test <script>alert("xss")</script> Template';
        $template = Template::create();
        $template->Title = $title;
        $template->write();

        $template->LayoutImageID = $this->createTestImage('escaped-image.png')->ID;
        $template->write();

        $html = $template->getLayoutImageThumbnail()->getValue();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);

        // The decoded handler still carries the title as one JS string literal
        // (the `<script>` text inside a JS string literal is inert).
        $onclick = $this->getOnclickAttribute($template);

        $this->assertStringContainsString('img.alt=' . json_encode($title) . ';', $onclick);
        $this->assertStringContainsString('document.body.appendChild(overlay)', $onclick);
    }

    /**
     * Test that values interpolated into the inline JavaScript are encoded as
     * JavaScript string literals (and not as bare identifiers).
     */
    public function testGetLayoutImageThumbnailJSONEncodesValues()
    {
        $title = "Test's \"Template\" with 'quotes'";
        [$template] = $this->createTemplateWithImage($title, 'encoded-image.png');

        $onclick = $this->getOnclickAttribute($template);

        // The interpolated title arrives as a quoted JS string literal with its
        // own quotes escaped, not as a broken single-quoted literal.
        $this->assertStringContainsString('img.alt=' . json_encode($title) . ';', $onclick);

        // The interpolated URL likewise stays a single quoted literal.
        $this->assertMatchesRegularExpression(
            '/img\.src="[^"]*encoded-image[^"]*";/',
            $onclick,
            'img.src must be one quoted JS literal: ' . $onclick
        );

        // Never a broken single-quoted literal, which is what an unescaped
        // apostrophe would produce.
        $this->assertStringNotContainsString("img.alt='Test's", $onclick);
        $this->assertStringNotContainsString("img.src='assets", $onclick);
    }

    /**
     * Test that accessibility attributes are present
     */
    public function testGetLayoutImageThumbnailHasAccessibilityAttributes()
    {
        /** @var Template $template */
        $template = $this->objFromFixture(Template::class, 'template1');
        $template->LayoutImageID = $this->createTestImage('accessible-image.png')->ID;
        $template->write();

        $html = $template->getLayoutImageThumbnail()->getValue();

        // Check for accessibility attributes
        $this->assertMatchesRegularExpression('/role=["\']button["\']/', $html);
        $this->assertMatchesRegularExpression('/tabindex=["\']0["\']/', $html);
        $this->assertStringContainsString('aria-label="View larger version of Test Template 1"', $html);

        // Check for keyboard event handler
        $this->assertStringContainsString('onkeydown=', $html);
    }

    /**
     * Test that getLayoutImageThumbnail handles missing image gracefully
     */
    public function testGetLayoutImageThumbnailHandlesMissingImage()
    {
        $template = Template::create();
        $template->Title = 'Test Template';
        $template->LayoutImageID = 99999; // Non-existent image ID
        $template->write();

        $result = $template->getLayoutImageThumbnail();

        $this->assertInstanceOf(DBHTMLText::class, $result);
        $this->assertEquals('', $result->getValue());
    }

    /**
     * Test that getLayoutImageThumbnail returns a DBHTMLText (and empty HTML) for
     * a database record whose file is missing on disk.
     */
    public function testGetLayoutImageThumbnailStructure()
    {
        $template = Template::create();
        $template->Title = 'Test Template';
        $template->write();

        // Record written without ever publishing a file to the test asset store.
        $image = Image::create();
        $image->Filename = 'test-image.jpg';
        $image->write();

        $template->LayoutImageID = $image->ID;
        $template->write();

        $result = $template->getLayoutImageThumbnail();

        $this->assertInstanceOf(DBHTMLText::class, $result);
        $this->assertSame('', $result->getValue(), 'A record with no physical file must render no thumbnail');
    }

    /**
     * Test that empty title doesn't break the output
     */
    public function testGetLayoutImageThumbnailWithEmptyTitle()
    {
        [$template] = $this->createTemplateWithImage('', 'blank-title-image.png');

        $result = $template->getLayoutImageThumbnail();

        $this->assertInstanceOf(DBHTMLText::class, $result);
        $this->assertStringContainsString('alt=""', $result->getValue());
    }
}

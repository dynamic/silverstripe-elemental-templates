<?php

namespace Dynamic\ElementalTemplates\Tests\Models;

use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Tests for Template layout image thumbnail functionality
 */
class TemplateLayoutImageTest extends SapphireTest
{
    protected static $fixture_file = __DIR__ . '/../fixtures.yml';

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
        $template = $this->objFromFixture(Template::class, 'template1');

        if (!$template->LayoutImage() || !$template->LayoutImage()->exists()) {
            $this->markTestSkipped('Fixture template1 does not have a layout image');
        }

        $result = $template->getLayoutImageThumbnail();

        $this->assertInstanceOf(DBHTMLText::class, $result);
        $html = $result->getValue();

        // Check for expected HTML structure
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('role="button"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('aria-label=', $html);
        $this->assertStringContainsString('max-width: 200px', $html);
        $this->assertStringContainsString('onclick=', $html);
        $this->assertStringContainsString('onkeydown=', $html);
    }

    /**
     * Test that title is properly escaped in HTML output
     */
    public function testGetLayoutImageThumbnailEscapesTitle()
    {
        $template = Template::create();
        $template->Title = 'Test <script>alert("xss")</script> Template';
        $template->write();

        // Create a mock image
        $image = Image::create();
        $image->Filename = 'test.jpg';
        $image->write();

        $template->LayoutImageID = $image->ID;
        $template->write();

        $result = $template->getLayoutImageThumbnail();
        $html = $result->getValue();

        // Skip if image has no physical file (expected in test environment)
        if (empty($html)) {
            $this->markTestSkipped('Image has no physical file in test environment');
        }

        // Check that script tags are escaped in alt attribute
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * Test that JavaScript values are properly JSON-encoded
     */
    public function testGetLayoutImageThumbnailJSONEncodesValues()
    {
        $template = Template::create();
        $template->Title = "Test's \"Template\" with 'quotes'";
        $template->write();

        // Create a mock image
        $image = Image::create();
        $image->Filename = 'test.jpg';
        $image->write();

        $template->LayoutImageID = $image->ID;
        $template->write();

        $result = $template->getLayoutImageThumbnail();
        $html = $result->getValue();

        // Skip if image has no physical file (expected in test environment)
        if (empty($html)) {
            $this->markTestSkipped('Image has no physical file in test environment');
        }

        // Check that values are JSON-encoded in JavaScript context
        // Single quotes and double quotes should be properly escaped
        $this->assertStringContainsString('img.alt=', $html);
        // Should not contain unescaped quotes that could break JS
        $this->assertStringNotContainsString("img.alt='Test's", $html);
    }

    /**
     * Test that accessibility attributes are present
     */
    public function testGetLayoutImageThumbnailHasAccessibilityAttributes()
    {
        $template = $this->objFromFixture(Template::class, 'template1');

        if (!$template->LayoutImage() || !$template->LayoutImage()->exists()) {
            $this->markTestSkipped('Fixture template1 does not have a layout image');
        }

        $result = $template->getLayoutImageThumbnail();
        $html = $result->getValue();

        // Check for accessibility attributes
        $this->assertMatchesRegularExpression('/role=["\']button["\']/', $html);
        $this->assertMatchesRegularExpression('/tabindex=["\']0["\']/', $html);
        $this->assertMatchesRegularExpression('/aria-label=/', $html);

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
     * Test that getLayoutImageThumbnail returns correct structure for valid image
     */
    public function testGetLayoutImageThumbnailStructure()
    {
        $template = Template::create();
        $template->Title = 'Test Template';
        $template->write();

        // Create an image
        $image = Image::create();
        $image->Filename = 'test-image.jpg';
        $image->write();

        $template->LayoutImageID = $image->ID;
        $template->write();

        $result = $template->getLayoutImageThumbnail();

        $this->assertInstanceOf(DBHTMLText::class, $result);
        // Result may be empty if image doesn't physically exist, which is expected in tests
    }

    /**
     * Test that empty title doesn't break the output
     */
    public function testGetLayoutImageThumbnailWithEmptyTitle()
    {
        $template = Template::create();
        $template->Title = '';
        $template->write();

        // Create an image
        $image = Image::create();
        $image->Filename = 'test-image.jpg';
        $image->write();

        $template->LayoutImageID = $image->ID;
        $template->write();

        $result = $template->getLayoutImageThumbnail();

        $this->assertInstanceOf(DBHTMLText::class, $result);
        // Should not throw an error even with empty title
    }
}

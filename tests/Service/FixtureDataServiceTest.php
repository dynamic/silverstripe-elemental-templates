<?php

namespace Dynamic\ElementalTemplates\Tests\Service;

use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\Elements\Card\Elements\ElementCard;
use SilverStripe\LinkField\Models\SiteTreeLink;
use Dynamic\Elements\Carousel\Elements\ElementCarousel;
use Dynamic\ElementalTemplates\Models\Template;
        $this->markTestSkipped('Requires Dynamic\\Elements\\Carousel which is not SS6 compatible yet');

        // Create a Template and associate it with an ElementalArea
        $template = Template::create();
        $template->Title = 'Test Template';
        $template->write();

        $elementalArea = ElementalArea::create();
        $elementalArea->write();
        $template->ElementsID = $elementalArea->ID;
        $template->write();

        // Create an ElementCarousel and attach it to the ElementalArea
        $elementCarousel = ElementCarousel::create();
        $elementCarousel->ParentID = $elementalArea->ID;
        $elementCarousel->write();

        // Verify that the ElementCarousel has the expected Slides
        $slides = $elementCarousel->Slides();
        $this->assertCount(2, $slides, 'ElementCarousel should have 2 Slides.');

        // Log the IDs of the slides being added to the ElementCarousel
        foreach ($slides as $slide) {
            echo "Slide ID: " . $slide->ID . "\n";
        }

        foreach ($slides as $slide) {
            $this->assertInstanceOf(ImageSlide::class, $slide, 'Each Slide should be an instance of ImageSlide.');
            $this->assertNotEmpty($slide->Title, 'Each Slide should have a Title.');
            $this->assertInstanceOf(Image::class, $slide->Image(), 'Each Slide should have an associated Image.');
            $this->assertInstanceOf(SiteTreeLink::class, $slide->ElementLink(), 'Each Slide should have an associated Link.');
        }
        $this->markTestSkipped('Test requires dynamic/silverstripe-elemental-carousel which is not yet compatible with SilverStripe 6');
        $this->markTestSkipped('Requires Dynamic\\Elements\\Card which is not SS6 compatible yet');

        // Create a Template and associate it with an ElementalArea
        $template = Template::create();
        $template->Title = 'Test Template';
        $template->write();

        $elementalArea = ElementalArea::create();
        $elementalArea->write();
        $template->ElementsID = $elementalArea->ID;
        $template->write();

        // Create an ElementCard and attach it to the ElementalArea
        $elementCard = ElementCard::create();
        $elementCard->ParentID = $elementalArea->ID;
        $elementCard->write();

        // Verify that the ElementCard has the expected Image and ElementLink records
        $this->assertEquals('Example Card Block', $elementCard->Title);
        $this->assertEquals('<p>This is placeholder content for the card block.</p>', $elementCard->Content);

        // Ensure the ElementLink is retrieved correctly
        $elementLink = $elementCard->ElementLink();
        $this->assertNotNull($elementLink, 'ElementLink should not be null.');
        $this->assertInstanceOf(SiteTreeLink::class, $elementLink, 'ElementLink should be an instance of SiteTreeLink.');
        $this->assertEquals('Learn More', $elementLink->LinkText, 'ElementLink should have the correct LinkText.');
        $this->assertEquals(1, $elementLink->PageID, 'ElementLink should have the correct PageID.');

        $this->assertInstanceOf(Image::class, $elementCard->Image());
        $this->markTestSkipped('Test requires dynamic/silverstripe-elemental-card which is not yet compatible with SilverStripe 6');
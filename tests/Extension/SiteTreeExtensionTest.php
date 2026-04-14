<?php

namespace Dynamic\ElementalTemplates\Tests\Extension;

use Dynamic\ElementalTemplates\Extension\SiteTreeExtension;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;
use Dynamic\ElementalTemplates\Tests\TestOnly\TestTemplate;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\ValidationException;

class SiteTreeExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'CMSPageAddControllerExtensionTest.yml';

    /**
     * @var string[]
     */
    protected static $extra_dataobjects = [
        SamplePage::class,
        TestTemplate::class,
    ];

    public function testApplyTemplateThrowsValidationExceptionOnAjax()
    {
        $page = $this->objFromFixture(SamplePage::class, 'testPage');

        $extension = new SiteTreeExtension();
        $extension->setOwner($page);

        // Mock ajax request
        $request = new HTTPRequest('POST', '/');
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');
        
        $controller = new Controller();
        $controller->setRequest($request);
        $controller->pushCurrent();

        $form = $this->createMock(Form::class);
        $data = ['ApplyTemplateID' => 9999]; // non-existent

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The selected template could not be found.');

        try {
            $extension->applyTemplate($data, $form);
        } finally {
            $controller->popCurrent();
        }
    }

    public function testApplyTemplateReturnsStringOnAjaxSuccess()
    {
        $page = $this->objFromFixture(SamplePage::class, 'testPage');
        $template = $this->objFromFixture(TestTemplate::class, 'testTemplate');

        $extension = new SiteTreeExtension();
        $extension->setOwner($page);

        // Mock ajax request
        $request = new HTTPRequest('POST', '/');
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');
        
        $controller = new Controller();
        $controller->setRequest($request);
        $controller->pushCurrent();

        $form = $this->createMock(Form::class);
        $data = ['ApplyTemplateID' => $template->ID]; // valid existing template

        try {
            $result = $extension->applyTemplate($data, $form);
            $this->assertIsString($result);
        } finally {
            $controller->popCurrent();
        }
    }
}

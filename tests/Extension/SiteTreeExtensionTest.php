<?php

namespace Dynamic\ElementalTemplates\Tests\Extension;

use Dynamic\ElementalTemplates\Extension\SiteTreeExtension;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;
use Dynamic\ElementalTemplates\Tests\TestOnly\TestTemplate;
use LeKoala\CmsActions\CustomAction;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Control\Session;

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

    protected static $required_extensions = [
        SamplePage::class => [
            \DNADesign\Elemental\Extensions\ElementalPageExtension::class,
        ],
    ];

    public function testApplyTemplateThrowsValidationExceptionOnAjax()
    {
        $page = $this->objFromFixture(SamplePage::class, 'testPage');

        $extension = new SiteTreeExtension();
        $extension->setOwner($page);

        // Mock ajax request
        $request = new HTTPRequest('POST', '/');
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');
        $request->setSession(new Session([]));

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
        $request->setSession(new Session([]));

        $controller = new Controller();
        $controller->setRequest($request);
        $controller->pushCurrent();

        $form = $this->createMock(Form::class);
        $data = ['ApplyTemplateID' => $template->ID]; // valid existing template

        try {
            $result = $extension->applyTemplate($data, $form);
            $this->assertIsString($result);
            $this->assertNotSame('', trim($result));
        } finally {
            $controller->popCurrent();
        }
    }

    public function testApplyTemplateActionRedirectsToCurrentRecord()
    {
        $this->logInWithPermission('ADMIN');

        $page = $this->objFromFixture(SamplePage::class, 'testPage');

        $extension = new SiteTreeExtension();
        $extension->setOwner($page);

        // Build the minimal ActionMenus.MoreOptions structure updateCMSActions() expects.
        // Tab::create() signature is ($name, $title, ...$children) — the Information field
        // must be passed as a child, not as the title.
        $moreOptions = Tab::create('MoreOptions', 'More Options', LiteralField::create('Information', 'info'));
        $actions = FieldList::create(TabSet::create('ActionMenus', $moreOptions));

        $extension->updateCMSActions($actions);

        $applyAction = null;
        foreach ($moreOptions->getChildren() as $field) {
            if ($field instanceof CustomAction && $field->actionName() === 'ApplyTemplate') {
                $applyAction = $field;
                break;
            }
        }

        $this->assertInstanceOf(
            CustomAction::class,
            $applyAction,
            'The ApplyTemplate action should be registered for an elemental page.'
        );
        // Without an explicit redirect URL the post-action reload falls back to the
        // referer and ejects the editor to /admin/pages (issue #63).
        $this->assertSame($page->CMSEditLink(), $applyAction->getRedirectURL());
    }
}

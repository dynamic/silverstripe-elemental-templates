<?php

namespace Dynamic\ElementalTemplates\Tests\Extension;

use Dynamic\ElementalTemplates\Extension\SiteTreeExtension;
use Dynamic\ElementalTemplates\Models\Template;
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
use SilverStripe\Versioned\Versioned;

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
        $this->logInWithPermission('ADMIN');

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

    /**
     * Regression test for issue #104: a member who may create templates but may not edit the
     * page must not be able to apply one to it (applying replaces the page's blocks).
     */
    public function testApplyTemplateRefusesWhenMemberCannotEditPage()
    {
        $page = $this->objFromFixture(SamplePage::class, 'testPage');
        $template = $this->objFromFixture(TestTemplate::class, 'testTemplate');

        // Restrict editing of the page to a group the member does not belong to.
        $page->CanEditType = 'OnlyTheseUsers';
        $page->write();
        $page->flushCache();
        $areaID = $page->ElementalAreaID;
        $this->assertGreaterThan(0, $areaID, 'The fixture page should have an elemental area.');
        $before = $this->elementIDs($areaID);
        $this->assertNotEmpty($before);

        // Template-create permission only: no CMS/edit permission on this page.
        $this->logInWithPermission('ELEMENTAL_TEMPLATE_CREATE');
        $this->assertFalse($page->canEdit(), 'Precondition: the member must not be able to edit the page.');

        $extension = new SiteTreeExtension();
        $extension->setOwner($page);

        $request = new HTTPRequest('POST', '/');
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');
        $request->setSession(new Session([]));

        $controller = new Controller();
        $controller->setRequest($request);
        $controller->pushCurrent();

        $form = $this->createMock(Form::class);
        $data = ['ApplyTemplateID' => $template->ID];

        try {
            $threw = null;
            try {
                $extension->applyTemplate($data, $form);
            } catch (ValidationException $e) {
                $threw = $e;
            }
            $this->assertNotNull($threw, 'Applying a template to a page the member cannot edit must be refused.');
            $this->assertStringContainsString('do not have permission', $threw?->getMessage() ?? '');

            // Nothing may be applied: the page's blocks are exactly as they were.
            $this->assertSame($before, $this->elementIDs($areaID), 'No block may be added to the page.');
        } finally {
            $controller->popCurrent();
        }
    }

    /**
     * Regression test for issue #104: CreateTemplate() must not copy blocks out of a page the
     * member cannot view.
     */
    public function testCreateTemplateRefusesWhenMemberCannotViewPage()
    {
        $page = $this->objFromFixture(SamplePage::class, 'testPage');

        // Restrict visibility of the page to a group the member does not belong to.
        $page->CanViewType = 'OnlyTheseUsers';
        $page->write();
        $page->flushCache();

        // Template-create permission only: the member may create templates but cannot see this page.
        $this->logInWithPermission('ELEMENTAL_TEMPLATE_CREATE');
        $this->assertFalse($page->canView(), 'Precondition: the member must not be able to view the page.');

        $before = Template::get()->count();
        $threw = $this->callCreateTemplate($page);

        $this->assertInstanceOf(
            ValidationException::class,
            $threw,
            'CreateTemplate() must refuse with a ValidationException, which cms-actions shows as an error.'
        );
        $this->assertStringContainsString('do not have permission', $threw->getMessage());
        $this->assertSame($before, Template::get()->count(), 'No template may be created from an unseen page.');
    }

    /**
     * The other half of the #104 check: a member who can see the page but may not create
     * templates is refused, and nothing is created.
     */
    public function testCreateTemplateRefusesWhenMemberCannotCreateTemplates()
    {
        $page = $this->objFromFixture(SamplePage::class, 'testPage');

        $this->logInWithPermission('CMS_ACCESS_CMSMain');
        $this->assertTrue($page->canView(), 'Precondition: the member must be able to view the page.');
        $this->assertFalse(Template::singleton()->canCreate(), 'Precondition: the member must not create templates.');

        $before = Template::get()->count();
        $threw = $this->callCreateTemplate($page);

        $this->assertInstanceOf(ValidationException::class, $threw);
        $this->assertStringContainsString('do not have permission', $threw->getMessage());
        $this->assertSame($before, Template::get()->count(), 'No template may be created without the permission.');
    }

    /**
     * A non-admin member with both permissions gets a template, so the check is not over-restrictive.
     */
    public function testCreateTemplateSucceedsForMemberWithBothPermissions()
    {
        // The CMS edits in Draft, which is also the only stage where the new template's area is created.
        Versioned::set_stage(Versioned::DRAFT);

        $page = $this->objFromFixture(SamplePage::class, 'testPage');

        $this->logInWithPermission(['CMS_ACCESS_CMSMain', 'ELEMENTAL_TEMPLATE_CREATE']);
        $this->assertTrue($page->canView(), 'Precondition: the member must be able to view the page.');

        $before = Template::get()->count();
        $threw = $this->callCreateTemplate($page);

        $this->assertNull($threw, 'Unexpected refusal: ' . ($threw ? $threw->getMessage() : ''));
        $this->assertSame($before + 1, Template::get()->count(), 'A template should have been created.');
    }

    /**
     * Runs CreateTemplate() for a page inside a minimal controller, returning what it threw (or null).
     *
     * @param SamplePage $page
     * @return \Throwable|null
     */
    protected function callCreateTemplate(SamplePage $page): ?\Throwable
    {
        $extension = new SiteTreeExtension();
        $extension->setOwner($page);

        $request = new HTTPRequest('POST', '/');
        $request->setSession(new Session([]));

        $controller = new Controller();
        $controller->setRequest($request);
        $controller->pushCurrent();

        $form = $this->createMock(Form::class);
        $data = ['ID' => $page->ID, 'ClassName' => SamplePage::class];

        try {
            $extension->CreateTemplate($data, $form);
        } catch (\Throwable $e) {
            return $e;
        } finally {
            $controller->popCurrent();
        }

        return null;
    }

    /**
     * IDs of the elements currently in an elemental area, in sort order.
     *
     * @param int $areaID
     * @return array<int>
     */
    protected function elementIDs(int $areaID): array
    {
        $area = \DNADesign\Elemental\Models\ElementalArea::get()->byID($areaID);
        if (!$area) {
            return [];
        }
        return $area->Elements()->column('ID');
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

<?php

namespace Dynamic\ElementalTemplates\Tests\Tasks;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Tasks\UpdateBaseElementGlobalAvailabilityTask;
use Dynamic\ElementalTemplates\Tests\TestOnly\SamplePage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class UpdateBaseElementGlobalAvailabilityTaskTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var string[]
     */
    protected static $extra_dataobjects = [
        SamplePage::class,
    ];

    /**
     * @var array<string, string[]>
     */
    protected static $required_extensions = [
        SamplePage::class => [
            ElementalPageExtension::class,
        ],
    ];

    private function runTask(): string
    {
        $buffer = new BufferedOutput();
        $output = new PolyOutput(PolyOutput::FORMAT_ANSI, wrappedOutput: $buffer);
        (new UpdateBaseElementGlobalAvailabilityTask())->run(new ArrayInput([]), $output);

        return $buffer->fetch();
    }

    /**
     * A published block under a published Template, stored global on both stages. The extension forces the
     * flag off on every write, so the legacy state is written straight to the tables.
     */
    private function publishedTemplateBlock(): ElementContent
    {
        $area = ElementalArea::create();
        $area->write();

        $template = Template::create();
        $template->Title = 'Template';
        $template->ElementsID = $area->ID;
        $template->write();

        $block = ElementContent::create();
        $block->Title = 'Original';
        $block->ParentID = $area->ID;
        $block->write();

        $template->publishRecursive();

        $this->forceGlobal($block, [Versioned::DRAFT, Versioned::LIVE]);

        return $block;
    }

    /**
     * @param string[] $stages
     */
    private function forceGlobal(BaseElement $block, array $stages): void
    {
        $tables = [Versioned::DRAFT => 'Element', Versioned::LIVE => 'Element_Live'];

        foreach ($stages as $stage) {
            DB::prepared_query(
                sprintf('UPDATE "%s" SET "AvailableGlobally" = 1 WHERE "ID" = ?', $tables[$stage]),
                [$block->ID]
            );
        }
    }

    private function flag(BaseElement $block, string $stage): ?int
    {
        $element = Versioned::get_by_stage(BaseElement::class, $stage)->byID($block->ID);

        return $element ? (int) $element->AvailableGlobally : null;
    }

    private function onStage(BaseElement $block, string $stage): ?BaseElement
    {
        return Versioned::get_by_stage(BaseElement::class, $stage)->byID($block->ID);
    }

    public function testRepairsAPublishedTemplateElementOnBothStages(): void
    {
        $block = $this->publishedTemplateBlock();
        $this->assertSame(1, $this->flag($block, Versioned::DRAFT), 'Precondition: global on draft');
        $this->assertSame(1, $this->flag($block, Versioned::LIVE), 'Precondition: global on Live');

        $this->runTask();

        $this->assertSame(0, $this->flag($block, Versioned::DRAFT));
        $this->assertSame(0, $this->flag($block, Versioned::LIVE));
        $this->assertFalse(
            $this->onStage($block, Versioned::DRAFT)->stagesDiffer(),
            'An element with no editor changes stays published, not flagged as modified'
        );
    }

    public function testRepairingLiveDoesNotPublishUnpublishedDraftEdits(): void
    {
        $block = $this->publishedTemplateBlock();

        $draft = $this->onStage($block, Versioned::DRAFT);
        $draft->Title = 'Edited on draft';
        $draft->writeToStage(Versioned::DRAFT);
        $this->forceGlobal($block, [Versioned::DRAFT, Versioned::LIVE]);
        $this->assertSame('Edited on draft', $this->onStage($block, Versioned::DRAFT)->Title, 'Precondition');
        $this->assertSame('Original', $this->onStage($block, Versioned::LIVE)->Title, 'Precondition');

        $this->runTask();

        $this->assertSame(0, $this->flag($block, Versioned::LIVE));
        $this->assertSame(
            'Original',
            $this->onStage($block, Versioned::LIVE)->Title,
            'The editor\'s unpublished change must not go live'
        );
        $this->assertSame('Edited on draft', $this->onStage($block, Versioned::DRAFT)->Title);
        $this->assertSame(0, $this->flag($block, Versioned::DRAFT));
    }

    public function testRepairsADraftOnlyTemplateElement(): void
    {
        $area = ElementalArea::create();
        $area->write();
        $template = Template::create();
        $template->Title = 'Draft template';
        $template->ElementsID = $area->ID;
        $template->write();

        $block = ElementContent::create();
        $block->Title = 'Draft block';
        $block->ParentID = $area->ID;
        $block->write();
        $this->forceGlobal($block, [Versioned::DRAFT]);

        $output = $this->runTask();

        $this->assertSame(0, $this->flag($block, Versioned::DRAFT));
        $this->assertNull($this->flag($block, Versioned::LIVE), 'The task does not publish a draft-only element');
        $this->assertStringContainsString('1 draft and 0 Live', $output);
    }

    public function testLeavesAPageElementUntouched(): void
    {
        $area = ElementalArea::create();
        $area->write();
        $page = SamplePage::create();
        $page->Title = 'Sample page';
        $page->ElementalAreaID = $area->ID;
        $page->write();

        $block = ElementContent::create();
        $block->Title = 'Page block';
        $block->ParentID = $area->ID;
        $block->write();
        $block->publishSingle();
        $this->forceGlobal($block, [Versioned::DRAFT, Versioned::LIVE]);

        $output = $this->runTask();

        $this->assertSame(1, $this->flag($block, Versioned::DRAFT));
        $this->assertSame(1, $this->flag($block, Versioned::LIVE));
        $this->assertStringContainsString('0 draft and 0 Live', $output);
    }

    public function testReportsRepairedCountsPerStage(): void
    {
        $this->publishedTemplateBlock();

        $this->assertStringContainsString('1 draft and 1 Live', $this->runTask());
    }
}

<?php

namespace Dynamic\ElementalTemplates\Tests\Tasks;

use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Tasks\PublishTemplatesTask;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class PublishTemplatesTaskTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    private function createTemplate(string $title): Template
    {
        $area = ElementalArea::create();
        $area->write();

        $template = Template::create();
        $template->Title = $title;
        $template->ElementsID = $area->ID;
        $template->write();

        return $template;
    }

    private function runTask(): string
    {
        $buffer = new BufferedOutput();
        $output = new PolyOutput(PolyOutput::FORMAT_ANSI, wrappedOutput: $buffer);
        (new PublishTemplatesTask())->run(new ArrayInput([]), $output);

        return $buffer->fetch();
    }

    private function live(Template $template): ?Template
    {
        return Versioned::get_by_stage(Template::class, Versioned::LIVE)->byID($template->ID);
    }

    private function addBlock(Template $template, string $title): ElementContent
    {
        $block = ElementContent::create();
        $block->Title = $title;
        $block->ParentID = $template->ElementsID;
        $block->write();

        return $block;
    }

    private function liveBlock(ElementContent $block): ?ElementContent
    {
        return Versioned::get_by_stage(ElementContent::class, Versioned::LIVE)->byID($block->ID);
    }

    public function testPublishesADraftOnlyTemplate(): void
    {
        $template = $this->createTemplate('Draft only');
        $this->assertNull($this->live($template), 'Precondition: not published');

        $this->runTask();

        $live = $this->live($template);
        $this->assertNotNull($live, 'The task publishes a Template that exists only on draft');
        $this->assertSame('Draft only', $live->Title);
    }

    public function testPublishesDraftChangesToAPublishedTemplate(): void
    {
        $template = $this->createTemplate('Original');
        $template->publishRecursive();

        $template->Title = 'Edited on draft';
        $template->write();
        $this->assertSame('Original', $this->live($template)->Title, 'Precondition: live is behind draft');

        $this->runTask();

        $this->assertSame('Edited on draft', $this->live($template)->Title);
    }

    public function testPublishesABlockEditedOnDraft(): void
    {
        $template = $this->createTemplate('With block');
        $block = $this->addBlock($template, 'Block v1');
        $template->publishRecursive();

        $block->Title = 'Block v2';
        $block->write();
        $this->assertSame('Block v1', $this->liveBlock($block)->Title, 'Precondition: live block is behind');

        $this->runTask();

        $this->assertSame('Block v2', $this->liveBlock($block)->Title);
    }

    public function testPublishesABlockAddedOnDraft(): void
    {
        $template = $this->createTemplate('With block');
        $template->publishRecursive();

        $added = $this->addBlock($template, 'Added later');
        $this->assertNull($this->liveBlock($added), 'Precondition: new block is draft only');

        $this->runTask();

        $this->assertNotNull($this->liveBlock($added));
    }

    public function testRemovesABlockDeletedOnDraftFromLive(): void
    {
        $template = $this->createTemplate('With block');
        $kept = $this->addBlock($template, 'Kept');
        $removed = $this->addBlock($template, 'Removed');
        $template->publishRecursive();
        $this->assertNotNull($this->liveBlock($removed), 'Precondition: block is live');

        $removed->deleteFromStage(Versioned::DRAFT);

        $this->runTask();

        $this->assertNull($this->liveBlock($removed), 'The block deleted on draft is gone from Live');
        $this->assertNotNull($this->liveBlock($kept));
    }

    public function testLeavesAPublishedUnmodifiedTemplateAlone(): void
    {
        $template = $this->createTemplate('Already live');
        $template->publishRecursive();
        $versionBefore = $this->live($template)->Version;

        $output = $this->runTask();

        $this->assertSame($versionBefore, $this->live($template)->Version, 'No new live version is written');
        $this->assertStringContainsString('already published', $output);
    }
}

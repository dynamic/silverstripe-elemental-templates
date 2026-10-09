<?php

namespace Dynamic\ElementalTemplates\Tests\Tasks;

use DNADesign\Elemental\Models\ElementContent;
use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\ElementalTemplates\Models\Template;
use Dynamic\ElementalTemplates\Tasks\SkeletonElementsPopulateTask;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class SkeletonElementsPopulateTaskTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * Runs the task with deprecation notices enabled and returns the exit status, the task output, and every
     * E_USER_DEPRECATED message Deprecation raised. Deprecation buffers notices and only emits them through
     * user_error() from a shutdown function, so the buffer is flushed inside the capturing handler.
     *
     * @return array{int, string, string[]}
     */
    private function runTaskCapturingNotices(): array
    {
        $notices = [];
        Deprecation::enable();
        set_error_handler(function (int $severity, string $message) use (&$notices): bool {
            if ($severity === E_USER_DEPRECATED) {
                $notices[] = $message;
                return true;
            }
            return false;
        });

        try {
            $buffer = new BufferedOutput();
            $output = new PolyOutput(PolyOutput::FORMAT_ANSI, wrappedOutput: $buffer);
            $status = (new SkeletonElementsPopulateTask())->run(new ArrayInput([]), $output);
            Deprecation::outputNotices();
        } finally {
            restore_error_handler();
            Deprecation::disable();
        }

        return [$status, $buffer->fetch(), $notices];
    }

    private function templateWithBlock(string $templateTitle = 'Skeleton', string $blockTitle = 'Block'): ElementContent
    {
        $area = ElementalArea::create();
        $area->write();

        $template = Template::create();
        $template->Title = $templateTitle;
        $template->ElementsID = $area->ID;
        $template->write();

        $block = ElementContent::create();
        $block->Title = $blockTitle;
        $block->ParentID = $area->ID;
        $block->HTML = 'Original content';
        $block->write();

        return $block;
    }

    /**
     * @param array<string, array<string, string>> $populate
     */
    private function setPopulateConfig(array $populate): void
    {
        Config::modify()->set(Template::class, 'populate', $populate);
    }

    private function html(ElementContent $block, string $stage = Versioned::DRAFT): ?string
    {
        $element = Versioned::get_by_stage(ElementContent::class, $stage)->byID($block->ID);

        return $element ? (string) $element->HTML : null;
    }

    /**
     * The task's own output header/footer always bracket the run; the line it writes per element is the part
     * that says the populate loop visited that element (it writes the line before checking the config).
     */
    private function visitedLines(string $output): array
    {
        return array_values(array_filter(
            explode("\n", $output),
            fn (string $line): bool => str_contains($line, 'Populating content for')
        ));
    }

    /**
     * Notices raised by anything else during the run would satisfy a plain "not empty" check, so every notice
     * assertion narrows to the ones that name this task.
     *
     * @param string[] $notices
     * @return string[]
     */
    private function taskNotices(array $notices): array
    {
        return array_values(array_filter(
            $notices,
            fn (string $notice): bool => str_contains($notice, 'SkeletonElementsPopulateTask')
        ));
    }

    public function testItRaisesTheGlobalDeprecationNotice(): void
    {
        $this->setPopulateConfig([
            ElementContent::class => ['HTML' => 'Populated from config'],
        ]);
        $this->templateWithBlock();

        [$status, $output, $notices] = $this->runTaskCapturingNotices();

        $this->assertSame(Command::SUCCESS, $status);
        $taskNotices = $this->taskNotices($notices);
        $this->assertNotEmpty(
            $taskNotices,
            'The notice names the deprecated task; got: ' . print_r($notices, true)
        );
        $this->assertStringContainsString('BaseElementDataExtension.fixtures', $taskNotices[0]);
        $this->assertStringContainsString('without equivalent functionality', $taskNotices[0]);
    }

    /**
     * The notice has to reach projects that never configured Template::populate, so it is raised ahead of the
     * populate lookup rather than only when there is something to write.
     */
    public function testItRaisesTheNoticeWhenThereIsNothingToPopulate(): void
    {
        [$status, $output, $notices] = $this->runTaskCapturingNotices();

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertSame([], $this->visitedLines($output), 'With no templates there is nothing to visit');
        $this->assertNotEmpty($this->taskNotices($notices), 'An empty database still gets the deprecation notice');
    }

    public function testThePopulateLoopStillWritesConfiguredFields(): void
    {
        $this->setPopulateConfig([
            ElementContent::class => ['HTML' => 'Populated from config'],
        ]);
        $block = $this->templateWithBlock();

        [$status, $output, $notices] = $this->runTaskCapturingNotices();

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertSame('Populated from config', $this->html($block, Versioned::DRAFT));
        $this->assertCount(1, $this->visitedLines($output));
    }

    /**
     * A class the config does not mention keeps its own content, and an unknown class in the config is ignored
     * rather than fataling.
     */
    public function testItLeavesUnconfiguredElementsAloneAndToleratesUnknownClasses(): void
    {
        $this->setPopulateConfig(['Not\\A\\Real\\Element' => ['HTML' => 'Should not be written']]);
        $block = $this->templateWithBlock();

        [$status, $output, $notices] = $this->runTaskCapturingNotices();

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertSame('Original content', $this->html($block));
        $this->assertCount(1, $this->visitedLines($output), 'The element is visited but not written');
        $this->assertNotEmpty($this->taskNotices($notices), 'The notice is raised regardless of what the config holds');
    }
}

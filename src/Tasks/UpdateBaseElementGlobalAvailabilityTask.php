<?php

namespace Dynamic\ElementalTemplates\Tasks;

use DNADesign\Elemental\Models\BaseElement;
use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class UpdateBaseElementGlobalAvailabilityTask extends BuildTask
{
    private static string $segment = 'UpdateBaseElementGlobalAvailabilityTask';

    protected string $title = 'Update BaseElement Global Availability';

    protected static string $description = 'Sets AvailableGlobally to false for BaseElements associated with Templates, on draft and Live.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        if (!BaseElement::singleton()->hasField('AvailableGlobally')) {
            $output->writeln('AvailableGlobally is not installed (dnadesign/silverstripe-elemental-virtual); nothing to do.');

            return Command::SUCCESS;
        }

        $draftRepaired = 0;
        $liveRepaired = 0;

        foreach ($this->globalElementIDs() as $id) {
            $draft = $this->elementOnStage($id, Versioned::DRAFT);
            $live = $this->elementOnStage($id, Versioned::LIVE);

            if (!$this->belongsToTemplate($draft ?? $live)) {
                continue;
            }

            // Decide before touching draft: an element whose Live copy differs from draft has unpublished
            // editor changes, and those must not go live as a side effect of this repair.
            $inSync = $draft && $live && !$draft->stagesDiffer();

            $draftFlagged = $draft && $draft->AvailableGlobally;
            $liveFlagged = $live && $live->AvailableGlobally;

            if ($draftFlagged) {
                $draft->AvailableGlobally = false;
                $draft->writeToStage(Versioned::DRAFT);
                $draftRepaired++;
            }

            if ($inSync && $draftFlagged) {
                // The draft write bumped the draft version; republish so the element stays published
                // instead of showing as modified. Safe because there were no editor changes to publish.
                $draft->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
            } elseif ($liveFlagged) {
                $this->clearLiveFlag($id);
            }

            if ($liveFlagged) {
                $liveRepaired++;
            }
        }

        $output->writeln("Repaired AvailableGlobally on {$draftRepaired} draft and {$liveRepaired} Live element(s).");
        $output->writeln('Task completed.');

        return Command::SUCCESS;
    }

    /**
     * IDs of elements flagged global on either stage.
     *
     * @return int[]
     */
    private function globalElementIDs(): array
    {
        $ids = [];

        foreach ([Versioned::DRAFT, Versioned::LIVE] as $stage) {
            $ids = array_merge(
                $ids,
                Versioned::get_by_stage(BaseElement::class, $stage)->filter('AvailableGlobally', 1)->column('ID')
            );
        }

        return array_values(array_unique($ids));
    }

    /**
     * Clears the flag on the Live row only. Writing the Live copy through the ORM would overwrite the
     * editor's unpublished draft changes, and publishing the draft would push them live.
     */
    private function clearLiveFlag(int $id): void
    {
        $table = DataObject::getSchema()->tableName(BaseElement::class) . '_Live';

        DB::prepared_query(sprintf('UPDATE "%s" SET "AvailableGlobally" = 0 WHERE "ID" = ?', $table), [$id]);
    }

    private function elementOnStage(int $id, string $stage): ?BaseElement
    {
        /** @var BaseElement|null $element */
        $element = Versioned::get_by_stage(BaseElement::class, $stage)->byID($id);

        return $element;
    }

    private function belongsToTemplate(?BaseElement $element): bool
    {
        return $element !== null && $element->getPage() instanceof Template;
    }
}

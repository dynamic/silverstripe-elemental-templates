<?php

namespace Dynamic\ElementalTemplates\Form;

use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Admin\AdminRootController;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\FormField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;

/**
 * A visual template picker field that displays templates as a grid of cards
 * with preview images.
 */
class TemplatePickerField extends FormField
{
    /**
     * @var string
     */
    protected $schemaDataType = FormField::SCHEMA_DATA_TYPE_SINGLESELECT;

    /**
     * @var array
     */
    protected $templates = [];

    /**
     * @var string|null Filter templates by page type
     */
    protected $pageTypeFilter = null;

    /**
     * @var int|null The page ID for apply actions
     */
    protected $pageID = null;

    /**
     * @param string $name Field name
     * @param string|null $title Field title
     * @param string|null $pageTypeFilter Filter templates to specific page type
     */
    public function __construct(string $name, ?string $title = null, ?string $pageTypeFilter = null)
    {
        parent::__construct($name, $title);
        $this->pageTypeFilter = $pageTypeFilter;
    }

    /**
     * Set the page type filter for templates
     *
     * @param string|null $pageType
     * @return $this
     */
    public function setPageTypeFilter(?string $pageType): TemplatePickerField
    {
        $this->pageTypeFilter = $pageType;
        return $this;
    }

    /**
     * Get the page type filter
     *
     * @return string|null
     */
    public function getPageTypeFilter(): ?string
    {
        return $this->pageTypeFilter;
    }

    /**
     * Set the page ID for apply actions
     *
     * @param int|null $pageID
     * @return $this
     */
    public function setPageID(?int $pageID): TemplatePickerField
    {
        $this->pageID = $pageID;
        return $this;
    }

    /**
     * Get the page ID
     *
     * @return int|null
     */
    public function getPageID(): ?int
    {
        return $this->pageID;
    }

    /**
     * Get templates as an ArrayList for use in templates
     *
     * @return ArrayList
     */
    public function getTemplates(): ArrayList
    {
        $list = ArrayList::create();
        $templates = Template::get();

        // Filter by page type if set. A template matches when its PageType is the
        // current class or any ancestor of it (a template tagged "Page" applies to a
        // "BlockPage" too), or when it has no PageType at all (untyped = applies to
        // any page type). Filtering in PHP keeps the NULL/blank handling explicit —
        // a `filter('PageType', ...)` query would silently exclude untyped templates.
        if ($this->pageTypeFilter) {
            $ancestry = array_map('strtolower', array_values(ClassInfo::ancestry($this->pageTypeFilter)));
            $matched = ArrayList::create();
            foreach ($templates as $template) {
                $pageType = $template->PageType;
                $isUniversal = ($pageType === null || $pageType === '');
                if ($isUniversal || in_array(strtolower($pageType), $ancestry, true)) {
                    $matched->push($template);
                }
            }
            $templates = $matched;
        }

        foreach ($templates as $template) {
            $thumbnail = null;
            $thumbnailUrl = null;

            if ($template->LayoutImage() && $template->LayoutImage()->exists()) {
                // Use ScaleWidth for fixed width but auto height to show full template preview
                $thumbnail = $template->LayoutImage()->ScaleWidth(200);
                $thumbnailUrl = $thumbnail ? $thumbnail->getURL() : null;
            }

            $list->push(ArrayData::create([
                'ID' => $template->ID,
                'Title' => $template->Title,
                'Category' => trim((string) $template->Category),
                'Description' => $template->dbObject('Description'),
                'HasThumbnail' => (bool) $thumbnailUrl,
                'ThumbnailURL' => $thumbnailUrl,
                'PreviewLink' => $template->getPreviewLink(),
                'IsSelected' => (int) $this->getValue() === (int) $template->ID,
                'ElementCount' => $template->Elements()->Elements()->count(),
            ]));
        }

        return $list;
    }

    /**
     * Templates grouped by Category for rendering. Groups follow the configured
     * category order, categories missing from the config follow them, and
     * uncategorised templates come last. When no template has a category the
     * single group has a blank Title so the field renders as a flat list with
     * no group headings.
     *
     * @return ArrayList
     */
    public function getGroupedTemplates(): ArrayList
    {
        $templates = $this->getTemplates();

        $grouped = [];
        foreach (array_keys(Template::getTemplateCategories()) as $category) {
            $grouped[$category] = ArrayList::create();
        }
        $uncategorised = ArrayList::create();

        foreach ($templates as $template) {
            $category = (string) $template->Category;
            if ($category === '') {
                $uncategorised->push($template);
                continue;
            }
            if (!isset($grouped[$category])) {
                $grouped[$category] = ArrayList::create();
            }
            $grouped[$category]->push($template);
        }

        $groups = ArrayList::create();
        foreach ($grouped as $category => $list) {
            if ($list->count()) {
                $groups->push(ArrayData::create([
                    'Title' => $category,
                    'Templates' => $list,
                ]));
            }
        }

        if ($uncategorised->count()) {
            // Label the group only when it sits alongside categorised groups; an
            // all-uncategorised library renders as a flat, heading-less list.
            $groups->push(ArrayData::create([
                'Title' => $groups->count() ? 'Other' : '',
                'Templates' => $uncategorised,
            ]));
        }

        return $groups;
    }

    /**
     * Check if there are any templates available
     *
     * @return bool
     */
    public function hasTemplates(): bool
    {
        return $this->getTemplates()->count() > 0;
    }

    /**
     * Get the field type for templates
     *
     * @return string
     */
    public function Type(): string
    {
        return 'templatepicker';
    }

    /**
     * Get field attributes
     *
     * @return array
     */
    public function getAttributes(): array
    {
        $attrs = parent::getAttributes();
        $attrs['data-field-type'] = 'template-picker';
        return $attrs;
    }

    /**
     * Render the field content
     *
     * @param array $properties
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    public function Field($properties = [])
    {
        $properties = array_merge($properties, [
            'Templates' => $this->getTemplates(),
            'GroupedTemplates' => $this->getGroupedTemplates(),
            'hasTemplates' => $this->hasTemplates(),
            'PageID' => $this->getPageID(),
            'AdminURL' => AdminRootController::admin_url('elemental-templates'),
        ]);

        return $this->customise($properties)->renderWith(
            'Dynamic\\ElementalTemplates\\Form\\TemplatePickerField'
        );
    }
}

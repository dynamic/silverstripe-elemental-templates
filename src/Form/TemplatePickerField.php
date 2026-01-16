<?php

namespace Dynamic\ElementalTemplates\Form;

use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;

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
    public function setPageTypeFilter(?string $pageType): self
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
    public function setPageID(?int $pageID): self
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

        // Filter by page type if set
        if ($this->pageTypeFilter) {
            $templates = $templates->filter('PageType', $this->pageTypeFilter);
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
                'Description' => $template->dbObject('Description'),
                'HasThumbnail' => (bool) $thumbnailUrl,
                'ThumbnailURL' => $thumbnailUrl,
                'PreviewLink' => $template->getPreviewLink(),
                'IsSelected' => (int) $this->Value() === (int) $template->ID,
                'ElementCount' => $template->Elements()->Elements()->count(),
            ]));
        }

        return $list;
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
            'hasTemplates' => $this->hasTemplates(),
            'PageID' => $this->getPageID(),
            'AdminURL' => \SilverStripe\Admin\AdminRootController::admin_url(),
        ]);

        return $this->customise($properties)->renderWith(
            'Dynamic\\ElementalTemplates\\Form\\TemplatePickerField'
        );
    }
}

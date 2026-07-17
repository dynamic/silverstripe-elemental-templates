<?php

namespace Dynamic\ElementalTemplates\Models;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use DNADesign\Elemental\Forms\ElementalAreaField;
use DNADesign\Elemental\Models\ElementalArea;
use LeKoala\CmsActions\CustomAction;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Member;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Control\Director;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Creates a Template of elements that can be used to set up a page
 *
 * @property string $Title
 * @property string $PageType
 * @property string $Category
 * @property int $ElementsID
 * @property int $LayoutImageID
 * @method ElementalArea Elements()
 * @method Image LayoutImage()
 * @mixin \DNADesign\Elemental\Extensions\ElementalAreasExtension
 * @mixin \SilverStripe\Versioned\Versioned
 */
class Template extends DataObject implements PermissionProvider
{
    /**
     * @var string
     * @config
     */
    private static string $table_name = 'ElementTemplate';

    /**
     * @var string
     * @config
     */
    private static string $singular_name = 'Template';

    /**
     * @var string
     * @config
     */
    private static string $plural_name = 'Templates';

    /**
     * @var array|string[]
     * @config
     */
    private static array $db = [
        'Title' => 'Varchar',
        'PageType' => 'Varchar',
        'Category' => 'Varchar',
        'Description' => 'HTMLText',
    ];

    /**
     * Category options offered in the CMS and used to group template pickers.
     *
     * This is a numerically-indexed list, so SilverStripe config merging
     * appends project YAML entries to these defaults rather than replacing
     * them. To remove or reorder the defaults, first reset the config (an
     * empty `template_categories: null` document) and then declare the full
     * list.
     *
     * @var array|string[]
     * @config
     */
    private static array $template_categories = [
        'Heroes',
        'Cards & Grids',
        'Content',
        'Social Proof',
        'Conversion',
        'People',
        'Contact & Location',
        'Listings',
        'Page Layouts',
    ];

    /**
     * @var array|string[]
     * @config
     */
    private static array $has_one = [
        'Elements' => ElementalArea::class,
        'LayoutImage' => Image::class,
    ];

    /**
     * @var array|string[]
     * @config
     */
    private static array $owns = [
        'Elements',
    ];

    /**
     * @var array|string[]
     * @config
     */
    private static array $cascade_deletes = [
        'Elements',
    ];

    /**
     * @var array|string[]
     * @config
     */
    private static array $cascade_duplicates = [
        'Elements',
    ];

    /**
     * @var array|string[]
     * @config
     */
    private static array $extensions = [
        ElementalAreasExtension::class,
        Versioned::class,
    ];

    /**
     * @var array|string[]
     * @config
     */
    private static array $summary_fields = [
        'LayoutImageThumbnail' => 'Preview Image',
        'Title' => 'Name',
        'Category' => 'Category',
        'PageTypeName' => 'Page Type',
        'ElementCount' => 'Blocks',
    ];

    /**
     * @var array|string[]
     * @config
     */
    private static array $searchable_fields = [
        'Title',
        'Category',
    ];

    /**
     * @var array|string[]
     * @config
     */
    private static array $field_labels = [
        'PageTypeName' => 'Page Type',
    ];

    /**
     * @param string $extension
     * @param string $baseClass
     * @return array
     * @throws \ReflectionException
     */
    public static function getDecoratedBy(string $extension, string $baseClass): array
    {
        $classes = [];
        $currentUser = Security::getCurrentUser();

        foreach (ClassInfo::subClassesFor($baseClass) as $className) {
            $class = $className::singleton();

            // Check if the class has the specified extension
            if ($class::has_extension($className, $extension)) {
                // Check if the user has create permissions for this class
                if ($currentUser && $class->canCreate($currentUser)) {
                    $classes[$className] = $class->singular_name();
                }
            }
        }

        return $classes;
    }

    /**
     * @return FieldList
     * @throws \ReflectionException
     */
    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $pageTypes = Template::getDecoratedBy(ElementalAreasExtension::class, \Page::class);

            $fields->removeByName([
                'Sort',
                'ElementsID',
            ]);

            $fields->replaceField(
                'PageType',
                $pt = DropdownField::create('PageType', 'Which page type to use as the base', $pageTypes)
            );

            $pt->setEmptyString('Please choose...');
            $pt->setRightTitle('This will determine which elements are possible to add to the template');

            $fields->replaceField(
                'Category',
                DropdownField::create('Category', 'Category', static::getTemplateCategories())
                    ->setEmptyString('None')
                    ->setRightTitle('Groups this template in the template pickers')
            );

            if ($this->isinDB()) {
                $fields->replaceField('PageType', $pt->performReadonlyTransformation());

                $fields->addFieldToTab(
                    'Root.Main',
                    ElementalAreaField::create('Elements', $this->Elements(), $this->getAllowedTypes())
                );
            }

            $layoutImage = $fields->dataFieldByName('LayoutImage');
            if ($layoutImage && method_exists($layoutImage, 'setFolderName')) {
                $layoutImage->setFolderName('Uploads/templates');
            }
            if ($layoutImage && method_exists($layoutImage, 'setAllowedFileCategories')) {
                $layoutImage->setAllowedFileCategories('image');
            }
        });

        $fields = parent::getCMSFields();

        $el = $fields->dataFieldByName('Elements');
        if ($el && method_exists($el, 'setTypes')) {
            $el->setTypes($this->getAllowedTypes());
        }

        return $fields;
    }

    /**
     * Generate the preview link for the template.
     *
     * @return string
     */
    public function getPreviewLink(): string
    {
        return Controller::join_links(
            Director::absoluteBaseURL(),
            'template-preview',
            $this->ID
        );
    }

    /**
     * Returns the link to the template preview.
     *
     * @return string
     */
    public function Link()
    {
        return $this->getPreviewLink();
    }

    /**
     * Returns the parent site configuration.
     *
     * @return SiteConfig
     */
    public function Parent()
    {
        return SiteConfig::current_site_config();
    }

    /**
     * Configured category options as a value => label map.
     *
     * @return array<string, string>
     */
    public static function getTemplateCategories(): array
    {
        $map = [];

        foreach ((array) static::config()->get('template_categories') as $category) {
            $map[$category] = $category;
        }

        return $map;
    }

    /**
     * Group an iterable of template records (or view models exposing Category /
     * ID / Title) into an ordered map of category label => list-of-items. The
     * single grouping algorithm shared by both the page-add dropdown and the
     * visual picker so they can never disagree on the same data:
     *
     *  - configured categories first, in `template_categories` order (empty
     *    ones dropped),
     *  - categories present in data but not configured, after those,
     *  - uncategorised items merged into `$fallbackLabel` — merging into a real
     *    configured "Other" group rather than overwriting it.
     *
     * When every item is uncategorised the result is a single group keyed by
     * the empty string, so callers can render a flat, heading-less list.
     *
     * @param iterable $items
     * @param string $fallbackLabel
     * @return array<string, array<int, object>> ordered; a sole '' key = flat
     */
    public static function groupByCategory(iterable $items, string $fallbackLabel = 'Other'): array
    {
        $groups = [];
        foreach (array_keys(static::getTemplateCategories()) as $category) {
            $groups[$category] = [];
        }

        $uncategorised = [];
        foreach ($items as $item) {
            $category = trim((string) $item->Category);
            if ($category === '') {
                $uncategorised[] = $item;
                continue;
            }
            $groups[$category][] = $item;
        }

        $groups = array_filter($groups);

        if ($uncategorised) {
            if (!$groups) {
                // Nothing is categorised: render flat, with no heading.
                return ['' => $uncategorised];
            }
            // Append (never overwrite) so a real "Other" category keeps its own.
            $groups[$fallbackLabel] = array_merge($groups[$fallbackLabel] ?? [], $uncategorised);
        }

        return $groups;
    }

    /**
     * All templates grouped by Category as a GroupedDropdownField source. Uses
     * {@see self::groupByCategory()} so ordering/fallback matches the visual
     * picker. When no template is categorised a flat id => title map is
     * returned (scalar values render as ungrouped options).
     *
     * @return array<string, array<int, string>>|array<int, string>
     */
    public static function getGroupedTemplateMap(string $fallbackLabel = 'Other'): array
    {
        $grouped = static::groupByCategory(static::get(), $fallbackLabel);

        // Sole '' key => no categories in play: flat, ungrouped options.
        if (array_keys($grouped) === ['']) {
            $flat = [];
            foreach ($grouped[''] as $template) {
                $flat[$template->ID] = $template->Title;
            }
            return $flat;
        }

        $source = [];
        foreach ($grouped as $category => $templates) {
            $options = [];
            foreach ($templates as $template) {
                $options[$template->ID] = $template->Title;
            }
            $source[$category] = $options;
        }

        return $source;
    }

    /**
     * @return mixed
     */
    protected function getAllowedTypes()
    {
        $pageType = $this->PageType;

        // A non-empty but unresolvable PageType is stale/misconfigured data;
        // return nothing so it surfaces rather than being silently masked.
        if ($pageType && !class_exists($pageType)) {
            return [];
        }

        // Untyped templates apply to any page type, so offer the base Page's
        // elemental types rather than nothing.
        if (!$pageType) {
            $pageType = \Page::class;
        }

        if (!class_exists($pageType)) {
            return [];
        }

        $singleton = $pageType::singleton();
        if (!$singleton->hasMethod('getElementalTypes')) {
            return [];
        }

        return $singleton->getElementalTypes();
    }

    /**
     * @return string
     */
    public function PageTypeName(): string
    {
        if (!$this->PageType) {
            return '';
        }
        return singleton($this->PageType)->singular_name();
    }

    /**
     * Returns the number of elements in this template.
     *
     * @return int
     */
    public function getElementCount(): int
    {
        if ($this->Elements()->exists()) {
            return $this->Elements()->Elements()->count();
        }
        return 0;
    }

    /**
     * Returns a thumbnail for the layout image in summary fields.
     * Uses ScaleWidth to preserve aspect ratio like TemplatePickerField.
     * Includes click-to-enlarge functionality with accessibility support.
     *
     * @return DBHTMLText
     */
    public function getLayoutImageThumbnail()
    {
        if ($this->LayoutImage() && $this->LayoutImage()->exists()) {
            // Use ScaleWidth for aspect-ratio preserving thumbnail (matches template picker)
            $thumbnail = $this->LayoutImage()->ScaleWidth(200);
            // Use original image for enlarged view to avoid extra scaling
            $fullUrl = $this->LayoutImage()->getURL();

            if ($thumbnail) {
                $thumbnailUrl = $thumbnail->getURL();
                // Properly escape for JavaScript context
                $title = htmlspecialchars($this->Title, ENT_QUOTES, 'UTF-8');
                $titleJs = json_encode($this->Title);
                $fullUrlJs = json_encode($fullUrl);

                $html = sprintf(
                    '<div style="position: relative; display: inline-block;"><img src="%s" alt="%s" role="button" tabindex="0" aria-label="View larger version of %s" style="width: 200px; height: auto; max-height: 300px; object-fit: contain; display: block; cursor: pointer; border-radius: 4px;" onclick="event.stopPropagation();if(window.__templateOverlay&&window.__templateOverlay.parentNode){window.__templateOverlay.parentNode.removeChild(window.__templateOverlay);window.__templateOverlay=null;}var previouslyFocused=document.activeElement;var overlay=document.createElement(\'div\');overlay.setAttribute(\'role\',\'dialog\');overlay.setAttribute(\'aria-modal\',\'true\');overlay.setAttribute(\'aria-label\',\'Enlarged template preview\');overlay.style.cssText=\'position:fixed;top:0;left:0;width:100%%;height:100%%;background:rgba(0,0,0,0.8);z-index:10000;display:flex;align-items:center;justify-content:center;cursor:pointer;\';overlay.tabIndex=-1;var img=document.createElement(\'img\');img.src=%s;img.alt=%s;img.style.cssText=\'max-width:90%%;max-height:90%%;box-shadow:0 0 20px rgba(0,0,0,0.5);\';overlay.appendChild(img);var closeOverlay=function(){if(overlay&&overlay.parentNode){overlay.parentNode.removeChild(overlay);if(window.__templateOverlay===overlay){window.__templateOverlay=null;}if(previouslyFocused&&typeof previouslyFocused.focus===\'function\'){previouslyFocused.focus();}}};overlay.onclick=function(event){if(event.target===overlay){closeOverlay();}};overlay.addEventListener(\'keydown\',function(e){if(e.key===\'Escape\'||e.key===\'Esc\'){e.preventDefault();closeOverlay();}else if(e.key===\'Tab\'){e.preventDefault();overlay.focus();}});document.body.appendChild(overlay);window.__templateOverlay=overlay;overlay.focus();" onkeydown="if(event.key===\'Enter\'||event.key===\' \'||event.key===\'Spacebar\'){event.preventDefault();this.click();}" title="Click to view larger" /></div>',
                    $thumbnailUrl,
                    $title,
                    $title,
                    $fullUrlJs,
                    $titleJs
                );

                return DBHTMLText::create()->setValue($html);
            }
        }

        return DBHTMLText::create()->setValue('');
    }

    /**
     * @return string
     */
    public function CMSEditLink(): string
    {
        return Controller::join_links(
            'admin',
            'elemental-templates',
            'Dynamic-ElementalTemplates-Models-Template',
            'EditForm',
            'field',
            'Dynamic-ElementalTemplates-Models-Template',
            'item',
            $this->ID,
            'edit'
        );
    }

    /**
     * Retrieve a elemental area relation name which this element owns
     *
     * @return string
     */
    public function getOwnedAreaRelationName(): string
    {
        $has_one = $this->config()->get('has_one');

        foreach ($has_one as $relationName => $relationClass) {
            if ($relationClass === ElementalArea::class && $relationName !== 'Parent') {
                return $relationName;
            }
        }

        return 'Elements';
    }

    /**
     * @return array[]
     */
    public function providePermissions(): array
    {
        return [
            'ELEMENTAL_TEMPLATE_CREATE' => [
                'name' => 'Create a template',
                'category' => 'Elemental Templates',
            ],
            'ELEMENTAL_TEMPLATE_EDIT' => [
                'name' => 'Edit a template',
                'category' => 'Elemental Templates',
            ],
            'ELEMENTAL_TEMPLATE_DELETE' => [
                'name' => 'Delete a template',
                'category' => 'Elemental Templates',
            ],
        ];
    }

    /**
     * @param $member
     * @param $context
     * @return bool
     */
    public function canCreate($member = null, $context = []): bool
    {
        if ($member === null) {
            $member = $this->getUser();
        }

        if ($member->can('ELEMENTAL_TEMPLATE_CREATE')) {
            return true;
        }

        return parent::canCreate($member, $context);
    }

    /**
     * @param $member
     * @return bool
     */
    public function canEdit($member = null): bool
    {
        if ($member === null) {
            $member = $this->getUser();
        }

        if ($member->can('ELEMENTAL_TEMPLATE_EDIT')) {
            return true;
        }

        return parent::canEdit($member);
    }

    /**
     * @param $member
     * @return bool
     */
    public function canDelete($member = null): bool
    {
        if ($member === null) {
            $member = $this->getUser();
        }

        if ($member->can('ELEMENTAL_TEMPLATE_DELETE')) {
            return true;
        }

        return parent::canDelete($member);
    }

    /**
     * @param $member
     * @return bool
     */
    public function canArchive($member = null): bool
    {
        if ($member === null) {
            $member = $this->getUser();
        }

        if ($member->can('ELEMENTAL_TEMPLATE_DELETE')) {
            return true;
        }

        return parent::canDelete($member);
    }

    /**
     * @param $member
     * @return bool
     */
    public function canView($member = null): bool
    {
        return true;
    }

    /**
     * @return Member|null
     */
    protected function getUser(): ?Member
    {
        return Security::getCurrentUser();
    }

    /**
     * @return FieldList
     */
    public function getCMSActions(): FieldList
    {
        $actions = parent::getCMSActions();

        // Add a custom CMS action for previewing the template
        $actions->push(
            CustomAction::create('PreviewTemplate', 'Preview Template')
                ->setUseButtonTag(true)
                ->setAttribute('onclick', "window.open('{$this->getPreviewLink()}', '_blank')")
        );

        // Add screenshot capture button (only for saved templates with elements)
        if ($this->exists() && $this->Elements()->exists() && $this->Elements()->Elements()->count() > 0) {
            $contentOnlyUrl = $this->getPreviewLink() . '?content_only=1';
            $templateID = $this->ID;
            $securityToken = \SilverStripe\Security\SecurityToken::getSecurityID();

            $actions->push(
                CustomAction::create('CaptureScreenshot', 'Capture Preview Image')
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn-outline-secondary font-icon-upload')
                    ->setAttribute('data-template-id', $templateID)
                    ->setAttribute('data-preview-url', $contentOnlyUrl)
                    ->setAttribute('data-upload-url', '/template-screenshot-upload/upload')
                    ->setAttribute('data-security-id', $securityToken)
            );
        }

        return $actions;
    }

    /**
     * Get the content-only preview URL for screenshot capture.
     *
     * @return string
     */
    public function getContentOnlyPreviewLink(): string
    {
        return $this->getPreviewLink() . '?content_only=1';
    }
}

<?php

namespace Dynamic\ElememtalTemplates\Models;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use DNADesign\Elemental\Forms\ElementalAreaField;
use DNADesign\Elemental\Models\ElementalArea;
use LeKoala\CmsActions\CustomAction;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

/**
 * Creates a Template of elements that can be used to set up a page
 *
 * @property string $Title
 * @property string $PageType
 * @property int $ElementsID
 * @property int $LayoutImageID
 * @method \DNADesign\Elemental\Models\ElementalArea Elements()
 * @method \SilverStripe\Assets\Image LayoutImage()
 * @mixin \DNADesign\Elemental\Extensions\ElementalAreasExtension
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
    ];

    /**
     * @var array|string[]
     * @config
     */
    private static array $summary_fields = [
        'Title' => 'Layout Name',
        'LayoutImage.CMSThumbnail' => 'Preview Image',
        'PageTypeName' => 'Page Type',
    ];

    /**
     * @var array|string[]
     * @config
     */
    private static array $field_labels = [
        'PageTypeName' => 'Page Type',
    ];

    /**
     * @param $extension
     * @param $baseClass
     * @return array
     * @throws \ReflectionException
     */
    public static function getDecoratedBy($extension, $baseClass): array
    {
        $classes = [];

        foreach (ClassInfo::subClassesFor($baseClass) as $className) {
            $class = $className::singleton();
            if ($class::has_extension($className, $extension)) {
                $classes[$className] = singleton($className)->singular_name();
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
            $pageTypes = self::getDecoratedBy(ElementalAreasExtension::class, \Page::class);

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

            if ($this->isinDB()) {
                $fields->replaceField('PageType', $pt->performReadonlyTransformation());

                $fields->addFieldToTab(
                    'Root.Main',
                    ElementalAreaField::create('ElementsID', $this->Elements(), $this->getAllowedTypes())
                );
            }

            $fields->dataFieldByName('LayoutImage')
                ->setFolderName('Uploads/templates')
                ->setAllowedFileCategories('image');
        });

        return parent::getCMSFields();
    }

    /**
     * @return mixed
     */
    protected function getAllowedTypes()
    {
        $pageType = $this->PageType;

        if (!$pageType || !class_exists($pageType)) {
            return []; // Return an empty array if PageType is invalid
        }

        return $pageType::singleton()->getElementalTypes();
    }

    /**
     * @return string
     */
    public function PageTypeName(): string
    {
        if (!$this->PageType) {
            return false;
        }
        return singleton($this->PageType)->singular_name();
    }

    /**
     * @return string
     */
    public function CMSEditLink(): string
    {
        return Controller::join_links(
            'admin',
            'elemental-templates',
            'Dynamic-ElememtalTemplates-Models-Template',
            'EditForm',
            'field',
            'Dynamic-ElememtalTemplates-Models-Template',
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
}

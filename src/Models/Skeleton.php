<?php

namespace DNADesign\ElementalSkeletons\Models;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
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
 * Creates a Skeleton of elements that can be used to set up a page
 *
 * @property string $Title
 * @property string $PageType
 * @property int $ElementsID
 * @property int $LayoutImageID
 * @method \DNADesign\Elemental\Models\ElementalArea Elements()
 * @method \SilverStripe\Assets\Image LayoutImage()
 * @mixin \DNADesign\Elemental\Extensions\ElementalAreasExtension
 */
class Skeleton extends DataObject implements PermissionProvider
{
    /**
     * @var string
     */
    private static string $table_name = 'ElementSkeletons';

    /**
     * @var string
     */
    private static string $singular_name = 'Skeleton';

    /**
     * @var string
     */
    private static string $plural_name = 'Skeletons';

    /**
     * @var array|string[]
     */
    private static array $db = [
        'Title' => 'Varchar',
        'PageType' => 'Varchar',
    ];

    /**
     * @var array|string[]
     */
    private static array $has_one = [
        'Elements' => ElementalArea::class,
        'LayoutImage' => Image::class,
    ];

    /**
     * @var array|string[]
     */
    private static array $owns = [
        'Elements',
    ];

    /**
     * @var array|string[]
     */
    private static array $cascade_deletes = [
        'Elements',
    ];

    /**
     * @var array|string[]
     */
    private static array $cascade_duplicates = [
        'Elements',
    ];

    /**
     * @var array|string[]
     */
    private static array $extensions = [
        ElementalAreasExtension::class,
    ];

    /**
     * @var array|string[]
     */
    private static array $summary_fields = [
        'Title' => 'Layout Name',
        'LayoutImage.CMSThumbnail' => 'Preview Image',
        'PageTypeName' => 'Page Type',
    ];

    /**
     * @var array|string[]
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
        $fields = parent::getCMSFields();

        $pageTypes = self::getDecoratedBy(ElementalAreasExtension::class, \Page::class);

        $fields->removeByName('Sort');
        $fields->replaceField('PageType', $pt = DropdownField::create('PageType', 'Which page type to use as the base', $pageTypes));

        $pt->setEmptyString('Please choose...');
        $pt->setRightTitle('This will determine which elements are possible to add to the skeleton');

        if ($this->isinDB()) {
            $fields->push(TreeDropdownField::create('ParentID', 'Parent Page', \Page::class)->setEmptyString('Parent page (empty for root)'));
            $fields->push(TextField::create('PageTitle', 'Page Title')->setDescription('Title for new page'));
        }

        $fields->dataFieldByName('LayoutImage')
            ->setFolderName('Uploads/Skeletons')
            ->setAllowedFileCategories('image');

        return $fields;
    }

    /**
     * @return FieldList
     */
    public function getCMSActions(): FieldList
    {
        $actions = parent::getCMSActions();

        if ($this->isinDB()) {
            $actions->push(
                CustomAction::create('createPage', 'Create new ' . $this->Title . ' page')
                    ->addExtraClass('btn btn-success font-icon-plus-circled')
            );
        }

        return $actions;
    }

    /**
     * @return string
     */
    public function PageTypeName(): string
    {
        return singleton($this->PageType)->singular_name();
    }

    /**
     * @param $request
     * @return string
     */
    public function createPage($request): string
    {
        $pageType = $this->PageType;
        $parentID = $request['ParentID'] ?? 0;

        $page = $pageType::create();
        $page->ParentID = $parentID;
        if ($request['PageTitle']) {
            $page->Title = $request['PageTitle'];
        }
        $page->write();
        $page->writeToStage(Versioned::DRAFT);

        $area = $page->ElementalArea();

        foreach ($this->Elements()->Elements() as $element) {
            $copy = $element->duplicate();
            $copy->write();
            $copy->writeToStage(Versioned::DRAFT);
            $area->Elements()->add($copy);
        }

        return 'Page Added';
    }

    /**
     * @return string
     */
    public function CMSEditLink(): string
    {
        return Controller::join_links(
            'admin',
            'elemental-skeletons',
            'DNADesign-ElementalSkeletons-Models-Skeleton',
            'EditForm',
            'field',
            'DNADesign-ElementalSkeletons-Models-Skeleton',
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
            'ELEMENTAL_SKELETONS_CREATE' => [
                'name' => 'Create a skeleton',
                'category' => 'Elemental Skeletons',
            ],
            'ELEMENTAL_SKELETONS_EDIT' => [
                'name' => 'Edit a skeleton',
                'category' => 'Elemental Skeletons',
            ],
            'ELEMENTAL_SKELETONS_DELETE' => [
                'name' => 'Delete a skeleton',
                'category' => 'Elemental Skeletons',
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

        if ($member->can('ELEMENTAL_SKELETONS_CREATE')) {
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

        if ($member->can('ELEMENTAL_SKELETONS_EDIT')) {
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

        if ($member->can('ELEMENTAL_SKELETONS_DELETE')) {
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

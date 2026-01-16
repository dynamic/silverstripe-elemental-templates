<?php

namespace Dynamic\ElementalTemplates\Admin;

use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;

/**
 * GridField component that adds a "Preview" action to the row actions menu
 */
class GridFieldPreviewAction implements GridField_ColumnProvider, GridField_ActionMenuItem
{
    /**
     * @param GridField $gridField
     * @param array $columns
     */
    public function augmentColumns($gridField, &$columns)
    {
        // No new columns needed, we're adding to the Actions column
    }

    /**
     * @param GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField): array
    {
        return [];
    }

    /**
     * @param GridField $gridField
     * @param \SilverStripe\ORM\DataObject $record
     * @param string $columnName
     * @return string
     */
    public function getColumnContent($gridField, $record, $columnName): string
    {
        return '';
    }

    /**
     * @param GridField $gridField
     * @param string $columnName
     * @param \SilverStripe\ORM\DataObject $record
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName): array
    {
        return [];
    }

    /**
     * @param GridField $gridField
     * @param string $columnName
     * @return array
     */
    public function getColumnMetadata($gridField, $columnName): array
    {
        return [];
    }

    /**
     * @param GridField $gridField
     * @param \SilverStripe\ORM\DataObject $record
     * @return string|null
     */
    public function getTitle($gridField, $record, $columnName): ?string
    {
        return 'Preview';
    }

    /**
     * @param GridField $gridField
     * @param \SilverStripe\ORM\DataObject $record
     * @return string|null
     */
    public function getExtraData($gridField, $record, $columnName): ?array
    {
        return [
            'classNames' => 'font-icon-eye action-menu--preview',
        ];
    }

    /**
     * @param GridField $gridField
     * @param \SilverStripe\ORM\DataObject $record
     * @return string|null
     */
    public function getGroup($gridField, $record, $columnName): ?string
    {
        return GridField_ActionMenuItem::DEFAULT_GROUP;
    }

    /**
     * @param GridField $gridField
     * @param \SilverStripe\ORM\DataObject $record
     * @return string|null
     */
    public function getUrl($gridField, $record, $columnName): ?string
    {
        if ($record instanceof Template) {
            return $record->getPreviewLink();
        }
        return null;
    }
}

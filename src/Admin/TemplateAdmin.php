<?php

namespace Dynamic\ElementalTemplates\Admin;

use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;

/**
 * Class \Dynamic\ElementalTemplates\Admin\TemplateAdmin
 *
 */
class TemplateAdmin extends ModelAdmin
{
    /**
     * @var string[]
     */
    private static array $managed_models = [
        Template::class,
    ];

    /**
     * @var string
     */
    private static string $menu_title = 'Element Templates';

    /**
     * @var string
     */
    private static string $url_segment = 'elemental-templates';

    /**
     * @var string
     */
    private static string $menu_icon_class = 'font-icon-block-layout';

    /**
     * Customize the GridField to add a Preview action
     *
     * @param int|null $id
     * @param \SilverStripe\Forms\FieldList|null $fields
     * @return Form
     */
    public function getEditForm($id = null, $fields = null): Form
    {
        $form = parent::getEditForm($id, $fields);

        if ($this->modelClass === Template::class) {
            /** @var GridField $gridField */
            $gridField = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));

            if ($gridField instanceof GridField) {
                $config = $gridField->getConfig();

                // Add preview action component
                $config->addComponent(new GridFieldPreviewAction());
            }
        }

        return $form;
    }
}

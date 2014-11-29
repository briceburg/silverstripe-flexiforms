<?php

class FlexiFormExtension extends DataExtension
{

    private static $flexiform_tab = 'Root.Form';

    private static $flexiform_insertBefore = null;

    private static $flexiform_addButton = 'Create New Field';

    /**
     * Allowed FlexiFormField Types
     * @var Array
     */
    private static $flexiform_field_types = array(
        'FlexiFormTextField',
        'FlexiFormEmailField',
        'FlexiFormDropdownField',
        'FlexiFormCheckboxField',
        'FlexiFormRadioField',
        'FlexiFormCheckboxSetField'
    );

    /**
     * An array of field definitions that are automatically added to newly
     * created forms. See documentation for field definitions.
     * @var Array
     */
    private static $flexiform_initial_fields = array();

    private static $db = array();

    private static $has_many = array(
        'Submissions'
    );

    private static $many_many = array(
        'FlexiFormFields' => 'FlexiFormField'
    );

    private static $many_many_extraFields = array(
        'FlexiFormFields' => array(
            'Name' => 'Varchar',
            'Prompt' => 'Varchar',
            'DefaultValue' => 'Varchar',
            'Required' => 'Boolean',
            'SortOrder' => 'Int'
        )
    );

    public function updateCMSFields(FieldList $fields)
    {
        if ($this->owner->exists()) {

            $config = new GridFieldConfig_FlexiForm();

            // Multi-Class Add Button
            // ///////////////////////
            $classes = array();
            foreach ($this->getFlexiFormFieldTypes() as $className) {
                $class = singleton($className);
                $classes[$className] = "{$class->Label()} Field";
            }

            $component = $config->getComponentByType('GridFieldAddNewMultiClass');
            $component->setClasses($classes);
            $component->setTitle($this->getFlexiFormAddButton());

            $fields->addFieldToTab($this->getFlexiFormTab(),
                new GridField('FlexiForm', 'Form Fields', $this->owner->FlexiFormFields(), $config));
        } else {
            $fields->addFieldToTab($this->getFlexiFormTab(),
                new LiteralField('FlexiForm', '<p>Please save before editing the form.</p>'));
        }
    }

    /**
     * Get the FieldList for this form
     *
     * @return FieldList
     */
    public function getFlexiFormFrontEndFields()
    {
        return new FieldList($this->owner->FlexiFormFields()->toArray());
    }

    // Getters & Setters
    ////////////////////
    public function getFlexiFormTab()
    {
        return $this->lookup('flexiform_tab');
    }

    public function setFlexiFormTab($tab_name)
    {
        return $this->owner->set_stat('flexiform_tab', $tab_name);
    }

    public function getFlexiFormInsertBefore()
    {
        return $this->lookup('flexiform_insertBefore');
    }

    public function setFlexiFormInsertBefore($field_name)
    {
        return $this->owner->set_stat('flexiform_insertBefore', $field_name);
    }

    public function getFlexiFormAddButton()
    {
        return $this->lookup('flexiform_addButton');
    }

    public function setFlexiFormAddButton($button_name)
    {
        return $this->owner->set_stat('flexiform_addButton', $button_name);
    }

    public function getFlexiFormFieldTypes()
    {
        return $this->lookup('flexiform_field_types', true);
    }

    public function setFlexiFormFieldTypes(Array $field_types)
    {
        return $this->owner->set_stat('flexiform_field_types', $field_types);
    }

    public function addFlexiFormFieldType($className)
    {
        if (! class_exists($className)) {
            throw new Exception("FlexiFormField class $className not found");
        }

        if (! singleton($className)->is_a('FlexiFormField')) {
            throw new Exception("$className is not a FlexiFormField");
        }

        $field_types = $this->getFlexiFormFieldTypes();
        $field_types[] = $className;

        return $this->setFlexiFormFieldTypes($field_types);
    }

    public function getFlexiFormInitialFields()
    {
        return $this->lookup('flexiform_initial_fields', true);
    }

    public function setFlexiFormInitialFields(Array $field_types)
    {
        return $this->owner->set_stat('flexiform_initial_fields', $field_types);
    }

    // Utility Methods
    //////////////////
    private function lookup($lookup, $do_not_merge = false)
    {
        if ($do_not_merge &&
             $unmerged = Config::inst()->get($this->owner->class, $lookup, Config::EXCLUDE_EXTRA_SOURCES)) {
            return $unmerged;
        }

        return $this->owner->stat($lookup);
    }

    public function validate(ValidationResult $validationResult)
    {
        $names = array();
        if ($result->valid()) {
            foreach ($this->owner->FlexiFormFields() as $field) {

                if (empty($field->Name)) {
                    $result->error("Field names cannot be blank. Encountered a blank {$field->Label()} field.");
                    break;
                }

                if (in_array($field->Name, $names)) {
                    $result->error(
                        "Field Names must be unique per form. {$field->Name} was encountered twice.");
                    break;
                } else {
                    $names[] = $field->Name;
                }

                $default_value = $field->DefaultValue;
                if (! empty($default_value) && $field->Options()->exists() &&
                     ! in_array($default_value, $field->Options()->column('Value'))) {
                    $result->error("The default value of {$field->getName()} must exist as an option value");
                    break;
                }
            }
        }
    }

    public function onAfterWrite()
    {
        // if this is a newly created form, prepopulate fields
        if ($this->owner->isChanged('ID')) {

            $fields = $this->owner->FlexiFormFields();
            foreach ($this->getFlexiFormInitialFields() as $field_type => $definition) {

                if (is_string($definition)) {

                    // lookup field name, prioritizing Readonly fields
                    if (! $field = FlexiFormField::get()->sort('Readonly', 'DESC')
                        ->filter(
                        array(
                            'FieldName' => $definition,
                            'ClassName' => $field_type
                        ))
                        ->first()) {
                        throw new ValidationException("No $field_type field found named `$definition`");
                    }
                } elseif (is_array($definition)) {
                    $field = FlexiFormUtil::CreateFlexiField($field_type, $definition);
                } else {
                    throw new ValidationException('Unknown Field Definition Encountered');
                }

                $fields->add($field);
            }
        }

        return parent::onAfterWrite();
    }
}
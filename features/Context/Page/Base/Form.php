<?php

namespace Context\Page\Base;

use Behat\Mink\Element\Element;
use Behat\Mink\Element\ElementInterface;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Context\Traits\ClosestTrait;
use Pim\Behat\Decorator\Field\Select2Decorator;

/**
 * Basic form page
 *
 * @author    Gildas Quemener <gildas@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Form extends Base
{
    use ClosestTrait;

    /**
     * {@inheritdoc}
     */
    public function __construct($session, $pageFactory, $parameters = [])
    {
        parent::__construct($session, $pageFactory, $parameters);

        $this->elements = array_merge(
            [
                'Dialog'                          => ['css' => 'div.modal'],
                'Associations list'               => ['css' => '.associations-list'],
                'Groups'                          => ['css' => '.tab-groups, .AknVerticalNavtab'],
                'Group'                           => ['css' => '.AknVerticalNavtab-link:contains("%s")'],
                'Validation errors'               => ['css' => '.validation-tooltip'],
                'Available attributes form'       => ['css' => '#pim_available_attributes'],
                'Available attributes button'     => ['css' => 'button:contains("Add attributes")'],
                'Available attributes list'       => ['css' => '.pimmultiselect .ui-multiselect-checkboxes'],
                'Available attributes search'     => ['css' => '.pimmultiselect input[type="search"]'],
                'Available attributes add button' => ['css' => '.pimmultiselect a.btn:contains("Add")'],
                'Updates grid'                    => ['css' => '.tab-pane.tab-history table.grid, .tab-container .history'],
                'Save'                            => ['css' => '.AknButton--apply'],
                'Panel sidebar'                   => [
                    'css'        => '.edit-form > .content',
                    'decorators' => ['Pim\Behat\Decorator\Page\PanelableDecorator'],
                ],
                'Tooltips'                         => ['css' => '.icon-info-sign'],
            ],
            $this->elements
        );
    }

    /**
     * Press the save button
     */
    public function save()
    {
        $this->getElement('Save')->click();
    }

    /**
     * Press the save button
     */
    public function saveAndClose()
    {
        $this->pressButton('Save and close');
    }

    /**
     * Open the specified panel
     *
     * @param string $panel
     */
    public function openPanel($panel)
    {
        $elt = $this->spin(function () {
            return $this->getElement('Panel selector');
        }, 'Can not find the Panel selector');

        $panel = strtolower($panel);
        if (null === $elt->find('css', sprintf('button[data-panel$="%s"].active', $panel))) {
            $elt->find('css', sprintf('button[data-panel$="%s"]', $panel))->click();
        }
    }

    /**
     * Close the specified panel
     *
     * @throws \Context\Spin\TimeoutException
     */
    public function closePanel()
    {
        $elt = $this->spin(function () {
            return $this->getElement('Panel container')->find('css', 'header .close');
        });

        $elt->click();
    }

    /**
     * Get the tabs in the current page
     *
     * @return NodeElement[]
     */
    public function getTabs()
    {
        $tabs = $this->spin(function () {
            return $this->find('css', $this->elements['Tabs']['css']);
        });

        if (!$tabs) {
            $tabs = $this->getElement('Oro tabs');
        }

        return $tabs->findAll('css', 'a');
    }

    /**
     * Get the form tab containing $tab text
     *
     * @param string $tab
     *
     * @return NodeElement|null
     */
    public function getFormTab($tab)
    {
        $tabs = $this->getPageTabs();

        try {
            $node = $this->spin(function () use ($tabs, $tab) {
                return $tabs->find('css', sprintf('a:contains("%s")', $tab));
            }, sprintf('Cannot find form tab "%s"', $tab));
        } catch (\Exception $e) {
            $node = null;
        }

        return $node;
    }

    /**
     * Get the specified tab
     *
     * @param string $tab
     *
     * @return NodeElement
     */
    public function getTab($tab)
    {
        return $this->spin(function () use ($tab) {
            return $this->find('css', sprintf('a:contains("%s")', $tab));
        }, sprintf('Cannot find the tab named "%s"', $tab));
    }

    /**
     * Visit the specified group
     *
     * @param string $group
     */
    public function visitGroup($group)
    {
        $this->spin(function () use ($group) {
            $group = $this->find('css', sprintf($this->elements['Group']['css'], $group));
            if (null !== $group && $group->isVisible()) {
                $group->click();

                return true;
            }
        }, sprintf('Cannot find the group "%s".', $group));
    }

    /**
     * @return NodeElement
     */
    public function getAssociationsList()
    {
        return $this->spin(function () {
            return $this->find('css', $this->elements['Associations list']['css']);
        }, sprintf('Cannot find association list "%s"', $this->elements['Associations list']['css']));
    }

    /**
     * Get the specified section
     *
     * @param string $title
     *
     * @return NodeElement
     */
    public function getSection($title)
    {
        return $this->find('css', sprintf('div.tabsection-title:contains("%s")', $title));
    }

    /**
     * @param string $name
     * {@inheritdoc}
     */
    public function findField($name)
    {
        return $this->spin(function () use ($name) {
            if ($tab = $this->find('css', $this->elements['Active tab']['css'])) {
                return $tab->findField($name);
            }

            return parent::findField($name);
        }, sprintf('Could not find field "%s"', $name));
    }

    /**
     * Find field container
     *
     * @param string $name
     *
     * @throws ElementNotFoundException
     *
     * @return NodeElement
     */
    public function findFieldContainer($name)
    {
        $label = $this->spin(function () use ($name) {
            return $this->find('css', sprintf('label:contains("%s")', $name));
        }, sprintf('Label containing text "%s" not found'), $name);

        $field = $this->spin(function () use ($label) {
            return $label->getParent()->find('css', 'input,textarea');
        }, sprintf('Can not find any input or textearea sibling of "%s" label', $name));

        return $field->getParent();
    }

    /**
     * Get validation errors
     *
     * @return array:string
     */
    public function getValidationErrors()
    {
        $tooltips = $this->findAll('css', $this->elements['Validation errors']['css']);
        $errors   = [];

        foreach ($tooltips as $tooltip) {
            $errors[] = $tooltip->getAttribute('data-original-title');
        }

        return $errors;
    }

    /**
     * Get tooltips messages
     *
     * @return string[]
     */
    public function getTooltipMessages()
    {
        $tooltips = $this->findAll('css', $this->elements['Tooltips']['css']);

        $messages = [];
        foreach ($tooltips as $tooltip) {
            $messages[] = $tooltip->getAttribute('data-original-title');
        }

        return $messages;
    }

    /**
     * Open the available attributes popin
     */
    public function openAvailableAttributesMenu()
    {
        $this->getElement('Available attributes button')->click();
    }

    /**
     * Add available attributes
     *
     * @param array $attributes
     */
    public function addAvailableAttributes(array $attributes = [])
    {
        $this->spin(function () {
            return $this->find('css', $this->elements['Available attributes button']['css']);
        }, sprintf('Cannot find element "%s"', $this->elements['Available attributes button']['css']));

        $list = $this->getElement('Available attributes list');
        if (!$list->isVisible()) {
            $this->openAvailableAttributesMenu();
        }

        $search = $this->getElement('Available attributes search');
        foreach ($attributes as $attributeLabel) {
            $search->setValue($attributeLabel);
            $label = $this->spin(
                function () use ($list, $attributeLabel) {
                    return $list->find('css', sprintf('li label:contains("%s")', $attributeLabel));
                },
                sprintf('Could not find available attribute "%s".', $attributeLabel)
            );

            $label->click();
        }

        $this->getElement('Available attributes add button')->press();
    }

    /**
     * @param string $attribute
     * @param string $group
     *
     * @return NodeElement
     */
    public function findAvailableAttributeInGroup($attribute, $group)
    {
        return $this->getElement('Available attributes form')->find(
            'css',
            sprintf(
                'optgroup[label="%s"] option:contains("%s")',
                $group,
                $attribute
            )
        );
    }

    /**
     * Attach file to file field
     *
     * @param string $locator
     * @param string $path
     *
     * @throws ElementNotFoundException
     */
    public function attachFileToField($locator, $path)
    {
        $field = $this->spin(function () use ($locator) {
            $field = $this->findField($locator);
            if (null === $field) {
                return false;
            }
            if ($field->getAttribute('type') === 'file') {
                $field = $field->getParent()->find('css', 'input[type="file"]');
            }
            if ($field !== null) {
                return $field;
            }
            echo "retry find file input" . PHP_EOL;
        }, sprintf('Cannot find "%s" file field', $locator));

        $field->attachFile($path);
        $this
            ->getSession()
            ->executeScript('$(\'.edit .field-input input[type="file"], .AknMediaField-fileUploaderInput\').trigger(\'change\');');
    }

    /**
     * Remove file from file field
     *
     * @param string $locator
     *
     * @throws ElementNotFoundException
     */
    public function removeFileFromField($locator)
    {
        $field = $this->findField($locator);

        if (null === $field) {
            throw new ElementNotFoundException($this->getSession(), 'form field', 'id|name|label|value', $locator);
        }

        $checkbox = $field->getParent()->find('css', 'input[type="checkbox"]');

        if (null === $checkbox) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Remove checkbox',
                'associated file input',
                $locator
            );
        }

        $checkbox->check();
    }

    /**
     * This method allows to fill a compound field by passing the label in reversed order separated
     * with whitespaces.
     *
     * Example:
     * We have a field "$" embedded inside a "Price" field
     * We can call fillField('$ Price', 26) to set the "$" value of parent field "Price"
     *
     * @param string  $field
     * @param string  $value
     * @param Element $element
     */
    public function fillField($field, $value, Element $element = null)
    {
        $label     = $this->extractLabelElement($field, $element);
        $fieldType = $this->getFieldType($label);
        switch ($fieldType) {
            case 'multiSelect2':
                $this->fillMultiSelect2Field($label, $value);
                break;
            case 'simpleSelect2':
                $this->fillSelect2Field($label, $value);
                break;
            case 'datepicker':
                $this->fillDateField($label, $value);
                break;
            case 'select':
                $this->fillSelectField($label, $value);
                break;
            case 'wysiwyg':
                $this->fillWysiwygField($label, $value);
                break;
            case 'text':
                $this->fillTextField($label, $value);
                break;
            case 'compound':
                $this->fillCompoundField($label, $value);
                break;
            default:
                parent::fillField($label->labelContent, $value);
                break;
        }
    }

    /**
     * @return array
     */
    public function getHistoryRows()
    {
        return $this->spin(function () {
            return $this->getElement('Updates grid')->findAll('css', 'tbody tr');
        }, 'Cannot find the history rows.');
    }

    /**
     * @param string $attribute
     */
    public function expandAttribute($attribute)
    {
        $label = $this->spin(function () use ($attribute) {
            return $this->find('css', sprintf('label:contains("%s")', $attribute));
        }, sprintf('Cannot find attribute "%s" field', $attribute));

        $this->expand($label);
    }

    /**
     * @param string $label
     */
    public function expand($label)
    {
        if ($icon = $label->getParent()->find('css', '.icon-caret-right')) {
            $icon->click();
        }
    }

    /**
     * @param string $groupField
     * @param string $field
     *
     * @throws \InvalidArgumentException
     */
    public function findFieldInTabSection($groupField, $field)
    {
        $tabSection = $this->spin(function () use ($groupField) {
            return $this->find(
                'css',
                sprintf('.tabsection-title:contains("%s")', $groupField)
            );
        }, sprintf('Could not find tab section "%s"', $groupField));

        $accordionContent = $tabSection->getParent()->find('css', '.tabsection-content');

        $this->spin(function () use ($accordionContent, $field) {
            return $accordionContent->findField($field);
        }, sprintf('Could not find a "%s" field inside the %s accordion group', $field, $groupField));
    }

    /**
     * Fill field in a simple popin
     *
     * @param array $fields
     */
    public function fillPopinFields($fields)
    {
        foreach ($fields as $field => $value) {
            $field = $this->spin(function () use ($field) {
                return $this->find('css', sprintf('.modal-body .control-label:contains("%s") input', $field));
            }, sprintf('Cannot find "%s" in popin field', $field));

            $field->setValue($value);
            $this->getSession()
                ->executeScript('$(\'.modal-body .control-label:contains("%s") input\').trigger(\'change\');');
        }
    }

    /**
     * Check if a select field contains (or not) the specified choices
     *
     * @param string $label
     * @param array  $choices
     * @param bool   $isExpected
     *
     * @throws ExpectationException
     */
    public function checkFieldChoices($label, array $choices, $isExpected = true)
    {
        $labelElement = $this->extractLabelElement($label);
        $container = $this->getClosest($labelElement, 'AknFieldContainer');
        $select2 = $this->spin(function () use ($container) {
            return $container->find('css', '.select2');
        }, 'Impossible to find the select');
        $select2 = $this->decorate($select2, [Select2Decorator::class]);
        $selectChoices = $select2->getAvailableValues();
        if ($isExpected) {
            foreach ($choices as $choice) {
                if (!in_array($choice, $selectChoices)) {
                    throw new ExpectationException(sprintf(
                        'Expecting to find choice "%s" in field "%s"',
                        $choice,
                        $labelElement
                    ), $this->getSession());
                }
            }
        } else {
            foreach ($choices as $choice) {
                if (in_array($choice, $selectChoices)) {
                    throw new ExpectationException(sprintf(
                        'Choice "%s" should not be in available for field "%s"',
                        $choice,
                        $labelElement
                    ), $this->getSession());
                }
            }
        }
    }

    /**
     * Find a price field
     *
     * @param string $name
     * @param string $currency
     *
     * @throws ElementNotFoundException
     *
     * @return NodeElement
     */
    protected function findPriceField($name, $currency)
    {
        $label = $this->find('css', sprintf('label:contains("%s")', $name));

        if (!$label) {
            throw new ElementNotFoundException($this->getSession(), 'form label', 'value', $name);
        }

        $labels = $label->getParent()->findAll('css', '.currency-label');

        $fieldNum = null;
        foreach ($labels as $index => $element) {
            if ($element->getText() === $currency) {
                $fieldNum = $index;
                break;
            }
        }

        if ($fieldNum === null) {
            throw new ElementNotFoundException($this->getSession(), 'price field', 'id|name|label|value', $currency);
        }

        $fields = $label->getParent()->findAll('css', 'input[type="text"]');

        if (!isset($fields[$fieldNum])) {
            throw new ElementNotFoundException($this->getSession(), 'form label ', 'value', $name);
        }

        return $fields[$fieldNum];
    }

    /**
     * Extracts and return the label NodeElement, identified by $field content and $element
     *
     * @param string           $field
     * @param ElementInterface $element
     *
     * @return \Behat\Mink\Element\NodeElement
     */
    protected function extractLabelElement($field, ElementInterface $element = null)
    {
        $subLabelContent = null;
        $channel         = null;
        $labelContent    = $field;

        if (false !== strpbrk($field, '€$')) {
            if (false !== strpos($field, ' ')) {
                list($subLabelContent, $labelContent) = explode(' ', $field);
            }
        }

        if ($element) {
            $label = $this->spin(function () use ($element, $labelContent) {
                return $element->find('css', sprintf('label:contains("%s")', $labelContent));
            }, sprintf('Cannot find "%s" label', $labelContent));
        } else {
            $labeParts = explode(' ', $labelContent);
            $channel   = in_array(reset($labeParts), ['mobile', 'ecommerce', 'print', 'tablet']) ?
                reset($labeParts) :
                null;

            if (null !== $channel) {
                $labelContent = substr($labelContent, strlen($channel . ' '));
            }

            $label = $this->spin(function () use ($labelContent) {
                return $this->find('css', sprintf('label:contains("%s")', $labelContent));
            }, sprintf('Cannot find "%s" label', $labelContent));
        }

        if (!$label) {
            $label = new \stdClass();
        }

        $label->channel         = $channel;
        $label->labelContent    = $labelContent;
        $label->subLabelContent = $subLabelContent;

        return $label;
    }

    /**
     * Guesses the type of field identified by $label and returns it.
     *
     * Possible identified fields are :
     * [multiSelect2, simpleSelect2, datepicker, select, wysiwyg, text, compound]
     *
     * @param $label
     *
     * @return string
     */
    protected function getFieldType($label)
    {
        if (null === $label || !($label instanceof NodeElement)) {
            return null;
        }

        if (null !== $label->subLabelContent) {
            return 'compound';
        }

        if ($label->hasAttribute('for')) {
            $for = $label->getAttribute('for');

            if (0 === strpos($for, 's2id_')) {
                if ($this->getClosest($label, 'AknFieldContainer')->find('css', '.select2-container-multi')) {
                    return 'multiSelect2';
                } elseif ($this->getClosest($label, 'AknFieldContainer')->find('css', '.select2-container')) {
                    return 'simpleSelect2';
                }

                return 'select';
            }

            if (null !== $this->find('css', sprintf('#date_selector_%s', $for))) {
                return 'datepicker';
            }

            $field = $this->find('css', sprintf('#%s', $for));

            if (null !== $field && $field->getTagName() === 'select') {
                return 'select';
            }

            if (null !== $field && false !== strpos($field->getAttribute('class'), 'wysiwyg')) {
                return 'wysiwyg';
            }
        }

        return 'text';
    }

    /**
     * Fills a multivalues Select2 field with $value, identified by its $label.
     * It deletes existing selected values from field if not present in $value.
     *
     * $value can be a string of multiple values. Each value must be separated with comma, eg :
     * 'Hot, Dry, Fresh'
     *
     * @param NodeElement $label
     * @param string      $value
     *
     * @throws \InvalidArgumentException
     */
    protected function fillMultiSelect2Field(NodeElement $label, $value)
    {
        $field = $this->decorate(
            $this->getClosest($label, 'AknFieldContainer')->find('css', '.select2-container'),
            ['Pim\Behat\Decorator\Field\Select2Decorator']
        );

        $field->setValue($value);
    }

    /**
     * Fills a simple (unique value) select2 field with $value, identified by its $label.
     *
     * @param NodeElement $label
     * @param string      $value
     *
     * @throws \InvalidArgumentException
     */
    protected function fillSelect2Field(NodeElement $label, $value)
    {
        if (trim($value)) {
            $container = $this->getClosest($label, 'AknFieldContainer');
            $link = $container->find('css', '.select2-choice');

            if (null !== $link) {
                $link->click();
                $this->getSession()->wait($this->getTimeout(), '!$.active');

                $field = $this->spin(function () use ($value) {
                    return $this->find('css', sprintf('#select2-drop li:contains("%s")', $value));
                }, sprintf('Cannot find "%s" select2 element', $value));

                $field->click();

                return;
            }

            throw new \InvalidArgumentException(
                sprintf('Could not find select2 widget inside %s', $container->getHtml())
            );
        }
    }

    /**
     * Fills a select element with $value, identified by its $label.
     *
     * @param NodeElement $label
     * @param string      $value
     */
    protected function fillSelectField(NodeElement $label, $value)
    {
        $field = $this->getClosest($label, 'AknFieldContainer')->find('css', 'select');

        $field->selectOption($value);
    }

    /**
     * Fills a Wysiwyg editor element with $value, identified by its $label.
     *
     * @param NodeElement $label
     * @param string      $value
     */
    protected function fillWysiwygField(NodeElement $label, $value)
    {
        $for = $label->getAttribute('for');

        $this->getSession()->executeScript(
            sprintf("$('#%s').val('%s');", $for, $value)
        );
    }

    /**
     * Fills a date field element with $value, identified by its $label.
     *
     * @param NodeElement $label
     * @param string      $value
     */
    protected function fillDateField(NodeElement $label, $value)
    {
        $for = $label->getAttribute('for');

        $this->getSession()->executeScript(
            sprintf("$('#%s').val('%s').trigger('change');", $for, $value)
        );
    }

    /**
     * Fills a text field element with $value, identified by its $label.
     *
     * @param NodeElement $label
     * @param string      $value
     */
    protected function fillTextField(NodeElement $label, $value)
    {
        if (!$label->getAttribute('for') && null !== $label->channel) {
            $label = $label->getParent()->find('css', sprintf('[data-scope="%s"] label', $label->channel));
        }

        $for   = $label->getAttribute('for');
        $field = $this->spin(function () use ($for) {
            return $this->find('css', sprintf('#%s', $for));
        }, sprintf('Cannot find element field with id %s', $for));

        $field->setValue($value);

        $this->getSession()->executeScript(
            sprintf("$('#%s').trigger('change');", $for)
        );
    }

    /**
     * Fills a compound field with $value, by passing the $label in reversed order separated
     * with whitespaces.
     *
     * Example:
     * We have a field "$" embedded inside a "Price" field
     * We can call fillField('$ Price', 26) to set the "$" value of parent field "Price"
     *
     * @param NodeElement $label
     * @param string      $value
     *
     * @throws ElementNotFoundException
     */
    protected function fillCompoundField(NodeElement $label, $value)
    {
        if (!$label->subLabelContent) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The "%s" field is compound but the sub label was not provided',
                    $label->labelContent
                )
            );
        }

        $this->expand($label);

        $field = $this->findPriceField($label->labelContent, $label->subLabelContent);
        $field->setValue($value);
    }

    /**
     * Returns the tabs of the current page, if any.
     *
     * @return NodeElement
     */
    protected function getPageTabs()
    {
        return $this->spin(function () {
            $tabs = $this->find('css', $this->elements['Tabs']['css']);
            if (null === $tabs) {
                $tabs = $this->find('css', $this->elements['Oro tabs']['css']);
            }
            if (null === $tabs) {
                $tabs = $this->find('css', $this->elements['Form tabs']['css']);
            }

            return $tabs;
        }, 'Cannot find any tabs in this page');
    }
}

<?php

class Driv_Richsnippets_Model_Attributes
{

    public function toOptionArray()
    {
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addVisibleFilter()
            ->addFieldToFilter('frontend_input', array('text', 'select', 'textarea'));

        $attributeArray[] = array(
            'label' => '-',
            'value' => ''
        );

        foreach ($attributes as $attribute) {
            $attributeArray[] = array(
                'label' => $attribute->getData('frontend_label'),
                'value' => $attribute->getData('attribute_code')
            );
        }

        return $attributeArray;
    }
}
<?php

/**
 * @author Matthias Kerstner <matthias@both-interact.com>
 * @version 1.6.1
 * @copyright (c) 2016, Both Interact GmbH
 */
class BothInteract_ConfigurableProductVariantsImageAssignment_Model_System_Config_Source_View {

    /** @var string base image type */
    public static $VALUE_IMAGE_TYPE_BASE_IMAGE = 0;

    /** @var string small image type */
    public static $VALUE_IMAGE_TYPE_SMALL_IMAGE = 1;

    /** @var string thumbnail image type */
    public static $VALUE_IMAGE_TYPE_THUMBNAIL = 2;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        return array(
            array('value' => self::$VALUE_IMAGE_TYPE_BASE_IMAGE, 'label' => Mage::helper('adminhtml')->__('Base image')),
            array('value' => self::$VALUE_IMAGE_TYPE_SMALL_IMAGE, 'label' => Mage::helper('adminhtml')->__('Small image')),
            array('value' => self::$VALUE_IMAGE_TYPE_THUMBNAIL, 'label' => Mage::helper('adminhtml')->__('Thumbnail')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray() {
        return array(
            self::$VALUE_IMAGE_TYPE_BASE_IMAGE => Mage::helper('adminhtml')->__('Base image'),
            self::$VALUE_IMAGE_TYPE_SMALL_IMAGE => Mage::helper('adminhtml')->__('Small image'),
            self::$VALUE_IMAGE_TYPE_THUMBNAIL => Mage::helper('adminhtml')->__('Thumbnail')
        );
    }

}

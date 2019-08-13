<?php

require_once 'abstract.php';

/**
 * @author Matthias Kerstner <matthias@both-interact.com>
 * @version 1.6.0
 * @copyright (c) 2016, Both Interact GmbH
 */
class Mage_Shell_Set_Required_Images_For_Configurable_Product_Variants extends Mage_Shell_Abstract {

    const PAGE_SIZE = 100;

    /**
     * Parse string with id's and return array
     *
     * @param string $string
     * @return array
     */
    protected function _parseString($string) {
        $ids = array();
        if (!empty($string)) {
            $ids = explode(',', $string);
            $ids = array_map('trim', $ids);
        }
        return $ids;
    }

    /**
     * Run script based on CL options specified.
     */
    public function run() {
        if ($this->getArg('products')) {

            // switch to admin event area
            Mage::app()->addEventArea('admin');

            // product model observer to be called on products
            $productModelObserver = new BothInteract_ConfigurableProductVariantsImageAssignment_Model_Observer();

            //allowed attribute types
            $types = array('varchar', 'text', 'decimal', 'datetime', 'int');

            //attribute sets array
            $attributeSets = array();

            //user defined attribute ids
            $entityType = Mage::getModel('eav/entity_type')
                    ->loadByCode('catalog_product');

            //connection for raw queries
            $connection = Mage::getSingleton('core/resource')
                    ->getConnection('core_write');

            $attributeCollection = $entityType
                    ->getAttributeCollection()
                    ->addFilter('is_user_defined', '1')
                    ->getItems();
            $attrIds = array();
            foreach ($attributeCollection as $attribute) {
                $attrIds[] = $attribute->getId();
            }
            $userDefined = implode(',', $attrIds);

            //product collection based on attribute filters
            $collection = Mage::getModel('catalog/product')->getCollection();
            $entityTable = $collection
                    ->getTable(Mage::getModel('eav/entity_type')
                    ->loadByCode('catalog_product')
                    ->getEntityTable());

            // load only configurable products
            $collection->addAttributeToFilter('type_id', array('eq' => 'configurable'));

            // load product IDs specified only
            if ($this->getArg('products') != 'all') {
                if ($ids = $this->_parseString($this->getArg('products'))) {
                    $collection->addAttributeToFilter('entity_id', array('in' => $ids));
                }
            }
            $collection->setPageSize(self::PAGE_SIZE);

            $pages = $collection->getLastPageNumber();
            $currentPage = 1;

            //light product collection iterating
            while ($currentPage <= $pages) {

                echo 'Processing page ' . $currentPage . ' of ' . $pages
                . '...' . PHP_EOL;

                $collection->setCurPage($currentPage);
                $collection->load();

                foreach ($collection->getItems() as $item) {

                    // load product to manipulate
                    $product = Mage::getModel('catalog/product')
                            ->load($item->getId());

                    // manually call our observer to update child products
                    $productModelObserver->processProduct($product);
                }

                $currentPage++;
                $collection->clear();
            }

            echo 'Done!' . PHP_EOL;
        } else {
            echo $this->usageHelp();
        }
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp() {
        return <<<USAGE
 
Usage:  php -f set_required_images_for_configurable_product_variants -- [options]
 
    --products all              Fix all products
    --products <product_ids>    Fix products by ids
    help                        This help
 
    <product_ids>               Comma separated id's of products
 
USAGE;
    }

}

$shell = new Mage_Shell_Set_Required_Images_For_Configurable_Product_Variants();
$shell->run();

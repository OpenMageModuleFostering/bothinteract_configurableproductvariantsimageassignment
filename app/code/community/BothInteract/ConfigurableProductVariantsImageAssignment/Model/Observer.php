<?php

/**
 * Handles automatic image assignment for child products of configurable 
 * products (i.e. associated products) once a configurable product is being 
 * save()'d.
 * 
 * It is possible to set the list of required image types for child products:
 * - @see self::$IMAGE_TYPE_BASE_IMAGE
 * - @see self::$IMAGE_TYPE_SMALL_IMAGE
 * - @see self::$IMAGE_TYPE_THUMBNAIL
 * 
 * To set the list required image types please refer to 
 * @see $requiredChildProductImageTypes.
 * 
 * All required image types set will be checked for each child product and will
 * be set based on a copy of the parent's base image.
 * 
 * This class writes log messages to a custom log file specified by 
 * @see self::$LOG_FILE.
 *  
 * @author Matthias Kerstner <matthias@both-interact.com>
 * @version 1.6.1
 * @copyright (c) 2016, Both Interact GmbH
 */
class BothInteract_ConfigurableProductVariantsImageAssignment_Model_Observer {

    /** @var string module namespace */
    private static $_MODULE_NAMESPACE = 'bothinteract_configurableproductvariantsimageassignment';

    /** @var string placeholder text if no image is set */
    private static $IMAGE_NO_SELECTION = 'no_selection';

    /** @var string base image type used by Magento */
    public static $IMAGE_TYPE_BASE_IMAGE = 'image';

    /** @var string small_image type used by Magento */
    public static $IMAGE_TYPE_SMALL_IMAGE = 'small_image';

    /** @var string thumbnail image type used by Magento */
    public static $IMAGE_TYPE_THUMBNAIL = 'thumbnail';

    /**
     * Logs $msg to logfile specified in configuration.
     * @param string $msg
     */
    private function logToFile($msg) {
        Mage::log($msg, null, Mage::getStoreConfig(
                        self::$_MODULE_NAMESPACE
                        . '/general/log_file', Mage::app()->getStore()));
    }

    /**
     * Converts source view value to internal image type.
     * @return array
     */
    private function valueToImageType($value) {
        $arr = array(
            BothInteract_ConfigurableProductVariantsImageAssignment_Model_System_Config_Source_View::$VALUE_IMAGE_TYPE_BASE_IMAGE =>
            self::$IMAGE_TYPE_BASE_IMAGE,
            BothInteract_ConfigurableProductVariantsImageAssignment_Model_System_Config_Source_View::$VALUE_IMAGE_TYPE_SMALL_IMAGE =>
            self::$IMAGE_TYPE_SMALL_IMAGE,
            BothInteract_ConfigurableProductVariantsImageAssignment_Model_System_Config_Source_View::$VALUE_IMAGE_TYPE_THUMBNAIL =>
            self::$IMAGE_TYPE_THUMBNAIL
        );

        return isset($arr[$value]) ? $arr[$value] : null;
    }

    /**
     * Returns absolute path to base image set on $product. If no image of type
     * $imageType is currently set will return NULL.
     * 
     * @param Mage_Catalog_Model_Product $product
     * @param string $imageType image type, i.e. image (base image), 
     *        small_image, thumbnail
     * @param boolean $isHttps
     * @return string|NULL
     */
    private function getImagePath(Mage_Catalog_Model_Product $product, $imageType, $isHttps = false) {

        if ($product->getImage() == '' ||
                $product->getImage() == self::$IMAGE_NO_SELECTION) {
            return null;
        }

        $image = null;

        if ($imageType === self::$IMAGE_TYPE_BASE_IMAGE) {
            $image = $product->getImage();
        } else if ($imageType === self::$IMAGE_TYPE_SMALL_IMAGE) {
            $image = $product->getSmallImage();
        } else if ($imageType === self::$IMAGE_TYPE_THUMBNAIL) {
            $image = $product->getThumbnail();
        } else {
            $this->logToFile('Invalid image type specified: ' . $imageType);
            return null;
        }

        $imageUrl = Mage::getModel('catalog/product_media_config')
                ->getMediaUrl($image, array('_secure' => $isHttps));
        $baseDir = Mage::getBaseDir();
        $mediaUrlwithoutIndex = str_replace('index.php/', '', Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB));
        $mediaUrlwithoutBase = str_replace($mediaUrlwithoutIndex, '', $imageUrl);

        return ($baseDir . DIRECTORY_SEPARATOR . $mediaUrlwithoutBase);
    }

    /**
     * Returns absolute path the product's based image if set, otherwise NULL.
     * 
     * @param Mage_Catalog_Model_Product $product
     * @param boolean $isHttps
     * @return string|NULL
     */
    private function getProductBaseImagePath(Mage_Catalog_Model_Product $product, $isHttps = false) {

        $this->logToFile('Product Base image: ' . $product->getImage());
        $this->logToFile('Product Small image: ' . $product->getSmallImage());
        $this->logToFile('Product Thumbnail: ' . $product->getThumbnail());

        if ($product->getImage() == '' ||
                $product->getImage() == self::$IMAGE_NO_SELECTION) {
            $this->logToFile('WARNING: product ' . $product->getId()
                    . ' does not have a base image set.'
                    . ' Make sure that parent product has a base image set and '
                    . 'check that entry "No image" is not set as base image in '
                    . 'admin backend.');
            return null;
        }

        $productBaseImagePath = $this->getImagePath($product, self::$IMAGE_TYPE_BASE_IMAGE, $isHttps);

        $this->logToFile('Using parent product image: ' . $productBaseImagePath);

        if (!is_file($productBaseImagePath)) {
            $this->logToFile('WARNING: parent product ' . $product->getId()
                    . ' base image not readable ' . $productBaseImagePath);
            return null;
        }

        return $productBaseImagePath;
    }

    /**
     * Sets required image types specified by $requiredChildProductImageTypes 
     * child product of parent product based on parent product's base image 
     * specified by $productBaseImagePath.
     * 
     * @param Mage_Catalog_Model_Product $parentProduct
     * @param Mage_Catalog_Model_Product $childProduct
     * @param array $requiredChildProductImageTypes
     */
    private function setChildProductRequiredImageTypesFromParent(
    Mage_Catalog_Model_Product $parentProduct, Mage_Catalog_Model_Product $childProduct, $requiredChildProductImageTypes) {

        $this->logToFile('---------------------------------------');
        $this->logToFile('Checking '
                . mb_strtoupper($childProduct->getTypeId())
                . ' product ' . $childProduct->getId());

        $isHttps = Mage::getStoreConfig(self::$_MODULE_NAMESPACE
                        . '/general/is_https_media_urls');

        $this->logToFile('Using SSL for image URLs: ' . ($isHttps ? 'YES' : 'NO'));

        $parentProductBaseImagePath = $this->getProductBaseImagePath($parentProduct, $isHttps);

        if (!$parentProductBaseImagePath) {
            $this->logToFile('WARNING: Failed to determine parent product '
                    . $parentProduct->getId() . ' base image - QUITTING!');
            return;
        }

        $this->logToFile('Using parent product ' . $parentProduct->getId()
                . ' base image:');
        $this->logToFile($parentProductBaseImagePath);

        $this->logToFile('Child Base image: ' . $childProduct->getImage());
        $this->logToFile('Child Small image: ' . $childProduct->getSmallImage());
        $this->logToFile('Child Thumbnail: ' . $childProduct->getThumbnail());

        $this->logToFile('Required image type(s) '
                . 'for child product ' . $childProduct->getId()
                . ': [' . implode(',', $requiredChildProductImageTypes) . ']');

        foreach ($requiredChildProductImageTypes as $k => $imageType) {
            $image = null;

            if ($imageType === self::$IMAGE_TYPE_BASE_IMAGE) {
                $image = $childProduct->getImage();
            } else if ($imageType === self::$IMAGE_TYPE_SMALL_IMAGE) {
                $image = $childProduct->getSmallImage();
            } else if ($imageType === self::$IMAGE_TYPE_THUMBNAIL) {
                $image = $childProduct->getThumbnail();
            } else {
                $this->logToFile('Invalid image type specified: '
                        . $imageType);
                continue;
            }

            if ($image == '' || $image == self::$IMAGE_NO_SELECTION) {
                $this->logToFile(mb_strtoupper($imageType)
                        . ' not set - using parent base image '
                        . $parentProduct->getImage()
                        . ' as ' . $imageType . ' for child product '
                        . $childProduct->getId());
            } else {
                $this->logToFile(mb_strtoupper($imageType)
                        . ' already set for child product '
                        . $childProduct->getId());
                unset($requiredChildProductImageTypes[$k]);
            }
        }

        if (!count($requiredChildProductImageTypes)) {
            $this->logToFile('No required image(s) to be set for child product '
                    . $childProduct->getId());
        } else {
            $this->logToFile('Setting required image(s) ['
                    . implode(',', $requiredChildProductImageTypes)
                    . '] for child product ' . $childProduct->getId());

            /**
             * Set required image types for child product based on base 
             * image from parent set through $requiredChildProductImageTypes.
             * 
             * Will automatically create a copy of image file(s) in media 
             * folder.
             */
            $childProduct->addImageToMediaGallery($parentProductBaseImagePath, $requiredChildProductImageTypes, false, false);

            if (Mage::getStoreConfig(self::$_MODULE_NAMESPACE
                            . '/general/is_simulation')) {
                $this->logToFile('************************************');
                $this->logToFile('SIMULATION: Not saving child product '
                        . $childProduct->getId());
                $this->logToFile('************************************');
            } else {
                $this->logToFile('Saving child product ' . $childProduct->getId());
                $childProduct->save();
            }

            $this->logToFile('Successfully set required image type(s) ['
                    . implode(',', $requiredChildProductImageTypes)
                    . '] for child product ' . $childProduct->getId());
        }
    }

    /**
     * Handles simple products.
     * @param Mage_Catalog_Model_Product $product
     * @var array $requiredChildProductImageTypes
     */
    private function handleSimpleProduct(Mage_Catalog_Model_Product $product, $requiredChildProductImageTypes) {
        $this->logToFile('Handling '
                . mb_strtoupper($product->getTypeId())
                . ' product ' . $product->getId());

        $this->logToFile('Checking if '
                . Mage_Catalog_Model_Product_Type::TYPE_GROUPED
                . ' product...');

        $parentIds = Mage::getModel('catalog/product_type_grouped')
                ->getParentIdsByChild($product->getId());

        if (!$parentIds) {
            $this->logToFile('Checking if '
                    . Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE
                    . ' product...');

            $parentIds = Mage::getModel('catalog/product_type_configurable')
                    ->getParentIdsByChild($product->getId());
        }

        $this->logToFile('Possible parent product(s): '
                . count($parentIds));

        if (!count($parentIds)) {
            $this->logToFile('No parent product(s) found for product '
                    . $product->getId());
        }

        /**
         * avoid recursion here if for instance base image was just removed
         * from child product. Thus, do not call 
         * setChildProductRequiredImageTypesFromParent() here!
         */
    }

    /**
     * Handles configurable product and determine associated (child) products
     * to process.
     * 
     * @param Mage_Catalog_Model_Product $product
     * @param array $requiredChildProductImageTypes
     */
    private function handleConfigurableProduct(Mage_Catalog_Model_Product $product, $requiredChildProductImageTypes) {

        $this->logToFile('Handling '
                . mb_strtoupper($product->getTypeId())
                . ' product ' . $product->getId());

        $childProducts = Mage::getModel('catalog/product_type_configurable')
                ->getUsedProducts(null, $product);

        if (!count($childProducts)) {
            $this->logToFile('No associated (child) product(s) to be processed for product '
                    . $product->getId() . ' - DONE!');
            return;
        }

        $this->logToFile('Found ' . count($childProducts)
                . ' associated (child) product(s)');

        foreach ($childProducts as $childProduct) {
            $this->setChildProductRequiredImageTypesFromParent(
                    $product, $childProduct, $requiredChildProductImageTypes);
        }

        $this->logToFile('Done handling associated products for '
                . mb_strtoupper($product->getTypeId())
                . ' product ' . $product->getId() . '!');
    }

    /**
     * Handles product save_after events by checking product type and settings
     * required image types.
     * 
     * @param Varien_Event_Observer $observer
     */
    public function catalog_product_save_after(Varien_Event_Observer $observer) {
        try {
            if (!Mage::getStoreConfig(self::$_MODULE_NAMESPACE
                            . '/general/is_active', Mage::app()->getStore())) {
                $this->logToFile('Extension INACTIVE - Quitting...');
                return;
            }
            $this->processProduct($observer->getEvent()->getProduct());
        } catch (Exception $e) {
            $this->logToFile('ERROR: ' . $e->getMessage());
        }
    }

    /**
     * Product specified can be of any type, e.g. configurable, grouped, 
     * simple, etc.
     * 
     * Make sure that this product is loaded here once for the remaining
     * process.
     * 
     * @param Mage_Catalog_Model_Product $product Can be of any valid product 
     *        type, e.g. configurable, grouped, simple, ...
     */
    public function processProduct(Mage_Catalog_Model_Product $product) {
        try {

            /**
             * first make sure that $product is fully loaded and keep reference 
             * to original product from event        
             */
            $_product = $product; // copy
            $product = Mage::getModel('catalog/product')->load($product->getId());

            $this->logToFile('==================================================');
            $this->logToFile('Checking product ' . $product->getId()
                    . ' of type ' . mb_strtoupper($product->getTypeId())
                    . '...');

            /**
             * @var array required image types to be set for each child product 
             * based on parent's base image.
             * Possible values are 
             * - IMAGE_TYPE_BASE_IMAGE, 
             * - IMAGE_TYPE_SMALL_IMAGE  
             * - IMAGE_TYPE_THUMBNAIL.
             * 
             * Make sure that this list *always* includes at least 
             * IMAGE_TYPE_BASE_IMAGE for e.g. Amazon Listing to work since it 
             * requires a base image.
             * 
             * Options are taken from system config source view.
             */
            $requiredChildProductImageTypes = array();
            $requiredChildProductImageTypeValues = explode(',', Mage::getStoreConfig(self::$_MODULE_NAMESPACE
                            . '/general/required_image_types', Mage::app()->getStore()));

            foreach ($requiredChildProductImageTypeValues as $requiredChildProductImageTypeValue) {
                $val = $this->valueToImageType($requiredChildProductImageTypeValue);

                if ($val) {
                    $requiredChildProductImageTypes[] = $val;
                }
            }

            if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
                $this->handleSimpleProduct($product, $requiredChildProductImageTypes);
            } else if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                $this->handleConfigurableProduct($product, $requiredChildProductImageTypes);
            } else {
                $this->logToFile('Ignoring '
                        . mb_strtoupper($product->getTypeId())
                        . ' product');
            }

            $this->logToFile('Done processing ' . $product->getId() . '!');
        } catch (Exception $e) {
            $this->logToFile('ERROR: ' . $e->getMessage());
        }
    }

}

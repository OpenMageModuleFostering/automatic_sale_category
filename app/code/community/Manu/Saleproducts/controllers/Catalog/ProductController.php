<?php
/**
 * Product list
 *
 * @category   Manu
 * @package    Manu_Saleproducts
 * @author    Manu Jose K
 */


include_once("Mage/Adminhtml/controllers/Catalog/ProductController.php");

class Manu_Saleproducts_Catalog_ProductController extends Mage_Adminhtml_Catalog_ProductController {

    protected function _initProductSave() {
        $product = $this->_initProduct();
        $productData = $this->getRequest()->getPost('product');
        if ($productData) {
            if (!isset($productData['stock_data']['use_config_manage_stock'])) {
                $productData['stock_data']['use_config_manage_stock'] = 0;
            }
            if (isset($productData['stock_data']['qty']) && (float) $productData['stock_data']['qty'] > self::MAX_QTY_VALUE) {
                $productData['stock_data']['qty'] = self::MAX_QTY_VALUE;
            }
        }

        /**
         * Websites
         */
        if (!isset($productData['website_ids'])) {
            $productData['website_ids'] = array();
        }

        $wasLockedMedia = false;
        if ($product->isLockedAttribute('media')) {
            $product->unlockAttribute('media');
            $wasLockedMedia = true;
        }

        $product->addData($productData);

        if ($wasLockedMedia) {
            $product->lockAttribute('media');
        }

        if (Mage::app()->isSingleStoreMode()) {
            $product->setWebsiteIds(array(Mage::app()->getStore(true)->getWebsite()->getId()));
        }

        /**
         * Create Permanent Redirect for old URL key
         */
        if ($product->getId() && isset($productData['url_key_create_redirect'])) {
            // && $product->getOrigData('url_key') != $product->getData('url_key')
            $product->setData('save_rewrites_history', (bool) $productData['url_key_create_redirect']);
        }

        /**
         * Check "Use Default Value" checkboxes values
         */
        if ($useDefaults = $this->getRequest()->getPost('use_default')) {
            foreach ($useDefaults as $attributeCode) {
                $product->setData($attributeCode, false);
            }
        }

        /**
         * Init product links data (related, upsell, crosssel)
         */
        $links = $this->getRequest()->getPost('links');
        if (isset($links['related']) && !$product->getRelatedReadonly()) {
            $product->setRelatedLinkData(Mage::helper('adminhtml/js')->decodeGridSerializedInput($links['related']));
        }
        if (isset($links['upsell']) && !$product->getUpsellReadonly()) {
            $product->setUpSellLinkData(Mage::helper('adminhtml/js')->decodeGridSerializedInput($links['upsell']));
        }
        if (isset($links['crosssell']) && !$product->getCrosssellReadonly()) {
            $product->setCrossSellLinkData(Mage::helper('adminhtml/js')
                            ->decodeGridSerializedInput($links['crosssell']));
        }
        if (isset($links['grouped']) && !$product->getGroupedReadonly()) {
            $product->setGroupedLinkData(Mage::helper('adminhtml/js')->decodeGridSerializedInput($links['grouped']));
        }

        /**
         * Initialize product categories
         */
        /**
         * Overriding core functionality-atomatically adds products to sale category
         */
        $datasale = $this->getRequest()->getPost();
        $categoryIds = $this->getRequest()->getPost('category_ids');
        /**
         * if categories are null in posted data, then retriving from model
         */
        if (null == $categoryIds) {
            $productId = $this->getRequest()->getParam('id');
            $productModel = Mage::getModel('catalog/product')->load($productId);
            $cats_ids = $productModel->getCategoryIds($productId);
            $categoryIds = implode($cats_ids, ",");
        }
        $sales=  array();
        $i = 0;
        $categories = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToFilter('name', Mage::getStoreConfig('sale_category/general/offer_category'))
                ->addAttributeToSelect('id')
                ->load();
        foreach ($categories as $productcategory) {
            $sales[$i] = $productcategory->getId();
            $i++;
        }
        $todayDate = date('d/m/Y');
        $data = $this->getRequest()->getPost();   
        $websiteId = $datasale['product']['website_ids'];
        foreach ($websiteId as $siteId) {
            if ($datasale['product']['special_price']) {
                    
                    if($data['product']['special_from_date']=="")
                            $data['product']['special_from_date']=$todayDate;                    
                    if(($data['product']['special_from_date'] <= $todayDate) && ($data['product']['special_from_date']<=$data['product']['special_to_date'] || $data['product']['special_to_date']=="") && ($data['product']['special_to_date']>=$todayDate || $data['product']['special_to_date']=="") )
                            $categoryIds = $categoryIds . "," . $sales[$siteId - 1]; 
                   else
                          $categoryIds = str_replace($sales[$siteId - 1], "", $categoryIds); 
               
                
            } else {
                $categoryIds = str_replace($sales[$siteId - 1], "", $categoryIds);
            }
        }
        $websiteCount = Mage::app()->getWebsites();
        for ($i = 1; $i <= count($websiteCount); $i++) {
            if (!in_array($i, $websiteId)) {
                $categoryIds = str_replace($sales[$i - 1], "", $categoryIds);
            }
        }
        if (null !== $categoryIds) {
            if (empty($categoryIds)) {
                $categoryIds = array();
            }
            $product->setCategoryIds($categoryIds);
        }

        /**
         * Initialize data for configurable product
         */
        if (($data = $this->getRequest()->getPost('configurable_products_data'))
                && !$product->getConfigurableReadonly()
        ) {
            $product->setConfigurableProductsData(Mage::helper('core')->jsonDecode($data));
        }
        if (($data = $this->getRequest()->getPost('configurable_attributes_data'))
                && !$product->getConfigurableReadonly()
        ) {
            $product->setConfigurableAttributesData(Mage::helper('core')->jsonDecode($data));
        }

        $product->setCanSaveConfigurableAttributes(
                (bool) $this->getRequest()->getPost('affect_configurable_product_attributes')
                && !$product->getConfigurableReadonly()
        );

        /**
         * Initialize product options
         */
        if (isset($productData['options']) && !$product->getOptionsReadonly()) {
            $product->setProductOptions($productData['options']);
        }

        $product->setCanSaveCustomOptions(
                (bool) $this->getRequest()->getPost('affect_product_custom_options')
                && !$product->getOptionsReadonly()
        );

        Mage::dispatchEvent(
                'catalog_product_prepare_save', array('product' => $product, 'request' => $this->getRequest())
        );

        return $product;
    }

    /**
     * Save product action
     */
    public function saveAction() {

        /**
         * Overriding core functionality-atomatically adds products to sale category
         */            
        $sales= array ();
        $i = 0;
        $categories = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToFilter('name', Mage::getStoreConfig('sale_category/general/offer_category'))
                ->addAttributeToSelect('id')
                ->load();
        foreach ($categories as $product) { //loop for getting products
            $sales[$i] = $product->getId();
            $i++;
        }
        $storeId = $this->getRequest()->getParam('store');
        $redirectBack = $this->getRequest()->getParam('back', false);
        $productId = $this->getRequest()->getParam('id');
        $isEdit = (int) ($this->getRequest()->getParam('id') != null);
        $categoryId_Sale = ",";
        $categorySale = ",";
        $data = $this->getRequest()->getPost();
        $websiteId = $data['product']['website_ids'];
        foreach ($websiteId as $siteId) {
            if ($data['product']['special_price']) {
                $categorySale = $categorySale . "," . $sales[$siteId - 1] . "," . $sales[$siteId - 1];
            } else {
                $categoryId_Sale = $categoryId_Sale . "," . $sales[$siteId - 1];
            }
        }

//        $data['category_ids'] = $data['category_ids'] . $categorySale . $categoryId_Sale;
        if ($data) {
            if (!isset($data['product']['stock_data']['use_config_manage_stock'])) {
                $data['product']['stock_data']['use_config_manage_stock'] = 0;
            }
            $product = $this->_initProductSave();

            try {
                $product->save();
                $productId = $product->getId();

                /**
                 * Do copying data to stores
                 */
                if (isset($data['copy_to_stores'])) {
                    foreach ($data['copy_to_stores'] as $storeTo => $storeFrom) {
                        $newProduct = Mage::getModel('catalog/product')
                                ->setStoreId($storeFrom)
                                ->load($productId)
                                ->setStoreId($storeTo)
                                ->save();
                    }
                }

                Mage::getModel('catalogrule/rule')->applyAllRulesToProduct($productId);

                $this->_getSession()->addSuccess($this->__('The product has been saved.'));
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage())
                        ->setProductData($data);
                $redirectBack = true;
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_getSession()->addError($e->getMessage());
                $redirectBack = true;
            }
        }

        if ($redirectBack) {
            $this->_redirect('*/*/edit', array(
                'id' => $productId,
                '_current' => true
            ));
        } elseif ($this->getRequest()->getParam('popup')) {
            $this->_redirect('*/*/created', array(
                '_current' => true,
                'id' => $productId,
                'edit' => $isEdit
            ));
        } else {
            $this->_redirect('*/*/', array('store' => $storeId));
        }
    }

}

?>

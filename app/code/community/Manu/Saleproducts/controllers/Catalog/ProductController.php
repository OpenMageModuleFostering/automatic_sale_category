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

     protected function _initProductSave()
    {
        $product     = $this->_initProduct();
        $productData = $this->getRequest()->getPost('product');
        if ($productData) {
            $this->_filterStockData($productData['stock_data']);
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
        if ($product->getId() && isset($productData['url_key_create_redirect']))
        // && $product->getOrigData('url_key') != $product->getData('url_key')
        {
            $product->setData('save_rewrites_history', (bool)$productData['url_key_create_redirect']);
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
        $categoryIds = $this->getRequest()->getPost('category_ids');
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
            (bool)$this->getRequest()->getPost('affect_product_custom_options')
            && !$product->getOptionsReadonly()
        );

        Mage::dispatchEvent(
            'catalog_product_prepare_save',
            array('product' => $product, 'request' => $this->getRequest())
        );

        return $product;
    }

    /**
     * Save product action
     */
    public function saveAction()
    {        
        $storeId        = $this->getRequest()->getParam('store');
        $redirectBack   = $this->getRequest()->getParam('back', false);
        $productId      = $this->getRequest()->getParam('id');
        $isEdit         = (int)($this->getRequest()->getParam('id') != null);

        $data = $this->getRequest()->getPost();
        if ($data) {
            $this->_filterStockData($data['product']['stock_data']);

            $product = $this->_initProductSave();

            try {
                $product->save();
                $productId = $product->getId();

                /**
                 * Do copying data to stores
                 */
                if (isset($data['copy_to_stores'])) {
                    foreach ($data['copy_to_stores'] as $storeTo=>$storeFrom) {
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
        
        //calls a custome function
        $this->autoSaleCategory($product);
        
        
        if ($redirectBack) {
            $this->_redirect('*/*/edit', array(
                'id'    => $productId,
                '_current'=>true
            ));
        } elseif($this->getRequest()->getParam('popup')) {
            $this->_redirect('*/*/created', array(
                '_current'   => true,
                'id'         => $productId,
                'edit'       => $isEdit
            ));
        } else {
            $this->_redirect('*/*/', array('store'=>$storeId));
        }
    }
    
    public function autoSaleCategory($product)
    {       
       $productId = $product->getId();
       $storeId=0;//Mage::app()->getRequest()->getParam('store');
       $attribute_code_tshirt_from='special_from_date';
       $attribute_code_tshirt_to='special_to_date';
       $attribute_code_after_from='afterhours_from_date';
       $attribute_code_after_to='afterhours_to_date';
       $attribute_code_gallery_from='gallery_from_date';
       $attribute_code_gallery_to='gallery_to_date';
       $current_date=date("Y-m-d 00:00:00", Mage::getModel('core/date')->timestamp(time()));
       

       
       //case 1:T shirt of the day
       if(Mage::getStoreConfig('sale_category/general/t_shirt_of_day_enable'))
       {
           $catid=Mage::getStoreConfig('sale_category/general/t_shirt_of_day');
           $from_date=Mage::getResourceModel('catalog/product')->getAttributeRawValue($productId, $attribute_code_tshirt_from, $storeId);
           $to_date=Mage::getResourceModel('catalog/product')->getAttributeRawValue($productId, $attribute_code_tshirt_to, $storeId);
          
           if($from_date!='')
           {
               
               if($from_date <= $current_date)
               {
                   if($to_date !='')
                   {
                       if($to_date >= $current_date)
                       {                          
                           $this->_autosale_add_category($product, $catid);
                       }
                     else {
                        
                        $this->_autosale_remove_category($product, $catid);
                    }
                   }
                   else{
                       
                       $this->_autosale_add_category($product, $catid);
                   }
               }
               
           }

       }
       
       
       //case 2: After hour
       if(Mage::getStoreConfig('sale_category/general/after_hour_enable'))
       {
           $catid=Mage::getStoreConfig('sale_category/general/after_hour');
           $from_date=Mage::getResourceModel('catalog/product')->getAttributeRawValue($productId, $attribute_code_after_from, $storeId);
           $to_date=Mage::getResourceModel('catalog/product')->getAttributeRawValue($productId, $attribute_code_after_to, $storeId);
           
           if($from_date!='')
           {
               
               if($from_date <= $current_date)
               {
                   if($to_date !='')
                   {
                       if($to_date >= $current_date)
                       {
                          
                           $this->_autosale_add_category($product, $catid);
                       }
                     else {
                       
                        $this->_autosale_remove_category($product, $catid);
                    }
                   }
                   else{
                     
                       $this->_autosale_add_category($product, $catid);
                   }
               }
               
           }
           
           
           
       }
       
       
       //case 3:Gallery Category
       if(Mage::getStoreConfig('sale_category/general/gallery_enable'))
       {
           $catid=Mage::getStoreConfig('sale_category/general/gallery');
           $from_date=Mage::getResourceModel('catalog/product')->getAttributeRawValue($productId, $attribute_code_gallery_from, $storeId);
           $to_date=Mage::getResourceModel('catalog/product')->getAttributeRawValue($productId, $attribute_code_gallery_to, $storeId);
           
           if($from_date!='')
           {
               
               if($from_date <= $current_date)
               {
                   if($to_date !='')
                   {
                       if($to_date >= $current_date)
                       {
                          
                           $this->_autosale_add_category($product, $catid);
                       }
                     else {
                        
                        $this->_autosale_remove_category($product, $catid);
                    }
                   }
                   else{
                       
                       $this->_autosale_add_category($product, $catid);
                   }
               }
               
           }
           
           
       }
       
    }
    
    private function _autosale_add_category($product,$cat_id='')
    {
        if($cat_id !='')
        {
            $categoryIds = $product->getCategoryIds();
            if(!in_array($cat_id, $categoryIds))
            {
                $categoryIds[]=$cat_id;
                $product->setCategoryIds($categoryIds);
                $product->save();                
            }
        }
    }
    private function _autosale_remove_category($product,$cat_id='')
    {
        if($cat_id !='')
        {
            $categoryIds = $product->getCategoryIds();
            if(in_array($cat_id, $categoryIds))
            {
                if (($key = array_search($cat_id, $categoryIds)) !== false) {
                unset($categoryIds[$key]);
                }                
                $product->setCategoryIds($categoryIds);
                $product->save();                
            }
        }
    }

}

?>

<?php
/**
 * Product list
 *
 * @category   Manu
 * @package    Manu_Saleproducts
 * @author    Manu Jose K
 */

class Manu_Saleproducts_Model_Observer {
        /*
         * Cron job for every day.
         * Functionality:Remove all products from Sale category when special price date ends.
         * Notification:send mail to the store owner with list of removed products.
         * 
         */
        public function addRemoveProducts() {

        //date calculation 
        $email_content = array();
        $current_date = date("Y-m-d 00:00:00", Mage::getModel('core/date')->timestamp(time()));
        $yesterday_date = date("Y-m-d 00:00:00", Mage::getModel('core/date')->timestamp(time() - 60 * 60 * 24));


        //case 1 
        if (Mage::getStoreConfig('sale_category/general/t_shirt_of_day_enable')) {

            //category
            $catid = Mage::getStoreConfig('sale_category/general/t_shirt_of_day');
            $attr_name = 'special_to_date';
            $email_content['shirt']['removed'] = $this->removeProduct($catid, $yesterday_date, $attr_name);
            $attr_name = 'special_from_date';
            $email_content['shirt']['added'] = $this->addProduct($catid, $current_date, $attr_name);
        }

        //case 2 
        if (Mage::getStoreConfig('sale_category/general/after_hour_enable')) {

            //category
            $catid = Mage::getStoreConfig('sale_category/general/after_hour');
            $attr_name = 'afterhours_to_date';
            $email_content['after_hour']['removed'] = $this->removeProduct($catid, $yesterday_date, $attr_name);
            $attr_name = 'afterhours_from_date';
            $email_content['after_hour']['added'] = $this->addProduct($catid, $current_date, $attr_name);
        }

        //case 3 
        if (Mage::getStoreConfig('sale_category/general/gallery_enable')) {

            //category
            $catid = Mage::getStoreConfig('sale_category/general/gallery');
            $attr_name = 'gallery_to_date';
            $email_content['gallery']['removed'] = $this->removeProduct($catid, $yesterday_date, $attr_name);
            $attr_name = 'gallery_from_date';
            $email_content['gallery']['added'] = $this->addProduct($catid, $current_date, $attr_name);
        }
        if (Mage::getStoreConfig('sale_category/general/offer_mail')) {
            $newContent = "Hi,<br/> These are the products Added or Removed through Auto Sale Extension";
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
            $headers .= 'From: <' . Mage::getStoreConfig('general/store_information/name') . '@magento.com>';
            
            $newContent .='<br/><br/><b><u>T-Shirt of the day </u></b><br/>Added Products';
            foreach ($email_content['shirt']['added'] as $prod_id => $prod_name) {
                $newContent .='<br/>' . $prod_id . ' - ' . $prod_name;
            }
            $newContent .='<br/><br/>Removed Products';
            foreach ($email_content['shirt']['removed'] as $prod_id => $prod_name) {
                $newContent .='<br/>' . $prod_id . ' - ' . $prod_name;
            }
            $newContent .='<br/><br/><b><u>After Hour </u></b><br/>Added Products';
            foreach ($email_content['after_hour']['added'] as $prod_id => $prod_name) {
                $newContent .='<br/>' . $prod_id . ' - ' . $prod_name;
            }
            $newContent .='<br/><br/>Removed Products';
            foreach ($email_content['after_hour']['removed'] as $prod_id => $prod_name) {
                $newContent .='<br/>' . $prod_id . ' - ' . $prod_name;
            }
            $newContent .='<br/><br/><b><u>Gallery </u></b><br/>Added Products';
            foreach ($email_content['gallery']['added'] as $prod_id => $prod_name) {
                $newContent .='<br/>' . $prod_id . ' - ' . $prod_name;
            }
            $newContent .='<br/><br/>Removed Products';
            foreach ($email_content['gallery']['removed'] as $prod_id => $prod_name) {
                $newContent .='<br/>' . $prod_id . ' - ' . $prod_name;
            }

            $newContent .='<br/><br/> Thanks....<br/>';
            mail(Mage::getStoreConfig('trans_email/ident_general/email'), "Product Added/Removed By Auto Sale Extension", $newContent, $headers);
        }

        //mage::log($email_content);
    }
    
        
        
        /**
         * Removes product from a category
         * @param type $category_id
         * @param type $yesterday_date
         * @param type $attr_name
         * @return type
         */
        public function removeProduct($category_id, $yesterday_date, $attr_name) {

        $removedProducts = array();
        $catagoryModel = Mage::getModel('catalog/category')->load($category_id);


        //product collection
        $productCollection = Mage::getResourceModel('reports/product_collection')
                ->addAttributeToSelect('id')
                ->addAttributeToSelect('name')
                ->addCategoryFilter($catagoryModel);
        $productCollection->addAttributeToFilter($attr_name, array('date' => true, 'to' => $yesterday_date));
        $productCollection->load();

        $currentStoreID = Mage::app()->getStore()->getId();
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        foreach ($productCollection as $product) {
            $categoryIds = $product->getCategoryIds();
            if (in_array($category_id, $categoryIds)) {
                if (($key = array_search($category_id, $categoryIds)) !== false) {
                    unset($categoryIds[$key]);
                    $removedProducts[$product->getId()] = $product->getName();
                }
                $product->setCategoryIds($categoryIds);
                $product->save();
            }
        }
        Mage::app()->setCurrentStore($currentStoreID);

        return $removedProducts;
    }
    
    /**
     * Add products to a category
     * @param type $category_id
     * @param type $current_date
     * @param type $attr_name
     * @return type
     */
    public function addProduct($category_id, $current_date, $attr_name) {

        $addedProducts = array();
       
        //product collection
        $productCollection = Mage::getResourceModel('reports/product_collection')
                ->addAttributeToSelect('id')
                ->addAttributeToSelect('name');
        $productCollection->addAttributeToFilter($attr_name, array('date' => true, 'from' => $current_date));
        $productCollection->load();

        $currentStoreID = Mage::app()->getStore()->getId();
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        foreach ($productCollection as $product) {
            $categoryIds = $product->getCategoryIds();
            if (!in_array($category_id, $categoryIds)) {
                $categoryIds[] = $category_id;
                $product->setCategoryIds($categoryIds);
                $product->save();
                
                $addedProducts[$product->getId()] = $product->getName();
            }
        }
        Mage::app()->setCurrentStore($currentStoreID);

        return $addedProducts;
    }

}

?>

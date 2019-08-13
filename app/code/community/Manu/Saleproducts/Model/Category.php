<?php
/**
 * Product list
 *
 * @category   Manu
 * @package    Manu_Saleproducts
 * @author    Manu Jose K
 */
class Manu_Saleproducts_Model_Category {

    public function getCategories() {
        $categoryOption = array();
        $categories = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToSelect('name')
                ->addAttributeToFilter('level', array('eq' => '2'))
                ->load();
        foreach ($categories as $cat):
            $temp = array('value' => $cat->getId(), 'label' => $cat->getName() . ' ( Root Category : ' . $cat->getParentCategory()->getName() . ' ) ');
            array_push($categoryOption, $temp);
        endforeach;
        return $categoryOption;
    }

    public function toOptionArray() {
        return ($this->getCategories());
    }

}
?>

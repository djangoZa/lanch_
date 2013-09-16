<?php
class Lanch_Order_Service
{
    private $_taxPercent = 21;
    
    private $_orderRepository;
    private $_comboRepository;
    private $_comboProductRepository;
    private $_productRepository;
    private $_equipmentRepository;
    private $_customerDetails;
    private $_size;
    
    public function __construct()
    {
        $this->_orderRepository = new Lanch_Order_Repository();
        $this->_comboRepository = new Lanch_Combo_Repository();
        $this->_comboProductRepository = new Lanch_ComboProduct_Repository();
        $this->_productRepository = new Lanch_Product_Repository();
        $this->_equipmentRepository = new Lanch_Equipment_Repository();
    }
    
    public function getOrderSession()
    {
        return $this->_orderRepository->getOrderSession();
    }
    
    public function getOrder($size)
    {
        $order = $this->_orderRepository->getOrderSession();

        //update the orders totals if its not in sync with the current size
        $order = (array) $order;
        $order['size'] = $size;
        $order = $this->setTotal($order);
        $order = $this->setSubTotal($order);
        $this->_orderRepository->setOrderSession($order);
        
        $order = $this->_orderRepository->getOrderSession();
        
        return $order;
    }
    
    public function saveCustomerDetails(Array $details)
    {
        $this->_orderRepository->saveCustomerDetailsToOrderSession($details);
    }
    
    public function setTotal(Array $order)
    {
        //get the data needed to form the total
        $combo = $this->_getCombo($order);
        $products = $this->_getProducts($combo, $order);
        $equipment = $this->_getEquipment($products);
        
        //calculate the total
        $total = 0;
         
        $total = $this->_addCostOfComboBasePrice($total, $combo);
        $total = $this->_addCostOfProducts($total, $products, $order);
        $total = $this->_addCostOfEquipment($total, $equipment, $order);
        $total = $this->_addCostOfWaiters($total, $order, $combo);
        $total = $this->_addCostOfFormalDishes($total, $order, $combo);
        $total = $this->_addDiscount($total, $combo, $order);
        
        $order['total'] = ceil($total);

        return $order;
    }
    
    public function setSubTotal(Array $order)
    {
        $subTotal = $this->_deductTax($order['total']);
        $order['sub_total'] = ceil($subTotal);
        return $order;
    }
    
    private function _addCostOfGuests($total, Array $order)
    {
        $guests = $order['guests'];
        $total = $total * $guests;
        return $total;
    }
    
    private function _addCostOfWaiters($total, Array $order, Lanch_Combo $combo)
    {
        $waiters = $order['waiters'];
        $pricePerWaiter = $combo->getPricePerWaiter();
        
        //sum the cost of the waiters
        for ($i = 0; $i < $waiters; $i++) {
            $total += $pricePerWaiter;
        }
        
        return $total;
    }
    
    private function _addCostOfFormalDishes($total, Array $order, Lanch_Combo $combo)
    {
        $formalDishes = $order['formalDishes'];
        $guests = $order['guests'];
        $pricePerFormalDish = $combo->getPricePerPersonForFormalDishes();
    
        if ($formalDishes != 0) {
            $total += ($pricePerFormalDish * $guests);
        }
    
        return $total;
    }
    
    private function _addCostOfComboBasePrice($total, Lanch_Combo $combo)
    {
        $total += $combo->getBasePrice();
        return $total;
    }
    
    private function _addCostOfProducts($total, Array $products, Array $order)
    {
        $guests = $order['guests'];
        
        foreach ($products as $product) {
            $total += $product->getPrice() * $guests;
        }
    
        return $total;
    }
    
    private function _addCostOfEquipment($total, $equipment, $order)
    {
        $equipmentBlackListedIds = $order['equipmentBlackList'];
        foreach ($equipment as $aEquipment) {
            if (!in_array($aEquipment->getId(), $equipmentBlackListedIds)) {
                $total += $aEquipment->getPrice();
            }
        }

        return $total;
    }
    
    private function _addDiscount($total, Lanch_Combo $combo, $order)
    {
        $size = $order['size'];

        $total -= ($total * ($combo->getDiscount($size) / 100));

        return $total;
    }
    
    private function _deductTax($total)
    {
        $total -= ($total * ($this->_taxPercent / 100));

        return $total;
        
    }
    
    private function _getProducts(Lanch_Combo $combo, Array $order)
    {
        $products = array();
    
        $size = $order['size'];
    
        $productIds = $combo->getSelectedProductIdsBySizeId($size);
    
        foreach ($productIds as $productId) {
            $products[] = $this->_productRepository->getProductById($productId);
        }
    
        return $products;
    }
    
    private function _getCombo(Array $order)
    {
        $comboId = $order['comboId'];
        $combo = $this->_comboRepository->getComboById($comboId);
        return $combo;
    }
    
    private function _getEquipment(Array $products)
    {
        $out = array();
    
        foreach($products as $product) {
    
            foreach($product->getEquipment() as $equipment) {
                $out[$equipment->getId()] = $equipment;
            }
    
        }
    
        return $out;
    }
}
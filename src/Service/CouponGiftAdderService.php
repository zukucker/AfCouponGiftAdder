<?php declare(strict_types=1);

namespace AfCouponGiftAdder\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection as StructTaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CouponGiftAdderService {

    private SystemConfigService $configService;
    private EntityRepository $promotionRepository; 
    private EntityRepository $productRepository; 
    private EntityRepository $mediaRepository; 
    private CartService $cartSerivce;

    public function __construct(
        SystemConfigService $configService, 
        EntityRepository $promotionRepository, 
        EntityRepository $productRepository, 
        EntityRepository $mediaRepository, 
        CartService $cartService
    )
    {
        $this->configService = $configService;
        $this->promotionRepository = $promotionRepository;
        $this->productRepository = $productRepository;
        $this->mediaRepository = $mediaRepository;
        $this->cartSerivce = $cartService;
    }


    public function getSelectedProducts(SalesChannelContext $context) :array
    {
        $selectedProducts = $this->configService->get('AfCouponGiftAdder.config.selectedProducts', null);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $selectedProducts));
        $productsResult = $this->productRepository->search($criteria, $context->getContext())->getEntities();

        $products = [];
        if($productsResult){
            $products = $productsResult->getElements();
        }
        return $products;
    }
    public function getSelectedVoucher(SalesChannelContext $context)
    {
        $promotionId = $this->configService->get('AfCouponGiftAdder.config.selectedPromotion', null);
        $voucherCode = "";

        if($promotionId != null){
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $promotionId));
            $promotion = $this->promotionRepository->search($criteria, $context->getContext())->getEntities()->first();
            $voucherCode = $promotion->getCode();
        }

        return $voucherCode;
    }

    public function getSelectedName(){
        $selectedName = $this->configService->get('AfCouponGiftAdder.config.selectedName', null);
        return $selectedName;
    }
    public function getSelectedMedia($context){
        $selectedMedia = $this->configService->get('AfCouponGiftAdder.config.placeholderImage', null);
        if($selectedMedia){
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $selectedMedia));
            $result = $this->mediaRepository->search($criteria, $context->getContext());
            $selectedMedia =  $result->getEntities()->first();
        }
        return $selectedMedia;
    }

    public function createPlaceholderItem(Cart $cart, SalesChannelContext $context)
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE, null, 1);
        $lineItem->setStackable(true);
        $lineItem->setRemovable(true);
        if($this->getSelectedName()){
            $lineItem->setLabel($this->getSelectedName());
        }else{
            $lineItem->setLabel("Gratis Produkt");
        }
        $lineItem->setCover($this->getSelectedMedia($context));
        $lineItem->setPriceDefinition(new QuantityPriceDefinition(0, new StructTaxRuleCollection()));

        $this->cartSerivce->add($cart, $lineItem, $context);
    }

    public function removePlaceholderItem(Cart $cart, SalesChannelContext $context)
    {
        $customLineItems = $cart->getLineItems()->filterFlatByType('custom');
        if($customLineItems){
            $this->cartSerivce->remove($cart, $customLineItems[0]->getId(), $context);
        }
    }

}

<?php declare(strict_types=1);

namespace AfCouponGiftAdder\Service;

use Exception;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection as StructTaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CouponGiftAdderService {

    private LineItemFactoryRegistry $factory;
    private SystemConfigService $configService;
    private EntityRepository $promotionRepository; 
    private EntityRepository $productRepository; 
    private EntityRepository $mediaRepository; 
    private SalesChannelRepository $salesChannelProductRepository; 
    private CartService $cartSerivce;

    public function __construct(
        LineItemFactoryRegistry $factory,
        SystemConfigService $configService, 
        EntityRepository $promotionRepository, 
        EntityRepository $productRepository, 
        EntityRepository $mediaRepository, 
        SalesChannelRepository $salesChannelProductRepository,
        CartService $cartService
    )
    {
        $this->factory = $factory;
        $this->configService = $configService;
        $this->promotionRepository = $promotionRepository;
        $this->productRepository = $productRepository;
        $this->mediaRepository = $mediaRepository;
        $this->salesChannelProductRepository = $salesChannelProductRepository;
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
        $selectedProducts = $this->getSelectedProducts($context);
        foreach($selectedProducts as $product){
            dump($product->getId());
            $lineItem = $this->factory->create([
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'referencedId' => $product->getId(),
                'stable' => false,
                'quantity' => 1,
                'price' => new QuantityPriceDefinition(100, $cart->getPrice()->getTaxRules(), 1),
                'payload' =>[
                    'free_product' => true
                ]
            ], $context);

            $lineItem->setGood(true);
            $lineItem->setRemovable(true);
            $lineItem->setStackable(false);
            $lineItem->setQuantityInformation(new QuantityInformation(false, 1, 1, 1));
            $lineItem->setId("free_product");

            try{
                $this->cartSerivce->add($cart, $lineItem, $context);
            }catch(Exception $err){
                dump($err->getMessage());
                die();
            }
        }
    }

    public function removePlaceholderItem(Cart $cart, SalesChannelContext $context)
    {
        $customLineItems = $cart->getLineItems()->filterFlatByType('custom');
        if($customLineItems){
            $this->cartSerivce->remove($cart, $customLineItems[0]->getId(), $context);
        }
    }

    public function toggleModal(BeforeLineItemAddedEvent $event, SalesChannelContext $context)
    {
        $products = $this->configService->get('AfCouponGiftAdder.config.selectedProducts');
        $result = $this->salesChannelProductRepository->search(new Criteria($products), $context)->getEntities();
        $cart = $event->getCart();
        $cart->addExtension("free_product", new ArrayStruct(["products" => $result]));

    }

}

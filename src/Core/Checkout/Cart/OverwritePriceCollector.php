<?php declare(strict_types=1);

namespace AfCouponGiftAdder\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OverwritePriceCollector implements CartDataCollectorInterface, CartProcessorInterface
{
    private QuantityPriceCalculator $calculator;
    private SystemConfigService $systemConfigService;

    public function __construct(
        QuantityPriceCalculator $calculator,
        SystemConfigService $systemConfigService
    )
    {
        $this->calculator = $calculator;
        $this->systemConfigService = $systemConfigService;
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // get all product ids of current cart
        //$vouchers = $original->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        $lineItems = $original->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $productIds = $original->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE)->getReferenceIds();

        // remove all product ids which are already fetched from the database
        $filtered = $this->filterAlreadyFetchedPrices($productIds, $data);

        // Skip execution if there are no prices to be saved
        if (empty($filtered)) {
            return;
        }
        $configuredId = $this->systemConfigService->get('AfCouponGiftAdder.config.selectedProducts');


        //$code = $this->systemConfigService->get('AfCouponGiftAdder.config.selectedPromotion');
        //foreach($vouchers as $voucher){
            //if($voucher->getId() == $code){
                //$key = $this->buildKey($voucher->getUniqueIdentifier());
                //$newPrice = 0;
                //$data->set($key, $newPrice);
            //}
        //}

        foreach($lineItems as $lineItem){
            if($lineItem->getId() == "free_product"){
                $key = $this->buildKey($lineItem->getUniqueIdentifier());
                $newPrice = 0;
                $data->set($key, $newPrice);
            }

        }
        //foreach ($filtered as $id) {
            //if($id === $configuredId){
                //$key = $this->buildKey($id);

                //// Needs implementation, just an example
                //$newPrice = 0;

                //// we have to set a value for each product id to prevent duplicate queries in next calculation
                //$data->set($key, $newPrice);
                //return;
            //}
        //}
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // get all product line items
        $products = $toCalculate->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        foreach ($products as $product) {
            $key = $this->buildKey($product->getUniqueIdentifier());

            // no overwritten price? continue with next product
            if (!$data->has($key) || $data->get($key) === null) {
                continue;
            }

            $newPrice = $data->get($key);

            // build new price definition
            $definition = new QuantityPriceDefinition(
                $newPrice,
                $product->getPrice()->getTaxRules(),
                $product->getPrice()->getQuantity()
            );

            // build CalculatedPrice over calculator class for overwritten price
            $calculated = $this->calculator->calculate($definition, $context);

            // set new price into line item
            $product->setPrice($calculated);
            $product->setPriceDefinition($definition);
        }
    }

    private function filterAlreadyFetchedPrices(array $productIds, CartDataCollection $data): array
    {
        $filtered = [];

        foreach ($productIds as $id) {
            $key = $this->buildKey($id);

            // already fetched from database?
            if ($data->has($key)) {
                continue;
            }

            $filtered[] = $id;
        }

        return $filtered;
    }

    private function buildKey(string $id): string
    {
        return 'price-overwrite-'.$id;
    }
}

<?php declare(strict_types=1);

namespace AfCouponGiftAdder\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use AfCouponGiftAdder\Service\CouponGiftAdderService;

class MySubscriber implements EventSubscriberInterface
{
    private CouponGiftAdderService $couponService;

    public function __construct(CouponGiftAdderService $couponService){
        $this->couponService = $couponService;
    }

    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            BeforeLineItemAddedEvent::class => 'onLineItemAdded',
            BeforeLineItemRemovedEvent::class => 'onLineItemRemoved',
        ];
    }

    public function onLineItemAdded(BeforeLineItemAddedEvent $event)
    {
        $code = "";
        $extensions = $event->getCart()->getExtensions();
        $context = $event->getSalesChannelContext();
        if ($event->getLineItem()->getType() === PromotionProcessor::LINE_ITEM_TYPE) {
            $code = $event->getLineItem()->getReferencedId();

            if ($code !== null && $code !== '') {
                // here we have valid code
                if($code === $this->couponService->getSelectedVoucher($context)){
                    // it is our code we now add the selection
                    $this->couponService->createPlaceholderItem($event->getcart(), $context);
                    //dump($this->couponService->getSelectedProducts($context));
                }
            }
        }
        //die();
    }
    public function onLineItemRemoved(BeforeLineItemRemovedEvent $event)
    {
        $cart = $event->getCart();
        $context = $event->getSalesChannelContext();
        if($event->getLineItem()->getType() === PromotionProcessor::LINE_ITEM_TYPE){
            $this->couponService->removePlaceholderItem($cart, $context);
        }

    }
}

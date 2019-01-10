<?php

namespace Drupal\commerce_postfinance\EventSubscriber;

use Drupal\commerce_postfinance\Event\PaymentResponseEvent;
use Drupal\commerce_postfinance\Event\PostfinanceEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event handler for Postfinance post-payment requests.
 *
 * Adds all post-sale parameters from Postfinance to the order.
 * This allows for example to lookup the used payment method.
 */
class PaymentResponseSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PostfinanceEvents::PAYMENT_RESPONSE => ['onPaymentResponse'],
    ];
  }

  /**
   * Store received post-sale parameters in the order's data property.
   *
   * @param \Drupal\commerce_postfinance\Event\PaymentResponseEvent $event
   *   The PaymentResponseEvent event.
   */
  public function onPaymentResponse(PaymentResponseEvent $event) {
    $order = $event->getOrder();
    $parameters = $event->getParameters();

    $order->setData('commerce_postfinance_payment', $parameters);
  }

}

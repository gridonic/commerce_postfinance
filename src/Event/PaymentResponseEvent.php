<?php

namespace Drupal\commerce_postfinance\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * This event is dispatched after receiving the post-payment request from
 * Postfinance. Note that the event is always dispatched, regardless of the
 * payment status (success, error, cancel). The event handler is responsible
 * to check the status and act accordingly.
 *
 * @see \whatwedo\PostFinanceEPayment\Model\PaymentStatus
 */
class PaymentResponseEvent extends Event {

  /**
   * The commerce order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  private $order;

  /**
   * Post-payment parameters received from Postfinance.
   *
   * @var array
   *
   * @see https://e-payment-postfinance.v-psp.com/de/de/guides/integration%20guides/e-commerce
   */
  private $parameters = [];

  /**
   * PaymentResponseEvent constructor.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   * @param array $parameters
   *   The received post-sale parameters.
   */
  public function __construct(OrderInterface $order, array $parameters) {
    $this->order = $order;
    $this->parameters = $parameters;
  }

  /**
   * Get the commerce order entity.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The commerce order entity.
   */
  public function getOrder() {
    return $this->order;
  }

  /**
   * Get the post-sale parameters.
   *
   * @return array
   *   The parameters.
   */
  public function getParameters() {
    return $this->parameters;
  }

}

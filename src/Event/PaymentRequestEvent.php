<?php

namespace Drupal\commerce_postfinance\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event fired when performing the payment request to Postfinance.
 *
 * Allows developers to modify or send additional parameters to Postfinance.
 */
class PaymentRequestEvent extends Event {

  /**
   * The commerce order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  private $order;

  /**
   * Additional parameters being sent to the Postfinance payment gateway.
   *
   * @var array
   *
   * @see https://e-payment-postfinance.v-psp.com/de/de/guides/integration%20guides/e-commerce
   */
  private $parameters = [];

  /**
   * PaymentRequestEvent constructor.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order where a payment request is sent.
   */
  public function __construct(OrderInterface $order) {
    $this->order = $order;
  }

  /**
   * Get additional parameters.
   *
   * @return array
   *   The parameters.
   */
  public function getParameters() {
    return $this->parameters;
  }

  /**
   * Set additional parameters.
   *
   * @param array $parameters
   *   The parameters.
   */
  public function setParameters(array $parameters) {
    $this->parameters = $parameters;
  }

  /**
   * Add a single parameter.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function addParameter($key, $value) {
    $this->parameters[$key] = $value;
  }

}

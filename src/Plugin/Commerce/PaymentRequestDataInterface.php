<?php

namespace Drupal\commerce_postfinance;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Provides data required for the payment POST request to Postfinance.
 *
 * @package Drupal\commerce_postfinance
 */
interface PaymentRequestDataInterface {

  /**
   * Returns the parameters to be sent to Postfinance for the given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   *
   * @return array
   *   The parameters.
   */
  public function getParameters(OrderInterface $order);

  /**
   * Returns the redirect url from Postfinance.
   *
   * @return string
   *   The redirect url.
   */
  public function getRedirectUrl();

}

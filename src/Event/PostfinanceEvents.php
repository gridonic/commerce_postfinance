<?php

namespace Drupal\commerce_postfinance\Event;

/**
 * Defines events for the Commerce Postfinance module.
 *
 * @package Drupal\commerce_postfinance\Event
 */
final class PostfinanceEvents {

  /**
   * Event fired when performing the payment request to Postfinance.
   *
   * @Event
   *
   * @see \Drupal\commerce_postfinance\Event\PaymentRequestEvent
   */
  const PAYMENT_REQUEST = 'commerce_postfinance.payment_request';

}

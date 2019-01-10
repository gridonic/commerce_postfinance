<?php

namespace Drupal\commerce_postfinance\Event;

/**
 * Defines events for the Commerce Postfinance module.
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

  /**
   * Event fired after receiving a request from Postfinance.
   *
   * @Event
   *
   * @see \Drupal\commerce_postfinance\Event\PaymentResponseEvent
   */
  const PAYMENT_RESPONSE = 'commerce_postfinance.payment_response';

}

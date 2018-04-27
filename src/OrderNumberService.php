<?php

namespace Drupal\commerce_postfinance;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Service to create a remote order number for the Postfinance payments.
 *
 * Postfinance does not allow to process the same order number multiple times.
 * If a payment fails due to errors or canceling, the order must get a new
 * number so that the payment can be executed again.
 *
 * @package Drupal\commerce_postfinance
 */
class OrderNumberService {

  const KEY_NUMBER_MINOR = 'postfinance_number_minor';

  /**
   * Get the order number of the given commerce order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   *
   * @return string
   *   The remote order number for Postfinance.
   */
  public function getNumber(OrderInterface $order) {
    if ($minorNumber = $order->getData(self::KEY_NUMBER_MINOR)) {
      return sprintf('%s-%s', $order->id(), $minorNumber);
    }
    return $order->id();
  }

  /**
   * Increase the minor number of the given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function increaseMinorNumber(OrderInterface $order) {
    $minorNumber = $order->getData(self::KEY_NUMBER_MINOR);
    if ($minorNumber === NULL) {
      $minorNumber = 1;
    }
    else {
      $minorNumber = (int) $minorNumber;
      $minorNumber++;
    }
    $order->setData(self::KEY_NUMBER_MINOR, $minorNumber);
    $order->save();
  }

}

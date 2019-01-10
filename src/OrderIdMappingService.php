<?php

namespace Drupal\commerce_postfinance;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Service to map internal order IDs to remote order IDs.
 *
 * Postfinance does not allow to process the same order ID multiple times.
 * If a payment fails due to errors or canceling, the following retrying payment
 * must get a new remote order ID so that the payment can be processed again.
 */
class OrderIdMappingService {

  const KEY_NUMBER_MINOR = 'commerce_postfinance_number_minor';

  /**
   * Get the remote order ID of the given commerce order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   *
   * @return string
   *   The remote order ID.
   */
  public function getRemoteOrderId(OrderInterface $order) {
    if ($minorNumber = $order->getData(self::KEY_NUMBER_MINOR)) {
      return sprintf('%s-%s', $order->id(), $minorNumber);
    }

    return $order->id();
  }

  /**
   * Increase the minor number of the remote ID for the given commerce order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function increaseRemoteOrderIdMinor(OrderInterface $order) {
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

  /**
   * Get the commerce order ID from the given remote order ID.
   *
   * @param string $remoteOrderId
   *   The remote order ID.
   *
   * @return int
   *   The internal order ID of a commerce order entity.
   */
  public function getOrderIdFromRemoteOrderId($remoteOrderId) {
    if (strpos($remoteOrderId, '-') !== FALSE) {
      return (int) explode('-', $remoteOrderId)[0];
    }

    return (int) $remoteOrderId;
  }

}

<?php

namespace Drupal\commerce_postfinance\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\InvalidResponseException;
use Drupal\commerce_postfinance\OrderIdMappingService;
use Drupal\commerce_postfinance\PaymentResponseService;
use Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A controller handling post-payment requests from Postfinance.
 *
 * Enter the following url post-payment url in the Postfinance back office:
 * https://yourshop.com/commerce_postfinance/payment.
 *
 * @package Drupal\commerce_postfinance\Controller
 */
class IpnController extends ControllerBase {

  /**
   * Handle a post-payment request from Postfinance.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Drupal's request object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A success or error response.
   */
  public function handlePostPayment(Request $request) {
    $parameters = ($request->getMethod() === 'GET') ? $request->query : $request->request;

    if (!$parameters->has('orderID')) {
      $this->logger()->warning('Received post-payment request with missing orderID parameter');
      return $this->errorResponse();
    }

    $order = $this->getOrderByRemoteOrderId($parameters->get('orderID'));
    if (!$order) {
      $this->logger()->warning('Received post-payment request which could not be mapped to an order: %params', [
        '%params' => json_encode($parameters),
      ]);
      return $this->errorResponse();
    }

    $redirectCheckout = $this->getRedirectCheckout($order);
    if (!$redirectCheckout instanceof RedirectCheckout) {
      $this->logger()->warning('Received post-payment request but the order does not seem to have the correct payment gateway assigned');
      return $this->errorResponse();
    }

    $paymentResponseService = new PaymentResponseService(
      $redirectCheckout,
      new OrderIdMappingService(),
      $this->entityTypeManager,
      $this->logger()
    );

    try {
      $paymentResponseService->onReturn($order, $request);
      return new Response();
    }
    catch (InvalidResponseException $e) {
      return $this->errorResponse();
    }
  }

  /**
   * Get the commerce order from a given remote order ID.
   *
   * @param string $remoteOrderId
   *   Remote order ID from Postfinance.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The commerce order.
   */
  protected function getOrderByRemoteOrderId($remoteOrderId) {
    $orderIdMappingService = new OrderIdMappingService();
    $orderId = $orderIdMappingService->getOrderIdFromRemoteOrderId($remoteOrderId);

    return $this->entityTypeManager()
      ->getStorage('commerce_order')
      ->load($orderId);
  }

  /**
   * Get the Postfinance redirect checkout payment gateway plugin.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   *
   * @return \Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout
   *   The redirect checkout payment plugin.
   */
  protected function getRedirectCheckout(OrderInterface $order) {
    $paymentGateway = $order->get('payment_gateway')->entity;
    $redirectCheckout = $paymentGateway->getPlugin();

    return $redirectCheckout;
  }

  /**
   * Build an error response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A 403 response.
   */
  protected function errorResponse() {
    return new Response('', 403);
  }

  /**
   * Get the logger channel for this module.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger channel.
   */
  protected function logger() {
    return $this->getLogger('commerce_postfinance');
  }

}

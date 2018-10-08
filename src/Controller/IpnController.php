<?php

namespace Drupal\commerce_postfinance\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_postfinance\OrderIdMappingService;
use Drupal\commerce_postfinance\PaymentResponseService;
use Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
   * Drupal's logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $loggerChannelFactory;

  /**
   * Drupal's event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * IpnController constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Drupal's logger factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Drupal's event dispatcher.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerChannelFactory, EventDispatcherInterface $eventDispatcher) {
    $this->loggerChannelFactory = $loggerChannelFactory;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('logger.factory'), $container->get('event_dispatcher'));
  }

  /**
   * Handle a post-payment request from Postfinance.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Drupal's request object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A HTTP 200 status response.
   */
  public function handlePostPayment(Request $request) {
    $parameters = ($request->getMethod() === 'GET') ? $request->query : $request->request;

    if (!$parameters->has('orderID')) {
      $this->logger()->warning('Received post-payment request with missing orderID parameter');
      return $this->response();
    }

    $order = $this->getOrderByRemoteOrderId($parameters->get('orderID'));
    if (!$order) {
      $this->logger()->warning('Received post-payment request which could not be mapped to an order: %params', [
        '%params' => json_encode($parameters),
      ]);
      return $this->response();
    }

    $redirectCheckout = $this->getRedirectCheckout($order);
    if (!$redirectCheckout instanceof RedirectCheckout) {
      $this->logger()->warning('Received post-payment request but the order does not seem to have the correct payment gateway assigned');
      return $this->response();
    }

    $paymentResponseService = new PaymentResponseService(
      $redirectCheckout,
      new OrderIdMappingService(),
      $this->entityTypeManager(),
      $this->logger(),
      $this->eventDispatcher
    );

    try {
      $paymentResponseService->onReturn($order, $request);
      $this->setOrderStateToCompleted($order);
      return $this->response();
    }
    catch (\Exception $e) {
      return $this->response();
    }
  }

  /**
   * Set the order state to 'complete' after a successful payment.
   *
   * The order may still be locked if the redirect back from Postfinance
   * was not successful. Unlock the order and place it, if possible.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setOrderStateToCompleted(OrderInterface $order) {
    $state = $order->getState();
    if ($state->getLabel() === 'complete') {
      return;
    }

    if ($order->isLocked()) {
      $order->unlock();
    }

    $transitions = $state->getTransitions();
    if (isset($transitions['place'])) {
      $state->applyTransition($transitions['place']);
      $this->logger()->info(sprintf('The order %s has been placed (completed) by a post-sale request.', $order->id()));
    }

    $order->save();
  }

  /**
   * Get the commerce order from a given remote order ID.
   *
   * @param string $remoteOrderId
   *   Remote order ID from Postfinance.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
   * Build the HTTP response returned by the IPN controller.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A HTTP 200 response.
   */
  protected function response() {
    return new Response();
  }

  /**
   * Get the logger channel for this module.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger channel.
   */
  protected function logger() {
    return $this->loggerChannelFactory->get('commerce_postfinance');
  }

}

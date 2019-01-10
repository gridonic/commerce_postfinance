<?php

namespace Drupal\commerce_postfinance;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\InvalidResponseException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_postfinance\Event\PaymentResponseEvent;
use Drupal\commerce_postfinance\Event\PostfinanceEvents;
use Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use whatwedo\PostFinanceEPayment\Environment\ProductionEnvironment;
use whatwedo\PostFinanceEPayment\Environment\TestEnvironment;
use whatwedo\PostFinanceEPayment\Exception\NotValidSignatureException;
use whatwedo\PostFinanceEPayment\Model\PaymentStatus;
use whatwedo\PostFinanceEPayment\PostFinanceEPayment;
use whatwedo\PostFinanceEPayment\Response\Response;

/**
 * Service class to handle a payment response from Postfinance in Commerce.
 */
class PaymentResponseService {

  /**
   * The Postfinance redirect checkout payment gateway plugin.
   *
   * @var \Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout
   */
  private $redirectCheckout;

  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The order number mapping service.
   *
   * @var \Drupal\commerce_postfinance\OrderIdMappingService
   */
  private $orderNumberMappingService;

  /**
   * The logger channel for this module.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * PaymentResponseService constructor.
   *
   * @param \Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout $redirectCheckout
   *   The Postfinance redirect checkout payment gateway plugin.
   * @param \Drupal\commerce_postfinance\OrderIdMappingService $orderNumberMappingService
   *   The order number service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Drupal's entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   *   A logger channel for this module.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Drupal's event dispatcher.
   */
  public function __construct(RedirectCheckout $redirectCheckout,
                              OrderIdMappingService $orderNumberMappingService,
                              EntityTypeManagerInterface $entityTypeManager,
                              LoggerChannelInterface $loggerChannel,
                              EventDispatcherInterface $eventDispatcher
    ) {
    $this->redirectCheckout = $redirectCheckout;
    $this->orderNumberMappingService = $orderNumberMappingService;
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $loggerChannel;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Handle the "return" request (success or error).
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $environment = $this->setupEnvironment();
    $ePayment = new PostFinanceEPayment($environment);

    try {
      $parameters = ($request->getMethod() === 'GET') ? $request->query->all() : $request->request->all();
      $response = $ePayment->getResponse($parameters);

      if ($response->hasError()) {
        if (PaymentStatus::isPartiallySuccess($response->getStatus())) {
          $this->handleResponsePartiallySuccess($order, $response, $parameters);
        }
        else {
          $this->handleResponseError($order, $response, $parameters);
        }
      }
      else {
        $this->handleResponseSuccess($order, $response, $parameters);
      }
    }
    catch (NotValidSignatureException $e) {
      $this->logger->alert('Signature mismatch, possible attempt to fraud payment request data for order %order', [
        '%order' => $order->id(),
      ]);
      throw new InvalidResponseException('Signature mismatch, possible attempt to fraud the payment request data');
    }
    catch (\Exception $e) {
      throw new InvalidResponseException($e->getMessage());
    }
  }

  /**
   * Handle the "cancel" request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onCancel(OrderInterface $order, Request $request) {
    $this->orderNumberMappingService->increaseRemoteOrderIdMinor($order);
    $parameters = ($request->getMethod() === 'GET') ? $request->query->all() : $request->request->all();
    $this->dispatchEvent($order, $parameters);
  }

  /**
   * Create or update the commerce payment for the given order and state.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   * @param \whatwedo\PostFinanceEPayment\Response\Response $response
   *   Postfinance response object.
   * @param string $state
   *   A state for the payment.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function saveCommercePayment(OrderInterface $order, Response $response, $state) {
    /* @var \Drupal\commerce_payment\PaymentStorage $paymentStorage */
    $paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment = $paymentStorage->loadByRemoteId($response->getPaymentId());

    if (!$payment) {
      $payment = $paymentStorage->create(
        [
          'payment_gateway' => $this->redirectCheckout->getEntityId(),
          'order_id' => $order->id(),
          'remote_id' => $response->getPaymentId(),
        ]
      );
    }

    $amount = new Price($response->getAmount(), $response->getCurrency());

    $payment->set('state', $state);
    $payment->set('amount', $amount);
    $payment->set('remote_state', $response->getStatus());
    $payment->save();
  }

  /**
   * Setup the Postfinance environment.
   *
   * @return \whatwedo\PostFinanceEPayment\Environment\Environment
   *   Postfinance environment object.
   */
  protected function setupEnvironment() {
    $config = $this->redirectCheckout->getConfiguration();

    if ($config['mode'] === 'live') {
      $environment = new ProductionEnvironment($config['psp_id'], $config['sha_in'], $config['sha_out']);
    }
    else {
      $environment = new TestEnvironment($config['psp_id'], $config['sha_in'], $config['sha_out']);
    }

    return $environment
      ->setCharset($config['charset'])
      ->setHashAlgorithm($config['hash_algorithm']);
  }

  /**
   * Handle successful payment.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   * @param \whatwedo\PostFinanceEPayment\Response\Response $response
   *   Postfinance Response object.
   * @param array $parameters
   *   Parameters from the transaction feedback.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function handleResponseSuccess(OrderInterface $order, Response $response, array $parameters) {
    $this->dispatchEvent($order, $parameters);
    $this->saveCommercePayment($order, $response, 'completed');
  }

  /**
   * Handle a "partially successful" payment.
   *
   * Refers to a status where the payment has not yet been processed,
   * for example if the acquiring system is unavailable. The payment
   * may be completed with a post-sale request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   * @param \whatwedo\PostFinanceEPayment\Response\Response $response
   *   Postfinance Response object.
   * @param array $parameters
   *   Parameters from the transaction feedback.
   *
   * @see \Drupal\commerce_postfinance\Controller\IpnController::handlePostPayment()
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function handleResponsePartiallySuccess(OrderInterface $order, Response $response, array $parameters) {
    $this->logger->info('Received a partially successful payment response for order %order: %details', [
      '%order' => $order->id(),
      '%details' => json_encode($parameters),
    ]);

    $this->dispatchEvent($order, $parameters);
    $this->saveCommercePayment($order, $response, 'completed');
  }

  /**
   * Handle payment if errors occurred.
   *
   * Increase a minor number of the order so that the payment
   * can be processed again by Postfinance with the next payment request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   * @param \whatwedo\PostFinanceEPayment\Response\Response $response
   *   Postfinance response object.
   * @param array $parameters
   *   Parameters from the transaction feedback.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function handleResponseError(OrderInterface $order, Response $response, array $parameters) {
    $this->logger->warning('Received an error payment response for order %order: %details', [
      '%order' => $order->id(),
      '%details' => json_encode($parameters),
    ]);

    $this->orderNumberMappingService->increaseRemoteOrderIdMinor($order);
    $this->dispatchEvent($order, $parameters);

    throw new PaymentGatewayException('Payment incomplete or declined');
  }

  /**
   * Dispatch the PaymentResponseEvent.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   * @param array $parameters
   *   The post-payment parameters.
   */
  protected function dispatchEvent(OrderInterface $order, array $parameters) {
    $this->eventDispatcher->dispatch(PostfinanceEvents::PAYMENT_RESPONSE, new PaymentResponseEvent($order, $parameters));
  }

}

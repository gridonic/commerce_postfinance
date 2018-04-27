<?php

namespace Drupal\commerce_postfinance;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\InvalidResponseException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use whatwedo\PostFinanceEPayment\Environment\ProductionEnvironment;
use whatwedo\PostFinanceEPayment\Environment\TestEnvironment;
use whatwedo\PostFinanceEPayment\Exception\NotValidSignatureException;
use whatwedo\PostFinanceEPayment\Model\PaymentStatus;
use whatwedo\PostFinanceEPayment\PostFinanceEPayment;
use whatwedo\PostFinanceEPayment\Response\Response;

/**
 * Service class to handle the payment response from Postfinance in Commerce.
 *
 * @package Drupal\commerce_postfinance
 */
class PaymentResponseService {

  /**
   * The configuration data from the plugin.
   *
   * @var array
   */
  private $pluginConfiguration;

  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Id of the payment gateway entity.
   *
   * @var int
   */
  private $paymentGatewayEntityId;

  /**
   * The order number service.
   *
   * @var \Drupal\commerce_postfinance\OrderNumberService
   */
  private $orderNumberService;

  /**
   * PaymentResponseService constructor.
   *
   * @param int $paymentGatewayEntityId
   *   The payment gateway entity id.
   * @param array $pluginConfiguration
   *   Configuration data from the plugin.
   * @param \Drupal\commerce_postfinance\OrderNumberService $orderNumberService
   *   The order number service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Drupal's entity type manager.
   */
  public function __construct($paymentGatewayEntityId,
        array $pluginConfiguration,
        OrderNumberService $orderNumberService,
        EntityTypeManagerInterface $entityTypeManager
    ) {
    $this->paymentGatewayEntityId = $paymentGatewayEntityId;
    $this->pluginConfiguration = $pluginConfiguration;
    $this->orderNumberService = $orderNumberService;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Handle the "return" request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $environment = $this->setupEnvironment();
    $ePayment = new PostFinanceEPayment($environment);
    try {
      $response = $ePayment->getResponse($request->query->all());
      if ($response->hasError()) {
        switch ($response->getStatus()) {
          case 0:
          case PaymentStatus::INCOMPLETE:
            $this->createCommercePayment($order, $response, 'incomplete');
            break;

          case PaymentStatus::DECLINED:
            $this->createCommercePayment($order, $response, 'declined');
            break;

          default:
            $this->createCommercePayment($order, $response, 'error');
        }
        // The PaymentGatewayException will display a warning and the user may
        // retry the payment. As Postfinance won't process the same order number
        // again, we increase the minor number.
        $this->orderNumberService->increaseMinorNumber($order);
        throw new PaymentGatewayException('Payment incomplete or declined');
      }
      // Payment successful.
      $this->createCommercePayment($order, $response, 'completed');
      $this->storePaymentDetailsInOrder($order, $request);
    }
    catch (NotValidSignatureException $e) {
      throw new InvalidResponseException('Signature mismatch, possible attempt to fraud the payment request data');
    }
    catch (\Exception $e) {
      throw new InvalidResponseException($e->getMessage());
    }
  }

  /**
   * Handle the "cancel" request.
   *
   * Increase the minor number for the order so that the payment can be
   * processed again (Postfinance does not allow to process the same order
   * number multiple times).
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onCancel(OrderInterface $order, Request $request) {
    $this->orderNumberService->increaseMinorNumber($order);
  }

  /**
   * Create a payment for the given order and state.
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
   */
  protected function createCommercePayment(OrderInterface $order, Response $response, $state) {
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $amount = new Price($response->getAmount(), $response->getCurrency());
    $payment = $payment_storage->create(
        [
          'state' => $state,
          'amount' => $amount,
          'payment_gateway' => $this->paymentGatewayEntityId,
          'order_id' => $order->id(),
          'remote_id' => $response->getPaymentId(),
          'remote_state' => $response->getStatus(),
        ]
    );
    $payment->save();
  }

  /**
   * Setup the Postfinance environment.
   *
   * @return \whatwedo\PostFinanceEPayment\Environment\Environment
   *   Postfinance environment object.
   */
  protected function setupEnvironment() {
    $config = $this->pluginConfiguration;
    if ($config['mode'] === 'live') {
      $environment = new ProductionEnvironment($config['psp_id'], $config['sha_in'], $config['sha_out']);
    }
    else {
      $environment = new TestEnvironment($config['psp_id'], $config['sha_in'], $config['sha_out']);
    }
    $environment->setCharset($config['charset']);
    $environment->setHashAlgorithm($config['hash_algorithm']);
    return $environment;
  }

  /**
   * Store the payment data returned from Postfinance in the commerce order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function storePaymentDetailsInOrder(OrderInterface $order, Request $request) {
    $order->setData(sprintf('postfinance_payment_%s', time()), $request->query->all());
    $order->save();
  }

}

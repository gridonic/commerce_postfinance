<?php

namespace Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_postfinance\OrderIdMappingService;
use Drupal\commerce_postfinance\PaymentResponseService;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the Postfinance offsite payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "postfinance_redirect_checkout",
 *   label = @Translation("Postfinance (Redirect to Postfinance)"),
 *   display_label = @Translation("Postfinance"),
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_postfinance\PluginForm\RedirectCheckoutForm",
 *   }
 * )
 */
class RedirectCheckout extends OffsitePaymentGatewayBase {

  const CHARSET_ISO_8859_1 = 'iso_8859-1';
  const CHARSET_UTF_8 = 'utf-8';

  const HASH_SHA1 = 'sha1';
  const HASH_SHA256 = 'sha256';
  const HASH_SHA512 = 'sha512';

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $loggerChannelFactory;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * Constructs a new Postfinance gateway.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $paymentTypeManager
   *   The payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $paymentMethodTypeManager
   *   The payment method type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(array $configuration,
                              $pluginId,
                              $pluginDefinition,
                              EntityTypeManagerInterface $entityTypeManager,
                              PaymentTypeManager $paymentTypeManager,
                              PaymentMethodTypeManager $paymentMethodTypeManager,
                              TimeInterface $time,
                              LoggerChannelFactoryInterface $loggerChannelFactory,
                              EventDispatcherInterface $eventDispatcher
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $entityTypeManager, $paymentTypeManager, $paymentMethodTypeManager, $time);
    $this->loggerChannelFactory = $loggerChannelFactory;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('logger.factory'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'psp_id' => '',
      'sha_in' => '',
      'sha_out' => '',
      'charset' => static::CHARSET_UTF_8,
      'hash_algorithm' => static::HASH_SHA1,
      'node_catalog' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState) {
    $form = parent::buildConfigurationForm($form, $formState);

    $form['psp_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PSPID'),
      '#default_value' => $this->configuration['psp_id'],
      '#required' => TRUE,
    ];

    $form['sha_in'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SHA-IN passphrase'),
      '#description' => $this->t('The passphrase used to calculate the SHA signature for the payment request. Must be equal with the SHA-IN passphrase in the Postfinance backend.'),
      '#default_value' => $this->configuration['sha_in'],
      '#required' => TRUE,
    ];

    $form['sha_out'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SHA-OUT passphrase'),
      '#description' => $this->t('The passphrase used to calculate the SHA signature for the payment response. Must be equal with the SHA-OUT passphrase in the Postfinance backend.'),
      '#default_value' => $this->configuration['sha_out'],
      '#required' => TRUE,
    ];

    $form['hash_algorithm'] = [
      '#type' => 'radios',
      '#title' => $this->t('Hashing algorithm'),
      '#description' => $this->t('The hashing algorithm used for the SHA-IN and SHA-OUT signatures. Must correspond to the algorithm selected in the Postfinance backend.'),
      '#options' => [
        static::HASH_SHA1 => 'SHA-1',
        static::HASH_SHA256 => 'SHA-256',
        static::HASH_SHA512 => 'SHA-512',
      ],
      '#default_value' => $this->configuration['hash_algorithm'],
      '#required' => TRUE,
    ];

    $form['charset'] = [
      '#type' => 'radios',
      '#title' => $this->t('Charset'),
      '#options' => [
        static::CHARSET_UTF_8 => 'UTF-8',
        static::CHARSET_ISO_8859_1 => 'ISO 8859 1',
      ],
      '#default_value' => $this->configuration['charset'],
      '#required' => TRUE,
    ];

    $nodeCatalog = '';
    if ($this->configuration['node_catalog']) {
      $nodeCatalog = $this->entityTypeManager->getStorage('node')
        ->load($this->configuration['node_catalog']);
    }
    $form['node_catalog'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => $this->t('Catalog url'),
      '#description' => $this->t('Select a node representing the catalog page.'),
      '#default_value' => ($nodeCatalog) ?: '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $formState) {
    parent::submitConfigurationForm($form, $formState);

    $values = $formState->getValue($form['#parents']);

    $this->configuration['psp_id'] = $values['psp_id'];
    $this->configuration['sha_in'] = $values['sha_in'];
    $this->configuration['sha_out'] = $values['sha_out'];
    $this->configuration['hash_algorithm'] = $values['hash_algorithm'];
    $this->configuration['charset'] = $values['charset'];
    $this->configuration['node_catalog'] = $values['node_catalog'];
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    parent::onReturn($order, $request);

    $paymentResponseService = $this->getPaymentResponseService();
    $paymentResponseService->onReturn($order, $request);
  }

  /**
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request) {
    parent::onCancel($order, $request);

    $paymentResponseService = $this->getPaymentResponseService();
    $paymentResponseService->onCancel($order, $request);
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    parent::onNotify($request);

    $parameters = ($request->getMethod() === 'GET') ? $request->query : $request->request;

    // Note: We always return a 200 response, also in case of errors.
    // Otherwise Postfinance performs this request multiple times.
    if (!$parameters->has('orderID')) {
      $this->logger()->warning('Received post-payment request with missing orderID parameter. Parameters: %params', [
        '%params' => json_encode($parameters->all()),
      ]);
      return new Response();
    }

    $order = $this->getOrderByRemoteOrderId($parameters->get('orderID'));

    if (!$order) {
      $this->logger()->warning('Received post-payment request which could not be mapped to an order. Parameters: %params', [
        '%params' => json_encode($parameters->all()),
      ]);
      return new Response();
    }

    try {
      $paymentResponseService = $this->getPaymentResponseService();
      $paymentResponseService->onReturn($order, $request);

      return new Response();
    }
    catch (\Exception $e) {
    }

    return new Response();
  }

  /**
   * Get the payment gateway entity ID.
   *
   * @return string
   *   The entity ID.
   */
  public function getEntityId() {
    return $this->entityId;
  }

  /**
   * Return an instance of the PaymentResponseService.
   *
   * @return \Drupal\commerce_postfinance\PaymentResponseService
   *   The payment response service.
   */
  private function getPaymentResponseService() {
    return new PaymentResponseService(
      $this,
      new OrderIdMappingService(),
      $this->entityTypeManager,
      $this->logger(),
      $this->eventDispatcher
    );
  }

  /**
   * Get the commerce order from a given remote order ID.
   *
   * @param string $remoteOrderId
   *   Remote order ID from Postfinance.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The commerce order.
   */
  private function getOrderByRemoteOrderId($remoteOrderId) {
    $orderIdMappingService = new OrderIdMappingService();
    $orderId = $orderIdMappingService->getOrderIdFromRemoteOrderId($remoteOrderId);

    try {
      return $this->entityTypeManager
        ->getStorage('commerce_order')
        ->load($orderId);
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Get the logger channel for this module.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The logger channel.
   */
  private function logger() {
    return $this->loggerChannelFactory->get('commerce_postfinance');
  }

}

<?php

namespace Drupal\commerce_postfinance;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_postfinance\Event\PaymentRequestEvent;
use Drupal\commerce_postfinance\Event\PostfinanceEvents;
use Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use whatwedo\PostFinanceEPayment\Order\Order as PostfinanceOrder;
use whatwedo\PostFinanceEPayment\Client\Client;
use whatwedo\PostFinanceEPayment\Environment\ProductionEnvironment;
use whatwedo\PostFinanceEPayment\Environment\TestEnvironment;
use whatwedo\PostFinanceEPayment\PostFinanceEPayment;

/**
 * Service collecting data required for the payment request to Postfinance.
 */
class PaymentRequestService {

  /**
   * The configuration data from the plugin.
   *
   * @var array
   */
  private $pluginConfiguration;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * The order ID mapping service.
   *
   * @var \Drupal\commerce_postfinance\OrderIdMappingService
   */
  private $orderIdMappingService;

  /**
   * The url generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  private $urlGenerator;

  /**
   * Drupal's language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private $languageManager;

  /**
   * PaymentRequestService constructor.
   *
   * @param array $pluginConfiguration
   *   Configuration data from the plugin.
   * @param \Drupal\commerce_postfinance\OrderIdMappingService $orderIdMappingService
   *   The order ID mapping service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   *   The url generator service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Drupal's language manager.
   */
  public function __construct(array $pluginConfiguration,
                              OrderIdMappingService $orderIdMappingService,
                              EventDispatcherInterface $eventDispatcher,
                              UrlGeneratorInterface $urlGenerator,
                              LanguageManagerInterface $languageManager
  ) {
    $this->pluginConfiguration = $pluginConfiguration;
    $this->eventDispatcher = $eventDispatcher;
    $this->orderIdMappingService = $orderIdMappingService;
    $this->urlGenerator = $urlGenerator;
    $this->languageManager = $languageManager;
  }

  /**
   * Returns the parameters to be sent to Postfinance for the given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   * @param array $form
   *   Form data from the payment offsite form.
   *
   * @return array
   *   The payment parameters.
   */
  public function getParameters(OrderInterface $order, array $form) {
    try {
      $environment = $this->setupEnvironment();
      $environment
        ->setAcceptUrl($form['#return_url'])
        ->setCancelUrl($form['#cancel_url'])
        ->setExceptionUrl($form['#exception_url'])
        ->setDeclineUrl($form['#return_url']);

      $orderPostfinance = $this->setupPostfinanceOrder($order);
      $clientPostfinance = $this->setupPostfinanceClient($order);

      $ePayment = new PostFinanceEPayment($environment);

      // Event listeners may include additional parameters for the request.
      $event = new PaymentRequestEvent($order);
      $this->eventDispatcher->dispatch(PostfinanceEvents::PAYMENT_REQUEST, $event);
      $additionalParameters = $event->getParameters();

      $payment = $ePayment->createPayment($clientPostfinance, $orderPostfinance, $additionalParameters);
      $parameters = $payment->getForm()->getHiddenFields();

      return $parameters;
    }
    catch (\Exception $e) {
      throw new PaymentGatewayException($e->getMessage());
    }
  }

  /**
   * Returns the redirect url from Postfinance.
   *
   * @return string
   *   The redirect url.
   */
  public function getRedirectUrl() {
    $config = $this->pluginConfiguration;
    $env = $config['mode'] === 'live' ? 'prod' : 'test';
    $endpoint = $config['charset'] === RedirectCheckout::CHARSET_UTF_8 ? 'orderstandard_utf8.asp' : 'orderstandard.asp';

    return "https://e-payment.postfinance.ch/ncol/$env/$endpoint";
  }

  /**
   * Setup the Postfinance production or test environment depending on config.
   *
   * @return \whatwedo\PostFinanceEPayment\Environment\Environment
   *   The EnvironmentInterface.
   */
  protected function setupEnvironment() {
    $config = $this->pluginConfiguration;

    if ($config['mode'] === 'live') {
      $environment = new ProductionEnvironment($config['psp_id'], $config['sha_in'], $config['sha_out']);
    }
    else {
      $environment = new TestEnvironment($config['psp_id'], $config['sha_in'], $config['sha_out']);
    }

    $catalogUrl = $config['node_catalog'] ? $this->urlGenerator->generateFromRoute('entity.node.canonical', ['node' => $config['node_catalog']], ['absolute' => TRUE]) : '';

    return $environment
      ->setCharset($config['charset'])
      ->setHashAlgorithm($config['hash_algorithm'])
      ->setCatalogUrl($catalogUrl)
      ->setHomeUrl($this->urlGenerator->generateFromRoute('<front>', [], ['absolute' => TRUE]));
  }

  /**
   * Setup the Postfinance order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   *
   * @return \whatwedo\PostFinanceEPayment\Order\Order
   *   A postfinance order.
   */
  protected function setupPostfinanceOrder(OrderInterface $order) {
    $orderPostfinance = new PostfinanceOrder();

    return $orderPostfinance
      ->setId($this->orderIdMappingService->getRemoteOrderId($order))
      ->setCurrency($order->getTotalPrice()->getCurrencyCode())
      ->setAmount((float) $order->getTotalPrice()->getNumber());
  }

  /**
   * Setup the Postfinance client.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *
   * @return \whatwedo\PostFinanceEPayment\Client\Client
   *   The postfinance client.
   */
  protected function setupPostfinanceClient(OrderInterface $order) {
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $order->getBillingProfile()->get('address')->first();
    $language = $this->languageManager->getCurrentLanguage();
    $languageCode = sprintf('%s_%s', $language->getId(), strtoupper($language->getId()));

    $client = new Client();

    return $client
      ->setName(sprintf('%s %s', $address->getGivenName(), $address->getFamilyName()))
      ->setAddress($address->getAddressLine1())
      ->setZip($address->getPostalCode())
      ->setTown($address->getLocality())
      ->setCountry($address->getCountryCode())
      ->setLocale($languageCode)
      ->setEmail($order->getEmail());
  }

}

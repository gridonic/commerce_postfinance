<?php

namespace Drupal\commerce_postfinance;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_postfinance\Event\PaymentRequestEvent;
use Drupal\commerce_postfinance\Event\PostfinanceEvents;
use Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use whatwedo\PostFinanceEPayment\Order\Order as PostfinanceOrder;
use whatwedo\PostFinanceEPayment\Client\Client;
use whatwedo\PostFinanceEPayment\Environment\ProductionEnvironment;
use whatwedo\PostFinanceEPayment\Environment\TestEnvironment;
use whatwedo\PostFinanceEPayment\PostFinanceEPayment;

/**
 * Provides data required for the payment POST request to Postfinance.
 *
 * @package Drupal\commerce_postfinance
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
   * PaymentRequestService constructor.
   *
   * @param array $pluginConfiguration
   *   Configuration data from the plugin.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(array $pluginConfiguration, EventDispatcherInterface $eventDispatcher) {
    $this->pluginConfiguration = $pluginConfiguration;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Returns the parameters to be sent to Postfinance for the given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   * @param string $languageCode
   *   The language code in the format en_US, de_DE, fr_FR.
   * @param array $urls
   *   An array containing the return, cancel and exception URLs.
   *
   * @return array
   *   The parameters.
   */
  public function getParameters(OrderInterface $order, $languageCode, array $urls) {
    try {
      if (!isset($urls['return'])) {
        throw new PaymentGatewayException("Return URL missing");
      }
      if (!isset($urls['cancel'])) {
        throw new PaymentGatewayException("Cancel URL missing");
      }
      if (!isset($urls['exception'])) {
        throw new PaymentGatewayException("Exception URL missing");
      }
      $environment = $this->setupEnvironment($urls);
      $orderPostfinance = $this->setupPostfinanceOrder($order);
      $client = $this->setupClient($order, $languageCode);
      $ePayment = new PostFinanceEPayment($environment);

      // Event listeners may include additional parameters for the request.
      $event = new PaymentRequestEvent($order);
      $this->eventDispatcher->dispatch(PostfinanceEvents::PAYMENT_REQUEST, $event);
      $additionalParameters = $event->getParameters();
      $payment = $ePayment->createPayment($client, $orderPostfinance, $additionalParameters);
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
   * @param array $urls
   *   The return, cancel and exception URLs.
   *
   * @return \whatwedo\PostFinanceEPayment\Environment\Environment
   *   The EnvironmentInterface.
   */
  protected function setupEnvironment(array $urls) {
    $config = $this->pluginConfiguration;
    if ($config['mode'] === 'live') {
      $environment = new ProductionEnvironment($config['psp_id'], $config['sha_in'], $config['sha_out']);
    }
    else {
      $environment = new TestEnvironment($config['psp_id'], $config['sha_in'], $config['sha_out']);
    }
    $environment->setCharset($config['charset']);
    $environment->setHashAlgorithm($config['hash_algorithm']);
    $environment->setCatalogUrl($config['catalog_url']);
    $environment->setAcceptUrl($urls['return']);
    $environment->setCancelUrl($urls['cancel']);
    $environment->setExceptionUrl($urls['exception']);
    $environment->setDeclineUrl($urls['return']);
    return $environment;
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
    $orderPostfinance->setId($order->id())
      ->setCurrency($order->getTotalPrice()->getCurrencyCode())
      ->setAmount((float) $order->getTotalPrice()->getNumber());
    return $orderPostfinance;
  }

  /**
   * Setup the Postfinance client.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   * @param string $languageCode
   *   A language code.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *
   * @return \whatwedo\PostFinanceEPayment\Client\Client
   *   The postfinance client.
   */
  protected function setupClient(OrderInterface $order, $languageCode) {
    $client = new Client();
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $order->getBillingProfile()->get('address')->first();
    $client->setName(sprintf('%s %s', $address->getGivenName(), $address->getFamilyName()))
      ->setAddress($address->getAddressLine1())
      ->setTown(sprintf('%s %s', $address->getPostalCode(), $address->getLocality()))
      ->setCountry($address->getCountryCode())
      ->setLocale($languageCode)
      ->setEmail($order->getEmail());
    return $client;
  }

}

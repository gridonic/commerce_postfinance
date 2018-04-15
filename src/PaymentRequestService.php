<?php

namespace Drupal\commerce_postfinance;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\Core\Language\LanguageManagerInterface;
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
  protected $pluginConfiguration;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private $languageManager;

  /**
   * PaymentRequestData constructor.
   *
   * @param array $pluginConfiguration
   *   Configuration data from the plugin.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(array $pluginConfiguration, LanguageManagerInterface $languageManager) {
    $this->pluginConfiguration = $pluginConfiguration;
    $this->languageManager = $languageManager;
  }

  /**
   * Returns the parameters to be sent to Postfinance for the given order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   *
   * @return array
   *   The parameters.
   */
  public function getParameters(OrderInterface $order) {
    try {
      $postfinanceOrder = new PostfinanceOrder();
      $postfinanceOrder->setId($order->id())
        ->setCurrency($order->getTotalPrice()->getCurrencyCode())
        ->setAmount($order->getTotalPrice()->getNumber());

      $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();
      $locale = sprintf('%s_%s', $currentLanguage, strtoupper($currentLanguage));

      $client = new Client();
      /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
      $address = $order->getBillingProfile()->get('address')->first();
      $client->setName(sprintf('%s %s', $address->getGivenName(), $address->getFamilyName()))
        ->setAddress($address->getAddressLine1())
        ->setTown(sprintf('%s %s', $address->getPostalCode(), $address->getLocality()))
        ->setCountry($address->getCountryCode())
        ->setLocale($locale)
        ->setEmail($order->getEmail());

      $environment = $this->getEnvironment();
      $ePayment = new PostFinanceEPayment($environment);
      // TODO: Dispatch event to collect additional parameters.
      $additionalParameters = [];
      $payment = $ePayment->createPayment($client, $postfinanceOrder, $additionalParameters);

      return $payment->getForm()->getHiddenFields();
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
    return $this->getEnvironment()->getGatewayUrl();
  }

  /**
   * Get the Postfinance production or test environment depending on config.
   *
   * @return \whatwedo\PostFinanceEPayment\Environment\EnvironmentInterface
   *   The EnvironmentInterface.
   */
  protected function getEnvironment() {
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
    return $environment;
  }

}

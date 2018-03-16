<?php

namespace Drupal\commerce_postfinance;

use Drupal\commerce_order\Entity\OrderInterface;
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
class PaymentRequestData implements PaymentRequestDataInterface {

  /**
   * The configuration that from the plugin.
   *
   * @var array
   */
  protected $pluginConfiguration;

  /**
   * PaymentRequestData constructor.
   *
   * @param array $pluginConfiguration
   *   Configuration data from the plugin.
   */
  public function __construct(array $pluginConfiguration) {
    $this->pluginConfiguration = $pluginConfiguration;
  }

  /**
   * {@inheritdoc}
   */
  public function getParameters(OrderInterface $order) {
    $postfinanceOrder = new PostfinanceOrder();
    $postfinanceOrder->setId($order->id())
      ->setCurrency($order->getTotalPrice()->getCurrencyCode())
      ->setAmount($order->getTotalPrice()->getNumber());

    $client = new Client();
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $order->getBillingProfile()->get('address')->first();
    $client->setName(sprintf('%s %s', $address->getGivenName(), $address->getFamilyName()))
      ->setAddress($address->getAddressLine1())
      ->setTown(sprintf('%s %s', $address->getPostalCode(), $address->getLocality()))
      ->setCountry($address->getCountryCode())
      ->setEmail($order->getEmail());

    $environment = $this->getEnvironment();
    $ePayment = new PostFinanceEPayment($environment);
    $payment = $ePayment->createPayment($client, $postfinanceOrder);

    return $payment->getForm()->getHiddenFields();
  }

  /**
   * {@inheritdoc}
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

    return $environment;
  }

}

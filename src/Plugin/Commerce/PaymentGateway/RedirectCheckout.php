<?php

namespace Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_postfinance\PaymentResponseService;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Postfinance offsite payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "postfinance_redirect_checkout",
 *   label = @Translation("Postfinance (Redirect to Postfinance)"),
 *   display_label = @Translation("Postfinance"),
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_postfinance\PluginForm\RedirectCheckoutForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "mastercard", "visa",
 *   },
 * )
 */
class RedirectCheckout extends OffsitePaymentGatewayBase {

  const CHARSET_ISO_8859_1 = 'iso_8859-1';
  const CHARSET_UTF_8 = 'utf-8';

  const HASH_SHA1 = 'sha1';
  const HASH_SHA256 = 'sha256';
  const HASH_SHA512 = 'sha512';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'psp_id' => '',
      'sha_in' => '',
      'sha_out' => '',
      'charset' => static::CHARSET_ISO_8859_1,
      'hash_algorithm' => static::HASH_SHA1,
      'catalog_url' => '',
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
      '#description' => $this->t('The passphrase used to calculate the SHA digest for the payment request. Must be equal with the SHA-IN passphrase in the Postfinance backend.'),
      '#default_value' => $this->configuration['sha_in'],
      '#required' => TRUE,
    ];

    $form['sha_out'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SHA-OUT passphrase'),
      '#description' => $this->t('The passphrase used to calculate the SHA digest for the payment response. Must be equal with the SHA-OUT passphrase in the Postfinance backend.'),
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
        static::CHARSET_ISO_8859_1 => 'ISO 8859 1',
        static::CHARSET_UTF_8 => 'UTF-8',
      ],
      '#default_value' => $this->configuration['charset'],
      '#required' => TRUE,
    ];

    $form['catalog_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Catalog url'),
      '#description' => $this->t('Enter an absolute url to your catalog page.'),
      '#default_value' => $this->configuration['catalog_url'],
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
    $this->configuration['catalog_url'] = $values['catalog_url'];
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $paymentResponseService = new PaymentResponseService($this->entityId, $this->configuration, $this->entityTypeManager);
    $paymentResponseService->onReturn($order, $request);
  }

}

<?php

namespace Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Postfinance offsite payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "postfinance_redirect_checkout",
 *   label = @Translation("Postfinance (Redirect to Postfinance)"),
 *   display_label = @Translation("Postfinance"),
 *    forms = {
 *     "offsite-payment" =
 *   "Drupal\commerce_postfinance\PluginForm\RedirectCheckoutForm",
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
      '#title' => $this->t('SHA-IN signature'),
      '#default_value' => $this->configuration['sha_in'],
      '#required' => TRUE,
    ];

    $form['sha_out'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SHA-OUT signature'),
      '#default_value' => $this->configuration['sha_out'],
      '#required' => TRUE,
    ];

    $form['hash_algorithm'] = [
      '#type' => 'radios',
      '#title' => $this->t('Hashing algorithm'),
      '#description' => $this->t('The hashing algorithm used for the SHA-IN and SHA-OUT signatures. This must correspond to the algorithm selected in the Postfinance backend.'),
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
  }

}

<?php

namespace Drupal\commerce_postfinance\PluginForm;

use Drupal;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\commerce_postfinance\OrderNumberService;
use Drupal\commerce_postfinance\PaymentRequestService;
use Drupal\Core\Form\FormStateInterface;

/**
 * A form redirecting to the Postfinance payment endpoint.
 *
 * @package Drupal\commerce_postfinance\PluginForm
 */
class RedirectCheckoutForm extends PaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState) {
    $form = parent::buildConfigurationForm($form, $formState);

    /* @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $order = $payment->getOrder();

    /* @var \Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout $redirectCheckout */
    $redirectCheckout = $payment->getPaymentGateway()->getPlugin();
    $pluginConfiguration = $redirectCheckout->getConfiguration();

    $paymentRequestService = new PaymentRequestService(
      $pluginConfiguration,
      new OrderNumberService(),
      Drupal::service('event_dispatcher'),
      Drupal::service('url_generator')
    );

    $language = Drupal::service('language_manager')->getCurrentLanguage();
    $languageCode = sprintf('%s_%s', $language->getId(), strtoupper($language->getId()));

    $urls = [
      'return' => $form['#return_url'],
      'cancel' => $form['#cancel_url'],
      'exception' => $form['#exception_url'],
    ];

    $parameters = $paymentRequestService->getParameters($order, $languageCode, $urls);

    return $this->buildRedirectForm(
      $form,
      $formState,
      $paymentRequestService->getRedirectUrl(),
      $parameters,
      PaymentOffsiteForm::REDIRECT_POST
    );
  }

}

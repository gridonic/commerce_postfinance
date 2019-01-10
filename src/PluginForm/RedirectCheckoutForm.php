<?php

namespace Drupal\commerce_postfinance\PluginForm;

use Drupal;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\commerce_postfinance\OrderIdMappingService;
use Drupal\commerce_postfinance\PaymentRequestService;
use Drupal\Core\Form\FormStateInterface;

/**
 * A form redirecting to the Postfinance payment endpoint.
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
      new OrderIdMappingService(),
      Drupal::service('event_dispatcher'),
      Drupal::service('url_generator'),
      Drupal::service('language_manager')
    );

    $parameters = $paymentRequestService->getParameters($order, $form);

    return $this->buildRedirectForm(
      $form,
      $formState,
      $paymentRequestService->getRedirectUrl(),
      $parameters,
      PaymentOffsiteForm::REDIRECT_POST
    );
  }

}

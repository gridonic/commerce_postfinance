<?php

namespace Drupal\commerce_postfinance\PluginForm;

use Drupal;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\commerce_postfinance\PaymentRequestService;
use Drupal\Core\Form\FormStateInterface;
use whatwedo\PostFinanceEPayment\Model\Parameter;

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

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $order = $payment->getOrder();

    /** @var \Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout $redirectCheckout */
    $redirectCheckout = $payment->getPaymentGateway()->getPlugin();
    $pluginConfiguration = $redirectCheckout->getConfiguration();
    $paymentRequestService = new PaymentRequestService($pluginConfiguration, Drupal::service('language_manager'));

    $parameters = array_merge(
      $paymentRequestService->getParameters($order),
      [
        Parameter::ACCEPT_URL => $form['#return_url'],
        Parameter::CANCEL_URL => $form['#cancel_url'],
        Parameter::EXCEPTION_URL => $form['#exception_url'],
        Parameter::DECLINE_URL => $form['#return_url'],
      ]
    );

    return $this->buildRedirectForm(
      $form,
      $formState,
      $paymentRequestService->getRedirectUrl(),
      $parameters,
      PaymentOffsiteForm::REDIRECT_POST
    );
  }

}

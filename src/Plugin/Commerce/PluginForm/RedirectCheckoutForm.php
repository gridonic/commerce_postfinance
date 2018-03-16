<?php

namespace Drupal\commerce_postfinance\PluginForm;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\commerce_postfinance\PaymentRequestData;
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

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $order = $payment->getOrder();

    /** @var \Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout $redirectCheckout */
    $redirectCheckout = $payment->getPaymentGateway()->getPlugin();
    $pluginConfiguration = $redirectCheckout->getConfiguration();

    try {
      $paymentRequestData = new PaymentRequestData($pluginConfiguration);
    }
    catch (\Exception $e) {
      throw new PaymentGatewayException($e->getMessage());
    }

    return $this->buildRedirectForm(
      $form,
      $formState,
      $paymentRequestData->getRedirectUrl(),
      $paymentRequestData->getParameters($order),
      PaymentOffsiteForm::REDIRECT_POST
    );
  }

}

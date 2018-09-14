<?php

namespace Drupal\Tests\commerce_postfinance\Unit;

// @codingStandardsIgnoreFile

use CommerceGuys\Addressing\Address;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_postfinance\Event\PaymentRequestEvent;
use Drupal\commerce_postfinance\Event\PostfinanceEvents;
use Drupal\commerce_postfinance\OrderIdMappingService;
use Drupal\commerce_postfinance\PaymentRequestService;

use Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout;
use Drupal\commerce_price\Price;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use whatwedo\PostFinanceEPayment\Model\Parameter;

/**
 * @package Drupal\Tests\commerce_postfinance\Unit
 * @group commerce_postfinance
 * @coversDefaultClass Drupal\commerce_postfinance\PaymentRequestService
 */
class PaymentRequestServiceTest extends UnitTestCase {

  /**
   * @var PaymentRequestService
   */
  private $paymentRequestService;

  public function testRedirectUrl_DifferentUrlBasedOnConfig_CorrectUrl() {
    $this->assertEquals('https://e-payment.postfinance.ch/ncol/test/orderstandard_utf8.asp', $this->getPaymentRequestService()->getRedirectUrl());

    $paymentRequestService = $this->getPaymentRequestService(function(&$config) {
      $config['mode'] = 'live';
    });
    $this->assertEquals('https://e-payment.postfinance.ch/ncol/prod/orderstandard_utf8.asp', $paymentRequestService->getRedirectUrl());

    $paymentRequestService = $this->getPaymentRequestService(function(&$config) {
      $config['charset'] = RedirectCheckout::CHARSET_ISO_8859_1;
    });
    $this->assertEquals('https://e-payment.postfinance.ch/ncol/test/orderstandard.asp', $paymentRequestService->getRedirectUrl());

    $paymentRequestService = $this->getPaymentRequestService(function(&$config) {
      $config['charset'] = RedirectCheckout::CHARSET_ISO_8859_1;
      $config['mode'] = 'live';
    });
    $this->assertEquals('https://e-payment.postfinance.ch/ncol/prod/orderstandard.asp', $paymentRequestService->getRedirectUrl());
  }

  public function testParameters_AmountAndCurrencyFromOrder_CorrectParameters() {
    $order = $this->getOrderMock(1, new Price('100', 'CHF'));
    $parameters = $this->paymentRequestService->getParameters($order, $this->getOffsitePaymentFormData());
    $this->assertEquals(10000, $parameters[Parameter::AMOUNT]);
    $this->assertEquals('CHF', $parameters[Parameter::CURRENCY]);

    $order = $this->getOrderMock(1, new Price('15.95', 'USD'));
    $parameters = $this->paymentRequestService->getParameters($order, $this->getOffsitePaymentFormData());
    $this->assertEquals(1595, $parameters[Parameter::AMOUNT]);
    $this->assertEquals('USD', $parameters[Parameter::CURRENCY]);

    $order = $this->getOrderMock(1, new Price('0.1', 'EUR'));
    $parameters = $this->paymentRequestService->getParameters($order, $this->getOffsitePaymentFormData());
    $this->assertEquals(10, $parameters[Parameter::AMOUNT]);
    $this->assertEquals('EUR', $parameters[Parameter::CURRENCY]);

    $order = $this->getOrderMock(1, new Price('20.99', 'USD'));
    $parameters = $this->paymentRequestService->getParameters($order, $this->getOffsitePaymentFormData());
    $this->assertEquals(2099, $parameters[Parameter::AMOUNT]);
  }

  public function testParameters_CustomerRelatedParameters_CorrectParameters() {
    $order = $this->getOrderMock(1, NULL, 'john.doe@example.com', $this->getAddressData());
    $parameters = $this->paymentRequestService->getParameters($order, $this->getOffsitePaymentFormData());

    $this->assertEquals('John Doe', $parameters[Parameter::CLIENT_NAME]);
    $this->assertEquals('Aarbergergasse 40', $parameters[Parameter::CLIENT_ADDRESS]);
    $this->assertEquals('3000', $parameters[Parameter::CLIENT_ZIP]);
    $this->assertEquals('Bern', $parameters[Parameter::CLIENT_TOWN]);
    $this->assertEquals('CH', $parameters[Parameter::CLIENT_COUNTRY]);
    $this->assertEquals('john.doe@example.com', $parameters[Parameter::CLIENT_EMAIL]);
  }

  public function testParameters_GeneralParameters_CorrectParameters() {
    $paymentRequestService = $this->getPaymentRequestService(function ($config, $orderIdMappingServiceMock, $eventDispatcherMock, $urlGeneratorMock, $languageManagerMock) {
      $orderIdMappingServiceMock
        ->method('getRemoteOrderId')
        ->willReturn(2467);

      $urlGeneratorMock
        ->method('generateFromRoute')
        ->willReturn('https://gridonic.ch');
    });

    $order = $this->getOrderMock(2467, new Price('20', 'CHF'), 'john.doe@example.com', []);
    $parameters = $paymentRequestService->getParameters($order, $this->getOffsitePaymentFormData());

    $this->assertEquals('Gridonic_TEST', $parameters[Parameter::PSPID]);
    $this->assertEquals(2467, $parameters[Parameter::ORDER_ID]);
    $this->assertEquals('en_EN', $parameters[Parameter::LANGUAGE]);
    $this->assertEquals('https://gridonic.ch', $parameters[Parameter::CATALOG_URL]);
    $this->assertEquals('https://gridonic.ch', $parameters[Parameter::HOME_URL]);
    $this->assertEquals('/url/return', $parameters[Parameter::ACCEPT_URL]);
    $this->assertEquals('/url/return', $parameters[Parameter::DECLINE_URL]);
    $this->assertEquals('/url/cancel', $parameters[Parameter::CANCEL_URL]);
    $this->assertEquals('/url/exception', $parameters[Parameter::EXCEPTION_URL]);
    $this->assertEquals('john.doe@example.com', $parameters[Parameter::CLIENT_EMAIL]);
    $this->assertArrayHasKey(Parameter::SIGNATURE, $parameters);
  }

  public function testParameters_DispatchEventToCollectAdditionalParameters_EventDispatcherCalled() {
    $paymentRequestService = $this->getPaymentRequestService(function($config, $orderIdMappingServiceMock, $eventDispatcherMock, $urlGeneratorMock, $languageManagerMock) {
      $eventDispatcherMock
        ->expects($this->once())
        ->method('dispatch')
        ->with(PostfinanceEvents::PAYMENT_REQUEST, $this->isInstanceOf(PaymentRequestEvent::class));
    });

    $paymentRequestService->getParameters($this->getOrderMock(), $this->getOffsitePaymentFormData());
  }

  protected function setUp() {
    $this->paymentRequestService = $this->getPaymentRequestService();
  }

  /**
   * Get an instance of the PaymentRequestService object with mocked dependencies.
   *
   * @param null $dependencyManipulator
   *   Closure receiving the dependency mocks as parameter.
   *
   * @return \Drupal\commerce_postfinance\PaymentRequestService
   */
  protected function getPaymentRequestService($dependencyManipulator = NULL) {
    $config = $this->getPluginConfiguration();
    $orderIdMappingServiceMock = $this->createMock(OrderIdMappingService::class);
    $eventDispatcherMock = $this->createMock(EventDispatcher::class);

    $urlGeneratorMock = $this->createMock(UrlGeneratorInterface::class);

    $languageManagerMock = $this->createMock(LanguageManagerInterface::class);
    $languageManagerMock
      ->method('getCurrentLanguage')
      ->willReturn(new Language(['id' => 'en']));

    if (is_callable($dependencyManipulator)) {
      $dependencyManipulator($config, $orderIdMappingServiceMock, $eventDispatcherMock, $urlGeneratorMock, $languageManagerMock);
    }

    return new PaymentRequestService($config, $orderIdMappingServiceMock, $eventDispatcherMock, $urlGeneratorMock, $languageManagerMock);
  }

  protected function getPluginConfiguration(array $config = []) {
    return array_merge([
      'psp_id' => 'Gridonic_TEST',
      'sha_in' => 'S3cr3t!',
      'sha_out' => 'S3cr3t!',
      'mode' => 'test',
      'charset' => 'utf-8',
      'hash_algorithm' => 'sha1',
      'node_catalog' => '1',
    ], $config);
  }

  protected function getAddressData(array $data = []) {
    return array_merge([
      'country_code' => 'CH',
      'locality' => 'Bern',
      'postal_code' => '3000',
      'address_line1' => 'Aarbergergasse 40',
      'given_name' => 'John',
      'family_name' => 'Doe',
    ], $data);
  }

  protected function getOffsitePaymentFormData() {
    return [
      '#return_url' => '/url/return',
      '#cancel_url' => '/url/cancel',
      '#exception_url' => '/url/exception',
    ];
  }

  protected function getOrderMock($orderId = 1, Price $price = NULL, $email = 'john.doe@example.com', array $addressData = []) {
    $price = $price === NULL ? new Price('20', 'CHF') : $price;
    $addressData = count($addressData) ? $addressData : $this->getAddressData();

    $address = new Address();
    $address = $address
      ->withCountryCode($addressData['country_code'])
      ->withLocality($addressData['locality'])
      ->withPostalCode($addressData['postal_code'])
      ->withAddressLine1($addressData['address_line1'])
      ->withGivenName($addressData['given_name'])
      ->withFamilyName($addressData['family_name']);

    $addressList = $this->createMock(FieldItemListInterface::class);
    $addressList
      ->method('first')
      ->willReturn($address);

    $billingProfile = $this->createMock(ProfileInterface::class);
    $billingProfile
      ->method('get')
      ->willReturn($addressList);

    $order = $this->createMock(OrderInterface::class);

    $order
      ->method('id')
      ->willReturn($orderId);

    $order
      ->method('getTotalPrice')
      ->willReturn($price);

    $order
      ->method('getBillingProfile')
      ->willReturn($billingProfile);

    $order
      ->method('getEmail')
      ->willReturn($email);

    return $order;
  }
}

<?php

namespace Drupal\Tests\commerce_postfinance\Unit;

// @codingStandardsIgnoreFile

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Exception\InvalidResponseException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentStorageInterface;
use Drupal\commerce_postfinance\Event\PaymentResponseEvent;
use Drupal\commerce_postfinance\Event\PostfinanceEvents;
use Drupal\commerce_postfinance\OrderIdMappingService;
use Drupal\commerce_postfinance\PaymentResponseService;
use Drupal\commerce_postfinance\Plugin\Commerce\PaymentGateway\RedirectCheckout;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use whatwedo\PostFinanceEPayment\Model\Parameter;

/**
 * @package Drupal\Tests\commerce_postfinance\Unit
 * @group commerce_postfinance
 * @coversDefaultClass Drupal\commerce_postfinance\PaymentResponseService
 */
class PaymentResponseServiceTest extends UnitTestCase {

  /**
   * @var PaymentResponseService
   */
  private $paymentResponseService;

  /**
   * @dataProvider successStatusCodesDataProvider
   */
  public function testOnReturn_PaymentSuccessful_PaymentGetsCreated($statusCodeSuccess) {
    $paymentResponseService = $this->getPaymentResponseService(function ($redirectCheckoutMock, $orderIdMappingServiceMock, $entityTypeManagerMock, $loggerChannelMock, $eventDispatcherMock) {
      $paymentEntityMock = $this->createMock(Payment::class);
      $paymentEntityMock
        ->expects($this->once())
        ->method('save');

      $entityStorageMock = $this->createMock(PaymentStorageInterface::class);
      $entityStorageMock
        ->expects($this->once())
        ->method('loadByRemoteId')
        ->willReturn($paymentEntityMock);

      $entityTypeManagerMock
        ->expects($this->once())
        ->method('getStorage')
        ->willReturn($entityStorageMock);
    });

    $parameters = $this->getPaymentParameters();
    $config = $this->getPluginConfiguration();
    $parameters[Parameter::STATUS] = $statusCodeSuccess;
    $parameters[Parameter::SIGNATURE] = $this->calculateSignature($parameters, $config['sha_out'], $config['hash_algorithm']);

    $paymentResponseService->onReturn($this->getOrderMock(), $this->getRequestMock($parameters));
  }

  /**
   * @dataProvider partiallySuccessStatusCodesDataProvider
   */
  public function testOnReturn_PaymentPartiallySuccessful_PaymentGetsCreated($statusCodePartialSuccess) {
    $paymentResponseService = $this->getPaymentResponseService(function ($redirectCheckoutMock, $orderIdMappingServiceMock, $entityTypeManagerMock, $loggerChannelMock, $eventDispatcherMock) {
      $paymentEntityMock = $this->createMock(Payment::class);
      $paymentEntityMock
        ->expects($this->once())
        ->method('save');

      $entityStorageMock = $this->createMock(PaymentStorageInterface::class);
      $entityStorageMock
        ->expects($this->once())
        ->method('loadByRemoteId')
        ->willReturn($paymentEntityMock);

      $entityTypeManagerMock
        ->expects($this->once())
        ->method('getStorage')
        ->willReturn($entityStorageMock);

      $eventDispatcherMock
        ->expects($this->once())
        ->method('dispatch')
        ->with(PostfinanceEvents::PAYMENT_RESPONSE, $this->isInstanceOf(PaymentResponseEvent::class));
    });

    $parameters = $this->getPaymentParameters();
    $config = $this->getPluginConfiguration();
    $parameters[Parameter::STATUS] = $statusCodePartialSuccess;
    $parameters[Parameter::SIGNATURE] = $this->calculateSignature($parameters, $config['sha_out'], $config['hash_algorithm']);

    $paymentResponseService->onReturn($this->getOrderMock(), $this->getRequestMock($parameters));
  }

  /**
   * @dataProvider errorStatusCodesDataProvider
   */
  public function testOnReturn_PaymentErrorOrDeclined_RemoteOrderIdMinorGetsIncreasedAndNoPaymentIsCreated($statusCodeError) {
    $paymentResponseService = $this->getPaymentResponseService(function ($redirectCheckoutMock, $orderIdMappingServiceMock, $entityTypeManagerMock, $loggerChannelMock, $eventDispatcherMock) {
      $orderIdMappingServiceMock
        ->expects($this->once())
        ->method('increaseRemoteOrderIdMinor');

      $entityTypeManagerMock
        ->expects($this->never())
        ->method('getStorage');

      $eventDispatcherMock
        ->expects($this->once())
        ->method('dispatch')
        ->with(PostfinanceEvents::PAYMENT_RESPONSE, $this->isInstanceOf(PaymentResponseEvent::class));
    });

    $parameters = $this->getPaymentParameters();
    $config = $this->getPluginConfiguration();
    $parameters[Parameter::STATUS] = $statusCodeError;
    $parameters[Parameter::SIGNATURE] = $this->calculateSignature($parameters, $config['sha_out'], $config['hash_algorithm']);

    $this->setExpectedException(PaymentGatewayException::class);
    $paymentResponseService->onReturn($this->getOrderMock(), $this->getRequestMock($parameters));
  }

  public function testOnReturn_MissingPostPaymentParameter_ThrowsException() {
    $paymentResponseService = $this->getPaymentResponseService(function ($redirectCheckoutMock, $orderIdMappingServiceMock, $entityTypeManagerMock, $loggerChannelMock, $eventDispatcherMock) {
      $orderIdMappingServiceMock
        ->expects($this->never())
        ->method('increaseRemoteOrderIdMinor');

      $entityTypeManagerMock
        ->expects($this->never())
        ->method('getStorage');
    });

    $parameters = $this->getPaymentParameters();

    // We want a correct signature for this test to be sure we don't fail with the internal InvalidSignatureException
    $config = $this->getPluginConfiguration();
    $parameters[Parameter::SIGNATURE] = $this->calculateSignature($parameters, $config['sha_out'], $config['hash_algorithm']);

    unset($parameters[array_rand($parameters)]);

    $this->setExpectedException(InvalidResponseException::class);
    $paymentResponseService->onReturn($this->getOrderMock(), $this->getRequestMock($parameters));
  }

  public function testOnReturn_IncorrectSignature_ThrowsException() {
    // Note that the signature is not correct - each parameter is initialized with some dummy value
    $parameters = $this->getPaymentParameters();

    $this->setExpectedException(InvalidResponseException::class);
    $this->paymentResponseService->onReturn($this->getOrderMock(), $this->getRequestMock($parameters));
  }

  public function testOnCancel_PaymentCancelled_RemoteOrderIdMinorGetsIncreasedAndNoPaymentIsCreated() {
    $paymentResponseService = $this->getPaymentResponseService(function ($redirectCheckoutMock, $orderIdMappingServiceMock, $entityTypeManagerMock, $loggerChannelMock, $eventDispatcherMock) {
      $orderIdMappingServiceMock
        ->expects($this->once())
        ->method('increaseRemoteOrderIdMinor');

      $entityTypeManagerMock
        ->expects($this->never())
        ->method('getStorage');

      $eventDispatcherMock
        ->expects($this->once())
        ->method('dispatch')
        ->with(PostfinanceEvents::PAYMENT_RESPONSE, $this->isInstanceOf(PaymentResponseEvent::class));
    });

    $paymentResponseService->onCancel($this->getOrderMock(), $this->getRequestMock());
  }

  public function partiallySuccessStatusCodesDataProvider() {
    return [[51], [52], [53], [54], [55], [56], [57], [58], [59], [91], [92], [99]];
  }

  public function successStatusCodesDataProvider() {
    return [[5], [9]];
  }

  public function errorStatusCodesDataProvider() {
    return [[0], [1], [2]];
  }

  protected function setUp() {
    $this->paymentResponseService = $this->getPaymentResponseService();
  }

  protected function getPaymentResponseService($dependencyManipulator = NULL) {
    $redirectCheckoutMock = $this->createMock(RedirectCheckout::class);
    $redirectCheckoutMock
      ->method('getConfiguration')
      ->willReturn($this->getPluginConfiguration());

    $redirectCheckoutMock
      ->method('getEntityId')
      ->willReturn(1);

    $orderIdMappingServiceMock = $this->createMock(OrderIdMappingService::class);
    $entityTypeManagerMock = $this->createMock(EntityTypeManagerInterface::class);
    $loggerChannelMock = $this->createMock(LoggerChannelInterface::class);
    $eventDispatcherMock = $this->createMock(EventDispatcher::class);

    if (is_callable($dependencyManipulator)) {
      $dependencyManipulator($redirectCheckoutMock, $orderIdMappingServiceMock, $entityTypeManagerMock, $loggerChannelMock, $eventDispatcherMock);
    }

    return new PaymentResponseService($redirectCheckoutMock, $orderIdMappingServiceMock, $entityTypeManagerMock, $loggerChannelMock, $eventDispatcherMock);
  }

  protected function getOrderMock() {
    return $this->createMock(OrderInterface::class);
  }

  protected function getRequestMock(array $parameters = []) {
    $requestMock = $this->createMock(Request::class);
    $requestMock->query = new ParameterBag($parameters);
    $requestMock->request = new ParameterBag($parameters);

    return $requestMock;
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

  protected function getPaymentParameters() {
    $parameters = [];
    foreach (Parameter::$postSaleParameters as $key) {
      $parameters[$key] = 1;
    }

    $parameters[Parameter::CURRENCY] = 'CHF';

    return $parameters;
  }

  /**
   * Calculates the correct signature based on parameters, shaOut and hash algorithm.
   *
   * @see \whatwedo\PostFinanceEPayment\Response\Response::create()
   * @return string
   */
  protected function calculateSignature(array $parameters, $shaOut, $hashAlgorithm) {
    $string = '';
    $p = Parameter::$postSaleParameters;
    sort($p);

    foreach ($p as $key) {
      if ($key === Parameter::SIGNATURE || !isset($parameters[$key]) || $parameters[$key] === '') {
        continue;
      }
      $string .= sprintf('%s=%s%s', $key, $parameters[$key], $shaOut);
    }

    return strtoupper(hash($hashAlgorithm, $string));
  }
}

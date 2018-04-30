<?php

namespace Drupal\Tests\commerce_postfinance\Unit;

// @codingStandardsIgnoreFile

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\InvalidResponseException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_postfinance\OrderNumberService;
use Drupal\commerce_postfinance\PaymentResponseService;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
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
  public function test_on_return_payment_successful($statusCode) {
    $paymentResponseService = $this->getPaymentResponseService(function ($_1, $_2, $_3, $entityTypeManagerMock) {
      $paymentEntityMock = $this->createMock(EntityInterface::class);
      $paymentEntityMock->expects($this->once())->method('save');
      $entityStorageMock = $this->createMock(EntityStorageInterface::class);
      $entityStorageMock->expects($this->once())
        ->method('create')
        ->willReturn($paymentEntityMock);
      $entityTypeManagerMock->expects($this->once())
        ->method('getStorage')
        ->willReturn($entityStorageMock);
    });
    $parameters = $this->getPaymentParameters();
    $config = $this->getPluginConfiguration();
    // Add correct parameters to simulate the "success" case
    $parameters[Parameter::STATUS] = $statusCode; // 5 or 9 are valid success codes
    $parameters[Parameter::SIGNATURE] = $this->calculateSignature($parameters, $config['sha_out'], $config['hash_algorithm']);
    $paymentResponseService->onReturn($this->getOrderMock(), $this->getRequestMock($parameters));
  }

  /**
   * @dataProvider errorStatusCodesDataProvider
   */
  public function test_on_return_payment_error($statusCode) {
    $paymentResponseService = $this->getPaymentResponseService(function ($_1, $_2, $orderNumberServiceMock, $entityTypeManagerMock) {
      $orderNumberServiceMock->expects($this->once())
        ->method('increaseMinorNumber');
      $paymentEntityMock = $this->createMock(EntityInterface::class);
      $paymentEntityMock->expects($this->once())->method('save');
      $entityStorageMock = $this->createMock(EntityStorageInterface::class);
      $entityStorageMock->expects($this->once())
        ->method('create')
        ->willReturn($paymentEntityMock);
      $entityTypeManagerMock->expects($this->once())
        ->method('getStorage')
        ->willReturn($entityStorageMock);
    });
    $parameters = $this->getPaymentParameters();
    $config = $this->getPluginConfiguration();
    $parameters[Parameter::STATUS] = $statusCode;
    $parameters[Parameter::SIGNATURE] = $this->calculateSignature($parameters, $config['sha_out'], $config['hash_algorithm']);
    $this->setExpectedException(PaymentGatewayException::class);
    $paymentResponseService->onReturn($this->getOrderMock(), $this->getRequestMock($parameters));
  }

  public function test_on_return_parameter_missing_throws_exception() {
    $paymentResponseService = $this->getPaymentResponseService(function ($_1, $_2, $orderNumberServiceMock, $entityTypeManagerMock) {
      $orderNumberServiceMock->expects($this->never())
        ->method('increaseMinorNumber');
      $entityTypeManagerMock->expects($this->never())
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

  public function test_on_return_parameter_signature_mismatch_throws_exception() {
    // The signature is not correct, each parameter is initialized with some dummy value
    $parameters = $this->getPaymentParameters();
    $this->setExpectedException(InvalidResponseException::class);
    $this->paymentResponseService->onReturn($this->getOrderMock(), $this->getRequestMock($parameters));
  }

  public function test_on_cancel_payment_cancelled() {
    $paymentResponseService = $this->getPaymentResponseService(function ($_1, $_2, $orderNumberServiceMock, $entityTypeManagerMock) {
      $orderNumberServiceMock->expects($this->once())
        ->method('increaseMinorNumber');
      $entityTypeManagerMock->expects($this->never())
        ->method('getStorage');
    });
    $paymentResponseService->onCancel($this->getOrderMock(), $this->getRequestMock());
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
    $paymentGatewayId = 1;
    $pluginConfig = $this->getPluginConfiguration();
    $orderNumberServiceMock = $this->getOrderNumberServiceMock();
    $entityTypeManagerMock = $this->getEntityTypeManagerMock();
    if (is_callable($dependencyManipulator)) {
      $dependencyManipulator($paymentGatewayId, $pluginConfig, $orderNumberServiceMock, $entityTypeManagerMock);
    }
    return new PaymentResponseService($paymentGatewayId, $pluginConfig, $orderNumberServiceMock, $entityTypeManagerMock);
  }

  protected function getOrderNumberServiceMock() {
    return $this->createMock(OrderNumberService::class);
  }

  protected function getEntityTypeManagerMock() {
    return $this->createMock(EntityTypeManagerInterface::class);
  }

  protected function getOrderMock() {
    return $this->createMock(OrderInterface::class);
  }

  protected function getRequestMock(array $queryParameters = []) {
    $requestMock = $this->createMock(Request::class);
    $requestMock->query = new ParameterBag($queryParameters);
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

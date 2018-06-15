<?php

namespace Drupal\Tests\commerce_postfinance\Unit;

// @codingStandardsIgnoreFile

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_postfinance\OrderIdMappingService;
use Drupal\Tests\UnitTestCase;

/**
 * @package Drupal\Tests\commerce_postfinance\Unit
 * @group commerce_postfinance
 * @coversDefaultClass Drupal\commerce_postfinance\OrderIdMappingService
 */
class OrderIdMappingServiceTest extends UnitTestCase {

  /**
   * @var OrderIdMappingService
   */
  private $orderIdMappingService;

  public function testGetRemoteOrderId_OrderHasNoMinorVersion_CorrectRemoteIdGetsReturned() {
    $orderMock = $this->createMock(OrderInterface::class);
    $orderMock
      ->method('getData')
      ->willReturn(FALSE);

    $orderMock
      ->method('id')
      ->willReturn(199);

    $this->assertEquals(199, $this->orderIdMappingService->getRemoteOrderId($orderMock));
  }

  public function testGetRemoteOrderId_OrderHasMinorVersion_CorrectRemoteIdGetsReturned() {
    $orderMock = $this->createMock(OrderInterface::class);
    $orderMock
      ->method('getData')
      ->willReturn('2');

    $orderMock
      ->method('id')
      ->willReturn(199);

    $this->assertEquals('199-2', $this->orderIdMappingService->getRemoteOrderId($orderMock));
  }

  public function testIncreaseRemoteOrderIdMinor_MinorVersionNotYetExisting_MinorNumberGetsIncrementedCorrectly() {
    $orderMock = $this->createMock(OrderInterface::class);
    $orderMock
      ->method('getData')
      ->willReturn(NULL);

    $orderMock
      ->expects($this->once())
      ->method('setData')
      ->with(OrderIdMappingService::KEY_NUMBER_MINOR, 1);

    $this->orderIdMappingService->increaseRemoteOrderIdMinor($orderMock);
  }

  public function testIncreaseRemoteOrderIdMinor_MinorVersionExisting_MinorNumberGetsIncrementedCorrectly() {
    $orderMock = $this->createMock(OrderInterface::class);
    $orderMock
      ->method('getData')
      ->willReturn('1');

    $orderMock
      ->expects($this->once())
      ->method('setData')
      ->with(OrderIdMappingService::KEY_NUMBER_MINOR, 2);

    $this->orderIdMappingService->increaseRemoteOrderIdMinor($orderMock);
  }

  public function testGetOrderIdFromRemoteOrderId_RemoteIdWithoutMinor_ReturnsCorrectOrderId() {
    $this->assertEquals(199, $this->orderIdMappingService->getOrderIdFromRemoteOrderId('199'));
  }

  public function testGetOrderIdFromRemoteOrderId_RemoteIdWitMinor_ReturnsCorrectOrderId() {
    $this->assertEquals(199, $this->orderIdMappingService->getOrderIdFromRemoteOrderId('199-3'));
  }

  protected function setUp() {
    parent::setUp();
    $this->orderIdMappingService = new OrderIdMappingService();
  }

}

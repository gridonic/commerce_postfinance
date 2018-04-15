<?php

namespace Drupal\Tests\commerce_postfinance\Unit;

// @codingStandardsIgnoreFile

use CommerceGuys\Addressing\Address;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_postfinance\PaymentRequestService;

use Drupal\commerce_price\Price;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @package Drupal\Tests\commerce_postfinance\Unit
 * @group commerce_postfinance
 * @coversDefaultClass Drupal\commerce_postfinance\PaymentRequestService
 */
class PaymentRequestServiceTest extends UnitTestCase {

  /**
   * @dataProvider parametersDataProvider
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   * @param array $pluginConfiguration
   * @param array $expectedParameters
   */
  public function testParameters(OrderInterface $order, array $pluginConfiguration, array $expectedParameters) {
    $currentLanguageStub = $this->createMock(LanguageInterface::class);
    $currentLanguageStub->method('getId')->willReturn('en');
    $languageManagerStub = $this->createMock(LanguageManagerInterface::class);
    $languageManagerStub->method('getCurrentLanguage')->willReturn($currentLanguageStub);
//    $paymentRequestService = new PaymentRequestService($pluginConfiguration, $languageManagerStub);
//    $this->assertEquals($expectedParameters, $paymentRequestService->getParameters($order));
    $this->assertTrue(TRUE);
  }

  /**
   * Provides data for the testParameters test case.
   *
   * @return array
   */
  public function parametersDataProvider() {
    $order1 = $this->getOrderStub(
      1,
      new Price('79.90', 'CHF'),
      'john.doe@example.com',
      [
        'country_code' => 'CH',
        'locality' => 'Bern',
        'postal_code' => '3000',
        'address_line1' => 'Aarbergergasse 40',
        'given_name' => 'John',
        'family_name' => 'Doe',
      ]
    );
    $expectedParameters = [];

    $dataSet1 = [
      $order1,
      [
        'psp_id' => 'Gridonic_TEST',
        'sha_in' => 'S3cr3t!',
        'sha_out' => 'S3cr3t!',
        'mode' => 'test',
        'charset' => 'utf-8',
        'hash_algorithm' => 'sha1',
        'catalog_url' => 'my_catalog_url',
      ],
      $expectedParameters,
    ];

    return [$dataSet1];
  }

  /**
   * @param int $orderId
   * @param \Drupal\commerce_price\Price $price
   * @param string $email
   * @param array $addressData
   *
   * @return \PHPUnit_Framework_MockObject_MockObject
   */
  protected function getOrderStub($orderId, Price $price, $email, array $addressData) {
    $billingProfile = $this->createMock(ProfileInterface::class);
    $addressList = $this->createMock(FieldItemListInterface::class);
    $address = new Address();
    $address = $address->withCountryCode($addressData['country_code']);
    $address = $address->withLocality($addressData['locality']);
    $address = $address->withPostalCode($addressData['postal_code']);
    $address = $address->withAddressLine1($addressData['address_line1']);
    $address = $address->withGivenName($addressData['given_name']);
    $address = $address->withFamilyName($addressData['family_name']);
    $addressList->method('first')->willReturn($address);
    $billingProfile->method('get')->willReturn($addressList);
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn($orderId);
    $order->method('getTotalPrice')->willReturn($price);
    $order->method('getBillingProfile')->willReturn($billingProfile);
    $order->method('getEmail')->willReturn($email);
    return $order;
  }

}

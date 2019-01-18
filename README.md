# Commerce Postfinance

Provides Commerce integration for the Postfinance payment gateway.

This payment gateway redirects to [Postfinance](https://www.postfinance.ch) 
where the user is able to pay with all activated payment methods, such as 
_Postfinance Card_, _Visa, Mastercard_, _Twint_ and many more.

## Installation

Install with Composer:

```
composer require drupal/commerce_postfinance:^2.0
```

Enable the module in Drupal and create a new payment gateway of type
*Postfinance (Redirect to Postfinance)*.

## Configuration

The module offers the following configuration options:

* **PSPID** The ID of the test- or production environment, obtained by 
Postfinance.
* **SHA-IN passphrase** The passphrase used to calculate the SHA signature 
for the payment request. Must be equal with the SHA-IN passphrase in the 
Postfinance backend.
* **SHA-OUT passphrase** The passphrase used to calculate the SHA signature 
for the payment response. Must be equal with the SHA-OUT passphrase in the 
Postfinance backend.
* **Hashing algorithm** The hashing algorithm used to calculate the 
SHA-IN and SHA-OUT signatures. Must correspond to the algorithm selected in 
the Postfinance backend. Choose between `SHA-1`, `SHA-256` or `SHA-512`.                       
* **Charset** Influences the payment request url. Choose `UTF-8` 
(recommended) or `ISO 8859 1`.
* **Catalog url** A node representing the catalog page of the shop.

Please follow the [official documentation](https://www.drupal.org/docs/8/modules/commerce-postfinance) on how to configure you Postfinance
Backoffice to work with this module.

# Commerce Postfinance

Provides Commerce integration for the Postfinance payment gateway.

This payment gateway redirects to [Postfinance](https://www.postfinance.ch) 
where the user is able to pay with all activated payment methods, such as 
`Postfinance Card`, `Visa`, `Mastercard`, `Twint` and many more.

## Installation

Since this module is not yet released on Drupal, the installation is slightly 
different than usual:

TODO: Add installation instructions.

## Configuration

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

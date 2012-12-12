-- SUMMARY --

  This project provides a Postfinance integration for the Drupal Commerce payment and checkout system.
  Postfinance, part of the Swiss Post, is a Swiss payment services provider (PSP) for e-payment solutions for professionals and beginners. Postfinance integrates all popular payment methods.
  (https://e-payment.postfinance.ch/)


-- REQUIREMENTS --
  Drupal 7
  Drupal Commerce - http://drupal.org/project/commerce


-- INSTALLATION --

  # DRUPAL
  Activate module
  Go to Store > Configuration > Payment methods and activate the payment methods you want (admin/commerce/config/payment-methods)
  Edit each payment methods, in the "Actions" click on the "edit"
  - Enter the PSPID from postfinance
  - Choose the Test or Production environment
  - Define the encoding to UTF-8
  - Set the "Digest Encryption" to "SHA-512"
  - Define SHA-X-IN/OUT Keys. All characters are not supported so don't be too freaky.
  - Shop URI represents the start uri on your page for the catalog on button on the post finance pages.
  - It makes sense to give different prefixes per payment-method (PostCard, VISA etc. - you'll see them in the transaciton log)

  # POSTFINANCE
  On the postfinance e-payment website got to configuration -> Technical information

  - Global security parameters
  Hash algorithm: SHA-512 for instance
  Character encoding: UTF-8

  - Data and origin verification
  Define the url of the merchant page, without subfolders, trailing slashes and spaces. ex:
    https://www.example.com;http://test2.io

  Put the same "SHA-IN Pass phrase" that you defined in drupal !

  - Transaction feedback
  Direct HTTP server-to-server request: Here define two url. The string after IPN can be different since we only use the post data. ex:
  http://www.example.com/my_shop/commerce_postfinance/IPN/ok
  and for the second address:
  http://www.example.com/my_shop/commerce_postfinance/IPN/notok

  Put the same "SHA-OUT Pass phrase" that you defined in drupal !

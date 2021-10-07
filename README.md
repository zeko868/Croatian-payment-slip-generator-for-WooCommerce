<p align="center"><a href="https://wordpress.org/plugins/croatian-payment-slip-generator-for-woocommerce/"><img src="https://raw.githubusercontent.com/zeko868/Croatian-payment-slip-generator-for-WooCommerce/master/images/banner-1544x500.png" alt="Banner of croatian payment slip generator for WooCommerce"></a></p>

*Read this in other languages: [English](README.md), [Hrvatski](README.hr.md).*

## Description

Make it easy for your customers from Croatia to perform direct bank transfer with generated payment slip, along with barcode for mBanking.

This plugin adds to the WooCommerce new payment gateway which is actually customization of Direct Bank Transfer payment option applicable for customers from the Republic of Croatia.
By installing, activating and enabling this payment gateway users are able to select payment options that offers them following:

* generated and pre-filled payment slip document which can be downloaded and printed, and then brought to any bank or post-office for making payment
* aforementioned payment slip also contains barcode which can be scanned through many apps for mobile banking in Croatia, thus making the payment process much faster and easier

**Translations:**

* Croatian
* English


## Requirements
This plugin requires at least PHP version 5.6. Also the following PHP modules have to be installed and enabled:
* bcmath
* fileinfo
* gd
* mbstring


## Installation

This section describes how to install the plugin and get it working.


1. Install this plugin through Plugins menu of WordPress Dashboard Admin Area by using search and downloading it from its WordPress page or upload the [code](https://github.com/zeko868/Croatian-payment-slip-generator-for-WooCommerce/releases/latest) of this plugin (or source code of this repo along with all required dependencies fetched by calling `composer install`) to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress


## Screenshots

Additional payment option is displayed on the checkout page and can be selected<br/>
![payment option on checkout page](/images/screenshot-1.png)<br/>
After user selects that option and proceeds to the payment, image of the payment slip, filled with previously-entered customer's data, is being shown. The same image is also sent to the customer via Email<br/>
![payment slip and barcode preview](/images/screenshot-2.png)<br/>
All fields (along with various other properties) can be set through settings page of this payment gateway that is located within WooCommerce Admin Dashboard
![payment option settings part 1](/images/screenshot-3.png)<br/>
![payment option settings part 2](/images/screenshot-4.png)<br/>


## Frequently Asked Questions

**Q:** Where can I report bugs or contribute to the project?

**A:** You can report bugs on [issue section of this repo](https://github.com/zeko868/croatian-payment-slip-generator-for-woocommerce/issues) or in [WordPress plugin support forum](https://wordpress.org/support/plugin/croatian-payment-slip-generator-for-woocommerce).
___
**Q:** How to handle order prices in different currency, so they could be converted to the prices corresponding to the currency on the payment slip?

**A:** In your `functions.php` file you should add function to the filter 'wooplatnica-croatia_order' that would perform conversion of the order price (which could be extracted from the object of class WC_Order passed as a first/only function argument) and would return instance of WC_Order class with updated field values.

## Special thanks to

* [Webmonster](https://webmonster.rs/) - team that developed plugin [Wooplatnica (Serbian payment slip generator for WooCommerce)](https://wordpress.org/plugins/wooplatnica/)
* [Ivan Habunek](https://github.com/ihabunek) - author of [PDF 417 barcode generator for PHP](https://github.com/ihabunek/pdf417-php)

## External links
* [Plugin page on WordPress](https://wordpress.org/plugins/croatian-payment-slip-generator-for-woocommerce/)
* [Plugin page on WordPress with croatian translation of documentation](https://hr.wordpress.org/plugins/croatian-payment-slip-generator-for-woocommerce/)

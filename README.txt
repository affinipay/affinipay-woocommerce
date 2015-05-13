=== ChargeIO for WooCommerce ===
Contributors: domenic95
Tags: woocommerce, chargeio, payment gateway, credit card, ecommerce, e-commerce, commerce, cart, checkout
Stable tag: trunk
Requires at least: 3.8.0
Tested up to: 4.1.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

A Payment Gateway for WooCommerce allowing you to take credit card payments using ChargeIO.

== Description ==

[ChargeIO](https://chargeio.com/) allows you to take credit card payments on your site without having sensitive credit card information hit your servers. The problem is, it's marketed towards developers so many people don't believe they can use it, or even know how. This plugin aims to show anyone that they can use ChargeIO to take credit card payments in their WooCommerce store without having to write a single line of code. All you have to do is copy 2 API keys to a settings page and you're done.

= Why ChargeIO? =
Without getting too technical, ChargeIO allows you to take credit card payments without having to put a lot of effort into securing your site. Normally you would have to save a customers sensitive credit card information on a seperate server than your site, using different usernames, passwords and limiting access to the point that it's nearly impossible to hack from the outside. It's a process that helps ensure security, but is not easy to do, and if done improperly leaves you open to fines and possibly lawsuits.

If you use this plugin, all you have to do is include an SSL certificate on your site and the hard work is done for you. Credit card breaches are serious, and with this plugin and an SSL certificate, you're protected. Your customers credit card information never hits your servers, it goes from your customers computer straight to ChargeIOs servers keeping their information safe.


== Installation ==

= Minimum Requirements =

* WooCommerce 2.1.0 or later

= Automatic installation =
Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of WooCommerce, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “ChargeIO for WooCommerce” and click Search Plugins. Once you've found our plugin you can view details about it such as the the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading our eCommerce plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

== Upgrade Notice ==

The plugin should automatically update with new features, but you could always download the new version of the plugin and manually update the same way you would manually install.


== Screenshots ==

1. The standard credit card form on the checkout page.
2. The form with saved cards and an icon for card identification.
3. Saved cards displayed on the account page.
4. Changing payment method for a subscription.

== Frequently Asked Questions ==

= Does I need to have an SSL Certificate? =

Yes you do. For any transaction involving sensitive information, you should take security seriously, and credit card information is incredibly sensitive. This plugin disables itself if you try to process live transactions without an SSL certificate.

== Changelog ==

= 1.0 =

* Feature: ChargeIO fee is added to order details
* Feature: Refunds! With WC 2.2, refunds were introduced
* Feature: Ability to delete ChargeIO account data per individual customer
* Feature: Filters for customer and charge descriptions sent to ChargeIO
* Feature: Button to delete all test data
* Feature: Charge a guest using ChargeIO
* Feature: Create a customer in ChargeIO for logged in users
* Feature: Charge a ChargeIO customer with a saved card
* Feature: Add a card to a customer
* Feature: Delete cards from customers
* Feature: Authorize & Capture or Authorize only 
=== ChargeIO for WooCommerce ===
Contributors: domenic95
Tags: woocommerce, chargeio, payment gateway, credit card, ecommerce, e-commerce, commerce, cart, checkout
Requires at least: 3.8.0
Tested up to: 4.1.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

A Payment Gateway for WooCommerce allowing you to take credit card payments using ChargeIO.

##SSL Certificate

For any transaction involving sensitive information, you should take security seriously, and credit card information is incredibly sensitive. This plugin disables itself if you try to process live transactions without an SSL certificate.
You can use the plugin in test mode without an SSL certificate.

[ChargeIO](https://chargeio.com/) allows you to take credit card payments on your site without having sensitive credit card information hit your servers. The problem is, it's marketed towards developers so many people don't believe they can use it, or even know how. This plugin aims to show anyone that they can use ChargeIO to take credit card payments in their WooCommerce store without having to write a single line of code. All you have to do is copy 2 API keys to a settings page and you're done.

= Why ChargeIO? =
Without getting too technical, ChargeIO allows you to take credit card payments without having to put a lot of effort into securing your site. Normally you would have to save a customers sensitive credit card information on a seperate server than your site, using different usernames, passwords and limiting access to the point that it's nearly impossible to hack from the outside. It's a process that helps ensure security, but is not easy to do, and if done improperly leaves you open to fines and possibly lawsuits.

If you use this plugin, all you have to do is include an SSL certificate on your site and the hard work is done for you. Credit card breaches are serious, and with this plugin and an SSL certificate, you're protected. Your customers credit card information never hits your servers, it goes from your customers computer straight to ChargeIOs servers keeping their information safe.


= Minimum Requirements =

* WooCommerce 2.1.0 or later


== Frequently Asked Questions ==

= Does I need to have an SSL Certificate? =

Yes you do. For any transaction involving sensitive information, you should take security seriously, and credit card information is incredibly sensitive. This plugin disables itself if you try to process live transactions without an SSL certificate. You can read [ChargeIO's reasaoning for using SSL here](https://chargeio.com/help/ssl).


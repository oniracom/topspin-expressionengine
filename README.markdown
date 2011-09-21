Topspin Expression Engine Module
===

A module that integrates with Topspin's (http://topspinmedia.com) REST API.
 
Topspin API Documentation: http://docs.topspin.net

REQUIREMENTS
===

* PHP 5.2.x
* jSON enabled for PHP
* cURL enabled for PHP
* A Topspin account with an API key.
* Expression Engine 1.6.7 - 1.7.x  (Not EE2 compatible yet)
* Pages module
* JQuery extension

FEATURES
===

* Creates any number of store pages (via the pages module) 
* Store pages automatically populate and update products from your topspin account
* Choose which products appear on pages by offer types and tags
* Built-in sorting and pagination
* Includes two customizable page templates


INSTALL INSTRUCTIONS
===
The folder structure mimics which folders you should copy the files to in your EE install.  The installation process is very similar to most EE modules
* system/language/english/lang.topspin.php goes into your system/language/english folder
* system/modules/topspin goes in your system/modules folder
* themes/third_party/topspin goes in your themes/third_party folder

* After copying files, login to your control panel and click 'install' next to the Topspin module
* Click on 'Topspin' in the control panel and enter your credentials
* After you save your credentials, a list of artists associated with your account will populate
* Choose an artist, save and then you can begin creating store pages by choosing a URL and setting up your offer filters

CUSTOMIZING INSTRUCTIONS
===
* The module will create a template group called '_topspin_stores' with the two default templates, light and dark.
* You can edit these templates directly to match the style of your site
* You can also create a new template and associate one of your store pages to it through the pages module


AUTHOR/MAINTAINER
===

* Oniracom (http://oniracom.com)
* Topspin Media (http://topspinmedia.com)

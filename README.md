# Lengow for Shopware 6

- **Requires at least:** Shopware 6.5
- **Tested up to:** Shopware 6.6.9.0
- **Requires PHP:** 8.1
- **Stable tag:** 2.1.1 <!-- x-release-please-version -->
- **License:** MIT
- **License URI:** https://opensource.org/licenses/MIT

## Overview

<p align="center">
  <img src="https://my.lengow.io/images/pages/launching/orders.png">
</p>

Lengow is the e-commerce automation solution that helps brands and distributors improve their performance, automate their business processes, and grow internationally. The Lengow platform is the key to strong profitability and visibility for products sold by online retailers around the world on all distribution channels: marketplaces, comparison shopping engines, affiliate platforms and display/retargeting platforms. Since 2009, Lengow has integrated more than 1,600 partners into its solution to provide a powerful platform to its 4,600 retailers and brands in 42 countries around the world.

Major features in Lengow include:

- Easily import your product data from your cms
- Use Lengow to target and exclude the right products for the right channels and tools (marketplaces, price comparison engines, product ads, retargeting, affiliation) and automate the process of product diffusion.
- Manipulate your feeds (categories, titles, descriptions, rules�) - no need for technical knowledge.
- Lengow takes care of the centralisation of orders received from marketplaces and synchronises inventory data with your backoffice. Track your demands accurately and set inventory rules to avoid running out of stock.
- Monitor and control your ecommerce activity using detailed, yet easy to understand graphs and statistics. Track clicks, sales, CTR, ROI and tweak your campaigns with automatic rules according to your cost of sales / profitability targets.
- Thanks to our API, Lengow is compatible with many applications so you can access the functionality of all your ecommerce tools on a single platform. There are already more than 40 available applications: marketing platform, translation, customer review, email, merchandise, price watch, web-to-store, product recommendation and many more

The Lengow plugin is free to download and it enables you to export your product catalogs and manage your orders. It is compatible only with the new version of our platform.
A Lengow account is created during the extension installation and you will have free access to our platform for 15 days. To benefit from all the functionalities of Lengow, this requires you to pay for an account on the Lengow platform.

## Plugin installation

Follow the instruction below if you want to install Lengow for Shopware using Git.

1.) Clone the git repository in the Shopware `custom/plugins` folder using:

    git@github.com:lengow/plugin-shopware6.git LengowConnector

In case you wish to contribute to the plugin, fork the `dev` branch rather than cloning it, and create a pull request via Github. For further information please read the section "Become a contributor" of this document.

2.) Set the correct directory permissions:

    chmod -R 755 custom/plugins/LengowConnector

Depending on your server configuration, it might be necessary to set whole write permissions (777) to the files and folders above.
You can also start testing with lower permissions due to security reasons (644 for example) as long as your php process can write to those files.

3.) Activate the plugin through the `Settings > System > Plugins` screen in Shopware 6

4.) Log in with your Lengow API credentials and configure the plugin

## Frequently Asked Questions

### Where can I find Lengow documentation and user guides?

For help setting up and configuring Lengow plugin please refer to our [user guide](https://help.lengow.com/hc/en-us/articles/4411830779410)

## Where can I get support?

To make a support request to Lengow, use [our helpdesk](https://help.lengow.com/hc/en-us/requests/new).


## Become a contributor

Lengow for Shopware 6 is available under license (MIT). If you want to contribute code (features or bugfixes), you have to create a pull request via Github and include valid license information.

The `master` branch contains the latest stable version of the plugin. The `dev` branch contains the version under development.
All Pull requests must be made on the `dev` branch and must be validated by reviewers working at Lengow.

By default, the plugin is made to work on our pre-production environment (my.lengow.net).
The environment can be changed to production (my.lengow.io) in the settings of this module.

If you want to record a new connection when you change environment, go to settings, then go to the import section and activate debug mode.
Then go back to the general settings section, and delete the data from the Identifiers fields.
Finally, open the Lengow module again and you can make a new connection.

### Translation

Translations in the plugin are managed via a key system and associated yaml files

Start by installing Yaml Parser:

    sudo apt-get install php5-dev libyaml-dev
    sudo pecl install yaml

To translate the project, use specific key in php code and modify the *.yml files in the directory: `LengowConnector/src/Translations/yml`

Once the translations are finished, just run the translation update script in `LengowConnector/tools` folder

    php translate.php

The plugin is translated into English, German and French.

## Changelog

The changelog and all available commits are located under [CHANGELOG](CHANGELOG.md).

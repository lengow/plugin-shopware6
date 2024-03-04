# v1.1.4
- Bugfix: [import] fix add product to Shopware Cart
- Bugfix: [action] Fix id Action is not integer

# v1.1.3
- Bugfix: [install] fix intallation plugin
- Bugfix: [global] Replace class EntityRepositoryInterface by EntityRepository
- Bugfix: [front] Use new class name for icon librairy of Shopware
- Bugfix: [import & export] Use new annotation for route scope
- Bugfix: [import] Fix use of RetryableQuery method
- Bugfix: [import] Not use id delivery address for DB Sharding
- Bugfix: [import] Add partial refunded state
- Bugfix: [import] Reimport order if VAT number is différent
- Feature: Add configurable URL environment to connect to Lengow in setting and add accessible setting in footer of module
- Feature: get details of modified file in toolbox

# v1.1.2
- Bugfix: [import] fix search carrier code 

# v1.1.1
- Feature: Adding the PHP version in the toolbox
- Feature: Modification of the fallback urls of the Lengow Help Center
- Feature: Adding extra field update date in external toolbox

# v1.1.0
- Feature: Integration of order synchronization in the toolbox webservice
- Feature: Retrieving the status of an order in the toolbox webservice

# v1.0.2
- Feature: Outsourcing of the toolbox via webservice
- Feature: Added compatibility with Shopware 6.4
- Feature: Setting up a modal for the plugin update
- Bugfix: [export] remove html_entity_decode call when retrieving product description
- Bugfix: [export] fix headers fields duplication
- Bugfix: [export] fix image retrieval

# v1.0.1
- Bugfix: [export] Add parameters in product SQL requests
- Bugfix: [export] Use getFeedUrl() function in product grid

# v1.0.0
- Feature: Lengow Dashboard (contact, helper center and quick links)
- Feature: Product page with product selection by sales channel
- Feature: Direct retrieval of the Shopware catalog in Lengow
- Feature: Implementation of the Lengow order management screen
- Feature: Automatic synchronization of marketplace orders between Lengow and Shopware
- Feature: Management of orders sent by marketplaces
- Feature: Display of order types (express, B2B, delivered by marketplace)
- Feature: Quick fix import or send action error using refresh button
- Feature: Management of ship and cancel actions on orders
- Feature: Automatic verification of actions sent to the marketplace
- Feature: Automatic sending of action if the first shipment was a failure
- Feature: Sending a report email with order import and action upload errors
- Feature: Viewing all Lengow order Information on the Shopware order detail
- Feature: Help page with all necessary support links
- Feature: Toolbox with all Lengow information for support
- Feature: Download maintenance logs globally or per day
- Feature: Direct management of settings in the plugin interface
- Feature: Lengow account synchronisation directly from the plugin

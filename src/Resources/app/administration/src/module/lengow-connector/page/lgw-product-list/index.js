import template from './lgw-product-list.html.twig';
import './lgw-product-list.scss';

const {
    Component,
    Mixin,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-product-list', {
    template,

    inject: ['repositoryFactory', 'numberRangeService', 'acl', 'LengowConnectorExportService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder')
    ],

    props: {
        salesChannelId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            products: [],
            currencies: [],
            sortBy: 'productNumber',
            sortDirection: 'DESC',
            naturalSorting: true,
            isLoading: false,
            total: 0,
            product: null,
            defaultSalesChannel: null,
            currentSalesChannelId: this.salesChannelId,
            currentEntryPoint: null,
            salesChannelModel: null,
            searchFilterText: '',
            stockFilterText: '',
            activeFilterText: '',
            salesChannelSelected: false,
            productIds: [],
            baseProducts: [],
            filteredResult: [],
            filters: {
                stock: null,
                active: null,
                search: null
            },
            productSelection: false,
            productSelectionSelectAll: false,
            selection: [],
            totalSelected: 0,
            exportedCount: 0,
            exportableCount: 0,
            countLoading: true,
            salesChannelName: '',
            salesChannelDomain: '',
            tempListActivated: [],
            downloadLink: '',
            page: 1,
            limit: 10,
        };
    },

    created() {
        this.salesChannelRepository
            .search(new Criteria(), Shopware.Context.api)
            .then(salesChannelCollection => {
                const salesChannelId = salesChannelCollection.first().id;
                this.defaultSalesChannel = salesChannelId;
                this.onSalesChannelChanged(salesChannelId);
            });
        this.addDynamicStyle();
        this.SEARCHFILTER = 'search';
        this.ACTIVEFILTER = 'active';
        this.STOCKFILTER = 'stock';
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        productColumns() {
            return this.getProductColumns();
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        lengowProductRepository() {
            return this.repositoryFactory.create('lengow_product');
        },

        systemConfigRepository() {
            return this.repositoryFactory.create('system_config');
        },

        lengowSettingsRepository() {
            return this.repositoryFactory.create('lengow_settings');
        },

        currenciesColumns() {
            return this.currencies
                .sort((a, b) => (b.isSystemDefault ? 1 : -1))
                .map(item => ({
                    property: `price-${item.isoCode}`,
                    dataIndex: `price.${item.id}`,
                    label: `${item.name}`,
                    allowResize: true,
                    currencyId: item.id,
                    visible: item.isSystemDefault,
                    align: 'center',
                    useCustomSort: true,
                }));
        },

        isActiveOptions() {
            return [
                {
                    label: this.$tc('lengow-connector.product.filter.option_all'),
                    value: 'all'
                },
                {
                    label: this.$tc('lengow-connector.product.filter.active_option_active'),
                    value: 'active'
                },
                {
                    label: this.$tc('lengow-connector.product.filter.active_option_inactive'),
                    value: 'inactive'
                }
            ];
        },

        isWithStockOptions() {
            return [
                {
                    label: this.$tc('lengow-connector.product.filter.option_all'),
                    value: 'all'
                },
                {
                    label: this.$tc('lengow-connector.product.filter.stock_option_with_stock'),
                    value: 'stock'
                },
                {
                    label: this.$tc('lengow-connector.product.filter.stock_option_without_stock'),
                    value: 'nostock'
                }
            ];
        }
    },

    filters: {
        stockColorVariant(value) {
            if (value > 25) {
                return 'success';
            }
            if (value < 25 && value > 0) {
                return 'warning';
            }
            return 'error';
        }
    },

    methods: {
        applyOtherFilter(products, current) {
            let productFiltered = products;
            if (current !== this.ACTIVEFILTER && this.filters.active) {
                productFiltered = this.activeFilter(productFiltered, this.filters.active);
            }
            if (current !== this.STOCKFILTER && this.filters.stock) {
                productFiltered = this.stockFilter(productFiltered, this.filters.stock);
            }
            if (current !== this.SEARCHFILTER && this.filters.search) {
                productFiltered = this.searchFilter(productFiltered, this.filters.search);
            }
            return productFiltered;
        },

        activeFilter(products, value) {
            if (value === 'all' || value === null) {
                this.filters.active = null;
                return products;
            }
            return products.filter(product => (value === 'active' ? product.active === true : product.active === false));
        },

        stockFilter(products, value) {
            if (value === 'all' || value === null) {
                this.filters.stock = null;
                return products;
            }
            // eslint-disable-next-line max-len
            return products.filter(product => (value === 'stock' ? product.availableStock > 0 : product.availableStock <= 0));
        },

        searchFilter(products, value) {
            if (!value) {
                this.filters.search = null;
                return products;
            }

            return products.filter(product => {
                const id = product.id?.toLowerCase() || '';
                const productNumber = product.productNumber?.toLowerCase() || '';
                const name = product.name?.toLowerCase() || '';

                return id.includes(value.toLowerCase()) ||
                    productNumber.includes(value.toLowerCase()) ||
                    name.includes(value.toLowerCase());
            });
        },

        onActiveFilter(value) {
            if (!this.salesChannelSelected) {
                return;
            }
            this.filteredResult = this.applyOtherFilter(this.baseProducts, 'active');
            this.filters.active = value;
            this.products = this.activeFilter(this.filteredResult, value);
            this.updateProductList();
        },

        onStockFilter(value) {
            if (!this.salesChannelSelected) {
                return;
            }
            this.filteredResult = this.applyOtherFilter(this.baseProducts, 'stock');
            this.filters.stock = value;
            this.products = this.stockFilter(this.filteredResult, value);
            this.updateProductList();
        },

        onSearchFilter(input) {
            if (!this.salesChannelSelected) {
                return;
            }
            this.filteredResult = this.applyOtherFilter(this.baseProducts, 'search');
            this.filters.search = input;
            this.products = this.searchFilter(this.filteredResult, input);
            this.updateProductList();
        },

        resetFilters() {
            this.filters.active = null;
            this.filters.stock = null;
            this.filters.search = null;
            this.searchFilterText = '';
            this.stockFilterText = 'all';
            this.activeFilterText = 'all';
            this.onActiveFilter('all');
            this.onStockFilter('all');
        },

        setupSelectionActivated() {
            const lengowSettingsCriteria = new Criteria();
            lengowSettingsCriteria.addFilter(Criteria.equals('salesChannelId', this.currentSalesChannelId));
            lengowSettingsCriteria.addFilter(Criteria.equals('name', 'lengowSelectionEnabled'));
            this.lengowSettingsRepository
                .search(lengowSettingsCriteria, Shopware.Context.api)
                .then(result => {
                    if (result.total > 0) {
                        this.productSelection = result.first().value === '1';
                    }
                });
        },

        setExportLink(salesChannelId) {
            this.LengowConnectorExportService.getExportLink(salesChannelId).then(response => {
                if (response.success) {
                    this.downloadLink = response.link;
                }
            });
        },

        setupExportedCount() {
            this.LengowConnectorExportService.getExportCount(this.currentSalesChannelId).then(response => {
                if (response.success) {
                    this.exportableCount = response.total;
                    this.exportedCount = response.exported;
                }
            }).finally(() => {
                this.countLoading = false;
            });
        },

        onSalesChannelChanged(salesChannelId) {
            this.salesChannelSelected = true;
            this.isLoading = true;
            this.countLoading = true;
            this.resetFilters();
            this.currentSalesChannelId = salesChannelId ? salesChannelId : this.defaultSalesChannel;
            this.setupSelectionActivated();
            this.setExportLink(this.currentSalesChannelId);
            this.LengowConnectorExportService.getProductList(this.currentSalesChannelId).then(data => {
                this.productIds = data.productList;
                return this.updateProductList().then(() => {
                    const salesChannelCriteria = new Criteria();
                    salesChannelCriteria.setIds([this.currentSalesChannelId]);
                    salesChannelCriteria.addAssociation('domains');


                    return this.salesChannelRepository
                        .search(salesChannelCriteria, Shopware.Context.api)
                        .then(salesChannelCollection => {
                            if (salesChannelCollection.first()) {
                                this.salesChannelName = salesChannelCollection.first().name;
                                if (salesChannelCollection.first().domains.first()) {
                                    this.salesChannelDomain = salesChannelCollection.first().domains.first().url;
                                } else {
                                    this.salesChannelDomain = 'Headless';
                                }
                            }
                        });
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        updateProductList() {
            this.total = this.products.total;
            const productCriteria = new Criteria(this.page, this.limit);
            this.naturalSorting = this.sortBy === 'productNumber';
            if (this.productIds.length > 0) {
                productCriteria.addFilter(Criteria.equalsAny('product.id', this.productIds));
            }
            productCriteria.addSorting(
                Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting)
            );

            if (this.filters.active !== null) {
                const isActive = this.filters.active === 'active';
                productCriteria.addFilter(Criteria.equals('active', isActive));
            }

            if (this.filters.stock) {
                if (this.filters.stock === 'nostock') {
                    productCriteria.addFilter({
                        type: 'equals',
                        field: 'stock',
                        value: 0
                    });
                } else {
                    productCriteria.addFilter({
                        type: 'range',
                        field: 'stock',
                        parameters: {
                            gte: 1
                        }
                    });
                }
            }

            if (this.filters.search) {
                productCriteria.addFilter(Criteria.multi('OR', [
                    Criteria.contains('name', this.filters.search),
                    Criteria.contains('productNumber', this.filters.search),
                    Criteria.contains('id', this.filters.search)
                ]));
            }

            productCriteria.addAssociation('cover');
            productCriteria.addAssociation('manufacturer');

            const currencyCriteria = new Criteria(1, 500);
            return Promise.all([
                this.productRepository.search(productCriteria, Shopware.Context.api),
                this.currencyRepository.search(currencyCriteria, Shopware.Context.api)
            ])
                .then(result => {

                    const [products, currencies] = result;
                    this.total = products.total;
                    products.forEach(product => {
                        product.extensions.activeInLengow.active =
                            typeof product.extensions.activeInLengow.activeArray[
                                this.currentSalesChannelId
                                ] !== 'undefined';
                    });
                    this.products = products;
                    this.baseProducts = this.products;
                    this.currencies = currencies;
                    this.isLoading = false;
                    this.productIds = [];
                    this.setupExportedCount();
                })
                .catch(() => {
                    this.isLoading = false;
                    this.productIds = [];
                });
        },

        updateSelection(selected) {
            const selectedItem = Object.values(selected);
            this.selection = [];
            selectedItem.forEach(item => {
                this.selection.push(item.id);
            });
            this.totalSelected = this.selection.length;
        },

        onActivateSelection($active) {
            const lengowSettingsCriteria = new Criteria();
            lengowSettingsCriteria.addFilter(Criteria.equals('salesChannelId', this.currentSalesChannelId));
            lengowSettingsCriteria.addFilter(Criteria.equals('name', 'lengowSelectionEnabled'));
            this.lengowSettingsRepository
                .search(lengowSettingsCriteria, Shopware.Context.api)
                .then(result => {
                    if (result.total !== 0) {

                        const lengowSettings = this.lengowSettingsRepository.create(Shopware.Context.api);
                        lengowSettings.id = result.first().id;
                        lengowSettings.salesChannelsId = this.currentSalesChannelId;
                        lengowSettings.name = 'lengowSelectionEnabled';
                        lengowSettings.value = $active === true ? '1' : '0';
                        this.lengowSettingsRepository.sync([lengowSettings], Shopware.Context.api).then(() => {
                            this.onSalesChannelChanged(this.currentSalesChannelId);
                        });
                    }
                });
        },

        onPublishOnLengow() {
            const lengowProductCriteria = new Criteria();
            lengowProductCriteria.addFilter(Criteria.equalsAny('productId', this.selection));
            lengowProductCriteria.addFilter(Criteria.equals('salesChannelId', this.currentSalesChannelId));
            this.lengowProductRepository
                .search(lengowProductCriteria, Shopware.Context.api)
                .then(result => {
                    const ids = [];
                    result.forEach(item => {
                        ids.push(item.productId);
                    });
                    const toPublish = this.selection.filter(x => !ids.includes(x));
                    toPublish.forEach(productId => {
                        const lengowProduct = this.lengowProductRepository.create(Shopware.Context.api);
                        lengowProduct.productId = productId;
                        lengowProduct.salesChannelId = this.currentSalesChannelId;
                        this.lengowProductRepository.save(lengowProduct, Shopware.Context.api);
                    });
                    this.onSalesChannelChanged(this.currentSalesChannelId);
                });

        },

        onUnpublishOnLengow() {
            const lengowProductCriteria = new Criteria();
            lengowProductCriteria.addFilter(Criteria.equalsAny('productId', this.selection));
            lengowProductCriteria.addFilter(Criteria.equals('salesChannelId', this.currentSalesChannelId));
            this.lengowProductRepository
                .searchIds(lengowProductCriteria, Shopware.Context.api)
                .then(result => {
                    result.data.forEach(lengowProductId => {
                        this.lengowProductRepository.delete(lengowProductId, Shopware.Context.api);
                    });
                    this.onSalesChannelChanged(this.currentSalesChannelId);
                });
            this.selection = [];
        },

        OnActivateOnLengow(selected) {
            const selectedItem = Object.values(selected)[0];
            const lengowProductCriteria = new Criteria();
            lengowProductCriteria.addFilter(Criteria.equals('productId', selectedItem.id));
            lengowProductCriteria.addFilter(Criteria.equals('salesChannelId', this.currentSalesChannelId));

            this.lengowProductRepository.search(lengowProductCriteria, Shopware.Context.api)
                .then(result => {
                    const productExists = result.total > 0;
                    if (!productExists) {
                        const lengowProduct = this.lengowProductRepository.create(Shopware.Context.api);
                        lengowProduct.productId = selectedItem.id;
                        lengowProduct.salesChannelId = this.currentSalesChannelId;
                        return this.lengowProductRepository.save(lengowProduct, Shopware.Context.api).then(() => {
                            this.countLoading = true;
                            this.LengowConnectorExportService
                                .getProductCountValue(selectedItem.id, this.currentSalesChannelId)
                                .then(response => {
                                    if (response.success) {
                                        this.exportedCount += response.countValue;
                                    }
                                    this.countLoading = false;
                                });
                        });
                    }
                    if (productExists) {
                        return this.lengowProductRepository.delete(result.first().id, Shopware.Context.api)
                            .then(() => {
                                this.countLoading = true;
                                this.LengowConnectorExportService
                                    .getProductCountValue(selectedItem.id, this.currentSalesChannelId)
                                    .then(response => {
                                        if (response.success) {
                                            this.exportedCount -= response.countValue;
                                        }
                                        this.countLoading = false;
                                    });
                            });
                    }
                }).catch(error => {
                console.error("Error:", error);
            });
        },

        getCurrencyPriceByCurrencyId(currencyId, prices) {
            const actualCurrencyId = currencyId.toString();
            const priceForProduct = prices.find(price => price.currencyId === actualCurrencyId);
            if (priceForProduct) {
                return priceForProduct;
            }
            return {
                currencyId: null,
                gross: null,
                linked: true,
                net: null
            };
        },

        formatCurrency(value, currencyIsoCode) {
            return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: currencyIsoCode }).format(value);
        },

        getProductColumns() {
            const columns = [
                {
                    property: 'extensions.activeInLengow.active',
                    label: this.$tc('lengow-connector.product.column.active_in_lengow'),
                    inlineEdit: 'boolean',
                    align: 'center',
                    allowResize: false,
                    sortable: false
                },
                {
                    property: 'name',
                    label: this.$tc('sw-product.list.columnName'),
                    routerLink: 'sw.product.detail',
                    inlineEdit: 'string',
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'productNumber',
                    naturalSorting: true,
                    label: this.$tc('sw-product.list.columnProductNumber'),
                    align: 'right',
                    allowResize: true
                },
                {
                    property: 'manufacturer.name',
                    label: this.$tc('sw-product.list.columnManufacturer'),
                    allowResize: true
                },
                {
                    property: 'active',
                    label: `${this.$tc('sw-product.list.columnActive')}`,
                    inlineEdit: 'boolean',
                    allowResize: true,
                    align: 'center'
                },
                ...this.currenciesColumns,
                {
                    property: 'availableStock',
                    label: this.$tc('sw-product.list.columnAvailableStock'),
                    allowResize: true,
                    align: 'right'
                }
            ];
            return columns;
        },

        onStartSorting() {
            this.$refs.swProductGrid.records.forEach(item => {
                if (this.$refs.swProductGrid.isSelected(item.id) !== true &&
                    item.extensions.activeInLengow.active
                ) {
                    this.tempListActivated.push(item.id);
                }
            });
        },

        onColumnSort() {
            this.$refs.swProductGrid.records.forEach(item => {
                if (this.$refs.swProductGrid.isSelected(item.id) !== true &&
                    this.tempListActivated.includes(item.id)
                ) {
                    item.extensions.activeInLengow.active = true;
                } else {
                    item.extensions.activeInLengow.active =
                        typeof item.extensions.activeInLengow.activeArray[this.currentSalesChannelId] !== 'undefined';
                }
            });
        },

        onPageChange(newPage) {
            this.page = newPage.page;
            this.limit = newPage.limit;
            this.updateProductList();
        },

        addDynamicStyle() {
            const style = document.createElement('style');
            style.innerHTML = `
            .sw-context-menu {
                width: fit-content !important;
            }
        `;
            document.head.appendChild(style);
        },
    }
});

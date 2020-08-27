import template from './views/lengow-export-list.html.twig';

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lengow-export-list', {
    template,

    inject: ['repositoryFactory', 'numberRangeService', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        salesChannelId: {
            type: String,
            required: false,
            default: null,
        },
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
            currentSalesChannelId: this.salesChannelId,
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
                search: null,
            },
            productSelection: false,
            productSelectionSelectAll: false,
            selection: [],
            totalSelected: 0,
        };
    },

    created() {
        this.salesChannelRepository
            .search(new Criteria(), Shopware.Context.api)
            .then(salesChannelCollection => {
                this.onSalesChannelChanged(salesChannelCollection.first().id);
            });
        this.SEARCHFILTER = 'search';
        this.ACTIVEFILTER = 'active';
        this.STOCKFILTER = 'stock';
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
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

        currenciesColumns() {
            return this.currencies
                .sort((a, b) => (b.isSystemDefault ? 1 : -1))
                .map(item => ({
                    property: `price-${item.isoCode}`,
                    dataIndex: `price.${item.id}`,
                    label: `${item.name}`,
                    routerLink: 'lengow.export.detail',
                    allowResize: true,
                    currencyId: item.id,
                    visible: item.isSystemDefault,
                    align: 'right',
                    useCustomSort: true,
                }));
        },

        isActiveOptions() {
            return [
                { label: 'All', value: 'all' },
                { label: 'Active only', value: 'active' },
                { label: 'Inactive only', value: 'inactive' },
            ];
        },

        isWithStockOptions() {
            return [
                { label: 'All', value: 'all' },
                { label: 'With stock only', value: 'stock' },
                { label: 'Without stock only', value: 'nostock' },
            ];
        },
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
        },
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
            return products.filter(product =>
                value === 'active' ? product.active === true : product.active === false,
            );
        },

        stockFilter(products, value) {
            if (value === 'all' || value === null) {
                this.filters.stock = null;
                return products;
            }
            return products.filter(product =>
                value === 'stock' ? product.availableStock > 0 : product.availableStock <= 0,
            );
        },

        searchFilter(products, value) {
            if (!value) {
                this.filters.search = null;
                return products;
            }
            return products.filter(
                product =>
                    product.id === value ||
                    product.productNumber === value ||
                    product.name.toLowerCase().includes(value.toLowerCase()),
            );
        },

        onActiveFilter(value) {
            if (!this.salesChannelSelected) {
                return;
            }
            this.filteredResult = this.applyOtherFilter(this.baseProducts, 'active');
            this.filters.active = value;
            this.products = this.activeFilter(this.filteredResult, value);
        },

        onStockFilter(value) {
            if (!this.salesChannelSelected) {
                return;
            }
            this.filteredResult = this.applyOtherFilter(this.baseProducts, 'stock');
            this.filters.stock = value;
            this.products = this.stockFilter(this.filteredResult, value);
        },

        onSearchFilter(input) {
            if (!this.salesChannelSelected) {
                return;
            }
            this.filteredResult = this.applyOtherFilter(this.baseProducts, 'search');
            this.filters.search = input;
            this.products = this.searchFilter(this.filteredResult, input);
        },

        resetFilters() {
            this.filters.active = null;
            this.filters.stock = null;
            this.filters.search = null;
            this.searchFilterText = '';
            this.stockFilterText = 'all';
            this.activeFilterText = 'all';
        },

        onSalesChannelChanged(salesChannelId) {
            this.salesChannelSelected = true;
            this.resetFilters();
            this.isLoading = true;
            this.currentSalesChannelId = salesChannelId;
            const salesChannelCriteria = new Criteria();
            salesChannelCriteria.setIds([salesChannelId]);
            this.salesChannelRepository
                .search(salesChannelCriteria, Shopware.Context.api)
                .then(salesChannelCollection => {
                    salesChannelCollection.find(entry => {
                        const categoryCriteria = new Criteria();
                        categoryCriteria.setIds([entry.navigationCategoryId]);

                        this.categoryRepository
                            .search(categoryCriteria, Shopware.Context.api)
                            .then(categoryCollection => {
                                const masterCategory = categoryCollection.first();
                                this.processCategoryTree(masterCategory.id);
                            });
                    });
                })
                .catch(() => {
                    this.isLoading = false;
                });
        },

        processCategoryTree(treeMasterCategoryId) {
            const categoryCriteria = new Criteria();
            categoryCriteria.addFilter(Criteria.contains('category.path', treeMasterCategoryId));
            categoryCriteria.addAssociation('products');
            this.categoryRepository
                .search(categoryCriteria, Shopware.Context.api)
                .then(categoryCollection => {
                    // eslint-disable-next-line no-restricted-syntax
                    for (const category of categoryCollection) {
                        const ids = category.products.getIds();
                        ids.forEach(id => this.productIds.push(id));
                    }
                    this.updateProductList();
                });
        },

        updateProductList() {
            this.total = this.products.total;
            const productCriteria = new Criteria(this.page, this.limit);
            this.naturalSorting = this.sortBy === 'productNumber';
            productCriteria.setTerm(this.term);
            productCriteria.addFilter(Criteria.equals('product.parentId', null));
            productCriteria.addFilter(Criteria.equalsAny('product.id', this.productIds));
            productCriteria.addSorting(
                Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting),
            );
            productCriteria.addAssociation('cover');
            productCriteria.addAssociation('manufacturer');

            const currencyCriteria = new Criteria(1, 500);
            return Promise.all([
                this.productRepository.search(productCriteria, Shopware.Context.api),
                this.currencyRepository.search(currencyCriteria, Shopware.Context.api),
            ])
                .then(result => {
                    const [products, currencies] = result;
                    this.total = products.total;
                    products.forEach(product => {
                        // eslint-disable-next-line no-param-reassign
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
                })
                .catch(() => {
                    this.isLoading = false;
                    this.productIds = [];
                });
        },

        onSelectAllProducts() {
            this.selection = this.products;
            this.totalSelected = this.selection.total;
            this.$refs.swProductGrid.records.forEach(item => {
                if (this.$refs.swProductGrid.isSelected(item.id) !== true) {
                    this.$refs.swProductGrid.selectItem(true, item);
                }
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

        onPublishOnLengow() {
            const lengowProductCriteria = new Criteria();
            lengowProductCriteria.addFilter(Criteria.equalsAny('productId', this.selection));
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
                });
        },

        OnActivateOnLengow(selected) {
            const selectedItem = Object.values(selected)[0];
            if (selectedItem.extensions.activeInLengow.active) {
                const lengowProduct = this.lengowProductRepository.create(Shopware.Context.api);
                lengowProduct.productId = selectedItem.id;
                lengowProduct.salesChannelId = this.currentSalesChannelId;
                this.lengowProductRepository.save(lengowProduct, Shopware.Context.api);
                return;
            }
            const lengowProductCriteria = new Criteria();
            lengowProductCriteria.addFilter(Criteria.equals('productId', selectedItem.id));
            lengowProductCriteria.addFilter(Criteria.equals('salesChannelId', this.currentSalesChannelId));
            this.lengowProductRepository
                .searchIds(lengowProductCriteria, Shopware.Context.api)
                .then(result =>
                    this.lengowProductRepository.delete(result.data[0], Shopware.Context.api),
                );
        },

        updateTotal({ total }) {
            this.total = total;
        },

        getCurrencyPriceByCurrencyId(currencyId, prices) {
            const priceForProduct = prices.find(price => price.currencyId === currencyId);

            if (priceForProduct) {
                return priceForProduct;
            }

            return {
                currencyId: null,
                gross: null,
                linked: true,
                net: null,
            };
        },

        getProductColumns() {
            return [
                {
                    property: 'extensions.activeInLengow.active',
                    label: 'active in lengow',
                    inlineEdit: 'boolean',
                    align: 'center',
                },
                {
                    property: 'name',
                    label: this.$tc('sw-product.list.columnName'),
                    routerLink: 'sw.product.detail',
                    inlineEdit: 'string',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'productNumber',
                    naturalSorting: true,
                    label: 'product ID',
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'manufacturer.name',
                    label: 'Brand',
                    allowResize: true,
                },
                {
                    property: 'active',
                    label: `${this.$tc('sw-product.list.columnActive')}`,
                    inlineEdit: 'boolean',
                    allowResize: true,
                    align: 'center',
                },
                ...this.currenciesColumns,
                {
                    property: 'stock',
                    label: this.$tc('sw-product.list.columnInStock'),
                    inlineEdit: 'number',
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'availableStock',
                    label: this.$tc('sw-product.list.columnAvailableStock'),
                    allowResize: true,
                    align: 'right',
                },
            ];
        },

        onColumnSort(column) {
            this.$refs.swProductGrid.loading = true;

            const context = { ...Shopware.Context.api };
            context.currencyId = column.currencyId;

            return this.$refs.swProductGrid.repository
                .search(this.$refs.swProductGrid.items.criteria, context)
                .then(this.$refs.swProductGrid.applyResult);
        },
    },
});

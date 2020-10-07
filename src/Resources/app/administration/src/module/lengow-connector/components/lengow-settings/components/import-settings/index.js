import template from "./views/import-settings.html.twig";

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lengow-import-settings', {
    template,

    inject: ['repositoryFactory'],

    props: {
        config: {
            type: Object,
            required: true,
            default: {},
        },
        onSaveSettings: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            lengowImportShipMpEnabled: false,
            lengowImportStockshipMp: false,
            lengowImportDays: 3,
            lengowReportMailEnabled: null,
            lengowReportMailAddress: [],
            lengowCurrencyConversion: false,
            lengowDebugEnabled: false,
            salesChannels: [],
            shippingMethods: [],
            render: false,
        }
    },

    created() {
        const salesChannelCriteria = new Criteria();
        salesChannelCriteria.addAssociation('domains');
        this.salesChannelRepository.search(salesChannelCriteria, Shopware.Context.api).then(result => {
            result.forEach(salesChannel => {
                const shippingMethodCriteria = new Criteria();
                shippingMethodCriteria.getAssociation('salesChannels');
                shippingMethodCriteria.addFilter(Criteria.equals('salesChannels.id', salesChannel.id));
                this.shippingMethodRepository.search(shippingMethodCriteria, Shopware.Context.api).then(result => {
                    result.forEach(shippingMethod => {
                        this.shippingMethods = [...this.shippingMethods, {
                            salesChannelId: salesChannel.id,
                            salesChannelName: salesChannel.name,
                            name: shippingMethod.name,
                            value: shippingMethod.id
                        }];
                    });
                });
                this.getConfigImportDefaultShippingMethod(salesChannel.id).then(defaultShippingMethod => {
                    this.salesChannels = [...this.salesChannels, {
                        salesChannelId: salesChannel.id,
                        label: salesChannel.name,
                        value: salesChannel.id,
                        defaultShippingMethod: defaultShippingMethod,
                    }];
                    this.render = true;
                })
            });
        });
        this.lengowImportShipMpEnabled = this.config.lengowImportShipMpEnabled.value === '1';
        this.lengowImportStockshipMp = this.config.lengowImportStockShipMp.value === '1';
        this.lengowImportDays = this.config.lengowImportDays.value;
        this.lengowReportMailEnabled = this.config.lengowReportMailEnabled.value === '1';
        this.lengowReportMailAddress = this.config.lengowReportMailAddress.value;
        this.lengowCurrencyConversion = this.config.lengowCurrencyConversion.value === '1';
        this.lengowDebugEnabled = this.config.lengowDebugEnabled.value === '1';
    },

    computed: {
        systemConfigRepository() {
            return this.repositoryFactory.create('system_config');
        },

        lengowConfigRepository() {
            return this.repositoryFactory.create('lengow_settings');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        shippingMethodRepository() {
            return this.repositoryFactory.create('shipping_method');
        },

        salesChannelShippingMethodRepository() {
            return this.repositoryFactory.create('sales_channel_shipping_method');
        },
    },

    methods: {
        getConfigImportDefaultShippingMethod(salesChannelId) {
            let defaultShippingMethodId = '';
            this.config.lengowImportDefaultShippingMethod.forEach(defaultShippingMethod => {
                if (defaultShippingMethod.salesChannel.id === salesChannelId) {
                    defaultShippingMethodId = defaultShippingMethod.value;
                }
            });
            if (defaultShippingMethodId === '') {
                return 'Not found';
            }
            const shippingMethodCriteria = new Criteria();
            shippingMethodCriteria.addFilter(Criteria.equals('id', defaultShippingMethodId));
            return this.shippingMethodRepository.search(shippingMethodCriteria, Shopware.Context.api).then(result => {
                return result.total !== 0 ? result.first().id : 'Not found';
            });
        },
    },

});

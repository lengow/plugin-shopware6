import template from './lgw-setting-import.html.twig';
import './lgw-setting-import.scss';

const {
    Component,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-setting-import', {
    template,

    inject: ['repositoryFactory','LengowConnectorSyncService'],

    props: {
        config: {
            type: Object,
            required: true,
            default: {}
        },
        onSaveSettings: {
            type: Object,
            required: true
        },
        onChangeStatus: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            lengowImportShipMpEnabled: false,
            lengowImportStockShipMp: false,
            //lengowSendReturnTrackingNumber: false,
            lengowImportDays: 3,
            lengowReportMailEnabled: null,
            lengowReportMailAddress: [],
            lengowCurrencyConversion: false,
            lengowImportB2b: false,
            lengowDebugEnabled: false,
            salesChannels: [],
            shippingMethods: [],
            render: false
        };
    },

    created() {
        const salesChannelCriteria = new Criteria();
        salesChannelCriteria.addAssociation('domains');
        this.salesChannelRepository.search(salesChannelCriteria, Shopware.Context.api).then(result => {
            result.forEach(salesChannel => {
                const shippingMethodCriteria = new Criteria();
                shippingMethodCriteria.getAssociation('salesChannels');
                shippingMethodCriteria.addFilter(Criteria.equals('salesChannels.id', salesChannel.id));
                // eslint-disable-next-line no-shadow
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
                        defaultShippingMethod: defaultShippingMethod
                    }];
                    this.render = true;
                });
            });
        });
        this.lengowImportShipMpEnabled = this.config.lengowImportShipMpEnabled.value === '1';
        this.lengowImportStockShipMp = this.config.lengowImportStockShipMp.value === '1';
        //this.lengowSendReturnTrackingNumber = this.config.lengowSendReturnTrackingNumber.value === '1';
        this.lengowImportDays = this.config.lengowImportDays.value;
        this.lengowReportMailEnabled = this.config.lengowReportMailEnabled.value === '1';
        this.lengowReportMailAddress = this.config.lengowReportMailAddress.value;
        this.lengowCurrencyConversion = this.config.lengowCurrencyConversion.value === '1';
        this.lengowImportB2b = this.config.lengowImportB2b.value === '1';
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
        }
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

        onChangeStatus(event, key) {
            this.LengowConnectorSyncService.onChangeStatus().then(result => {
                if (!result.success) {
                    console.error("Failure to create custom field :", result.error);
                }
            });
        }
    }
});

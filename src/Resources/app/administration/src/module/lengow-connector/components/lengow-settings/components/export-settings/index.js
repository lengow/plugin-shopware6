import template from "./views/export-settings.html.twig";

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lengow-export-settings', {
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
            salesChannels: [],
            shippingMethods: [],
            render: false,
        }
    },

    async created() {
        const salesChannelCriteria = new Criteria();
        salesChannelCriteria.addAssociation('domains');
        this.salesChannelRepository.search(salesChannelCriteria, Shopware.Context.api).then(result => {
            result.forEach(salesChannel => {
                this.getShippingMethod(salesChannel).then(() => {
                    this.getConfigExportDefaultShippingMethod(salesChannel.id).then(defaultShippingMethod => {
                        this.salesChannels = [ ...this.salesChannels, {
                            salesChannelId: salesChannel.id,
                            name: salesChannel.name,
                            value: salesChannel.id,
                            defaultShippingMethod: defaultShippingMethod,
                            exportDisabled: this.getConfigExportDisabledProduct(salesChannel.id),
                            exportSelection: this.getConfigExportSelection(salesChannel.id)
                        }
                        ];
                        this.render = true;
                    })
                });
            });
        });
    },

    computed: {
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
        getConfigExportDefaultShippingMethod(salesChannelId) {
            let defaultShippingMethodId = '';
            this.config.lengowExportDefaultShippingMethod.forEach(defaultShippingMethod => {
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

        getConfigExportDisabledProduct(salesChannelId) {
            return this.config.lengowExportDisabledProduct.some(
                elem => elem.salesChannel.id === salesChannelId && elem.value === '1'
            );
        },

        getConfigExportSelection(salesChannelId) {
            return this.config.lengowSelectionEnabled.some(
                elem => elem.salesChannel.id === salesChannelId && elem.value === '1'
            );
        },

        getShippingMethod(salesChannel) {
            const shippingMethodCriteria = new Criteria();
            shippingMethodCriteria.getAssociation('salesChannels');
            shippingMethodCriteria.addFilter(Criteria.equals('salesChannels.id', salesChannel.id));
            return this.shippingMethodRepository.search(shippingMethodCriteria, Shopware.Context.api).then(result => {
                result.forEach(shippingMethod => {
                    this.shippingMethods = [...this.shippingMethods,
                        {
                            salesChannelId: salesChannel.id,
                            salesChannelName: salesChannel.name,
                            name: shippingMethod.name,
                            value: shippingMethod.id
                        }];
                });
            });
        },
    },

});

import template from './lgw-setting-export.html.twig';
import './lgw-setting-export.scss';

const {
    Component,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-setting-export', {
    template,

    inject: ['repositoryFactory'],

    props: {
        config: {
            type: Object,
            required: true,
            default: {}
        },
        onSaveSettings: {
            type: Function,
            required: true
        }
    },

    data() {
        return {
            salesChannels: [],
            shippingMethods: [],
            render: false
        };
    },

    async created() {
        const salesChannelCriteria = new Criteria();
        salesChannelCriteria.addAssociation('domains');
        this.salesChannelRepository.search(salesChannelCriteria, Shopware.Context.api).then(result => {
            result.forEach(salesChannel => {
                this.getShippingMethod(salesChannel).then(() => {
                    this.getConfigExportDefaultShippingMethod(salesChannel.id).then(defaultShippingMethod => {
                        this.salesChannels = [...this.salesChannels, {
                            salesChannelId: salesChannel.id,
                            name: salesChannel.name,
                            value: salesChannel.id,
                            defaultShippingMethod: defaultShippingMethod,
                            exportDisabled: this.getConfigExportDisabledProduct(salesChannel.id),
                            exportSelection: this.getConfigExportSelection(salesChannel.id)
                        }
                        ];
                        this.render = true;
                    });
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
        }
    },

    methods: {
        getConfigEntries(key) {
            const value = this.config[key];
            return Array.isArray(value) ? value : [];
        },

        salesChannelMatches(entry, salesChannelId) {
            if (!entry) {
                return false;
            }
            const entrySalesChannelId = entry.salesChannel?.id || entry.salesChannelId;
            return entrySalesChannelId === salesChannelId;
        },

        getConfigExportDefaultShippingMethod(salesChannelId) {
            let defaultShippingMethodId = '';
            this.getConfigEntries('lengowExportDefaultShippingMethod').forEach(defaultShippingMethod => {
                if (this.salesChannelMatches(defaultShippingMethod, salesChannelId)) {
                    defaultShippingMethodId = defaultShippingMethod.value;
                }
            });
            if (defaultShippingMethodId === '') {
                return Promise.resolve('Not found');
            }
            const shippingMethodCriteria = new Criteria();
            shippingMethodCriteria.addFilter(Criteria.equals('id', defaultShippingMethodId));
            return this.shippingMethodRepository.search(shippingMethodCriteria, Shopware.Context.api).then(result => {
                return result.total !== 0 ? result.first().id : 'Not found';
            });
        },

        getConfigExportDisabledProduct(salesChannelId) {
            return this.getConfigEntries('lengowExportDisabledProduct').some(
                elem => this.salesChannelMatches(elem, salesChannelId) && elem.value === '1'
            );
        },

        getConfigExportSelection(salesChannelId) {
            return this.getConfigEntries('lengowSelectionEnabled').some(
                elem => this.salesChannelMatches(elem, salesChannelId) && elem.value === '1'
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
                            value: shippingMethod.id,
                            label: shippingMethod.name
                        }];
                });
            });
        },
        
        getShippingMethodsForSalesChannel(salesChannelId) {
            return this.shippingMethods.filter(method => method.salesChannelId === salesChannelId);
        }
    }
});

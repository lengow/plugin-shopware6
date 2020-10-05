import template from "./views/general-settings.html.twig";

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lengow-general-settings', {
    template,

    inject: ['repositoryFactory'],

    props: {
        locked: {
            type: Boolean,
            required: false,
            default: true,
        },
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
            lengowAccountId: '',
            lengowAccessToken: '',
            lengowSecretToken: '',
            lengowIpEnabled: false,
            lengowAuthorizedIp: '',
            lengowTrackingEnabled: false,
            lengowTrackingId: '',
            lengowTimezone: '',
            credentialLocked: true,
            render: false,
        }
    },

    created() {
        const salesChannelCriteria = new Criteria();
        salesChannelCriteria.addAssociation('domains');
        this.salesChannelRepository.search(salesChannelCriteria, Shopware.Context.api).then(result => {
                result.forEach(salesChannel => {
                    this.salesChannels = [...this.salesChannels,  {
                        salesChannelId: salesChannel.id,
                        label: salesChannel.name,
                        value: salesChannel.id,
                        enabled: this.getConfigSalesChannelEnabledValue(salesChannel.id),
                        catalogId: this.getConfigCatalogIdValue(salesChannel.id),
                    }];
                });
                this.render = true;
        });
        this.lengowAccountId = this.config.lengowAccountId.value;
        this.lengowAccessToken = this.config.lengowAccessToken.value;
        this.lengowSecretToken = this.config.lengowSecretToken.value;
        this.lengowIpEnabled = this.config.lengowIpEnabled.value === '1';
        this.lengowAuthorizedIp = this.config.lengowAuthorizedIp.value;
        this.lengowTrackingEnabled = this.config.lengowTrackingEnabled.value === '1';
        this.lengowTrackingId = this.config.lengowTrackingId.value;
        this.lengowTimezone = this.config.lengowTimezone.value;
        this.credentialLocked = this.config.lengowDebugEnabled.value === '1';
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
    },

    methods: {
        getConfigCatalogIdValue(salesChannelId) {
            const catalogIdValue = this.config.lengowCatalogId.find(elem =>
                elem.salesChannel.id === salesChannelId
            );
            if (catalogIdValue !== 'undefined') {
                return catalogIdValue.value;
            }
            return '';
        },

        getConfigSalesChannelEnabledValue(salesChannelId) {
            return this.config.lengowStoreEnabled.some(
                elem => elem.salesChannel.id === salesChannelId && elem.value === '1'
            );
        }
    },

});

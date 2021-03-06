import template from './lgw-setting.html.twig';
import './lgw-setting.scss';

const {
    Component,
    Mixin,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-setting', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            selectedTabGeneral: true, // default tab is general
            selectedTabExport: false,
            selectedTabImport: false,
            configLoaded: false,
            config: {},
            generalSettingsKey: 0
        };
    },

    // when created, retrieve all settings and pass them to child components via props
    async created() {
        // eslint-disable-next-line no-return-await
        return await this.loadConfig();
    },

    computed: {
        systemConfigRepository() {
            return this.repositoryFactory.create('system_config');
        },

        lengowConfigRepository() {
            return this.repositoryFactory.create('lengow_settings');
        },

        shippingMethodRepository() {
            return this.repositoryFactory.create('shipping_method');
        }
    },

    methods: {
        onChangeSelectedTab(selectedTab) {
            switch (selectedTab) {
                case 'export':
                    this.selectedTabExport = true;
                    this.selectedTabImport = false;
                    this.selectedTabGeneral = false;
                    break;
                case 'import':
                    this.selectedTabExport = false;
                    this.selectedTabImport = true;
                    this.selectedTabGeneral = false;
                    break;
                default:
                    this.selectedTabExport = false;
                    this.selectedTabImport = false;
                    this.selectedTabGeneral = true;
            }
        },

        onSaveSettings(event, key, salesChannelId) {
            let reload = false;
            const lengowConfigCriteria = new Criteria();
            if (salesChannelId) {
                lengowConfigCriteria.addFilter(Criteria.equals('salesChannelId', salesChannelId));
            }
            lengowConfigCriteria.addFilter(Criteria.equals('name', key));
            // eslint-disable-next-line consistent-return
            this.lengowConfigRepository.search(lengowConfigCriteria, Shopware.Context.api).then(result => {
                if (result.total > 0) {
                    const lengowConfig = result.first();
                    if (typeof event === 'boolean') {
                        lengowConfig.value = event ? '1' : '0';
                    } else {
                        lengowConfig.value = String(event);
                    }
                    if (key === 'lengowDebugEnabled') { // activate debug mode need to reload config data
                        reload = true;
                    }
                    // eslint-disable-next-line no-shadow,consistent-return,no-unused-vars
                    return this.lengowConfigRepository.sync([lengowConfig], Shopware.Context.api).then(result => {
                        if (reload) {
                            this.generalSettingsKey += 1;
                            this.configLoaded = false;
                            return this.loadConfig();
                        }
                    });
                }
            }).finally(() => {
                this.createNotificationInfo({
                    message: `${this.$tc('lengow-connector.setting.lengow_main_setting.saved_message_1')
                    } ${key} ${this.$tc('lengow-connector.setting.lengow_main_setting.saved_message_2')}`
                });
            });
        },

        async loadConfig() {
            const lengowConfigCriteria = new Criteria(1, 500);
            // eslint-disable-next-line no-return-await
            return await this.lengowConfigRepository.search(lengowConfigCriteria, Shopware.Context.api).then(result => {
                result.forEach(config => {
                    if (config.salesChannel) {
                        if (typeof this.config[config.name] === 'undefined') {
                            this.config[config.name] = [];
                        }
                        this.config[config.name].push({
                            name: config.name,
                            value: config.value,
                            salesChannel: config.salesChannel
                        });
                    } else {
                        this.config[config.name] = {
                            name: config.name,
                            value: config.value,
                            salesChannel: config.salesChannel
                        };
                    }
                });
                this.configLoaded = true;
            });
        }
    }
});

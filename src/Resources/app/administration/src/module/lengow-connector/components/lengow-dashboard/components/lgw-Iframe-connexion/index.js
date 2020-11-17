import template from './views/lgw-iframe-connexion.html.twig';
import {envMixin, LENGOW_URL, LENGOW_CMS_URL} from "../../../../../const";

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lgw-iframe-connexion', {
    template,

    mixins: [envMixin],

    inject: [
        'LengowSynchronisationService',
        'repositoryFactory',
    ],

    data() {
        return {
            iframeSource: '',
            syncData : null,
        }
    },

    computed: {
        systemConfigRepository() {
            return this.repositoryFactory.create('system_config');
        },

        lengowConfigRepository() {
            return this.repositoryFactory.create('lengow_settings');
        },
    },

    created() {
        this.setupIframe();
    },

    methods: {
        setupIframe() {
            this.iframeSource = LENGOW_CMS_URL + '?lang=en&clientType=shopware';
            window.addEventListener("message", message => {
                switch (message.data.function) {
                    case 'sync':
                        this.saveSyncData('lengowAccountId', message.data.parameters.account_id, false);
                        this.saveSyncData('lengowAccessToken', message.data.parameters.access_token, false);
                        this.saveSyncData('lengowSecretToken', message.data.parameters.secret_token, true);
                        break;
                    case 'sync_and_reload':
                        this.saveSyncData('lengowAccountId', message.data.parameters.account_id, false);
                        this.saveSyncData('lengowAccessToken', message.data.parameters.access_token, false);
                        this.saveSyncData('lengowSecretToken', message.data.parameters.secret_token, true);
                        location.reload();
                        break;
                    case 'reload':
                    case 'cancel':
                        location.reload();
                    default:
                        console.log(message);
                }
            });
        },

        onLoadIframe() {
            this.LengowSynchronisationService.getSyncData().then(syncData => {
                document.getElementById('lgw-sync-iframe').contentWindow.postMessage(syncData, '*');
            });
        },

        saveSyncData(key, data, reload) {
            if (data !== undefined) {
                let lengowConfigCriteria = new Criteria();
                lengowConfigCriteria.addFilter(Criteria.equals('name', key));
                return this.lengowConfigRepository.search(lengowConfigCriteria, Shopware.Context.api).then(result => {
                    if (result.total > 0) {
                        const lengowConfig = result.first();
                        lengowConfig.value = String(data);
                        this.lengowConfigRepository.sync([lengowConfig], Shopware.Context.api).then(() => {
                            if (reload) {
                                window.location.reload();
                            }
                        });
                    }
                });
            }

        }
    },

});

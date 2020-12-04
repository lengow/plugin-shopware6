import template from './lgw-dashboard-connexion.html.twig';
import './lgw-dashboard-connexion.scss';
import { envMixin, LENGOW_CMS_URL } from '../../../const';

const {
    Component,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-dashboard-connexion', {
    template,

    mixins: [envMixin],

    inject: ['LengowConnectorSyncService', 'repositoryFactory'],

    data() {
        return {
            iframeSource: '',
            syncData: null
        };
    },

    computed: {
        lengowConfigRepository() {
            return this.repositoryFactory.create('lengow_settings');
        }
    },

    created() {
        this.setupIframe();
    },

    methods: {
        setupIframe() {
            this.iframeSource = `${LENGOW_CMS_URL}?lang=en&clientType=shopware`;
            window.addEventListener('message', message => {
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
                        // eslint-disable-next-line no-restricted-globals
                        location.reload();
                        break;
                    case 'reload':
                    case 'cancel':
                        // eslint-disable-next-line no-restricted-globals
                        location.reload();
                    // eslint-disable-next-line no-fallthrough
                    default:
                        console.log(message);
                }
            });
        },

        onLoadIframe() {
            this.LengowConnectorSyncService.getSyncData().then(syncData => {
                document.getElementById('lgw-sync-iframe').contentWindow.postMessage(syncData, '*');
            });
        },

        // eslint-disable-next-line consistent-return
        saveSyncData(key, data, reload) {
            if (data !== undefined) {
                const lengowConfigCriteria = new Criteria();
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
    }

});

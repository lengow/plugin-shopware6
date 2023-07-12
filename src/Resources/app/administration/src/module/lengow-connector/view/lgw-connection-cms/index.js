import template from './lgw-connection-cms.html.twig';
import './lgw-connection-cms.scss';

const {
    Component,
    Data: { Criteria }
} = Shopware;

const { mapState } = Shopware.Component.getComponentHelper();

Component.register('lgw-connection-cms', {
    template,

    inject: [
        'LengowConnectorConnectionService',
        'LengowConnectorSyncService',
        'repositoryFactory'
    ],

    data() {
        return {
            isLoading: false,
            lengowUrl: 'https://my.lengow.net',
            preprod: false,
            connectionButtonDisabled: true,
            showCredentialForm: true,
            credentialsValid: false,
            cmsConnected: false,
            hasCatalogToLink: false,
            accessToken: '',
            secret: '',
            helpCenterLink: '',
            supportLink: ''
        };
    },

    computed: {
        ...mapState('lgwConnection', ['catalogList']),
        lengowConfigRepository() {
            return this.repositoryFactory.create('lengow_settings');
        }
    },

    created() {
        this.loadEnvironmentUrl();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (this.lengowUrl === 'https://my.lengow.net') {
                this.preprod = true;
            }
            this.LengowConnectorSyncService.getPluginLinks().then(result => {
                if (result.success) {
                    this.helpCenterLink = result.links.help_center;
                    this.supportLink = result.links.support;
                }
            });
            this.isLoading = false;
        },

        loadEnvironmentUrl() {
            const lengowConfigCriteria = new Criteria();
            lengowConfigCriteria.addFilter(Criteria.equals('name', 'lengowEnvironmentUrl'));
            this.lengowConfigRepository.search(lengowConfigCriteria, Shopware.Context.api).then(result => {
                if (result.total > 0) {
                    this.lengowUrl = 'https://my.lengow' + result[0].value;
                    this.createdComponent();
                }
            });
        },

        connectCms() {
            this.isLoading = true;
            const accessToken = this.accessToken;
            const secret = this.secret;
            this.LengowConnectorConnectionService.checkApiCredentials({ accessToken, secret }).then(response => {
                this.credentialsValid = response.success;
                if (this.credentialsValid) {
                    this.LengowConnectorConnectionService.connectCms().then(result => {
                        this.cmsConnected = result.success;
                        if (this.cmsConnected) {
                            this.LengowConnectorConnectionService.getCatalogList().then(catalogList => {
                                Shopware.State.commit('lgwConnection/setCatalogList', catalogList);
                            }).finally(() => {
                                this.hasCatalogToLink = this.catalogList.length > 0;
                                this.showCredentialForm = false;
                                this.isLoading = false;
                            });
                        } else {
                            this.showCredentialForm = false;
                            this.isLoading = false;
                        }
                    });
                } else {
                    this.showCredentialForm = false;
                    this.isLoading = false;
                }
            });
        },

        handleChangeCredentials(field, value) {
            if (field === 'accessToken') {
                this.accessToken = value;
            } else if (field === 'secret') {
                this.secret = value;
            }
            this.connectionButtonDisabled = !(this.accessToken !== '' && this.secret !== '');
        },

        retryConnection() {
            this.isLoading = true;
            this.accessToken = '';
            this.secret = '';
            this.cmsConnected = false;
            this.connectionButtonDisabled = true;
            this.credentialsValid = false;
            this.showCredentialForm = true;
            this.isLoading = false;
        }
    }
});

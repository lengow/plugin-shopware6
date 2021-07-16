import template from './lgw-connection-catalog.html.twig';

const {
    Component,
    Data: { Criteria }
} = Shopware;
const { mapState } = Shopware.Component.getComponentHelper();

Component.register('lgw-connection-catalog', {
    template,

    inject: [
        'repositoryFactory',
        'LengowConnectorConnectionService',
        'LengowConnectorSyncService'
    ],

    data() {
        return {
            isLoading: false,
            buttonDisabled: true,
            salesChannels: [],
            salesChannelLoaded: false,
            nbCatalog: 0,
            hasError: false,
            helpCenterLink: '',
            supportLink: ''
        };
    },

    computed: {
        ...mapState('lgwConnection', ['catalogList', 'catalogSelected', 'optionIsLoading']),

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.nbCatalog = this.catalogList.length;
            this.buttonDisabled = this.nbCatalog === 0;
            this.LengowConnectorSyncService.getPluginLinks().then(result => {
                if (result.success) {
                    this.helpCenterLink = result.links.help_center;
                    this.supportLink = result.links.support;
                }
            });
            const salesChannelCriteria = new Criteria();
            salesChannelCriteria.addAssociation('domains');
            this.salesChannelRepository.search(salesChannelCriteria, Shopware.Context.api)
                .then(response => {
                    this.salesChannels = response;
                    this.initCatalogSelected();
                }).finally(() => {
                    this.salesChannelLoaded = true;
                    this.isLoading = false;
                });
        },

        initCatalogSelected() {
            const catalogSelected = [];
            this.salesChannels.forEach((salesChannel) => {
                catalogSelected[salesChannel.id] = [];
            });
            Shopware.State.commit('lgwConnection/setCatalogSelected', catalogSelected);
        },

        catalogSelectionChanged(salesChannelId, value) {
            const catalogSelected = this.catalogSelected;
            catalogSelected[salesChannelId] = value;
            Shopware.State.commit('lgwConnection/setCatalogSelected', catalogSelected);
            this.salesChannels.forEach((salesChannel) => {
                Shopware.State.commit('lgwConnection/setOptionIsLoading', [salesChannel.id, true]);
            });
            Shopware.State.commit('lgwConnection/setCatalogSelectionChanged', true);
        },

        catalogOptionsLoaded() {
            if (!this.optionIsLoading) {
                Shopware.State.commit('lgwConnection/setCatalogSelectionChanged', false);
            }
        },

        linkCatalogs() {
            this.isLoading = true;
            const catalogSelected = [];
            Object.keys(this.catalogSelected).forEach(salesChannelId => {
                const catalogs = this.catalogSelected[salesChannelId];
                catalogs.forEach(catalogId => {
                    catalogSelected.push({ salesChannelId: salesChannelId, catalogId: catalogId });
                });
            });
            this.LengowConnectorConnectionService.saveCatalogsLinked({ catalogSelected }).then(response => {
                if (response.success) {
                    this.redirectToDashboard();
                } else {
                    this.isLoading = false;
                    this.hasError = true;
                }
            });
        },

        redirectToDashboard() {
            this.$router.push({ name: 'lengow.connector.dashboard' });
        },

        retryMatching() {
            this.isLoading = true;
            this.initCatalogSelected();
            this.hasError = false;
            this.isLoading = false;
        }
    }
});

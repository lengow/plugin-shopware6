import template from './lgw-dashboard.html.twig';

const { Component } = Shopware;

Component.register('lgw-dashboard', {
    template,

    inject: ['LengowConnectorSyncService'],

    data() {
        return {
            isLoading: true,
            freeTrialEnabled: false,
            trialExpired: false,
            newVersionIsAvailable: false,
            showUpdateModal: false,
            accountStatusData: [],
            pluginData: []
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.LengowConnectorSyncService.getAccountStatus(false).then(result => {
                if (result.success) {
                    this.accountStatusData = result;
                    this.freeTrialEnabled = this.accountStatusData.type === 'free_trial';
                    if (this.freeTrialEnabled && this.accountStatusData.expired) {
                        this.trialExpired = true;
                        this.isLoading = false;
                    } else {
                        this.LengowConnectorSyncService.getPluginData().then(result => {
                            if (result.success) {
                                this.pluginData = result.plugin_data;
                                this.newVersionIsAvailable = this.pluginData.new_version_is_available;
                                this.showUpdateModal = this.pluginData.show_update_modal;
                            }
                        }).finally(() => {
                            this.isLoading = false;
                        });
                    }
                }
            });
        },

        openUpdateModal() {
            this.showUpdateModal = true;
        },

        closeUpdateModal() {
            this.showUpdateModal = false;
        }
    }
});

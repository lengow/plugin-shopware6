import template from './lgw-dashboard.html.twig';

const { Component } = Shopware;

Component.register('lgw-dashboard', {
    template,

    inject: ['LengowConnectorSyncService'],

    mixins: [],

    data() {
        return {
            isLoading: true,
            trialExpired: false
        };
    },

    computed: {},

    created() {
        this.isTrialExpired();
    },

    methods: {
        isTrialExpired() {
            this.LengowConnectorSyncService.getAccountStatus(false).then(result => {
                if (result.success && result.type === 'free_trial') {
                    this.trialExpired = result.expired;
                }
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
});

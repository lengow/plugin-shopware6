import template from './lgw-free-trial-warning.html.twig';
import './lgw-free-trial-warning.scss';

const { Component } = Shopware;

Component.register('lgw-free-trial-warning', {
    template,

    inject: ['LengowConnectorSyncService'],

    props: {
        setTrialExpired: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            freeTrialEnabled: false,
            dayLeft: '',
            isExpired: false,
        };
    },

    created() {
        this.LengowConnectorSyncService.getAccountStatus(false).then(result => {
            if (result.success) {
                this.freeTrialEnabled = result.type === 'free_trial';
                this.dayLeft = result.day;
                this.isExpired = result.expired;
                if (this.isExpired) {
                    this.setTrialExpired();
                }
            }
        });
    },
});

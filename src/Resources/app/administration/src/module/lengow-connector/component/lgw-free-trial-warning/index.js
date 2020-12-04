import template from './lgw-free-trial-warning.html.twig';
import './lgw-free-trial-warning.scss';
import { LENGOW_URL } from '../../../const';

const { Component } = Shopware;

Component.register('lgw-free-trial-warning', {
    template,

    inject: ['LengowConnectorSyncService'],

    data() {
        return {
            freeTrialEnabled: false,
            dayLeft: '',
            isExpired: false,
            link: LENGOW_URL
        };
    },

    created() {
        this.LengowConnectorSyncService.getAccountStatus(false).then(result => {
            if (result.success) {
                this.freeTrialEnabled = result.type === 'free_trial';
                this.dayLeft = result.day;
                this.isExpired = result.expired;
            }
        });
    }
});

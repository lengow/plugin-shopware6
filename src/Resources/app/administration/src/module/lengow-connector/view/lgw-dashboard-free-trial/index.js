import template from './lgw-dashboard-free-trial.html.twig';
import './lgw-dashboard-free-trial.scss';
import { LENGOW_URL } from '../../../const';

const { Component } = Shopware;

Component.register('lgw-dashboard-free-trial', {
    template,

    inject: ['LengowConnectorSyncService'],

    data() {
        return {
            lengow_url: LENGOW_URL
        };
    },

    methods: {
        reloadAccountStatus() {
            this.LengowConnectorSyncService.getAccountStatus(true).then(result => {
                if (result.success) {
                    window.location.reload();
                }
            });
        }
    }
});

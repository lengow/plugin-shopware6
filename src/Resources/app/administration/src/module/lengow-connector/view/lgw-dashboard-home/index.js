import template from './lgw-dashboard-home.html.twig';
import './lgw-dashboard-home.scss';
import { LENGOW_URL } from '../../../const';

const { Component } = Shopware;

Component.register('lgw-dashboard-home', {
    template,

    inject: ['LengowConnectorSyncService'],

    data() {
        return {
            lengow_url: LENGOW_URL,
            helpCenterLink: ''
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.LengowConnectorSyncService.getPluginLinks().then(result => {
                if (result.success) {
                    this.helpCenterLink = result.links.help_center;
                }
            });
        }
    }
});

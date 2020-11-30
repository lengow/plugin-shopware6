import template from './lgw-update-warning.html.twig';
import './lgw-update-warning.scss';

import { LENGOW_URL } from '../../../const';

const { Component } = Shopware;

Component.register('lgw-update-warning', {
    template,

    inject: ['LengowConnectorSyncService'],

    data() {
        return {
            display: false,
            newVersion: null,
            link: '',
        };
    },

    computed: {},

    created() {
        this.LengowConnectorSyncService.getPluginData().then(data => {
            if (data.success && data.should_update) {
                this.display = true;
                this.newVersion = data.plugin_data.version;
                this.link = LENGOW_URL + data.plugin_data.download_link;
            }
        });
    },
});

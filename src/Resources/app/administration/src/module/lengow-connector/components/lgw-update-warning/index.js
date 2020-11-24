import template from './views/lgw-update-warning.html.twig';
import './views/lgw-update-warning.scss';

import {LENGOW_URL, MODULE_VERSION} from "../../../const";

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lgw-update-warning', {
    template,

    inject: ['repositoryFactory', 'LengowSynchronisationService',],

    data() {
        return {
            display: false,
            newVersion: null,
            link: '',
        }
    },

    computed: {
        lengowSettingsRepository() {
            return this.repositoryFactory.create('lengow_settings');
        },
    },

    created() {
        this.LengowSynchronisationService.getPluginData().then(data => {
            if (data.success && data.should_update) {
                this.display = true;
                this.newVersion = data.plugin_data.version;
                this.link = LENGOW_URL + data.plugin_data.download_link;
            }
        });
    },

});

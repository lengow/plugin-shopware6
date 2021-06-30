import template from './lgw-contact.html.twig';
import './lgw-contact.scss';

const { Component } = Shopware;

Component.register('lgw-contact', {
    template,

    inject: ['LengowConnectorSyncService'],

    data() {
        return {
            helpCenterLink: '',
            supportLink: ''
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
                    this.supportLink = result.links.support;
                }
            });
        }
    }
});

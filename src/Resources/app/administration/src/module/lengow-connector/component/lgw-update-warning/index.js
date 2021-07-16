import template from './lgw-update-warning.html.twig';
import './lgw-update-warning.scss';

const { Component } = Shopware;

Component.register('lgw-update-warning', {
    template,

    props: {
        pluginData: {
            type: Object,
            required: true
        },
        onClickDownload: {
            type: Object,
            required: true
        },
    },

    data() {
        return {
            version: ''
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.version = this.pluginData.version;
        },

        onClick() {
            this.onClickDownload();
        }
    }
});

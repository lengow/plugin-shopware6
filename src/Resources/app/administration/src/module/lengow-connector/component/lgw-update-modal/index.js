import template from './lgw-update-modal.html.twig';
import './lgw-update-modal.scss';
import { LENGOW_URL } from "../../../const";

const { Component } = Shopware;

Component.register('lgw-update-modal', {
    template,

    inject: ['LengowConnectorSyncService'],

    props: {
        pluginData: {
            type: Object,
            required: true
        },
        onClickClose: {
            type: Object,
            required: true
        },
    },

    data() {
        return {
            version: '',
            cmsMinVersion: '',
            cmsMaxVersion: '',
            extensions: [],
            changelogLink: '',
            updateGuideLink: '',
            supportLink: '',
            downloadLink: '',
            showRemindMeLater: false
        };
    },

    created() {
        this.createdComponent()
    },

    methods: {
        createdComponent() {
            this.version = this.pluginData.version;
            this.downloadLink = LENGOW_URL + this.pluginData.download_link;
            this.cmsMinVersion = this.pluginData.cms_min_version;
            this.cmsMaxVersion = this.pluginData.cms_max_version;
            this.extensions = this.pluginData.extensions;
            this.changelogLink = this.pluginData.links.changelog;
            this.updateGuideLink = this.pluginData.links.update_guide;
            this.supportLink = this.pluginData.links.support;
            this.showRemindMeLater = this.pluginData.show_update_modal;
        },

        remindMeLater() {
            this.LengowConnectorSyncService.remindMeLater().then(result => {
                if (result.success) {
                    this.closeModal();
                }
            });
        },

        closeModal() {
            this.showRemindMeLater = false;
            this.onClickClose();
        }
    }
});

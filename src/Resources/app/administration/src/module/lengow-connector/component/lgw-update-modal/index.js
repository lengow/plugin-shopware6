import template from './lgw-update-modal.html.twig';
import './lgw-update-modal.scss';

const {
    Component,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-update-modal', {
    template,

    inject: ['LengowConnectorSyncService', 'repositoryFactory'],

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
            showRemindMeLater: false,
            lengowEnvironmentUrl: 'https://my.lengow.net',
        };
    },

    computed: {
        lengowConfigRepository() {
            return this.repositoryFactory.create('lengow_settings');
        }
    },

    created() {
        this.loadEnvironmentUrl()
    },

    methods: {
        createdComponent() {
            this.version = this.pluginData.version;
            this.downloadLink = this.lengowEnvironmentUrl + this.pluginData.download_link;
            this.cmsMinVersion = this.pluginData.cms_min_version;
            this.cmsMaxVersion = this.pluginData.cms_max_version;
            this.extensions = this.pluginData.extensions;
            this.changelogLink = this.pluginData.links.changelog;
            this.updateGuideLink = this.pluginData.links.update_guide;
            this.supportLink = this.pluginData.links.support;
            this.showRemindMeLater = this.pluginData.show_update_modal;
        },

        loadEnvironmentUrl() {
            const lengowConfigCriteria = new Criteria();
            lengowConfigCriteria.addFilter(Criteria.equals('name', 'lengowEnvironmentUrl'));
            this.lengowConfigRepository.search(lengowConfigCriteria, Shopware.Context.api).then(result => {
                if (result.total > 0) {
                    this.lengowEnvironmentUrl = 'https://my.lengow' + result[0].value;
                    this.createdComponent();
                }
            });
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

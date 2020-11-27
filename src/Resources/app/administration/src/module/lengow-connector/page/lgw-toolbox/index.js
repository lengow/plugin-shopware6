import template from './lgw-toolbox.html.twig';
import './lgw-toolbox.scss';
import lgwToolboxState from './state';

const { Component } = Shopware;
const { mapGetters } = Shopware.Component.getComponentHelper();

Component.register('lgw-toolbox', {
    template,

    inject: ['LengowConnectorToolboxService'],

    mixins: [],

    data() {
        return {};
    },

    beforeCreate() {
        Shopware.State.registerModule('lgwToolbox', lgwToolboxState);
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        Shopware.State.unregisterModule('lgwToolbox');
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        ...mapGetters('lgwToolbox', ['isLoading']),
    },

    methods: {
        createdComponent() {
            this.loadOverviewData();
            this.loadChecksumData();
            this.loadLogData();
        },

        loadOverviewData() {
            Shopware.State.commit('lgwToolbox/setLoading', ['overview', true]);
            this.LengowConnectorToolboxService.getOverviewData()
                .then(response => {
                    Shopware.State.commit('lgwToolbox/setOverviewData', response);
                })
                .finally(() => {
                    Shopware.State.commit('lgwToolbox/setLoading', ['overview', false]);
                });
        },

        loadChecksumData() {
            Shopware.State.commit('lgwToolbox/setLoading', ['checksum', true]);
            this.LengowConnectorToolboxService.getChecksumData()
                .then(response => {
                    Shopware.State.commit('lgwToolbox/setChecksumData', response);
                })
                .finally(() => {
                    Shopware.State.commit('lgwToolbox/setLoading', ['checksum', false]);
                });
        },

        loadLogData() {
            Shopware.State.commit('lgwToolbox/setLoading', ['log', true]);
            this.LengowConnectorToolboxService.getLogData()
                .then(response => {
                    Shopware.State.commit('lgwToolbox/setLogData', response);
                })
                .finally(() => {
                    Shopware.State.commit('lgwToolbox/setLoading', ['log', false]);
                });
        },
    },
});

import template from './lgw-toolbox-base.html.twig';

const { Component } = Shopware;

Component.register('lgw-toolbox-base', {
    template,

    inject: ['LengowConnectorToolboxService'],

    mixins: [],

    data() {
        return {
            data: [],
            isLoading: false,
        };
    },

    created() {
        this.loadToolboxData();
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {},

    methods: {
        loadToolboxData() {
            this.isLoading = true;
            this.LengowConnectorToolboxService.getOverviewData()
                .then(response => {
                    this.data = response;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});

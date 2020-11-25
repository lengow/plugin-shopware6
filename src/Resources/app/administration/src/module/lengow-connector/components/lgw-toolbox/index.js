import template from './views/lgw-toolbox.html.twig';
import './views/lgw-toolbox.scss';
import lgwListElement from './components/lgw-list-element';

const { Component } = Shopware;

Component.register('lgw-toolbox', {
    template,

    inject: ['LengowConnectorToolboxService'],

    mixins: [],

    data() {
        return {
            data: [],
            dataIsLoading: false,
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
            this.dataIsLoading = true;
            this.LengowConnectorToolboxService.getAllData()
                .then(response => {
                    this.data = response;
                })
                .finally(() => {
                    this.dataIsLoading = false;
                });
        },
    },
});

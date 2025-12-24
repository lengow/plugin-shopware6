import template from './lgw-toolbox-base.html.twig';

const { Component } = Shopware;

Component.register('lgw-toolbox-base', {
    template,

    inject: [],

    mixins: [],

    data() {
        return {};
    },

    created() {},

    computed: {
        overviewData() {
            const state = Shopware.State.get('lgwToolbox');
            return state && state.overviewData ? state.overviewData : {
                checklist: {},
                plugin: {},
                synchronization: {},
                shops: []
            };
        },
        
        loading() {
            const state = Shopware.State.get('lgwToolbox');
            return state && state.loading ? state.loading : {};
        },

        isLoading() {
            const getters = Shopware.State.getters['lgwToolbox/isLoading'];
            return getters !== undefined ? getters : false;
        }
    },

    methods: {}
});

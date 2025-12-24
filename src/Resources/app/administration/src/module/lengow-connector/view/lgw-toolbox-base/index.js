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
            return state && state.overviewData ? state.overviewData : {};
        },
        
        loading() {
            const state = Shopware.State.get('lgwToolbox');
            return state && state.loading ? state.loading : {};
        },

        isLoading() {
            return Shopware.State.getters['lgwToolbox/isLoading'];
        }
    },

    methods: {}
});

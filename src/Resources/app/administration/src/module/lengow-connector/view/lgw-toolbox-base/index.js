import template from './lgw-toolbox-base.html.twig';

const { Component } = Shopware;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper() || {};

Component.register('lgw-toolbox-base', {
    template,

    inject: [],

    mixins: [],

    data() {
        return {};
    },

    created() {},

    computed: {
        ...(mapState ? mapState('lgwToolbox', ['overviewData', 'loading']) : {
            overviewData() {
                const state = Shopware.State.get('lgwToolbox');
                return state ? state.overviewData : {};
            },
            loading() {
                const state = Shopware.State.get('lgwToolbox');
                return state ? state.loading : {};
            }
        }),

        ...(mapGetters ? mapGetters('lgwToolbox', ['isLoading']) : {
            isLoading() {
                return Shopware.State.getters['lgwToolbox/isLoading'];
            }
        })
    },

    methods: {}
});

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
                return Shopware.State.get('lgwToolbox').overviewData;
            },
            loading() {
                return Shopware.State.get('lgwToolbox').loading;
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

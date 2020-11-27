import template from './lgw-toolbox-base.html.twig';

const { Component } = Shopware;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('lgw-toolbox-base', {
    template,

    inject: [],

    mixins: [],

    data() {
        return {};
    },

    created() {},

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        ...mapState('lgwToolbox', ['overviewData', 'loading']),

        ...mapGetters('lgwToolbox', ['isLoading']),
    },

    methods: {},
});

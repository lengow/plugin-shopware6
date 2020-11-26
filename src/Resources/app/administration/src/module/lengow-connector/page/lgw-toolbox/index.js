import template from './lgw-toolbox.html.twig';
import './lgw-toolbox.scss';

const { Component } = Shopware;

Component.register('lgw-toolbox', {
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

    computed: {},

    methods: {},
});

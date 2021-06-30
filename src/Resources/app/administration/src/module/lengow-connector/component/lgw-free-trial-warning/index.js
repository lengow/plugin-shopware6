import template from './lgw-free-trial-warning.html.twig';
import './lgw-free-trial-warning.scss';
import { LENGOW_URL } from '../../../const';

const { Component } = Shopware;

Component.register('lgw-free-trial-warning', {
    template,

    props: {
        accountStatusData: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            dayLeft: '',
            link: LENGOW_URL
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.dayLeft = this.accountStatusData.day;
        }
    }
});

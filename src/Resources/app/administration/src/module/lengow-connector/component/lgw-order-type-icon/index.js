import template from './lgw-order-type-icon.html.twig';
import './lgw-order-type-icon.scss';
import { ORDER_TYPES } from '../../../const';

const { Component } = Shopware;

Component.register('lgw-order-type-icon', {
    template,

    props: {
        type: {
            type: String,
            required: true
        },
        label: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            iconColorClass: '',
            iconModClass: '',
            iconLabel: '',
            isLoading: false
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            let iconColorClass;
            let iconModClass;
            if (this.type === ORDER_TYPES.express || this.type === ORDER_TYPES.prime) {
                iconColorClass = 'mod-orange';
                iconModClass = 'mod-chrono';
            }
            if (this.type === ORDER_TYPES.delivered_by_marketplace) {
                iconColorClass = 'mod-green';
                iconModClass = 'mod-delivery';
            }
            if (this.type === ORDER_TYPES.business) {
                iconColorClass = 'mod-blue';
                iconModClass = 'mod-pro';
            }
            this.iconColorClass = iconColorClass;
            this.iconModClass = iconModClass;
            this.iconLabel = this.label;
            this.isLoading = false;
        }
    }
});

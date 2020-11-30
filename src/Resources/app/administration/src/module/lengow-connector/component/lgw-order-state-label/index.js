import template from './lgw-order-state-label.html.twig';
import './lgw-order-state-label.scss';
import { ORDER_LENGOW_STATES } from '../../../const';

const { Component } = Shopware;

Component.register('lgw-order-state-label', {
    template,

    props: {
        orderLengowState: {
            type: String,
            required: true,
            default: '',
        },
    },

    data() {
        return {
            orderStateClass: '',
            orderStateTranslation: '',
            isLoading: false,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            let translationKey, orderStateClass;
            switch (this.orderLengowState) {
                case ORDER_LENGOW_STATES.accepted:
                    translationKey = 'lengow-connector.order.state.accepted';
                    orderStateClass = 'mod-accepted';
                    break;
                case ORDER_LENGOW_STATES.waiting_shipment:
                    translationKey = 'lengow-connector.order.state.waiting_shipment';
                    orderStateClass = 'mod-waiting-shipment';
                    break;
                case ORDER_LENGOW_STATES.shipped:
                    translationKey = 'lengow-connector.order.state.shipped';
                    orderStateClass = 'mod-shipped';
                    break;
                case ORDER_LENGOW_STATES.refunded:
                    translationKey = 'lengow-connector.order.state.refunded';
                    orderStateClass = 'mod-refunded';
                    break;
                case ORDER_LENGOW_STATES.closed:
                    translationKey = 'lengow-connector.order.state.closed';
                    orderStateClass = 'mod-closed';
                    break;
                case ORDER_LENGOW_STATES.canceled:
                    translationKey = 'lengow-connector.order.state.canceled';
                    orderStateClass = 'mod-canceled';
                    break;
                default:
                    translationKey = this.orderLengowState;
                    orderStateClass = 'mod-default';
                    break;
            }
            this.orderStateClass = orderStateClass;
            this.orderStateTranslation = this.$tc(translationKey);
            this.isLoading = false;
        },
    },
});

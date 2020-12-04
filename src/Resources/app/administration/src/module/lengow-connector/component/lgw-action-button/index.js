import template from './lgw-action-button.html.twig';
import './lgw-action-button.scss';
import { ERROR_TYPE, ACTION_BUTTON } from '../../../const';

const { Component } = Shopware;

Component.register('lgw-action-button', {
    template,

    inject: ['LengowConnectorOrderService'],

    props: {
        lengowOrderId: {
            type: String,
            required: true
        },
        orderProcessState: {
            type: Number,
            required: true
        },
        onRefresh: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            errors: [],
            buttonContent: '',
            buttonAction: '',
            tooltipTitle: '',
            isLoading: false
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {},

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (this.orderProcessState === 0) {
                this.buttonAction = ACTION_BUTTON.reimport;
                this.buttonContent = this.$tc('lengow-connector.order.action_button.not_imported');
                this.tooltipTitle = this.$tc('lengow-connector.order.action_button.import');
            } else {
                this.buttonAction = ACTION_BUTTON.resend;
                this.buttonContent = this.$tc('lengow-connector.order.action_button.not_sent');
                this.tooltipTitle = this.$tc('lengow-connector.order.action_button.action');
            }
            this.getLengowOrderErrors()
                .then(response => {
                    this.errors = response;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        getLengowOrderErrors() {
            return this.LengowConnectorOrderService.getOrderErrorMessages({
                lengowOrderId: this.lengowOrderId,
                orderErrorType: this.orderProcessState === 0 ? ERROR_TYPE.import : ERROR_TYPE.send
            });
        },

        clickButton(action) {
            if (action === ACTION_BUTTON.reimport) {
                this.reImportOrder();
            } else {
                this.reSendAction();
            }
        },

        reImportOrder() {
            this.isLoading = true;
            this.LengowConnectorOrderService.reImportOrder({
                lengowOrderId: this.lengowOrderId
            }).then(response => {
                if (response.success) {
                    this.onRefresh();
                } else {
                    this.getLengowOrderErrors().then(result => {
                        this.errors = result;
                        this.isLoading = false;
                    });
                }
            });
        },

        reSendAction() {
            this.isLoading = true;
            this.LengowConnectorOrderService.reSendAction({
                lengowOrderId: this.lengowOrderId
            }).then(response => {
                if (response.success) {
                    this.onRefresh();
                } else {
                    this.getLengowOrderErrors().then(result => {
                        this.errors = result;
                        this.isLoading = false;
                    });
                }
            });
        }
    }
});

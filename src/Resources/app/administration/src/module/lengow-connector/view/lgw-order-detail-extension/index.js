import template from './lgw-order-detail-extension.html.twig';
import './lgw-order-detail-extension.scss';
import {
    ACTION_STATE,
    ORDER_PROCESS_STATE,
    SHOPWARE_ORDER_DELIVERY_STATE,
    SHOPWARE_ORDER_STATE
} from '../../../const';

const {
    Data: { Criteria }
} = Shopware;

Shopware.Component.register('lgw-order-detail-extension', {
    template,

    inject: ['repositoryFactory', 'LengowConnectorOrderService'],

    metaInfo() {
        return {
            title: 'Custom'
        };
    },

    data() {
        return {
            isLoading: true,
            btnSynchroLoading: true,
            btnActionDisplay: false,
            btnActionLoading: false,
            btnReimportLoading: true,
            modalDisplay: false,
            modalLoading: false,
            debugMode: true,
            isFromLengow: false,
            orderId: '',
            lengowOrder: null,
            lengowOrderId: '',
            marketplaceSku: '',
            marketplaceName: '',
            deliveryAddressId: '',
            orderLengowState: '',
            orderProcessState: '',
            totalPaid: '',
            commission: '',
            currency: '',
            customerName: '',
            customerEmail: '',
            carrier: '',
            carrierMethod: '',
            carrierTracking: '',
            carrierIdRelay: '',
            isExpress: false,
            isShippedByMarketplace: false,
            isB2b: false,
            customerVatNumber: '',
            orderDate: '',
            importedAt: '',
            message: '',
            extra: ''
        };
    },

    computed: {
        lengowOrderRepository() {
            return this.repositoryFactory.create('lengow_order');
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        lengowConfigRepository() {
            return this.repositoryFactory.create('lengow_settings');
        },

        lengowActionRepository() {
            return this.repositoryFactory.create('lengow_action');
        }
    },

    created() {
        this.loadOrderData().then(() => {
            this.isLoading = false;
            this.loadDebugMode();
            this.loadSyncData();
        });
    },

    methods: {
        loadOrderData() {
            this.orderId = this.$route.params.id;
            const lengowOrderCriteria = new Criteria();
            lengowOrderCriteria.addFilter(Criteria.equals('orderId', this.orderId));
            return this.lengowOrderRepository.search(lengowOrderCriteria, Shopware.Context.api).then(result => {
                if (result.total > 0) {
                    const lengowOrder = result.first();
                    this.isFromLengow = true;
                    this.lengowOrderId = lengowOrder.id;
                    this.marketplaceSku = lengowOrder.marketplaceSku || '-';
                    this.marketplaceName = lengowOrder.marketplaceName || '-';
                    this.deliveryAddressId = lengowOrder.deliveryAddressId || '-';
                    this.orderLengowState = lengowOrder.orderLengowState || '-';
                    this.orderProcessState = lengowOrder.orderProcessState || '';
                    this.totalPaid = lengowOrder.totalPaid || '-';
                    this.commission = lengowOrder.commission || '-';
                    this.currency = lengowOrder.currency || '-';
                    this.customerName = lengowOrder.customerName || '-';
                    this.customerEmail = lengowOrder.customerEmail || '-';
                    this.carrier = lengowOrder.carrier || '-';
                    this.carrierMethod = lengowOrder.carrierMethod || '-';
                    this.carrierTracking = lengowOrder.carrierTracking || '-';
                    this.carrierIdRelay = lengowOrder.carrierIdRelay || '-';
                    this.customerVatNumber = lengowOrder.customerVatNumber || '-';
                    this.orderDate = lengowOrder.orderDate != null
                        ? new Date(lengowOrder.orderDate).toLocaleString()
                        : '-';
                    this.importedAt = lengowOrder.importedAt != null
                        ? new Date(lengowOrder.importedAt).toLocaleString()
                        : '-';
                    this.message = lengowOrder.message || '-';
                    this.extra = lengowOrder.extra != null ? JSON.stringify(lengowOrder.extra) : '-';
                    if (lengowOrder.orderTypes != null) {
                        if (lengowOrder.orderTypes.is_business !== undefined) {
                            this.isB2b = true;
                        }
                        if (lengowOrder.orderTypes.is_express !== undefined) {
                            this.isExpress = true;
                        }
                        if (lengowOrder.orderTypes.delivered_by_marketplace !== undefined) {
                            this.isShippedByMarketplace = true;
                        }
                    }
                }
            });
        },

        loadSyncData() {
            const lengowActionCriteria = new Criteria();
            lengowActionCriteria.addFilter(Criteria.equals('state', ACTION_STATE.new));
            lengowActionCriteria.addFilter(Criteria.equals('orderId', this.orderId));
            this.lengowActionRepository.search(lengowActionCriteria, Shopware.Context.api).then(result => {
                if ((result.total > 0)) {
                    this.canResendAction();
                }
            });
        },

        loadDebugMode() {
            const lengowConfigCriteria = new Criteria();
            lengowConfigCriteria.addFilter(Criteria.equals('name', 'lengowDebugEnabled'));
            this.lengowConfigRepository.search(lengowConfigCriteria, Shopware.Context.api).then(result => {
                if (result.total > 0) {
                    this.debugMode = result.first().value === '1';
                }
                this.btnSynchroLoading = false;
                this.btnReimportLoading = false;
            });
        },

        canResendAction() {
            const orderCriteria = new Criteria();
            orderCriteria.addAssociation('deliveries');
            orderCriteria.setIds([this.orderId]);
            return this.orderRepository.search(orderCriteria, Shopware.Context.api).then(result => {
                if (!(result.total > 0)) {
                    return;
                }
                const order = result.first();
                const orderDeliveryState = this.getOrderDeliveryState(order);
                let orderState = '';
                if (order.stateMachineState && order.stateMachineState.technicalName) {
                    orderState = result.first().stateMachineState.technicalName;
                }
                if ((orderDeliveryState === SHOPWARE_ORDER_DELIVERY_STATE.shipped
                    || orderState === SHOPWARE_ORDER_STATE.canceled)
                    && this.lengowOrderId
                    && this.orderProcessState !== ORDER_PROCESS_STATE.finish
                ) {
                    this.btnActionDisplay = true;
                }
            });
        },

        getOrderDeliveryState(order) {
            let orderDeliveryState = '';
            if (order.deliveries.length > 0) {
                const orderDelivery = order.deliveries.first();
                if (orderDelivery.stateMachineState.technicalName) {
                    orderDeliveryState = orderDelivery.stateMachineState.technicalName;
                }
            }
            return orderDeliveryState;
        },

        toggleModal() {
            if (this.modalDisplay) {
                this.modalDisplay = false;
            } else {
                this.modalDisplay = true;
            }
        },

        reSynchronizeOrder() {
            this.btnSynchroLoading = true;
            this.LengowConnectorOrderService.reSynchroniseOrder({ orderId: this.orderId }).then(() => {
                this.btnSynchroLoading = false;
            });
        },

        reImportOrder() {
            this.btnReimportLoading = true;
            this.modalLoading = true;
            this.LengowConnectorOrderService.reImportFailedOrder({
                lengowOrderId: this.lengowOrderId,
                orderId: this.orderId
            }).then((response) => {
                this.toggleModal();
                if (response.success) {
                    this.modalLoading = false;
                    this.orderId = response.new_order_id;
                }
            }).finally(() => {
                this.btnReimportLoading = false;
                this.modalLoading = false;
                this.$router.push({ name: 'sw.order.detail', params: { id: this.orderId } });
                if (this.modalDisplay === true) {
                    this.toggleModal();
                }
            });
        },

        reSendAction() {
            this.btnActionLoading = true;
            this.LengowConnectorOrderService.reSendAction({
                lengowOrderId: this.lengowOrderId
            }).then(() => {
                this.btnActionLoading = false;
            });
        }
    }
});

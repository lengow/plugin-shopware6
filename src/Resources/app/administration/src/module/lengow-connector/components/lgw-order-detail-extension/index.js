import template from './views/lgw-order-detail-extension.html.twig';
import './views/lgw-order-detail-extension.scss';

const {
    Component,
    Mixin,
    Data: { Criteria },
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
            btnSynchroLoading: false,
            debugMode: true,
            isFromLengow: false,
            orderId: '',
            marketplaceSku: '',
            marketplaceName: '',
            deliveryAddressId: '',
            orderLengowState: '',
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
            extra: '',
        };
    },


    computed: {
        lengowOrderRepository() {
            return this.repositoryFactory.create('lengow_order');
        },

        lengowConfigRepository() {
            return this.repositoryFactory.create('lengow_settings');
        }

    },

    created() {
        this.loadOrderData().then(() => {
            this.isLoading = false;
        });
        this.loadSyncData();
    },

    methods: {
        loadOrderData() {
            this.orderId = this.$route.params.id
            const lengowOrderCriteria = new Criteria();
            lengowOrderCriteria.addFilter(Criteria.equals('orderId', this.orderId));
            return this.lengowOrderRepository.search(lengowOrderCriteria, Shopware.Context.api).then(result => {
                if (result.total > 0) {
                    const lengowOrder = result.first();
                    console.log(lengowOrder);
                    this.isFromLengow = true;
                    this.marketplaceSku = lengowOrder.marketplaceSku || '-';
                    this.marketplaceName = lengowOrder.marketplaceName || '-';
                    this.deliveryAddressId = lengowOrder.deliveryAddressId || '-';
                    this.orderLengowState = lengowOrder.orderLengowState || '-';
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
                        ? new Date(lengowOrder.importDate).toLocaleString()
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
            const lengowConfigCriteria = new Criteria();
            lengowConfigCriteria.addFilter(Criteria.equals('name', 'lengowDebugEnabled'));
            this.lengowConfigRepository.search(lengowConfigCriteria, Shopware.Context.api).then(result => {
                if (result.total > 0) {
                    this.debugMode = result.first().value === '1';
                }
            })
        },

        reSynchronizeOrder() {
            this.btnSynchroLoading = true;
            this.LengowConnectorOrderService.reSynchroniseOrder({'orderId': this.orderId}).then(() => {
                this.btnSynchroLoading = false;
            });
        },

        reImportOrder() {
            console.log('WIP reimportOrder');
        },

        reSendAction() {
            console.log('WIP re send action');
        }
    },
});

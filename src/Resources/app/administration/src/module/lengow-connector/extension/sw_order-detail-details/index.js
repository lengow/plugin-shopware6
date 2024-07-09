import template from './sw-order-detail-details.html.twig';
import './lgw-order-orders.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.override('sw-order-detail-details', {
    template,

    props: {
        orderId: {
            type: String,
            required: true
        },
    },

    inject: ['LengowConnectorSyncService','repositoryFactory'],

    data() {
        return {
            returnTrackingNumberSet: false,
            returnCarrierSet: false,
            localReturnTrackingNumber: [],
            localReturnCarrier: "",
        };
    },

    computed: {
        shippingRepository() {
            return this.repositoryFactory.create('shipping_method');
        },
    },

    created() {
        this.checkMarketplaceArguments();
        this.loadReturnTrackingNumbers();
        this.loadReturnCarrier();
        this.localReturnTrackingNumber = this.returnTrackingNumber;
        this.localReturnCarrier = this.returnCarrier;
    },

    methods: {
        checkMarketplaceArguments() {

            this.LengowConnectorSyncService.verifyArgRtnRc(this.orderId)
                .then(response => {
                    const returnTrackingNumberExists = response.return_tracking_number_exists;
                    const returnCarrierExists = response.return_carrier_exists;
                    this.returnTrackingNumberSet = !!returnTrackingNumberExists;
                    this.returnCarrierSet = !!returnCarrierExists;
                })
                .catch(error => {
                    console.error("Error fetching marketplace arguments:", error);
                });
        },
        onSaveRtn() {
            const returnTrackingNumber = this.localReturnTrackingNumber;

            this.LengowConnectorSyncService.OnChangeRtn(this.orderId, returnTrackingNumber)
                .then(() => {
                    this.$emit('return-tracking-number-saved', returnTrackingNumber);
                })
                .catch((error) => {
                    console.error("Request failed:", error);
                });
        },
        updateReturnTrackingNumber(newValue) {
            this.localReturnTrackingNumber = newValue;
            this.onSaveRtn();
        },
        loadReturnTrackingNumbers() {

            this.LengowConnectorSyncService.OnLoadRtn(this.orderId)
                .then(response => {
                    if (response.return_tracking_number && Array.isArray(response.return_tracking_number)) {
                        const returnTrackingNumbers = JSON.parse(response.return_tracking_number);
                        this.localReturnTrackingNumber = returnTrackingNumbers.map(item => item.replace(/"/g, ''));
                    } else {
                        this.localReturnTrackingNumber = [];
                    }
                })
                .catch(error => {
                    console.error("Error fetching return tracking numbers:", error);
                    this.localReturnTrackingNumber = [];
                });
        },
        async onSaveRc() {
            const returnCarrier = this.localReturnCarrier;

            try {
                const returnCarrierName = await this.getShippingMethodName(returnCarrier);
                this.LengowConnectorSyncService.OnChangeRc(this.orderId, returnCarrierName)
                    .then(() => {
                        this.$emit('return-carrier-saved', returnCarrierName);
                    })
                    .catch((error) => {
                        console.error("Request failed:", error);
                    });
            } catch (error) {
                console.error("Error fetching shipping method name:", error);
            }
        },
        updateReturnCarrier(newValue) {
            this.localReturnCarrier = newValue;
            this.onSaveRc();
        },
        loadReturnCarrier(methodId) {
            this.localReturnCarrier = methodId;
            this.LengowConnectorSyncService.OnLoadRc(this.orderId)
                .then(response => {
                    if (response.return_carrier) {
                        const id = this.getShippingMethodIdByName(response.return_carrier)
                            .then(id => {
                                this.localReturnCarrier = id;
                            })
                            .catch(error => {
                                console.error("Error fetching shipping method id:", error);
                            });
                    } else {
                        this.localReturnCarrier = "";
                    }
                })
                .catch(error => {
                    console.error("Error fetching return carrier:", error);
                    this.localReturnCarrier = [];
                });
        },
        getShippingMethodName(shippingMethodId) {
            const criteria = new Criteria(1, 1);
            criteria.addFilter({ field: 'id', type: 'equals', value: shippingMethodId });

            return this.shippingRepository
                .search(criteria, Shopware.Context.api)
                .then(response => {
                    if (response[0]["name"]) {
                        return response[0]["name"];
                    }

                    throw new Error('Shipping method not found');
                });
        },
        getShippingMethodIdByName(shippingMethodName) {
            shippingMethodName = shippingMethodName.replace(/^"(.*)"$/, '$1');
            const criteria = new Criteria(1, 1);
            criteria.addFilter({ field: 'name', type: 'equals', value: shippingMethodName });
            return this.shippingRepository
                .search(criteria, Shopware.Context.api)
                .then(response => {
                    if (response.total > 0) {
                        return response.first().id;
                    }

                    throw new Error("Shipping method not found");
                });
        },
    },
});

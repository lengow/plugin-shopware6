import template from './views/lengow-order-list.html.twig';
import {ORDER_LENGOW_STATES, ORDER_TYPES} from "../../../const";
import lgwActionButton from './components/lgw-action-button';
import lgwCountryIcon from './components/lgw-country-icon';
import lgwOrderStateLabel from './components/lgw-order-state-label';
import lgwOrderTypeIcon from './components/lgw-order-type-icon';

const {
    Component,
    Mixin,
    Data: {Criteria},
} = Shopware;

Component.register('lengow-order-list', {
    template,

    inject: [
        'repositoryFactory',
        'stateStyleDataProviderService',
        'acl'
    ],

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            lengowOrders: [],
            sortBy: 'orderDate',
            sortDirection: 'DESC',
            isLoading: false,
            filterLoading: false,
            showDeleteModal: false,
            searchFilter: '',
            orderLengowStateFilter: [],
            orderTypeFilter: '',
            marketplaceFilter: [],
            availableOrderLengowStates: [],
            availableOrderTypes: [],
            availableMarketplaces: [],
        };
    },

    created() {
        this.loadFilterValues();
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {

        lengowOrderRepository() {
            return this.repositoryFactory.create('lengow_order');
        },

        lengowOrderColumns() {
            return this.getLengowOrderColumns();
        },

        lengowOrderCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            if (this.orderLengowStateFilter.length > 0) {
                criteria.addFilter(Criteria.equalsAny('orderLengowState', this.orderLengowStateFilter));
            }
            if (this.orderTypeFilter) {
                if (this.orderTypeFilter === ORDER_TYPES.express) {
                    criteria.addFilter(Criteria.multi('OR', [
                        Criteria.contains('orderTypes', ORDER_TYPES.express),
                        Criteria.contains('orderTypes', ORDER_TYPES.prime),
                    ]));
                } else {
                    criteria.addFilter(Criteria.contains('orderTypes', this.orderTypeFilter));
                }
            }
            if (this.marketplaceFilter.length > 0) {
                criteria.addFilter(Criteria.equalsAny('marketplaceName', this.marketplaceFilter));
            }
            if (this.searchFilter) {
                criteria.addFilter(Criteria.multi('OR', [
                    Criteria.contains('marketplaceSku', this.searchFilter),
                    Criteria.contains('customerName', this.searchFilter),
                ]));
            }
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection))
                .addAssociation('salesChannel')
                .addAssociation('order')
                .addAssociation('order.stateMachineState');

            return criteria;
        },

        getAvailableOrderLengowStates() {
            return [
                {label: this.$tc('lengow-connector.order.state.accepted'), value: ORDER_LENGOW_STATES.accepted},
                {label: this.$tc('lengow-connector.order.state.waiting_shipment'), value: ORDER_LENGOW_STATES.waiting_shipment},
                {label: this.$tc('lengow-connector.order.state.shipped'), value: ORDER_LENGOW_STATES.shipped},
                {label: this.$tc('lengow-connector.order.state.refunded'), value: ORDER_LENGOW_STATES.refunded},
                {label: this.$tc('lengow-connector.order.state.closed'), value: ORDER_LENGOW_STATES.closed},
                {label: this.$tc('lengow-connector.order.state.canceled'), value: ORDER_LENGOW_STATES.canceled},
            ];
        },

        getAvailableOrderTypes() {
            return [
                {label: this.$tc('lengow-connector.order.filter.default_order_type'), value: ''},
                {label: this.$tc('lengow-connector.order.type.express'), value: ORDER_TYPES.express},
                {label: this.$tc('lengow-connector.order.type.delivered_by_marketplace'), value: ORDER_TYPES.delivered_by_marketplace},
                {label: this.$tc('lengow-connector.order.type.business'), value: ORDER_TYPES.business},
            ];
        },
    },

    methods: {
        getList() {
            this.isLoading = true;

            return this.lengowOrderRepository.search(this.lengowOrderCriteria, Shopware.Context.api).then((response) => {
                this.total = response.total;
                this.lengowOrders = response;

                return response;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        getLengowOrderColumns() {
            return [
                {
                    property: 'isInError',
                    label: 'lengow-connector.order.column.actions',
                    allowResize: true,
                },
                {
                    property: 'orderLengowState',
                    label: 'lengow-connector.order.column.lengow_status',
                    align: 'center',
                    allowResize: true,
                },
                {
                    property: 'orderTypes',
                    label: 'lengow-connector.order.column.order_types',
                    align: 'center',
                    allowResize: true,
                },
                {
                    property: 'marketplaceSku',
                    label: 'lengow-connector.order.column.marketplace_sku',
                    allowResize: true,
                },
                {
                    property: 'marketplaceLabel',
                    label: 'lengow-connector.order.column.marketplace',
                    allowResize: true,
                },
                {
                    property: 'salesChannel.name',
                    label: 'lengow-connector.order.column.sales_channel_name',
                    allowResize: true,
                },
                {
                    property: 'order.stateMachineState.name',
                    label: 'lengow-connector.order.column.shopware_status',
                    allowResize: true,
                },
                {
                    property: 'order.orderNumber',
                    label: 'lengow-connector.order.column.shopware_sku',
                    allowResize: true,
                },
                {
                    property: 'customerName',
                    label: 'lengow-connector.order.column.customer_name',
                    allowResize: true,
                },
                {
                    property: 'orderDate',
                    label: 'lengow-connector.order.column.order_date',
                    allowResize: true,
                },
                {
                    property: 'deliveryCountryIso',
                    label: 'lengow-connector.order.column.country',
                    align: 'center',
                    allowResize: true,
                },
                {
                    property: 'totalPaid',
                    label: 'lengow-connector.order.column.total_paid',
                    align: 'right',
                    allowResize: true,
                },
            ];
        },

        getVariantFromOrderState(order) {
            return this.stateStyleDataProviderService.getStyle(
                'order.state', order.stateMachineState.technicalName
            ).variant;
        },

        getOrderTypes(types) {
            const orderTypes = [];
            Object.keys(types).forEach(key => {
                orderTypes.push({type: key, label: types[key]});
            });
            return orderTypes;
        },

        getOrderItemTooltip(order) {
            return `${order.orderItem.toString()} ${this.$tc('lengow-connector.order.nb_product')}`;
        },

        loadFilterValues() {
            this.filterLoading = true;

            this.availableOrderLengowStates = this.getAvailableOrderLengowStates;
            this.availableOrderTypes = this.getAvailableOrderTypes;

            const criteria = new Criteria();
            criteria.addGrouping('marketplaceName');
            return this.lengowOrderRepository.search(criteria, Shopware.Context.api).then((response) => {
                const availableMarketplaces = [];
                response.forEach(item => {
                    availableMarketplaces.push({label: item.marketplaceLabel, value: item.marketplaceName});
                });
                this.availableMarketplaces = availableMarketplaces;
            }).finally(() => {
                this.filterLoading = false;
            });
        },

        onSearch(value) {
            this.searchFilter = value;
            this.getList();
        },

        onChangeOrderLengowStateFilter(value) {
            this.orderLengowStateFilter = value;
            this.getList();
        },

        onChangeOrderTypeFilter(value) {
            this.orderTypeFilter = value;
            this.getList();
        },

        onChangeMarketplaceFilter(value) {
            this.marketplaceFilter = value;
            this.getList();
        },

        onRefresh() {
            this.searchFilter = '';
            this.orderLengowStateFilter = [];
            this.orderTypeFilter = '';
            this.marketplaceFilter = [];
            this.getList();
        },
    },
});

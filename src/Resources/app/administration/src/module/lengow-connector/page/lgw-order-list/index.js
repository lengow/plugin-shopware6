import template from './lgw-order-list.html.twig';
import './lgw-order-list.scss';
import { ORDER_LENGOW_STATES, ORDER_TYPES, ORDER_SYNCHRONISATION } from '../../../const';

const {
    Component,
    Mixin,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-order-list', {
    template,

    inject: [
        'repositoryFactory',
        'stateStyleDataProviderService',
        'acl',
        'LengowConnectorOrderService'
    ],

    mixins: [Mixin.getByName('listing')],

    data() {
        return {
            lengowOrders: [],
            sortBy: 'orderDate',
            sortDirection: 'DESC',
            isLoading: false,
            filterLoading: false,
            showSyncModal: false,
            searchFilter: '',
            orderLengowStateFilter: [],
            orderTypeFilter: '',
            marketplaceFilter: [],
            availableOrderLengowStates: [],
            availableOrderTypes: [],
            availableMarketplaces: [],
            syncModalTitle: '',
            syncModalMessages: [],
            orderWithError: 0,
            orderWaitingToBeSent: 0,
            reportMailEnabled: false,
            reportMailAddress: '',
            defaultMail: '',
            lastSynchronisation: {},
            settingsLoading: false,
            orderWithErrorLoading: false,
            orderWaitingToBeSentLoading: false,
            selection: [],
            debugMode: false
        };
    },

    created() {
        this.loadFilterValues();
        this.loadSyncData();
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        lengowOrderRepository() {
            return this.repositoryFactory.create('lengow_order');
        },

        lengowSettingsRepository() {
            return this.repositoryFactory.create('lengow_settings');
        },

        systemConfigRepository() {
            return this.repositoryFactory.create('system_config');
        },

        lengowOrderColumns() {
            return this.getLengowOrderColumns();
        },

        lengowOrderCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            if (this.orderLengowStateFilter.length > 0) {
                criteria.addFilter(
                    Criteria.equalsAny('orderLengowState', this.orderLengowStateFilter)
                );
            }
            if (this.orderTypeFilter) {
                if (this.orderTypeFilter === ORDER_TYPES.express) {
                    criteria.addFilter(
                        Criteria.multi('OR', [
                            Criteria.contains('orderTypes', ORDER_TYPES.express),
                            Criteria.contains('orderTypes', ORDER_TYPES.prime)
                        ])
                    );
                } else {
                    criteria.addFilter(Criteria.contains('orderTypes', this.orderTypeFilter));
                }
            }
            if (this.marketplaceFilter.length > 0) {
                criteria.addFilter(Criteria.equalsAny('marketplaceName', this.marketplaceFilter));
            }
            if (this.searchFilter) {
                criteria.addFilter(
                    Criteria.multi('OR', [
                        Criteria.contains('marketplaceSku', this.searchFilter),
                        Criteria.contains('customerName', this.searchFilter)
                    ])
                );
            }
            criteria
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection))
                .addAssociation('salesChannel')
                .addAssociation('order')
                .addAssociation('order.stateMachineState');

            return criteria;
        },

        getAvailableOrderLengowStates() {
            return [
                {
                    label: this.$tc('lengow-connector.order.state.accepted'),
                    value: ORDER_LENGOW_STATES.accepted
                },
                {
                    label: this.$tc('lengow-connector.order.state.waiting_shipment'),
                    value: ORDER_LENGOW_STATES.waiting_shipment
                },
                {
                    label: this.$tc('lengow-connector.order.state.shipped'),
                    value: ORDER_LENGOW_STATES.shipped
                },
                {
                    label: this.$tc('lengow-connector.order.state.refunded'),
                    value: ORDER_LENGOW_STATES.refunded
                },
                {
                    label: this.$tc('lengow-connector.order.state.closed'),
                    value: ORDER_LENGOW_STATES.closed
                },
                {
                    label: this.$tc('lengow-connector.order.state.canceled'),
                    value: ORDER_LENGOW_STATES.canceled
                },
                {
                    label: this.$tc('lengow-connector.order.state.partial_refunded'),
                    value: ORDER_LENGOW_STATES.partial_refunded
                }
            ];
        },

        getAvailableOrderTypes() {
            return [
                {
                    label: this.$tc('lengow-connector.order.filter.default_order_type'),
                    value: ''
                },
                {
                    label: this.$tc('lengow-connector.order.type.express'),
                    value: ORDER_TYPES.express
                },
                {
                    label: this.$tc('lengow-connector.order.type.delivered_by_marketplace'),
                    value: ORDER_TYPES.delivered_by_marketplace
                },
                {
                    label: this.$tc('lengow-connector.order.type.business'),
                    value: ORDER_TYPES.business
                }
            ];
        }
    },

    methods: {
        currency(value, currency) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency
            }).format(value);
        },
        formatDate(date) {
            if (!(date instanceof Date) || isNaN(date.getTime())) {
                return "";
            }
            return new Intl.DateTimeFormat(navigator.language.substring(0, 2), { hour: '2-digit', minute: '2-digit' }).format(date);
        },
        getList() {
            this.isLoading = true;
            console.log('test')
            return this.lengowOrderRepository
                .search(this.lengowOrderCriteria, Shopware.Context.api)
                .then(response => {
                    console.log(response)
                    this.total = response.total;
                    this.lengowOrders = response;
                    return response;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        getLengowOrderColumns() {
            return [
                {
                    property: 'isInError',
                    label: 'lengow-connector.order.column.actions',
                    align: 'center',
                    allowResize: true
                },
                {
                    property: 'orderLengowState',
                    label: 'lengow-connector.order.column.lengow_status',
                    align: 'center',
                    allowResize: true
                },
                {
                    property: 'orderTypes',
                    label: 'lengow-connector.order.column.order_types',
                    align: 'center',
                    allowResize: true
                },
                {
                    property: 'marketplaceSku',
                    label: 'lengow-connector.order.column.marketplace_sku',
                    allowResize: true
                },
                {
                    property: 'marketplaceLabel',
                    label: 'lengow-connector.order.column.marketplace',
                    allowResize: true
                },
                {
                    property: 'salesChannel.name',
                    label: 'lengow-connector.order.column.sales_channel_name',
                    allowResize: true
                },
                {
                    property: 'order.stateMachineState.name',
                    label: 'lengow-connector.order.column.shopware_status',
                    allowResize: true
                },
                {
                    property: 'order.orderNumber',
                    label: 'lengow-connector.order.column.shopware_sku',
                    allowResize: true
                },
                {
                    property: 'customerName',
                    label: 'lengow-connector.order.column.customer_name',
                    allowResize: true
                },
                {
                    property: 'orderDate',
                    label: 'lengow-connector.order.column.order_date',
                    allowResize: true
                },
                {
                    property: 'deliveryCountryIso',
                    label: 'lengow-connector.order.column.country',
                    align: 'center',
                    allowResize: true
                },
                {
                    property: 'totalPaid',
                    label: 'lengow-connector.order.column.total_paid',
                    align: 'right',
                    allowResize: true
                }
            ];
        },

        getVariantFromOrderState(order) {
            return this.stateStyleDataProviderService.getStyle(
                'order.state',
                order.stateMachineState.technicalName
            ).variant;
        },

        getOrderTypes(types) {
            const orderTypes = [];
            Object.keys(types).forEach(key => {
                orderTypes.push({ type: key, label: types[key] });
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
            this.LengowConnectorOrderService.getAvailableMarketplaces()
                .then(response => {
                    this.availableMarketplaces = response;
                })
                .finally(() => {
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
            this.loadSyncData();
        },

        onCloseSynResultModal() {
            this.showSyncModal = false;
        },

        updateSelection(selected) {
            this.selection = Object.values(selected);
        },

        loadSyncData() {
            this.loadDefaultEmail();
            this.loadSettings();
            this.loadOrderWithError();
            this.loadOrderWaitingToBeSent();
        },

        loadSettings() {
            this.settingsLoading = true;
            const criteria = new Criteria();
            criteria.addFilter(
                Criteria.equalsAny('name', [
                    'lengowLastImportCron',
                    'lengowLastImportManual',
                    'lengowReportMailEnabled',
                    'lengowReportMailAddress',
                    'lengowDebugEnabled'
                ])
            );
            this.lengowSettingsRepository
                .search(criteria, Shopware.Context.api)
                .then(response => {
                    const settings = [];
                    if (response.total > 0) {
                        response.forEach(setting => {
                            settings[setting.name] = setting.value;
                        });
                    }
                    if (settings.lengowDebugEnabled === '1') {
                        this.debugMode = true;
                    }
                    if (
                        settings.lengowLastImportCron !== undefined &&
                        settings.lengowLastImportManual !== undefined
                    ) {
                        this.lastSynchronisation = this.getLastSynchronisationDate(
                            settings.lengowLastImportCron,
                            settings.lengowLastImportManual
                        );
                    }
                    if (settings.lengowReportMailEnabled !== undefined) {
                        this.reportMailEnabled = settings.lengowReportMailEnabled === '1';
                    }
                    if (settings.lengowReportMailAddress !== undefined && settings.lengowReportMailAddress) {
                        this.reportMailAddress = this.cleanReportMailAddresses(settings.lengowReportMailAddress);
                    }
                })
                .finally(() => {
                    this.settingsLoading = false;
                });
        },

        getLastSynchronisationDate(timestampCron, timestampManual) {
            if (timestampCron && timestampManual) {
                if (parseInt(timestampCron, 10) > parseInt(timestampManual, 10)) {
                    return {
                        type: ORDER_SYNCHRONISATION.cron,
                        date: new Date(parseInt(timestampCron, 10) * 1000)
                    };
                }
                return {
                    type: ORDER_SYNCHRONISATION.manual,
                    date: new Date(parseInt(timestampManual, 10) * 1000)
                };
            }
            if (timestampCron && !timestampManual) {
                return {
                    type: ORDER_SYNCHRONISATION.cron,
                    date: new Date(parseInt(timestampCron,10) * 1000)
                };
            }
            if (timestampManual && !timestampCron) {
                return {
                    type: ORDER_SYNCHRONISATION.manual,
                    date: new Date(parseInt(timestampManual, 10) * 1000)
                };
            }
            return {};
        },

        loadDefaultEmail() {
            const criteria = new Criteria();
            criteria.addFilter(
                Criteria.contains('configurationKey', 'core.basicInformation.email')
            );
            this.systemConfigRepository.search(criteria, Shopware.Context.api).then(response => {
                if (response.total > 0) {
                    this.defaultEmail = response.first().configurationValue;
                }
            });
        },

        cleanReportMailAddresses(reportMailAddress) {
            return reportMailAddress
                .trim()
                .replaceAll('\r\n', ',')
                .replaceAll(';', ',')
                .replaceAll(' ', ',')
                .replaceAll(',', ', ');
        },

        loadOrderWithError() {
            this.orderWithErrorLoading = true;
            const criteria = new Criteria();
            criteria.addFilter(Criteria.contains('isInError', '1'));
            this.lengowOrderRepository
                .search(criteria, Shopware.Context.api)
                .then(response => {
                    this.orderWithError = parseInt(response.total, 10);
                })
                .finally(() => {
                    this.orderWithErrorLoading = false;
                });
        },

        loadOrderWaitingToBeSent() {
            this.orderWaitingToBeSentLoading = true;
            const criteria = new Criteria();
            criteria.addFilter(Criteria.contains('orderProcessState', '1'));
            this.lengowOrderRepository
                .search(criteria, Shopware.Context.api)
                .then(response => {
                    this.orderWaitingToBeSent = parseInt(response.total, 10);
                })
                .finally(() => {
                    this.orderWaitingToBeSentLoading = false;
                });
        },

        synchroniseOrders() {
            this.isLoading = true;
            this.LengowConnectorOrderService.synchroniseOrders()
                .then(response => {
                    this.syncModalTitle = this.$tc('lengow-connector.order.sync_modal_title_order');
                    this.syncModalMessages = response;
                    this.showSyncModal = true;
                    this.onRefresh();
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        massReImportOrders() {
            this.isLoading = true;

            let lengowOrderIds = [];
            this.selection.forEach(orderSelected => {
                if (orderSelected.isInError && orderSelected.orderProcessState === 0) {
                    lengowOrderIds = [...lengowOrderIds, orderSelected.id];
                }
            });
            this.LengowConnectorOrderService.massReImportOrders({ lengowOrderIds })
                .then(response => {
                    this.syncModalTitle = this.$tc('lengow-connector.order.sync_modal_title_order');
                    this.syncModalMessages = response;
                    this.showSyncModal = true;
                    this.onRefresh();
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        massReSendActions() {
            this.isLoading = true;

            let lengowOrderIds = [];
            this.selection.forEach(orderSelected => {
                if (orderSelected.isInError && orderSelected.orderProcessState === 1) {
                    lengowOrderIds = [...lengowOrderIds, orderSelected.id];
                }
            });
            this.LengowConnectorOrderService.massReSendActions({ lengowOrderIds })
                .then(response => {
                    this.syncModalTitle = this.$tc('lengow-connector.order.sync_modal_title_action');
                    this.syncModalMessages = response;
                    this.showSyncModal = true;
                    this.onRefresh();
                })
                .finally(() => {
                    this.isLoading = false;
                });
        }
    }
});

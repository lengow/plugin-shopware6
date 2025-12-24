import template from './lgw-toolbox-base.html.twig';

const { Component } = Shopware;

Component.register('lgw-toolbox-base', {
    template,

    inject: [],

    mixins: [],

    data() {
        return {};
    },

    created() {},

    computed: {
        overviewData() {
            const state = Shopware.State.get('lgwToolbox');
            return state && state.overviewData ? state.overviewData : {
                checklist: {
                    curl_activated: false,
                    simple_xml_activated: false,
                    json_activated: false,
                    md5_success: false
                },
                plugin: {
                    cms_version: '',
                    plugin_version: '',
                    php_version: '',
                    debug_mode_disable: false,
                    server_ip: '',
                    authorized_ip_enable: false,
                    authorized_ips: [],
                    write_permission: false,
                    toolbox_url: ''
                },
                synchronization: {
                    cms_token: '',
                    cron_url: '',
                    synchronization_in_progress: false,
                    last_synchronization: '',
                    last_synchronization_type: '',
                    number_orders_imported: 0,
                    number_orders_waiting_shipment: 0,
                    number_orders_in_error: 0
                },
                shops: []
            };
        },
        
        loading() {
            const state = Shopware.State.get('lgwToolbox');
            return state && state.loading ? state.loading : {};
        },

        isLoading() {
            const getters = Shopware.State.getters['lgwToolbox/isLoading'];
            return getters !== undefined ? getters : false;
        }
    },

    methods: {}
});

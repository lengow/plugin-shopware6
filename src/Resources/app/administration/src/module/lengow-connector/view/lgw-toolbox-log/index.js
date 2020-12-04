import template from './lgw-toolbox-log.html.twig';
import './lgw-toolbox-log.scss';

const { Component } = Shopware;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('lgw-toolbox-log', {
    template,

    inject: ['LengowConnectorToolboxService'],

    mixins: [],

    data() {
        return {
            availableLogs: [],
            logFilter: '',
            showButton: false,
            isLoading: true,
            buttonIsLoading: false
        };
    },

    mounted() {
        this.mountedComponent();
    },

    computed: {
        ...mapState('lgwToolbox', ['logData']),

        ...mapGetters('lgwToolbox', {
            isToolboxLoading: 'isLoading'
        })
    },

    watch: {
        isToolboxLoading: {
            handler() {
                if (!this.isToolboxLoading) {
                    this.loadData();
                }
            }
        }
    },

    methods: {
        mountedComponent() {
            this.loadData();
        },

        loadData() {
            if (!this.isToolboxLoading) {
                const availableLogs = [];
                const options = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                };
                if (this.logData.length > 0) {
                    this.logData.forEach(log => {
                        const date = new Date(log.date);
                        availableLogs.push({
                            label: date.toLocaleDateString(undefined, options),
                            value: log.name
                        });
                    });
                    availableLogs.push({
                        label: this.$tc('lengow-connector.toolbox.log.download_all_files'),
                        value: 'logs'
                    });
                }
                this.availableLogs = availableLogs;
                this.isLoading = false;
            }
        },

        onChangeLogFilter(value) {
            this.logFilter = value;
            if (this.logFilter !== '') {
                this.showButton = true;
            }
        },

        downloadLog() {
            this.buttonIsLoading = true;
            const fileName = this.logFilter;
            this.LengowConnectorToolboxService.downloadLog({ fileName })
                .then(response => {
                    this.forceFileDownload(response, fileName);
                })
                .finally(() => {
                    this.buttonIsLoading = false;
                });
        },

        forceFileDownload(response, fileName) {
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', fileName);
            document.body.appendChild(link);
            link.click();
        }
    }
});

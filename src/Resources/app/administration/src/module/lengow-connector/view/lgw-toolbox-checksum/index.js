import template from './lgw-toolbox-checksum.html.twig';
import './lgw-toolbox-checksum.scss';

const { Component } = Shopware;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('lgw-toolbox-checksum', {
    template,

    inject: [],

    mixins: [],

    data() {
        return {
            checksumAvailable: true,
            checksumSuccess: true,
            fileHasModified: false,
            fileHasDeleted: false,
            fileModified: [],
            fileDeleted: [],
            fileCheckedCounterLabel: '',
            fileCheckedCounterValue: true,
            fileModifiedCounterLabel: '',
            fileModifiedCounterValue: true,
            fileDeletedCounterLabel: '',
            fileDeletedCounterValue: true,
            isLoading: true,
        };
    },

    mounted() {
        this.mountedComponent();
    },

    computed: {
        ...mapState('lgwToolbox', ['checksumData']),

        ...mapGetters('lgwToolbox', {
            isToolboxLoading: 'isLoading',
        }),
    },

    watch: {
        isToolboxLoading: {
            handler() {
                if (!this.isToolboxLoading) {
                    this.loadData();
                }
            },
        },
    },

    methods: {
        mountedComponent() {
            this.loadData();
        },

        loadData() {
            if (!this.isToolboxLoading) {
                this.checksumAvailable = this.checksumData.available;
                this.checksumSuccess = this.checksumData.success;
                if (this.checksumData.file_modified_counter > 0) {
                    this.fileHasModified = true;
                    this.fileModifiedCounterValue = false;
                    this.fileModified = this.checksumData.file_modified;
                }
                if (this.checksumData.file_deleted_counter > 0) {
                    this.fileHasDeleted = true;
                    this.fileDeletedCounterValue = false;
                    this.fileDeleted = this.checksumData.file_deleted;
                }
                this.fileCheckedCounterLabel = `${this.checksumData.file_checked_counter}
                    ${this.$tc('lengow-connector.toolbox.checksum.file_checked')}`;
                this.fileModifiedCounterLabel = `${this.checksumData.file_modified_counter}
                    ${this.$tc('lengow-connector.toolbox.checksum.file_modified')}`;
                this.fileDeletedCounterLabel = `${this.checksumData.file_deleted_counter}
                    ${this.$tc('lengow-connector.toolbox.checksum.file_deleted')}`;
                this.isLoading = false;
            }
        },
    },
});

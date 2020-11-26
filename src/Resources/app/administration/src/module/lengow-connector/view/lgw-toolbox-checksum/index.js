import template from './lgw-toolbox-checksum.html.twig';

const { Component } = Shopware;

Component.register('lgw-toolbox-checksum', {
    template,

    inject: ['LengowConnectorToolboxService'],

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
            isLoading: false,
        };
    },

    created() {
        this.loadToolboxData();
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {},

    methods: {
        loadToolboxData() {
            this.isLoading = true;
            this.LengowConnectorToolboxService.getChecksumData()
                .then(data => {
                    this.checksumAvailable = data.available;
                    this.checksumSuccess = data.success;
                    if (data.file_modified_counter > 0) {
                        this.fileHasModified = true;
                        this.fileModifiedCounterValue = false;
                        this.fileModified = data.file_modified;
                    }
                    if (data.file_deleted_counter > 0) {
                        this.fileHasDeleted = true;
                        this.fileDeletedCounterValue = false;
                        this.fileDeleted = data.file_deleted;
                    }
                    this.fileCheckedCounterLabel = `${data.file_checked_counter} ${this.$tc(
                        'lengow-connector.toolbox.checksum.file_checked',
                    )}`;
                    this.fileModifiedCounterLabel = `${data.file_modified_counter} ${this.$tc(
                        'lengow-connector.toolbox.checksum.file_modified',
                    )}`;
                    this.fileDeletedCounterLabel = `${data.file_deleted_counter} ${this.$tc(
                        'lengow-connector.toolbox.checksum.file_deleted',
                    )}`;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});

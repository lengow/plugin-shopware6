import template from './lgw-catalog-select.hmtl.twig';
import './lgw-catalog-select.scss';

const { Component } = Shopware;
const { mapState } = Shopware.Component.getComponentHelper();

Component.register('lgw-catalog-select', {
    template,

    props: {
        salesChannel: {
            type: Object,
            required: true
        },
        onSelectChange: {
            type: Object,
            required: true
        },
        onOptionsLoaded: {
            type: Object,
            required: true
        },
        disabled: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            options: [],
            filter: []
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        ...mapState('lgwConnection', ['catalogList', 'catalogSelected', 'catalogSelectionChanged'])
    },

    watch: {
        catalogSelectionChanged() {
            if (this.catalogSelectionChanged) {
                this.createdComponent();
            }
        }
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            const catalogLinked = [];
            Object.keys(this.catalogSelected).forEach(salesChannelId => {
                if (salesChannelId !== this.salesChannel.id) {
                    const catalogs = this.catalogSelected[salesChannelId];
                    catalogs.forEach(catalogId => {
                        catalogLinked.push(catalogId);
                    });
                }
            });
            this.options = this.catalogList.filter((catalog) => {
                return !catalogLinked.includes(catalog.value);
            });
            Shopware.State.commit('lgwConnection/setOptionIsLoading', [this.salesChannel.id, false]);
            this.onOptionsLoaded();
            this.isLoading = false;
        },

        onChange(value) {
            this.filter = value;
            this.onSelectChange(this.salesChannel.id, value);
        }
    }
});

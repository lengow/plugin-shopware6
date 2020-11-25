import template from './views/lgw-list-element.html.twig';
import './views/lgw-list-element.scss';

const { Component } = Shopware;

Component.register('lgw-list-element', {
    template,

    inject: [],

    mixins: [],

    props: {
        title: {
            type: String,
            required: true,
        },
        value: {
            type: String,
            required: true,
        },
        type: {
            type: String,
            required: false,
            default: '',
        },
        helpText: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            showIcon: false,
            iconName: '',
            iconClass: '',
            content: '',
            isDate: false,
            isLoading: false,
        };
    },

    created() {
        this.createdComponent();
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {},

    methods: {
        createdComponent() {
            this.isLoading = true;
            const emptyValue = this.$tc('lengow-connector.toolbox.none');
            switch (this.type) {
                case 'bool':
                    if (this.value) {
                        this.showIcon = true;
                        this.iconName = 'small-default-checkmark-line-medium';
                        this.iconClass = 'is--active green';
                    } else {
                        if (this.helpText !== '') {
                            this.content = this.helpText;
                        } else {
                            this.showIcon = true;
                            this.iconName = 'small-default-x-line-medium';
                            this.iconClass = 'is--inactive red';
                        }
                    }
                    break;
                case 'date':
                    if (this.value !== 0) {
                        this.isDate = true;
                        this.content = new Date(this.value * 1000);
                    } else {
                        this.content = emptyValue;
                    }
                    break;
                case 'array':
                    if (this.value.length > 0) {
                        this.content = this.value.join(', ');
                    } else {
                        this.content = emptyValue;
                    }
                    break;
                default:
                    this.content = this.value;
                    break;
            }
            this.isLoading = false;
        },
    },
});

import template from './lgw-description-list-element.html.twig';
import './lgw-description-list-element.scss';

const { Component } = Shopware;

Component.register('lgw-description-list-element', {
    template,

    inject: [],

    mixins: [],

    props: {
        title: {
            type: String,
            required: true
        },
        value: {
            type: String,
            required: true
        },
        type: {
            type: String,
            required: false,
            default: ''
        },
        helpText: {
            type: String,
            required: false,
            default: ''
        }
    },

    data() {
        return {
            showIcon: false,
            iconName: '',
            iconClass: '',
            content: '',
            isDate: false,
            isArray: false,
            isLoading: false
        };
    },

    created() {
        this.createdComponent();
    },

    metaInfo() {
        return {
            title: this.$createTitle()
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
                        this.iconName = 'solid-checkmark-s';
                        this.iconClass = 'is--active lgw-check-green';
                    } else if (this.helpText !== '') {
                        this.content = this.helpText;
                    } else {
                        this.showIcon = true;
                        this.iconName = 'regular-times-xs';
                        this.iconClass = 'is--inactive lgw-check-red';
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
                        this.isArray = true;
                        this.content = this.value;
                    } else {
                        this.content = emptyValue;
                    }
                    break;
                default:
                    this.content = this.value;
                    break;
            }
            this.isLoading = false;
        }
    }
});

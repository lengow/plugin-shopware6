import template from './lgw-lockable-string-field.html.twig';

const { Component } = Shopware;

Component.register('lgw-lockable-string-field', {
    template,

    props: {
        fieldContent: {
            type: String,
            required: false,
            default: '',
        },
        locked: {
            type: Boolean,
            required: false,
            default: true,
        },
        fieldPlaceholder: {
            type: String,
            required: false,
            default: '',
        },
        fieldLabel: {
            type: String,
            required: false,
            default: '',
        },
        onSaveSettings: {
            type: Object,
            required: true,
        },
        settingsKey: {
            type: String,
            required: false,
            default: '',
        },
        helpText: {
            type: String,
            required: false,
            default: ''
        },
        settingsSalesChannelId: {
            type: String,
            required: false,
            default: '',
        },
    },

    methods: {
        handleChange(value) {
          this.fieldContent = value;
        },
    }
});

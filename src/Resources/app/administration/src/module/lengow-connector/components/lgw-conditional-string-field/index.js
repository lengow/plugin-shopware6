import template from './lgw-conditional-string-field.html.twig';

const { Component } = Shopware;

Component.register('lgw-conditional-string-field', {
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
        switchLabel: {
            type: String,
            required: true,
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
        switchSettingsKey: {
            type: String,
            required: false,
            default: '',
        },
        fieldSettingsKey: {
            type: String,
            required: false,
            default: '',
        },
        settingsSalesChannelId: {
            type: String,
            required: false,
            default: '',
        },
        helpText: {
            type: String,
            required: false,
            default: ''
        },
        switchHelpText: {
            type: String,
            required: false,
            default: ''
        },
    },

    computed: {
      isLocked() {
          return !this.locked;
      },
    },
});

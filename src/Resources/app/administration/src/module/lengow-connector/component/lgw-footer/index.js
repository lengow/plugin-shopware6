import template from './lgw-footer.html.twig';
import './lgw-footer.css';
import { envMixin, LENGOW_URL, MODULE_VERSION } from '../../../const';

const { Component } = Shopware;

Component.register('lgw-footer', {
    template,

    mixins: [envMixin],

    data() {
        return {
            lengowUrl: LENGOW_URL,
            moduleVersion: MODULE_VERSION,
            currentYear: new Date().getFullYear(),
            preprod: false
        };
    },

    created() {
        if (this.lengowUrl === 'https://my.lengow.net') {
            this.preprod = true;
        }
    }
});

import template from './views/lengow-footer.html.twig';
import './views/lengow-footer.css';
import { envMixin, LENGOW_URL, MODULE_VERSION } from "../../../const";

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lengow-footer', {
    template,

    mixins: [envMixin],

    data() {
        return {
            lengowUrl: LENGOW_URL,
            moduleVersion: MODULE_VERSION,
            currentYear: new Date().getFullYear(),
            preprod: false,
        }
    },

    created() {
        if (this.lengowUrl === 'https://my.lengow.net') {
            this.preprod = true;
        }
    },


});

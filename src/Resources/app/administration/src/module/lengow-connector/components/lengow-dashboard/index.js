import template from './views/lengow-dashboard.html.twig';
import './views/lengow-dashboard.scss';
import { envMixin, LENGOW_URL } from "../../../const";

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lengow-dashboard', {
    template,

    mixins: [envMixin],

    data() {
        return {
            lengow_url: LENGOW_URL
        }
    },
});

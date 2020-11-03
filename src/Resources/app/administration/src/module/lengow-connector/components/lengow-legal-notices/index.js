import template from './views/lengow-legal-notices.html.twig';
import {LENGOW_URL} from "../../../const";

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lengow-legal-notices', {
    template,
});

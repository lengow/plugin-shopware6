import template from './views/lengow-contact.html.twig';
import {LENGOW_URL} from "../../../const";

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lengow-contact', {
    template,
});

import template from './views/lengow-dashboard.html.twig';
import './views/lengow-dashboard.scss';

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lengow-dashboard', {
    template,
});

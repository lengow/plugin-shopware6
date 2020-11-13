import template from './views/sw-order-detail.html.twig';

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.override('sw-order-detail', {
    template,
});

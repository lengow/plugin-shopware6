import template from './views/lengow-settings.html.twig';

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lengow-settings', {
    template,
});

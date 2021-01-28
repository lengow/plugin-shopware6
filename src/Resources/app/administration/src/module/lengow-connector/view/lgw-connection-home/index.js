import template from './lgw-connection-home.html.twig';
import { LENGOW_URL } from '../../../const';

const { Component } = Shopware;

Component.register('lgw-connection-home', {
    template,

    data() {
        return {
            isLoading: false,
            lengowUrl: LENGOW_URL
        };
    }
});

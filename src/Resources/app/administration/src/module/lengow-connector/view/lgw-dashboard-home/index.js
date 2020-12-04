import template from './lgw-dashboard-home.html.twig';
import './lgw-dashboard-home.scss';
import { LENGOW_URL } from '../../../const';

const { Component } = Shopware;

Component.register('lgw-dashboard-home', {
    template,

    data() {
        return {
            lengow_url: LENGOW_URL,
            isLoading: true
        };
    }
});

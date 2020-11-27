import template from './views/lgw-free-trial-expired.html.twig';
import './views/lgw-free-trial-expired.scss';
import {LENGOW_URL} from "../../../../../const";


const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lgw-free-trial-expired', {
    template,

    inject: ['LengowSynchronisationService'],

    data() {
        return {
            lengow_url: LENGOW_URL,
        }
    },

    methods: {
        reloadAccountStatus() {
            this.LengowSynchronisationService.getAccountStatus(true).then(result => {
                if (result.success) {
                    window.location.reload();
                }
            })
        },
    }

});

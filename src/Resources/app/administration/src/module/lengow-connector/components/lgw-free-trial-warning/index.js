import template from './views/lgw-free-trial-warning.html.twig';
import './views/lgw-free-trial-warning.scss';

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lgw-free-trial-warning', {
    template,

    inject: ['LengowSynchronisationService'],

    props: {
        setTrialExpired: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            freeTrialEnabled: true,
            dayLeft: '',
            isExpired: false,
        }
    },

    created() {
        this.LengowSynchronisationService.getAccountStatus(false).then(result => {
            if (result.success) {
                this.freeTrialEnabled = result.type === 'free_trial';
                this.dayLeft = result.day;
                this.isExpired = result.expired;
                if (this.isExpired) {
                    this.setTrialExpired();
                }
            }
        })
    },
});

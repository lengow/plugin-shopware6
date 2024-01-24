import template from './lgw-free-trial-warning.html.twig';
import './lgw-free-trial-warning.scss';
import { LENGOW_URL, BASE_LENGOW_URL } from '../../../const';

const {
    Component,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-free-trial-warning', {
    template,

    inject: ['repositoryFactory'],

    props: {
        accountStatusData: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            dayLeft: '',
            link: LENGOW_URL,
        };
    },

    computed: {
        lengowConfigRepository() {
            return this.repositoryFactory.create('lengow_settings');
        }
    },

    created() {
        this.createdComponent();
        this.loadEnvironmentUrl();
    },

    methods: {
        createdComponent() {
            this.dayLeft = this.accountStatusData.day;
        },

        loadEnvironmentUrl() {
            const lengowConfigCriteria = new Criteria();
            lengowConfigCriteria.addFilter(Criteria.equals('name', 'lengowEnvironmentUrl'));
            this.lengowConfigRepository.search(lengowConfigCriteria, Shopware.Context.api).then(result => {
                if (result.total > 0) {
                    this.link = BASE_LENGOW_URL + result[0].value;
                }
            });
        }
    }
});

import template from './lgw-free-trial-warning.html.twig';
import './lgw-free-trial-warning.scss';

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
            link: 'https://my.lengow.net',
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
                    this.link = 'https://my.lengow' + result[0].value;
                }
            });
        }
    }
});

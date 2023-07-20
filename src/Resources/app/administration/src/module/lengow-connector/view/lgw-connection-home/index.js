import template from './lgw-connection-home.html.twig';

const {
    Component,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-connection-home', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            isLoading: false,
            lengowUrl: 'https://my.lengow.net',
        };
    },

    computed: {
        lengowConfigRepository() {
            return this.repositoryFactory.create('lengow_settings');
        }
    },

    created() {
        this.loadEnvironmentUrl();
    },

    methods: {
        loadEnvironmentUrl() {
            const lengowConfigCriteria = new Criteria();
            lengowConfigCriteria.addFilter(Criteria.equals('name', 'lengowEnvironmentUrl'));
            this.lengowConfigRepository.search(lengowConfigCriteria, Shopware.Context.api).then(result => {
                if (result.total > 0) {
                    this.lengowUrl = 'https://my.lengow' + result[0].value;
                }
            });
        }
    }
});

import template from './lgw-debug-warning.html.twig';
import './lgw-debug-warning.scss';

const {
    Component,
    Data: { Criteria },
} = Shopware;

Component.register('lgw-debug-warning', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            debugMode: false,
        };
    },

    computed: {
        lengowSettingsRepository() {
            return this.repositoryFactory.create('lengow_settings');
        },
    },

    created() {
        const criteria = new Criteria();
        criteria.addFilter(Criteria.equals('name', 'lengowDebugEnabled'));
        this.lengowSettingsRepository.search(criteria, Shopware.Context.api).then(response => {
            if (response.total > 0) {
                this.debugMode = response.first().value === '1';
            }
        });
    },
});

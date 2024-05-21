import template from './lgw-connection-home.html.twig';
import { LENGOW_URL, BASE_LENGOW_URL } from '../../../const';

const {
    Component,
    Filter,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-connection-home', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            isLoading: false,
            lengowUrl: LENGOW_URL,
        };
    },

    computed: {
        assetFilter() {
            return Filter.getByName('asset');
        },
        lengowConfigRepository() {
            return this.repositoryFactory.create('lengow_settings');
        }
    },

    created() {
        this.assetFilter();
        this.loadEnvironmentUrl();
    },

    methods: {
        loadEnvironmentUrl() {
            const lengowConfigCriteria = new Criteria();
            lengowConfigCriteria.addFilter(Criteria.equals('name', 'lengowEnvironmentUrl'));
            this.lengowConfigRepository.search(lengowConfigCriteria, Shopware.Context.api).then(result => {
                if (result.total > 0) {
                    this.lengowUrl = BASE_LENGOW_URL + result[0].value;
                }
            });
        }
    }
});

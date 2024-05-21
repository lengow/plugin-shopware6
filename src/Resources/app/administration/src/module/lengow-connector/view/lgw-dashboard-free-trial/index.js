import template from './lgw-dashboard-free-trial.html.twig';
import './lgw-dashboard-free-trial.scss';
import { LENGOW_URL, BASE_LENGOW_URL } from '../../../const';

const {
    Component,
    Filter,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-dashboard-free-trial', {
    template,

    inject: ['LengowConnectorSyncService', 'repositoryFactory'],

    data() {
        return {
            lengowUrl: LENGOW_URL
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
        reloadAccountStatus() {
            this.LengowConnectorSyncService.getAccountStatus(true).then(result => {
                if (result.success) {
                    window.location.reload();
                }
            });
        },

        loadEnvironmentUrl() {
            const lengowConfigCriteria = new Criteria();
            lengowConfigCriteria.addFilter(Criteria.equals('name', 'lengowEnvironmentUrl'));
            this.lengowConfigRepository.search(lengowConfigCriteria, Shopware.Context.api).then(result => {
                if (result.total > 0) {
                    this.lengowUrl = BASE_LENGOW_URL + result[0].value;
                    this.createdComponent();
                }
            });
        }
    }
});

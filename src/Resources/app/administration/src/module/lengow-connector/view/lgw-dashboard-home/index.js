import template from './lgw-dashboard-home.html.twig';
import './lgw-dashboard-home.scss';
import { LENGOW_URL, BASE_LENGOW_URL } from '../../../const';

const {
    Component,
    Filter,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-dashboard-home', {
    template,

    inject: ['repositoryFactory','LengowConnectorSyncService'],

    data() {
        return {
            lengowEnvironmentUrl: LENGOW_URL,
            helpCenterLink: ''
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
        this.createdComponent();
        this.loadEnvironmentUrl();
    },

    methods: {
        createdComponent() {
            this.LengowConnectorSyncService.getPluginLinks().then(result => {
                if (result.success) {
                    this.helpCenterLink = result.links.help_center;
                }
            });
        },
        loadEnvironmentUrl() {
            const lengowConfigCriteria = new Criteria();
            lengowConfigCriteria.addFilter(Criteria.equals('name', 'lengowEnvironmentUrl'));
            this.lengowConfigRepository.search(lengowConfigCriteria, Shopware.Context.api).then(result => {
                if (result.total > 0) {
                    this.lengowEnvironmentUrl = BASE_LENGOW_URL + result[0].value;
                }
            });
        }

    }
});

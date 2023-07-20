import template from './lgw-footer.html.twig';
import './lgw-footer.css';
import { MODULE_VERSION, LENGOW_URL, BASE_LENGOW_URL } from '../../../const';

const {
    Component,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-footer', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            lengowEnvironmentUrl: LENGOW_URL,
            moduleVersion: MODULE_VERSION,
            currentYear: new Date().getFullYear(),
            preprod: false
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
                    this.lengowEnvironmentUrl = BASE_LENGOW_URL + result[0].value;
                    this.checkPreprod();
                }
            });
        },

        checkPreprod() {
            if (this.lengowEnvironmentUrl === LENGOW_URL) {
                this.preprod = true;
            } else {
                this.preprod = false;
            }
        },
    }
});
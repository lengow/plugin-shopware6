import template from './lgw-footer.html.twig';
import './lgw-footer.css';
import { MODULE_VERSION } from '../../../const';

const {
    Component,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-footer', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            lengowEnvironmentUrl: 'https://my.lengow.net',
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
                    this.lengowEnvironmentUrl = 'https://my.lengow' + result[0].value;
                    this.checkPreprod();
                }
            });
        },

        checkPreprod() {
            if (this.lengowEnvironmentUrl === 'https://my.lengow.net') {
                this.preprod = true;
            } else {
                this.preprod = false;
            }
        },
    }
});
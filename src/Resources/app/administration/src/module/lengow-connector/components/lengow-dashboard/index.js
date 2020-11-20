import template from './views/lengow-dashboard.html.twig';
import lgwConnexionIframe from './components/lgw-Iframe-connexion';
import './views/lengow-dashboard.scss';
import { envMixin, LENGOW_URL } from "../../../const";

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('lengow-dashboard', {
    template,

    inject: ['repositoryFactory'],

    mixins: [envMixin],

    data() {
        return {
            lengow_url: LENGOW_URL,
            isNew: false,
            isLoading: true,
        }
    },

    computed: {
        lengowSettingsRepository() {
            return this.repositoryFactory.create('lengow_settings');
        },
    },

    created() {
        this.isNewMerchant();
    },

    methods: {
        isNewMerchant() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equalsAny('name', [
                'lengowAccountId',
                'lengowAccessToken',
                'lengowSecretToken',
            ]));
            this.lengowSettingsRepository.search(criteria, Shopware.Context.api).then(response => {
                const settings = [];
                if (response.total > 0) {
                    response.forEach(setting => {
                        settings[setting.name] = setting.value;
                    });
                }
                if (!settings['lengowAccountId'] || !settings['lengowAccessToken'] || !settings['lengowSecretToken']) {
                    this.isNew = true;
                }
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
});

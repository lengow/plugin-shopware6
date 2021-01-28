import template from './lgw-connection.html.twig';
import './lgw-connection.scss';
import lgwConnectionState from './state';

const {
    Component,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-connection', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            isNew: false,
            isLoading: true
        };
    },

    computed: {
        lengowSettingsRepository() {
            return this.repositoryFactory.create('lengow_settings');
        }
    },

    beforeCreate() {
        Shopware.State.registerModule('lgwConnection', lgwConnectionState);
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        Shopware.State.unregisterModule('lgwConnection');
    },

    methods: {
        createdComponent() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equalsAny('name', [
                'lengowAccountId',
                'lengowAccessToken',
                'lengowSecretToken'
            ]));
            this.lengowSettingsRepository.search(criteria, Shopware.Context.api).then(response => {
                const settings = [];
                if (response.total > 0) {
                    response.forEach(setting => {
                        settings[setting.name] = setting.value;
                    });
                }
                if (!settings.lengowAccountId || !settings.lengowAccessToken || !settings.lengowSecretToken) {
                    this.isNew = true;
                }
            }).finally(() => {
                this.isLoading = false;
                if (this.isNew === false) {
                    this.redirectToDashboard();
                }
            });
        },

        redirectToDashboard() {
            this.$router.push({ name: 'lengow.connector.dashboard' });
        }
    }
});

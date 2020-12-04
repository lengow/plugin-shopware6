import template from './lgw-country-icon.html.twig';
import './lgw-country-icon.scss';

const {
    Component,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-country-icon', {
    template,

    inject: [
        'repositoryFactory',
        'acl'
    ],

    props: {
        codeIsoA2: {
            type: String,
            required: true,
            default: ''
        }
    },

    data() {
        return {
            isLoading: true,
            countryName: '',
            countryIso: ''
        };
    },

    computed: {
        countryRepository() {
            return this.repositoryFactory.create('country');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('iso', this.codeIsoA2));
            this.countryRepository
                .search(criteria, Shopware.Context.api)
                .then(response => {
                    if (response.total > 0) {
                        const country = response.first();
                        this.countryName = country.name;
                        this.countryIso = country.iso;
                    } else {
                        this.countryName = 'Others';
                        this.countryIso = 'OTHERS';
                    }
                })
                .catch(() => {
                    this.countryName = 'Others';
                    this.countryIso = 'OTHERS';
                });
        },

        loaded() {
            this.isLoading = false;
        }
    }
});

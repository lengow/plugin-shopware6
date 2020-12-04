import template from './lgw-action-label.html.twig';
import './lgw-action-label.scss';
import { ACTION_STATE, ACTION_TYPE } from '../../../const';

const {
    Component,
    Data: { Criteria }
} = Shopware;

Component.register('lgw-action-label', {
    template,

    inject: ['repositoryFactory'],

    props: {
        orderId: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            isLoading: false,
            hasActiveAction: false,
            labelContent: '',
            labelTitle: ''
        };
    },

    computed: {
        lengowActionRepository() {
            return this.repositoryFactory.create('lengow_action');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            const criteria = new Criteria();
            criteria.addFilter(
                Criteria.multi('AND', [
                    Criteria.equals('order.id', this.orderId),
                    Criteria.equals('state', ACTION_STATE.new)
                ])
            );
            this.lengowActionRepository
                .search(criteria, Shopware.Context.api)
                .then(response => {
                    if (response.total > 0) {
                        this.hasActiveAction = true;
                        const action = response.first();
                        this.labelContent =
                            action.actionType === ACTION_TYPE.ship
                                ? this.$tc('lengow-connector.order.action_label.action_ship_sent')
                                : this.$tc(
                                    'lengow-connector.order.action_label.action_cancel_sent'
                                );
                        this.labelTitle = this.$tc(
                            'lengow-connector.order.action_label.action_waiting_return'
                        );
                    }
                })
                .finally(() => {
                    this.isLoading = false;
                });
        }
    }
});

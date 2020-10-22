export const LENGOW_URL = 'https://my.lengow.io';
//export const LENGOW_URL = 'https://my.lengow.net';

export const envMixin = {
    data() {
        return {
            LENGOW_URL,
        }
    }
}

export const ORDER_LENGOW_STATES = {
    accepted: 'accepted',
    waiting_shipment: 'waiting_shipment',
    shipped: 'shipped',
    refunded: 'refunded',
    closed: 'closed',
    canceled: 'canceled',
}

export const ORDER_TYPES = {
    prime: 'is_prime',
    express: 'is_express',
    business: 'is_business',
    delivered_by_marketplace: 'is_delivered_by_marketplace',
}

export const ORDER_SYNCHRONISATION = {
    manual: 'manual',
    cron: 'cron',
}
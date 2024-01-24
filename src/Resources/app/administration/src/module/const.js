export const LENGOW_URL = 'https://my.lengow.net';
export const BASE_LENGOW_URL = 'https://my.lengow';
export const MODULE_VERSION = '1.1.3';

export const ORDER_LENGOW_STATES = {
    accepted: 'accepted',
    waiting_shipment: 'waiting_shipment',
    shipped: 'shipped',
    refunded: 'refunded',
    closed: 'closed',
    canceled: 'canceled',
    partial_refunded: 'partial_refunded'
};

export const ORDER_TYPES = {
    prime: 'is_prime',
    express: 'is_express',
    business: 'is_business',
    delivered_by_marketplace: 'is_delivered_by_marketplace'
};

export const ORDER_SYNCHRONISATION = {
    manual: 'manual',
    cron: 'cron'
};

export const ERROR_TYPE = {
    import: 1,
    send: 2
};

export const ACTION_BUTTON = {
    reimport: 'reimport',
    resend: 'resend'
};

export const ACTION_STATE = {
    new: 0,
    finish: 1
};

export const ACTION_TYPE = {
    ship: 'ship',
    cancel: 'cancel'
};

export const ORDER_PROCESS_STATE = {
    new: 0,
    import: 1,
    finish: 2
};

export const SHOPWARE_ORDER_DELIVERY_STATE = {
    shipped: 'shipped'
};

export const SHOPWARE_ORDER_STATE = {
    canceled: 'canceled'
};

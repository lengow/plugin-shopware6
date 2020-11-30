import './components/lengow-export-list';
import './components/lgw-action-button';
import './components/lgw-action-label';
import './components/lgw-conditional-string-field';
import './components/lgw-country-icon';
import './components/lgw-debug-warning';
import './components/lgw-description-list-element';
import './components/lgw-footer';
import './components/lgw-free-trial-warning';
import './components/lgw-lockable-string-field';
import './components/lgw-order-state-label';
import './components/lgw-order-type-icon';
import './components/lgw-update-warning';
import './extension/sw-order-detail';
import './page/lgw-contact';
import './page/lgw-dashboard';
import './page/lgw-legal-notices';
import './page/lgw-order-list';
import './page/lgw-setting';
import './page/lgw-toolbox';
import './view/lgw-dashboard-connexion';
import './view/lgw-dashboard-free-trial';
import './view/lgw-order-detail-extension';
import './view/lgw-setting-export';
import './view/lgw-setting-general';
import './view/lgw-setting-import';
import './view/lgw-toolbox-base';
import './view/lgw-toolbox-checksum';
import './view/lgw-toolbox-log';

Shopware.Module.register('lengow-connector', {
    color: '#ff3d58',
    icon: 'default-shopping-paper-bag-product',
    title: 'Lengow',
    description: 'Lengow',

    routes: {
        dashboard: {
            component: 'lgw-dashboard',
            path: 'dashboard',
        },
        export: {
            component: 'lengow-export-list',
            path: 'export',
            meta: {
                parentPath: 'lengow.connector.dashboard',
            },
        },
        order: {
            component: 'lgw-order-list',
            path: 'order',
            meta: {
                parentPath: 'lengow.connector.dashboard',
            },
        },
        setting: {
            component: 'lgw-setting',
            path: 'setting',
            meta: {
                parentPath: 'lengow.connector.dashboard',
            },
        },
        legal: {
            component: 'lgw-legal-notices',
            path: 'legal',
            meta: {
                parentPath: 'lengow.connector.dashboard',
            },
        },
        contact: {
            component: 'lgw-contact',
            path: 'contact',
            meta: {
                parentPath: 'lengow.connector.dashboard',
            },
        },
        toolbox: {
            component: 'lgw-toolbox',
            path: 'toolbox',
            redirect: {
                name: 'lengow.connector.toolbox.base'
            },
            children: {
                base: {
                    component: 'lgw-toolbox-base',
                    path: 'base',
                    meta: {
                        parentPath: 'lengow.connector.dashboard',
                    }
                },
                checksum: {
                    component: 'lgw-toolbox-checksum',
                    path: 'checksum',
                    meta: {
                        parentPath: 'lengow.connector.dashboard',
                    }
                },
                log: {
                    component: 'lgw-toolbox-log',
                    path: 'log',
                    meta: {
                        parentPath: 'lengow.connector.dashboard',
                    }
                }
            }
        },
    },

    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.order.detail') {
            currentRoute.children.push({
                name: 'lgw.order.detail',
                path: '/sw/order/detail/:id/lgw',
                component: 'lgw-order-detail-extension',
                meta: {
                    parentPath: "sw.order.index"
                }
            });
        }
        next(currentRoute);
    },

    navigation: [
        {
            label: 'Lengow',
            color: '#ff3d58',
            path: 'lengow.connector.dashboard',
            icon: 'default-shopping-paper-bag-product',
            position: 100,
        },
    ],
});

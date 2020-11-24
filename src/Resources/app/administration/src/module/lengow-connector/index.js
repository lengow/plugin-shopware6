import './components/lgw-debug-warning';
import './components/lgw-update-warning'
import './components/lengow-export-list';
import './components/lengow-dashboard';
import './components/lengow-order-list';
import './components/lengow-settings';
import './components/lengow-legal-notices';
import './components/lengow-contact';
import './components/lengow-footer';
import './extension/sw-order-detail';
import './components/lgw-order-detail-extension';

Shopware.Module.register('lengow-connector', {
    color: '#ff3d58',
    icon: 'default-shopping-paper-bag-product',
    title: 'Lengow',
    description: 'Lengow',

    routes: {
        dashboard: {
            component: 'lengow-dashboard',
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
            component: 'lengow-order-list',
            path: 'order',
            meta: {
                parentPath: 'lengow.connector.dashboard',
            },
        },
        settings: {
            component: 'lengow-settings',
            path: 'settings',
            meta: {
                parentPath: 'lengow.connector.dashboard',
            },
        },
        legal: {
            component: 'lengow-legal-notices',
            path: 'legal',
            meta: {
                parentPath: 'lengow.connector.dashboard',
            },
        },
        contact: {
            component: 'lengow-contact',
            path: 'contact',
            meta: {
                parentPath: 'lengow.connector.dashboard',
            },
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

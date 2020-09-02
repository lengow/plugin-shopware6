import './components/lengow-export-list';
import './components/lengow-dashboard';
import './components/lengow-order-list';
import './components/lengow-settings';

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
        parentPath: 'lengow.connector.lengow-dashboard',
      },
    },
    order: {
      component: 'lengow-order-list',
      path: 'order',
      meta: {
        parentPath: 'lengow.connector.lengow-dashboard',
      },
    },
    settings: {
      component: 'lengow-settings',
      path: 'settings',
      meta: {
        parentPath: 'lengow.connector.lengow-dashboard',
      },
    },
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

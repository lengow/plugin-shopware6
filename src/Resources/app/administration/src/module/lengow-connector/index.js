import './components/lengow-export-list';

Shopware.Module.register('lengow-connector', {
  color: '#ff3d58',
  icon: 'default-shopping-paper-bag-product',
  title: 'Lengow',
  description: 'Lengow',

  routes: {
    list: {
      component: 'lengow-export-list',
      path: 'list',
    },
    detail: {
      component: 'lengow-export-detail',
      path: 'detail/:id',
      meta: {
        parentPath: 'lengow.export.list',
      },
    },
  },

  navigation: [
    {
      label: 'Lengow',
      color: '#ff3d58',
      path: 'lengow.connector.list',
      icon: 'default-shopping-paper-bag-product',
      position: 100,
    },
  ],
});

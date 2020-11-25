import LengowConnectorOrderService from '../service/api/lengow-connector-order-service';
import LengowSynchronisationService from '../service/Sync/lengow-synchronisation-service';
import LengowConnectorToolboxService from '../service/api/lengow-connector-toolbox-service';

const { Application } = Shopware;

Application.addServiceProvider('LengowConnectorOrderService', container => {
  const initContainer = Application.getContainer('init');
  return new LengowConnectorOrderService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('LengowSynchronisationService', container => {
  const initContainer = Application.getContainer('init');
  return new LengowSynchronisationService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('LengowConnectorToolboxService', container => {
  const initContainer = Application.getContainer('init');
  return new LengowConnectorToolboxService(initContainer.httpClient, container.loginService);
});

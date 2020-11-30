import LengowConnectorOrderService from '../service/api/lengow-connector-order-service';
import LengowConnectorSyncService from '../service/api/lengow-connector-sync-service';
import LengowConnectorToolboxService from '../service/api/lengow-connector-toolbox-service';
import LengowConnectorExportService from '../service/api/lengow-connector-export-service';

const { Application } = Shopware;

Application.addServiceProvider('LengowConnectorOrderService', container => {
  const initContainer = Application.getContainer('init');
  return new LengowConnectorOrderService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('LengowConnectorSyncService', container => {
  const initContainer = Application.getContainer('init');
  return new LengowConnectorSyncService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('LengowConnectorToolboxService', container => {
  const initContainer = Application.getContainer('init');
  return new LengowConnectorToolboxService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('LengowConnectorExportService', container => {
    const initContainer = Application.getContainer('init');
    return new LengowConnectorExportService(initContainer.httpClient, container.loginService);
});

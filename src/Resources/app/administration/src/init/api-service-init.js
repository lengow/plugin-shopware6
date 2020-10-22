import LengowConnectorOrderService from '../service/api/lengow-connector-order-service';

const { Application } = Shopware;

Application.addServiceProvider('LengowConnectorOrderService', (container) => {
    const initContainer = Application.getContainer('init');

    return new LengowConnectorOrderService(initContainer.httpClient, container.loginService);
});
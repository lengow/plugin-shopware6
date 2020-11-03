const { ApiService } = Shopware.Classes;

class LengowConnectorOrderService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'lengow') {
        super(httpClient, loginService, apiEndpoint);
    }

    synchroniseOrders() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/order/synchronise-orders`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    reImportOrder(data) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/order/reimport-order`,
                JSON.stringify(data),
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getAvailableMarketplaces() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/order/get-available-marketplaces`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getOrderErrorMessages(data) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/order/get-order-errors`,
                JSON.stringify(data),
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default LengowConnectorOrderService;

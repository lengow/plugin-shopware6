const ApiService = Shopware.Classes.ApiService;

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
}

export default LengowConnectorOrderService;
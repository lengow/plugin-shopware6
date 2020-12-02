const { ApiService } = Shopware.Classes;

class LengowConnectorExportService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'lengow') {
        super(httpClient, loginService, apiEndpoint);
    }

    getExportCount(salesChannelId) {
        const headers = this.getBasicHeaders();
        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/export/get-export-count`,
                {
                    headers: headers,
                    params: {salesChannelId: salesChannelId}
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getProductCountValue(productId, salesChannelId) {
        const headers = this.getBasicHeaders();
        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/export/get-product-count`,
                {
                    headers: headers,
                    params: {productId: productId, salesChannelId: salesChannelId}
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getProductList(salesChannelId) {
        const headers = this.getBasicHeaders();
        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/export/get-product-list`,
                {
                    headers: headers,
                    params: {salesChannelId: salesChannelId}
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default LengowConnectorExportService;

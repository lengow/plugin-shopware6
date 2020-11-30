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
}

export default LengowConnectorExportService;

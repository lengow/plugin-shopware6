const ApiService = Shopware.Classes.ApiService;

class LengowConnectorSyncService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'lengow') {
        super(httpClient, loginService, apiEndpoint);
    }

    getPluginData() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/sync/get-plugin-data`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getPluginLinks() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/sync/get-plugin-links`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getAccountStatus(shouldForce) {
        const headers = this.getBasicHeaders();
        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/sync/get-account-status`,
                {
                    headers: headers,
                    params: { force: shouldForce }
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    remindMeLater() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/sync/remind-me-later`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default LengowConnectorSyncService;

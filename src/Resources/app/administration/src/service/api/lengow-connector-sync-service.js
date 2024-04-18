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
    OnChangeRtn(orderId, returnTrackingNumbers) {
        const headers = this.getBasicHeaders();
        const data = {
            order_id: orderId,
            return_tracking_numbers: returnTrackingNumbers
        };

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/sync/save-return-tracking-numbers`,
                data,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
    OnLoadRtn(orderId, returnTrackingNumbers) {
        const headers = this.getBasicHeaders();
        const data = {
            order_id: orderId,
            return_tracking_numbers: returnTrackingNumbers
        };

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/sync/load-return-tracking-numbers`,
                data,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
    OnChangeRc(orderId, returnCarrierName) {
        const headers = this.getBasicHeaders();
        const data = {
            order_id: orderId,
            return_carrier: returnCarrierName
        };

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/sync/save-return-carrier`,
                data,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
    OnLoadRc(orderId) {
        const headers = this.getBasicHeaders();
        const data = {
            order_id: orderId
        };

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/sync/load-return-carrier`,
                data,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
    verifyArgRtnRc(orderId) {
        const headers = this.getBasicHeaders();
        const data = {
            order_id: orderId
        };

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/sync/verifyArgRtn`,
                data,
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

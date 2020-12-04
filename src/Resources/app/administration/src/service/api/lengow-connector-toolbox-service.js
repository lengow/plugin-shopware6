const { ApiService } = Shopware.Classes;

class LengowConnectorToolboxService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'lengow') {
        super(httpClient, loginService, apiEndpoint);
    }

    getOverviewData() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/toolbox/get-overview-data`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getChecksumData() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/toolbox/get-checksum-data`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getLogData() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/toolbox/get-log-data`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    downloadLog(data) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/order/download-log`,
                JSON.stringify(data),
                {
                    headers: headers
                }
            );
    }
}

export default LengowConnectorToolboxService;

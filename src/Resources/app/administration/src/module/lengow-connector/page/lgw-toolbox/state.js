export default {
    namespaced: true,

    state() {
        return {
            overviewData: [],
            checksumData: [],
            logData: [],
            loading: {
                overview: false,
                checksum: false,
                log: false,
            },
        };
    },

    getters: {
        isLoading: state => {
            return Object.values(state.loading).some(loadState => loadState);
        },

        overviewData(state) {
            return state.overviewData;
        },

        checksumData(state) {
            return state.checksumData;
        },

        logData(state) {
            return state.logData;
        },
    },

    mutations: {
        setLoading(state, value) {
            const name = value[0];
            const data = value[1];

            if (typeof data !== 'boolean') {
                return false;
            }

            if (state.loading[name] !== undefined) {
                state.loading[name] = data;
                return true;
            }
            return false;
        },

        setOverviewData(state, overviewData) {
            state.overviewData = overviewData;
        },

        setChecksumData(state, checksumData) {
            state.checksumData = checksumData;
        },

        setLogData(state, logData) {
            state.logData = logData;
        },
    },
};

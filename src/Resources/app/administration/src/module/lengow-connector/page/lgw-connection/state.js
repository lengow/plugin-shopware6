export default {
    namespaced: true,

    state() {
        return {
            catalogList: [],
            catalogSelected: {},
            loading: {},
            optionIsLoading: false,
            catalogSelectionChanged: false
        };
    },

    getters: {
        catalogList(state) {
            return state.catalogList;
        },

        catalogSelected(state) {
            return state.catalogSelected;
        },

        optionIsLoading(state) {
            return state.optionIsLoading;
        },

        catalogSelectionChanged(state) {
            return state.catalogSelectionChanged;
        }
    },

    mutations: {
        setCatalogList(state, catalogList) {
            state.catalogList = catalogList;
        },

        setCatalogSelected(state, catalogSelected) {
            state.catalogSelected = catalogSelected;
        },

        setOptionIsLoading(state, value) {
            const name = value[0];
            const data = value[1];
            if (typeof data !== 'boolean') {
                return false;
            }
            state.loading[name] = data;
            state.optionIsLoading = Object.values(state.loading).some(loadState => loadState);
            return true;
        },

        setCatalogSelectionChanged(state, catalogSelectionChanged) {
            state.catalogSelectionChanged = catalogSelectionChanged;
        }
    }
};

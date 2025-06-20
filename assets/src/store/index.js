/**
 * WP-data store  (namespace: sc/feeds)
 */
import {registerStore} from '@wordpress/data';
import {controls} from '@wordpress/data-controls';
import apiFetch from '@wordpress/api-fetch';

export const STORE_KEY = 'sc/feeds';
const ENDPOINT = '/sc-autoparser/v1/feeds';

/* ---------- state ---------- */
const DEFAULT_STATE = {
    list: [],
    isLoading: false,
    formFeed: null,
};

/* ---------- actions (generator) ---------- */
const actions = {
    * fetchFeeds() {
        yield {type: 'SET_LOADING', payload: true};
        const feeds = yield apiFetch({
            path: ENDPOINT,
            credentials: 'same-origin',
        });
        yield {type: 'SET_LIST', payload: feeds};
        yield {type: 'SET_LOADING', payload: false};
    },

    * saveFeed(feed) {
        const path = feed.id ? `${ENDPOINT}/${feed.id}` : ENDPOINT;
        const method = feed.id ? 'PUT' : 'POST';
        const saved = yield apiFetch({path, method, data: feed});
        yield {type: 'UPSERT_FEED', payload: saved};
    },

    * removeFeed(id) {
        yield apiFetch({path: `${ENDPOINT}/${id}`, method: 'DELETE'});
        yield {type: 'DELETE_FEED', payload: id};
    },

    * runFeed(id) {
        yield apiFetch({
            path: `/sc-autoparser/v1/feeds/${id}/run`,
            method: 'POST',
            credentials: 'same-origin',
        });

        yield actions.fetchFeeds();
    },

    openForm(feed = null) {
        return {type: 'OPEN_FORM', payload: feed};
    },
};

/* ---------- reducer ---------- */
function reducer(state = DEFAULT_STATE, {type, payload}) {
    switch (type) {
        case 'SET_LIST':
            return {...state, list: payload};
        case 'SET_LOADING':
            return {...state, isLoading: payload};
        case 'OPEN_FORM':
            return {...state, formFeed: payload};
        case 'UPSERT_FEED': {
            const exists = state.list.find((f) => f.id === payload.id);
            return {
                ...state,
                list: exists
                    ? state.list.map((f) => (f.id === payload.id ? payload : f))
                    : [...state.list, payload],
            };
        }
        case 'DELETE_FEED':
            return {...state, list: state.list.filter((f) => f.id !== payload)};
        default:
            return state;
    }
}

/* ---------- selectors ---------- */
const selectors = {
    getFeeds: (state) => state.list,
    isFetching: (state) => state.isLoading,
    getFormFeed: (state) => state.formFeed,
};

/* ---------- register ---------- */
registerStore(STORE_KEY, {
    reducer,
    actions,
    selectors,
    controls,
});

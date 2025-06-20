/**
 * Lightweight wrapper over wp.data and apiFetch for SC Autoparser.
 */
import apiFetch from '@wordpress/api-fetch';

const NAMESPACE = '/sc-autoparser/v1/feeds';

export const fetchFeeds = () => apiFetch({path: NAMESPACE});

export const createFeed = (data) => apiFetch({
    path: NAMESPACE,
    method: 'POST',
    data,
});

export const updateFeed = (id, data) => apiFetch({
    path: `${NAMESPACE}/${id}`,
    method: 'PUT',
    data,
});

export const deleteFeed = (id) => apiFetch({
    path: `${NAMESPACE}/${id}`,
    method: 'DELETE',
});

/**
 * FeedsScreen
 * -----------
 * Container for the "Ленти" admin page: switches between the list and
 * the add/edit form in place (no full page reload), keeping the URL in
 * sync via pushState so refresh/back-button/bookmarks still work.
 */
import {useState, useCallback} from '@wordpress/element';
import FeedList from './FeedList';
import FeedFormShared from './FeedFormShared';

function readViewFromLocation() {
    const qs = new URLSearchParams(window.location.search);
    const feedId = qs.get('feed');

    if (feedId) return {view: 'edit', feedId};
    if (qs.get('view') === 'add') return {view: 'add', feedId: null};

    return {view: 'list', feedId: null};
}

function pushUrl(params) {
    const url = new URL(window.location.href);
    url.searchParams.delete('view');
    url.searchParams.delete('feed');
    Object.entries(params).forEach(([key, value]) => {
        if (value) url.searchParams.set(key, value);
    });
    window.history.pushState({}, '', url);
}

export default function FeedsScreen() {
    const [state, setState] = useState(readViewFromLocation);

    const goToList = useCallback(() => {
        pushUrl({});
        setState({view: 'list', feedId: null});
    }, []);

    const goToAdd = useCallback(() => {
        pushUrl({view: 'add'});
        setState({view: 'add', feedId: null});
    }, []);

    const goToEdit = useCallback((feedId) => {
        pushUrl({feed: feedId});
        setState({view: 'edit', feedId});
    }, []);

    if (state.view === 'list') {
        return <FeedList onAdd={goToAdd} onEdit={goToEdit}/>;
    }

    return (
        <FeedFormShared
            feedId={state.view === 'edit' ? state.feedId : null}
            onBack={goToList}
            onSuccess={goToList}
        />
    );
}

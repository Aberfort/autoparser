/**
 * Entry bootstrap — mounts React components into WP pages
 */
import {createRoot} from 'react-dom/client';
import FeedList from './components/FeedList';
import FeedFormStandalone from './components/FeedFormStandalone';
import FeedFormEdit from './components/FeedFormEdit';
import Settings from './components/Settings';
import LogTable from './components/LogTable';
import CronTable from './components/CronTable';

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[id^="autoparser-root-"]')) {
        document.body.classList.add('autoparser-admin');
    }

    const qs = new URLSearchParams(window.location.search);
    const feedId = qs.get('feed');

    const mounts = {
        'autoparser-root-list': <FeedList/>,
        'autoparser-root-add': <FeedFormStandalone/>,
        'autoparser-root-edit': <FeedFormEdit feedId={feedId}/>,
        'autoparser-root-settings': <Settings/>,
        'autoparser-root-log': <LogTable/>,
        'autoparser-root-cron': <CronTable/>,
    };

    Object.entries(mounts).forEach(([id, node]) => {
        const el = document.getElementById(id);
        if (el) createRoot(el).render(node);
    });
});

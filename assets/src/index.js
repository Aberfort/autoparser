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
    if (document.querySelector('[id^="scap-root-"]')) {
        document.body.classList.add('scap-admin');
    }

    const qs = new URLSearchParams(window.location.search);
    const feedId = qs.get('feed');

    const mounts = {
        'scap-root-list': <FeedList/>,
        'scap-root-add': <FeedFormStandalone/>,
        'scap-root-edit': <FeedFormEdit feedId={feedId}/>,
        'scap-root-settings': <Settings/>,
        'scap-root-log': <LogTable/>,
        'scap-root-cron': <CronTable/>,
    };

    Object.entries(mounts).forEach(([id, node]) => {
        const el = document.getElementById(id);
        if (el) createRoot(el).render(node);
    });
});

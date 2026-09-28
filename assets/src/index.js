/**
 * Entry bootstrap — mounts React components into WP pages
 */
import {createRoot} from 'react-dom/client';
import FeedsScreen from './components/FeedsScreen';
import ActivityScreen from './components/ActivityScreen';
import Settings from './components/Settings';

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[id^="autoparser-root-"]')) {
        document.body.classList.add('autoparser-admin');
    }

    const mounts = {
        'autoparser-root-feeds': <FeedsScreen/>,
        'autoparser-root-activity': <ActivityScreen/>,
        'autoparser-root-settings': <Settings/>,
    };

    Object.entries(mounts).forEach(([id, node]) => {
        const el = document.getElementById(id);
        if (el) createRoot(el).render(node);
    });
});

/**
 * ActivityScreen
 * --------------
 * Container for the "Активність" admin page: tabs between the log and
 * the cron schedule, so checking on a feed's health doesn't require
 * jumping between two separate wp-admin pages.
 */
import {useState} from '@wordpress/element';
import {__} from '@wordpress/i18n';
import LogTable from './LogTable';
import CronTable from './CronTable';

function readTabFromLocation() {
    const qs = new URLSearchParams(window.location.search);

    return qs.get('tab') === 'cron' ? 'cron' : 'log';
}

export default function ActivityScreen() {
    const [tab, setTab] = useState(readTabFromLocation);

    const selectTab = (next) => {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', next);
        window.history.pushState({}, '', url);
        setTab(next);
    };

    return (
        <div className="space-y-6">
            <div className="autoparser-tabs" role="tablist">
                <button
                    type="button"
                    role="tab"
                    aria-selected={tab === 'log'}
                    className={`autoparser-tab ${tab === 'log' ? 'is-active' : ''}`}
                    onClick={() => selectTab('log')}
                >
                    {__('Журнал', 'autoparser')}
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected={tab === 'cron'}
                    className={`autoparser-tab ${tab === 'cron' ? 'is-active' : ''}`}
                    onClick={() => selectTab('cron')}
                >
                    {__('Розклад', 'autoparser')}
                </button>
            </div>

            {tab === 'log' ? <LogTable/> : <CronTable/>}
        </div>
    );
}

/**
 * CronTable — schedule view with neon badges & action buttons.
 */
import {useState, useEffect} from '@wordpress/element';
import {
    Spinner,
    Button,
    TextControl,
    Tooltip,
    Notice,
} from '@wordpress/components';
import {closeSmall} from '@wordpress/icons';
import {__} from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {motion, AnimatePresence} from 'framer-motion';

const ENDPOINT = '/sc-autoparser/v1/cron';

export default function CronTable() {
    /* state */
    const [rows, setRows] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState('');
    const [busyId, setBusyId] = useState(null);
    const [notice, setNotice] = useState(null);

    /* fetch rows */
    const refresh = () => {
        setLoading(true);
        apiFetch({path: ENDPOINT}).then(setRows).finally(() => setLoading(false));
    };
    useEffect(refresh, []);

    /* run / cancel helpers */
    const doAction = (id, type) => {
        setBusyId(id);
        apiFetch({path: `${ENDPOINT}/${id}/${type}`, method: 'POST'})
            .then(() => setNotice({
                status: 'success',
                text: type === 'run' ? __('Запуск розпочато', 'sc-autoparser')
                    : __('Дію скасовано', 'sc-autoparser'),
            }))
            .catch(() => setNotice({
                status: 'error',
                text: __('Помилка', 'sc-autoparser')
            }))
            .finally(() => {
                setBusyId(null);
                refresh();
            });
    };

    /* badge css helper */
    const badge = s => ({
        complete: 'status-badge status-badge--ok',
        failed: 'status-badge status-badge--error',
        running: 'status-badge status-badge--run',
        pending: 'status-badge status-badge--pending',
    }[s] || 'status-badge');

    if (loading) return <Spinner/>;

    const visible = rows.filter(r =>
        `${r.feed_id}`.includes(filter) || r.status.includes(filter)
    );

    return (
        <div className="space-y-10">
            <h2 className="text-3xl font-bold">{__('Розклад запусків', 'sc-autoparser')}</h2>

            {/* filters */}
            <div className="flex flex-wrap gap-4 items-start">
                <TextControl
                    placeholder={__('Фільтр за Feed або статусом…', 'sc-autoparser')}
                    value={filter}
                    onChange={setFilter}
                    className="w-full sm:w-1/3 scap-input"
                />
                <Button className="scap-btn scap-btn--secondary" onClick={refresh}>
                    {__('Оновити', 'sc-autoparser')}
                </Button>
            </div>

            {notice && (
                <Notice status={notice.status} isDismissible onRemove={() => setNotice(null)}>
                    {notice.text}
                </Notice>
            )}

            {/* table */}
            <div className="scap-card p-0 overflow-auto">
                <table className="scap-table">
                    <thead>
                    <tr>{['ID дії', 'Feed', 'Статус', 'Заплановано', 'Спроб', 'Дії'].map(h =>
                        <th key={h}>{h}</th>)}</tr>
                    </thead>
                    <AnimatePresence initial={false}>
                        <tbody>
                        {visible.length === 0 && (
                            <tr>
                                <td colSpan={6} className="py-10 text-center text-gray-500">
                                    {__('Немає запланованих дій', 'sc-autoparser')}
                                </td>
                            </tr>
                        )}

                        {visible.map(r => (
                            <motion.tr
                                key={r.id}
                                layout
                                initial={{opacity: 0, y: -6}}
                                animate={{opacity: 1, y: 0}}
                                exit={{opacity: 0, y: 6}}
                                transition={{duration: .15}}
                            >
                                <td>{r.id}</td>
                                <td>{r.feed_id ?? '—'}</td>
                                <td><span className={badge(r.status)}>
                                        {r.status === 'in-progress'
                                            ? __('Запускається', 'sc-autoparser')
                                            : r.status === 'pending'
                                                ? __('Заплановано', 'sc-autoparser')
                                                : r.status === 'complete'
                                                    ? 'OK'
                                                    : __('Помилка', 'sc-autoparser')}
                                    </span></td>
                                <td>{r.scheduled}</td>
                                <td>{r.attempts}</td>
                                <td className="flex gap-2">
                                    {/* RUN button — Dashicon controls-play */}
                                    <Tooltip text={__('Запустити', 'sc-autoparser')}>
                                        <Button
                                            icon="controls-play"                 /* ← контрастна іконка */
                                            className="scap-btn scap-btn--icon scap-btn--secondary"
                                            size="small"
                                            disabled={busyId === r.id}
                                            onClick={() => doAction(r.id, 'run')}
                                        />
                                    </Tooltip>

                                    {/* CANCEL button */}
                                    <Tooltip text={__('Скасувати', 'sc-autoparser')}>
                                        <Button
                                            icon={closeSmall}
                                            className="scap-btn scap-btn--icon scap-btn--danger"
                                            size="small"
                                            isDestructive
                                            disabled={busyId === r.id}
                                            onClick={() => doAction(r.id, 'cancel')}
                                        />
                                    </Tooltip>
                                </td>
                            </motion.tr>
                        ))}
                        </tbody>
                    </AnimatePresence>
                </table>
            </div>
        </div>
    );
}

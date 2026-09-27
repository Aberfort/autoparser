import {useState, useEffect} from '@wordpress/element';
import {
    Spinner,
    Button,
    TextControl,
    Notice,
    Tooltip,
} from '@wordpress/components';
import {trash, edit} from '@wordpress/icons';
import {__} from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {motion, AnimatePresence} from 'framer-motion';

const ENDPOINT = '/autoparser/v1/feeds';

export default function FeedList() {
    /* ───── state ───── */
    const [feeds, setFeeds] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState('');
    const [notice, setNotice] = useState(null);

    /* ───── fetch ───── */
    const load = () => {
        setLoading(true);
        apiFetch({path: ENDPOINT}).then(setFeeds).finally(() => setLoading(false));
    };
    useEffect(load, []);

    /* ───── delete ───── */
    const deleteFeed = async id => {
        if (!window.confirm(__('Ви впевнені, що хочете видалити цю ленту?', 'autoparser'))) return;
        try {
            await apiFetch({path: `${ENDPOINT}/${id}`, method: 'DELETE'});
            setNotice({
                status: 'success',
                message: __('Ленту видалено', 'autoparser')
            });
            load();
        } catch {
            setNotice({
                status: 'error',
                message: __('Помилка видалення', 'autoparser')
            });
        }
    };

    if (loading) return <Spinner/>;

    /* ───── filter ───── */
    const visible = feeds.filter(f =>
        f.name.toLowerCase().includes(filter.toLowerCase()) ||
        f.url.toLowerCase().includes(filter.toLowerCase())
    );

    return (
        <div className="space-y-10">

            {/* header */}
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h2 className="text-3xl font-bold">{__('Список лент', 'autoparser')}</h2>
                <Button
                    className="autoparser-btn"
                    onClick={() => window.location = 'admin.php?page=autoparser-add'}
                >{__('Додати ленту', 'autoparser')}</Button>
            </div>

            {/* notice */}
            {notice && (
                <Notice status={notice.status} isDismissible onRemove={() => setNotice(null)}>
                    {notice.message}
                </Notice>
            )}

            {/* search */}
            <TextControl
                placeholder={__('Пошук за назвою або URL…', 'autoparser')}
                value={filter}
                onChange={setFilter}
                className="max-w-sm autoparser-input"
            />

            {/* table */}
            <div className="autoparser-card p-0 overflow-auto">
                <table className="autoparser-table">
                    <thead>
                    <tr>{['ID', 'Назва', 'URL', 'Статус', 'Активна', 'Дії'].map(h =>
                        <th key={h}>{h}</th>)}</tr>
                    </thead>
                    <AnimatePresence initial={false}>
                        <tbody>
                        {visible.length === 0 && (
                            <tr>
                                <td colSpan={6} className="text-center py-10 text-gray-500">
                                    {__('Ленти відсутні', 'autoparser')}
                                </td>
                            </tr>
                        )}
                        {visible.map(f => (
                            <motion.tr
                                key={f.id}
                                layout
                                initial={{opacity: 0, y: -6}}
                                animate={{opacity: 1, y: 0}}
                                exit={{opacity: 0, y: 6}}
                                transition={{duration: .15}}
                            >
                                <td>{f.id}</td>
                                <td>{f.name}</td>
                                <td className="break-all">{f.url}</td>
                                <td>{f.status}</td>
                                <td>{f.active ? '✓' : '—'}</td>
                                <td className="flex gap-2">
                                    <Tooltip text={__('Редагувати', 'autoparser')}>
                                        <Button
                                            icon={edit}
                                            className="autoparser-btn autoparser-btn--icon autoparser-btn--secondary"
                                            onClick={() => window.location = `admin.php?page=autoparser-edit&feed=${f.id}`}
                                            size="small"
                                        />
                                    </Tooltip>
                                    <Tooltip text={__('Видалити', 'autoparser')}>
                                        <Button
                                            icon={trash}
                                            className="autoparser-btn autoparser-btn--icon autoparser-btn--danger"
                                            onClick={() => deleteFeed(f.id)}
                                            size="small"
                                            isDestructive
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

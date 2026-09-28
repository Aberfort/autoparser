import {useState, useEffect, useRef} from '@wordpress/element';
import {
    Spinner,
    Button,
    TextControl,
    Notice,
    Tooltip,
} from '@wordpress/components';
import {trash, edit} from '@wordpress/icons';
import {__, sprintf} from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {motion, AnimatePresence} from 'framer-motion';

const ENDPOINT = '/autoparser/v1/feeds';

export default function FeedList({onAdd, onEdit}) {
    /* ───── state ───── */
    const [feeds, setFeeds] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState('');
    const [notice, setNotice] = useState(null);
    const [importing, setImporting] = useState(false);
    const fileInputRef = useRef(null);

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

    /* ───── OPML import ───── */
    const handleImportFile = (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;

        setImporting(true);
        const reader = new FileReader();
        reader.onload = () => {
            apiFetch({
                path: `${ENDPOINT}/import-opml`,
                method: 'POST',
                data: {opml: reader.result},
            })
                .then(({imported, skipped}) => {
                    setNotice({
                        status: 'success',
                        message: sprintf(
                            __('Імпортовано %1$d, пропущено %2$d (вже існують). Нові стрічки додано неактивними — перевірте налаштування перед активацією.', 'autoparser'),
                            imported,
                            skipped
                        ),
                    });
                    load();
                })
                .catch(() => setNotice({
                    status: 'error',
                    message: __('Не вдалося імпортувати OPML-файл.', 'autoparser')
                }))
                .finally(() => setImporting(false));
        };
        reader.readAsText(file);
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
                <h2 className="text-3xl font-bold">{__('Ленти', 'autoparser')}</h2>
                <div className="flex gap-2">
                    {window.autoparserOpml?.exportUrl && (
                        <a
                            href={window.autoparserOpml.exportUrl}
                            className="autoparser-btn autoparser-btn--secondary"
                        >{__('Експорт OPML', 'autoparser')}</a>
                    )}
                    <Button
                        className="autoparser-btn autoparser-btn--secondary"
                        disabled={importing}
                        onClick={() => fileInputRef.current?.click()}
                    >{importing ? <Spinner/> : __('Імпорт OPML', 'autoparser')}</Button>
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept=".opml,.xml"
                        className="hidden"
                        onChange={handleImportFile}
                    />
                    <Button
                        className="autoparser-btn"
                        onClick={onAdd}
                    >{__('Додати ленту', 'autoparser')}</Button>
                </div>
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
                                <td colSpan={6} className="autoparser-empty">
                                    <div className="autoparser-empty__icon" aria-hidden="true">🗞️</div>
                                    <p className="autoparser-empty__title">
                                        {feeds.length === 0
                                            ? __('Ще немає жодної стрічки', 'autoparser')
                                            : __('Нічого не знайдено за цим фільтром', 'autoparser')}
                                    </p>
                                    {feeds.length === 0 && (
                                        <>
                                            <p className="autoparser-empty__hint">
                                                {__('Додайте перше джерело — RSS/sitemap URL або AI-прогноз без URL.', 'autoparser')}
                                            </p>
                                            <Button className="autoparser-btn" onClick={onAdd}>
                                                {__('Додати першу ленту', 'autoparser')}
                                            </Button>
                                        </>
                                    )}
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
                                            onClick={() => onEdit(f.id)}
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

/**
 * FeedFormShared
 * --------------
 *  • Створення / редагування фіда (RSS або AI-прогнози).
 */

import {useState, useEffect} from '@wordpress/element';
import {Button, Spinner, Notice, Tooltip} from '@wordpress/components';
import {__} from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {motion} from 'framer-motion';

const ENDPOINT = '/autoparser/v1/feeds';

/* режими мініатюри */
const THUMBNAIL_MODES = [
    {
        value: 'first',
        label: __('Використати перше зображення', 'autoparser')
    },
    {
        value: 'manual',
        label: __('Ручне завантаження (нічого не додається)', 'autoparser')
    },
];

/* бейдж «AI-прогноз» */
const AIPostBadge = () => (
    <span className="inline-block bg-purple-600 text-white text-xs font-semibold px-2 py-0.5 rounded">
		AI-прогноз
	</span>
);

export default function FeedFormShared({
                                           feedId = null,
                                           onSuccess = () => {
                                           },
                                           onBack = null,
                                       }) {

    /* ───────── state ───────── */
    const [form, setForm] = useState(null);
    const [types, setTypes] = useState([]);
    const [authors, setAuthors] = useState([]);
    const [cats, setCats] = useState([]);

    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [running, setRun] = useState(false);

    const [preview, setPreview] = useState(null);
    const [previewError, setPreviewError] = useState(null);
    const [previewLoading, setPreviewLoading] = useState(false);

    const update = (k, v) => setForm(prev => ({...prev, [k]: v}));

    const testSelector = () => {
        setPreviewLoading(true);
        setPreviewError(null);
        setPreview(null);
        apiFetch({
            path: '/autoparser/v1/feeds/preview',
            method: 'POST',
            data: {
                url: form.url,
                selector: form.selector,
                selector_end: form.selector_end,
            },
        })
            .then(setPreview)
            .catch(err => setPreviewError(err.message || __('Не вдалося отримати вміст за цим URL/селектором.', 'autoparser')))
            .finally(() => setPreviewLoading(false));
    };

    /* ───────── load options ───────── */
    useEffect(() => {
        /* CPT */
        apiFetch({
            path: '/wp/v2/types?context=edit',
            credentials: 'same-origin'
        })
            .then(data => Object.entries(data)
                .filter(([, info]) => info.show_in_rest && !info.slug?.startsWith('wp_') && info.slug !== 'attachment')
                .map(([slug, info]) => ({value: slug, label: info.name})))
            .then(arr => setTypes(arr.length ? arr : [{
                value: 'post',
                label: __('Пости', 'autoparser')
            }]))
            .catch(() => setTypes([{
                value: 'post',
                label: __('Пости', 'autoparser')
            }]));

        /* Authors */
        (async () => {
            try {
                const list = await apiFetch({
                    path: '/wp/v2/users?per_page=100&roles[]=author&context=edit',
                    credentials: 'same-origin'
                });
                if (list.length) {
                    setAuthors(list.map(u => ({
                        value: u.id,
                        label: u.name || u.slug
                    })));
                    return;
                }
            } catch {/* ignore */
            }
            const all = await apiFetch({
                path: '/wp/v2/users?per_page=100',
                credentials: 'same-origin'
            });
            setAuthors(all.map(u => ({value: u.id, label: u.name || u.slug})));
        })();

        /* Categories */
        apiFetch({
            path: '/wp/v2/categories?per_page=100',
            credentials: 'same-origin'
        })
            .then(list => setCats(list.map(t => ({
                value: t.id,
                label: t.name
            }))))
            .catch(() => setCats([]));
    }, []);

    /* ───────── init form ───────── */
    useEffect(() => {
        if (!types.length || !authors.length) return;

        /* new */
        if (!feedId) {
            setForm({
                /* базове */
                name: '', url: '', selector: 'article',
                post_type: types[0].value,
                author_id: authors[0].value,
                categories: [],
                status: 'draft', active: true,

                /* обмеження / час */
                limit: 5,
                post_time: '08:00',

                /* AI / SEO / thumb */
                prompt: '',
                list_prompt: form?.list_prompt ?? '',
                detail_prompt: form?.detail_prompt ?? '',
                thumbnail_mode: 'first',
                meta_title: '', meta_description: '',
                predict_only: false,
                ai_provider: 'gemini',
            });
            return;
        }

        /* existing */
        let alive = true;
        apiFetch({path: `${ENDPOINT}/${feedId}`, credentials: 'same-origin'})
            .then(d => alive && setForm({
                ...d,
                prompt: d.prompt ?? '',
                thumbnail_mode: d.thumbnail_mode ?? 'first',
                meta_title: d.meta_title ?? '',
                meta_description: d.meta_description ?? '',
                categories: d.categories ?? [],
                post_time: d.post_time ?? '08:00',
                predict_only: d.predict_only ?? false,
                ai_provider: d.ai_provider ?? 'gemini',
            }));
        return () => {
            alive = false;
        };
    }, [feedId, types, authors]);

    if (!form) return <Spinner/>;

    /* ───────── actions ───────── */
    const save = () => {
        setSaving(true);
        apiFetch({
            path: feedId ? `${ENDPOINT}/${feedId}` : ENDPOINT,
            method: feedId ? 'PUT' : 'POST',
            data: form,
            credentials: 'same-origin',
        })
            .then(() => {
                setSaved(true);
                setTimeout(onSuccess, 700);
            })
            .finally(() => setSaving(false));
    };

    const runNow = () => {
        if (!feedId) return;
        setRun(true);
        apiFetch({
            path: `${ENDPOINT}/${feedId}/run`,
            method: 'POST',
            credentials: 'same-origin'
        })
            .finally(() => setRun(false));
    };

    /* ───────── UI ───────── */
    return (
        <motion.div initial={{opacity: 0, y: 10}} animate={{
            opacity: 1,
            y: 0
        }} className="mx-auto">
            <div className="autoparser-card overflow-hidden">

                {/* header */}
                <header className="autoparser-card__head">
                    <div className="flex items-center gap-4">
                        {onBack && (
                            <Button className="autoparser-btn autoparser-btn--secondary" onClick={onBack}>
                                {__('← Назад до списку', 'autoparser')}
                            </Button>
                        )}
                        <h2 className="text-2xl font-semibold tracking-wide">
                            {feedId ? __('Редагування ленти', 'autoparser') : __('Нова лента', 'autoparser')}
                            {feedId && ` #${feedId}`}
                        </h2>
                    </div>

                    {feedId &&
                        <Tooltip text={__('Запустити зараз', 'autoparser')}>
                            <Button className="autoparser-btn" disabled={running} onClick={runNow}>
                                {running ?
                                    <Spinner/> : __('Запуск', 'autoparser')}
                            </Button>
                        </Tooltip>
                    }
                </header>

                {/* form */}
                <form className="p-10 autoparser-form" onSubmit={e => {
                    e.preventDefault();
                    save();
                }}>

                    {saved &&
                        <Notice status="success" isDismissible onRemove={() => setSaved(false)}>
                            {__('Збережено!', 'autoparser')}
                        </Notice>
                    }

                    {/* ======= LEFT ======= */}
                    <fieldset className="autoparser-fieldset">
                        <legend>{__('Основні', 'autoparser')}</legend>

                        <label className="autoparser-label">
                            AI Engine
                            <select
                                className="autoparser-select"
                                value={form.ai_provider}
                                onChange={e => update('ai_provider', e.target.value)}
                            >
                                <option value="gemini">Gemini</option>
                                <option value="openai">GPT-4 / 3.5</option>
                            </select>
                        </label>

                        {/* Name */}
                        <label className="autoparser-label">
                            {__('Назва', 'autoparser')}
                            <input className="autoparser-input" value={form.name} onChange={e => update('name', e.target.value)}/>
                        </label>

                        {/* URL */}
                        <label className="autoparser-label">
                            URL (RSS / API)
                            {!form.url && <AIPostBadge/>}
                            <input
                                className="autoparser-input"
                                type="url"
                                placeholder="https://..."
                                value={form.url}
                                onChange={e => update('url', e.target.value)}
                            />
                        </label>

                        <label className="autoparser-label">
                            Фільтрувати лише прогнози
                            <input
                                type="checkbox"
                                checked={form.predict_only}
                                onChange={e => update('predict_only', e.target.checked)}
                            />
                            <small className="autoparser-help text-gray-500">
                                {__('Потребує увімкненого модуля AI-прогнозів у Налаштуваннях.', 'autoparser')}
                            </small>
                        </label>

                        {/* CSS-selector (прихований, якщо AI-постинг) */}
                        {form.url &&
                            <label className="autoparser-label">
                                CSS-селектори (start/end)
                                <input className="autoparser-input" value={form.selector} onChange={e => update('selector', e.target.value)}/>
                                <input className="autoparser-input" value={form.selector_end} onChange={e => update('selector_end', e.target.value)}/>
                                <small className="autoparser-help text-gray-500">
                                    {__('Необов\'язково — якщо селектор порожній або не знайдений, плагін спробує типові варіанти (article, main, .entry-content…) автоматично.', 'autoparser')}
                                </small>

                                <Button
                                    type="button"
                                    className="autoparser-btn autoparser-btn--secondary"
                                    disabled={!form.url || previewLoading}
                                    onClick={testSelector}
                                >
                                    {previewLoading ? <Spinner/> : __('Перевірити селектор', 'autoparser')}
                                </Button>

                                {previewError && (
                                    <Notice status="error" isDismissible onRemove={() => setPreviewError(null)}>
                                        {previewError}
                                    </Notice>
                                )}

                                {preview && (
                                    <div className="autoparser-card p-4 space-y-2">
                                        <p><strong>{__('Заголовок', 'autoparser')}:</strong> {preview.title || '—'}</p>
                                        <p><strong>{__('Довжина контенту', 'autoparser')}:</strong> {preview.content_length} {__('символів', 'autoparser')}</p>
                                        <p className="autoparser-help text-gray-500 max-h-40 overflow-auto">
                                            {preview.content_excerpt}
                                        </p>
                                    </div>
                                )}
                            </label>
                        }

                        {/* CPT */}
                        <label className="autoparser-label">
                            {__('Тип запису', 'autoparser')}
                            <select className="autoparser-select" value={form.post_type} onChange={e => update('post_type', e.target.value)}>
                                {types.map(o =>
                                    <option key={o.value} value={o.value}>{o.label}</option>)}
                            </select>
                        </label>

                        {/* Author */}
                        <label className="autoparser-label">
                            {__('Автор', 'autoparser')}
                            <select className="autoparser-select" value={form.author_id} onChange={e => update('author_id', Number(e.target.value))}>
                                {authors.map(a =>
                                    <option key={a.value} value={a.value}>{a.label}</option>)}
                            </select>
                        </label>

                        {/* Categories */}
                        <label className="autoparser-label">
                            {__('Категорії', 'autoparser')}
                            <select
                                multiple
                                size="6"
                                className="autoparser-select h-36"
                                value={form.categories.map(String)}
                                onChange={e => update(
                                    'categories',
                                    Array.from(e.target.selectedOptions).map(o => Number(o.value))
                                )}
                            >
                                {cats.map(c =>
                                    <option key={c.value} value={c.value}>{c.label}</option>)}
                            </select>
                        </label>

                        {/* Limit */}
                        <label className="autoparser-label">
                            {__('Ліміт постів (RSS) / прогнозів', 'autoparser')}
                            <input
                                className="autoparser-input"
                                type="number"
                                min="1"
                                value={form.limit}
                                onChange={e => update('limit', Number(e.target.value) || 1)}
                            />
                        </label>

                        {/* Active flag */}
                        <div className="flex items-center gap-4">
                            <span className="autoparser-label mb-0">{__('Активна', 'autoparser')}</span>
                            <label className="autoparser-toggle">
                                <input type="checkbox" checked={form.active} onChange={e => update('active', e.target.checked)}/>
                                <span></span>
                            </label>
                        </div>

                        {/* Post time */}
                        <label className="autoparser-label">
                            {__('Час автопостингу', 'autoparser')}
                            <input
                                className="autoparser-input"
                                type="time"
                                value={form.post_time}
                                onChange={e => update('post_time', e.target.value)}
                            />
                        </label>
                    </fieldset>

                    {/* ======= RIGHT ======= */}
                    <fieldset className="autoparser-fieldset">
                        <legend>{__('Додатково', 'autoparser')}</legend>

                        {!form.url && (
                            <>
                                <label className="autoparser-label">
                                    {__('Детальний прогноз', 'autoparser')}
                                    <textarea
                                        className="autoparser-textarea"
                                        rows="10"
                                        value={form.detail_prompt}
                                        onChange={e => update('detail_prompt', e.target.value)}
                                        placeholder="Напиши профессиональный прогноз на матч..."
                                    />
                                    <small className="autoparser-help text-gray-500">
                                        {__('Доступні змінні', 'autoparser')}:{' '}
                                        <code>{'{{team1}}'}</code>,
                                        <code>{'{{team2}}'}</code>,
                                        <code>{'{{time}}'}</code>,
                                        <code>{'{{league}}'}</code>,
                                        <code>{'{{date}}'}</code>
                                    </small>
                                </label>
                            </>
                        )}

                        {form.url && (
                            <>
                                <label className="autoparser-label">
                                    {__('Локальний шаблон промпту', 'autoparser')}
                                    <textarea
                                        className="autoparser-textarea"
                                        rows="8"
                                        value={form.prompt}
                                        onChange={e => update('prompt', e.target.value)}
                                    />
                                    <small className="autoparser-help text-gray-500">
                                        {__('Рядок до “---” використовується для заголовка, після “---” — для контенту.', 'autoparser')}
                                    </small>
                                </label>
                            </>
                        )}
                        <label className="autoparser-label">
                            {__('Режим мініатюри', 'autoparser')}
                            <select className="autoparser-select" value={form.thumbnail_mode} onChange={e => update('thumbnail_mode', e.target.value)}>
                                {THUMBNAIL_MODES.map(o =>
                                    <option key={o.value} value={o.value}>{o.label}</option>)}
                            </select>
                        </label>

                        {/* Meta */}
                        <label className="autoparser-label">
                            Meta Title
                            <input className="autoparser-input" value={form.meta_title} onChange={e => update('meta_title', e.target.value)}/>
                        </label>

                        <label className="autoparser-label">
                            Meta Description
                            <textarea
                                className="autoparser-textarea"
                                rows="4"
                                value={form.meta_description}
                                onChange={e => update('meta_description', e.target.value)}
                            />
                        </label>
                        {!form.url && (
                            <>
                                <small className="autoparser-help text-gray-500">
                                    {__(
                                        'Доступні змінні: {{team1}}, {{team2}}, {{date}}, {{sitename}}, {{title}}, {{excerpt}}',
                                        'autoparser'
                                    )}
                                </small>
                            </>
                        )}
                        {form.url && (
                            <>
                                <small className="autoparser-help text-gray-500">
                                    {__(
                                        'Доступні змінні: {{title}}, {{excerpt}}, {{sitename}}, {{date}}, {{team1}}, {{team2}} ',
                                        'autoparser'
                                    )}
                                </small>
                            </>
                        )}
                    </fieldset>

                    {/* save */}
                    <div className="col-span-full flex justify-end">
                        <Button type="submit" className="autoparser-btn" disabled={saving}>
                            {saving ?
                                <Spinner/> : __('Зберегти', 'autoparser')}
                        </Button>
                    </div>
                </form>
            </div>
        </motion.div>
    );
}

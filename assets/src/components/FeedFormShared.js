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

const ENDPOINT = '/sc-autoparser/v1/feeds';

/* режими мініатюри */
const THUMBNAIL_MODES = [
    {
        value: 'first',
        label: __('Використати перше зображення', 'sc-autoparser')
    },
    {
        value: 'manual',
        label: __('Ручне завантаження (нічого не додається)', 'sc-autoparser')
    },
];

/* бейдж «AI-прогноз» */
const AIPostBadge = () => (
    <span className="inline-block bg-purple-600 text-white text-xs font-semibold px-2 py-0.5 rounded">
		AI-прогноз
	</span>
);

export default function FeedFormShared({
                                           feedId = null, onSuccess = () => {
    }
                                       }) {

    /* ───────── state ───────── */
    const [form, setForm] = useState(null);
    const [types, setTypes] = useState([]);
    const [authors, setAuthors] = useState([]);
    const [cats, setCats] = useState([]);

    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [running, setRun] = useState(false);

    const update = (k, v) => setForm(prev => ({...prev, [k]: v}));

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
                label: __('Пости', 'sc-autoparser')
            }]))
            .catch(() => setTypes([{
                value: 'post',
                label: __('Пости', 'sc-autoparser')
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
                onSuccess();
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
            <div className="scap-card overflow-hidden">

                {/* header */}
                <header className="scap-card__head">
                    <h2 className="text-2xl font-semibold tracking-wide">
                        {feedId ? __('Редагування ленти', 'sc-autoparser') : __('Нова лента', 'sc-autoparser')}
                        {feedId && ` #${feedId}`}
                    </h2>

                    {feedId &&
                        <Tooltip text={__('Запустити зараз', 'sc-autoparser')}>
                            <Button className="scap-btn" disabled={running} onClick={runNow}>
                                {running ?
                                    <Spinner/> : __('Запуск', 'sc-autoparser')}
                            </Button>
                        </Tooltip>
                    }
                </header>

                {/* form */}
                <form className="p-10 scap-form" onSubmit={e => {
                    e.preventDefault();
                    save();
                }}>

                    {saved &&
                        <Notice status="success" isDismissible onRemove={() => setSaved(false)}>
                            {__('Збережено!', 'sc-autoparser')}
                        </Notice>
                    }

                    {/* ======= LEFT ======= */}
                    <fieldset className="scap-fieldset">
                        <legend>{__('Основні', 'sc-autoparser')}</legend>

                        <label className="scap-label">
                            AI Engine
                            <select
                                className="scap-select"
                                value={form.ai_provider}
                                onChange={e => update('ai_provider', e.target.value)}
                            >
                                <option value="gemini">Gemini</option>
                                <option value="openai">GPT-4 / 3.5</option>
                            </select>
                        </label>

                        {/* Name */}
                        <label className="scap-label">
                            {__('Назва', 'sc-autoparser')}
                            <input className="scap-input" value={form.name} onChange={e => update('name', e.target.value)}/>
                        </label>

                        {/* URL */}
                        <label className="scap-label">
                            URL (RSS / API)
                            {!form.url && <AIPostBadge/>}
                            <input
                                className="scap-input"
                                type="url"
                                placeholder="https://..."
                                value={form.url}
                                onChange={e => update('url', e.target.value)}
                            />
                        </label>

                        <label className="scap-label">
                            Фільтрувати лише прогнози
                            <input
                                type="checkbox"
                                checked={form.predict_only}
                                onChange={e => update('predict_only', e.target.checked)}
                            />
                        </label>

                        {/* CSS-selector (прихований, якщо AI-постинг) */}
                        {form.url &&
                            <label className="scap-label">
                                CSS-селектори (start/end)
                                <input className="scap-input" value={form.selector} onChange={e => update('selector', e.target.value)}/>
                                <input className="scap-input" value={form.selector_end} onChange={e => update('selector_end', e.target.value)}/>
                            </label>
                        }

                        {/* CPT */}
                        <label className="scap-label">
                            {__('Тип запису', 'sc-autoparser')}
                            <select className="scap-select" value={form.post_type} onChange={e => update('post_type', e.target.value)}>
                                {types.map(o =>
                                    <option key={o.value} value={o.value}>{o.label}</option>)}
                            </select>
                        </label>

                        {/* Author */}
                        <label className="scap-label">
                            {__('Автор', 'sc-autoparser')}
                            <select className="scap-select" value={form.author_id} onChange={e => update('author_id', Number(e.target.value))}>
                                {authors.map(a =>
                                    <option key={a.value} value={a.value}>{a.label}</option>)}
                            </select>
                        </label>

                        {/* Categories */}
                        <label className="scap-label">
                            {__('Категорії', 'sc-autoparser')}
                            <select
                                multiple
                                size="6"
                                className="scap-select h-36"
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
                        <label className="scap-label">
                            {__('Ліміт постів (RSS) / прогнозів', 'sc-autoparser')}
                            <input
                                className="scap-input"
                                type="number"
                                min="1"
                                value={form.limit}
                                onChange={e => update('limit', Number(e.target.value) || 1)}
                            />
                        </label>

                        {/* Active flag */}
                        <div className="flex items-center gap-4">
                            <span className="scap-label mb-0">{__('Активна', 'sc-autoparser')}</span>
                            <label className="scap-toggle">
                                <input type="checkbox" checked={form.active} onChange={e => update('active', e.target.checked)}/>
                                <span></span>
                            </label>
                        </div>

                        {/* Post time */}
                        <label className="scap-label">
                            {__('Час автопостингу', 'sc-autoparser')}
                            <input
                                className="scap-input"
                                type="time"
                                value={form.post_time}
                                onChange={e => update('post_time', e.target.value)}
                            />
                        </label>
                    </fieldset>

                    {/* ======= RIGHT ======= */}
                    <fieldset className="scap-fieldset">
                        <legend>{__('Додатково', 'sc-autoparser')}</legend>

                        {!form.url && (
                            <>
                                <label className="scap-label">
                                    {__('Детальний прогноз', 'sc-autoparser')}
                                    <textarea
                                        className="scap-textarea"
                                        rows="10"
                                        value={form.detail_prompt}
                                        onChange={e => update('detail_prompt', e.target.value)}
                                        placeholder="Напиши профессиональный прогноз на матч..."
                                    />
                                    <small className="scap-help text-gray-500">
                                        {__('Доступні змінні', 'sc-autoparser')}:{' '}
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
                                <label className="scap-label">
                                    {__('Локальний шаблон промпту', 'sc-autoparser')}
                                    <textarea
                                        className="scap-textarea"
                                        rows="8"
                                        value={form.prompt}
                                        onChange={e => update('prompt', e.target.value)}
                                    />
                                    <small className="scap-help text-gray-500">
                                        {__('Рядок до “---” використовується для заголовка, після “---” — для контенту.', 'sc-autoparser')}
                                    </small>
                                </label>
                            </>
                        )}
                        <label className="scap-label">
                            {__('Режим мініатюри', 'sc-autoparser')}
                            <select className="scap-select" value={form.thumbnail_mode} onChange={e => update('thumbnail_mode', e.target.value)}>
                                {THUMBNAIL_MODES.map(o =>
                                    <option key={o.value} value={o.value}>{o.label}</option>)}
                            </select>
                        </label>

                        {/* Meta */}
                        <label className="scap-label">
                            Meta Title
                            <input className="scap-input" value={form.meta_title} onChange={e => update('meta_title', e.target.value)}/>
                        </label>

                        <label className="scap-label">
                            Meta Description
                            <textarea
                                className="scap-textarea"
                                rows="4"
                                value={form.meta_description}
                                onChange={e => update('meta_description', e.target.value)}
                            />
                        </label>
                        {!form.url && (
                            <>
                                <small className="scap-help text-gray-500">
                                    {__(
                                        'Доступні змінні: {{team1}}, {{team2}}, {{date}}, {{sitename}}, {{title}}, {{excerpt}}',
                                        'sc-autoparser'
                                    )}
                                </small>
                            </>
                        )}
                        {form.url && (
                            <>
                                <small className="scap-help text-gray-500">
                                    {__(
                                        'Доступні змінні: {{title}}, {{excerpt}}, {{sitename}}, {{date}}, {{team1}}, {{team2}} ',
                                        'sc-autoparser'
                                    )}
                                </small>
                            </>
                        )}
                    </fieldset>

                    {/* save */}
                    <div className="col-span-full flex justify-end">
                        <Button type="submit" className="scap-btn" disabled={saving}>
                            {saving ?
                                <Spinner/> : __('Зберегти', 'sc-autoparser')}
                        </Button>
                    </div>
                </form>
            </div>
        </motion.div>
    );
}

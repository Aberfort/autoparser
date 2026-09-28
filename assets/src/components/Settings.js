import {useState, useEffect} from '@wordpress/element';
import {
    TextControl,
    TextareaControl,
    ToggleControl,
    Button,
    Spinner,
    Notice,
} from '@wordpress/components';
import {__} from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function Settings() {
    /* ───────── state ───────── */
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    const [apiKeyGemini, setKeyGemini] = useState('');
    const [apiKeyOpenAI, setKeyOpenAI] = useState('');
    const [openaiModel, setOpenaiModel] = useState('');
    const [prompt, setPrompt] = useState('');

    const [fixturesKey, setFixturesKey] = useState('');
    const [enableFallbackProxy, setEnableFallbackProxy] = useState(false);
    const [predictionsEnabled, setPredictionsEnabled] = useState(false);

    const [notice, setNotice] = useState(null);
    const [usage, setUsage] = useState(null);

    /* ───────── fetch on mount ───────── */
    useEffect(() => {
        apiFetch({path: '/autoparser/v1/settings'})
            .then(d => {
                setKeyGemini(d.gemini_api_key || '');
                setKeyOpenAI(d.openai_api_key || '');
                setOpenaiModel(d.openai_model || '');
                setPrompt(d.global_prompt || '');
                setFixturesKey(d.fixtures_api_key || '');
                setEnableFallbackProxy(!!d.enable_fallback_proxy);
                setPredictionsEnabled(!!d.predictions_enabled);
            })
            .finally(() => setLoading(false));

        apiFetch({path: '/autoparser/v1/settings/usage'})
            .then(setUsage)
            .catch(() => setUsage({}));
    }, []);

    /* ───────── save ───────── */
    const save = () => {
        setSaving(true);
        apiFetch({
            path: '/autoparser/v1/settings',
            method: 'POST',
            data: {
                gemini_api_key: apiKeyGemini,
                openai_api_key: apiKeyOpenAI,
                openai_model: openaiModel,
                global_prompt: prompt,
                fixtures_api_key: fixturesKey,
                enable_fallback_proxy: enableFallbackProxy,
                predictions_enabled: predictionsEnabled,
            },
        })
            .then(() => setNotice({
                status: 'success',
                text: __('Збережено ✅', 'autoparser')
            }))
            .catch(() => setNotice({
                status: 'error',
                text: __('Помилка', 'autoparser')
            }))
            .finally(() => setSaving(false));
    };

    if (loading) return <Spinner/>;

    /* ───────── UI ───────── */
    return (
        <div className="mx-auto space-y-10">
            <div className="autoparser-card">
                <div className="autoparser-card__head">
                    <h2 className="text-xl font-semibold">{__('Налаштування API', 'autoparser')}</h2>
                </div>

                <div className="p-10 space-y-8">

                    {/*<label className="autoparser-label">*/}
                    {/*    RapidAPI Key (API-Football)*/}
                    {/*    <TextControl*/}
                    {/*        type="password"*/}
                    {/*        value={fixturesKey}*/}
                    {/*        onChange={setFixturesKey}*/}
                    {/*        className="autoparser-input"*/}
                    {/*    />*/}
                    {/*</label>*/}

                    <label className="autoparser-label">
                        Gemini API Key
                        <TextControl
                            type="password"
                            value={apiKeyGemini}
                            onChange={setKeyGemini}
                            className="autoparser-input"
                        />
                    </label>

                    <label className="autoparser-label">
                        OpenAI (GPT) API Key
                        <TextControl
                            type="password"
                            value={apiKeyOpenAI}
                            onChange={setKeyOpenAI}
                            className="autoparser-input"
                        />
                    </label>

                    <label className="autoparser-label">
                        OpenAI Model
                        <TextControl
                            value={openaiModel}
                            onChange={setOpenaiModel}
                            className="autoparser-input"
                        />
                    </label>

                    <label className="autoparser-label">
                        {__('Глобальний шаблон промпту', 'autoparser')}
                        <TextareaControl
                            rows={8}
                            value={prompt}
                            onChange={setPrompt}
                            className="autoparser-textarea"
                        />
                    </label>

                    <ToggleControl
                        label={__('Резервний проксі (r.jina.ai) при 403/503 від джерела', 'autoparser')}
                        help={__('Якщо джерело блокує прямі запити, URL джерела буде передано стороньому сервісу r.jina.ai для отримання вмісту. Вимкнено за замовчуванням.', 'autoparser')}
                        checked={enableFallbackProxy}
                        onChange={setEnableFallbackProxy}
                    />

                    <ToggleControl
                        label={__('Модуль AI-прогнозів (футбол)', 'autoparser')}
                        help={__('Окрема, вузькоспеціалізована фіча для спортивних прогнозів (API-Football + ACF-шаблон ставок). Не потрібна для звичайного парсингу статей. Вимкнено за замовчуванням.', 'autoparser')}
                        checked={predictionsEnabled}
                        onChange={setPredictionsEnabled}
                    />

                    {notice &&
                        <Notice status={notice.status} isDismissible onRemove={() => setNotice(null)}>
                            {notice.text}
                        </Notice>}

                    <div className="flex justify-end">
                        <Button className="autoparser-btn" disabled={saving} onClick={save}>
                            {saving ? __('Зберігаємо…', 'autoparser') : __('Зберегти', 'autoparser')}
                        </Button>
                    </div>
                </div>
            </div>

            {usage && Object.keys(usage).length > 0 && (
                <div className="autoparser-card">
                    <div className="autoparser-card__head">
                        <h2 className="text-xl font-semibold">{__('Використання AI', 'autoparser')}</h2>
                    </div>
                    <div className="p-10">
                        <p className="autoparser-help text-gray-500 mb-4">
                            {__('Кількість викликів AI-провайдера (не вартість — тарифи змінюються надто часто, щоб їх надійно порахувати тут).', 'autoparser')}
                        </p>
                        <table className="autoparser-table">
                            <thead>
                            <tr>
                                <th>{__('Провайдер', 'autoparser')}</th>
                                <th>{__('Усього викликів', 'autoparser')}</th>
                            </tr>
                            </thead>
                            <tbody>
                            {Object.entries(usage).map(([provider, stats]) => (
                                <tr key={provider}>
                                    <td>{provider}</td>
                                    <td>{stats.total ?? 0}</td>
                                </tr>
                            ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </div>
    );
}

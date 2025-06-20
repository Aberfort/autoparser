import {useState, useEffect} from '@wordpress/element';
import {
    TextControl,
    TextareaControl,
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

    const [notice, setNotice] = useState(null);

    /* ───────── fetch on mount ───────── */
    useEffect(() => {
        apiFetch({path: '/sc-autoparser/v1/settings'})
            .then(d => {
                setKeyGemini(d.gemini_api_key || '');
                setKeyOpenAI(d.openai_api_key || '');
                setOpenaiModel(d.openai_model || '');
                setPrompt(d.global_prompt || '');
                setFixturesKey(d.fixtures_api_key || '');
            })
            .finally(() => setLoading(false));
    }, []);

    /* ───────── save ───────── */
    const save = () => {
        setSaving(true);
        apiFetch({
            path: '/sc-autoparser/v1/settings',
            method: 'POST',
            data: {
                gemini_api_key: apiKeyGemini,
                openai_api_key: apiKeyOpenAI,
                openai_model: openaiModel,
                global_prompt: prompt,
                fixtures_api_key: fixturesKey
            },
        })
            .then(() => setNotice({
                status: 'success',
                text: __('Збережено ✅', 'sc-autoparser')
            }))
            .catch(() => setNotice({
                status: 'error',
                text: __('Помилка', 'sc-autoparser')
            }))
            .finally(() => setSaving(false));
    };

    if (loading) return <Spinner/>;

    /* ───────── UI ───────── */
    return (
        <div className="mx-auto space-y-10">
            <div className="scap-card">
                <div className="scap-card__head">
                    <h2 className="text-xl font-semibold">{__('Налаштування API', 'sc-autoparser')}</h2>
                </div>

                <div className="p-10 space-y-8">

                    {/*<label className="scap-label">*/}
                    {/*    RapidAPI Key (API-Football)*/}
                    {/*    <TextControl*/}
                    {/*        type="password"*/}
                    {/*        value={fixturesKey}*/}
                    {/*        onChange={setFixturesKey}*/}
                    {/*        className="scap-input"*/}
                    {/*    />*/}
                    {/*</label>*/}

                    <label className="scap-label">
                        Gemini API Key
                        <TextControl
                            type="password"
                            value={apiKeyGemini}
                            onChange={setKeyGemini}
                            className="scap-input"
                        />
                    </label>

                    <label className="scap-label">
                        OpenAI (GPT) API Key
                        <TextControl
                            type="password"
                            value={apiKeyOpenAI}
                            onChange={setKeyOpenAI}
                            className="scap-input"
                        />
                    </label>

                    <label className="scap-label">
                        OpenAI Model
                        <TextControl
                            value={openaiModel}
                            onChange={setOpenaiModel}
                            className="scap-input"
                        />
                    </label>

                    <label className="scap-label">
                        {__('Глобальний шаблон промпту', 'sc-autoparser')}
                        <TextareaControl
                            rows={8}
                            value={prompt}
                            onChange={setPrompt}
                            className="scap-textarea"
                        />
                    </label>

                    {notice &&
                        <Notice status={notice.status} isDismissible onRemove={() => setNotice(null)}>
                            {notice.text}
                        </Notice>}

                    <div className="flex justify-end">
                        <Button className="scap-btn" disabled={saving} onClick={save}>
                            {saving ? __('Зберігаємо…', 'sc-autoparser') : __('Зберегти', 'sc-autoparser')}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}

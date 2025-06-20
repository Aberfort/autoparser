/**
 * LogTable – glass card with level badges, zebra rows & auto-scroll.
 */
import {useState, useEffect, useRef, useMemo} from '@wordpress/element';
import {
    Spinner,
    SelectControl,
    Button,
    Tooltip,
} from '@wordpress/components';
import {__} from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {motion, AnimatePresence} from 'framer-motion';

export default function LogTable() {
    /* ───────── dates ───────── */
    const today = useMemo(() =>
        new Date().toISOString().slice(0, 10), []);
    const yesterday = useMemo(() =>
        new Date(Date.now() - 864e5).toISOString().slice(0, 10), []);

    /* state */
    const [logs, setLogs] = useState([]);
    const [date, setDate] = useState(today);
    const [level, setLevel] = useState('all');
    const [loading, setLoading] = useState(true);
    const endRef = useRef(null);

    /* fetch logs */
    const fetchLogs = () => {
        setLoading(true);
        apiFetch({path: `/sc-autoparser/v1/logs?date=${date}&limit=500`})
            .then(arr => setLogs(
                arr.filter(([, lvl]) => level === 'all' || lvl === level)
            ))
            .finally(() => setLoading(false));
    };
    useEffect(fetchLogs, [date, level]);

    /* auto-scroll to newest */
    useEffect(() => {
        endRef.current?.scrollIntoView({behavior: 'smooth'});
    }, [logs]);

    if (loading) return <Spinner/>;

    return (
        <div className="space-y-10">
            <h2 className="text-3xl font-bold">{__('Журнал', 'sc-autoparser')} {date}</h2>

            {/* filters */}
            <div className="flex flex-wrap gap-4">
                <SelectControl
                    label={__('День', 'sc-autoparser')}
                    value={date}
                    options={[
                        {label: today, value: today},
                        {label: yesterday, value: yesterday},
                    ]}
                    onChange={setDate}
                    className="w-44 scap-select"
                />
                <Button className="scap-btn scap-btn--secondary" onClick={fetchLogs}>
                    {__('Оновити', 'sc-autoparser')}
                </Button>
            </div>

            {/* card */}
            <div className="scap-card p-0 overflow-auto max-h-[70vh]">
                <table className="scap-table scap-table--log">
                    <thead>
                    <tr>
                        <th>{__('Повідомлення', 'sc-autoparser')}</th>
                    </tr>
                    </thead>

                    <AnimatePresence initial={false}>
                        <tbody>
                        {logs.map(([time, lvl, msg], i) => (
                            <motion.tr
                                key={i}
                                layout
                                initial={{opacity: 0, y: -4}}
                                animate={{opacity: 1, y: 0}}
                                exit={{opacity: 0, y: 4}}
                                transition={{duration: .15}}
                            >
                                <td className="font-mono whitespace-nowrap">{time}</td>
                            </motion.tr>
                        ))}
                        {/* scroll target */}
                        <tr ref={endRef}>
                            <td/>
                        </tr>
                        </tbody>
                    </AnimatePresence>
                </table>
            </div>
        </div>
    );
}

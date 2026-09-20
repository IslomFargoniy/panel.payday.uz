import { SearchData, WorkerPaginate } from '@/types';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

type WorkerTableProps = {
    worker: WorkerPaginate;
    branch_id?: number;
    searchData: SearchData;
};

const WorkerMonthlyAttendanceTable = ({ worker, searchData }: WorkerTableProps) => {
    const { t } = useTranslation();

    // Converts HH:mm:ss string to minutes
    const timeToMinutes = (time?: string | null): number => {
        if (!time) return 0;
        const [h, m, s] = time.split(':').map(Number);
        return h * 60 + m + s / 60;
    };

    // Converts minutes to "HH:mm" format
    const minutesToHHMM = (minutes: number): string => {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return `${hours}:${String(mins).padStart(2, '0')}`;
    };

    // Calculates worked time based on work_time, end_time, and number of days
    const calculateWorkedTime = (startTime?: string | null, endTime?: string | null, days: number = 1): string => {
        if (timeToMinutes(endTime) < timeToMinutes(startTime)) {
            return '0:00';
        }

        let diff = timeToMinutes(endTime) - timeToMinutes(startTime);

        // Overnight shift support
        if (diff < 0) diff += 24 * 60;

        diff *= days;

        return minutesToHHMM(Math.round(diff));
    };

    return (
        <div className="space-y-3">
            {/* Table Card */}
            <div className="w-full min-w-0 max-w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="w-full overflow-x-auto">
                    <table className="w-full min-w-[1050px] text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-3.5 py-3 text-center w-10 font-mono">{t('n', '№')}</th>
                                <th className="px-3.5 py-3 min-w-[140px]">{t('name', 'F.I.SH')}</th>
                                <th className="px-3.5 py-3 min-w-[120px]">{t('firm', 'Filial')}</th>
                                <th className="px-3.5 py-3">{t('phone', 'Telefon')}</th>
                                <th className="px-3.5 py-3 text-rose-600 dark:text-rose-400">{t('late_hours', 'Kechikish (soat)')}</th>
                                <th className="px-3.5 py-3">{t('break_hours', 'Tanaffus')}</th>
                                <th className="px-3.5 py-3">{t('worked_hours', 'Ishlagan soat')}</th>
                                <th className="px-3.5 py-3 text-indigo-600 dark:text-indigo-400">{t('common_worked_hours', 'Sof soat')}</th>
                                <th className="px-3.5 py-3 text-rose-600 dark:text-rose-400">{t('late_days', 'Kechikkan kun')}</th>
                                <th className="px-3.5 py-3 text-emerald-600 dark:text-emerald-400">{t('worked_days', 'Ishlagan kun')}</th>
                                <th className="px-3.5 py-3">{t('work_days', 'Ish kuni')}</th>
                                <th className="px-3.5 py-3">{t('work_hours', 'Grafik soati')}</th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {(!worker.data || worker.data.length === 0) ? (
                                <tr>
                                    <td colSpan={12} className="px-4 py-8 text-center text-slate-400 dark:text-slate-500">
                                        {t('no_data', 'Ma’lumot mavjud emas')}
                                    </td>
                                </tr>
                            ) : (
                                worker.data.map((item, rowIndex) => {
                                    const globalIndex = (worker.current_page - 1) * worker.per_page + rowIndex + 1;

                                    return (
                                        <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-3.5 py-2.5 text-center font-mono text-slate-400 dark:text-slate-500">{globalIndex}</td>
                                            <td className="px-3.5 py-2.5 font-medium text-slate-900 dark:text-slate-100">
                                                <Link href={`/worker/${item.id}`} className="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                    {item.name}
                                                </Link>
                                            </td>
                                            <td className="px-3.5 py-2.5 text-slate-500 truncate max-w-[140px]">
                                                {item.branch?.firm?.name} ({item.branch?.name})
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-500">{item.phone || '—'}</td>
                                            <td className="px-3.5 py-2.5 font-mono font-semibold text-rose-600 dark:text-rose-400">
                                                {~~(item.late_minutes! / 60)}:{String(item.late_minutes! % 60).padStart(2, '0')}
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-600 dark:text-slate-300">
                                                {~~(item.break_minutes! / 60)}:{String(item.break_minutes! % 60).padStart(2, '0')}
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-700 dark:text-slate-200">
                                                {~~(item.worked_minutes! / 60)}:{String(item.worked_minutes! % 60).padStart(2, '0')}
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                                                {~~((item.worked_minutes! - item.break_minutes!) / 60)}:{String((item.worked_minutes! - item.break_minutes!) % 60).padStart(2, '0')}
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono font-semibold text-rose-600 dark:text-rose-400">{item.late_days}</td>
                                            <td className="px-3.5 py-2.5 font-mono font-semibold text-emerald-600 dark:text-emerald-400">{item.worked_days}</td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-600 dark:text-slate-300">{item.work_days}</td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-600 dark:text-slate-300">
                                                {calculateWorkedTime(item.work_time, item.end_time, item.work_days ?? 0)}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            <div className="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400 px-1 pt-1">
                <div>
                    {t('showing', {
                        from: worker.from || 0,
                        to: worker.to || 0,
                        total: worker.total || 0,
                    })}
                </div>
                <div className="flex items-center gap-1">
                    {worker.links.map((link, index) => (
                        <Link
                            key={index}
                            href={`${link.url ?? '?'}&search=${searchData.search || ''}&per_page=${searchData.per_page || 15}&firm_id=${searchData.firm_id || 0}&branch_id=${searchData.branch_id || 0}&month=${searchData.month || ''}`}
                            className={`rounded-lg px-2.5 py-1 text-xs font-medium transition-colors ${
                                link.active
                                    ? 'bg-indigo-600 text-white shadow-xs'
                                    : !link.url
                                        ? 'cursor-not-allowed opacity-40 text-slate-400'
                                        : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
};

export default WorkerMonthlyAttendanceTable;


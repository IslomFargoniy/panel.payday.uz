import { SearchData, WorkerPaginate } from '@/types';
import { Link } from '@inertiajs/react';
import { Check, Clock, Minus, MoonStar } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';

type WorkerTableProps = {
    worker: WorkerPaginate;
    branch_id?: number;
    searchData: SearchData;
};

const WorkerAttendanceTable = ({ worker, searchData }: WorkerTableProps) => {
    const { t } = useTranslation();

    // Global totals
    let allCheckCount = 0;
    let allLateCount = 0;
    let allAbsentCount = 0;

    return (
        <div className="space-y-2">
            {/* Legend */}
            <div className="flex items-center justify-end">
                <div className="hidden sm:flex items-center gap-4 text-xs font-medium text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-900 px-3 py-1.5 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <span className="flex items-center gap-1.5">
                        <span className="flex h-5 w-5 items-center justify-center rounded-md bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <Check className="w-3 h-3 stroke-[3]" />
                        </span>
                        {t('on_time', 'O‘z vaqtida')}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="flex h-5 w-5 items-center justify-center rounded-md bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                            <Clock className="w-3 h-3 stroke-[2.5]" />
                        </span>
                        {t('late', 'Kechikkan')}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="flex h-5 w-5 items-center justify-center rounded-md bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400">
                            <Minus className="w-3 h-3 stroke-[3]" />
                        </span>
                        {t('absent', 'Kelmadi')}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="flex h-5 w-5 items-center justify-center rounded-md bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                            <MoonStar className="w-3 h-3" />
                        </span>
                        {t('off_day', 'Dam olish')}
                    </span>
                </div>
            </div>

            {/* Table Card */}
            <div className="w-full min-w-0 max-w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="w-full overflow-x-auto">
                    <table className="w-full min-w-[1100px] text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-3 py-2.5 text-center w-8 font-mono">{t('n', '№')}</th>
                                <th className="px-3 py-2.5 min-w-[140px]">{t('name', 'F.I.SH')}</th>
                                <th className="px-3 py-2.5 min-w-[120px]">{t('firm', 'Filial')}</th>

                                {[...Array(searchData.daysInMonth)].map((_, index) => (
                                    <th key={index} className="px-1.5 py-2.5 text-center font-mono w-7 text-[11px]">
                                        {index + 1}
                                    </th>
                                ))}

                                <th className="px-2 py-2.5 text-center text-emerald-600 dark:text-emerald-400 font-bold" title={t('on_time')}>
                                    <Check className="w-3.5 h-3.5 mx-auto" />
                                </th>
                                <th className="px-2 py-2.5 text-center text-amber-600 dark:text-amber-400 font-bold" title={t('late')}>
                                    <Clock className="w-3.5 h-3.5 mx-auto" />
                                </th>
                                <th className="px-2 py-2.5 text-center text-rose-600 dark:text-rose-400 font-bold" title={t('absent')}>
                                    <Minus className="w-3.5 h-3.5 mx-auto" />
                                </th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {worker.data?.map((item, rowIndex) => {
                                const globalIndex = (worker.current_page - 1) * worker.per_page + rowIndex + 1;

                                let checkCount = 0;
                                let lateCount = 0;
                                let absentCount = 0;

                                const dayCells = [...Array(searchData.daysInMonth)].map((_, dayIndex) => {
                                    const day = String(dayIndex + 1).padStart(2, '0');
                                    const dateToCompare = `${searchData.month}-${day}`;

                                    const dayEvents = item.hikvision_access_events?.filter((event) => {
                                        return format(new Date(event.created_at), 'yyyy-MM-dd') === dateToCompare;
                                    });
                                    const event = dayEvents?.find(e => format(new Date(e.created_at), 'HH:mm:ss') >= '05:00:00') || dayEvents?.[0];

                                    if (event) {
                                        const eventTime = format(new Date(event.created_at), 'HH:mm:ss');
                                        const workTime = event.work_time || item.work_time;

                                        if (workTime) {
                                            if (eventTime <= workTime) {
                                                checkCount++;
                                            } else {
                                                lateCount++;
                                            }
                                        } else {
                                            checkCount++;
                                        }
                                    } else {
                                        absentCount++;
                                    }

                                    const isOffDay = item.holidays?.includes(Number(day));

                                    return (
                                        <td key={dayIndex} className="px-1 py-2 text-center">
                                            {event ? (
                                                (() => {
                                                    const eventTimeStr = format(new Date(event.created_at), 'HH:mm:ss');
                                                    const workTimeStr = event.work_time || item.work_time;

                                                    return !workTimeStr || eventTimeStr <= workTimeStr ? (
                                                        <span className="inline-flex h-5 w-5 items-center justify-center rounded-md bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                                                            <Check className="w-3 h-3 stroke-[3]" />
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex h-5 w-5 items-center justify-center rounded-md bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400">
                                                            <Clock className="w-3 h-3 stroke-[2.5]" />
                                                        </span>
                                                    );
                                                })()
                                            ) : (
                                                <>
                                                    {isOffDay ? (
                                                        <span className="inline-flex h-5 w-5 items-center justify-center rounded-md bg-indigo-50 text-indigo-500 dark:bg-indigo-950/40 dark:text-indigo-400">
                                                            <MoonStar className="w-3 h-3" />
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex h-5 w-5 items-center justify-center rounded-md bg-rose-50 text-rose-500 dark:bg-rose-950/40 dark:text-rose-400">
                                                            <Minus className="w-3 h-3 stroke-[3]" />
                                                        </span>
                                                    )}
                                                </>
                                            )}
                                        </td>
                                    );
                                });

                                allCheckCount += checkCount;
                                allLateCount += lateCount;
                                allAbsentCount += absentCount;

                                return (
                                    <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                        <td className="px-3 py-2 text-center font-mono text-slate-400 dark:text-slate-500">{globalIndex}</td>
                                        <td className="px-3 py-2 font-medium text-slate-900 dark:text-slate-100">
                                            <Link href={`/worker/${item.id}`} className="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                {item.name}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2 text-slate-500 truncate max-w-[140px]">
                                            {item.branch?.firm?.name} ({item.branch?.name})
                                        </td>
                                        {dayCells}
                                        <td className="px-2 py-2 text-center font-bold font-mono text-emerald-600 dark:text-emerald-400">{checkCount}</td>
                                        <td className="px-2 py-2 text-center font-bold font-mono text-amber-600 dark:text-amber-400">{lateCount}</td>
                                        <td className="px-2 py-2 text-center font-bold font-mono text-rose-600 dark:text-rose-400">{absentCount}</td>
                                    </tr>
                                );
                            })}
                        </tbody>

                        <tfoot className="border-t-2 border-slate-200 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-900 font-semibold text-slate-700 dark:text-slate-200">
                            <tr>
                                <td className="px-3 py-2.5 text-center font-mono">∑</td>
                                <td className="px-3 py-2.5">{t('all', 'Jami')}</td>
                                <td colSpan={(searchData.daysInMonth ?? 0) + 1} className="px-3 py-2.5 text-slate-400"></td>
                                <td className="px-2 py-2.5 text-center font-mono text-emerald-600 dark:text-emerald-400 font-bold">{allCheckCount}</td>
                                <td className="px-2 py-2.5 text-center font-mono text-amber-600 dark:text-amber-400 font-bold">{allLateCount}</td>
                                <td className="px-2 py-2.5 text-center font-mono text-rose-600 dark:text-rose-400 font-bold">{allAbsentCount}</td>
                            </tr>
                        </tfoot>
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
                            href={`${link.url ?? '?'}&search=${searchData.search}&per_page=${searchData.per_page}&firm_id=${searchData.firm_id}&branch_id=${searchData.branch_id}&month=${searchData.month}`}
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

export default WorkerAttendanceTable;


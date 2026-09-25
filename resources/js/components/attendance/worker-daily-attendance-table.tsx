import React from 'react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { format, parseISO, differenceInSeconds } from 'date-fns';
import { SearchData, WorkerPaginate } from '@/types';
import { Clock, ArrowDownLeft, ArrowUpRight } from 'lucide-react';

type WorkerTableProps = {
    worker: WorkerPaginate;
    branch_id?: number;
    searchData: SearchData;
};

const WorkerDailyAttendanceTable = ({ worker, searchData }: WorkerTableProps) => {
    const { t } = useTranslation();

    return (
        <div className="space-y-3">
            {/* Table Card */}
            <div className="w-full min-w-0 max-w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="w-full overflow-x-auto">
                    <table className="w-full min-w-[850px] text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-3.5 py-3 text-center w-10 font-mono">{t('n', '№')}</th>
                                <th className="px-3.5 py-3">{t('name', 'F.I.SH')}</th>
                                <th className="px-3.5 py-3">{t('phone', 'Telefon')}</th>
                                <th className="px-3.5 py-3">{t('date', 'Sana')}</th>
                                <th className="px-3.5 py-3 text-emerald-600 dark:text-emerald-400">{t('checkIn', 'Kirdi')}</th>
                                <th className="px-3.5 py-3 text-blue-600 dark:text-blue-400">{t('checkOut', 'Chiqdi')}</th>
                                <th className="px-3.5 py-3 text-rose-600 dark:text-rose-400">{t('late', 'Kechikish')}</th>
                                <th className="px-3.5 py-3 text-indigo-600 dark:text-indigo-400">{t('worked', 'Ishlagan vaqti')}</th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {(!worker.data || worker.data.length === 0) ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center text-slate-400 dark:text-slate-500">
                                        {t('no_data', 'Ma’lumot mavjud emas')}
                                    </td>
                                </tr>
                            ) : (
                                worker.data.map((item, rowIndex) => {
                                    const globalIndex = (worker.current_page - 1) * worker.per_page + rowIndex + 1;

                                    const checkIns = item.hikvision_access_events?.filter(event => ['checkIn', 'keldi', 'entered'].includes(event.attendanceStatus)) || [];
                                    const checkOuts = item.hikvision_access_events?.filter(event => ['checkOut', 'ketdi', 'exited'].includes(event.attendanceStatus)) || [];

                                    const pairedFromTimes = new Set(item.paired_events?.map(p => p.from_time).filter(Boolean) || []);
                                    const pairedToTimes = new Set(item.paired_events?.map(p => p.to_time).filter(Boolean) || []);

                                    return (
                                        <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-3.5 py-2.5 text-center font-mono text-slate-400 dark:text-slate-500">{globalIndex}</td>
                                            <td className="px-3.5 py-2.5 font-medium text-slate-900 dark:text-slate-100">
                                                <Link href={`/worker/${item.id}`} className="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                    {item.name}
                                                </Link>
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-500">{item.phone || '—'}</td>
                                            <td className="px-3.5 py-2.5 font-mono text-[11px] text-slate-500">
                                                {item.hikvision_access_events?.[0]?.created_at
                                                    ? format(new Date(item.hikvision_access_events[0].created_at), 'yyyy-MM-dd')
                                                    : '—'}
                                            </td>

                                            <td className="px-3.5 py-2.5">
                                                {checkIns.length > 0 ? (
                                                    <div className="flex flex-wrap gap-1">
                                                        {checkIns.map((ci, i) => {
                                                            const isAccepted = pairedFromTimes.size > 0 
                                                                ? pairedFromTimes.has(ci.created_at)
                                                                : i === 0;
                                                            return (
                                                                <span
                                                                    key={i}
                                                                    title={isAccepted ? t('check_in_accepted', 'Qabul qilingan kirish') : t('check_in_consecutive_skipped', 'Ketma-ket kelish (birinchisi hisobga olingan)')}
                                                                    className={`inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md font-mono text-[11px] transition-all ${
                                                                        isAccepted
                                                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 font-semibold'
                                                                            : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500 line-through opacity-60'
                                                                    }`}
                                                                >
                                                                    <ArrowDownLeft className={`w-3 h-3 ${isAccepted ? 'text-emerald-500' : 'text-slate-400'}`} />
                                                                    {format(new Date(ci.created_at), 'HH:mm:ss')}
                                                                </span>
                                                            );
                                                        })}
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400">—</span>
                                                )}
                                            </td>

                                            <td className="px-3.5 py-2.5">
                                                {checkOuts.length > 0 ? (
                                                    <div className="flex flex-wrap gap-1">
                                                        {checkOuts.map((co, i) => {
                                                            const isAccepted = pairedToTimes.size > 0
                                                                ? pairedToTimes.has(co.created_at)
                                                                : i === checkOuts.length - 1;
                                                            return (
                                                                <span
                                                                    key={i}
                                                                    title={isAccepted ? t('check_out_accepted', 'Qabul qilingan chiqish') : t('check_out_consecutive_skipped', 'Ketma-ket ketish (oxirgisi hisobga olingan)')}
                                                                    className={`inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md font-mono text-[11px] transition-all ${
                                                                        isAccepted
                                                                            ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 font-semibold'
                                                                            : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500 line-through opacity-60'
                                                                    }`}
                                                                >
                                                                    <ArrowUpRight className={`w-3 h-3 ${isAccepted ? 'text-blue-500' : 'text-slate-400'}`} />
                                                                    {format(new Date(co.created_at), 'HH:mm:ss')}
                                                                </span>
                                                            );
                                                        })}
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400">—</span>
                                                )}
                                            </td>

                                            <td className="px-3.5 py-2.5">
                                                {item.late_minutes !== undefined && item.late_minutes > 0 ? (
                                                    (() => {
                                                        const hours = Math.floor(item.late_minutes / 60);
                                                        const minutes = item.late_minutes % 60;
                                                        return (
                                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 font-mono text-[11px] font-semibold">
                                                                <Clock className="w-3 h-3" />
                                                                {String(hours).padStart(2, '0')}:{String(minutes).padStart(2, '0')}
                                                            </span>
                                                        );
                                                    })()
                                                ) : checkIns.length > 0 ? (
                                                    <span className="text-emerald-500 font-mono text-[11px]">{t('on_time', 'O‘z vaqtida')}</span>
                                                ) : (
                                                    <span className="text-slate-400">—</span>
                                                )}
                                            </td>

                                            <td className="px-3.5 py-2.5">
                                                {item.worked_minutes !== undefined && item.worked_minutes > 0 ? (
                                                    (() => {
                                                        const hours = Math.floor(item.worked_minutes / 60);
                                                        const minutes = item.worked_minutes % 60;
                                                        return (
                                                            <div className="flex flex-col gap-0.5">
                                                                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 font-mono text-[11px] font-semibold">
                                                                    <Clock className="w-3 h-3 text-indigo-500" />
                                                                    {hours}s {String(minutes).padStart(2, '0')}daq
                                                                </span>
                                                                {item.paired_events && item.paired_events.length > 1 && (
                                                                    <div className="flex flex-wrap gap-1 text-[10px] text-slate-400 font-mono">
                                                                        {item.paired_events.map((p, idx) => (
                                                                            <span key={idx}>
                                                                                {format(new Date(p.from_time), 'HH:mm')}-{p.to_time ? format(new Date(p.to_time), 'HH:mm') : '...'}
                                                                            </span>
                                                                        ))}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        );
                                                    })()
                                                ) : (
                                                    <span className="text-slate-400">—</span>
                                                )}
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
                            href={`${link.url ?? '?'}&search=${searchData.search}&per_page=${searchData.per_page}&firm_id=${searchData.firm_id}&branch_id=${searchData.branch_id}`}
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

export default WorkerDailyAttendanceTable;


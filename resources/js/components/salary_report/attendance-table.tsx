import { type AttendancePaginate, SearchData } from '@/types';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import React from 'react';

interface AttendanceTableProps extends AttendancePaginate {
    searchData: SearchData;
}

const AttendanceTable = ({ searchData, ...attendance }: AttendanceTableProps) => {
    const { t } = useTranslation();

    return (
        <div className="space-y-3">
            <h3 className="text-sm font-semibold capitalize text-slate-800 dark:text-slate-200">
                {t('attendance')}
            </h3>

            {/* Table Card */}
            <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-4 py-3.5">{t('n')}</th>
                                <th className="px-4 py-3.5">{t('worker')}</th>
                                <th className="px-4 py-3.5">{t('firm')}</th>
                                <th className="px-4 py-3.5">{t('work_time')}</th>
                                <th className="px-4 py-3.5">{t('from')}</th>
                                <th className="px-4 py-3.5">{t('to')}</th>
                                <th className="px-4 py-3.5">{t('worked_minutes')}</th>
                                <th className="px-4 py-3.5">{t('break_minutes')}</th>
                                <th className="px-4 py-3.5 text-rose-600 dark:text-rose-400">{t('late_minutes')}</th>
                                <th className="px-4 py-3.5">{t('status')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80">
                            {attendance.data.map((item, index) => {
                                const globalIndex = (attendance.current_page - 1) * attendance.per_page + index + 1;
                                return (
                                    <tr key={item.id} className="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                        <td className="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{globalIndex}</td>
                                        <td className="px-4 py-3 font-medium text-slate-800 dark:text-slate-200">{item.worker}</td>
                                        <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                            {item.firm} ({item.branch})
                                        </td>
                                        <td className="px-4 py-3 text-xs font-mono text-slate-600 dark:text-slate-300">{item.work_time?.slice(0, 5)}</td>
                                        <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{item.from?.slice(0, 16)}</td>
                                        <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{item.to?.slice(0, 16)}</td>
                                        <td className="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">{item.worked_minutes}</td>
                                        <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{item.break_minutes}</td>
                                        <td className="px-4 py-3 font-semibold text-rose-600 dark:text-rose-400">
                                            {item.late_minutes > 0 ? item.late_minutes : 0}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                {item.status}
                                            </span>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <div>
                    {t('showing', {
                        from: attendance.from,
                        to: attendance.to,
                        total: attendance.total
                    })}
                </div>
                <div className="flex items-center gap-1">
                    {attendance.links.map((link, index) => (
                        <Link
                            key={index}
                            href={`${link.url ?? '?'}&search=${searchData.search || ''}&firm_id=${searchData.firm_id || 0}&branch_id=${searchData.branch_id || 0}&worker_id=${searchData.worker_id || 0}&from=${searchData.from ?? ''}&to=${searchData.to ?? ''}&per_page=${searchData.per_page || 10}`}
                            className={`rounded-lg px-2.5 py-1 text-xs font-medium transition-colors ${
                                link.active
                                    ? 'bg-indigo-600 text-white shadow-xs'
                                    : !link.url
                                        ? 'cursor-not-allowed opacity-40'
                                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
};

export default AttendanceTable;


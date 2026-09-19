import React from 'react';
import { useTranslation } from 'react-i18next';
import { WorkerHistory } from '@/types';
import { History } from 'lucide-react';

type WorkerTableProps = {
    history: WorkerHistory[];
};

const WorkerHistoryTable = ({ history }: WorkerTableProps) => {
    const { t } = useTranslation();

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <div className="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <History className="w-4 h-4" />
                    </div>
                    <h3 className="font-semibold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <span>{t('worker_history', 'Xodim moliyaviy tarixi')}</span>
                        <span className="text-xs font-normal px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                            {history?.length || 0} ta
                        </span>
                    </h3>
                </div>
            </div>

            {/* Table Card */}
            <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-3.5 py-3 text-center w-10 font-mono">{t('n', '№')}</th>
                                <th className="px-3.5 py-3">{t('amount', 'Summa')}</th>
                                <th className="px-3.5 py-3">{t('action', 'Operatsiya turi')}</th>
                                <th className="px-3.5 py-3">{t('balance', 'Balans')}</th>
                                <th className="px-3.5 py-3">{t('user_name', 'Mas’ul xodim')}</th>
                                <th className="px-3.5 py-3">{t('date', 'Sana')}</th>
                                <th className="px-3.5 py-3">{t('comment', 'Izoh')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {(!history || history.length === 0) ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-slate-400 dark:text-slate-500">
                                        {t('no_data', 'Tarix mavjud emas')}
                                    </td>
                                </tr>
                            ) : (
                                history.map((item, index) => {
                                    const isSalary = !!item.salary_id;
                                    return (
                                        <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-3.5 py-2.5 text-center font-mono text-slate-400 dark:text-slate-500">{index + 1}</td>
                                            <td className={`px-3.5 py-2.5 font-semibold ${isSalary ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`}>
                                                {isSalary ? '+' : '-'}{Number(item.amount).toLocaleString()} {t('sum', 'so‘m')}
                                            </td>
                                            <td className="px-3.5 py-2.5">
                                                <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ${
                                                    isSalary
                                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                        : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'
                                                }`}>
                                                    {isSalary ? t('salary', 'Oylik hisoblandi') : t('salary_payment', 'Oylik to‘landi')}
                                                </span>
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono font-medium text-slate-900 dark:text-slate-100">
                                                {Number(item.balance).toLocaleString()} {t('sum', 'so‘m')}
                                            </td>
                                            <td className="px-3.5 py-2.5 text-slate-600 dark:text-slate-300">{item.user_name || '—'}</td>
                                            <td className="px-3.5 py-2.5 font-mono text-[11px] text-slate-500">{item.created_at}</td>
                                            <td className="px-3.5 py-2.5 text-slate-400 dark:text-slate-500 truncate max-w-[150px]" title={item.comment || ''}>
                                                {item.comment || '—'}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

export default WorkerHistoryTable;


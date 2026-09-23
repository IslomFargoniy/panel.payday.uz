import { Report } from '@/types';
import { useTranslation } from 'react-i18next';

const RightBar = ({ ...report }: Report) => {
    const { t } = useTranslation();

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('uz-UZ', {
            maximumFractionDigits: 0,
        }).format(value).replace(/,/g, ' ');
    };

    return (
        <div className="space-y-3">
            <h3 className="text-sm font-semibold capitalize text-slate-800 dark:text-slate-200">
                {t('report')}
            </h3>

            {/* Table Card */}
            <div className="w-full min-w-0 max-w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="w-full overflow-x-auto">
                    <table className="w-full min-w-[280px] text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-4 py-3">{t('status')}</th>
                                <th className="px-4 py-3 text-right">{t('count')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            <tr className="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td className="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">{t('working_days')}</td>
                                <td className="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">
                                    {(() => {
                                        const workingDays = report.working_days ?? 0;

                                        const toMinutes = (time?: string | number) => {
                                            if (!time) return 0;
                                            const [h, m] = String(time).split(':').map(Number);
                                            return h * 60 + (m || 0);
                                        };

                                        const dailyMinutes = toMinutes(report.end_time) - toMinutes(report.work_time);

                                        if (workingDays <= 0 || dailyMinutes <= 0) {
                                            return `${workingDays} ${t('day')}`;
                                        }

                                        const totalMinutes = workingDays * dailyMinutes;
                                        const hours = Math.floor(totalMinutes / 60);
                                        const minutes = totalMinutes % 60;

                                        return `${workingDays} ${t('day')} (${hours} soat ${minutes} min)`;
                                    })()}
                                </td>
                            </tr>
                            <tr className="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td className="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">{t('worked_days')}</td>
                                <td className="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">
                                    {report.worked_days} {t('day')}
                                </td>
                            </tr>
                            <tr className="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td className="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">{t('worked_hours')}</td>
                                <td className="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">
                                    {~~(report.worked_minutes / 60)} {t('hour')} {report.worked_minutes % 60} {t('minute')}
                                </td>
                            </tr>

                            {(() => {
                                const hourPrice = report.hour_price ?? 0;
                                const workedMinutes = report.worked_minutes ?? 0;

                                if (hourPrice <= 0) return null;

                                const calculated = (workedMinutes * hourPrice) / 60;

                                return (
                                    <>
                                        <tr className="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                            <td className="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">{t('hour_price')}</td>
                                            <td className="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">
                                                {formatCurrency(hourPrice)}
                                            </td>
                                        </tr>

                                        <tr className="bg-emerald-50/50 transition-colors hover:bg-emerald-50/80 dark:bg-emerald-950/20 dark:hover:bg-emerald-950/30">
                                            <td className="px-4 py-3 font-semibold text-emerald-700 dark:text-emerald-300">{t('calculated')}</td>
                                            <td className="px-4 py-3 text-right font-bold text-emerald-700 dark:text-emerald-300">
                                                {formatCurrency(calculated)}
                                            </td>
                                        </tr>
                                    </>
                                );
                            })()}

                            <tr className="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td className="px-4 py-3 font-medium text-rose-600 dark:text-rose-400">{t('late_hours')}</td>
                                <td className="px-4 py-3 text-right font-medium text-rose-600 dark:text-rose-400">
                                    {~~(report.late_minutes / 60)} {t('hour')} {report.late_minutes % 60} {t('minute')}
                                </td>
                            </tr>
                            <tr className="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td className="px-4 py-3 font-medium text-rose-600 dark:text-rose-400">{t('fine')}</td>
                                <td className="px-4 py-3 text-right font-semibold text-rose-600 dark:text-rose-400">
                                    {(() => {
                                        const lateMinutes = report.late_minutes ?? 0;
                                        const finePrice = report.fine_price ?? 0;

                                        if (lateMinutes <= 0 || finePrice <= 0) return 0;

                                        const totalFine = (lateMinutes / 60) * finePrice;

                                        return formatCurrency(totalFine);
                                    })()}
                                </td>
                            </tr>
                            <tr className="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td className="px-4 py-3 font-medium text-rose-600 dark:text-rose-400">{t('late_days')}</td>
                                <td className="px-4 py-3 text-right font-medium text-rose-600 dark:text-rose-400">
                                    {report.late_days} {t('day')}
                                </td>
                            </tr>
                            {report.last_salary_date && (
                                <tr className="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td className="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">{t('last_salary_date')}</td>
                                    <td className="px-4 py-3 text-right text-xs text-slate-500 dark:text-slate-400">{report.last_salary_date}</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

export default RightBar;


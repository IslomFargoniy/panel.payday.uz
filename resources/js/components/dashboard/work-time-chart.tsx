import React from 'react';
import Chart from 'react-apexcharts';
import { ApexOptions } from 'apexcharts';
import { DailyStats } from '@/types';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from 'react-i18next';
import { Clock, Briefcase, Coffee, AlertCircle } from 'lucide-react';

type Props = {
    daily_stats: DailyStats[];
};

const WorkTimeChart = ({ daily_stats }: Props) => {
    const data = daily_stats;
    const { t } = useTranslation();
    const { appearance } = useAppearance();

    const resolvedTheme =
        appearance === 'system'
            ? (typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : appearance;

    const isDark = resolvedTheme === 'dark';

    const series = [
        {
            name: t('worked'),
            data: data.map((item) => parseFloat(item.worked_hours || '0'))
        },
        {
            name: t('break'),
            data: data.map((item) => parseFloat(item.break_hours || '0'))
        },
        {
            name: t('late'),
            data: data.map((item) => parseFloat(item.late_hours || '0'))
        }
    ];

    const totalWorked = data.reduce((acc, curr) => acc + parseFloat(curr.worked_hours || '0'), 0);
    const totalBreak = data.reduce((acc, curr) => acc + parseFloat(curr.break_hours || '0'), 0);
    const totalLate = data.reduce((acc, curr) => acc + parseFloat(curr.late_hours || '0'), 0);

    const labelColor = isDark ? '#94A3B8' : '#64748B';
    const gridColor = isDark ? '#1E293B' : '#F1F5F9';

    const options: ApexOptions = {
        chart: {
            type: 'bar',
            height: 340,
            toolbar: { show: false },
            fontFamily: 'inherit',
            background: 'transparent',
            foreColor: labelColor,
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '42%',
                borderRadius: 4,
                borderRadiusApplication: 'end',
            }
        },
        dataLabels: { enabled: false },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        colors: ['#10B981', '#6366F1', '#F59E0B'],
        xaxis: {
            categories: data.map((item) => item.worked_date),
            labels: {
                style: {
                    colors: labelColor,
                    fontSize: '11px',
                    fontWeight: 500
                }
            },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            title: {
                text: `${t('hours')} (h)`,
                style: {
                    color: labelColor,
                    fontSize: '11px',
                    fontWeight: 500
                }
            },
            labels: {
                style: {
                    colors: labelColor,
                    fontSize: '11px'
                },
                formatter: (val: number) => val.toFixed(1)
            }
        },
        grid: {
            borderColor: gridColor,
            strokeDashArray: 4,
            padding: {
                top: 0,
                right: 10,
                bottom: 0,
                left: 10
            }
        },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
            y: {
                formatter: (val: number) => `${val.toFixed(1)} ${t('hour') || 'soat'}`
            }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '12px',
            labels: {
                colors: labelColor
            },
            markers: {
                size: 6,
                shape: 'circle'
            }
        }
    };

    return (
        <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
            <div className="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h3 className="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                        <Clock className="h-4 w-4 text-indigo-500" />
                        {t('work_hours_overview')}
                    </h3>
                    <p className="text-xs text-slate-500 dark:text-slate-400">
                        {t('common_worked_hours')}: <span className="font-semibold text-slate-700 dark:text-slate-200">{totalWorked.toFixed(1)} soat</span>
                    </p>
                </div>

                <div className="flex items-center gap-2 text-xs">
                    <div className="flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                        <Briefcase className="h-3 w-3" />
                        <span className="font-semibold">{totalWorked.toFixed(1)}h</span>
                    </div>
                    <div className="flex items-center gap-1.5 rounded-lg bg-indigo-50 px-2.5 py-1 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                        <Coffee className="h-3 w-3" />
                        <span className="font-semibold">{totalBreak.toFixed(1)}h</span>
                    </div>
                    <div className="flex items-center gap-1.5 rounded-lg bg-amber-50 px-2.5 py-1 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                        <AlertCircle className="h-3 w-3" />
                        <span className="font-semibold">{totalLate.toFixed(1)}h</span>
                    </div>
                </div>
            </div>

            {data.length === 0 ? (
                <div className="flex h-[320px] items-center justify-center text-xs text-slate-400">
                    {t('no_data')}
                </div>
            ) : (
                <div className="h-[340px] w-full">
                    <Chart options={options} series={series} type="bar" height={340} />
                </div>
            )}
        </div>
    );
};

export default WorkTimeChart;

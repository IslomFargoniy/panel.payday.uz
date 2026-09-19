import React, { useMemo, useEffect } from 'react';
import * as echarts from 'echarts';
import { Stats } from '@/types';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from 'react-i18next';
import { PieChart as PieIcon, CheckCircle2, Clock, XCircle, Palmtree, LogOut } from 'lucide-react';

type Props = {
    stats: Stats;
};

const StatsPieChart = ({ stats }: Props) => {
    const { appearance } = useAppearance();
    const { t } = useTranslation();

    const resolvedTheme =
        appearance === 'system'
            ? (typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : appearance;

    const isDark = resolvedTheme === 'dark';

    const values = useMemo(() => [
        { value: stats.on_time, name: t('on_time'), itemStyle: { color: '#10B981' } },
        { value: stats.late, name: t('late'), itemStyle: { color: '#F59E0B' } },
        { value: stats.absent, name: t('absent'), itemStyle: { color: '#EF4444' } },
        { value: stats.on_holiday, name: t('on_holiday'), itemStyle: { color: '#8B5CF6' } },
        { value: stats.gone, name: t('gone'), itemStyle: { color: '#3B82F6' } }
    ].filter(item => item.value > 0), [stats, t]);

    const totalTracked = (stats.on_time || 0) + (stats.late || 0) + (stats.absent || 0) + (stats.on_holiday || 0);
    const attendanceRate = totalTracked > 0 ? (((stats.on_time || 0) + (stats.late || 0)) / totalTracked * 100).toFixed(1) : '0';

    useEffect(() => {
        const chartDom = document.getElementById('stats-pie-chart');
        if (!chartDom) return;

        const chart = echarts.init(chartDom, isDark ? 'dark' : undefined, {
            renderer: 'canvas'
        });

        chart.setOption({
            backgroundColor: 'transparent',
            tooltip: {
                trigger: 'item',
                backgroundColor: isDark ? '#1E293B' : '#FFFFFF',
                borderColor: isDark ? '#334155' : '#E2E8F0',
                textStyle: {
                    color: isDark ? '#F8FAFC' : '#0F172A',
                    fontSize: 12
                },
                formatter: '{b}: <b style="font-weight:700">{c} ta</b> ({d}%)'
            },
            series: [
                {
                    name: t('attendance'),
                    type: 'pie',
                    radius: ['52%', '75%'],
                    center: ['50%', '50%'],
                    avoidLabelOverlap: false,
                    itemStyle: {
                        borderRadius: 6,
                        borderColor: isDark ? '#0F172A' : '#FFFFFF',
                        borderWidth: 2
                    },
                    label: {
                        show: false
                    },
                    emphasis: {
                        scale: true,
                        scaleSize: 6,
                        label: {
                            show: true,
                            fontSize: 13,
                            fontWeight: 'bold',
                            formatter: '{b}\n{d}%'
                        }
                    },
                    data: values.length > 0 ? values : [{ value: 1, name: t('no_data'), itemStyle: { color: isDark ? '#334155' : '#E2E8F0' } }]
                }
            ]
        });

        const handleResize = () => chart.resize();
        window.addEventListener('resize', handleResize);
        return () => {
            window.removeEventListener('resize', handleResize);
            chart.dispose();
        };
    }, [values, isDark, t]);

    return (
        <div className="flex h-full flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
            <div>
                <div className="mb-2 flex items-center justify-between">
                    <h3 className="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                        <PieIcon className="h-4 w-4 text-indigo-500" />
                        {t('attendance_distribution')}
                    </h3>
                    <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                        {attendanceRate}% {t('active')}
                    </span>
                </div>
                <p className="text-xs text-slate-500 dark:text-slate-400">
                    {t('daily_attendance')} tahlili
                </p>
            </div>

            <div className="relative my-2 flex items-center justify-center">
                <div id="stats-pie-chart" className="h-[210px] w-full" />
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span className="text-xs font-medium text-slate-400">{t('at_work')}</span>
                    <span className="text-xl font-extrabold text-slate-900 dark:text-white">
                        {stats.on_time + stats.late - stats.gone}
                    </span>
                </div>
            </div>

            <div className="space-y-2 pt-2 text-xs border-t border-slate-100 dark:border-slate-800">
                <div className="flex items-center justify-between">
                    <span className="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                        <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                        {t('on_time')}
                    </span>
                    <span className="font-bold text-slate-800 dark:text-slate-100">{stats.on_time}</span>
                </div>

                <div className="flex items-center justify-between">
                    <span className="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                        <Clock className="h-3.5 w-3.5 text-amber-500" />
                        {t('late')}
                    </span>
                    <span className="font-bold text-slate-800 dark:text-slate-100">{stats.late}</span>
                </div>

                <div className="flex items-center justify-between">
                    <span className="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                        <XCircle className="h-3.5 w-3.5 text-rose-500" />
                        {t('absent')}
                    </span>
                    <span className="font-bold text-slate-800 dark:text-slate-100">{stats.absent}</span>
                </div>

                <div className="flex items-center justify-between">
                    <span className="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                        <Palmtree className="h-3.5 w-3.5 text-purple-500" />
                        {t('on_holiday')}
                    </span>
                    <span className="font-bold text-slate-800 dark:text-slate-100">{stats.on_holiday}</span>
                </div>

                <div className="flex items-center justify-between">
                    <span className="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                        <LogOut className="h-3.5 w-3.5 text-blue-500" />
                        {t('gone')}
                    </span>
                    <span className="font-bold text-slate-800 dark:text-slate-100">{stats.gone}</span>
                </div>
            </div>
        </div>
    );
};

export default StatsPieChart;

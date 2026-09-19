import AppLayout from '@/layouts/app-layout';
import { Auth, Branch, type BreadcrumbItem, DailyStats, Firm, SearchData, Stats } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import WorkTimeChart from '@/components/dashboard/work-time-chart';
import StatsPieChart from '@/components/dashboard/stats-pie-chart';
import DashboardFilterForm from '@/components/dashboard/dashboard-filter-form';
import { useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Users,
    UserCheck,
    ClockAlert,
    UserX,
    Building2,
    GitBranch,
    ArrowRight,
    TrendingUp,
    Calendar,
    Sparkles
} from 'lucide-react';

interface PageProps {
    stats: Stats;
    daily_stats: DailyStats[];
    firms: Firm[];
    branches: Branch[];
    [key: string]: unknown;
}

export default function Dashboard() {
    const { t, i18n } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('sidebar.dashboard'),
            href: '/dashboard'
        }
    ];

    const { auth } = usePage().props as unknown as { auth?: Auth };
    const { stats, daily_stats, firms, branches } = usePage<PageProps>().props;

    const { data, setData } = useForm<SearchData>({
        search: '',
        firm_id: 0,
        branch_id: 0
    });

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        router.get('/dashboard', data, { preserveState: true });
    };

    useEffect(() => {
        const urlParams = new URLSearchParams(location.search);
        const searchQuery = urlParams.get('search') || '';
        const firm_id = parseInt(urlParams.get('firm_id') ?? '0', 10);
        const branch_id = parseInt(urlParams.get('branch_id') ?? '0', 10);

        setData({
            search: searchQuery,
            firm_id: isNaN(firm_id) ? 0 : firm_id,
            branch_id: isNaN(branch_id) ? 0 : branch_id
        });
    }, [location.search]);

    // Live Attendance Rate %
    const attendancePercentage = useMemo(() => {
        if (!stats.all_worker || stats.all_worker === 0) return 0;
        const present = (stats.on_time || 0) + (stats.late || 0);
        return Math.min(100, Math.round((present / stats.all_worker) * 1000) / 10);
    }, [stats]);

    // Format current localized date
    const currentDateFormatted = useMemo(() => {
        const now = new Date();
        const locale = i18n.language === 'ru' ? 'ru-RU' : i18n.language === 'en' ? 'en-US' : 'uz-UZ';
        return new Intl.DateTimeFormat(locale, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }).format(now);
    }, [i18n.language]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('sidebar.dashboard')} />

            <div className="flex h-full flex-col gap-5 p-4 sm:p-6">
                {/* 1. Header Greeting & Filter Ribbon */}
                <div className="flex flex-col justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs dark:border-slate-800 dark:bg-slate-900 lg:flex-row lg:items-center">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                                {t('welcome_back')}, <span className="text-indigo-600 dark:text-indigo-400">{auth?.user.name}</span> 👋
                            </h1>
                        </div>
                        <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <span className="flex items-center gap-1 font-medium capitalize">
                                <Calendar className="h-3.5 w-3.5 text-slate-400" />
                                {currentDateFormatted}
                            </span>
                            <span className="text-slate-300 dark:text-slate-700">•</span>
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                                {attendancePercentage}% {t('today_attendance_rate')}
                            </span>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <DashboardFilterForm
                            handleSubmit={handleSubmit}
                            setData={setData}
                            data={data}
                            firms={firms}
                            branches={branches}
                        />
                    </div>
                </div>

                {/* 2. Top KPI Stat Cards (4 columns) */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* Card 1: Jami Xodimlar */}
                    <div className="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                {t('total_workers')}
                            </span>
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-xs shadow-indigo-500/20">
                                <Users className="h-5 w-5" />
                            </div>
                        </div>
                        <div className="mt-4 flex items-baseline gap-2">
                            <span className="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                                {stats.all_worker}
                            </span>
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
                                {t('workers_count')}
                            </span>
                        </div>
                        <div className="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 border-t border-slate-100 pt-3 dark:border-slate-800/80">
                            <span>{t('registered_staff')}</span>
                            <span className="font-semibold text-indigo-600 dark:text-indigo-400">100%</span>
                        </div>
                    </div>

                    {/* Card 2: O'z Vaqtida Kelganlar */}
                    <div className="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                {t('present_at_work')}
                            </span>
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-xs shadow-emerald-500/20">
                                <UserCheck className="h-5 w-5" />
                            </div>
                        </div>
                        <div className="mt-4 flex items-baseline gap-2">
                            <span className="text-2xl font-extrabold tracking-tight text-emerald-600 dark:text-emerald-400">
                                {stats.on_time}
                            </span>
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
                                ({stats.on_time + stats.late - stats.gone} {t('in_building')})
                            </span>
                        </div>
                        <div className="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 border-t border-slate-100 pt-3 dark:border-slate-800/80">
                            <span>{t('on_time')} ulushi</span>
                            <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                {stats.all_worker > 0 ? Math.round((stats.on_time / stats.all_worker) * 100) : 0}%
                            </span>
                        </div>
                    </div>

                    {/* Card 3: Kechikkanlar */}
                    <div className="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                {t('late_arrivals')}
                            </span>
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-xs shadow-amber-500/20">
                                <ClockAlert className="h-5 w-5" />
                            </div>
                        </div>
                        <div className="mt-4 flex items-baseline gap-2">
                            <span className="text-2xl font-extrabold tracking-tight text-amber-600 dark:text-amber-400">
                                {stats.late}
                            </span>
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
                                {t('workers_count')}
                            </span>
                        </div>
                        <div className="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 border-t border-slate-100 pt-3 dark:border-slate-800/80">
                            <span>{t('late')} darajasi</span>
                            <span className="font-semibold text-amber-600 dark:text-amber-400">
                                {stats.all_worker > 0 ? Math.round((stats.late / stats.all_worker) * 100) : 0}%
                            </span>
                        </div>
                    </div>

                    {/* Card 4: Kelmadi / Ta'tilda */}
                    <div className="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                {t('absent_on_leave')}
                            </span>
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-rose-500 to-red-600 text-white shadow-xs shadow-rose-500/20">
                                <UserX className="h-5 w-5" />
                            </div>
                        </div>
                        <div className="mt-4 flex items-baseline gap-2">
                            <span className="text-2xl font-extrabold tracking-tight text-rose-600 dark:text-rose-400">
                                {stats.absent + stats.on_holiday}
                            </span>
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
                                ({stats.absent} {t('absent')}, {stats.on_holiday} {t('on_holiday')})
                            </span>
                        </div>
                        <div className="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 border-t border-slate-100 pt-3 dark:border-slate-800/80">
                            <span>{t('absent')} ulushi</span>
                            <span className="font-semibold text-rose-600 dark:text-rose-400">
                                {stats.all_worker > 0 ? Math.round(((stats.absent + stats.on_holiday) / stats.all_worker) * 100) : 0}%
                            </span>
                        </div>
                    </div>
                </div>

                {/* 3. Main Analytics Section (8 Cols : 4 Cols) */}
                <div className="grid grid-cols-1 gap-5 lg:grid-cols-12">
                    {/* Left: Work Time Chart (8 cols) */}
                    <div className="lg:col-span-8">
                        <WorkTimeChart daily_stats={daily_stats} />
                    </div>

                    {/* Right: Stats Donut Chart (4 cols) */}
                    <div className="lg:col-span-4">
                        <StatsPieChart stats={stats} />
                    </div>
                </div>

                {/* 4. Branches Quick Overview Grid */}
                {branches && branches.length > 0 && (
                    <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                        <div className="mb-4 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <GitBranch className="h-4 w-4 text-indigo-500" />
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                    {t('branches_overview')}
                                </h3>
                                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {branches.length} ta {t('branch')}
                                </span>
                            </div>
                            <Link
                                href="/branch"
                                className="flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                            >
                                {t('view')}
                                <ArrowRight className="h-3.5 w-3.5" />
                            </Link>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                            {branches.map((b) => (
                                <Link
                                    key={b.id}
                                    href={`/branch/${b.id}`}
                                    className="group flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 transition-all hover:border-indigo-200 hover:bg-white hover:shadow-xs dark:border-slate-800/80 dark:bg-slate-800/40 dark:hover:border-slate-700 dark:hover:bg-slate-800"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <h4 className="text-xs font-bold text-slate-800 group-hover:text-indigo-600 dark:text-slate-200 dark:group-hover:text-indigo-400">
                                                {b.name}
                                            </h4>
                                            <p className="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500 truncate max-w-[180px]">
                                                {b.firm?.name || b.address || t('branch')}
                                            </p>
                                        </div>
                                        <span className={`inline-block h-2 w-2 rounded-full ${b.status ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'}`} />
                                    </div>

                                    <div className="mt-3 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 border-t border-slate-200/50 pt-2 dark:border-slate-700/50">
                                        <span>{t('work_time')}:</span>
                                        <span className="font-medium text-slate-700 dark:text-slate-300 font-mono">
                                            {b.work_time?.slice(0, 5)} - {b.end_time?.slice(0, 5)}
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

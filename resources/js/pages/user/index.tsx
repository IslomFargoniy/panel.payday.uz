import AppLayout from '@/layouts/app-layout';
import { Head, usePage, useForm, router } from '@inertiajs/react';
import { type BreadcrumbItem, type UserPaginate, SearchData } from '@/types';
import { useEffect } from 'react';
import UserTable from '@/components/user/user-table';
import SearchForm from '@/components/search-form';
import { useTranslation } from 'react-i18next';
import MobileSearchModal from '@/components/MobileSearchModal';
import { Clock, CheckCircle2 } from 'lucide-react';

interface UserPageProps {
    user: UserPaginate;
    tab?: 'waiting' | 'approved';
    counts?: {
        waiting: number;
        approved: number;
    };
    [key: string]: unknown;
}

export default function User() {
    const { user, tab = 'waiting', counts } = usePage<UserPageProps>().props;
    const currentTab = tab || 'waiting';
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('user'),
            href: '/dashboard',
        },
    ];

    // Form handling for search and per_page
    const { data, setData } = useForm<SearchData>({
        search: '',
        per_page: user.per_page,
        page: user.current_page,
        total: user.total,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/user', {
            search: data.search,
            per_page: data.per_page,
            tab: currentTab,
        });
    };

    const handleTabChange = (newTab: 'waiting' | 'approved') => {
        router.get(
            '/user',
            {
                search: data.search,
                per_page: data.per_page,
                tab: newTab,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    useEffect(() => {
        const urlParams = new URLSearchParams(location.search);
        const searchQuery = urlParams.get('search') || '';
        setData('search', searchQuery);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [location.search, setData]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('sidebar.user')} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 md:p-6 min-w-0 max-w-full">
                {/* Header: Tabs and Search */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 w-full min-w-0">
                    {/* Tabs */}
                    <div className="inline-flex items-center gap-1.5 p-1 rounded-xl bg-slate-100/90 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 self-start">
                        <button
                            type="button"
                            onClick={() => handleTabChange('waiting')}
                            className={`inline-flex items-center gap-2 px-3 py-1.5 text-xs sm:text-sm font-semibold rounded-lg transition-all ${
                                currentTab === 'waiting'
                                    ? 'bg-white text-amber-700 shadow-xs dark:bg-slate-900 dark:text-amber-400'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100'
                            }`}
                        >
                            <Clock className="h-4 w-4 text-amber-500" />
                            <span>{t('user_tab_waiting', 'Kutilmoqda')}</span>
                            <span
                                className={`px-2 py-0.2 text-xs rounded-full font-bold ${
                                    currentTab === 'waiting'
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300'
                                        : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300'
                                }`}
                            >
                                {counts?.waiting ?? 0}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => handleTabChange('approved')}
                            className={`inline-flex items-center gap-2 px-3 py-1.5 text-xs sm:text-sm font-semibold rounded-lg transition-all ${
                                currentTab === 'approved'
                                    ? 'bg-white text-indigo-700 shadow-xs dark:bg-slate-900 dark:text-indigo-400'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100'
                            }`}
                        >
                            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                            <span>{t('user_tab_approved', 'Tasdiqlangan')}</span>
                            <span
                                className={`px-2 py-0.2 text-xs rounded-full font-bold ${
                                    currentTab === 'approved'
                                        ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/80 dark:text-indigo-300'
                                        : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300'
                                }`}
                            >
                                {counts?.approved ?? 0}
                            </span>
                        </button>
                    </div>

                    {/* Search and Per-Page Selection */}
                    <div className="flex justify-end items-center min-w-0">
                        <MobileSearchModal
                            data={data}
                            setData={setData}
                            handleSubmit={handleSubmit}
                        />
                        <div className="hidden lg:block w-full max-w-full">
                            <SearchForm handleSubmit={handleSubmit} setData={setData} data={data} />
                        </div>
                    </div>
                </div>

                {/* Table */}
                <div className="w-full min-w-0 max-w-full">
                    <UserTable {...user} searchData={data} currentTab={currentTab} />
                </div>
            </div>
        </AppLayout>
    );
}

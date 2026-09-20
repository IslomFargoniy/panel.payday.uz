import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import AppLayout from '@/layouts/app-layout';
import { Head, usePage, useForm, Link } from '@inertiajs/react';
import { Branch, type BreadcrumbItem as LayoutBreadcrumbItem, SearchData, WorkerPaginate } from '@/types';
import { Building2 } from 'lucide-react';
import React, { useEffect } from 'react';
import { router } from '@inertiajs/react';
import SearchForm from '@/components/search-form';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';
import WorkerDailyAttendanceTable from '@/components/attendance/worker-daily-attendance-table';


export default function Attendance() {
    const {
        worker,
        branch,
    } = usePage<{
        worker: WorkerPaginate,
        branch: Branch,
    }>().props;

    const { t } = useTranslation();  // Using the translation hook


    const breadcrumbs: LayoutBreadcrumbItem[] = [
        {
            title: `${t('worker')}`,
            href: '/dashboard'
        }
    ];
    // Form handling for search and per_page
    const { data, setData } = useForm<SearchData>({
        search: '',
        per_page: worker.per_page,
        page: worker.current_page,
        total: worker.total,
        date: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(`/daily_attendance/${branch.id}`, data); // ✅ Correct for search queries
    };


    useEffect(() => {
        const urlParams = new URLSearchParams(location.search);
        const searchQuery = urlParams.get('search') || ''; // Get 'search' query from the URL
        setData('search', searchQuery); // Set it to the form state

        const dateStr = urlParams.get('date') ?? format(new Date(), 'yyyy-MM-dd');
        setData('date', dateStr);

    }, [location.search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('worker')} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 md:p-6 min-w-0 max-w-full">
                {/* Search and Per-Page Selection */}
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between bg-white dark:bg-slate-900 p-3.5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs min-w-0 max-w-full">
                    <Breadcrumb className="min-w-0">
                        <BreadcrumbList className="text-xs sm:text-sm font-medium flex-wrap">
                            <BreadcrumbItem>
                                <BreadcrumbLink asChild>
                                    <Link href="/firm" className="flex items-center gap-1.5 text-muted-foreground hover:text-primary transition-colors">
                                        <Building2 className="h-4 w-4" />
                                        <span>{t('firm')}</span>
                                    </Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbLink asChild>
                                    <Link href={`/firm/${branch.firm_id}`} className="text-muted-foreground hover:text-primary transition-colors truncate max-w-[120px] sm:max-w-none">
                                        {branch.firm?.name}
                                    </Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbLink asChild>
                                    <Link href={`/branch/${branch.id}`} className="text-muted-foreground hover:text-primary transition-colors truncate max-w-[120px] sm:max-w-none">
                                        {branch.name}
                                    </Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbPage className="font-semibold text-foreground px-2.5 py-1 rounded-md bg-muted text-xs">
                                    {t('daily_attendance')}
                                </BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                    <div className="flex items-center gap-2 min-w-0">
                        <SearchForm handleSubmit={handleSubmit} setData={setData} data={data} />
                    </div>
                </div>

                {/* Table */}
                <div className="w-full min-w-0 max-w-full">
                    {worker &&
                        <WorkerDailyAttendanceTable
                            worker={worker}
                            searchData={data}
                        />
                    }
                </div>
            </div>
        </AppLayout>
    );
}

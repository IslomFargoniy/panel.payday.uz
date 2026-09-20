import BranchDayTable from '@/components/branch/branch-day-table';
import BranchDeviceTable from '@/components/branch/branch-device-table';
import BranchHolidayTable from '@/components/branch/branch-holiday-table';
import WorkerTable from '@/components/branch/worker-table';
import MobileSearchModal from '@/components/MobileSearchModal';
import SearchForm from '@/components/search-form';
import { Button } from '@/components/ui/button';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import AppLayout from '@/layouts/app-layout';
import { type Branch, type BreadcrumbItem as LayoutBreadcrumbItem, Day, SearchData, WorkerPaginate } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Building2, CalendarCheck } from 'lucide-react';
import React, { useEffect } from 'react';
import { useTranslation } from 'react-i18next';

export default function Branch() {
    const { branch, days, worker } = usePage<{
        branch: Branch;
        days: Day[];
        worker: WorkerPaginate;
    }>().props;
    const { t } = useTranslation(); // Using the translation hook

    const breadcrumbs: LayoutBreadcrumbItem[] = [
        {
            title: `${t('branch')} (${branch.name})`,
            href: '/dashboard',
        },
    ];
    // Form handling for search and per_page
    const { data, setData } = useForm<SearchData>({
        search: '',
        per_page: worker.per_page,
        page: worker.current_page,
        total: worker.total,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(`/branch/${branch.id}`, data); // ✅ Correct for search queries
    };

    useEffect(() => {
        const urlParams = new URLSearchParams(location.search);
        const searchQuery = urlParams.get('search') || ''; // Get 'search' query from the URL
        setData('search', searchQuery); // Set it to the form state
    }, [location.search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('branch')}: ${branch.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 md:p-6 min-w-0 max-w-full">
                {/* Header: Breadcrumbs Navigation, Daily Attendance Action, and Search/Filter */}
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between bg-white dark:bg-slate-900 p-3.5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs min-w-0 max-w-full">
                    {/* Left: Breadcrumbs */}
                    <div className="flex items-center min-w-0">
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
                                    <BreadcrumbPage className="font-semibold text-foreground px-2.5 py-1 rounded-md bg-muted text-xs">
                                        {branch.name}
                                    </BreadcrumbPage>
                                </BreadcrumbItem>
                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>

                    {/* Middle: Daily Attendance Button */}
                    <div className="flex items-center justify-start lg:justify-center">
                        <Button
                            asChild
                            className="bg-emerald-600 hover:bg-emerald-700 text-white font-medium shadow-xs hover:shadow transition-all gap-2 h-9 px-4"
                        >
                            <Link href={`/daily_attendance/${branch.id}`}>
                                <CalendarCheck className="h-4 w-4" />
                                <span>{t('daily_attendance')}</span>
                            </Link>
                        </Button>
                    </div>

                    {/* Right: Search and Per-Page Selection */}
                    <div className="flex items-center gap-2 min-w-0">
                        <MobileSearchModal data={data} setData={setData} handleSubmit={handleSubmit} />
                        <div className="hidden lg:block">
                            <SearchForm handleSubmit={handleSubmit} setData={setData} data={data} />
                        </div>
                    </div>
                </div>

                {/* Content Layout */}
                <div className="pt-1 w-full min-w-0 max-w-full">
                    <div className="grid grid-cols-12 gap-6 min-w-0 max-w-full">
                        <div className="col-span-12 lg:col-span-8 space-y-6 min-w-0 max-w-full">
                            {branch.workers && <WorkerTable worker={worker} branch={branch} searchData={data} />}
                        </div>
                        <div className="col-span-12 lg:col-span-4 space-y-6 min-w-0 max-w-full">
                            <BranchDayTable branch={branch} days={days} />
                            <BranchDeviceTable branch={branch} />
                            <BranchHolidayTable branch={branch} />
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

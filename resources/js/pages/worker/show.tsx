import MobileSearchModal from '@/components/MobileSearchModal';
import SearchForm from '@/components/search-form';
import HikvisionAccessEventTable from '@/components/worker/HikvisionAccessEvent-table';
import SalaryPaymentTable from '@/components/worker/salary-payment-table';
import SalaryTable from '@/components/worker/salary-table';
import WorkerDayTable from '@/components/worker/worker-day-table';
import WorkerHolidayTable from '@/components/worker/worker-holiday-table';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem as LayoutBreadcrumbItem, type Worker, Day, HikvisionAccessEventPaginate, SearchData } from '@/types';
import { format, startOfMonth } from 'date-fns';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import React, { useEffect } from 'react';
import { useTranslation } from 'react-i18next';


export default function WorkerShow() {
    const { worker, hikvision_access_events, days } = usePage<{
        worker: Worker;
        hikvision_access_events: HikvisionAccessEventPaginate;
        days: Day[];
    }>().props;
    const { t } = useTranslation(); // Using the translation hook

    const defaultFrom = format(startOfMonth(new Date()), 'yyyy-MM-dd');
    const defaultTo = format(new Date(), 'yyyy-MM-dd');

    const breadcrumbs: LayoutBreadcrumbItem[] = [
        {
            title: `${t('worker')} ( ${worker.name} )`,
            href: '/dashboard',
        },
    ];
    // Form handling for search and per_page
    const { data, setData } = useForm<SearchData>({
        search: '',
        per_page: hikvision_access_events.per_page,
        page: hikvision_access_events.current_page,
        total: hikvision_access_events.total,
        from: defaultFrom,
        to: defaultTo,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(`/worker/${worker.id}`, data); // ✅ Correct for search queries
    };

    useEffect(() => {
        const urlParams = new URLSearchParams(location.search);

        const searchQuery = urlParams.get('search') || ''; // Get 'search' query from the URL
        const fromParam = urlParams.get('from');
        const toParam = urlParams.get('to');

        const from = fromParam && fromParam !== 'null' && fromParam !== '' ? String(fromParam) : defaultFrom;
        const to = toParam && toParam !== 'null' && toParam !== '' ? String(toParam) : defaultTo;

        setData('search', searchQuery); // Set it to the form state
        setData('from', from);
        setData('to', to);
    }, [location.search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('worker')}: ${worker.name}`} />
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
                                    <Link href={`/firm/${worker?.branch?.firm_id}`} className="text-muted-foreground hover:text-primary transition-colors truncate max-w-[120px] sm:max-w-none">
                                        {worker?.branch?.firm?.name}
                                    </Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbLink asChild>
                                    <Link href={`/branch/${worker?.branch?.id}`} className="text-muted-foreground hover:text-primary transition-colors truncate max-w-[120px] sm:max-w-none">
                                        {worker?.branch?.name}
                                    </Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbPage className="font-semibold text-foreground px-2.5 py-1 rounded-md bg-muted text-xs">
                                    {worker?.name}
                                </BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
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
                            <HikvisionAccessEventTable worker={worker} hikvision_access_events={hikvision_access_events} searchData={data} />
                            <SalaryTable worker={worker} />
                            <SalaryPaymentTable worker={worker} />
                        </div>
                        <div className="col-span-12 lg:col-span-4 space-y-6 min-w-0 max-w-full">
                            <WorkerDayTable worker={worker} days={days} />
                            <WorkerHolidayTable worker={worker} />
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

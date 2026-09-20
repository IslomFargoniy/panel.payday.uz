import WorkerMonthlyAttendanceTable from '@/components/attendance/worker-monthly-attendance-table';
import MobileSearchModal from '@/components/MobileSearchModal';
import SearchForm from '@/components/search-form';
import AppLayout from '@/layouts/app-layout';
import { Branch, type BreadcrumbItem, Firm, SearchData, WorkerPaginate } from '@/types';
import { format, startOfMonth } from 'date-fns';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';

export default function MonthlyAttendance() {
    const { worker, firms, branches } = usePage<{
        worker: WorkerPaginate;
        firms: Firm[];
        branches: Branch[];
    }>().props;
    const { t } = useTranslation(); // Using the translation hook

    const defaultFrom = format(startOfMonth(new Date()), 'yyyy-MM-dd');
    const defaultTo = format(new Date(), 'yyyy-MM-dd');

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: `${t('sidebar.report')}`,
            href: '/dashboard',
        },
    ];
    // Form handling for search and per_page
    const { data, setData } = useForm<SearchData>({
        search: '',
        per_page: worker.per_page,
        page: worker.current_page,
        total: worker.total,
        firm_id: 0,
        branch_id: 0,
        from: defaultFrom,
        to: defaultTo,
    });

    console.log(worker.data);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(`/monthly_attendance`, data); // ✅ Correct for search queries
    };

    useEffect(() => {
        const urlParams = new URLSearchParams(location.search);
        const getTrimmed = (key: string) => {
            const value = urlParams.get(key);
            return value && value !== 'null' ? value.trim() : '';
        };

        const searchQuery = urlParams.get('search') || ''; // Get 'search' query from the URL
        setData('search', searchQuery); // Set it to the form state

        const firmIdStr = urlParams.get('firm_id') ?? '0';
        const firm_id = parseInt(firmIdStr);
        setData('firm_id', firm_id);

        const branchIdStr = urlParams.get('branch_id') ?? '0';
        const branch_id = parseInt(branchIdStr);
        setData('branch_id', branch_id);

        setData('from', getTrimmed('from') || defaultFrom);
        setData('to', getTrimmed('to') || defaultTo);
    }, [location.search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('sidebar.report')} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 md:p-6 min-w-0 max-w-full">
                {/* Search and Per-Page Selection */}
                <div className="flex items-center justify-end w-full min-w-0">
                    <MobileSearchModal data={data} setData={setData} handleSubmit={handleSubmit} firms={firms} branches={branches} />
                    <div className="hidden lg:block w-full max-w-full">
                        <SearchForm handleSubmit={handleSubmit} setData={setData} data={data} firms={firms} branches={branches} />
                    </div>
                </div>

                {/* Table */}
                <div className="w-full min-w-0 max-w-full">{worker && <WorkerMonthlyAttendanceTable worker={worker} searchData={data} />}</div>
            </div>
        </AppLayout>
    );
}

import AppLayout from '@/layouts/app-layout';
import { Head, usePage, useForm } from '@inertiajs/react';
import { Branch, type BreadcrumbItem, Firm, type SalaryPaymentPaginate, SearchData, Worker } from '@/types';
import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import SalaryPaymentTable from '@/components/salary_payment/salary_payment-table';
import SearchForm from '@/components/search-form';
import { useTranslation } from 'react-i18next';
import MobileSearchModal from '@/components/MobileSearchModal';

export default function SalaryPayment() {
    const {
        salary_payment,
        workers,
        firms,
        branches

    } = usePage<{
        salary_payment: SalaryPaymentPaginate,
        workers: Worker[],
        firms: Firm[],
        branches: Branch[]
    }>().props;
    const { t } = useTranslation();  // Using the translation hook

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('salary_payment'),
            href: '/dashboard'
        }
    ];

    // Form handling for search and per_page
    const { data, setData } = useForm<SearchData>({
        search: '',
        per_page: salary_payment.per_page,
        page: salary_payment.current_page,
        total: salary_payment.total,
        worker_id: 0,
        firm_id: 0,
        branch_id: 0
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/salary_payment', data); // ✅ Correct for search queries
    };


    useEffect(() => {
        const urlParams = new URLSearchParams(location.search);

        const searchQuery = urlParams.get('search') || ''; // Get 'search' query from the URL
        setData('search', searchQuery); // Set it to the form state

        const workerIdStr = urlParams.get('worker_id') ?? '0';
        const worker_id = parseInt(workerIdStr);
        setData('worker_id', worker_id);

        const firmIdStr = urlParams.get('firm_id') ?? '0';
        const firm_id = parseInt(firmIdStr);
        setData('firm_id', firm_id);

        const branchIdStr = urlParams.get('branch_id') ?? '0';
        const branch_id = parseInt(branchIdStr);
        setData('branch_id', branch_id);

    }, [location.search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('sidebar.salary_payment')} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 md:p-6 min-w-0 max-w-full">
                {/* Search and Per-Page Selection */}
                <div className="flex justify-end items-center w-full min-w-0">
                    <MobileSearchModal
                        data={data}
                        setData={setData}
                        handleSubmit={handleSubmit}
                        workers={workers}
                        firms={firms}
                        branches={branches}
                    />
                    <div className="hidden lg:block w-full max-w-full">
                        <SearchForm
                            handleSubmit={handleSubmit}
                            setData={setData}
                            data={data}
                            workers={workers}
                            firms={firms}
                            branches={branches}
                        />
                    </div>
                </div>

                {/* Table */}
                <div className="w-full min-w-0 max-w-full">
                    <SalaryPaymentTable
                        {...salary_payment}
                        searchData={data}
                        workers={workers}
                    />
                </div>
            </div>
        </AppLayout>
    );
}

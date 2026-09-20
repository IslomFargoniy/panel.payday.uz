import AppLayout from '@/layouts/app-layout';
import { Head, usePage, useForm } from '@inertiajs/react';
import { Branch, type BreadcrumbItem, Firm, type SalaryPaginate, SearchData } from '@/types';
import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import SalaryTable from '@/components/salary/salary-table';
import SearchForm from '@/components/search-form';
import { useTranslation } from 'react-i18next';
import MobileSearchModal from '@/components/MobileSearchModal';

export default function Salary() {
    const {
        salary,
        firms,
        branches
    } = usePage<{
        salary: SalaryPaginate,
        firms: Firm[],
        branches: Branch[]
    }>().props;
    const { t } = useTranslation();  // Using the translation hook

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('salary'),
            href: '/dashboard'
        }
    ];

    // Form handling for search and per_page
    const { data, setData } = useForm<SearchData>({
        search: '',
        per_page: salary.per_page,
        page: salary.current_page,
        total: salary.total,
        firm_id: 0,
        branch_id: 0
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/salary', data); // ✅ Correct for search queries
    };


    useEffect(() => {
        const urlParams = new URLSearchParams(location.search);
        const searchQuery = urlParams.get('search') || ''; // Get 'search' query from the URL
        setData('search', searchQuery); // Set it to the form state

        const firmIdStr = urlParams.get('firm_id') ?? '0';
        const firm_id = parseInt(firmIdStr);
        setData('firm_id', firm_id);

        const branchIdStr = urlParams.get('branch_id') ?? '0';
        const branch_id = parseInt(branchIdStr);
        setData('branch_id', branch_id);
    }, [location.search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('sidebar.salary')} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 md:p-6 min-w-0 max-w-full">
                {/* Search and Per-Page Selection */}
                <div className="flex justify-end items-center w-full min-w-0">
                    <MobileSearchModal
                        data={data}
                        setData={setData}
                        handleSubmit={handleSubmit}
                        firms={firms}
                        branches={branches}
                    />
                    <div className="hidden lg:block w-full max-w-full">
                        <SearchForm
                            handleSubmit={handleSubmit}
                            setData={setData}
                            data={data}
                            firms={firms}
                            branches={branches}
                        />
                    </div>
                </div>

                {/* Table */}
                <div className="w-full min-w-0 max-w-full">
                    <SalaryTable {...salary} searchData={data} />
                </div>
            </div>
        </AppLayout>
    );
}

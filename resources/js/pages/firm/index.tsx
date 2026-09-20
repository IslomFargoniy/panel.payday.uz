import AppLayout from '@/layouts/app-layout';
import { Head, usePage, useForm } from '@inertiajs/react';
import { type BreadcrumbItem, type FirmPaginate, SearchData } from '@/types';
import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import FirmTable from '@/components/firm/firm-table';
import SearchForm from '@/components/search-form';
import { useTranslation } from 'react-i18next';
import MobileSearchModal from '@/components/MobileSearchModal';

export default function Firm() {
    const { firm } = usePage<{ firm: FirmPaginate }>().props;
    const { t } = useTranslation();  // Using the translation hook

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('firm'),
            href: '/dashboard'
        }
    ];

    // Form handling for search and per_page
    const { data, setData } = useForm<SearchData>({
        search: '',
        per_page: firm.per_page,
        page: firm.current_page,
        total: firm.total
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/firm', data); // ✅ Correct for search queries
    };


    useEffect(() => {
        const urlParams = new URLSearchParams(location.search);
        const searchQuery = urlParams.get('search') || ''; // Get 'search' query from the URL
        setData('search', searchQuery); // Set it to the form state
    }, [location.search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('sidebar.firm')} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 md:p-6 min-w-0 max-w-full">
                {/* Search and Per-Page Selection */}
                <div className="flex justify-end items-center w-full min-w-0">
                    <MobileSearchModal
                        data={data}
                        setData={setData}
                        handleSubmit={handleSubmit}
                    />
                    <div className="hidden lg:block w-full max-w-full">
                        <SearchForm handleSubmit={handleSubmit} setData={setData} data={data} />
                    </div>
                </div>

                {/* Table */}
                <div className="w-full min-w-0 max-w-full">
                    <FirmTable {...firm} searchData={data} />
                </div>
            </div>
        </AppLayout>
    );
}

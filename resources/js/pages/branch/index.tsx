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
import { type BreadcrumbItem as LayoutBreadcrumbItem, Firm, SearchData } from '@/types';
import { Building2 } from 'lucide-react';
import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import BranchTable from '@/components/branch/branch-table';
import SearchForm from '@/components/search-form';
import FirmHolidayTable from '@/components/firm/firm-holiday-table';
import { useTranslation } from 'react-i18next';
import MobileSearchModal from '@/components/MobileSearchModal';


export default function Branch() {
    const { firm } = usePage<{ firm: Firm }>().props;
    const { t } = useTranslation();  // Using the translation hook

    const breadcrumbs: LayoutBreadcrumbItem[] = [
        {
            title: `${t('branch')} ( ${firm.name} )`,
            href: '/dashboard'
        }
    ];
    // Form handling for search and per_page
    const { data, setData } = useForm<SearchData>({
        search: ''
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(`/firm/${firm.id}`, data); // ✅ Correct for search queries
    };


    useEffect(() => {
        const urlParams = new URLSearchParams(location.search);
        const searchQuery = urlParams.get('search') || ''; // Get 'search' query from the URL
        setData('search', searchQuery); // Set it to the form state
    }, [location.search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('branch')} ${firm.name}`} />
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
                                <BreadcrumbPage className="font-semibold text-foreground px-2.5 py-1 rounded-md bg-muted text-xs">
                                    {firm.name}
                                </BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                    <div className="flex items-center gap-2 min-w-0">
                        <MobileSearchModal
                            data={data}
                            setData={setData}
                            handleSubmit={handleSubmit}
                        />
                        <div className="hidden lg:block">
                            <SearchForm handleSubmit={handleSubmit} setData={setData} data={data} />
                        </div>
                    </div>
                </div>

                {/* Table */}
                <div className="w-full min-w-0 max-w-full">
                    <div className="grid grid-cols-12 gap-6 min-w-0 max-w-full">
                        <div className="col-span-12 lg:col-span-8 min-w-0 max-w-full">
                            <BranchTable firm={firm} />
                        </div>
                        <div className="col-span-12 lg:col-span-4 min-w-0 max-w-full">
                            <FirmHolidayTable firm={firm} />
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

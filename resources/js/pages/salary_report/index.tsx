import AttendanceTable from '@/components/salary_report/attendance-table';
import AppLayout from '@/layouts/app-layout';
import {
    type AttendancePaginate,
    Branch,
    type BreadcrumbItem,
    Firm,
    Report as ReportType,
    SearchData, Worker
} from '@/types';
import { format, startOfMonth } from 'date-fns';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import React, { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import RightBar from '@/components/salary_report/right-bar';
import CalculateSalary from '@/components/salary_report/calculate-salary';
import SearchForm from '@/components/search-form';
import MobileSearchModal from '@/components/MobileSearchModal';

export default function SalaryReport() {
    const {
        attendance, report,
        firms,
        branches,
        workers
    } = usePage<{
        attendance: AttendancePaginate;
        report: ReportType;
        firms: Firm[];
        branches: Branch[];
        workers: Worker[];
    }>().props;
    const { t } = useTranslation(); // Using the translation hook

    const defaultFrom = format(startOfMonth(new Date()), 'yyyy-MM-dd');
    const defaultTo = format(new Date(), 'yyyy-MM-dd');

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('sidebar.salary_report'),
            href: '/dashboard'
        }
    ];

    // Form handling for search and per_page
    const { data, setData } = useForm<SearchData>({
        search: '',
        per_page: attendance.per_page,
        page: attendance.current_page,
        total: attendance.total,
        worker_id: 0,
        branch_id: 0,
        firm_id: 0,
        from: defaultFrom,
        to: defaultTo
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/salary_report', data); // ✅ Correct for search queries
    };

    useEffect(() => {
        const urlParams = new URLSearchParams(location.search);

        const getTrimmed = (key: string) => {
            const value = urlParams.get(key);
            return value && value !== 'null' ? value.trim() : '';
        };

        setData('search', getTrimmed('search'));
        setData('worker_id', Number(urlParams.get('worker_id')) || 0);
        setData('branch_id', Number(urlParams.get('branch_id')) || 0);
        setData('firm_id', Number(urlParams.get('firm_id')) || 0);
        setData('from', getTrimmed('from') || defaultFrom);
        setData('to', getTrimmed('to') || defaultTo);
    }, [location.search, setData]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('sidebar.salary_report')} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Search and Per-Page Selection */}
                <div className="flex items-center justify-end">
                    <MobileSearchModal
                        handleSubmit={handleSubmit}
                        setData={setData}
                        data={data}
                        firms={firms}
                        branches={branches}
                        workers={workers}
                    />
                    <div className={'hidden lg:block'}>
                        <SearchForm handleSubmit={handleSubmit}
                                    setData={setData}
                                    data={data}
                                    firms={firms}
                                    branches={branches}
                                    workers={workers}
                        />
                    </div>
                </div>
                <div className="pt-1">
                    <div className="grid grid-cols-12 gap-6">
                        <div className="col-span-12 lg:col-span-9 space-y-6">
                            <AttendanceTable {...attendance} searchData={data} />
                        </div>
                        <div className="col-span-12 lg:col-span-3 space-y-6">
                            <RightBar {...report} />
                            {(data.worker_id && report.from && report.to)
                                ? <CalculateSalary report={report} search_data={data} />
                                : null
                            }
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

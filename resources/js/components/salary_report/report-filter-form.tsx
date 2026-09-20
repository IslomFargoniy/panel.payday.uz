import React from 'react';
import { Branch, Firm, SearchData, Worker } from '@/types';
import { Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';
import DatePicker from 'react-datepicker';
import { Button } from '@/components/ui/button';

interface ReportFilterFormProps {
    handleSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    setData: <K extends keyof SearchData>(key: K, value: SearchData[K]) => void;
    data: SearchData;
    firms: Firm[];
    branches: Branch[];
    workers: Worker[];
}

const parseDate = (val?: string | null) => {
    if (!val || val === 'null' || val === 'undefined' || val === '') return null;
    const dateStr = val.includes('T') ? val : `${val}T00:00:00`;
    const d = new Date(dateStr);
    return isNaN(d.getTime()) ? null : d;
};

const ReportFilterForm = ({
    handleSubmit,
    setData,
    data,
    firms,
    branches,
    workers
}: ReportFilterFormProps) => {
    const { t } = useTranslation();

    const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
        setData('search', e.target.value);
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('per_page', parseInt(e.target.value, 10));
    };

    const handleFirmChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('firm_id', e.target.value ? parseInt(e.target.value, 10) : undefined);
    };

    const handleBranchChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('branch_id', e.target.value ? parseInt(e.target.value, 10) : undefined);
    };

    const handleWorkerChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('worker_id', e.target.value ? parseInt(e.target.value, 10) : undefined);
    };

    return (
        <form onSubmit={handleSubmit} className="w-full">
            <div className="flex flex-wrap items-center justify-start sm:justify-end gap-1.5 w-full max-w-full" role="group">
                {/* Search Bar */}
                <input
                    type="text"
                    value={data.search}
                    onChange={handleSearch}
                    className="h-9 w-full sm:w-auto min-w-[140px] rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    placeholder={t('search')}
                />

                {typeof data.total === 'number' && (
                    <select
                        value={data.per_page}
                        onChange={handlePerPageChange}
                        className="h-9 w-full sm:w-auto rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        <option value={15}>15</option>
                        <option value={30}>30</option>
                        <option value={50}>50</option>
                        <option value={data.total}>{t('pagination_optionAll')}</option>
                    </select>
                )}

                {(typeof data.from === 'string' || typeof data.to === 'string') && (
                    <div className="w-full sm:w-auto">
                        <DatePicker
                            selectsRange={true}
                            startDate={parseDate(data.from)}
                            endDate={parseDate(data.to)}
                            onChange={(update: [Date | null, Date | null] | null) => {
                                const [start, end] = update ?? [null, null];
                                setData('from', start ? format(start, 'yyyy-MM-dd') : '');
                                setData('to', end ? format(end, 'yyyy-MM-dd') : '');
                            }}
                            isClearable={true}
                            dateFormat="yyyy-MM-dd"
                            placeholderText={`${t('from')} — ${t('to')}`}
                            className="h-9 w-full sm:w-64 md:w-72 rounded-xl border border-slate-200 bg-white pl-3 pr-8 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer"
                        />
                    </div>
                )}

                {/* Firm Select */}
                <select
                    value={data.firm_id || ''}
                    onChange={handleFirmChange}
                    className="h-9 w-full sm:w-auto rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                    <option value="">{t('select_firm')}</option>
                    {firms.map((firm) => (
                        <option key={firm.id} value={firm.id}>
                            {firm.name}
                        </option>
                    ))}
                </select>

                {/* Branch Select */}
                <select
                    value={data.branch_id || ''}
                    onChange={handleBranchChange}
                    className="h-9 w-full sm:w-auto rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                    <option value="">{t('select_branch')}</option>
                    {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                            {branch.name}
                        </option>
                    ))}
                </select>

                {/* Worker Select */}
                <select
                    value={data.worker_id || ''}
                    onChange={handleWorkerChange}
                    className="h-9 w-full sm:w-auto rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                    <option value="">{t('select_worker')}</option>
                    {workers.map((worker) => (
                        <option key={worker.id} value={worker.id}>
                            {worker.name}
                        </option>
                    ))}
                </select>

                {/* Submit button */}
                <Button
                    type="submit"
                    className="h-9 w-full sm:w-auto gap-1.5 rounded-xl bg-indigo-600 px-3.5 text-xs font-medium text-white shadow-xs hover:bg-indigo-700 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                >
                    <Search className="h-4 w-4" />
                    <span>{t('search')}</span>
                </Button>
            </div>
        </form>
    );
};

export default ReportFilterForm;


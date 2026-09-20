import { Branch, Firm, SearchData, Worker } from '@/types';
import { format } from 'date-fns';
import { Search } from 'lucide-react';
import React, { useEffect } from 'react';
import DatePicker from 'react-datepicker';
import { MaskedDateInput } from '@/components/ui/masked-date-input';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

interface SearchFormProps {
    handleSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    setData: <K extends keyof SearchData>(key: K, value: SearchData[K]) => void;
    data: SearchData;
    workers?: Worker[];
    firms?: Firm[];
    branches?: Branch[];
    className?: string;
    isModal?: boolean;
}

const parseDate = (val?: string | null) => {
    if (!val || val === 'null' || val === 'undefined' || val === '') return null;
    const dateStr = val.includes('T') ? val : `${val}T00:00:00`;
    const d = new Date(dateStr);
    return isNaN(d.getTime()) ? null : d;
};

const SearchForm = ({ handleSubmit, setData, data, workers, firms, branches, className, isModal = false }: SearchFormProps) => {
    const { t } = useTranslation(); // Hook to access translations

    const [filteredBranches, setBranches] = React.useState<Branch[] | undefined>(branches);

    const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
        setData('search', e.target.value);
    };

    const handleMonth = (e: React.ChangeEvent<HTMLInputElement>) => {
        setData('month', e.target.value);
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('per_page', parseInt(e.target.value, 10)); // parse as number
    };

    const handleWorkerChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('worker_id', parseInt(e.target.value, 10)); // parse as number
    };

    const handleFirmChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const firm_id = parseInt(e.target.value, 10); // parse as number
        setData('firm_id', firm_id); // parse as number

        if (firm_id) {
            setBranches(branches?.filter((branch) => branch.firm_id === firm_id));
        } else {
            setBranches(branches);
        }
    };

    const handleBranchChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('branch_id', parseInt(e.target.value, 10)); // parse as number
    };

    useEffect(() => {
        if (data.firm_id) {
            setBranches(branches?.filter((branch) => branch.firm_id === data.firm_id));
        } else {
            setBranches(branches);
        }
    }, [data, setBranches, branches]);

    if (isModal) {
        return (
            <form onSubmit={handleSubmit} className="flex flex-col gap-3.5 w-full">
                {/* Search Bar */}
                <div className="space-y-1">
                    <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">{t('search')}</label>
                    <input
                        type="text"
                        value={data.search}
                        onChange={handleSearch}
                        className="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                        placeholder={t('search')}
                    />
                </div>

                {/* Date Range (from - to) */}
                {(typeof data.from === 'string' || typeof data.to === 'string') && (
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">{t('date')} ({t('from')} — {t('to')})</label>
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
                            wrapperClassName="w-full"
                            className="h-10 w-full rounded-xl border border-slate-200 bg-white pl-3 pr-8 py-2 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 cursor-pointer"
                        />
                    </div>
                )}

                {/* Month */}
                {typeof data.month === 'string' && (
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">{t('month')}</label>
                        <input
                            type="month"
                            value={data.month}
                            max={format(new Date(), 'yyyy-MM')}
                            onChange={handleMonth}
                            className="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                            placeholder={t('month')}
                        />
                    </div>
                )}

                {/* Single Date */}
                {typeof data.date === 'string' && (
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">{t('date')}</label>
                        <DatePicker
                            id="date"
                            placeholderText={t('date')}
                            selected={parseDate(data.date)}
                            onChange={(date) => {
                                setData('date', date ? format(date, 'yyyy-MM-dd') : '');
                            }}
                            isClearable={true}
                            dateFormat="yyyy-MM-dd"
                            wrapperClassName="w-full"
                            customInput={
                                <MaskedDateInput
                                    className="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 cursor-pointer"
                                    placeholder={t('date')}
                                />
                            }
                        />
                    </div>
                )}

                {/* Firms & Branches Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {firms && (
                        <div className="space-y-1">
                            <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">{t('firm')}</label>
                            <select
                                value={data.firm_id || ''}
                                onChange={handleFirmChange}
                                className="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                            >
                                <option value="0">{t('firm')}</option>
                                {firms.map((firm) => (
                                    <option key={firm.id} value={firm.id}>
                                        {firm.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {filteredBranches && (
                        <div className="space-y-1">
                            <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">{t('branch')}</label>
                            <select
                                value={data.branch_id || ''}
                                onChange={handleBranchChange}
                                className="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                            >
                                <option value="0">{t('branch')}</option>
                                {filteredBranches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>

                {/* Workers */}
                {workers && (
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">{t('worker')}</label>
                        <select
                            value={data.worker_id || 0}
                            onChange={handleWorkerChange}
                            className="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                        >
                            <option value="0">{t('worker')}</option>
                            {workers.map((worker) => (
                                <option key={worker.id} value={worker.id}>
                                    {worker.name}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                {/* Per Page */}
                {typeof data.total === 'number' && (
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">{t('pagination_optionAll') || 'Qatorlar soni'}</label>
                        <select
                            value={data.per_page}
                            onChange={handlePerPageChange}
                            className="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                        >
                            <option value={15}>15</option>
                            <option value={30}>30</option>
                            <option value={50}>50</option>
                            <option value={data.total}>{t('pagination_optionAll')}</option>
                        </select>
                    </div>
                )}

                {/* Submit Button */}
                <Button
                    type="submit"
                    className="mt-2 h-10 w-full gap-2 rounded-xl bg-indigo-600 px-4 text-xs font-semibold text-white shadow-xs hover:bg-indigo-700 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                >
                    <Search className="h-4 w-4" />
                    <span>{t('search')}</span>
                </Button>
            </form>
        );
    }

    return (
        <form onSubmit={handleSubmit} className={className || 'w-auto'}>
            <div className="flex flex-wrap items-center justify-end gap-1.5 w-full max-w-full" role="group">
                {/* Search Bar */}
                <input
                    type="text"
                    value={data.search}
                    onChange={handleSearch}
                    className="h-9 w-36 sm:w-44 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    placeholder={t('search')}
                />

                {typeof data.total === 'number' && (
                    <select
                        value={data.per_page}
                        onChange={handlePerPageChange}
                        className="h-9 w-auto rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        <option value={15}>15</option>
                        <option value={30}>30</option>
                        <option value={50}>50</option>
                        <option value={data.total}>{t('pagination_optionAll')}</option>
                    </select>
                )}

                {(typeof data.from === 'string' || typeof data.to === 'string') && (
                    <div className="w-[196px] min-w-[196px]">
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
                            wrapperClassName="w-[196px]"
                            className="h-9 w-[196px] min-w-[196px] rounded-xl border border-slate-200 bg-white pl-2.5 pr-5.5 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer"
                        />
                    </div>
                )}

                {typeof data.month === 'string' && (
                    <input
                        type="month"
                        value={data.month}
                        max={format(new Date(), 'yyyy-MM')}
                        onChange={handleMonth}
                        className="h-9 w-auto rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        placeholder={t('month')}
                    />
                )}

                {typeof data.date === 'string' && (
                    <div className="w-auto">
                        <DatePicker
                            id="date"
                            placeholderText={t('date')}
                            selected={parseDate(data.date)}
                            onChange={(date) => {
                                setData('date', date ? format(date, 'yyyy-MM-dd') : '');
                            }}
                            isClearable={true}
                            dateFormat="yyyy-MM-dd"
                            customInput={
                                <MaskedDateInput
                                    className="h-9 w-36 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer"
                                    placeholder={t('date')}
                                />
                            }
                        />
                    </div>
                )}

                {firms && (
                    <select
                        value={data.firm_id || ''}
                        onChange={handleFirmChange}
                        className="h-9 w-auto rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        <option value="0">{t('firm')}</option>
                        {firms.map((firm) => (
                            <option key={firm.id} value={firm.id}>
                                {firm.name}
                            </option>
                        ))}
                    </select>
                )}

                {filteredBranches && (
                    <select
                        value={data.branch_id || ''}
                        onChange={handleBranchChange}
                        className="h-9 w-auto rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        <option value="0">{t('branch')}</option>
                        {filteredBranches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                )}

                {workers && (
                    <select
                        value={data.worker_id || 0}
                        onChange={handleWorkerChange}
                        className="h-9 w-auto rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        <option value="0">{t('worker')}</option>
                        {workers.map((worker) => (
                            <option key={worker.id} value={worker.id}>
                                {worker.name}
                            </option>
                        ))}
                    </select>
                )}

                <Button
                    type="submit"
                    className="h-9 w-auto gap-1.5 rounded-xl bg-indigo-600 px-3.5 text-xs font-medium text-white shadow-xs hover:bg-indigo-700 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                >
                    <Search className="h-4 w-4" />
                    <span>{t('search')}</span>
                </Button>
            </div>
        </form>
    );
};

export default SearchForm;

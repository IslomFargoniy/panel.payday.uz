import { Branch, Firm, SearchData, Worker } from '@/types';
import { format } from 'date-fns';
import { Search } from 'lucide-react';
import React, { useEffect } from 'react';
import DatePicker from 'react-datepicker';
import { MaskedDateInput } from '@/components/ui/masked-date-input';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import SearchableSelect from '@/components/ui/searchable-select';

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
    const formRef = React.useRef<HTMLFormElement>(null);
    const shouldAutoSubmitRef = React.useRef(false);

    const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
        setData('search', e.target.value);
    };

    const handleMonth = (e: React.ChangeEvent<HTMLInputElement>) => {
        setData('month', e.target.value);
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('per_page', parseInt(e.target.value, 10)); // parse as number
        shouldAutoSubmitRef.current = true;
    };

    const handleWorkerChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('worker_id', parseInt(e.target.value, 10)); // parse as number
        shouldAutoSubmitRef.current = true;
    };

    const handleFirmChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const firm_id = parseInt(e.target.value, 10); // parse as number
        setData('firm_id', firm_id); // parse as number

        if (firm_id) {
            setBranches(branches?.filter((branch) => branch.firm_id === firm_id));
        } else {
            setBranches(branches);
        }

        if (data.branch_id) {
            setData('branch_id', 0);
        }
        shouldAutoSubmitRef.current = true;
    };

    const handleBranchChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('branch_id', parseInt(e.target.value, 10)); // parse as number
        shouldAutoSubmitRef.current = true;
    };

    useEffect(() => {
        if (data.firm_id) {
            setBranches(branches?.filter((branch) => branch.firm_id === data.firm_id));
        } else {
            setBranches(branches);
        }
    }, [data.firm_id, branches]);

    useEffect(() => {
        if (shouldAutoSubmitRef.current) {
            shouldAutoSubmitRef.current = false;
            if (formRef.current) {
                if (typeof formRef.current.requestSubmit === 'function') {
                    formRef.current.requestSubmit();
                } else {
                    formRef.current.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
            }
        }
    }, [data]);

    if (isModal) {
        return (
            <form ref={formRef} onSubmit={handleSubmit} className="flex flex-col gap-3.5 w-full">
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
                            <SearchableSelect
                                value={data.firm_id}
                                onChange={(val) => {
                                    const firm_id = Number(val) || 0;
                                    setData('firm_id', firm_id);
                                    if (firm_id) {
                                        setBranches(branches?.filter((branch) => branch.firm_id === firm_id));
                                    } else {
                                        setBranches(branches);
                                    }
                                    if (data.branch_id) {
                                        setData('branch_id', 0);
                                    }
                                    shouldAutoSubmitRef.current = true;
                                }}
                                options={firms.map((f) => ({ value: f.id, label: f.name }))}
                                placeholder={t('firm')}
                                emptyOptionLabel={t('firm')}
                                triggerClassName="h-10 rounded-xl"
                                allowClear
                            />
                        </div>
                    )}

                    {filteredBranches && (
                        <div className="space-y-1">
                            <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">{t('branch')}</label>
                            <SearchableSelect
                                value={data.branch_id}
                                onChange={(val) => {
                                    setData('branch_id', Number(val) || 0);
                                    shouldAutoSubmitRef.current = true;
                                }}
                                options={filteredBranches.map((b) => ({ value: b.id, label: b.name }))}
                                placeholder={t('branch')}
                                emptyOptionLabel={t('branch')}
                                triggerClassName="h-10 rounded-xl"
                                allowClear
                            />
                        </div>
                    )}
                </div>

                {/* Workers */}
                {workers && (
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">{t('worker')}</label>
                        <SearchableSelect
                            value={data.worker_id}
                            onChange={(val) => {
                                setData('worker_id', Number(val) || 0);
                                shouldAutoSubmitRef.current = true;
                            }}
                            options={workers.map((w) => ({ value: w.id, label: w.name, sublabel: w.phone || undefined }))}
                            placeholder={t('worker')}
                            emptyOptionLabel={t('worker')}
                            triggerClassName="h-10 rounded-xl"
                            allowClear
                        />
                    </div>
                )}

                {/* Per Page */}
                {typeof data.total === 'number' && (
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">{t('pagination_optionAll') || 'Qatorlar soni'}</label>
                        <SearchableSelect
                            value={data.per_page}
                            onChange={(val) => {
                                setData('per_page', Number(val));
                                shouldAutoSubmitRef.current = true;
                            }}
                            options={[
                                { value: 15, label: '15' },
                                { value: 30, label: '30' },
                                { value: 50, label: '50' },
                                { value: data.total, label: t('pagination_optionAll') || 'Barchasi' },
                            ]}
                            triggerClassName="h-10 rounded-xl"
                        />
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
        <form ref={formRef} onSubmit={handleSubmit} className={className || 'w-auto'}>
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
                    <SearchableSelect
                        value={data.per_page}
                        onChange={(val) => {
                            setData('per_page', Number(val));
                            shouldAutoSubmitRef.current = true;
                        }}
                        options={[
                            { value: 15, label: '15' },
                            { value: 30, label: '30' },
                            { value: 50, label: '50' },
                            { value: data.total, label: t('pagination_optionAll') || 'Barchasi' },
                        ]}
                        className="w-20"
                    />
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
                    <SearchableSelect
                        value={data.firm_id}
                        onChange={(val) => {
                            const firm_id = Number(val) || 0;
                            setData('firm_id', firm_id);
                            if (firm_id) {
                                setBranches(branches?.filter((b) => b.firm_id === firm_id));
                            } else {
                                setBranches(branches);
                            }
                            if (data.branch_id) {
                                setData('branch_id', 0);
                            }
                            shouldAutoSubmitRef.current = true;
                        }}
                        options={firms.map((f) => ({ value: f.id, label: f.name }))}
                        placeholder={t('firm')}
                        emptyOptionLabel={t('firm')}
                        className="w-36 sm:w-44"
                        allowClear
                    />
                )}

                {filteredBranches && (
                    <SearchableSelect
                        value={data.branch_id}
                        onChange={(val) => {
                            setData('branch_id', Number(val) || 0);
                            shouldAutoSubmitRef.current = true;
                        }}
                        options={filteredBranches.map((b) => ({ value: b.id, label: b.name }))}
                        placeholder={t('branch')}
                        emptyOptionLabel={t('branch')}
                        className="w-36 sm:w-44"
                        allowClear
                    />
                )}

                {workers && (
                    <SearchableSelect
                        value={data.worker_id}
                        onChange={(val) => {
                            setData('worker_id', Number(val) || 0);
                            shouldAutoSubmitRef.current = true;
                        }}
                        options={workers.map((w) => ({ value: w.id, label: w.name, sublabel: w.phone || undefined }))}
                        placeholder={t('worker')}
                        emptyOptionLabel={t('worker')}
                        className="w-40 sm:w-48"
                        allowClear
                    />
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

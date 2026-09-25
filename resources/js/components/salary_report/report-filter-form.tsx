import React from 'react';
import { Branch, Firm, SearchData, Worker } from '@/types';
import { Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';
import DatePicker from 'react-datepicker';
import { Button } from '@/components/ui/button';
import SearchableSelect from '@/components/ui/searchable-select';

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
    const formRef = React.useRef<HTMLFormElement>(null);
    const shouldAutoSubmitRef = React.useRef(false);

    const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
        setData('search', e.target.value);
    };

    const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('per_page', parseInt(e.target.value, 10));
        shouldAutoSubmitRef.current = true;
    };

    const handleFirmChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('firm_id', e.target.value ? parseInt(e.target.value, 10) : undefined);
        if (data.branch_id) {
            setData('branch_id', undefined);
        }
        shouldAutoSubmitRef.current = true;
    };

    const handleBranchChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('branch_id', e.target.value ? parseInt(e.target.value, 10) : undefined);
        shouldAutoSubmitRef.current = true;
    };

    const handleWorkerChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('worker_id', e.target.value ? parseInt(e.target.value, 10) : undefined);
        shouldAutoSubmitRef.current = true;
    };

    React.useEffect(() => {
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

    return (
        <form ref={formRef} onSubmit={handleSubmit} className="w-full">
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
                        className="w-full sm:w-20"
                    />
                )}

                {(typeof data.from === 'string' || typeof data.to === 'string') && (
                    <div className="w-full sm:w-[196px]">
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
                            wrapperClassName="w-full sm:w-[196px]"
                            className="h-9 w-full sm:w-[196px] sm:min-w-[196px] rounded-xl border border-slate-200 bg-white pl-2.5 pr-5.5 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer"
                        />
                    </div>
                )}

                {/* Firm Select */}
                <SearchableSelect
                    value={data.firm_id}
                    onChange={(val) => {
                        const fid = val ? Number(val) : undefined;
                        setData('firm_id', fid);
                        if (data.branch_id) {
                            setData('branch_id', undefined);
                        }
                        shouldAutoSubmitRef.current = true;
                    }}
                    options={firms.map((firm) => ({ value: firm.id, label: firm.name }))}
                    placeholder={t('select_firm')}
                    emptyOptionLabel={t('select_firm')}
                    className="w-full sm:w-44"
                    allowClear
                />

                {/* Branch Select */}
                <SearchableSelect
                    value={data.branch_id}
                    onChange={(val) => {
                        setData('branch_id', val ? Number(val) : undefined);
                        shouldAutoSubmitRef.current = true;
                    }}
                    options={branches.map((branch) => ({ value: branch.id, label: branch.name }))}
                    placeholder={t('select_branch')}
                    emptyOptionLabel={t('select_branch')}
                    className="w-full sm:w-44"
                    allowClear
                />

                {/* Worker Select */}
                <SearchableSelect
                    value={data.worker_id}
                    onChange={(val) => {
                        setData('worker_id', val ? Number(val) : undefined);
                        shouldAutoSubmitRef.current = true;
                    }}
                    options={workers.map((worker) => ({ value: worker.id, label: worker.name, sublabel: worker.phone || undefined }))}
                    placeholder={t('select_worker')}
                    emptyOptionLabel={t('select_worker')}
                    className="w-full sm:w-48"
                    allowClear
                />

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


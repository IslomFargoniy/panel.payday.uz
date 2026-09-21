import React from 'react';
import { Branch, Firm, SearchData } from '@/types';
import { Building2, GitBranch, Search, RotateCcw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

interface SearchFormProps {
    handleSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    setData: <K extends keyof SearchData>(key: K, value: SearchData[K]) => void;
    data: SearchData;
    firms?: Firm[];
    branches?: Branch[];
}

const DashboardFilterForm = ({ handleSubmit, setData, data, firms, branches }: SearchFormProps) => {
    const { t } = useTranslation();
    const formRef = React.useRef<HTMLFormElement>(null);
    const shouldAutoSubmitRef = React.useRef(false);

    const handleFirmChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('firm_id', parseInt(e.target.value, 10));
        if (data.branch_id) {
            setData('branch_id', 0);
        }
        shouldAutoSubmitRef.current = true;
    };

    const handleBranchChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setData('branch_id', parseInt(e.target.value, 10));
        shouldAutoSubmitRef.current = true;
    };

    const handleReset = () => {
        setData('firm_id', 0);
        setData('branch_id', 0);
        setData('search', '');
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

    const filteredBranches = React.useMemo(() => {
        if (!branches) return [];
        if (!data.firm_id || data.firm_id === 0) return branches;
        return branches.filter((b) => b.firm_id === data.firm_id);
    }, [branches, data.firm_id]);

    const hasActiveFilters = Boolean(data.firm_id || data.branch_id || data.search);

    return (
        <form ref={formRef} onSubmit={handleSubmit} className="flex flex-wrap items-center gap-2">
            {firms && firms.length > 0 && (
                <div className="relative">
                    <Building2 className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                    <select
                        value={data.firm_id || ''}
                        onChange={handleFirmChange}
                        className="h-9 rounded-xl border border-slate-200 bg-white pl-8 pr-7 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        <option value="0">{t('select_firm')}</option>
                        {firms.map((firm) => (
                            <option key={firm.id} value={firm.id}>
                                {firm.name}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            {branches && branches.length > 0 && (
                <div className="relative">
                    <GitBranch className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                    <select
                        value={data.branch_id || ''}
                        onChange={handleBranchChange}
                        className="h-9 rounded-xl border border-slate-200 bg-white pl-8 pr-7 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        <option value="0">{t('select_branch')}</option>
                        {filteredBranches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            <Button
                type="submit"
                size="sm"
                className="h-9 rounded-xl bg-indigo-600 px-3.5 text-xs font-semibold text-white shadow-2xs hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
            >
                <Search className="mr-1.5 h-3.5 w-3.5" />
                {t('filter')}
            </Button>

            {hasActiveFilters && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={handleReset}
                    className="h-9 rounded-xl px-2.5 text-xs font-medium text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
                >
                    <RotateCcw className="h-3.5 w-3.5" />
                </Button>
            )}
        </form>
    );
};

export default DashboardFilterForm;

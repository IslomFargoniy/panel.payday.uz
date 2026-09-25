import React from 'react';
import { Branch, Firm, SearchData } from '@/types';
import { Building2, GitBranch, Search, RotateCcw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import SearchableSelect from '@/components/ui/searchable-select';

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
                <SearchableSelect
                    value={data.firm_id}
                    onChange={(val) => {
                        const firm_id = Number(val) || 0;
                        setData('firm_id', firm_id);
                        if (data.branch_id) {
                            setData('branch_id', 0);
                        }
                        shouldAutoSubmitRef.current = true;
                    }}
                    options={firms.map((firm) => ({ value: firm.id, label: firm.name }))}
                    placeholder={t('select_firm')}
                    emptyOptionLabel={t('select_firm')}
                    icon={<Building2 className="h-3.5 w-3.5 text-slate-400" />}
                    className="min-w-[160px]"
                    allowClear
                />
            )}

            {branches && branches.length > 0 && (
                <SearchableSelect
                    value={data.branch_id}
                    onChange={(val) => {
                        setData('branch_id', Number(val) || 0);
                        shouldAutoSubmitRef.current = true;
                    }}
                    options={filteredBranches.map((branch) => ({ value: branch.id, label: branch.name }))}
                    placeholder={t('select_branch')}
                    emptyOptionLabel={t('select_branch')}
                    icon={<GitBranch className="h-3.5 w-3.5 text-slate-400" />}
                    className="min-w-[160px]"
                    allowClear
                />
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

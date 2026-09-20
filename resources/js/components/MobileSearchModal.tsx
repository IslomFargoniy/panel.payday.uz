import React, { useState } from 'react';
import { Filter, X } from 'lucide-react';
import { SearchData, Worker, Branch, Firm } from '@/types';
import SearchForm from '@/components/search-form';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

interface Props {
    data: SearchData;
    setData: <K extends keyof SearchData>(key: K, value: SearchData[K]) => void;
    handleSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    workers?: Worker[];
    firms?: Firm[];
    branches?: Branch[];
}

const MobileSearchModal = ({ data, setData, handleSubmit, workers, firms, branches }: Props) => {
    const [isOpen, setIsOpen] = useState(false);
    const { t } = useTranslation();

    return (
        <>
            {/* Button to open modal - only visible on mobile/tablet */}
            <Button
                type="button"
                variant="outline"
                onClick={() => setIsOpen(true)}
                className="lg:hidden h-9 gap-1.5 rounded-xl border-slate-200/80 bg-white px-3 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                <Filter className="h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400" />
                <span>{t('filter')}</span>
            </Button>

            {/* Modal Overlay */}
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs lg:hidden">
                    <div className="relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                        {/* Close Button */}
                        <button
                            type="button"
                            onClick={() => setIsOpen(false)}
                            className="absolute right-3.5 top-3.5 rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        >
                            <X className="h-5 w-5" />
                        </button>

                        <div className="mb-4 flex items-center gap-2 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <Filter className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                            <h2 className="text-base font-bold text-slate-900 dark:text-slate-100">{t('filter')}</h2>
                        </div>

                        {/* Search form */}
                        <SearchForm
                            handleSubmit={(e) => {
                                handleSubmit(e);
                                setIsOpen(false);
                            }}
                            data={data}
                            setData={setData}
                            workers={workers}
                            firms={firms}
                            branches={branches}
                            isModal={true}
                        />
                    </div>
                </div>
            )}
        </>
    );
};

export default MobileSearchModal;

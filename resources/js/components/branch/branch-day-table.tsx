import React, { useState } from 'react';
import { TrashIcon } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Branch, BranchDay, Day } from '@/types';
import DeleteItemModal from '@/components/delete-item-modal';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import CreateBranchDayModal from '@/components/branch/create-branch-day-modal';


type BranchDayTableProps = {
    branch: Branch;
    days: Day[]
};

const BranchDayTable = ({ branch, days }: BranchDayTableProps) => {

    const { i18n, t } = useTranslation();  // Using the translation hook
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedBranchDay, setSelectedBranchDay] = useState<BranchDay | null>(null);

    const handleDeleteClick = (branch_day: BranchDay) => {
        setSelectedBranchDay(branch_day);
        setOpenDelete(true);
    };
    const { delete: deleteBranchDay, reset, errors: deleteError, clearErrors } = useForm();

    const handleDelete = (id: number) => {

        deleteBranchDay(`/branch_day/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setOpenDelete(false); // 🔒 CLOSE MODAL HERE
                toast.success(t('deleted_successfully')); // Success message
            },
            onError: (err) => {
                // Display a friendly error message if available
                const errorMessage = err?.error || t('delete_failed'); // Use fallback error message
                toast.error(errorMessage); // Display error message
            }
        });
    };

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between py-1.5 border-b border-slate-200/80 dark:border-slate-800">
                <h3 className="font-semibold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2 capitalize">
                    <span>{t('branch_day')}</span>
                    <span className="text-xs font-normal px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                        {branch.branch_days?.length || 0} ta
                    </span>
                </h3>

                <CreateBranchDayModal branch={branch} days={days} />
            </div>

            {/* Table Card */}
            <div className="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-3 py-2.5 text-center w-10 font-mono">{t('n')}</th>
                                <th className="px-3 py-2.5">{t('day')}</th>
                                <th className="px-3 py-2.5 text-right w-16">{t('action', 'Amal')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {(!branch.branch_days || branch.branch_days.length === 0) ? (
                                <tr>
                                    <td colSpan={3} className="px-3 py-6 text-center text-slate-400 dark:text-slate-500">
                                        {t('no_days_selected', 'Ish kunlari belgilanmagan')}
                                    </td>
                                </tr>
                            ) : (
                                branch.branch_days.map((item, index) => {
                                    const dayName = i18n.language === 'uz' ? item.day?.name :
                                        i18n.language === 'ru' ? item.day?.name_ru :
                                            item.day?.name_en;
                                    return (
                                        <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-3 py-2.5 text-center font-mono text-slate-400 dark:text-slate-500">{index + 1}</td>
                                            <td className="px-3 py-2.5 font-medium text-slate-900 dark:text-slate-100">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 font-medium">
                                                    {dayName}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2.5 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleDeleteClick(item)}
                                                    className="h-7 w-7 p-0 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400"
                                                    title={t('delete', 'O‘chirish')}
                                                >
                                                    <TrashIcon className="w-3.5 h-3.5" />
                                                </Button>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>

                        {/* Pass selected branch to the DeleteBranchModal */}
                        {selectedBranchDay && openDelete && (
                            <DeleteItemModal
                                item={selectedBranchDay}
                                open={openDelete}
                                setOpen={setOpenDelete}
                                onDelete={handleDelete}
                            />
                        )}
                    </table>
                </div>
            </div>
        </div>
    );
};

export default BranchDayTable;

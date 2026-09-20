import React, { useState } from 'react';
import { Trash2, CalendarDays, Calendar } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Branch, BranchHoliday } from '@/types';
import DeleteItemModal from '@/components/delete-item-modal';
import { toast } from 'sonner';
import CreateBranchHolidayModal from '@/components/branch/create-branch-holiday-modal';

type BranchHolidayTableProps = {
    branch: Branch;
};

const BranchHolidayTable = ({ branch }: BranchHolidayTableProps) => {
    const { t } = useTranslation();
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedBranchHoliday, setSelectedBranchHoliday] = useState<BranchHoliday | null>(null);

    const handleDeleteClick = (holiday: BranchHoliday) => {
        setSelectedBranchHoliday(holiday);
        setOpenDelete(true);
    };

    const { delete: deleteBranchHoliday, reset, clearErrors } = useForm();

    const handleDelete = (id: number) => {
        deleteBranchHoliday(`/branch_holiday/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setOpenDelete(false);
                toast.success(t('deleted_successfully', 'Muvaffaqiyatli o‘chirildi'));
            },
            onError: (err) => {
                const errorMessage = err?.error || t('delete_failed', 'O‘chirishda xatolik yuz berdi');
                toast.error(errorMessage);
            }
        });
    };

    const holidays = branch.branch_holidays || [];

    return (
        <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900 space-y-3">
            {/* Header */}
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2 min-w-0">
                    <div className="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                        <CalendarDays className="w-4 h-4" />
                    </div>
                    <div className="min-w-0">
                        <h3 className="font-semibold text-xs text-slate-900 dark:text-slate-100 flex items-center gap-1.5 truncate">
                            <span>{t('branch_holiday', 'Filial bayram kuni')}</span>
                            <span className="text-[11px] font-medium px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                {holidays.length} ta
                            </span>
                        </h3>
                    </div>
                </div>

                <CreateBranchHolidayModal branch={branch} />
            </div>

            {/* List or Empty State */}
            {holidays.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-200 dark:border-slate-800 p-4 text-center">
                    <p className="text-xs text-slate-400 dark:text-slate-500">
                        {t('no_holidays_found', 'Dam olish kunlari belgilanmagan')}
                    </p>
                </div>
            ) : (
                <div className="space-y-2 max-h-[320px] overflow-y-auto pr-0.5">
                    {holidays.map((item) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between gap-2.5 p-2.5 rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-slate-100/70 dark:border-slate-800/80 dark:bg-slate-800/40 dark:hover:bg-slate-800/80 transition-colors"
                        >
                            <div className="min-w-0 flex-1 space-y-0.5">
                                <div className="flex items-center gap-2">
                                    <span className="font-medium text-xs text-slate-900 dark:text-slate-100 truncate">
                                        {item.name}
                                    </span>
                                </div>
                                <div className="flex items-center gap-2 text-[11px]">
                                    <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 font-mono font-medium">
                                        <Calendar className="w-3 h-3 text-amber-500" />
                                        {item.date}
                                    </span>
                                    {item.comment && (
                                        <span className="text-slate-400 dark:text-slate-500 truncate max-w-[150px]" title={item.comment}>
                                            {item.comment}
                                        </span>
                                    )}
                                </div>
                            </div>

                            <button
                                type="button"
                                onClick={() => handleDeleteClick(item)}
                                className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-colors shrink-0"
                                title={t('delete', 'O‘chirish')}
                            >
                                <Trash2 className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {/* Pass selected branch holiday to the DeleteItemModal */}
            {selectedBranchHoliday && openDelete && (
                <DeleteItemModal
                    item={selectedBranchHoliday}
                    open={openDelete}
                    setOpen={setOpenDelete}
                    onDelete={handleDelete}
                />
            )}
        </div>
    );
};

export default BranchHolidayTable;

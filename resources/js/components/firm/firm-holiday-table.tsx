import React, { useState } from 'react';
import { Calendar, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Firm, FirmHoliday } from '@/types';
import DeleteItemModal from '@/components/delete-item-modal';
import { toast } from 'sonner';
import CreateFirmHolidayModal from '@/components/firm/create-firm-holiday-modal';

type FirmHolidayTableProps = {
    firm: Firm;
};

const FirmHolidayTable = ({ firm }: FirmHolidayTableProps) => {
    const { t } = useTranslation();
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedFirmHoliday, setSelectedFirmHoliday] = useState<FirmHoliday | null>(null);

    const handleDeleteClick = (holiday: FirmHoliday) => {
        setSelectedFirmHoliday(holiday);
        setOpenDelete(true);
    };

    const { delete: deleteFirmHoliday, reset, clearErrors } = useForm();

    const handleDelete = (id: number) => {
        deleteFirmHoliday(`/firm_holiday/${id}`, {
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

    const holidays = firm.firm_holidays || [];

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between py-1.5 border-b border-slate-200/80 dark:border-slate-800">
                <h3 className="font-semibold text-xs uppercase tracking-wider text-slate-600 dark:text-slate-300 flex items-center gap-1.5">
                    <Calendar className="w-4 h-4 text-indigo-500 dark:text-indigo-400" />
                    <span>{t('firm_holiday', 'Firma Dam Olish Kunlari')}</span>
                    <span className="text-[11px] font-normal px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                        {holidays.length} ta
                    </span>
                </h3>

                <CreateFirmHolidayModal firm={firm} />
            </div>

            {/* Table */}
            <div className="w-full min-w-0 max-w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="w-full overflow-x-auto">
                    <table className="w-full min-w-[600px] text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-3 py-2.5 text-center w-10 font-mono">{t('n', '№')}</th>
                                <th className="px-3 py-2.5">{t('name', 'Nomi')}</th>
                                <th className="px-3 py-2.5">{t('date', 'Sana')}</th>
                                <th className="px-3 py-2.5">{t('comment', 'Izoh')}</th>
                                <th className="px-3 py-2.5 text-right w-16">{t('action', 'Amal')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {holidays.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-3 py-6 text-center text-slate-400 dark:text-slate-500">
                                        {t('no_holidays', 'Dam olish kunlari belgilanmagan')}
                                    </td>
                                </tr>
                            ) : (
                                holidays.map((item, index) => (
                                    <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                        <td className="px-3 py-2.5 text-center font-mono text-slate-400 dark:text-slate-500">{index + 1}</td>
                                        <td className="px-3 py-2.5 font-medium text-slate-900 dark:text-slate-100">{item.name}</td>
                                        <td className="px-3 py-2.5 font-mono text-slate-600 dark:text-slate-300">
                                            <span className="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[11px] font-semibold">
                                                {item.date}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2.5 text-slate-500 dark:text-slate-400">{item.comment || '—'}</td>
                                        <td className="px-3 py-2.5 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleDeleteClick(item)}
                                                className="h-7 w-7 p-0 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg"
                                                title={t('delete', 'O‘chirish')}
                                            >
                                                <Trash2 className="w-3.5 h-3.5" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {selectedFirmHoliday && openDelete && (
                    <DeleteItemModal
                        item={selectedFirmHoliday}
                        open={openDelete}
                        setOpen={setOpenDelete}
                        onDelete={handleDelete}
                    />
                )}
            </div>
        </div>
    );
};

export default FirmHolidayTable;

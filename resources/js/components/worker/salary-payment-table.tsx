import React, { useState } from 'react';
import { Trash2, CreditCard } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Worker, SalaryPayment } from '@/types';
import DeleteItemModal from '@/components/delete-item-modal';
import { toast } from 'sonner';
import { format } from 'date-fns';

type SalaryPaymentTableProps = {
    worker: Worker;
};

const SalaryPaymentTable = ({ worker }: SalaryPaymentTableProps) => {
    const { t } = useTranslation();
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedSalaryPayment, setSelectedSalaryPayment] = useState<SalaryPayment | null>(null);

    const handleDeleteClick = (salary_payment: SalaryPayment) => {
        setSelectedSalaryPayment(salary_payment);
        setOpenDelete(true);
    };

    const { delete: deleteSalaryPayment, reset, clearErrors } = useForm();

    const handleDelete = (id: number) => {
        deleteSalaryPayment(`/salary_payment/${id}`, {
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

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <div className="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <CreditCard className="w-4 h-4" />
                    </div>
                    <h3 className="font-semibold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <span>{t('salary_payment', 'Oylik to‘lovlari')}</span>
                        <span className="text-xs font-normal px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                            {worker.salary_payments?.length || 0} ta
                        </span>
                    </h3>
                </div>
            </div>

            {/* Table Card */}
            <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-3.5 py-3 text-center w-10 font-mono">{t('n', '№')}</th>
                                <th className="px-3.5 py-3">{t('amount', 'To‘langan summa')}</th>
                                <th className="px-3.5 py-3">{t('datetime', 'Sana va vaqt')}</th>
                                <th className="px-3.5 py-3">{t('comment', 'Izoh')}</th>
                                <th className="px-3.5 py-3 text-right w-16">{t('action', 'Amal')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {(!worker.salary_payments || worker.salary_payments.length === 0) ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-slate-400 dark:text-slate-500">
                                        {t('no_data', 'To‘lovlar mavjud emas')}
                                    </td>
                                </tr>
                            ) : (
                                worker.salary_payments.map((item, index) => (
                                    <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                        <td className="px-3.5 py-2.5 text-center font-mono text-slate-400 dark:text-slate-500">{index + 1}</td>
                                        <td className="px-3.5 py-2.5 font-semibold text-rose-600 dark:text-rose-400">
                                            {Number(item.amount).toLocaleString()} {t('sum', 'so‘m')}
                                        </td>
                                        <td className="px-3.5 py-2.5 font-mono text-slate-600 dark:text-slate-300">
                                            {format(new Date(item.created_at), 'yyyy-MM-dd HH:mm:ss')}
                                        </td>
                                        <td className="px-3.5 py-2.5 text-slate-400 dark:text-slate-500 truncate max-w-[200px]" title={item.comment || ''}>
                                            {item.comment || '—'}
                                        </td>
                                        <td className="px-3.5 py-2.5 text-right">
                                            <div className="flex items-center justify-end">
                                                <button
                                                    type="button"
                                                    onClick={() => handleDeleteClick(item)}
                                                    className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-colors inline-flex items-center justify-center"
                                                    title={t('delete', 'O‘chirish')}
                                                >
                                                    <Trash2 className="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pass selected salary payment to the DeleteItemModal */}
            {selectedSalaryPayment && openDelete && (
                <DeleteItemModal
                    item={selectedSalaryPayment}
                    open={openDelete}
                    setOpen={setOpenDelete}
                    onDelete={handleDelete}
                />
            )}
        </div>
    );
};

export default SalaryPaymentTable;


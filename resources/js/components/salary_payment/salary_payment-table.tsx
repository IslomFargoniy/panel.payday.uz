import React, { useState } from 'react';
import CreateSalaryPaymentModal from '@/components/salary_payment/create-salary_payment-modal';
import { Pencil, Trash2 } from 'lucide-react';
import UpdateSalaryPaymentModal from '@/components/salary_payment/update-salary_payment-modal';
import DeleteItemModal from '@/components/delete-item-modal';
import { Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { SalaryPayment, type SalaryPaymentPaginate, SearchData, Worker } from '@/types';
import { toast } from 'sonner';
import { format } from 'date-fns';

interface SalaryPaymentTableProps extends SalaryPaymentPaginate {
    searchData: SearchData;
    workers: Worker[];
}

const Salary_paymentTable = ({ searchData, workers, ...salary_payment }: SalaryPaymentTableProps) => {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedSalaryPayment, setSelectedSalaryPayment] = useState<SalaryPayment | null>(null);

    const handleUpdateClick = (salary_paymentData: SalaryPayment) => {
        setSelectedSalaryPayment(salary_paymentData);
        setOpen(true);
    };

    const handleDeleteClick = (salary_paymentData: SalaryPayment) => {
        setSelectedSalaryPayment(salary_paymentData);
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
                toast.success(t('deleted_successfully'));
            },
            onError: (err) => {
                const errorMessage = err?.error || t('delete_failed');
                toast.error(errorMessage);
            }
        });
    };

    return (
        <div className="space-y-4">
            {/* Table Card */}
            <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-4 py-3 text-center w-12 font-mono">{t('n')}</th>
                                <th className="px-4 py-3">{t('user')}</th>
                                <th className="px-4 py-3">{t('worker')}</th>
                                <th className="px-4 py-3">{t('firm')}</th>
                                <th className="px-4 py-3">{t('amount')}</th>
                                <th className="px-4 py-3">{t('comment')}</th>
                                <th className="px-4 py-3">{t('date')}</th>
                                <th className="px-4 py-3 text-right font-medium">
                                    <CreateSalaryPaymentModal workers={workers} />
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {(!salary_payment.data || salary_payment.data.length === 0) ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center text-slate-400 dark:text-slate-500">
                                        {t('no_data', 'Ma’lumot mavjud emas')}
                                    </td>
                                </tr>
                            ) : (
                                salary_payment.data.map((item, index) => {
                                    const globalIndex = (salary_payment.current_page - 1) * salary_payment.per_page + index + 1;
                                    return (
                                        <tr key={item.id} className="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                            <td className="px-4 py-3 text-center font-mono text-slate-400 dark:text-slate-500">{globalIndex}</td>
                                            <td className="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">{item.user?.name ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`/worker/${item.worker?.id}`}
                                                    className="font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                >
                                                    {item.worker?.name}
                                                </Link>
                                            </td>
                                        <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                            {item.worker?.branch?.firm?.name} ({item.worker?.branch?.name})
                                        </td>
                                        <td className="px-4 py-3 font-semibold text-emerald-600 dark:text-emerald-400">
                                            {typeof item.amount === 'number' ? item.amount.toLocaleString('ru-RU') : item.amount}
                                        </td>
                                        <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{item.comment || '—'}</td>
                                        <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                            {format(new Date(item.created_at), 'yyyy-MM-dd HH:mm')}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => handleUpdateClick(item)}
                                                    className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-950/40 dark:hover:text-amber-400"
                                                    title={t('edit') ?? 'Edit'}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDeleteClick(item)}
                                                    className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
                                                    title={t('delete') ?? 'Delete'}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Modals */}
            {selectedSalaryPayment && open && (
                <UpdateSalaryPaymentModal
                    salary_payment={selectedSalaryPayment}
                    open={open}
                    setOpen={setOpen}
                />
            )}

            {selectedSalaryPayment && openDelete && (
                <DeleteItemModal
                    item={selectedSalaryPayment}
                    open={openDelete}
                    setOpen={setOpenDelete}
                    onDelete={handleDelete}
                />
            )}

            {/* Pagination */}
            <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <div>
                    {t('showing', {
                        from: salary_payment.from,
                        to: salary_payment.to,
                        total: salary_payment.total
                    })}
                </div>
                <div className="flex items-center gap-1">
                    {salary_payment.links.map((link, index) => (
                        <Link
                            key={index}
                            href={`${link.url ?? '?'}&search=${searchData.search || ''}&per_page=${searchData.per_page || 15}`}
                            className={`rounded-lg px-2.5 py-1 text-xs font-medium transition-colors ${
                                link.active
                                    ? 'bg-indigo-600 text-white shadow-xs'
                                    : !link.url
                                        ? 'cursor-not-allowed opacity-40'
                                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
};

export default Salary_paymentTable;


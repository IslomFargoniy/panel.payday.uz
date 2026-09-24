import React, { useState } from 'react';
import { Trash2 } from 'lucide-react';
import DeleteItemModal from '@/components/delete-item-modal';
import { Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Salary, type SalaryPaginate, SearchData } from '@/types';
import { toast } from 'sonner';
import { format } from 'date-fns';

interface SalaryTableProps extends SalaryPaginate {
    searchData: SearchData;
}

const SalaryTable = ({ searchData, ...salary }: SalaryTableProps) => {
    const { t } = useTranslation();
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedSalary, setSelectedSalary] = useState<Salary | null>(null);

    const handleDeleteClick = (salaryData: Salary) => {
        setSelectedSalary(salaryData);
        setOpenDelete(true);
    };

    const { delete: deleteSalary, reset, clearErrors } = useForm();

    const handleDelete = (id: number) => {
        deleteSalary(`/salary/${id}`, {
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
            {/* Table Card */}
            <div className="w-full min-w-0 max-w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="w-full overflow-x-auto">
                    <table className="w-full min-w-[1000px] text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-3.5 py-3 text-center w-10 font-mono">{t('n', '№')}</th>
                                <th className="px-3.5 py-3">{t('user', 'Hisoblagan')}</th>
                                <th className="px-3.5 py-3">{t('worker', 'Xodim')}</th>
                                <th className="px-3.5 py-3">{t('firm', 'Filial')}</th>
                                <th className="px-3.5 py-3">{t('amount', 'Summa')}</th>
                                <th className="px-3.5 py-3">{t('worked_minutes', 'Ish vaqti')}</th>
                                <th className="px-3.5 py-3">{t('hour_price', 'Soat narxi')}</th>
                                <th className="px-3.5 py-3">{t('from', 'Boshlanishi')}</th>
                                <th className="px-3.5 py-3">{t('to', 'Tugashi')}</th>
                                <th className="px-3.5 py-3">{t('date', 'Sana')}</th>
                                <th className="px-3.5 py-3 text-right w-16">{t('action', 'Amal')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {(!salary.data || salary.data.length === 0) ? (
                                <tr>
                                    <td colSpan={11} className="px-4 py-8 text-center text-slate-400 dark:text-slate-500">
                                        {t('no_data', 'Ma’lumot mavjud emas')}
                                    </td>
                                </tr>
                            ) : (
                                salary.data.map((item, index) => {
                                    const globalIndex = (salary.current_page - 1) * salary.per_page + index + 1;
                                    return (
                                        <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-3.5 py-2.5 text-center font-mono text-slate-400 dark:text-slate-500">{globalIndex}</td>
                                            <td className="px-3.5 py-2.5 font-medium text-slate-600 dark:text-slate-300">
                                                {item.user?.name || '—'}
                                            </td>
                                            <td className="px-3.5 py-2.5 font-medium text-slate-900 dark:text-slate-100">
                                                <Link href={`/worker/${item.worker?.id}`} className="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                    {item.worker?.name}
                                                </Link>
                                            </td>
                                            <td className="px-3.5 py-2.5 text-slate-500 truncate max-w-[140px]">
                                                {item.worker?.branch?.firm?.name} ({item.worker?.branch?.name})
                                            </td>
                                            <td className="px-3.5 py-2.5 font-semibold text-emerald-600 dark:text-emerald-400 font-mono">
                                                {Number(item.amount).toLocaleString()} {t('sum', 'so‘m')}
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-600 dark:text-slate-300">
                                                {~~(item.worked_minute! / 60)}s {item.worked_minute! % 60}daq
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-600 dark:text-slate-300">
                                                {Number(item.hour_price).toLocaleString()}
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono text-[11px] text-slate-500">{item.from}</td>
                                            <td className="px-3.5 py-2.5 font-mono text-[11px] text-slate-500">{item.to}</td>
                                            <td className="px-3.5 py-2.5 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                                {format(new Date(item.created_at), 'yyyy-MM-dd HH:mm:ss')}
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
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            <div className="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400 px-1 pt-1">
                <div>
                    {t('showing', {
                        from: salary.from || 0,
                        to: salary.to || 0,
                        total: salary.total || 0,
                    })}
                </div>
                <div className="flex items-center gap-1">
                    {salary.links.map((link, index) => (
                        <Link
                            key={index}
                            href={`${link.url ?? '?'}&search=${searchData.search}&per_page=${searchData.per_page}`}
                            className={`rounded-lg px-2.5 py-1 text-xs font-medium transition-colors ${
                                link.active
                                    ? 'bg-indigo-600 text-white shadow-xs'
                                    : !link.url
                                        ? 'cursor-not-allowed opacity-40 text-slate-400'
                                        : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>

            {/* Pass selected salary to the DeleteItemModal */}
            {selectedSalary && openDelete && (
                <DeleteItemModal
                    item={selectedSalary}
                    open={openDelete}
                    setOpen={setOpenDelete}
                    onDelete={handleDelete}
                />
            )}
        </div>
    );
};

export default SalaryTable;


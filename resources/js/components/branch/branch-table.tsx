import React, { useState } from 'react';
import UpdateBranchModal from '@/components/branch/update-branch-modal';
import { Button } from '@/components/ui/button';
import { CalendarCheck, Eye, MapPin, Pencil, Store, Trash2 } from 'lucide-react';
import { Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Branch, Firm } from '@/types';
import DeleteItemModal from '@/components/delete-item-modal';
import { toast } from 'sonner';
import CreateBranchModal from '@/components/branch/create-branch-modal';

type BranchTableProps = {
    firm: Firm;
};

const BranchTable = ({ firm }: BranchTableProps) => {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedBranch, setSelectedBranch] = useState<Branch | null>(null);

    const handleUpdateClick = (branch: Branch) => {
        setSelectedBranch(branch);
        setOpen(true);
    };

    const handleDeleteClick = (branch: Branch) => {
        setSelectedBranch(branch);
        setOpenDelete(true);
    };

    const { delete: deleteBranch, reset, clearErrors } = useForm();

    const handleDelete = (id: number) => {
        deleteBranch(`/branch/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setOpen(false);
                toast.success(t('deleted_successfully', 'Muvaffaqiyatli o‘chirildi'));
            },
            onError: (err) => {
                const errorMessage = err?.error || t('delete_failed', 'O‘chirishda xatolik yuz berdi');
                toast.error(errorMessage);
            }
        });
    };

    const branches = firm.branches || [];

    return (
        <div className="space-y-4">
            {/* Table Card Container */}
            <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-4 py-3 text-center w-12 font-mono">{t('n', '№')}</th>
                                <th className="px-4 py-3">{t('name', 'Filial Nomi')}</th>
                                <th className="px-4 py-3">{t('address', 'Manzil')}</th>
                                <th className="px-4 py-3">{t('work_time', 'Ish vaqti')}</th>
                                <th className="px-4 py-3">{t('hour_price', 'Soat / Jarima')}</th>
                                <th className="px-4 py-3">{t('worker', 'Xodimlar')}</th>
                                <th className="px-4 py-3 text-center">{t('status', 'Holat')}</th>
                                <th className="px-4 py-3 text-right w-32">
                                    <CreateBranchModal firm={firm} />
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {branches.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-10 text-center text-slate-400 dark:text-slate-500">
                                        <Store className="w-8 h-8 mx-auto mb-2 opacity-40" />
                                        <p>{t('no_branches', 'Filiallar mavjud emas')}</p>
                                    </td>
                                </tr>
                            ) : (
                                branches.map((item, index) => {
                                    const isActive = item.status == 1;

                                    return (
                                        <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-4 py-3 text-center font-mono text-slate-400 dark:text-slate-500">{index + 1}</td>
                                            <td className="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">
                                                <Link href={`/branch/${item.id}`} className="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                    {item.name}
                                                </Link>
                                                {item.comment && (
                                                    <p className="text-[11px] font-normal text-slate-400 truncate max-w-xs">{item.comment}</p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600 dark:text-slate-300">
                                                <div className="flex items-center gap-1">
                                                    <MapPin className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                                    <span className="truncate max-w-xs">{item.address || '—'}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-slate-600 dark:text-slate-300">
                                                <span className="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[11px] font-medium">
                                                    {item.work_time?.slice(0, 5)} – {item.end_time?.slice(0, 5)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs">
                                                <span className="text-emerald-600 font-semibold">{item.hour_price ?? 0}</span>
                                                <span className="text-slate-400 mx-1">/</span>
                                                <span className="text-rose-500 font-semibold">{item.fine_price ?? 0}</span>
                                            </td>
                                            <td className="px-4 py-3 font-semibold text-slate-800 dark:text-slate-200">
                                                {item.workers_count ?? 0} ta
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {isActive ? (
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                                        <span>{t('active', 'Faol')}</span>
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-slate-400" />
                                                        <span>{t('inactive', 'Nofaol')}</span>
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                        className="h-8 w-8 p-0 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 rounded-lg"
                                                        title={t('view', 'Ko‘rish')}
                                                    >
                                                        <Link href={`/branch/${item.id}`}>
                                                            <Eye className="w-4 h-4" />
                                                        </Link>
                                                    </Button>

                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                        className="h-8 w-8 p-0 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 rounded-lg"
                                                        title={t('daily_attendance', 'Kunlik davomat')}
                                                    >
                                                        <Link href={`/daily_attendance/${item.id}`}>
                                                            <CalendarCheck className="w-4 h-4" />
                                                        </Link>
                                                    </Button>

                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleUpdateClick(item)}
                                                        className="h-8 w-8 p-0 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40 rounded-lg"
                                                        title={t('edit', 'Tahrirlash')}
                                                    >
                                                        <Pencil className="w-4 h-4" />
                                                    </Button>

                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDeleteClick(item)}
                                                        className="h-8 w-8 p-0 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg"
                                                        title={t('delete', 'O‘chirish')}
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {selectedBranch && open && (
                    <UpdateBranchModal branch={selectedBranch} open={open} setOpen={setOpen} />
                )}

                {selectedBranch && openDelete && (
                    <DeleteItemModal item={selectedBranch} open={openDelete} setOpen={setOpenDelete} onDelete={handleDelete} />
                )}
            </div>
        </div>
    );
};

export default BranchTable;

import React, { useState } from 'react';
import CreateFirmModal from '@/components/firm/create-firm-modal';
import { Building2, Pencil, Settings, Trash2 } from 'lucide-react';
import UpdateFirmModal from '@/components/firm/update-firm-modal';
import DeleteItemModal from '@/components/delete-item-modal';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Auth, Firm, type FirmPaginate, SearchData } from '@/types';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import FirmSettingModal from '@/components/firm/firm-setting-modal';

interface FirmTableProps extends FirmPaginate {
    searchData: SearchData;
}

const FirmTable = ({ searchData, ...firm }: FirmTableProps) => {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [openDelete, setOpenDelete] = useState(false);
    const [openSetting, setOpenSetting] = useState(false);
    const [selectedFirm, setSelectedFirm] = useState<Firm | null>(null);

    const { auth } = usePage().props as unknown as { auth?: Auth };
    const isAdmin = auth?.user?.roles?.some(role => role.name === 'Admin');

    const handleUpdateClick = (firmData: Firm) => {
        setSelectedFirm(firmData);
        setOpen(true);
    };

    const handleDeleteClick = (firmData: Firm) => {
        setSelectedFirm(firmData);
        setOpenDelete(true);
    };

    const handleSettingClick = (firmData: Firm) => {
        setSelectedFirm(firmData);
        setOpenSetting(true);
    };

    const { delete: deleteFirm, reset, clearErrors } = useForm();

    const handleDelete = (id: number) => {
        deleteFirm(`/firm/${id}`, {
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
        <div className="space-y-4">
            {/* Table Container Card */}
            <div className="w-full min-w-0 max-w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="w-full overflow-x-auto">
                    <table className="w-full min-w-[950px] text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-4 py-3 text-center w-12 font-mono">{t('n', '№')}</th>
                                <th className="px-4 py-3">{t('name', 'Nomi')}</th>
                                <th className="px-4 py-3">{t('user', 'Mas‘ul foydalanuvchilar')}</th>
                                <th className="px-4 py-3">{t('address', 'Manzil')}</th>
                                <th className="px-4 py-3">{t('worker', 'Xodimlar')}</th>
                                <th className="px-4 py-3">{t('branch_limit', 'Filiallar')}</th>
                                <th className="px-4 py-3">{t('valid_date', 'Amal qilish')}</th>
                                <th className="px-4 py-3 text-center">{t('status', 'Holat')}</th>
                                <th className="px-4 py-3 text-right w-28">
                                    {isAdmin && <CreateFirmModal />}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {firm.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="px-4 py-10 text-center text-slate-400 dark:text-slate-500">
                                        <Building2 className="w-8 h-8 mx-auto mb-2 opacity-40" />
                                        <p>{t('no_firms', 'Firmalar mavjud emas')}</p>
                                    </td>
                                </tr>
                            ) : (
                                firm.data.map((item, index) => {
                                    const globalIndex = (firm.current_page - 1) * firm.per_page + index + 1;
                                    const isActive = item.status == 1;

                                    return (
                                        <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-4 py-3 text-center font-mono text-slate-400 dark:text-slate-500">{globalIndex}</td>
                                            <td className="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">
                                                <Link href={`/firm/${item.id}`} className="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                    {item.name}
                                                </Link>
                                                {item.comment && (
                                                    <p className="text-[11px] font-normal text-slate-400 truncate max-w-xs">{item.comment}</p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-1 max-w-xs">
                                                    {item.user_firms?.length ? (
                                                        item.user_firms.map(user_firm => (
                                                            <span key={user_firm.id} className="px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                                                {user_firm.user?.name ?? '—'}
                                                            </span>
                                                        ))
                                                    ) : (
                                                        <span className="text-slate-400 text-xs">—</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-slate-600 dark:text-slate-300">{item.address || '—'}</td>
                                            <td className="px-4 py-3 font-semibold text-slate-800 dark:text-slate-200">{item.workers_count ?? 0} ta</td>
                                            <td className="px-4 py-3 text-slate-600 dark:text-slate-300">{item.branch_limit ?? 0} ta</td>
                                            <td className="px-4 py-3 text-slate-600 dark:text-slate-300 font-mono">{item.valid_date || '—'}</td>
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
                                                        onClick={() => handleSettingClick(item)}
                                                        className="h-8 w-8 p-0 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 rounded-lg"
                                                        title={t('settings', 'Sozlamalar')}
                                                    >
                                                        <Settings className="w-4 h-4" />
                                                    </Button>

                                                    {isAdmin && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleUpdateClick(item)}
                                                            className="h-8 w-8 p-0 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40 rounded-lg"
                                                            title={t('edit', 'Tahrirlash')}
                                                        >
                                                            <Pencil className="w-4 h-4" />
                                                        </Button>
                                                    )}

                                                    {isAdmin && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDeleteClick(item)}
                                                            className="h-8 w-8 p-0 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg"
                                                            title={t('delete', 'O‘chirish')}
                                                        >
                                                            <Trash2 className="w-4 h-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Modals */}
                {selectedFirm && open && (
                    <UpdateFirmModal firm={selectedFirm} open={open} setOpen={setOpen} />
                )}

                {selectedFirm && openDelete && (
                    <DeleteItemModal item={selectedFirm} open={openDelete} setOpen={setOpenDelete} onDelete={handleDelete} />
                )}

                {selectedFirm && openSetting && (
                    <FirmSettingModal firm={selectedFirm} open={openSetting} setOpen={setOpenSetting} />
                )}
            </div>

            {/* Modern Pagination */}
            <div className="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400 px-1 pt-1">
                <div>
                    {t('showing', {
                        from: firm.from || 0,
                        to: firm.to || 0,
                        total: firm.total || 0,
                    })}
                </div>
                <div className="flex items-center gap-1">
                    {firm.links.map((link, index) => (
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
        </div>
    );
};

export default FirmTable;

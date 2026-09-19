import React, { useState } from 'react';
import { Trash2 } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Firm, User, UserFirm } from '@/types';
import DeleteItemModal from '@/components/delete-item-modal';
import { toast } from 'sonner';
import CreateUserFirmModal from '@/components/user/create-user-firm-modal';

type UserFirmTableProps = {
    user: User;
    firms: Firm[];
};

const UserFirmTable = ({ user, firms }: UserFirmTableProps) => {
    const { t } = useTranslation();
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedUserFirm, setSelectedUserFirm] = useState<UserFirm | null>(null);

    const handleDeleteClick = (user_firm: UserFirm) => {
        setSelectedUserFirm(user_firm);
        setOpenDelete(true);
    };

    const { delete: deleteUserFirm, reset, clearErrors } = useForm();

    const handleDelete = (id: number) => {
        deleteUserFirm(`/user_firm/${id}`, {
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
        <div className="space-y-3">
            <h3 className="text-sm font-semibold capitalize text-slate-800 dark:text-slate-200">
                {t('user_firm')}
            </h3>

            {/* Table Card */}
            <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-4 py-3.5">{t('n')}</th>
                                <th className="px-4 py-3.5">{t('firm')}</th>
                                <th className="px-4 py-3.5">{t('user')}</th>
                                <th className="px-4 py-3.5 text-right font-medium">
                                    <CreateUserFirmModal user={user} firms={firms} />
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80">
                            {user.user_firms?.map((item, index) => {
                                return (
                                    <tr key={item.id} className="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                        <td className="px-4 py-3 font-medium text-slate-500 dark:text-slate-400">{index + 1}</td>
                                        <td className="px-4 py-3 font-medium text-slate-800 dark:text-slate-200">{item.firm?.name}</td>
                                        <td className="px-4 py-3 text-slate-600 dark:text-slate-300">{item.user?.name}</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
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
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Delete Modal */}
            {selectedUserFirm && openDelete && (
                <DeleteItemModal
                    item={selectedUserFirm}
                    open={openDelete}
                    setOpen={setOpenDelete}
                    onDelete={handleDelete}
                />
            )}
        </div>
    );
};

export default UserFirmTable;


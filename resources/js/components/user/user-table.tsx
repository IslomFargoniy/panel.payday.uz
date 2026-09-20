import React, { useState } from 'react';
import { Pencil, Trash2 } from 'lucide-react';
import UpdateUserModal from '@/components/user/update-user-modal';
import DeleteItemModal from '@/components/delete-item-modal';
import { Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { User, type UserPaginate, SearchData } from '@/types';
import { toast } from 'sonner';

interface UserTableProps extends UserPaginate {
    searchData: SearchData;
}

const UserTable = ({ searchData, ...user }: UserTableProps) => {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);

    const handleUpdateClick = (userData: User) => {
        setSelectedUser(userData);
        setOpen(true);
    };

    const handleDeleteClick = (userData: User) => {
        setSelectedUser(userData);
        setOpenDelete(true);
    };

    const { delete: deleteUser, reset, clearErrors } = useForm();

    const handleDelete = (id: number) => {
        deleteUser(`/user/${id}`, {
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
            <div className="w-full min-w-0 max-w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="w-full overflow-x-auto">
                    <table className="w-full min-w-[750px] text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-4 py-3 text-center w-12 font-mono">{t('n')}</th>
                                <th className="px-4 py-3">{t('name')}</th>
                                <th className="px-4 py-3">{t('firm')}</th>
                                <th className="px-4 py-3">{t('role')}</th>
                                <th className="px-4 py-3">{t('phone')}</th>
                                <th className="px-4 py-3">{t('email')}</th>
                                <th className="px-4 py-3 text-right font-medium">{t('actions') ?? ''}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {(!user.data || user.data.length === 0) ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-slate-400 dark:text-slate-500">
                                        {t('no_data', 'Ma’lumot mavjud emas')}
                                    </td>
                                </tr>
                            ) : (
                                user.data.map((item, index) => {
                                    const globalIndex = (user.current_page - 1) * user.per_page + index + 1;
                                    return (
                                        <tr key={item.id} className="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                            <td className="px-4 py-3 text-center font-mono text-slate-400 dark:text-slate-500">{globalIndex}</td>
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`/user/${item.id}`}
                                                    className="font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                >
                                                    {item.name}
                                                </Link>
                                            </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {item.user_firms && item.user_firms.length > 0 ? (
                                                    item.user_firms.map((user_firm) => (
                                                        <span
                                                            key={user_firm.id}
                                                            className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                        >
                                                            {user_firm.firm?.name ?? '—'}
                                                        </span>
                                                    ))
                                                ) : (
                                                    <span className="text-xs text-slate-400">—</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {item.roles?.map((role) => (
                                                    <span
                                                        key={role.id}
                                                        className="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300"
                                                    >
                                                        {role.name}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600 dark:text-slate-300">{item.phone || '—'}</td>
                                        <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{item.email || '—'}</td>
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
            {selectedUser && open && (
                <UpdateUserModal
                    user={selectedUser}
                    open={open}
                    setOpen={setOpen}
                />
            )}

            {selectedUser && openDelete && (
                <DeleteItemModal
                    item={selectedUser}
                    open={openDelete}
                    setOpen={setOpenDelete}
                    onDelete={handleDelete}
                />
            )}

            {/* Pagination */}
            <div className="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400 px-1 pt-1">
                <div>
                    {t('showing', {
                        from: user.from || 0,
                        to: user.to || 0,
                        total: user.total || 0,
                    })}
                </div>
                <div className="flex items-center gap-1">
                    {user.links.map((link, index) => (
                        <Link
                            key={index}
                            href={`${link.url ?? '?'}&search=${searchData.search || ''}&per_page=${searchData.per_page || 15}`}
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

export default UserTable;


import React, { useState } from 'react';
import { TrashIcon } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Branch, BranchDevice } from '@/types';
import DeleteItemModal from '@/components/delete-item-modal';
import { toast } from 'sonner';
import CreateBranchDeviceModal from '@/components/branch/create-branch-device-modal';


import DeviceConnectionGuideModal from '@/components/branch/device-connection-guide-modal';

type BranchDeviceTableProps = {
    branch: Branch;
};

const BranchDeviceTable = ({ branch }: BranchDeviceTableProps) => {

    const { t } = useTranslation();  // Using the translation hook
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedBranchDevice, setSelectedBranchDevice] = useState<BranchDevice | null>(null);

    const handleDeleteClick = (branch_device: BranchDevice) => {
        setSelectedBranchDevice(branch_device);
        setOpenDelete(true);
    };
    const { delete: deleteBranchDevice, reset, errors: deleteError, clearErrors } = useForm();

    const handleDelete = (id: number) => {

        deleteBranchDevice(`/branch_device/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setOpenDelete(false); // 🔒 CLOSE MODAL HERE
                toast.success(t('deleted_successfully', 'Muvaffaqiyatli o‘chirildi')); // Success message
            },
            onError: (err) => {
                const errorMessage = err?.error || t('delete_failed', 'O‘chirishda xatolik yuz berdi');
                toast.error(errorMessage);
            }
        });
    };

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <h3 className="font-semibold text-base text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    <span>{t('branch_device', 'Filial Qurilmalari')}</span>
                    <span className="text-xs font-normal px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                        {branch.branch_devices?.length || 0} ta
                    </span>
                </h3>

                <div className="flex items-center gap-2">
                    <DeviceConnectionGuideModal branch={branch} />
                    <CreateBranchDeviceModal branch={branch} />
                </div>
            </div>

            {/* Table */}
            <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table className="border-collapse w-full text-sm text-left text-gray-800 dark:text-gray-100">
                    <thead className="bg-gray-100 dark:bg-gray-800/80 text-xs text-gray-700 dark:text-gray-300 uppercase">
                    <tr>
                        <th className="border-b border-gray-200 dark:border-gray-700 px-4 py-2.5 w-12 text-center">{t('n', '№')}</th>
                        <th className="border-b border-gray-200 dark:border-gray-700 px-4 py-2.5">{t('device_name', 'Qurilma / ID')}</th>
                        <th className="border-b border-gray-200 dark:border-gray-700 px-4 py-2.5">{t('mac_address', 'MAC manzil')}</th>
                        <th className="border-b border-gray-200 dark:border-gray-700 px-4 py-2.5">{t('connection_type', 'Ulanish turi')}</th>
                        <th className="border-b border-gray-200 dark:border-gray-700 px-4 py-2.5 text-center">{t('status', 'Holat')}</th>
                        <th className="border-b border-gray-200 dark:border-gray-700 px-4 py-2.5 text-right w-20">{t('action', 'Amallar')}</th>
                    </tr>
                    </thead>
                    <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                    {(!branch.branch_devices || branch.branch_devices.length === 0) ? (
                        <tr>
                            <td colSpan={6} className="px-4 py-6 text-center text-xs text-gray-500 dark:text-gray-400">
                                {t('no_devices', 'Hozircha ulangan qurilmalar mavjud emas. Yuqoridagi "Qurilma qo‘shish" yoki "Yo‘riqnoma" tugmasidan foydalaning.')}
                            </td>
                        </tr>
                    ) : (
                        branch.branch_devices.map((item, index) => {
                            const isIsup = item.connection_type === 'isup';
                            return (
                                <tr key={item.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/60 transition-colors">
                                    <td className="px-4 py-3 text-center text-xs font-mono text-gray-500">{index + 1}</td>
                                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-white text-xs">
                                        <div>{item.name || 'Hikvision Terminal'}</div>
                                        {item.device_id && (
                                            <span className="text-[11px] text-gray-500 font-mono">ID: {item.device_id}</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">
                                        {item.mac_address}
                                    </td>
                                    <td className="px-4 py-3 text-xs">
                                        {isIsup ? (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300">
                                                ISUP 5.0
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                                                HTTP Listening
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-center text-xs">
                                        <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Faol</span>
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <button
                                            onClick={() => handleDeleteClick(item)}
                                            title={t('delete', 'O‘chirish')}
                                            className="p-1.5 text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950/40 rounded transition-colors"
                                        >
                                            <TrashIcon className="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            );
                        })
                    )}
                    </tbody>

                    {/* Pass selected branch to the DeleteBranchModal */}
                    {selectedBranchDevice && openDelete && (
                        <DeleteItemModal
                            item={selectedBranchDevice}
                            open={openDelete}  // Assuming you have a separate state for openDelete
                            setOpen={setOpenDelete}  // Or you can manage this in its own state
                            onDelete={handleDelete} // Handle deletion
                        />
                    )}

                </table>

            </div>
        </div>
    );
};

export default BranchDeviceTable;

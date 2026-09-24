import React, { useState } from 'react';
import { TrashIcon, Copy, Check, ScanFace, Wifi, ShieldCheck, HardDrive, Radio, Network } from 'lucide-react';
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
    const { t } = useTranslation();
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedBranchDevice, setSelectedBranchDevice] = useState<BranchDevice | null>(null);
    const [copiedId, setCopiedId] = useState<number | null>(null);

    const handleDeleteClick = (branch_device: BranchDevice) => {
        setSelectedBranchDevice(branch_device);
        setOpenDelete(true);
    };

    const { delete: deleteBranchDevice, reset, clearErrors } = useForm();

    const handleDelete = (id: number) => {
        deleteBranchDevice(`/branch_device/${id}`, {
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

    const copyToClipboard = (text: string, deviceId: number) => {
        navigator.clipboard.writeText(text);
        setCopiedId(deviceId);
        toast.success(t('copied', 'Nusxalandi!'));
        setTimeout(() => setCopiedId(null), 2000);
    };

    const formatLastSeen = (dateStr?: string) => {
        if (!dateStr) return null;
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return null;

        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');

        return `${hours}:${minutes}, ${day}.${month}`;
    };

    const devices = branch.branch_devices || [];

    return (
        <div className="space-y-3">
            {/* Header */}
            <div className="flex flex-wrap items-center justify-between gap-2 py-1.5 border-b border-slate-200/80 dark:border-slate-800">
                <h3 className="font-semibold text-xs uppercase tracking-wider text-slate-600 dark:text-slate-300 flex items-center gap-2">
                    <Radio className="w-4 h-4 text-indigo-500 dark:text-indigo-400" />
                    <span>{t('branch_device', 'Filial Qurilmalari')}</span>
                    <span className="text-[11px] font-normal px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                        {devices.length} {t('items_count', 'ta')}
                    </span>
                </h3>

                <div className="flex items-center gap-1.5">
                    <DeviceConnectionGuideModal branch={branch} />
                    <CreateBranchDeviceModal branch={branch} />
                </div>
            </div>

            {/* Devices Container */}
            {devices.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-6 text-center bg-slate-50/50 dark:bg-slate-900/40">
                    <div className="w-12 h-12 mx-auto mb-2 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <ScanFace className="w-6 h-6" />
                    </div>
                    <p className="text-xs font-medium text-slate-700 dark:text-slate-300">
                        {t('no_devices', 'Hozircha ulangan qurilmalar mavjud emas')}
                    </p>
                    <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                        {t('add_device_hint', 'Yuqoridagi "Qo‘shish" yoki "Yo‘riqnoma" orqali Hikvision terminalini ulang.')}
                    </p>
                </div>
            ) : (
                <div className="space-y-3">
                    {devices.map((item, index) => {
                        const isIsup = item.connection_type === 'isup';
                        const isOnline = Boolean(item.is_online);
                        return (
                            <div
                                key={item.id}
                                className={`group relative overflow-hidden rounded-2xl border bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950 text-white shadow-md transition-all ${
                                    isOnline
                                        ? 'border-slate-700/80 hover:border-emerald-500/80 hover:shadow-emerald-500/10'
                                        : 'border-slate-800/80 hover:border-rose-500/60 hover:shadow-rose-500/10'
                                }`}
                            >
                                {/* Hikvision Terminal Top Camera Bar */}
                                <div className="flex items-center justify-between px-3.5 py-2 bg-slate-950/80 border-b border-slate-800">
                                    <div className="flex items-center gap-1.5">
                                        {/* Dual IR / RGB Camera Sensor Lens Mockup */}
                                        <div className="flex items-center gap-1 bg-slate-900 px-2 py-0.5 rounded-full border border-slate-800">
                                            <span className="w-2.5 h-2.5 rounded-full bg-slate-950 border border-indigo-400/60 flex items-center justify-center shadow-inner">
                                                <span className="w-1 h-1 rounded-full bg-indigo-400" />
                                            </span>
                                            <span className={`w-1.5 h-1.5 rounded-full ${isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-rose-500/80'}`} />
                                            <span className="w-2.5 h-2.5 rounded-full bg-slate-950 border border-slate-700" />
                                        </div>
                                        <span className="text-[10px] font-black tracking-widest text-slate-400 uppercase font-sans">
                                            HIKVISION
                                        </span>
                                    </div>

                                    {/* Action button */}
                                    <button
                                        onClick={() => handleDeleteClick(item)}
                                        title={t('delete', 'O‘chirish')}
                                        className="p-1 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-md transition-colors"
                                    >
                                        <TrashIcon className="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                {/* Terminal Screen Display (Simulated LCD Bezel) */}
                                <div className="p-3.5 space-y-3">
                                    {/* Device Name and Mode Header */}
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <div className="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center text-indigo-400">
                                                <ScanFace className="w-4 h-4" />
                                            </div>
                                            <div>
                                                <h4 className="text-xs font-bold text-slate-100 leading-tight">
                                                    {item.name || `Hikvision Terminal #${index + 1}`}
                                                </h4>
                                                <p className="text-[10px] text-slate-400 font-mono">
                                                    MinMoe Face Recognition
                                                </p>
                                            </div>
                                        </div>

                                        {/* Connection Pill */}
                                        {isIsup ? (
                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                                <Network className="w-3 h-3" />
                                                <span>ISUP 5.0</span>
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30">
                                                <Wifi className="w-3 h-3" />
                                                <span>HTTP</span>
                                            </span>
                                        )}
                                    </div>

                                    {/* Specs / Properties Box */}
                                    <div className="rounded-xl bg-slate-950/90 border border-slate-800/80 p-2.5 space-y-1.5 text-xs font-mono">
                                        {/* Device ID */}
                                        {item.device_id && (
                                            <div className="flex items-center justify-between text-[11px]">
                                                <span className="text-slate-400 font-sans">Device ID:</span>
                                                <span className="text-indigo-300 font-semibold px-1.5 py-0.5 rounded bg-indigo-950/60 border border-indigo-800/50">
                                                    {item.device_id}
                                                </span>
                                            </div>
                                        )}

                                        {/* MAC Address with copy */}
                                        <div className="flex items-center justify-between text-[11px]">
                                            <span className="text-slate-400 font-sans">MAC:</span>
                                            <div className="flex items-center gap-1.5">
                                                <span className="text-slate-200">{item.mac_address}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => copyToClipboard(item.mac_address, item.id)}
                                                    className="text-slate-400 hover:text-indigo-300 p-0.5 rounded"
                                                    title={t('copy_mac', 'MAC manzilni nusxalash')}
                                                >
                                                    {copiedId === item.id ? (
                                                        <Check className="w-3 h-3 text-emerald-400" />
                                                    ) : (
                                                        <Copy className="w-3 h-3" />
                                                    )}
                                                </button>
                                            </div>
                                        </div>

                                        {/* Connection Info */}
                                        <div className="flex items-center justify-between text-[11px] pt-0.5 border-t border-slate-800/60">
                                            <span className="text-slate-400 font-sans">Port / Protocol:</span>
                                            <span className="text-slate-300">
                                                {isIsup ? 'Port 7660 (ISUP)' : 'Port 80 (HTTP)'}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Terminal Bottom Status Bar */}
                                    <div className="flex items-center justify-between pt-1 text-[11px] text-slate-400">
                                        <div className="flex items-center gap-1.5">
                                            {isOnline ? (
                                                <>
                                                    <span className="relative flex h-2 w-2">
                                                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                                                        <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
                                                    </span>
                                                    <span className="text-emerald-400 font-medium font-sans">
                                                        {t('online_active', 'Online • Faol')}
                                                    </span>
                                                </>
                                            ) : (
                                                <>
                                                    <span className="relative flex h-2 w-2">
                                                        <span className="relative inline-flex rounded-full h-2 w-2 bg-rose-500" />
                                                    </span>
                                                    <span className="text-rose-400 font-medium font-sans">
                                                        {t('offline_inactive', 'Offline • Aloqada emas')}
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                        {item.last_seen_at ? (
                                            <span className="text-[10px] text-slate-400 font-mono" title={t('last_seen', 'Oxirgi aloqa vaqti')}>
                                                {t('last_seen', 'Oxirgi aloqa')}: {formatLastSeen(item.last_seen_at)}
                                            </span>
                                        ) : (
                                            <span className="text-[10px] text-slate-400 font-mono">{t('biometric_sync', 'Biometric Sync')}</span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {/* Delete Modal */}
            {selectedBranchDevice && openDelete && (
                <DeleteItemModal
                    item={selectedBranchDevice}
                    open={openDelete}
                    setOpen={setOpenDelete}
                    onDelete={handleDelete}
                />
            )}
        </div>
    );
};

export default BranchDeviceTable;

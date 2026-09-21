import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger
} from '@/components/ui/dialog';
import { Plus, Cpu } from 'lucide-react';
import { Branch } from '@/types';
import { Input } from '@/components/ui/input';

interface createBranch {
    branch: Branch;
}

type FormData = {
    branch_id: number;
    name: string;
    mac_address: string;
    device_id: string;
    connection_type: 'isup' | 'http_listening';
    encryption_key: string;
};

export default function CreateBranchDeviceModal({ branch }: createBranch) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm<FormData>({
        branch_id: branch.id,
        name: '',
        mac_address: '',
        device_id: `branch${branch.id}`,
        connection_type: 'isup',
        encryption_key: `PayDay${branch.id}2026`,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/branch_device', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('created_successfully', 'Muvaffaqiyatli saqlandi'));
                reset();
                clearErrors();
                setOpen(false);
            },
            onError: (err) => {
                const errorMessage = err?.error || t('create_failed', 'Xatolik yuz berdi');
                toast.error(errorMessage);
            }
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium h-8 px-2.5 rounded-lg shadow-xs flex items-center gap-1 text-xs">
                    <Plus className="w-3.5 h-3.5" />
                    <span>{t('create', 'Qo‘shish')}</span>
                </Button>
            </DialogTrigger>

            <DialogContent className="rounded-2xl border-slate-200 dark:border-slate-800 max-w-md p-6 bg-white dark:bg-slate-900 shadow-xl">
                <DialogHeader className="space-y-1.5 pb-2">
                    <DialogTitle className="flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                            <Cpu className="w-4 h-4" />
                        </div>
                        <span>{t('modal.create_device_title', 'Yangi Qurilma Qo‘shish')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500 dark:text-slate-400">
                        {t('branch', 'Filial')}: <strong className="font-semibold text-slate-700 dark:text-slate-300">{branch.name}</strong>
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4 pt-1">
                    <div className="space-y-1.5">
                        <Label htmlFor="name" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('device_name', 'Qurilma nomi')}
                        </Label>
                        <Input
                            id="name"
                            placeholder={t('device_name_placeholder', 'Masalan: Kassa 1 terminali')}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="mac_address" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('mac_address', 'MAC manzil')} <span className="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="mac_address"
                            placeholder="88:de:39:32:d8:0f"
                            value={data.mac_address}
                            onChange={(e) => setData('mac_address', e.target.value)}
                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm font-mono focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.mac_address} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="connection_type" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('connection_type', 'Ulanish turi')}
                        </Label>
                        <select
                            id="connection_type"
                            className="w-full h-9.5 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs sm:text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                            value={data.connection_type}
                            onChange={(e) => setData('connection_type', e.target.value as 'isup' | 'http_listening')}
                        >
                            <option value="isup">{t('isup_option', 'ISUP 5.0 (2 tomonlama avtomatik sinxronizatsiya)')}</option>
                            <option value="http_listening">{t('http_listening_option', 'HTTP Listening (1 tomonlama klassik)')}</option>
                        </select>
                        <InputError message={errors.connection_type} />
                    </div>

                    {data.connection_type === 'isup' && (
                        <div className="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5 dark:border-indigo-950 dark:bg-indigo-950/30 space-y-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="device_id" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {t('device_id', 'ISUP Device ID')}
                                </Label>
                                <Input
                                    id="device_id"
                                    value={data.device_id}
                                    onChange={(e) => setData('device_id', e.target.value)}
                                    className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm font-mono dark:border-slate-800 dark:bg-slate-800"
                                />
                                <InputError message={errors.device_id} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="encryption_key" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {t('encryption_key', 'Xavfsizlik kaliti (Key)')}
                                </Label>
                                <Input
                                    id="encryption_key"
                                    value={data.encryption_key}
                                    onChange={(e) => setData('encryption_key', e.target.value)}
                                    className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm font-mono dark:border-slate-800 dark:bg-slate-800"
                                />
                                <InputError message={errors.encryption_key} />
                            </div>
                        </div>
                    )}

                    <DialogFooter className="gap-2 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end">
                        <DialogClose asChild>
                            <Button
                                variant="outline"
                                type="button"
                                size="sm"
                                className="h-9 rounded-xl border-slate-200 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800"
                                onClick={() => {
                                    reset();
                                    clearErrors();
                                    setOpen(false);
                                }}
                            >
                                {t('cancel', 'Bekor qilish')}
                            </Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            size="sm"
                            disabled={processing}
                            className="h-9 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-4 text-xs font-semibold text-white shadow-xs dark:bg-indigo-600 dark:hover:bg-indigo-500"
                        >
                            {t('save', 'Saqlash')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}


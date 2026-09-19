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
                    <span>{t('add_device', 'Qurilma qo‘shish')}</span>
                </Button>
            </DialogTrigger>

            <DialogContent className="rounded-2xl border-slate-200 dark:border-slate-800 max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <Cpu className="w-4 h-4" />
                        </div>
                        <span>{t('modal.create_device_title', 'Yangi Qurilma Qo‘shish')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500">
                        Filial: <strong className="text-slate-700 dark:text-slate-300">{branch.name}</strong>
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-3.5">
                    <div>
                        <Label htmlFor="name" className="text-xs">{t('device_name', 'Qurilma nomi')}</Label>
                        <Input
                            id="name"
                            placeholder="Masalan: Kassa 1 terminali"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div>
                        <Label htmlFor="mac_address" className="text-xs">{t('mac_address', 'MAC manzil')} *</Label>
                        <Input
                            id="mac_address"
                            placeholder="88:de:39:32:d8:0f"
                            value={data.mac_address}
                            onChange={(e) => setData('mac_address', e.target.value)}
                        />
                        <InputError message={errors.mac_address} />
                    </div>

                    <div>
                        <Label htmlFor="connection_type" className="text-xs">{t('connection_type', 'Ulanish turi')}</Label>
                        <select
                            id="connection_type"
                            className="w-full mt-1 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                            value={data.connection_type}
                            onChange={(e) => setData('connection_type', e.target.value as 'isup' | 'http_listening')}
                        >
                            <option value="isup">ISUP 5.0 (2 tomonlama avtomatik sinxronizatsiya)</option>
                            <option value="http_listening">HTTP Listening (1 tomonlama klassik)</option>
                        </select>
                        <InputError message={errors.connection_type} />
                    </div>

                    {data.connection_type === 'isup' && (
                        <>
                            <div>
                                <Label htmlFor="device_id" className="text-xs">{t('device_id', 'ISUP Device ID')}</Label>
                                <Input
                                    id="device_id"
                                    value={data.device_id}
                                    onChange={(e) => setData('device_id', e.target.value)}
                                />
                                <InputError message={errors.device_id} />
                            </div>

                            <div>
                                <Label htmlFor="encryption_key" className="text-xs">{t('encryption_key', 'Xavfsizlik kaliti (Key)')}</Label>
                                <Input
                                    id="encryption_key"
                                    value={data.encryption_key}
                                    onChange={(e) => setData('encryption_key', e.target.value)}
                                />
                                <InputError message={errors.encryption_key} />
                            </div>
                        </>
                    )}

                    <DialogFooter className="gap-2 pt-2">
                        <DialogClose asChild>
                            <Button
                                variant="secondary"
                                type="button"
                                size="sm"
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
                            className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium"
                        >
                            {t('save', 'Saqlash')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}


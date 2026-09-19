import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import 'react-time-picker/dist/TimePicker.css';
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
import { IoCreate } from 'react-icons/io5';
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
                <Button className="px-2 py-1 text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-600 flex items-center gap-1">
                    <IoCreate className="w-3.5 h-3.5" />
                    <span>{t('add_device', 'Qurilma qo‘shish')}</span>
                </Button>
            </DialogTrigger>

            <DialogContent className="dark:border-gray-700 max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('modal.create_device_title', 'Yangi Qurilma Qo‘shish')}</DialogTitle>
                    <DialogDescription>
                        Filial: <strong>{branch.name}</strong>
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-3.5">
                    <div>
                        <Label htmlFor="name">{t('device_name', 'Qurilma nomi')}</Label>
                        <Input
                            id="name"
                            placeholder="Masalan: Kassa 1 terminali"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div>
                        <Label htmlFor="mac_address">{t('mac_address', 'MAC manzil')} *</Label>
                        <Input
                            id="mac_address"
                            placeholder="88:de:39:32:d8:0f"
                            value={data.mac_address}
                            onChange={(e) => setData('mac_address', e.target.value)}
                        />
                        <InputError message={errors.mac_address} />
                    </div>

                    <div>
                        <Label htmlFor="connection_type">{t('connection_type', 'Ulanish turi')}</Label>
                        <select
                            id="connection_type"
                            className="w-full mt-1 border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
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
                                <Label htmlFor="device_id">{t('device_id', 'ISUP Device ID')}</Label>
                                <Input
                                    id="device_id"
                                    value={data.device_id}
                                    onChange={(e) => setData('device_id', e.target.value)}
                                />
                                <InputError message={errors.device_id} />
                            </div>

                            <div>
                                <Label htmlFor="encryption_key">{t('encryption_key', 'Xavfsizlik kaliti (Key)')}</Label>
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
                                onClick={() => {
                                    reset();
                                    clearErrors();
                                    setOpen(false);
                                }}
                            >
                                {t('cancel', 'Bekor qilish')}
                            </Button>
                        </DialogClose>

                        <Button type="submit" disabled={processing} className="bg-blue-600 text-white">
                            {t('save', 'Saqlash')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

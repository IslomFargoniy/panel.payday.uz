import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';
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
    DialogTitle
} from '@/components/ui/dialog';
import { Pencil } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Worker } from '@/types';
import TimePicker from 'react-time-picker';
import 'react-time-picker/dist/TimePicker.css';

interface Props {
    worker: Worker;
    open: boolean;
    setOpen: (open: boolean) => void;
}

export default function UpdateWorkerModal({ worker, open, setOpen }: Props) {
    const { t } = useTranslation();
    const nameInput = useRef<HTMLInputElement>(null);

    const [previewUrl, setPreviewUrl] = useState<string | null>(worker.avatar ? `/storage/${worker.avatar}` : null);

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        _method: 'put',
        name: worker.name || '',
        work_time: worker.work_time,
        end_time: worker.end_time,
        hour_price: worker.hour_price,
        fine_price: worker.fine_price,
        phone: worker.phone || '',
        address: worker.address || '',
        comment: worker.comment || '',
        status: worker.status,
        avatar: null as File | null,
    });

    useEffect(() => {
        if (open) {
            setPreviewUrl(worker.avatar ? `/storage/${worker.avatar}` : null);
            setData({
                _method: 'put',
                name: worker.name || '',
                work_time: worker.work_time,
                end_time: worker.end_time,
                hour_price: worker.hour_price,
                fine_price: worker.fine_price,
                phone: worker.phone || '',
                address: worker.address || '',
                comment: worker.comment || '',
                status: worker.status,
                avatar: null,
            });
        }
    }, [open, worker]);

    const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        setData('avatar', file);
        if (file) {
            setPreviewUrl(URL.createObjectURL(file));
        } else {
            setPreviewUrl(worker.avatar ? `/storage/${worker.avatar}` : null);
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(`/worker/${worker.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('updated_successfully', 'Muvaffaqiyatli saqlandi'));
                clearErrors();
                setOpen(false);
            },
            onError: (err) => {
                nameInput.current?.focus();
                const errorMessage = err?.error || t('create_failed', 'Xatolik yuz berdi');
                toast.error(errorMessage);
            }
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="max-w-2xl rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-950/60 flex items-center justify-center text-amber-600 dark:text-amber-400">
                            <Pencil className="w-4 h-4" />
                        </div>
                        <span>{t('modal.update_title', 'Xodim Ma’lumotlarini Tahrirlash')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500 dark:text-slate-400">
                        {t('modal.update_description', 'Xodim ma’lumotlarini yangilang')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="mt-4">
                    <div className="max-h-[65vh] overflow-y-auto px-1 pr-3 space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="name" className="text-xs">{t('name', 'F.I.SH')} *</Label>
                                <Input id="name" ref={nameInput} value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="phone" className="text-xs">{t('phone', 'Telefon')}</Label>
                                <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                                <InputError message={errors.phone} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="address" className="text-xs">{t('address', 'Manzil')}</Label>
                                <Input id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                                <InputError message={errors.address} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="comment" className="text-xs">{t('comment', 'Izoh')}</Label>
                                <Input id="comment" value={data.comment} onChange={(e) => setData('comment', e.target.value)} />
                                <InputError message={errors.comment} />
                            </div>

                            <div className="space-y-1.5 md:col-span-2">
                                <Label htmlFor="update-avatar" className="text-xs">{t('avatar', 'Rasm')}</Label>
                                <div className="flex items-center gap-4">
                                    {previewUrl ? (
                                        <div className="relative group size-14 shrink-0">
                                            <img
                                                src={previewUrl}
                                                alt="Avatar preview"
                                                className="size-14 rounded-xl object-cover border-2 border-amber-500 shadow-xs"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setData('avatar', null);
                                                    setPreviewUrl(null);
                                                    const fileInput = document.getElementById('update-avatar') as HTMLInputElement;
                                                    if (fileInput) fileInput.value = '';
                                                }}
                                                className="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white shadow hover:bg-rose-600"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    ) : (
                                        <div className="flex size-14 shrink-0 items-center justify-center rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 p-1 text-center text-xs text-slate-400 dark:border-slate-800 dark:bg-slate-900">
                                            {t('no_image', 'Rasm yo‘q')}
                                        </div>
                                    )}
                                    <div className="flex-1">
                                        <Input id="update-avatar" type="file" accept="image/*" onChange={handleAvatarChange} />
                                        <InputError message={errors.avatar as string} />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="work_time" className="block text-xs">
                                    {t('work_time', 'Ish boshlanishi')}
                                </Label>
                                <TimePicker
                                    id="work_time"
                                    value={data.work_time}
                                    onChange={(time) => setData('work_time', time ?? '')}
                                    format="HH:mm"
                                    locale="sv-sv"
                                    disableClock={true}
                                    className="block w-full rounded-lg border border-slate-200 px-3 py-2 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-slate-700 dark:text-white"
                                />
                                <InputError message={errors.work_time} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="end_time" className="block text-xs">
                                    {t('end_time', 'Ish tugashi')}
                                </Label>
                                <TimePicker
                                    id="end_time"
                                    value={data.end_time}
                                    onChange={(time) => setData('end_time', time ?? '')}
                                    format="HH:mm"
                                    locale="sv-sv"
                                    disableClock={true}
                                    className="block w-full rounded-lg border border-slate-200 px-3 py-2 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-slate-700 dark:text-white"
                                />
                                <InputError message={errors.end_time} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="hour_price" className="text-xs">{t('hour_price', 'Soatbay narx')}</Label>
                                <Input
                                    id="hour_price"
                                    type="number"
                                    value={data.hour_price}
                                    onChange={(e) => setData('hour_price', parseFloat(e.target.value) || 0)}
                                />
                                <InputError message={errors.hour_price} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="fine_price" className="text-xs">{t('fine_price', 'Jarima narxi')}</Label>
                                <Input
                                    id="fine_price"
                                    type="number"
                                    value={data.fine_price}
                                    onChange={(e) => setData('fine_price', parseFloat(e.target.value) || 0)}
                                />
                                <InputError message={errors.fine_price} />
                            </div>

                            <div className="space-y-1.5 md:col-span-2">
                                <Label htmlFor="status" className="mb-2 block text-xs">
                                    {t('status', 'Holati')}
                                </Label>
                                <label className="inline-flex cursor-pointer items-center gap-3">
                                    <input
                                        type="checkbox"
                                        id="status"
                                        className="peer sr-only"
                                        checked={data.status === 1}
                                        onChange={(e) => setData('status', e.target.checked ? 1 : 0)}
                                    />
                                    <div className="peer relative h-6 w-11 rounded-full bg-slate-200 peer-checked:bg-emerald-600 peer-focus:outline-none after:absolute after:start-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:border-slate-700 dark:bg-slate-800 dark:peer-checked:bg-emerald-600"></div>
                                    <span className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                        {data.status === 1 ? t('active', 'Faol') : t('inactive', 'Nofaol')}
                                    </span>
                                </label>
                                <InputError message={errors.status} />
                            </div>
                        </div>
                    </div>

                    <DialogFooter className="mt-6 gap-2 border-t border-slate-100 dark:border-slate-800 pt-4">
                        <DialogClose asChild>
                            <Button
                                variant="secondary"
                                type="button"
                                size="sm"
                                onClick={() => {
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
                            className="bg-indigo-600 hover:bg-indigo-700 font-medium text-white transition-colors"
                        >
                            {t('update', 'Saqlash')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}


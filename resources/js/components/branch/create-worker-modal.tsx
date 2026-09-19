import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Branch } from '@/types';
import { IoCreate } from 'react-icons/io5';
import TimePicker from 'react-time-picker';
import 'react-time-picker/dist/TimePicker.css';
import { toast } from 'sonner';

interface createWorker {
    branch: Branch;
}

type FormData = {
    branch_id: number;
    work_time: string;
    end_time: string;
    hour_price: number;
    fine_price: number;
    name: string;
    phone: string;
    address: string;
    comment: string;
    avatar: File | null;
};

export default function CreateWorkerModal({ branch }: createWorker) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const nameInput = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<FormData>({
        branch_id: branch.id,
        work_time: branch.work_time?.slice(0, 5) || '09:00',
        end_time: branch.end_time?.slice(0, 5) || '18:00',
        hour_price: branch.hour_price || 0,
        fine_price: branch.fine_price || 0,
        name: '',
        phone: '',
        address: '',
        comment: '',
        avatar: null,
    });

    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        setData('avatar', file);
        if (file) {
            setPreviewUrl(URL.createObjectURL(file));
        } else {
            setPreviewUrl(null);
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/worker', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('created_successfully'));
                setOpen(false);
                setPreviewUrl(null);
                reset();
                clearErrors();
            },
            onError: (err) => {
                nameInput.current?.focus();
                const errorMessage = err?.error || t('create_failed');
                toast.error(errorMessage);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button className="h-8 gap-1.5 bg-indigo-600 px-3 py-1 text-xs font-semibold text-white shadow-xs hover:bg-indigo-700 transition-colors rounded-lg">
                    <IoCreate className="h-3.5 w-3.5" />
                    {t('modal.create_title')}
                </Button>
            </DialogTrigger>

            <DialogContent className="max-w-2xl rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <DialogHeader className="space-y-1">
                    <DialogTitle className="text-lg font-bold text-slate-900 dark:text-slate-100">{t('modal.create_title')}</DialogTitle>
                    <DialogDescription className="text-xs text-slate-500 dark:text-slate-400">{t('modal.create_description')}</DialogDescription>
                </DialogHeader>

                <form onSubmit={submit}>
                    <div className="max-h-[65vh] overflow-y-auto px-1 pr-3">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pb-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">{t('name')}</Label>
                                <Input id="name" ref={nameInput} value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                <InputError message={errors.name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="phone">{t('phone')}</Label>
                                <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="address">{t('address')}</Label>
                                <Input id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                                <InputError message={errors.address} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="comment">{t('comment')}</Label>
                                <Input id="comment" value={data.comment} onChange={(e) => setData('comment', e.target.value)} />
                                <InputError message={errors.comment} />
                            </div>
                            
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="avatar">{t('avatar')}</Label>
                                <div className="flex items-center gap-4">
                                    {previewUrl ? (
                                        <div className="relative group size-16 shrink-0">
                                            <img
                                                src={previewUrl}
                                                alt="Avatar preview"
                                                className="size-16 rounded-full object-cover border-2 border-blue-500 shadow-sm"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setData('avatar', null);
                                                    setPreviewUrl(null);
                                                    const fileInput = document.getElementById('avatar') as HTMLInputElement;
                                                    if (fileInput) fileInput.value = '';
                                                }}
                                                className="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white shadow hover:bg-red-600"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    ) : (
                                        <div className="flex size-16 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-gray-300 bg-gray-100 p-1 text-center text-xs text-gray-400 dark:border-gray-600 dark:bg-gray-800">
                                            {t('no_image') || 'Rasm yo\'q'}
                                        </div>
                                    )}
                                    <div className="flex-1">
                                        <Input id="avatar" type="file" accept="image/*" onChange={handleAvatarChange} />
                                        <InputError message={errors.avatar as string} />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="work_time" className="block text-sm font-medium">
                                    {t('work_time')}
                                </Label>
                                <TimePicker
                                    id="work_time"
                                    value={data.work_time}
                                    onChange={(time) => setData('work_time', time ?? '')}
                                    format="HH:mm"
                                    locale="sv-sv"
                                    disableClock={true}
                                    className="block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:text-white"
                                />
                                <InputError message={errors.work_time} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="end_time" className="block text-sm font-medium">
                                    {t('end_time')}
                                </Label>
                                <TimePicker
                                    id="end_time"
                                    value={data.end_time}
                                    onChange={(time) => setData('end_time', time ?? '')}
                                    format="HH:mm"
                                    locale="sv-sv"
                                    disableClock={true}
                                    className="block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:text-white"
                                />
                                <InputError message={errors.end_time} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="hour_price">{t('hour_price')}</Label>
                                <Input
                                    id="hour_price"
                                    type="number"
                                    value={data.hour_price}
                                    onChange={(e) => setData('hour_price', Number(e.target.value))}
                                />
                                <InputError message={errors.hour_price} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="fine_price">{t('fine_price')}</Label>
                                <Input
                                    id="fine_price"
                                    type="number"
                                    value={data.fine_price}
                                    onChange={(e) => setData('fine_price', Number(e.target.value))}
                                />
                                <InputError message={errors.fine_price} />
                            </div>
                        </div>
                    </div>

                    <DialogFooter className="mt-6 gap-2 border-t pt-4">
                        <DialogClose asChild>
                            <Button
                                variant="secondary"
                                onClick={() => {
                                    reset();
                                    clearErrors();
                                    setOpen(false);
                                }}
                            >
                                {t('cancel')}
                            </Button>
                        </DialogClose>

                        <Button type="submit" disabled={processing} className="bg-blue-600 hover:bg-blue-700">
                            {t('save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

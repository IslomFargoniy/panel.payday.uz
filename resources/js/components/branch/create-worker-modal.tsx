import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Branch } from '@/types';
import { Plus, UserPlus, Camera } from 'lucide-react';
import TimePicker from 'react-time-picker';
import 'react-time-picker/dist/TimePicker.css';
import { toast } from 'sonner';
import WebcamCaptureModal from '@/components/webcam-capture-modal';

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
    const [cameraModalOpen, setCameraModalOpen] = useState(false);

    const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        setData('avatar', file);
        if (file) {
            setPreviewUrl(URL.createObjectURL(file));
        } else {
            setPreviewUrl(null);
        }
    };

    const handleCameraCapture = (file: File) => {
        setData('avatar', file);
        setPreviewUrl(URL.createObjectURL(file));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/worker', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('created_successfully', 'Muvaffaqiyatli saqlandi'));
                setOpen(false);
                setPreviewUrl(null);
                reset();
                clearErrors();
            },
            onError: (err) => {
                nameInput.current?.focus();
                const errorMessage = err?.error || t('create_failed', 'Xatolik yuz berdi');
                toast.error(errorMessage);
            },
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

            <DialogContent className="max-w-2xl rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <UserPlus className="w-4 h-4" />
                        </div>
                        <span>{t('modal.create_title', 'Yangi Xodim Qo‘shish')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500 dark:text-slate-400">
                        {t('modal.create_description', 'Xodimning shaxsiy va ish parametrlarini kiriting')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="mt-4">
                    <div className="max-h-[65vh] overflow-y-auto px-1 pr-3 space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="name" className="text-xs">{t('name', 'F.I.SH')} *</Label>
                                <Input id="name" ref={nameInput} value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Aliyev Vali" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="phone" className="text-xs">{t('phone', 'Telefon')}</Label>
                                <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+998 90 123 45 67" />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="address" className="text-xs">{t('address', 'Manzil')}</Label>
                                <Input id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} placeholder="Toshkent sh." />
                                <InputError message={errors.address} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="comment" className="text-xs">{t('comment', 'Izoh')}</Label>
                                <Input id="comment" value={data.comment} onChange={(e) => setData('comment', e.target.value)} placeholder="Qo‘shimcha izoh" />
                                <InputError message={errors.comment} />
                            </div>
                            
                            <div className="space-y-1.5 md:col-span-2">
                                <Label htmlFor="avatar" className="text-xs">{t('avatar', 'Rasm')}</Label>
                                <div className="flex items-center gap-4">
                                    {previewUrl ? (
                                        <div className="relative group size-14 shrink-0">
                                            <img
                                                src={previewUrl}
                                                alt="Avatar preview"
                                                className="size-14 rounded-xl object-cover border-2 border-indigo-500 shadow-xs"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setData('avatar', null);
                                                    setPreviewUrl(null);
                                                    const fileInput = document.getElementById('avatar') as HTMLInputElement;
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
                                    <div className="flex-1 flex gap-2">
                                        <Input id="avatar" type="file" accept="image/*" onChange={handleAvatarChange} className="flex-1" />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setCameraModalOpen(true)}
                                            className="shrink-0 flex items-center gap-1.5 h-9 px-3 text-xs border-indigo-200 text-indigo-600 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-400 dark:hover:bg-indigo-950/50"
                                            title="Kamera orqali rasmga olish"
                                        >
                                            <Camera className="w-3.5 h-3.5" />
                                            <span className="hidden sm:inline">{t('camera.snap', 'Kamera')}</span>
                                        </Button>
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
                                    onChange={(e) => setData('hour_price', Number(e.target.value))}
                                />
                                <InputError message={errors.hour_price} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="fine_price" className="text-xs">{t('fine_price', 'Jarima narxi')}</Label>
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

                    <DialogFooter className="mt-6 gap-2 border-t border-slate-100 dark:border-slate-800 pt-4">
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
            <WebcamCaptureModal
                open={cameraModalOpen}
                onOpenChange={setCameraModalOpen}
                onCapture={handleCameraCapture}
            />
        </Dialog>
    );
}


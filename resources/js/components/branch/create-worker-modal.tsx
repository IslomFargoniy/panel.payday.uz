import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Branch } from '@/types';
import { Plus, UserPlus, Camera, User, Clock, Coins, FileText, X } from 'lucide-react';
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

            <DialogContent className="max-w-2xl rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900 max-h-[90vh] flex flex-col">
                <DialogHeader className="space-y-1.5 pb-2">
                    <DialogTitle className="flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                            <UserPlus className="w-4 h-4" />
                        </div>
                        <span>{t('modal.create_title', 'Yangi Xodim Qo‘shish')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500 dark:text-slate-400">
                        {t('modal.create_description', 'Xodimning shaxsiy va ish parametrlarini kiriting')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex-1 overflow-y-auto space-y-5 px-1 pr-2 py-1">
                    {/* SECTION 1: SHAXSIY MA'LUMOTLAR */}
                    <div className="rounded-xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50 space-y-3.5">
                        <div className="flex items-center gap-2 text-xs font-semibold text-slate-800 dark:text-slate-200">
                            <User className="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                            <span>{t('personal_info', 'Shaxsiy ma’lumotlar')}</span>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div className="space-y-1.5">
                                <Label htmlFor="name" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {t('name', 'F.I.SH')} <span className="text-rose-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    ref={nameInput}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Aliyev Vali"
                                    className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="phone" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {t('phone', 'Telefon')}
                                </Label>
                                <Input
                                    id="phone"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    placeholder="+998 90 123 45 67"
                                    className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                                />
                                <InputError message={errors.phone} />
                            </div>

                            <div className="sm:col-span-2 space-y-1.5">
                                <Label htmlFor="address" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {t('address', 'Manzil')}
                                </Label>
                                <Input
                                    id="address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    placeholder="Toshkent sh., Chilonzor tumani..."
                                    className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                                />
                                <InputError message={errors.address} />
                            </div>

                            <div className="sm:col-span-2 space-y-1.5">
                                <Label htmlFor="avatar" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {t('avatar', 'Rasm')}
                                </Label>
                                <div className="flex items-center gap-3">
                                    {previewUrl ? (
                                        <div className="relative group size-12 shrink-0">
                                            <img
                                                src={previewUrl}
                                                alt="Avatar preview"
                                                className="size-12 rounded-xl object-cover border-2 border-indigo-500 shadow-xs"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setData('avatar', null);
                                                    setPreviewUrl(null);
                                                    const fileInput = document.getElementById('avatar') as HTMLInputElement;
                                                    if (fileInput) fileInput.value = '';
                                                }}
                                                className="absolute -top-1 -right-1 flex h-4.5 w-4.5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white shadow hover:bg-rose-600"
                                            >
                                                <X className="w-3 h-3" />
                                            </button>
                                        </div>
                                    ) : (
                                        <div className="flex size-12 shrink-0 items-center justify-center rounded-xl border-2 border-dashed border-slate-200 bg-white p-1 text-center text-[10px] font-medium text-slate-400 dark:border-slate-800 dark:bg-slate-850">
                                            {t('no_image', 'Rasm yo‘q')}
                                        </div>
                                    )}
                                    <div className="flex-1 flex gap-2">
                                        <Input
                                            id="avatar"
                                            type="file"
                                            accept="image/*"
                                            onChange={handleAvatarChange}
                                            className="flex-1 h-9.5 rounded-xl text-xs"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setCameraModalOpen(true)}
                                            className="shrink-0 flex items-center gap-1.5 h-9.5 px-3 rounded-xl text-xs border-indigo-200 text-indigo-600 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-400 dark:hover:bg-indigo-950/50"
                                            title="Kamera orqali rasmga olish"
                                        >
                                            <Camera className="w-3.5 h-3.5" />
                                            <span className="hidden sm:inline">{t('camera.snap', 'Kamera')}</span>
                                        </Button>
                                    </div>
                                </div>
                                <InputError message={errors.avatar as string} />
                            </div>
                        </div>
                    </div>

                    {/* SECTION 2: ISH REJIMI VA NARXLAR */}
                    <div className="rounded-xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50 space-y-3.5">
                        <div className="flex items-center gap-2 text-xs font-semibold text-slate-800 dark:text-slate-200">
                            <Clock className="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                            <span>{t('schedule_and_pricing', 'Ish tartibi va Hisob-kitob')}</span>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div className="space-y-1.5">
                                <Label htmlFor="work_time" className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {t('work_time', 'Ish boshlanishi')}
                                </Label>
                                <TimePicker
                                    id="work_time"
                                    value={data.work_time}
                                    onChange={(time) => setData('work_time', time ?? '')}
                                    format="HH:mm"
                                    locale="sv-sv"
                                    disableClock={true}
                                    className="block w-full h-9.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-800 dark:text-white"
                                />
                                <InputError message={errors.work_time} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="end_time" className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {t('end_time', 'Ish tugashi')}
                                </Label>
                                <TimePicker
                                    id="end_time"
                                    value={data.end_time}
                                    onChange={(time) => setData('end_time', time ?? '')}
                                    format="HH:mm"
                                    locale="sv-sv"
                                    disableClock={true}
                                    className="block w-full h-9.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-800 dark:text-white"
                                />
                                <InputError message={errors.end_time} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="hour_price" className="text-xs font-medium text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                    <Coins className="w-3 h-3 text-emerald-500" />
                                    <span>{t('hour_price', 'Soatbay narx (so‘m)')}</span>
                                </Label>
                                <Input
                                    id="hour_price"
                                    type="number"
                                    value={data.hour_price}
                                    onChange={(e) => setData('hour_price', Number(e.target.value))}
                                    placeholder="0"
                                    className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                                />
                                <InputError message={errors.hour_price} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="fine_price" className="text-xs font-medium text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                    <Coins className="w-3 h-3 text-rose-500" />
                                    <span>{t('fine_price', 'Jarima narxi (so‘m)')}</span>
                                </Label>
                                <Input
                                    id="fine_price"
                                    type="number"
                                    value={data.fine_price}
                                    onChange={(e) => setData('fine_price', Number(e.target.value))}
                                    placeholder="0"
                                    className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                                />
                                <InputError message={errors.fine_price} />
                            </div>
                        </div>
                    </div>

                    {/* SECTION 3: IZOH */}
                    <div className="rounded-xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50 space-y-3.5">
                        <div className="flex items-center gap-2 text-xs font-semibold text-slate-800 dark:text-slate-200">
                            <FileText className="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                            <span>{t('comment', 'Qo‘shimcha ma‘lumot')}</span>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="comment" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                {t('comment', 'Izoh')}
                            </Label>
                            <Input
                                id="comment"
                                value={data.comment}
                                onChange={(e) => setData('comment', e.target.value)}
                                placeholder="Xodim bo‘yicha qo‘shimcha izohlar (ixtiyoriy)"
                                className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                            />
                            <InputError message={errors.comment} />
                        </div>
                    </div>

                    <DialogFooter className="mt-4 gap-2 border-t border-slate-100 dark:border-slate-800 pt-3 flex items-center justify-end">
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
            <WebcamCaptureModal
                open={cameraModalOpen}
                onOpenChange={setCameraModalOpen}
                onCapture={handleCameraCapture}
            />
        </Dialog>
    );
}


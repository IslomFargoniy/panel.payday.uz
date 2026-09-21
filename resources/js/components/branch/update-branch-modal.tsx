import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import TimePicker from 'react-time-picker';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import { Branch } from '@/types';
import LocationPicker from '@/components/branch/location-picker';
import { Pencil } from 'lucide-react';

interface UpdateBranchModalProps {
    branch: Branch;
    open: boolean;
    setOpen: (open: boolean) => void;
}

export default function UpdateBranchModal({ branch, open, setOpen }: UpdateBranchModalProps) {
    const { t } = useTranslation();
    const nameInput = useRef<HTMLInputElement>(null);

    const { data, setData, put, processing, reset, errors, clearErrors } = useForm({
        name: branch.name || '',
        address: branch.address || '',
        comment: branch.comment || '',
        work_time: branch.work_time || '',
        end_time: branch.end_time || '',
        hour_price: branch.hour_price || '',
        fine_price: branch.fine_price || '',
        telegram_group_id: branch.telegram_group_id ?? '',
        latitude: branch.latitude ?? '',
        longitude: branch.longitude ?? '',
        status: branch.status ?? 1,
    });

    useEffect(() => {
        setData({
            name: branch.name || '',
            address: branch.address || '',
            comment: branch.comment || '',
            work_time: branch.work_time || '',
            end_time: branch.end_time || '',
            hour_price: branch.hour_price || '',
            fine_price: branch.fine_price || '',
            telegram_group_id: branch.telegram_group_id ?? '',
            latitude: branch.latitude ?? '',
            longitude: branch.longitude ?? '',
            status: branch.status ?? 1,
        });
    }, [branch]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(`/branch/${branch.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setOpen(false);
                toast.success(t('updated_successfully', 'Muvaffaqiyatli saqlandi'));
            },
            onError: (err) => {
                nameInput.current?.focus();
                const errorMessage = err?.error || t('update_failed', 'Xatolik yuz berdi');
                toast.error(errorMessage);
            }
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="!max-w-[95%] !w-[95vw] lg:!max-w-[85vw] lg:!w-[85vw] xl:!max-w-[75vw] xl:!w-[75vw] max-h-[90vh] overflow-y-auto rounded-2xl border-slate-200 dark:border-slate-800 p-6 bg-white dark:bg-slate-900 shadow-2xl">
                <DialogHeader className="space-y-1.5 pb-2">
                    <DialogTitle className="flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0">
                            <Pencil className="w-4 h-4" />
                        </div>
                        <span>{t('modal.update_title', 'Filialni Tahrirlash')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500 dark:text-slate-400">
                        <strong className="font-semibold text-slate-700 dark:text-slate-300">{branch.name}</strong> parametrlarini o‘zgartirish
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-6 pt-1">
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        {/* LEFT COLUMN: INPUTS */}
                        <div className="space-y-4">
                            <div className="rounded-xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50 space-y-3.5">
                                <div className="space-y-1.5">
                                    <Label htmlFor="edit_branch_name" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                        {t('name', 'Filial nomi')} <span className="text-rose-500">*</span>
                                    </Label>
                                    <Input
                                        id="edit_branch_name"
                                        ref={nameInput}
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Filial nomi"
                                        className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="edit_branch_address" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                        {t('address', 'Manzil')}
                                    </Label>
                                    <Input
                                        id="edit_branch_address"
                                        value={data.address}
                                        onChange={(e) => setData('address', e.target.value)}
                                        placeholder="Filial manzili"
                                        className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                                    />
                                    <InputError message={errors.address} />
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="edit_branch_work_time" className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                            {t('work_time', 'Boshlanish vaqti')}
                                        </Label>
                                        <TimePicker
                                            id="edit_branch_work_time"
                                            value={data.work_time}
                                            onChange={(time) => setData('work_time', time ?? '')}
                                            format="HH:mm"
                                            disableClock={true}
                                            className="block w-full h-9.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-800 dark:text-white"
                                        />
                                        <InputError message={errors.work_time} />
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="edit_branch_end_time" className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                            {t('end_time', 'Tugash vaqti')}
                                        </Label>
                                        <TimePicker
                                            id="edit_branch_end_time"
                                            value={data.end_time}
                                            onChange={(time) => setData('end_time', time ?? '')}
                                            format="HH:mm"
                                            disableClock={true}
                                            className="block w-full h-9.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-800 dark:text-white"
                                        />
                                        <InputError message={errors.end_time} />
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="edit_branch_hour_price" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                            {t('hour_price', 'Soatbay narx (so‘m)')}
                                        </Label>
                                        <Input
                                            id="edit_branch_hour_price"
                                            type="number"
                                            value={data.hour_price}
                                            onChange={(e) => setData('hour_price', e.target.value)}
                                            placeholder="0"
                                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                                        />
                                        <InputError message={errors.hour_price} />
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="edit_branch_fine_price" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                            {t('fine_price', 'Jarima narxi (so‘m)')}
                                        </Label>
                                        <Input
                                            id="edit_branch_fine_price"
                                            type="number"
                                            value={data.fine_price}
                                            onChange={(e) => setData('fine_price', e.target.value)}
                                            placeholder="0"
                                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                                        />
                                        <InputError message={errors.fine_price} />
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="edit_branch_telegram_group_id" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                            {t('telegram_group_id', 'Telegram Guruh ID')}
                                        </Label>
                                        <Input
                                            id="edit_branch_telegram_group_id"
                                            value={data.telegram_group_id}
                                            onChange={(e) => setData('telegram_group_id', e.target.value)}
                                            placeholder="-100..."
                                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                                        />
                                        <InputError message={errors.telegram_group_id} />
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="edit_branch_comment" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                            {t('comment', 'Izoh')}
                                        </Label>
                                        <Input
                                            id="edit_branch_comment"
                                            value={data.comment}
                                            onChange={(e) => setData('comment', e.target.value)}
                                            placeholder="Qo‘shimcha izoh"
                                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                                        />
                                        <InputError message={errors.comment} />
                                    </div>

                                    <div className="sm:col-span-2 pt-1">
                                        <Label htmlFor="edit_branch_status" className="text-xs font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                                            {t('status', 'Filial holati')}
                                        </Label>
                                        <label className="inline-flex cursor-pointer items-center gap-2.5 select-none">
                                            <input
                                                type="checkbox"
                                                id="edit_branch_status"
                                                className="peer sr-only"
                                                checked={data.status === 1}
                                                onChange={(e) => setData('status', e.target.checked ? 1 : 0)}
                                            />
                                            <div className="relative w-10 h-5.5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4.5 after:w-4.5 after:transition-all peer-checked:bg-emerald-600" />
                                            <span className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                                {data.status === 1 ? t('active', 'Faol') : t('inactive', 'Nofaol')}
                                            </span>
                                        </label>
                                        <InputError message={errors.status} />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* RIGHT COLUMN: MAP */}
                        <div className="flex flex-col gap-4">
                            <div className="rounded-xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50 h-full flex flex-col">
                                <Label className="mb-3 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                                    {t('location', 'Xarita Joylashuvi')}
                                </Label>
                                <div className="mb-3 grid grid-cols-2 gap-3">
                                    <div className="space-y-1">
                                        <Label htmlFor="edit_branch_latitude" className="text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                                            {t('latitude', 'Kenglik (Lat)')}
                                        </Label>
                                        <Input
                                            id="edit_branch_latitude"
                                            className="h-8.5 rounded-lg text-xs font-mono border-slate-200 dark:border-slate-700 dark:bg-slate-800"
                                            value={data.latitude}
                                            onChange={(e) => setData('latitude', e.target.value)}
                                            placeholder="41.2995"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label htmlFor="edit_branch_longitude" className="text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                                            {t('longitude', 'Uzunlik (Lng)')}
                                        </Label>
                                        <Input
                                            id="edit_branch_longitude"
                                            className="h-8.5 rounded-lg text-xs font-mono border-slate-200 dark:border-slate-700 dark:bg-slate-800"
                                            value={data.longitude}
                                            onChange={(e) => setData('longitude', e.target.value)}
                                            placeholder="69.2401"
                                        />
                                    </div>
                                </div>
                                <div className="flex-1 min-h-[220px] overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700 shadow-xs">
                                    <LocationPicker
                                        latitude={data.latitude}
                                        longitude={data.longitude}
                                        onChange={(lat, lng) => {
                                            setData((prev) => ({ ...prev, latitude: lat, longitude: lng }));
                                        }}
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter className="mt-2 border-t border-slate-100 dark:border-slate-800 pt-3 gap-2 flex items-center justify-end">
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

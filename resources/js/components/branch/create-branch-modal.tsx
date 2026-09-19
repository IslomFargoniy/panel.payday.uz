import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import 'react-time-picker/dist/TimePicker.css';
import TimePicker from 'react-time-picker';
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
import { Plus, Store } from 'lucide-react';
import { Firm } from '@/types';
import LocationPicker from '@/components/branch/location-picker';

interface createBranch {
    firm: Firm;
}

export default function CreateBranchModal({ firm }: createBranch) {
    const { t } = useTranslation();
    const nameInput = useRef<HTMLInputElement>(null);

    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
        firm_id: firm.id,
        name: '',
        address: '',
        comment: '',
        work_time: '',
        end_time: '',
        hour_price: '',
        fine_price: '',
        telegram_group_id: '',
        latitude: '',
        longitude: '',
        status: 1,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/branch', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('created_successfully', 'Muvaffaqiyatli saqlandi'));
                reset();
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
            <DialogTrigger asChild>
                <Button className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium h-8 px-2.5 rounded-lg shadow-xs flex items-center gap-1 text-xs">
                    <Plus className="w-3.5 h-3.5" />
                    <span>{t('create', 'Qo‘shish')}</span>
                </Button>
            </DialogTrigger>

            <DialogContent className="!max-w-[95%] !w-[95vw] lg:!max-w-[85vw] lg:!w-[85vw] xl:!max-w-[75vw] xl:!w-[75vw] max-h-[90vh] overflow-y-auto rounded-2xl border-slate-200 dark:border-slate-800">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <Store className="w-4 h-4" />
                        </div>
                        <span>{t('modal.create_title', 'Yangi Filial Qo‘shish')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500">
                        Firma: {firm.name}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-6">
                    <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                        {/* LEFT COLUMN: INPUTS */}
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="sm:col-span-2">
                                    <Label htmlFor="branch_name" className="text-xs">{t('name', 'Nomi')} *</Label>
                                    <Input id="branch_name" ref={nameInput} value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Filial nomi" />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="sm:col-span-2">
                                    <Label htmlFor="branch_address" className="text-xs">{t('address', 'Manzil')}</Label>
                                    <Input id="branch_address" value={data.address} onChange={(e) => setData('address', e.target.value)} placeholder="Filial manzili" />
                                    <InputError message={errors.address} />
                                </div>

                                <div>
                                    <Label htmlFor="branch_work_time" className="mb-1 block text-xs">{t('work_time', 'Boshlanish vaqti')}</Label>
                                    <TimePicker
                                        id="branch_work_time"
                                        value={data.work_time}
                                        onChange={(time) => setData('work_time', time ?? '')}
                                        format="HH:mm"
                                        locale="sv-sv"
                                        disableClock={true}
                                        className="block w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                    <InputError message={errors.work_time} />
                                </div>

                                <div>
                                    <Label htmlFor="branch_end_time" className="mb-1 block text-xs">{t('end_time', 'Tugash vaqti')}</Label>
                                    <TimePicker
                                        id="branch_end_time"
                                        value={data.end_time}
                                        onChange={(time) => setData('end_time', time ?? '')}
                                        format="HH:mm"
                                        locale="sv-sv"
                                        disableClock={true}
                                        className="block w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                    <InputError message={errors.end_time} />
                                </div>

                                <div>
                                    <Label htmlFor="branch_hour_price" className="text-xs">{t('hour_price', 'Soatbay narx')}</Label>
                                    <Input id="branch_hour_price" type="number" value={data.hour_price} onChange={(e) => setData('hour_price', e.target.value)} placeholder="0" />
                                    <InputError message={errors.hour_price} />
                                </div>

                                <div>
                                    <Label htmlFor="branch_fine_price" className="text-xs">{t('fine_price', 'Jarima narxi')}</Label>
                                    <Input id="branch_fine_price" type="number" value={data.fine_price} onChange={(e) => setData('fine_price', e.target.value)} placeholder="0" />
                                    <InputError message={errors.fine_price} />
                                </div>

                                <div>
                                    <Label htmlFor="branch_telegram_group_id" className="text-xs">{t('telegram_group_id', 'Telegram Guruh ID')}</Label>
                                    <Input id="branch_telegram_group_id" value={data.telegram_group_id} onChange={(e) => setData('telegram_group_id', e.target.value)} placeholder="-100..." />
                                    <InputError message={errors.telegram_group_id} />
                                </div>

                                <div>
                                    <Label htmlFor="branch_comment" className="text-xs">{t('comment', 'Izoh')}</Label>
                                    <Input id="branch_comment" value={data.comment} onChange={(e) => setData('comment', e.target.value)} placeholder="Qo‘shimcha izoh" />
                                    <InputError message={errors.comment} />
                                </div>
                            </div>
                        </div>

                        {/* RIGHT COLUMN: MAP */}
                        <div className="flex flex-col gap-4">
                            <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                                <Label className="mb-3 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">{t('location', 'Xarita Joylashuvi')}</Label>
                                <div className="mb-3 grid grid-cols-2 gap-3">
                                    <div>
                                        <Label htmlFor="branch_latitude" className="text-[11px] text-slate-500">{t('latitude', 'Kenglik (Lat)')}</Label>
                                        <Input id="branch_latitude" className="h-8 text-xs font-mono" value={data.latitude} onChange={(e) => setData('latitude', e.target.value)} />
                                    </div>
                                    <div>
                                        <Label htmlFor="branch_longitude" className="text-[11px] text-slate-500">{t('longitude', 'Uzunlik (Lng)')}</Label>
                                        <Input id="branch_longitude" className="h-8 text-xs font-mono" value={data.longitude} onChange={(e) => setData('longitude', e.target.value)} />
                                    </div>
                                </div>
                                <div className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700 shadow-xs">
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

                    <DialogFooter className="mt-2 border-t border-slate-200 dark:border-slate-800 pt-4 gap-2">
                        <DialogClose asChild>
                            <Button
                                variant="secondary"
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

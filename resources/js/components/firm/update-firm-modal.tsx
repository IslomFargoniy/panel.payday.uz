import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { Firm } from '@/types';
import DatePicker from 'react-datepicker';
import { MaskedDateInput } from '@/components/ui/masked-date-input';
import { format } from 'date-fns';
import { Pencil } from 'lucide-react';

interface UpdateFirmModalProps {
    firm: Firm;
    open: boolean;
    setOpen: (open: boolean) => void;
}

export default function UpdateFirmModal({ firm, open, setOpen }: UpdateFirmModalProps) {
    const { t } = useTranslation();
    const nameInput = useRef<HTMLInputElement>(null);

    const { data, setData, put, processing, reset, errors, clearErrors } = useForm({
        name: firm.name || '',
        address: firm.address || '',
        comment: firm.comment || '',
        branch_limit: firm.branch_limit || '',
        branch_price: firm.branch_price || '',
        valid_date: firm.valid_date || '',
        status: firm.status ?? 1
    });

    useEffect(() => {
        setData({
            name: firm.name || '',
            address: firm.address || '',
            comment: firm.comment || '',
            branch_limit: firm.branch_limit || '',
            branch_price: firm.branch_price || '',
            valid_date: firm.valid_date || '',
            status: firm.status ?? 1
        });
    }, [firm]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(`/firm/${firm.id}`, {
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
            <DialogContent className="rounded-2xl border-slate-200 dark:border-slate-800 max-w-md p-6 bg-white dark:bg-slate-900 shadow-xl">
                <DialogHeader className="space-y-1.5 pb-2">
                    <DialogTitle className="flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0">
                            <Pencil className="w-4 h-4" />
                        </div>
                        <span>{t('modal.update_title', 'Firmani Tahrirlash')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500 dark:text-slate-400">
                        <strong className="font-semibold text-slate-700 dark:text-slate-300">{firm.name}</strong> ma‘lumotlarini o‘zgartirish
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4 pt-1">
                    <div className="space-y-1.5">
                        <Label htmlFor="edit_name" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('name', 'Nomi')} <span className="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="edit_name"
                            ref={nameInput}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Firma nomi"
                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="edit_address" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('address', 'Manzil')}
                        </Label>
                        <Input
                            id="edit_address"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                            placeholder="Toshkent sh., ..."
                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.address} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="edit_comment" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('comment', 'Izoh')}
                        </Label>
                        <Input
                            id="edit_comment"
                            value={data.comment}
                            onChange={(e) => setData('comment', e.target.value)}
                            placeholder="Qo‘shimcha izoh"
                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.comment} />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div className="space-y-1.5">
                            <Label htmlFor="edit_branch_limit" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                {t('branch_limit', 'Filial limiti')}
                            </Label>
                            <Input
                                id="edit_branch_limit"
                                type="number"
                                value={data.branch_limit}
                                onChange={(e) => setData('branch_limit', e.target.value)}
                                placeholder="5"
                                className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                            />
                            <InputError message={errors.branch_limit} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="edit_branch_price" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                {t('branch_price', 'Filial narxi (so‘m)')}
                            </Label>
                            <Input
                                id="edit_branch_price"
                                type="number"
                                value={data.branch_price}
                                onChange={(e) => setData('branch_price', e.target.value)}
                                placeholder="0"
                                className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                            />
                            <InputError message={errors.branch_price} />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="edit_valid_date" className="text-xs font-medium text-slate-700 dark:text-slate-300 block">
                            {t('valid_date', 'Amal qilish muddati')}
                        </Label>
                        <DatePicker
                            id="edit_valid_date"
                            selected={data.valid_date ? new Date(data.valid_date) : null}
                            onChange={(date: Date | null) => {
                                setData('valid_date', date ? format(date, 'yyyy-MM-dd') : '');
                            }}
                            dateFormat="yyyy-MM-dd"
                            locale="sv-sv"
                            wrapperClassName="w-full"
                            customInput={
                                <MaskedDateInput
                                    className="block w-full h-9.5 px-3 py-2 text-xs sm:text-sm border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800 rounded-xl shadow-xs focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:text-white"
                                    placeholder="YYYY-MM-DD"
                                />
                            }
                        />
                        <InputError message={errors.valid_date} />
                    </div>

                    <div className="pt-1">
                        <Label htmlFor="edit_status" className="text-xs font-medium text-slate-700 dark:text-slate-300 mb-2 block">
                            {t('status', 'Firma holati')}
                        </Label>
                        <label className="inline-flex items-center gap-2.5 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                id="edit_status"
                                className="sr-only peer"
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

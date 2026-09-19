import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
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
    DialogTitle,
    DialogTrigger
} from '@/components/ui/dialog';
import { Building2, Plus } from 'lucide-react';
import DatePicker from 'react-datepicker';
import { format } from 'date-fns';

export default function CreateFirmModal() {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const nameInput = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
        name: '',
        address: '',
        comment: '',
        branch_limit: '',
        branch_price: '',
        valid_date: ''
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/firm', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setOpen(false);
                toast.success(t('created_successfully', 'Muvaffaqiyatli saqlandi'));
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

            <DialogContent className="rounded-2xl border-slate-200 dark:border-slate-800 max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <Building2 className="w-4 h-4" />
                        </div>
                        <span>{t('modal.create_title', 'Yangi Firma Qo‘shish')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500">
                        {t('modal.create_description', 'Yangi firma parametrlarini kiriting')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-3.5">
                    <div>
                        <Label htmlFor="name" className="text-xs">{t('name', 'Nomi')} *</Label>
                        <Input
                            id="name"
                            ref={nameInput}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Firma nomini kiriting"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div>
                        <Label htmlFor="address" className="text-xs">{t('address', 'Manzil')}</Label>
                        <Input
                            id="address"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                            placeholder="Toshkent sh., ..."
                        />
                        <InputError message={errors.address} />
                    </div>

                    <div>
                        <Label htmlFor="comment" className="text-xs">{t('comment', 'Izoh')}</Label>
                        <Input
                            id="comment"
                            value={data.comment}
                            onChange={(e) => setData('comment', e.target.value)}
                            placeholder="Qo‘shimcha izoh"
                        />
                        <InputError message={errors.comment} />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label htmlFor="branch_limit" className="text-xs">{t('branch_limit', 'Filial limiti')}</Label>
                            <Input
                                id="branch_limit"
                                type="number"
                                value={data.branch_limit}
                                onChange={(e) => setData('branch_limit', e.target.value)}
                                placeholder="5"
                            />
                            <InputError message={errors.branch_limit} />
                        </div>

                        <div>
                            <Label htmlFor="branch_price" className="text-xs">{t('branch_price', 'Filial narxi')}</Label>
                            <Input
                                id="branch_price"
                                type="number"
                                value={data.branch_price}
                                onChange={(e) => setData('branch_price', e.target.value)}
                                placeholder="0"
                            />
                            <InputError message={errors.branch_price} />
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="valid_date" className="text-xs block mb-1">
                            {t('valid_date', 'Amal qilish muddati')}
                        </Label>
                        <DatePicker
                            id="valid_date"
                            selected={data.valid_date ? new Date(data.valid_date) : null}
                            onChange={(date: Date | null) => {
                                setData('valid_date', date ? format(date, 'yyyy-MM-dd') : '');
                            }}
                            locale="sv-sv"
                            className="block w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 rounded-lg shadow-xs focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:text-white"
                            placeholderText="YYYY-MM-DD"
                        />
                        <InputError message={errors.valid_date} />
                    </div>

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

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
import { Plus, CalendarPlus } from 'lucide-react';
import { Branch } from '@/types';
import DatePicker from 'react-datepicker';
import { format } from 'date-fns';

interface createBranch {
    branch: Branch;
}

export default function CreateBranchHolidayModal({ branch }: createBranch) {
    const { t } = useTranslation();
    const nameInput = useRef<HTMLInputElement>(null);

    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
        branch_id: branch.id,
        name: '',
        date: '',
        comment: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/branch_holiday', {
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

            <DialogContent className="rounded-2xl border-slate-200 dark:border-slate-800 max-w-md p-6 bg-white dark:bg-slate-900 shadow-xl">
                <DialogHeader className="space-y-1.5 pb-2">
                    <DialogTitle className="flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                            <CalendarPlus className="w-4 h-4" />
                        </div>
                        <span>{t('modal.create_title', 'Dam Olish Kuni Qo‘shish')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500 dark:text-slate-400">
                        {t('modal.create_description', 'Filial uchun dam olish kunini belgilang')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4 pt-1">
                    <div className="space-y-1.5">
                        <Label htmlFor="name" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('name', 'Nomi')} <span className="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="name"
                            ref={nameInput}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Masalan: Navro‘z bayrami"
                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="date" className="text-xs font-medium text-slate-700 dark:text-slate-300 block">
                            {t('date', 'Sana')} <span className="text-rose-500">*</span>
                        </Label>
                        <DatePicker
                            id="date"
                            selected={data.date ? new Date(data.date) : null}
                            onChange={(date: Date | null) => {
                                setData('date', date ? format(date, 'yyyy-MM-dd') : '');
                            }}
                            dateFormat="yyyy-MM-dd"
                            locale="sv-sv"
                            wrapperClassName="w-full"
                            className="block w-full h-9.5 px-3 py-2 text-xs sm:text-sm border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800 rounded-xl shadow-xs focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:text-white"
                            placeholderText="YYYY-MM-DD"
                        />
                        <InputError message={errors.date} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="comment" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('comment', 'Izoh')}
                        </Label>
                        <Input
                            id="comment"
                            value={data.comment}
                            onChange={(e) => setData('comment', e.target.value)}
                            placeholder="Qo‘shimcha ma‘lumot (ixtiyoriy)"
                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.comment} />
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


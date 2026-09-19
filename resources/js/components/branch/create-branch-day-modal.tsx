import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
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
    DialogTitle,
    DialogTrigger
} from '@/components/ui/dialog';
import { Plus, Calendar } from 'lucide-react';
import { Branch, Day } from '@/types';

interface createBranch {
    branch: Branch;
    days: Day[];
}

type FormData = {
    branch_id: number;
    day_ids: number[];
};

export default function CreateBranchDayModal({ branch, days }: createBranch) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm<FormData>({
        branch_id: branch.id,
        day_ids: []
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/branch_day', {
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
                <Button className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium h-8 px-2.5 rounded-lg shadow-xs flex items-center gap-1 text-xs">
                    <Plus className="w-3.5 h-3.5" />
                    <span>{t('add_day', 'Ish kunlarini sozlash')}</span>
                </Button>
            </DialogTrigger>

            <DialogContent className="rounded-2xl border-slate-200 dark:border-slate-800 max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <Calendar className="w-4 h-4" />
                        </div>
                        <span>{t('modal.create_title', 'Ish Kunlarini Sozlash')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500">
                        {t('modal.create_description', 'Filial uchun haftalik ish kunlarini tanlang')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300 block mb-2">
                            {t('weekdays', 'Hafta kunlari')}
                        </Label>
                        <div className="grid grid-cols-2 gap-2">
                            {days.map((day) => {
                                const isChecked = data.day_ids.includes(day.id);
                                return (
                                    <label
                                        key={day.id}
                                        className={`flex items-center gap-2.5 p-2.5 rounded-xl border text-xs font-medium cursor-pointer transition-all ${
                                            isChecked
                                                ? 'border-indigo-600 bg-indigo-50/60 text-indigo-700 dark:border-indigo-500 dark:bg-indigo-950/40 dark:text-indigo-300 shadow-xs'
                                                : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800 dark:text-slate-300'
                                        }`}
                                    >
                                        <input
                                            type="checkbox"
                                            className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            value={day.id}
                                            checked={isChecked}
                                            onChange={(e) => {
                                                const checked = e.target.checked;
                                                if (checked) {
                                                    setData('day_ids', [...data.day_ids, day.id]);
                                                } else {
                                                    setData('day_ids', data.day_ids.filter(id => id !== day.id));
                                                }
                                            }}
                                        />
                                        <span>{day.name}</span>
                                    </label>
                                );
                            })}
                        </div>
                        <InputError message={errors.day_ids} />
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


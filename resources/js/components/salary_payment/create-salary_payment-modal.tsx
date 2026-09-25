import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
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
import { Plus, CreditCard } from 'lucide-react';
import { Worker } from '@/types';

interface PageProps {
    workers: Worker[];
}

export default function CreateSalaryPaymentModal({ workers }: PageProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const nameInput = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
        worker_id: 0,
        amount: 0,
        comment: ''
    });

    const workerOptions = workers.map((worker) => ({
        value: worker.id,
        label: worker.name,
        sublabel: worker?.balance !== undefined ? `${worker.balance.toLocaleString('ru-RU')} so‘m` : undefined,
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/salary_payment', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setOpen(false);
                toast.success(t('created_successfully'));
            },
            onError: (err) => {
                nameInput.current?.focus();
                const errorMessage = err?.error || t('create_failed');
                toast.error(errorMessage);
            }
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    className="h-8 gap-1.5 rounded-lg bg-indigo-600 px-3 text-xs font-medium text-white shadow-xs hover:bg-indigo-700 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                >
                    <Plus className="h-3.5 w-3.5" />
                    <span>{t('create')}</span>
                </Button>
            </DialogTrigger>

            <DialogContent className="max-w-md rounded-2xl border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                <DialogHeader className="space-y-1.5 pb-2">
                    <DialogTitle className="flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                            <CreditCard className="w-4 h-4" />
                        </div>
                        <span>{t('modal.create_title', 'Maosh To‘lovi')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500 dark:text-slate-400">
                        {t('modal.create_description', 'Xodimga to‘langan maosh summasini kiriting')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4 pt-1">
                    <div className="space-y-1.5">
                        <Label htmlFor="worker_id" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('worker', 'Xodim')} <span className="text-rose-500">*</span>
                        </Label>

                        <SearchableSelect
                            value={data.worker_id || ''}
                            onChange={(val) => setData('worker_id', typeof val === 'number' ? val : parseInt(String(val)) || 0)}
                            options={workerOptions}
                            placeholder={t('select', 'Xodimni tanlang')}
                            searchPlaceholder={t('search_worker', 'Xodimni qidirish...')}
                            triggerClassName="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />

                        <InputError message={errors.worker_id} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="amount" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('amount', 'Summa')} (so‘m) <span className="text-rose-500">*</span>
                        </Label>
                        <Input
                            type="number"
                            id="amount"
                            ref={nameInput}
                            value={data.amount > 0 ? data.amount : ''}
                            onChange={(e) => setData('amount', parseInt(e.target.value) || 0)}
                            placeholder="0"
                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.amount} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="comment" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('comment', 'Izoh')}
                        </Label>
                        <Input
                            id="comment"
                            value={data.comment}
                            onChange={(e) => setData('comment', e.target.value)}
                            placeholder="To‘lov bo‘yicha izoh (ixtiyoriy)"
                            className="h-9.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.comment} />
                    </div>

                    <DialogFooter className="mt-6 flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
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


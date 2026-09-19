import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem
} from '@/components/ui/select';
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
                <DialogHeader className="space-y-2">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                            <CreditCard className="h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle className="text-base font-semibold text-slate-900 dark:text-slate-100">
                                {t('modal.create_title')}
                            </DialogTitle>
                            <DialogDescription className="text-xs text-slate-500 dark:text-slate-400">
                                {t('modal.create_description')}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form onSubmit={submit} className="mt-4 space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="worker_id" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('worker')}
                        </Label>

                        <Select
                            value={data.worker_id ? data.worker_id.toString() : 'placeholder'}
                            onValueChange={(val) => {
                                if (val === 'placeholder') return;
                                setData('worker_id', parseInt(val));
                            }}
                        >
                            <SelectTrigger className="h-9 rounded-xl border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800">
                                <SelectValue placeholder={t('select')} />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl border-slate-200 dark:border-slate-800">
                                {workers.map((worker) => (
                                    <SelectItem
                                        key={worker.id}
                                        value={worker.id.toString()}
                                        className="text-xs"
                                    >
                                        {worker.name} ({worker?.balance?.toLocaleString('ru-RU')})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <InputError message={errors.worker_id} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="amount" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('amount')}
                        </Label>
                        <Input
                            type="number"
                            id="amount"
                            ref={nameInput}
                            value={data.amount > 0 ? data.amount : ''}
                            onChange={(e) => setData('amount', parseInt(e.target.value) || 0)}
                            className="h-9 rounded-xl border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.amount} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="comment" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('comment')}
                        </Label>
                        <Input
                            id="comment"
                            value={data.comment}
                            onChange={(e) => setData('comment', e.target.value)}
                            className="h-9 rounded-xl border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.comment} />
                    </div>

                    <DialogFooter className="mt-6 flex items-center justify-end gap-2 pt-2">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-9 rounded-lg border-slate-200 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800"
                                onClick={() => {
                                    reset();
                                    clearErrors();
                                    setOpen(false);
                                }}
                            >
                                {t('cancel')}
                            </Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            disabled={processing}
                            className="h-9 rounded-lg bg-indigo-600 px-4 text-xs font-medium text-white shadow-xs hover:bg-indigo-700 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                        >
                            {t('save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}


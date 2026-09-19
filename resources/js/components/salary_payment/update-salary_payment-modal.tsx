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
import { Pencil } from 'lucide-react';
import { SalaryPayment } from '@/types';

interface UpdateSalaryPaymentModalProps {
    salary_payment: SalaryPayment;
    open: boolean;
    setOpen: (open: boolean) => void;
}

export default function UpdateSalaryPaymentModal({ salary_payment, open, setOpen }: UpdateSalaryPaymentModalProps) {
    const { t } = useTranslation();
    const nameInput = useRef<HTMLInputElement>(null);

    const { data, setData, put, processing, reset, errors, clearErrors } = useForm({
        amount: salary_payment.amount,
        comment: salary_payment.comment,
    });

    useEffect(() => {
        setData({
            amount: salary_payment.amount,
            comment: salary_payment.comment,
        });
    }, [salary_payment, setData]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(`/salary_payment/${salary_payment.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setOpen(false);
                toast.success(t('updated_successfully'));
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
            <DialogContent className="max-w-md rounded-2xl border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                <DialogHeader className="space-y-2">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                            <Pencil className="h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle className="text-base font-semibold text-slate-900 dark:text-slate-100">
                                {t('modal.update_title')}
                            </DialogTitle>
                            <DialogDescription className="text-xs text-slate-500 dark:text-slate-400">
                                {t('modal.update_description')}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form onSubmit={submit} className="mt-4 space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="amount" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('amount')}
                        </Label>
                        <Input
                            type="number"
                            id="amount"
                            ref={nameInput}
                            value={data.amount}
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


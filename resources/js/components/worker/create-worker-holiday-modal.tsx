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
import { Worker } from '@/types';
import DatePicker from 'react-datepicker';
import { format } from 'date-fns';

interface createWorker {
    worker: Worker;
}

export default function CreateWorkerHolidayModal({ worker }: createWorker) {
    const { t } = useTranslation();
    const nameInput = useRef<HTMLInputElement>(null);

    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
        worker_id: worker.id,
        from: '',
        to: '',
        comment: ''
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post('/worker_holiday', {
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
                    <span>{t('add_holiday', 'Ta’til qo‘shish')}</span>
                </Button>
            </DialogTrigger>

            <DialogContent className="rounded-2xl border-slate-200 dark:border-slate-800 max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <CalendarPlus className="w-4 h-4" />
                        </div>
                        <span>{t('modal.create_title', 'Ta’til Qo‘shish')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500">
                        {worker.name} {t('modal.create_description', 'uchun ta’til muddatini belgilang')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-3.5">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label htmlFor="from" className="text-xs block mb-1">
                                {t('from', 'Boshlanishi')} *
                            </Label>
                            <DatePicker
                                id="from"
                                selected={data.from ? new Date(data.from) : null}
                                onChange={(from) => {
                                    setData('from', from ? format(from, 'yyyy-MM-dd') : '');
                                }}
                                locale="sv-sv"
                                className="block w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 rounded-lg shadow-xs focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:text-white"
                                placeholderText="YYYY-MM-DD"
                            />
                            <InputError message={errors.from} />
                        </div>
                        <div>
                            <Label htmlFor="to" className="text-xs block mb-1">
                                {t('to', 'Tugashi')} *
                            </Label>
                            <DatePicker
                                id="to"
                                selected={data.to ? new Date(data.to) : null}
                                onChange={(to) => {
                                    setData('to', to ? format(to, 'yyyy-MM-dd') : '');
                                }}
                                locale="sv-sv"
                                className="block w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 rounded-lg shadow-xs focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:text-white"
                                placeholderText="YYYY-MM-DD"
                            />
                            <InputError message={errors.to} />
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="comment" className="text-xs">{t('comment', 'Izoh')}</Label>
                        <Input
                            id="comment"
                            value={data.comment}
                            onChange={(e) => setData('comment', e.target.value)}
                            placeholder="Ta’til sababi yoki qo‘shimcha izoh"
                        />
                        <InputError message={errors.comment} />
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


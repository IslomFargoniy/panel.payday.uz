import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Report, SearchData } from '@/types';
import { useForm } from '@inertiajs/react';
import React, { FormEventHandler, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Calculator } from 'lucide-react';

interface CalculateSalaryProps {
    report: Report;
    search_data: SearchData;
}

const CalculateSalary = ({ report, search_data }: CalculateSalaryProps) => {
    const { t } = useTranslation();
    const nameInput = useRef<HTMLInputElement>(null);

    // Summani hisoblash va yaxlitlash (round)
    const initialAmount = Math.round(((report.worked_minutes - report.break_minutes) * (report?.hour_price ?? 0)) / 60);

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
        worker_id: search_data.worker_id,
        amount: initialAmount,
        worked_minute: report.worked_minutes,
        break_minute: report.break_minutes,
        hour_price: report.hour_price,
        from: report.from,
        to: report.to,
        comment: '',
    });

    // Raqamni "120 000" ko'rinishiga keltirish
    const formatNumber = (num: number | string) => {
        const value = String(num).replace(/\D/g, '');
        return value.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    };

    // Input o'zgarganda formatni buzmasdan raqamni saqlash
    const handleAmountChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const rawValue = e.target.value.replace(/\s/g, '');
        const numValue = parseInt(rawValue) || 0;
        setData('amount', numValue);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/salary`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setData('amount', 0);
                setData('comment', '');
                toast.success(t('created_successfully'));
            },
            onError: (err) => {
                nameInput.current?.focus();
                const errorMessage = err?.error || t('create_failed');
                toast.error(errorMessage);
            },
        });
    };

    return (
        <div className="space-y-3">
            <h3 className="text-sm font-semibold capitalize text-slate-800 dark:text-slate-200">
                {t('salary')}
            </h3>
            <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="mb-4 flex items-center gap-3">
                    <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                        <Calculator className="h-4 w-4" />
                    </div>
                    <div>
                        <h4 className="text-xs font-semibold text-slate-800 dark:text-slate-200">{t('calculate_salary')}</h4>
                        <p className="text-[11px] text-slate-500 dark:text-slate-400">{t('calculate_salary_description')}</p>
                    </div>
                </div>

                <form className="space-y-4" onSubmit={submit}>
                    <div className="space-y-1.5">
                        <Label htmlFor="amount" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('amount')}
                        </Label>
                        <Input
                            type="text"
                            id="amount"
                            ref={nameInput}
                            value={formatNumber(data.amount)}
                            onChange={handleAmountChange}
                            className="h-9 rounded-xl border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.amount} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="comment" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                            {t('comment')}
                        </Label>
                        <Input
                            type="text"
                            id="comment"
                            value={data.comment}
                            onChange={(e) => setData('comment', e.target.value)}
                            className="h-9 rounded-xl border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                        />
                        <InputError message={errors.comment} />
                    </div>

                    <div className="flex items-center justify-between pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-9 rounded-lg border-slate-200 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800"
                            onClick={() => {
                                reset();
                                clearErrors();
                            }}
                        >
                            {t('clear')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="h-9 rounded-lg bg-indigo-600 px-4 text-xs font-medium text-white shadow-xs hover:bg-indigo-700 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                        >
                            {t('save')}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default CalculateSalary;


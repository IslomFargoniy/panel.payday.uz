import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Report, SearchData } from '@/types';
import { router, useForm } from '@inertiajs/react';
import React, { FormEventHandler, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { AlertTriangle, Calculator, RefreshCw } from 'lucide-react';
import { isSalaryPeriodMismatch, resolveNextSalaryStartDate } from '@/lib/salary-date';

interface CalculateSalaryProps {
    report: Report;
    search_data: SearchData;
}

const CalculateSalary = ({ report, search_data }: CalculateSalaryProps) => {
    const { t } = useTranslation();
    const nameInput = useRef<HTMLInputElement>(null);

    // Summani hisoblash va yaxlitlash (round) - jarimani avtomatik ayirish
    const totalEarned = (report.worked_minutes * (report?.hour_price ?? 0)) / 60;
    const totalFine = ((report.late_minutes ?? 0) / 60) * (report?.fine_price ?? 0);
    const initialAmount = Math.max(0, Math.round(totalEarned - totalFine));

    const initialFrom = resolveNextSalaryStartDate(report?.last_salary_date, report.from, report.to);

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
        worker_id: search_data.worker_id,
        amount: initialAmount,
        worked_minute: report.worked_minutes,
        break_minute: 0,
        hour_price: report.hour_price,
        from: initialFrom,
        to: report.to || '',
        comment: '',
    });

    const isMismatch = isSalaryPeriodMismatch(report.from, report.to, data.from, data.to);

    const handleRecalculate = () => {
        router.get(
            '/salary_report',
            {
                ...search_data,
                page: 1,
                from: data.from,
                to: data.to,
            },
            {
                // preserveState: false — sahifa to'liq qayta yaratiladi, shunda forma (summa, daqiqalar, narx)
                // yangi hisobot qiymatlari bilan qayta initsializatsiya qilinadi.
                preserveState: false,
                preserveScroll: true,
            }
        );
    };

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
        if (isMismatch) {
            toast.error("Tanlangan davr bo'yicha hisobotni qayta hisoblang.");
            return;
        }
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
                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="from" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                {t('from')}
                            </Label>
                            <Input
                                type="date"
                                id="from"
                                value={data.from}
                                onChange={(e) => setData('from', e.target.value)}
                                className="h-9 rounded-xl border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                            />
                            <InputError message={errors.from} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="to" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                {t('to')}
                            </Label>
                            <Input
                                type="date"
                                id="to"
                                value={data.to}
                                onChange={(e) => setData('to', e.target.value)}
                                className="h-9 rounded-xl border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 dark:border-slate-800 dark:bg-slate-800"
                            />
                            <InputError message={errors.to} />
                        </div>
                    </div>

                    {isMismatch && (
                        <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300 space-y-2">
                            <div className="flex items-start gap-2">
                                <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
                                <span>
                                    Tanlangan davr ({data.from} — {data.to}) hisobot davridan ({report.from} — {report.to}) farq qiladi. Summa va daqiqalar to'g'ri bo'lishi uchun hisobotni qayta hisoblang.
                                </span>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleRecalculate}
                                className="w-full h-8 border-amber-300 bg-amber-100/70 text-amber-900 hover:bg-amber-200/70 dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                            >
                                <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                                Shu oraliq bo'yicha qayta hisoblash
                            </Button>
                        </div>
                    )}

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
                            disabled={processing || isMismatch}
                            className="h-9 rounded-lg bg-indigo-600 px-4 text-xs font-medium text-white shadow-xs hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-indigo-600 dark:hover:bg-indigo-500"
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


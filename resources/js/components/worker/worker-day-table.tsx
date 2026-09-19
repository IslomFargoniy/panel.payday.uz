import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Day, Worker, WorkerDay } from '@/types';
import { toast } from 'sonner';
import { Calendar, Check, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

type WorkerDayTableProps = {
    worker: Worker;
    days?: Day[];
};

// Fallback days metadata if DB days not loaded yet
const defaultWeekDays = [
    { id: 2, name: 'Dushanba', name_ru: 'Понедельnik', name_en: 'Monday', short_uz: 'Du', short_ru: 'Пн', short_en: 'Mon' },
    { id: 3, name: 'Seshanba', name_ru: 'Вторник', name_en: 'Tuesday', short_uz: 'Se', short_ru: 'Вт', short_en: 'Tue' },
    { id: 4, name: 'Chorshanba', name_ru: 'Среда', name_en: 'Wednesday', short_uz: 'Chor', short_ru: 'Ср', short_en: 'Wed' },
    { id: 5, name: 'Payshanba', name_ru: 'Четверг', name_en: 'Thursday', short_uz: 'Pay', short_ru: 'Чт', short_en: 'Thu' },
    { id: 6, name: 'Juma', name_ru: 'Пятница', name_en: 'Friday', short_uz: 'Jum', short_ru: 'Пт', short_en: 'Fri' },
    { id: 7, name: 'Shanba', name_ru: 'Суббота', name_en: 'Saturday', short_uz: 'Sha', short_ru: 'Сб', short_en: 'Sat' },
    { id: 1, name: 'Yakshanba', name_ru: 'Воскресенье', name_en: 'Sunday', short_uz: 'Yak', short_ru: 'Вс', short_en: 'Sun' },
];

const WorkerDayTable = ({ worker, days }: WorkerDayTableProps) => {
    const { i18n, t } = useTranslation();
    const [loadingDayId, setLoadingDayId] = useState<number | null>(null);

    // Week order starting from Monday (id: 2) through Sunday (id: 1)
    const weekOrder = [2, 3, 4, 5, 6, 7, 1];

    const allDays = (days && days.length > 0) ? days : defaultWeekDays;
    const sortedDays = [...allDays].sort((a, b) => {
        const indexA = weekOrder.indexOf(a.id);
        const indexB = weekOrder.indexOf(b.id);
        return (indexA === -1 ? 99 : indexA) - (indexB === -1 ? 99 : indexB);
    });

    const getWorkerDay = (dayId: number): WorkerDay | undefined => {
        return worker.worker_days?.find((item) => item.day_id === dayId || item.day?.id === dayId);
    };

    const handleToggleDay = (dayId: number) => {
        const existingWorkerDay = getWorkerDay(dayId);
        setLoadingDayId(dayId);

        if (existingWorkerDay) {
            router.delete(`/worker_day/${existingWorkerDay.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setLoadingDayId(null);
                    toast.success(t('deleted_successfully', 'Muvaffaqiyatli o‘chirildi'));
                },
                onError: (err) => {
                    setLoadingDayId(null);
                    toast.error(err?.error || t('action_failed', 'Xatolik yuz berdi'));
                },
                onFinish: () => setLoadingDayId(null),
            });
        } else {
            router.post('/worker_day', {
                worker_id: worker.id,
                day_ids: [dayId],
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    setLoadingDayId(null);
                    toast.success(t('created_successfully', 'Muvaffaqiyatli saqlandi'));
                },
                onError: (err) => {
                    setLoadingDayId(null);
                    toast.error(err?.error || t('action_failed', 'Xatolik yuz berdi'));
                },
                onFinish: () => setLoadingDayId(null),
            });
        }
    };

    const activeDaysCount = worker.worker_days?.length || 0;

    return (
        <div className="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs dark:border-slate-800 dark:bg-slate-900 space-y-2.5">
            <div className="flex items-center justify-between">
                <h3 className="font-semibold text-xs uppercase tracking-wider text-slate-600 dark:text-slate-300 flex items-center gap-1.5">
                    <Calendar className="w-4 h-4 text-indigo-500 dark:text-indigo-400" />
                    <span>{t('worker_day', 'Xodim ish kunlari')}</span>
                </h3>
                <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full">
                    {activeDaysCount} / 7 {t('days', 'kun')}
                </span>
            </div>

            {/* Single row of 7 day toggle pills */}
            <div className="grid grid-cols-7 gap-1.5 pt-1">
                {sortedDays.map((day) => {
                    const isActive = !!getWorkerDay(day.id);
                    const isLoading = loadingDayId === day.id;

                    const defaultMeta = defaultWeekDays.find((d) => d.id === day.id);
                    const dayName = i18n.language === 'uz' ? (day.name || defaultMeta?.name) :
                        i18n.language === 'ru' ? (day.name_ru || defaultMeta?.name_ru) :
                            (day.name_en || defaultMeta?.name_en);

                    const shortLabel = i18n.language === 'uz' ? (defaultMeta?.short_uz || day.name?.slice(0, 3)) :
                        i18n.language === 'ru' ? (defaultMeta?.short_ru || day.name_ru?.slice(0, 2)) :
                            (defaultMeta?.short_en || day.name_en?.slice(0, 3));

                    return (
                        <button
                            key={day.id}
                            type="button"
                            disabled={isLoading}
                            onClick={() => handleToggleDay(day.id)}
                            title={dayName}
                            className={cn(
                                "flex flex-col items-center justify-center py-2 px-0.5 rounded-lg border transition-all cursor-pointer select-none relative group",
                                isActive
                                    ? "bg-indigo-600 text-white border-indigo-600 shadow-xs hover:bg-indigo-700 dark:bg-indigo-500 dark:border-indigo-500 dark:hover:bg-indigo-600"
                                    : "bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700/80 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200 hover:border-slate-300 dark:hover:border-slate-600"
                            )}
                        >
                            {isLoading ? (
                                <Loader2 className="w-3.5 h-3.5 animate-spin my-1" />
                            ) : (
                                <>
                                    <span className="text-[11px] font-bold leading-tight">{shortLabel}</span>
                                    {isActive ? (
                                        <Check className="w-3 h-3 mt-0.5 text-indigo-200 dark:text-indigo-100" />
                                    ) : (
                                        <span className="w-1.5 h-1.5 mt-1 rounded-full bg-slate-300 dark:bg-slate-600 group-hover:bg-slate-400" />
                                    )}
                                </>
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
};

export default WorkerDayTable;

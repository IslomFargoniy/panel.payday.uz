import { Appearance, useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { LucideIcon, Monitor, Moon, Sun } from 'lucide-react';
import { HTMLAttributes } from 'react';
import { useTranslation } from 'react-i18next';

export default function AppearanceToggleTab({ className = '', ...props }: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();
    const { t } = useTranslation();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: t('appearance_toggle.light', 'Yoritilgan') },
        { value: 'dark', icon: Moon, label: t('appearance_toggle.dark', 'Qorong‘u') },
        { value: 'system', icon: Monitor, label: t('appearance_toggle.system', 'Tizim') },
    ];

    return (
        <div className={cn('inline-flex gap-1.5 rounded-xl bg-slate-100 p-1 border border-slate-200/80 dark:bg-slate-900 dark:border-slate-800', className)} {...props}>
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    type="button"
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        'flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-xs font-medium transition-all cursor-pointer',
                        appearance === value
                            ? 'bg-white text-indigo-600 shadow-xs dark:bg-slate-800 dark:text-indigo-400 font-semibold'
                            : 'text-slate-500 hover:bg-slate-200/50 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-slate-200'
                    )}
                >
                    <Icon className="h-4 w-4" />
                    <span>{label}</span>
                </button>
            ))}
        </div>
    );
}

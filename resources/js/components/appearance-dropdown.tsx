import React from 'react';
import { useTranslation } from 'react-i18next';
import { Check, Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Appearance, useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

interface AppearanceDropdownProps {
    className?: string;
}

export default function AppearanceToggleDropdown({ className }: AppearanceDropdownProps) {
    const { appearance, updateAppearance } = useAppearance();
    const { t } = useTranslation();

    const themes: { value: Appearance; icon: React.ElementType; label: string; iconColor: string }[] = [
        {
            value: 'light',
            icon: Sun,
            label: t('appearance_toggle.light', 'Yoritilgan'),
            iconColor: 'text-amber-500',
        },
        {
            value: 'dark',
            icon: Moon,
            label: t('appearance_toggle.dark', 'Qorong‘u'),
            iconColor: 'text-indigo-400',
        },
        {
            value: 'system',
            icon: Monitor,
            label: t('appearance_toggle.system', 'Tizim'),
            iconColor: 'text-slate-500 dark:text-slate-400',
        },
    ];

    return (
        <div className={cn('relative', className)}>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8.5 w-8.5 p-0 rounded-xl border border-slate-200/90 dark:border-slate-800 bg-white/90 dark:bg-slate-900/90 shadow-2xs hover:bg-slate-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 focus-visible:ring-1 focus-visible:ring-indigo-500 flex items-center justify-center cursor-pointer"
                        title={t('appearance.title', 'Ko‘rinish')}
                    >
                        {appearance === 'dark' ? (
                            <Moon className="h-4 w-4 text-indigo-400 transition-transform duration-200 hover:-rotate-12" />
                        ) : appearance === 'light' ? (
                            <Sun className="h-4 w-4 text-amber-500 transition-transform duration-200 hover:rotate-12" />
                        ) : (
                            <Monitor className="h-4 w-4 text-slate-600 dark:text-slate-300" />
                        )}
                        <span className="sr-only">Toggle theme</span>
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent
                    align="end"
                    sideOffset={6}
                    className="w-38 rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-1.5 shadow-xl"
                >
                    {themes.map((item) => {
                        const Icon = item.icon;
                        const isActive = appearance === item.value;
                        return (
                            <DropdownMenuItem
                                key={item.value}
                                onClick={() => updateAppearance(item.value)}
                                className={cn(
                                    'flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium cursor-pointer transition-colors',
                                    isActive
                                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold'
                                        : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100/80 dark:hover:bg-slate-800/70'
                                )}
                            >
                                <span className="flex items-center gap-2">
                                    <Icon className={cn('h-4 w-4', item.iconColor)} />
                                    <span>{item.label}</span>
                                </span>
                                {isActive && (
                                    <Check className="h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400 shrink-0" />
                                )}
                            </DropdownMenuItem>
                        );
                    })}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

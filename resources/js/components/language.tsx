import React from 'react';
import { useTranslation } from 'react-i18next';
import { Check, ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

interface LanguageOption {
    code: 'uz' | 'ru' | 'en';
    label: string;
    short: string;
    flag: string;
}

const languages: LanguageOption[] = [
    { code: 'uz', label: "O‘zbekcha", short: "O‘zb", flag: '🇺🇿' },
    { code: 'ru', label: 'Русский', short: 'Рус', flag: '🇷🇺' },
    { code: 'en', label: 'English', short: 'Eng', flag: '🇬🇧' },
];

interface LanguageBarProps {
    className?: string;
}

const LanguageBar = ({ className }: LanguageBarProps) => {
    const { i18n } = useTranslation();

    const currentCode = (i18n.language || 'uz').toLowerCase().startsWith('ru')
        ? 'ru'
        : (i18n.language || 'uz').toLowerCase().startsWith('en')
        ? 'en'
        : 'uz';

    const currentLang = languages.find((l) => l.code === currentCode) || languages[0];

    const changeLanguage = (lang: string) => {
        i18n.changeLanguage(lang);
        localStorage.setItem('lang', lang);
        document.cookie = `locale=${lang};path=/;max-age=31536000;SameSite=Lax`;
        document.cookie = `lang=${lang};path=/;max-age=31536000;SameSite=Lax`;
    };

    return (
        <div className={cn('relative', className)}>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8.5 px-2.5 rounded-xl border border-slate-200/90 dark:border-slate-800 bg-white/90 dark:bg-slate-900/90 shadow-2xs hover:bg-slate-50 dark:hover:bg-slate-800/80 text-xs font-medium text-slate-700 dark:text-slate-200 gap-1.5 focus-visible:ring-1 focus-visible:ring-indigo-500 cursor-pointer"
                    >
                        <span className="text-sm leading-none">{currentLang.flag}</span>
                        <span className="hidden sm:inline font-medium">{currentLang.label}</span>
                        <span className="inline sm:hidden font-medium">{currentLang.short}</span>
                        <ChevronDown className="h-3.5 w-3.5 text-slate-400 opacity-70 ml-0.5" />
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent
                    align="end"
                    sideOffset={6}
                    className="w-40 rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-1.5 shadow-xl"
                >
                    {languages.map((lang) => {
                        const isActive = currentCode === lang.code;
                        return (
                            <DropdownMenuItem
                                key={lang.code}
                                onClick={() => changeLanguage(lang.code)}
                                className={cn(
                                    'flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium cursor-pointer transition-colors',
                                    isActive
                                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold'
                                        : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100/80 dark:hover:bg-slate-800/70'
                                )}
                            >
                                <span className="flex items-center gap-2">
                                    <span className="text-sm leading-none">{lang.flag}</span>
                                    <span>{lang.label}</span>
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
};

export default LanguageBar;

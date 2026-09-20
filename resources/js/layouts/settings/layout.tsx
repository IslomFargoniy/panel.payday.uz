import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { User, Lock, Palette } from 'lucide-react';

const sidebarNavItems: (Omit<NavItem, 'icon'> & { icon: React.ComponentType<{ className?: string }> })[] = [
    {
        title: 'Profile',
        href: '/settings/profile',
        icon: User,
    },
    {
        title: 'Password',
        href: '/settings/password',
        icon: Lock,
    },
    {
        title: 'Appearance',
        href: '/settings/appearance',
        icon: Palette,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { t } = useTranslation();

    if (typeof window === 'undefined') {
        return null;
    }

    const currentPath = window.location.pathname;

    return (
        <div className="px-4 py-6 max-w-6xl mx-auto">
            <Heading title={t('settings_layout.title')} description={t('settings_layout.description')} />

            <div className="mt-6 flex flex-col space-y-6 lg:flex-row lg:space-y-0 lg:space-x-10">
                {/* Mobile Tabs & Desktop Sidebar */}
                <aside className="w-full lg:w-52 shrink-0">
                    <nav className="flex items-center gap-1.5 p-1 bg-slate-100/90 dark:bg-slate-850/80 rounded-2xl overflow-x-auto no-scrollbar sm:gap-2 lg:bg-transparent lg:p-0 lg:flex-col lg:items-stretch lg:space-y-1.5 border border-slate-200/60 dark:border-slate-800/60 lg:border-none">
                        {sidebarNavItems.map((item, index) => {
                            const isActive = currentPath === item.href;
                            const Icon = item.icon;
                            return (
                                <Button
                                    key={`${item.href}-${index}`}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className={cn(
                                        'flex-1 justify-center rounded-xl px-3.5 py-2 text-xs font-semibold transition-all whitespace-nowrap lg:justify-start lg:w-full lg:px-3 lg:py-2.5',
                                        isActive
                                            ? 'bg-white text-indigo-600 shadow-xs dark:bg-slate-900 dark:text-indigo-400 font-bold lg:bg-indigo-50/80 lg:text-indigo-600 dark:lg:bg-indigo-950/40 dark:lg:text-indigo-400'
                                            : 'text-slate-600 hover:text-slate-900 hover:bg-white/60 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800/50'
                                    )}
                                >
                                    <Link href={item.href} prefetch className="flex items-center justify-center lg:justify-start gap-2">
                                        <Icon className={cn('w-4 h-4 shrink-0', isActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500')} />
                                        <span>{t(`settings_layout.${item.title.toLowerCase()}`)}</span>
                                    </Link>
                                </Button>
                            );
                        })}
                    </nav>
                </aside>

                {/* Main Settings Content */}
                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-8">{children}</section>
                </div>
            </div>
        </div>
    );
}

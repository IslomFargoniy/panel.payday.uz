import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem
} from '@/components/ui/sidebar';
import { Auth, type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    BookOpen,
    Folder,
    LayoutGrid,
    Briefcase,
    Clock,
    BarChart3,
    Receipt,
    HandCoins,
    Github,
    Send,
    Users,
    Building2,
    Calendar
} from 'lucide-react';
import AppLogo from './app-logo';
import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';


export function AppSidebar() {

    const { t, i18n } = useTranslation();

    const footerNavItems: NavItem[] = [
        {
            title: t('sidebar.repository'),
            href: 'https://github.com/IslomFargoniy',
            icon: Github
        },
        {
            title: t('sidebar.telegram'),
            href: 'https://t.me/IslomFargoniy',
            icon: Send
        }
    ];


    const { auth } = usePage().props as unknown as { auth?: Auth };

    const isAdmin = auth?.user?.roles?.some(role => role.name === 'Admin');

    const filteredNavItems = useMemo((): NavItem[] => {
        const items: NavItem[] = [
            {
                title: t('sidebar.dashboard'),
                href: '/dashboard', icon: LayoutGrid
            },
            {
                title: t('sidebar.user'),
                href: '/user',
                icon: Users
            },
            {
                title: t('sidebar.firm'),
                href: '/firm',
                icon: Building2
            },
            {
                title: t('sidebar.worker'),
                href: '/worker',
                icon: Briefcase
            },
            {
                title: t('sidebar.attendance'),
                href: '/attendance',
                icon: Clock
            },
            {
                title: t('sidebar.report'),
                href: '/monthly_attendance',
                icon: BarChart3
            },
            {
                title: t('sidebar.salary_report'),
                href: '/salary_report',
                icon: Receipt
            },
            {
                title: t('sidebar.salary'),
                href: '/salary',
                icon: HandCoins
            },
            {
                title: t('sidebar.salary_payment'),
                href: '/salary_payment',
                icon: HandCoins
            }
        ];

        return items.filter(item => {
            if (item.href === '/user' && !isAdmin) return false;
            return true;
        });
    }, [isAdmin, i18n.language]);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={filteredNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

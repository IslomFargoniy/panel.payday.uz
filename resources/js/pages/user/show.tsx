import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import AppLayout from '@/layouts/app-layout';
import { Head, usePage, Link } from '@inertiajs/react';
import {
    type BreadcrumbItem as LayoutBreadcrumbItem,
    type User, Firm
} from '@/types';
import UserFirmTable from '@/components/user/user-firm-table';
import { useTranslation } from 'react-i18next';
import { User as UserIcon } from 'lucide-react';


export default function UserShow() {
    const { user, firms } = usePage<{
        user: User,
        firms: Firm[]
    }>().props;
    const { t } = useTranslation();  // Using the translation hook

    const breadcrumbs: LayoutBreadcrumbItem[] = [
        {
            title: `${t('user')} ( ${user.name} )`,
            href: '/dashboard'
        }
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('user')}: ${user.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 md:p-6 min-w-0 max-w-full">
                {/* Search and Per-Page Selection */}
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between bg-white dark:bg-slate-900 p-3.5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs min-w-0 max-w-full">
                    <Breadcrumb className="min-w-0">
                        <BreadcrumbList className="text-xs sm:text-sm font-medium flex-wrap">
                            <BreadcrumbItem>
                                <BreadcrumbLink asChild>
                                    <Link href="/user" className="flex items-center gap-1.5 text-muted-foreground hover:text-primary transition-colors">
                                        <UserIcon className="h-4 w-4" />
                                        <span>{t('user')}</span>
                                    </Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbPage className="font-semibold text-foreground px-2.5 py-1 rounded-md bg-muted text-xs">
                                    {user?.name}
                                </BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>

                {/* Table */}
                <div className="w-full min-w-0 max-w-full">
                    <UserFirmTable user={user} firms={firms} />
                </div>
            </div>
        </AppLayout>
    );
}

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
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Search and Per-Page Selection */}
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between bg-card p-3 rounded-xl border border-border shadow-xs">
                    <Breadcrumb>
                        <BreadcrumbList className="text-sm font-medium">
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
                                <BreadcrumbPage className="font-semibold text-foreground px-2.5 py-1 rounded-md bg-muted">
                                    {user?.name}
                                </BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>

                {/* Table */}
                <div className="overflow-x-auto">
                    <UserFirmTable user={user} firms={firms} />
                </div>
            </div>
        </AppLayout>
    );
}

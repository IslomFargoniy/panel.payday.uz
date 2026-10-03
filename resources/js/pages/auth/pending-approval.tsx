import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Clock, LogOut, RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { User } from '@/types';

export default function PendingApproval() {
    const { t } = useTranslation();
    const { auth } = usePage<{ auth: { user: User } }>().props;
    const user = auth?.user;

    const { post, processing } = useForm();

    const handleLogout = (e: React.FormEvent) => {
        e.preventDefault();
        post('/logout');
    };

    const handleCheckStatus = () => {
        window.location.href = '/dashboard';
    };

    return (
        <AuthLayout
            title={t('pending_approval.title', 'Hisobingiz kutilmoqda')}
            description={t('pending_approval.description', 'Tizimdan foydalanish uchun superadmin tasdig‘i talab qilinadi')}
        >
            <Head title={t('pending_approval.title', 'Hisob tasdiqlanishi kutilmoqda')} />

            <div className="flex flex-col items-center text-center space-y-6">
                {/* Icon with glowing aura */}
                <div className="relative flex items-center justify-center">
                    <div className="absolute h-20 w-20 rounded-full bg-amber-400/20 blur-xl dark:bg-amber-500/20" />
                    <div className="relative flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50 border border-amber-200/80 shadow-xs dark:bg-amber-950/40 dark:border-amber-800/60">
                        <Clock className="h-8 w-8 text-amber-600 dark:text-amber-400 animate-pulse" />
                    </div>
                </div>

                {/* User info box */}
                {user && (
                    <div className="w-full rounded-xl border border-slate-200/80 bg-slate-50/70 p-4 text-left dark:border-slate-800 dark:bg-slate-900/60">
                        <div className="flex items-center justify-between gap-2">
                            <div className="min-w-0 flex-1">
                                <p className="font-semibold text-sm text-slate-800 dark:text-slate-200 truncate">
                                    {user.name}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400 truncate">
                                    {user.email || user.phone || '—'}
                                </p>
                            </div>
                            <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                {t('user_status_waiting', 'Kutilmoqda')}
                            </span>
                        </div>
                    </div>
                )}

                {/* Informative text */}
                <p className="text-xs text-slate-600 dark:text-slate-300 leading-relaxed max-w-sm">
                    {t(
                        'pending_approval.info_message',
                        'Sizning hisobingiz tizim ma\'muri (Superadmin) tasdiqlash jarayonida. Tasdiqlanganingizdan so‘ng sizga panelga kirish imkoni beriladi.'
                    )}
                </p>

                {/* Action buttons */}
                <div className="w-full space-y-2 pt-2">
                    <Button
                        type="button"
                        onClick={handleCheckStatus}
                        className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-sm transition-all"
                    >
                        <RefreshCw className="mr-2 h-4 w-4" />
                        {t('pending_approval.check_status', 'Holatni tekshirish')}
                    </Button>

                    <form onSubmit={handleLogout} className="w-full">
                        <Button
                            type="submit"
                            variant="ghost"
                            disabled={processing}
                            className="w-full text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200"
                        >
                            <LogOut className="mr-2 h-4 w-4" />
                            {t('pending_approval.logout', 'Tizimdan chiqish')}
                        </Button>
                    </form>
                </div>
            </div>
        </AuthLayout>
    );
}

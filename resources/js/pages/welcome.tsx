import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import LanguageBar from '@/components/language';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { useTranslation } from 'react-i18next';
import { ShieldCheck, Users, Clock, ArrowRight } from 'lucide-react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title="PayDay - Boshqaruv Tizimi">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
            </Head>
            <div className="flex min-h-screen flex-col bg-slate-50 text-slate-900 transition-colors dark:bg-[#0c0f17] dark:text-slate-100">
                {/* Header */}
                <header className="sticky top-0 z-50 w-full border-b border-slate-200/80 bg-white/80 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/80">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-3">
                            <AppLogoIcon className="size-9 shadow-sm" />
                            <span className="text-xl font-bold tracking-tight">
                                Pay<span className="text-indigo-600 dark:text-indigo-400">Day</span>
                            </span>
                        </div>

                        <div className="flex items-center gap-3">
                            <LanguageBar />
                            <AppearanceToggleDropdown />
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                                >
                                    Dashboard
                                    <ArrowRight className="size-4" />
                                </Link>
                            ) : (
                                <Link
                                    href={route('login')}
                                    className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                                >
                                    {t('login.sign_in', 'Tizimga kirish')}
                                    <ArrowRight className="size-4" />
                                </Link>
                            )}
                        </div>
                    </div>
                </header>

                {/* Main Hero */}
                <main className="flex-1">
                    <div className="relative isolate overflow-hidden">
                        {/* Background glow */}
                        <div
                            className="absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-top-80"
                            aria-hidden="true"
                        >
                            <div
                                className="relative left-[calc(50%-11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-indigo-500 to-sky-400 opacity-20 sm:left-[calc(50%-30rem)] sm:w-[72.1875rem]"
                            />
                        </div>

                        <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
                            <div className="mx-auto max-w-2xl text-center">
                                <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-indigo-200/80 bg-indigo-50/80 px-4 py-1.5 text-xs font-semibold text-indigo-700 shadow-xs dark:border-indigo-900/60 dark:bg-indigo-950/40 dark:text-indigo-300">
                                    <span className="flex size-2 rounded-full bg-emerald-500" />
                                    PayDay Professional Management Panel
                                </div>

                                <h1 className="text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
                                    Xodimlar davomati va oylik hisob-kitobi tizimi
                                </h1>

                                <p className="mt-6 text-base leading-7 text-slate-600 sm:text-lg dark:text-slate-400">
                                    Hikvision terminallari bilan to'g'ridan-to'g'ri integratsiya, daqiqalik aniqlikdagi davomat jadvallari va shaffof maosh hisoblash platformasi.
                                </p>

                                <div className="mt-10 flex items-center justify-center gap-4">
                                    <Link
                                        href={auth.user ? route('dashboard') : route('login')}
                                        className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-indigo-500/25 transition-all hover:bg-indigo-700 hover:shadow-indigo-500/40 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                                    >
                                        {auth.user ? "Boshqaruv paneliga o'tish" : "Tizimga kirish"}
                                        <ArrowRight className="size-5" />
                                    </Link>
                                </div>
                            </div>

                            {/* Features Grid */}
                            <div className="mt-20 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                <div className="rounded-2xl border border-slate-200/80 bg-white/70 p-7 shadow-xs backdrop-blur-sm dark:border-slate-800/80 dark:bg-slate-900/60">
                                    <div className="mb-4 inline-flex size-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                                        <Clock className="size-6" />
                                    </div>
                                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                                        Avtomatik Davomat
                                    </h3>
                                    <p className="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                        Terminallardan keladigan yuzni tanish hodisalarini avtomatlashtirilgan tarzda qabul qilish va kechikishlarni soniyagacha hisoblash.
                                    </p>
                                </div>

                                <div className="rounded-2xl border border-slate-200/80 bg-white/70 p-7 shadow-xs backdrop-blur-sm dark:border-slate-800/80 dark:bg-slate-900/60">
                                    <div className="mb-4 inline-flex size-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                        <Users className="size-6" />
                                    </div>
                                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                                        Filiallar & Xodimlar
                                    </h3>
                                    <p className="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                        Barcha filiallar va ularning ish rejimlarini yagona markazlashtirilgan panel orqali nazorat qilish.
                                    </p>
                                </div>

                                <div className="rounded-2xl border border-slate-200/80 bg-white/70 p-7 shadow-xs backdrop-blur-sm dark:border-slate-800/80 dark:bg-slate-900/60">
                                    <div className="mb-4 inline-flex size-12 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-950/50 dark:text-sky-400">
                                        <ShieldCheck className="size-6" />
                                    </div>
                                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                                        Oylik & To'lovlar
                                    </h3>
                                    <p className="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                        Haqiqiy ishlangan soatlar va kechikishlar asosida shaffof hisobotlar hamda Excel eksport imkoniyati.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>

                {/* Footer */}
                <footer className="border-t border-slate-200/80 bg-white/50 py-6 text-center text-xs text-slate-500 backdrop-blur-sm dark:border-slate-800/80 dark:bg-slate-950/50 dark:text-slate-400">
                    <p>© {new Date().getFullYear()} PayDay. Barcha huquqlar himoyalangan.</p>
                </footer>
            </div>
        </>
    );
}

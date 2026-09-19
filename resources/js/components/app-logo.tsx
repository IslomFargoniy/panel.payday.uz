import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <div className="flex items-center gap-2.5">
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg shadow-xs transition-transform hover:scale-105">
                <AppLogoIcon className="size-8" />
            </div>

            <div className="grid flex-1 text-left">
                <span className="truncate text-base font-bold tracking-tight text-slate-900 dark:text-white">
                    Pay<span className="text-indigo-600 dark:text-indigo-400">Day</span>
                </span>
            </div>
        </div>
    );
}

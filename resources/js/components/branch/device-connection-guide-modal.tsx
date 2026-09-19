import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Branch } from '@/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { BookOpen, Check, Copy, HelpCircle, Network, ShieldCheck, Zap } from 'lucide-react';
import { toast } from 'sonner';

interface DeviceConnectionGuideModalProps {
    branch: Branch;
}

export default function DeviceConnectionGuideModal({ branch }: DeviceConnectionGuideModalProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [activeTab, setActiveTab] = useState<'isup' | 'http'>('isup');
    const [copiedKey, setCopiedKey] = useState<string | null>(null);

    const copyToClipboard = (text: string, label: string, key: string) => {
        navigator.clipboard.writeText(text);
        setCopiedKey(key);
        toast.success(`${label} ${t('copied', 'nusxalandi!')}`);
        setTimeout(() => setCopiedKey(null), 2000);
    };

    const isupDeviceId = `branch${branch.id}`;
    const isupKey = branch.id === 14 ? 'PayDay14!2026' : `PayDay${branch.id}2026`;
    const serverIp = '193.180.213.188';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    className="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800"
                >
                    <BookOpen className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                    <span>{t('device_guide', 'Qurilmani ulash yo‘riqnomasi')}</span>
                </Button>
            </DialogTrigger>

            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto dark:border-gray-700">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg font-bold text-gray-900 dark:text-white">
                        <HelpCircle className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        Hikvision Qurilmasini Serverga Ulash Yo‘riqnomasi
                    </DialogTitle>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        Filial: <strong className="text-gray-800 dark:text-gray-200">{branch.name}</strong> (ID: {branch.id})
                    </p>
                </DialogHeader>

                {/* Tab switcher */}
                <div className="flex rounded-lg bg-gray-100 dark:bg-gray-800 p-1 gap-1 my-2">
                    <button
                        type="button"
                        onClick={() => setActiveTab('isup')}
                        className={`flex-1 flex items-center justify-center gap-2 py-2 px-3 text-xs font-semibold rounded-md transition-all ${
                            activeTab === 'isup'
                                ? 'bg-white dark:bg-gray-700 text-blue-600 dark:text-blue-400 shadow-sm'
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900'
                        }`}
                    >
                        <Zap className="w-4 h-4 text-amber-500" />
                        <span>1-Usul: ISUP 5.0 (2 tomonlama avtomatik)</span>
                        <span className="text-[10px] bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 px-1.5 py-0.5 rounded font-bold">
                            Tavsiya
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('http')}
                        className={`flex-1 flex items-center justify-center gap-2 py-2 px-3 text-xs font-semibold rounded-md transition-all ${
                            activeTab === 'http'
                                ? 'bg-white dark:bg-gray-700 text-blue-600 dark:text-blue-400 shadow-sm'
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900'
                        }`}
                    >
                        <Network className="w-4 h-4 text-blue-500" />
                        <span>2-Usul: HTTP Listening (Klassik)</span>
                    </button>
                </div>

                {/* Tab 1: ISUP 5.0 */}
                {activeTab === 'isup' && (
                    <div className="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                        <div className="p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900 rounded-lg text-xs leading-relaxed text-blue-800 dark:text-blue-300">
                            <strong>✨ ISUP 5.0 (2-tomonlama to‘liq integratsiya):</strong>
                            <ul className="list-disc list-inside mt-1 space-y-0.5">
                                <li><strong>Statik IP kerak emas</strong> (oddiy Wi-Fi yoki 4G orqali ishlaydi).</li>
                                <li><strong>Xodimlar + Yuz rasmlari:</strong> Panelda yangi xodim qo‘shilganda terminalga avtomatik yuklanadi.</li>
                                <li><strong>Keldi-Ketdi (Davomat):</strong> Xodim yuzini skaner qilganda davomat real vaqtda ISUP orqali serverga yoziladi.</li>
                            </ul>
                        </div>

                        <div className="space-y-3">
                            <h4 className="font-semibold text-gray-900 dark:text-white flex items-center gap-1.5 text-xs">
                                <span className="flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white text-[10px]">1</span>
                                Hikvision Web paneliga kiring va menyuni oching:
                            </h4>
                            <div className="p-2.5 bg-gray-50 dark:bg-gray-800/80 rounded border border-gray-200 dark:border-gray-700 font-mono text-xs text-gray-800 dark:text-gray-200">
                                Configuration ➔ Network ➔ Device Access ➔ <strong className="text-blue-600 dark:text-blue-400">ISUP</strong>
                            </div>

                            <h4 className="font-semibold text-gray-900 dark:text-white flex items-center gap-1.5 text-xs">
                                <span className="flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white text-[10px]">2</span>
                                Quyidagi parametrlarni kiriting:
                            </h4>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                                <div className="p-2.5 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                    <div>
                                        <div className="text-gray-500 dark:text-gray-400 text-[11px]">Enable:</div>
                                        <div className="font-semibold text-emerald-600 dark:text-emerald-400 font-mono">ON (Yoqilgan)</div>
                                    </div>
                                </div>

                                <div className="p-2.5 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                    <div>
                                        <div className="text-gray-500 dark:text-gray-400 text-[11px]">Protocol Version:</div>
                                        <div className="font-semibold font-mono">ISUP 5.0</div>
                                    </div>
                                </div>

                                <div className="p-2.5 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                    <div>
                                        <div className="text-gray-500 dark:text-gray-400 text-[11px]">Server IP Address:</div>
                                        <div className="font-semibold font-mono text-blue-600 dark:text-blue-400">{serverIp}</div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => copyToClipboard(serverIp, 'Server IP', 'serverIp')}
                                        className="h-7 px-2"
                                    >
                                        {copiedKey === 'serverIp' ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                                    </Button>
                                </div>

                                <div className="p-2.5 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                    <div>
                                        <div className="text-gray-500 dark:text-gray-400 text-[11px]">Port:</div>
                                        <div className="font-semibold font-mono">7660</div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => copyToClipboard('7660', 'Port', 'port')}
                                        className="h-7 px-2"
                                    >
                                        {copiedKey === 'port' ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                                    </Button>
                                </div>

                                <div className="p-2.5 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                    <div>
                                        <div className="text-gray-500 dark:text-gray-400 text-[11px]">Device ID:</div>
                                        <div className="font-semibold font-mono text-purple-600 dark:text-purple-400">{isupDeviceId}</div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => copyToClipboard(isupDeviceId, 'Device ID', 'deviceId')}
                                        className="h-7 px-2"
                                    >
                                        {copiedKey === 'deviceId' ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                                    </Button>
                                </div>

                                <div className="p-2.5 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                    <div>
                                        <div className="text-gray-500 dark:text-gray-400 text-[11px]">Encryption Key:</div>
                                        <div className="font-semibold font-mono">{isupKey}</div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => copyToClipboard(isupKey, 'Encryption Key', 'isupKey')}
                                        className="h-7 px-2"
                                    >
                                        {copiedKey === 'isupKey' ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                                    </Button>
                                </div>
                            </div>

                            <h4 className="font-semibold text-gray-900 dark:text-white flex items-center gap-1.5 text-xs">
                                <span className="flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white text-[10px]">3</span>
                                Saqlash:
                            </h4>
                            <p className="text-xs text-gray-600 dark:text-gray-400 pl-6">
                                <strong>Save</strong> tugmasini bosing. 5-10 soniya ichida Register Status: <strong className="text-emerald-600">🟢 Online</strong> bo‘ladi.
                            </p>
                        </div>
                    </div>
                )}

                {/* Tab 2: HTTP Listening */}
                {activeTab === 'http' && (
                    <div className="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                        <div className="p-3 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 rounded-lg text-xs leading-relaxed">
                            <strong>ℹ️ HTTP Listening haqida:</strong> Bu usulda terminal har safar xodim kelganda serverga HTTP webhook yuboradi.
                        </div>

                        <div className="space-y-3">
                            <h4 className="font-semibold text-gray-900 dark:text-white flex items-center gap-1.5 text-xs">
                                <span className="flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white text-[10px]">1</span>
                                Hikvision Web paneliga kiring va menyuni oching:
                            </h4>
                            <div className="p-2.5 bg-gray-50 dark:bg-gray-800/80 rounded border border-gray-200 dark:border-gray-700 font-mono text-xs text-gray-800 dark:text-gray-200">
                                Configuration ➔ Network ➔ Network Service (yoki Advanced) ➔ <strong className="text-blue-600 dark:text-blue-400">HTTP Listening</strong>
                            </div>

                            <h4 className="font-semibold text-gray-900 dark:text-white flex items-center gap-1.5 text-xs">
                                <span className="flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white text-[10px]">2</span>
                                Quyidagi parametrlarni kiriting:
                            </h4>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                                <div className="p-2.5 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                    <div>
                                        <div className="text-gray-500 dark:text-gray-400 text-[11px]">Event Alarm IP / Domain Name:</div>
                                        <div className="font-semibold font-mono text-blue-600 dark:text-blue-400">panel.payday.uz</div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => copyToClipboard('panel.payday.uz', 'Domain', 'domain')}
                                        className="h-7 px-2"
                                    >
                                        {copiedKey === 'domain' ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                                    </Button>
                                </div>

                                <div className="p-2.5 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                    <div>
                                        <div className="text-gray-500 dark:text-gray-400 text-[11px]">URL:</div>
                                        <div className="font-semibold font-mono text-blue-600 dark:text-blue-400">/api/hikvision-callback</div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => copyToClipboard('/api/hikvision-callback', 'URL', 'url')}
                                        className="h-7 px-2"
                                    >
                                        {copiedKey === 'url' ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                                    </Button>
                                </div>

                                <div className="p-2.5 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                    <div>
                                        <div className="text-gray-500 dark:text-gray-400 text-[11px]">Port:</div>
                                        <div className="font-semibold font-mono">80</div>
                                    </div>
                                </div>

                                <div className="p-2.5 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                    <div>
                                        <div className="text-gray-500 dark:text-gray-400 text-[11px]">Protocol:</div>
                                        <div className="font-semibold font-mono">HTTP</div>
                                    </div>
                                </div>
                            </div>

                            <h4 className="font-semibold text-gray-900 dark:text-white flex items-center gap-1.5 text-xs">
                                <span className="flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white text-[10px]">3</span>
                                Saqlash:
                            </h4>
                            <p className="text-xs text-gray-600 dark:text-gray-400 pl-6">
                                <strong>Save</strong> tugmasini bosing. So‘ng terminalga yuzingizni ko‘rsatsangiz, keldi-ketdilar to‘g‘ridan-to‘g‘ri serverga uzatiladi.
                            </p>
                        </div>
                    </div>
                )}

                <DialogFooter className="mt-4">
                    <DialogClose asChild>
                        <Button variant="secondary" size="sm">
                            {t('close', 'Yopish')}
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

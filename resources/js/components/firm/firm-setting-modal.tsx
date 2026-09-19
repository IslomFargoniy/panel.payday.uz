import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import { Firm } from '@/types';
import { Settings } from 'lucide-react';

interface FirmSettingModalProps {
    firm: Firm;
    open: boolean;
    setOpen: (open: boolean) => void;
}

export default function FirmSettingModal({ firm, open, setOpen }: FirmSettingModalProps) {
    const { t } = useTranslation();
    const nameInput = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
        firm_id: firm.id,
        webhook_url: firm.firm_setting?.webhook_url || ''
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(`/firm_setting`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setOpen(false);
                toast.success(t('updated_successfully', 'Muvaffaqiyatli saqlandi'));
            },
            onError: (err) => {
                nameInput.current?.focus();
                const errorMessage = err?.error || t('save_failed', 'Xatolik yuz berdi');
                toast.error(errorMessage);
            }
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="rounded-2xl border-slate-200 dark:border-slate-800 max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                        <div className="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <Settings className="w-4 h-4" />
                        </div>
                        <span>{t('modal.setting_title', 'Firma Sozlamalari')}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500">
                        Firma: {firm.name}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-3.5">
                    <div>
                        <Label htmlFor="webhook_url" className="text-xs">{t('webhook_url', 'Webhook URL')}</Label>
                        <Input
                            id="webhook_url"
                            ref={nameInput}
                            value={data.webhook_url}
                            onChange={(e) => setData('webhook_url', e.target.value)}
                            placeholder="https://example.com/api/webhook"
                        />
                        <InputError message={errors.webhook_url} />
                    </div>

                    <DialogFooter className="gap-2 pt-2">
                        <DialogClose asChild>
                            <Button
                                variant="secondary"
                                type="button"
                                size="sm"
                                onClick={() => {
                                    reset();
                                    clearErrors();
                                    setOpen(false);
                                }}
                            >
                                {t('cancel', 'Bekor qilish')}
                            </Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            size="sm"
                            disabled={processing}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium"
                        >
                            {t('save', 'Saqlash')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

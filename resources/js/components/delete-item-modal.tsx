import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogDescription
} from '@/components/ui/dialog';
import { useTranslation } from 'react-i18next';
import { AlertTriangle } from 'lucide-react';

interface DeleteItemModalProps {
    item: { id: number, name?: string };
    open: boolean;
    setOpen: (open: boolean) => void;
    onDelete: (id: number) => void; // Callback function to handle deletion
}

export default function DeleteItemModal({ item, open, setOpen, onDelete }: DeleteItemModalProps) {
    const { t } = useTranslation();

    const handleDelete = () => {
        onDelete(item.id); // Call the onDelete function passed as a prop
        setOpen(false); // Close the modal
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="rounded-2xl border-slate-200 dark:border-slate-800 max-w-sm">
                <DialogHeader>
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 mb-2">
                        <AlertTriangle className="h-6 w-6" />
                    </div>
                    <DialogTitle className="text-center text-base font-bold text-slate-900 dark:text-slate-100">
                        {t('modal.delete_title', 'O‘chirishni tasdiqlang')}
                    </DialogTitle>
                    <DialogDescription className="text-center text-xs text-slate-500 mt-1">
                        {t('modal.delete_confirmation', 'Haqiqatan ham ushbu ma’lumotni o‘chirmoqchimisiz?')}
                        {item.name && (
                            <span className="block mt-1 font-semibold text-slate-700 dark:text-slate-300">
                                «{item.name}»
                            </span>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="grid grid-cols-2 gap-2 mt-4 sm:space-x-0">
                    <DialogClose asChild>
                        <Button variant="secondary" size="sm" onClick={() => setOpen(false)} className="w-full">
                            {t('cancel', 'Bekor qilish')}
                        </Button>
                    </DialogClose>
                    <Button variant="destructive" size="sm" onClick={handleDelete} className="w-full bg-rose-600 hover:bg-rose-700 text-white font-medium">
                        {t('delete', 'O‘chirish')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}


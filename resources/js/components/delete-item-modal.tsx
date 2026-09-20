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
            <DialogContent className="rounded-2xl border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900 max-w-sm">
                <DialogHeader>
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 mb-2">
                        <AlertTriangle className="h-6 w-6" />
                    </div>
                    <DialogTitle className="text-center text-base font-bold text-slate-900 dark:text-slate-100">
                        {t('modal.delete_title', 'O‘chirishni tasdiqlang')}
                    </DialogTitle>
                    <DialogDescription className="text-center text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                        {t('modal.delete_confirmation', 'Haqiqatan ham ushbu ma’lumotni o‘chirmoqchimisiz?')}
                        {item.name && (
                            <span className="block mt-1 font-semibold text-slate-800 dark:text-slate-200">
                                «{item.name}»
                            </span>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="grid grid-cols-2 gap-2 mt-5 sm:space-x-0 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <DialogClose asChild>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                            className="h-9 rounded-xl border-slate-200 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800 w-full"
                        >
                            {t('cancel', 'Bekor qilish')}
                        </Button>
                    </DialogClose>
                    <Button
                        onClick={handleDelete}
                        className="h-9 rounded-xl bg-rose-600 hover:bg-rose-700 text-xs font-semibold text-white shadow-xs w-full"
                    >
                        {t('delete', 'O‘chirish')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}



import React, { useState } from 'react';
import { CheckCircle, MinusCircle, PencilIcon, TrashIcon, EyeIcon } from 'lucide-react';
import { Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Branch, SearchData, type Worker, WorkerPaginate } from '@/types';
import DeleteItemModal from '@/components/delete-item-modal';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import CreateWorkerModal from '@/components/branch/create-worker-modal';
import UpdateWorkerModal from '@/components/branch/update-worker-modal';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';


type WorkerTableProps = {
    worker: WorkerPaginate;
    branch?: Branch
    searchData: SearchData;
};

const WorkerTable = ({ worker, branch, searchData }: WorkerTableProps) => {

    console.log(worker);

    const { t } = useTranslation();  // Using the translation hook
    const [open, setOpen] = useState(false);
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedWorker, setSelectedWorker] = useState<Worker | null>(null);
    const [zoomedImage, setZoomedImage] = useState<string | null>(null);

    const handleUpdateClick = (worker: Worker) => {
        setSelectedWorker(worker);
        setOpen(true);
    };

    const handleDeleteClick = (worker: Worker) => {
        setSelectedWorker(worker);
        setOpenDelete(true);
    };
    const { delete: deleteWorker, reset, clearErrors } = useForm();

    const handleDelete = (id: number) => {

        deleteWorker(`/worker/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setOpenDelete(false); // 🔒 CLOSE MODAL HERE
                toast.success(t('deleted_successfully')); // Success message
            },
            onError: (err) => {
                // Display a friendly error message if available
                const errorMessage = err?.error || t('delete_failed'); // Use fallback error message
                toast.error(errorMessage); // Display error message
            }
        });
    };

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between px-1">
                <div className="flex items-center gap-2">
                    <h3 className="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100 capitalize">
                        {t('worker')}
                    </h3>
                    <span className="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-400">
                        {worker.total || 0} {t('workers_count', 'nafar')}
                    </span>
                </div>
            </div>

            {/* Table Card Container */}
            <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-4 py-3 text-center w-12 font-mono">{t('n')}</th>
                                <th className="px-4 py-3 text-center w-16">{t('avatar')}</th>
                                <th className="px-4 py-3">{t('worker')} / {t('employeeNoString')}</th>
                                <th className="px-4 py-3">{t('phone')} / {t('address')}</th>
                                <th className="px-4 py-3 whitespace-nowrap">{t('work_time')} - {t('end_time')}</th>
                                <th className="px-4 py-3 whitespace-nowrap">{t('hour_price')} / {t('fine_price')}</th>
                                <th className="px-4 py-3 text-center">{t('status')} / {t('balance')}</th>
                                <th className="px-4 py-3 text-right w-28">
                                    {branch && <CreateWorkerModal branch={branch} />}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {(!worker.data || worker.data.length === 0) ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-10 text-center text-xs text-slate-400 dark:text-slate-500">
                                        {t('no_workers_found', 'Hozircha xodimlar mavjud emas.')}
                                    </td>
                                </tr>
                            ) : (
                                worker.data.map((item, index) => {
                                    const globalIndex = (worker.current_page - 1) * worker.per_page + index + 1;
                                    const balance = Number(item.balance || 0);
                                    return (
                                        <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-4 py-3 text-center text-xs font-mono text-slate-400 dark:text-slate-500">
                                                {globalIndex}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <div className="flex items-center justify-center">
                                                    {item.avatar ? (
                                                        <img
                                                            src={`/storage/${item.avatar}`}
                                                            alt={item.name || "Avatar"}
                                                            className="h-10 w-10 rounded-full object-cover ring-2 ring-slate-100 dark:ring-slate-800 shadow-xs cursor-zoom-in transition-transform duration-200 hover:scale-110"
                                                            onClick={() => setZoomedImage(`/storage/${item.avatar}`)}
                                                            onError={(e) => {
                                                                const target = e.currentTarget;
                                                                target.style.display = 'none';
                                                                const sibling = target.nextElementSibling as HTMLElement;
                                                                if (sibling) {
                                                                    sibling.style.display = 'flex';
                                                                }
                                                            }}
                                                        />
                                                    ) : null}
                                                    <div
                                                        className={`h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-indigo-700 ring-2 ring-slate-100 dark:bg-indigo-950/50 dark:text-indigo-300 dark:ring-slate-800 text-xs font-bold ${item.avatar ? 'hidden' : 'flex'}`}
                                                    >
                                                        {item.name ? item.name.slice(0, 2).toUpperCase() : 'N/A'}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-semibold text-slate-900 dark:text-slate-100 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                    <Link href={`/worker/${item.id}`}>{item.name}</Link>
                                                </div>
                                                <div className="text-xs text-slate-400 dark:text-slate-500 mt-0.5 font-mono">
                                                    ID: {item.employeeNoString || item.id}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-slate-800 dark:text-slate-200 text-xs">
                                                    {item.phone || <span className="text-slate-400 italic">—</span>}
                                                </div>
                                                <div className="text-xs text-slate-400 dark:text-slate-500 truncate max-w-[160px] mt-0.5" title={item.address}>
                                                    {item.address || '—'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 font-mono">
                                                    {item.work_time?.slice(0, 5)} - {item.end_time?.slice(0, 5)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs">
                                                <div className="flex items-center gap-1.5 font-medium">
                                                    <span className="text-emerald-600 dark:text-emerald-400">
                                                        {Number(item.hour_price || 0).toLocaleString()}
                                                    </span>
                                                    <span className="text-slate-300 dark:text-slate-600">/</span>
                                                    <span className="text-rose-600 dark:text-rose-400">
                                                        {Number(item.fine_price || 0).toLocaleString()}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <div className="flex flex-col items-center gap-1">
                                                    {item.status == 1 ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200/50 dark:border-emerald-800/30">
                                                            <CheckCircle className="h-3 w-3" /> {t('active', 'Faol')}
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200/50 dark:border-rose-800/30">
                                                            <MinusCircle className="h-3 w-3" /> {t('inactive', 'No-faol')}
                                                        </span>
                                                    )}
                                                    <div className={`text-xs font-bold font-mono ${balance < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
                                                        {balance.toLocaleString()}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="inline-flex items-center rounded-lg border border-slate-200 bg-white p-0.5 shadow-2xs dark:border-slate-700 dark:bg-slate-800">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                        className="h-7 w-7 p-0 text-slate-500 hover:text-slate-900 dark:hover:text-white"
                                                    >
                                                        <Link href={`/worker/show_history/${item.id}`} title={t('view_history', 'Tarixni ko‘rish')}>
                                                            <EyeIcon className="h-3.5 w-3.5" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleUpdateClick(item)}
                                                        className="h-7 w-7 p-0 text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400"
                                                        title={t('edit', 'Tahrirlash')}
                                                    >
                                                        <PencilIcon className="h-3.5 w-3.5" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDeleteClick(item)}
                                                        className="h-7 w-7 p-0 text-slate-500 hover:text-rose-600 dark:hover:text-rose-400"
                                                        title={t('delete', 'O‘chirish')}
                                                    >
                                                        <TrashIcon className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>

                        {/* Place the UpdateBranchModal here */}
                        {selectedWorker && open && <UpdateWorkerModal worker={selectedWorker} open={open} setOpen={setOpen} />}

                        {/* Pass selected branch to the DeleteBranchModal */}
                        {selectedWorker && openDelete && (
                            <DeleteItemModal
                                item={selectedWorker}
                                open={openDelete}
                                setOpen={setOpenDelete}
                                onDelete={handleDelete}
                            />
                        )}

                        {/* Lightbox for Zoomed Image */}
                        <Dialog open={!!zoomedImage} onOpenChange={(open) => !open && setZoomedImage(null)}>
                            <DialogContent className="sm:max-w-3xl p-1 bg-transparent border-none shadow-none overflow-hidden">
                                <DialogHeader className="sr-only">
                                    <DialogTitle>{t('avatar')}</DialogTitle>
                                </DialogHeader>
                                <div className="flex items-center justify-center w-full h-full p-4">
                                    {zoomedImage && (
                                        <img
                                            src={zoomedImage}
                                            alt="Zoomed Avatar"
                                            className="max-w-full max-h-[85vh] rounded-xl shadow-2xl object-contain animate-in zoom-in-95 duration-200"
                                        />
                                    )}
                                </div>
                            </DialogContent>
                        </Dialog>
                    </table>
                </div>

                {/* Pagination */}
                {worker.total > 0 && (
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-200/80 bg-slate-50/50 px-4 py-3 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400">
                        <div>
                            {t('showing', {
                                from: worker.from || 0,
                                to: worker.to || 0,
                                total: worker.total || 0,
                            })}
                        </div>
                        <div className="flex flex-wrap gap-1">
                            {worker.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={`${link.url ?? '?'}&search=${searchData.search}&per_page=${searchData.per_page}&firm_id=${searchData.firm_id}&branch_id=${searchData.branch_id}`}
                                    className={`rounded-md px-2.5 py-1 text-xs font-medium transition ${
                                        link.active
                                            ? 'bg-indigo-600 text-white shadow-xs'
                                            : !link.url
                                              ? 'cursor-not-allowed text-slate-300 dark:text-slate-600'
                                              : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default WorkerTable;

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
        <div>
            <h3 className={'py-2 text-center capitalize'}>{t('worker')}</h3>

            {/* Table */}
            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-left text-sm text-gray-800 dark:text-gray-100">
                    <thead className="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">{t('n')}</td>
                            <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">{t('avatar')}</td>
                            <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">{t('worker')} / {t('employeeNoString')}</td>
                            <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">{t('phone')} / {t('address')}</td>
                            <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">{t('work_time')} - {t('end_time')}</td>
                            <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">{t('hour_price')} / {t('fine_price')}</td>
                            <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">{t('status')} / {t('balance')}</td>
                            <th className="border border-gray-300 px-4 py-2 dark:border-gray-600">
                                {branch && <CreateWorkerModal branch={branch} />}
                            </th>
                        </tr>
                    </thead>
                    <tbody className="bg-white dark:bg-gray-800">
                        {worker.data?.map((item, index) => {
                            const globalIndex = (worker.current_page - 1) * worker.per_page + index + 1;
                            return (
                                <tr key={item.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">{globalIndex}</td>
                                    <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">
                                        <div className="flex items-center justify-center">
                                            {item.avatar ? (
                                                <img
                                                    src={`/storage/${item.avatar}`}
                                                    alt={item.name || "Avatar"}
                                                    className="h-10 w-10 rounded-full object-cover cursor-zoom-in transition-transform hover:scale-110"
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
                                                className={`h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200 text-xs font-semibold ${item.avatar ? 'hidden' : 'flex'}`}
                                            >
                                                {item.name ? item.name.slice(0, 2).toUpperCase() : 'N/A'}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">
                                        <div className="font-semibold text-blue-600 dark:text-blue-400">
                                            <Link href={`/worker/${item.id}`}>{item.name}</Link>
                                        </div>
                                        <div className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            ID: {item.employeeNoString}
                                        </div>
                                    </td>
                                    <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">
                                        <div className="font-medium">{item.phone}</div>
                                        <div className="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[150px]" title={item.address}>
                                            {item.address}
                                        </div>
                                    </td>
                                    <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">
                                        <div className="whitespace-nowrap font-medium text-gray-700 dark:text-gray-300">
                                            {item.work_time?.slice(0, 5)} - {item.end_time?.slice(0, 5)}
                                        </div>
                                    </td>
                                    <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">
                                        <div className="text-xs">
                                            <span className="text-green-600 dark:text-green-400 font-semibold">{Number(item.hour_price).toLocaleString()}</span>
                                            <span className="mx-1 text-gray-400">/</span>
                                            <span className="text-red-600 dark:text-red-400 font-semibold">{Number(item.fine_price).toLocaleString()}</span>
                                        </div>
                                    </td>
                                    <td className="border border-gray-300 px-4 py-2 dark:border-gray-600 text-center">
                                        <div className="flex flex-col items-center gap-1">
                                            {item.status == 1 ? (
                                                <span className="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                    <CheckCircle className="mr-1 h-3 w-3" /> Faol
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                                    <MinusCircle className="mr-1 h-3 w-3" /> No-faol
                                                </span>
                                            )}
                                            <div className={`text-xs font-bold ${Number(item.balance) < 0 ? 'text-red-500' : 'text-green-500'}`}>
                                                {Number(item.balance).toLocaleString()}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="border border-gray-300 px-4 py-2 dark:border-gray-600">
                                        <div className="flex justify-center shadow-sm">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                                className="h-8 w-8 p-0 rounded-r-none"
                                            >
                                                <Link href={`/worker/show_history/${item.id}`}>
                                                    <EyeIcon className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleUpdateClick(item)}
                                                className="h-8 w-8 p-0 rounded-none border-x-0"
                                            >
                                                <PencilIcon className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleDeleteClick(item)}
                                                className="h-8 w-8 p-0 rounded-l-none text-red-500 hover:text-red-600"
                                            >
                                                <TrashIcon className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>

                    {/* Place the UpdateBranchModal here */}
                    {selectedWorker && open && <UpdateWorkerModal worker={selectedWorker} open={open} setOpen={setOpen} />}

                    {/* Pass selected branch to the DeleteBranchModal */}
                    {selectedWorker && openDelete && (
                        <DeleteItemModal
                            item={selectedWorker}
                            open={openDelete} // Assuming you have a separate state for openDelete
                            setOpen={setOpenDelete} // Or you can manage this in its own state
                            onDelete={handleDelete} // Handle deletion
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
                                        className="max-w-full max-h-[85vh] rounded-lg shadow-2xl object-contain animate-in zoom-in-95 duration-200"
                                    />
                                )}
                            </div>
                        </DialogContent>
                    </Dialog>
                </table>

                {/* Pagination */}
                <div className="mt-4 flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                    <div>
                        {t('showing', {
                            from: worker.from,
                            to: worker.to,
                            total: worker.total,
                        })}
                    </div>
                    <div className="flex gap-1">
                        {worker.links.map((link, index) => (
                            <Link
                                key={index}
                                href={`${link.url ?? '?'}&search=${searchData.search}&per_page=${searchData.per_page}&firm_id=${searchData.firm_id}&branch_id=${searchData.branch_id}`}
                                className={`rounded-md px-3 py-1 text-sm transition ${
                                    link.active
                                        ? 'bg-blue-600 text-white'
                                        : !link.url
                                          ? 'cursor-not-allowed text-gray-400 dark:text-gray-500'
                                          : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default WorkerTable;

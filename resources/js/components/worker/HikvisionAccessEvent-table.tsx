import React, { useState, useEffect } from 'react';
import { TrashIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Worker, HikvisionAccessEvent, HikvisionAccessEventPaginate, SearchData } from '@/types';
import DeleteItemModal from '@/components/delete-item-modal';
import { toast } from 'sonner';

type HikvisionAccessEventTableProps = {
    worker: Worker;
    searchData: SearchData;
    hikvision_access_events: HikvisionAccessEventPaginate;
};

interface ValidImageItem {
    id: number;
    src: string;
    title: string;
}

const ImageThumbnail = ({
    src,
    alt,
    onClick,
    noImageText,
}: {
    src: string;
    alt: string;
    onClick: () => void;
    noImageText: string;
}) => {
    const [hasError, setHasError] = useState(false);

    if (hasError) {
        return (
            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-700/50">
                {noImageText}
            </span>
        );
    }

    return (
        <div className="overflow-hidden inline-block">
            <img
                onClick={onClick}
                className="transition-transform duration-300 ease-in-out transform hover:scale-110 cursor-pointer max-h-12 rounded object-cover border border-gray-200 dark:border-gray-700 shadow-sm"
                src={src}
                alt={alt}
                onError={() => setHasError(true)}
            />
        </div>
    );
};

const HikvisionAccessEventTable = ({ searchData, hikvision_access_events }: HikvisionAccessEventTableProps) => {
    const { t } = useTranslation();
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedHikvisionAccessEvent, setSelectedHikvisionAccessEvent] = useState<HikvisionAccessEvent | null>(null);

    const [showModal, setShowModal] = useState(false);
    const [currentIndex, setCurrentIndex] = useState<number | null>(null);

    const handleDeleteClick = (hikvisionAccessEvent: HikvisionAccessEvent) => {
        setSelectedHikvisionAccessEvent(hikvisionAccessEvent);
        setOpenDelete(true);
    };
    const { delete: deleteHikvisionAccessEvent, reset, clearErrors } = useForm();

    const getImageUrl = (item: HikvisionAccessEvent): string | null => {
        if (!item.picture || item.picture === 'null' || item.picture === 'undefined') {
            return null;
        }
        if (item.picture.includes('/')) {
            return `/storage/${item.picture}`;
        }
        const serial = item.hikvision_access?.shortSerialNumber;
        return serial ? `/storage/hikvision/${serial}/${item.picture}` : null;
    };

    const validImages: ValidImageItem[] = hikvision_access_events.data
        .map(item => {
            const url = getImageUrl(item);
            return url
                ? {
                      id: item.id,
                      src: url,
                      title: `${item.name || ''} ${item.hikvision_access?.dateTime || ''}`.trim(),
                  }
                : null;
        })
        .filter((item): item is ValidImageItem => item !== null);

    const handleDelete = (id: number) => {
        deleteHikvisionAccessEvent(`/hikvision_access_event/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                setOpenDelete(false);
                toast.success(t('deleted_successfully'));
            },
            onError: err => {
                const errorMessage = err?.error || t('delete_failed');
                toast.error(errorMessage);
            },
        });
    };

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (!showModal || currentIndex === null || validImages.length === 0) return;

            if (event.key === 'ArrowRight') {
                setCurrentIndex(prev => {
                    if (prev === null) return 0;
                    return prev < validImages.length - 1 ? prev + 1 : 0;
                });
            }

            if (event.key === 'ArrowLeft') {
                setCurrentIndex(prev => {
                    if (prev === null) return 0;
                    return prev > 0 ? prev - 1 : validImages.length - 1;
                });
            }

            if (event.key === 'Escape') {
                setShowModal(false);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => {
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [showModal, currentIndex, validImages.length]);

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between px-1">
                <div className="flex items-center gap-2">
                    <h3 className="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100 capitalize">
                        {t('hikvisionAccessEvent')}
                    </h3>
                    <span className="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-400">
                        {hikvision_access_events.total || 0} ta
                    </span>
                </div>
            </div>

            {/* Table Card */}
            <div className="w-full min-w-0 max-w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="w-full overflow-x-auto">
                    <table className="w-full min-w-[800px] text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-3 py-2.5 text-center w-10 font-mono">{t('n')}</th>
                                <th className="px-3 py-2.5 whitespace-nowrap">{t('datetime')}</th>
                                <th className="px-3 py-2.5">{t('shortSerialNumber')}</th>
                                <th className="px-3 py-2.5 font-mono">{t('mac_address')}</th>
                                <th className="px-3 py-2.5">{t('attendanceStatus')}</th>
                                <th className="px-3 py-2.5">{t('label')}</th>
                                <th className="px-3 py-2.5 text-center w-16">{t('image')}</th>
                                <th className="px-3 py-2.5 text-right w-14"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {(!hikvision_access_events.data || hikvision_access_events.data.length === 0) ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center text-slate-400 dark:text-slate-500">
                                        {t('no_events_found', 'Hozircha kirish-chiqish hodisalari mavjud emas.')}
                                    </td>
                                </tr>
                            ) : (
                                hikvision_access_events.data.map((item, index) => {
                                    const globalIndex = (hikvision_access_events.current_page - 1) * hikvision_access_events.per_page + index + 1;
                                    const imgUrl = getImageUrl(item);

                                    return (
                                        <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-3 py-2.5 text-center font-mono text-slate-400 dark:text-slate-500">{globalIndex}</td>
                                            <td className="px-3 py-2.5 whitespace-nowrap font-mono text-slate-700 dark:text-slate-300">
                                                {item.hikvision_access?.dateTime || '—'}
                                            </td>
                                            <td className="px-3 py-2.5 font-medium text-slate-900 dark:text-slate-100">
                                                {item.hikvision_access?.shortSerialNumber || '—'}
                                            </td>
                                            <td className="px-3 py-2.5 font-mono text-slate-500 dark:text-slate-400 text-[11px]">
                                                {item.hikvision_access?.macAddress || '—'}
                                            </td>
                                            <td className="px-3 py-2.5 whitespace-nowrap">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 font-medium text-[11px]">
                                                    {item.attendanceStatus || '—'}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2.5 text-slate-600 dark:text-slate-300">
                                                {item.label || '—'}
                                            </td>
                                            <td className="px-3 py-2.5 text-center">
                                                {imgUrl ? (
                                                    <ImageThumbnail
                                                        src={imgUrl}
                                                        alt={item.name || 'Olingan Rasm'}
                                                        noImageText={t('no_image')}
                                                        onClick={() => {
                                                            const validIndex = validImages.findIndex(img => img.id === item.id);
                                                            if (validIndex !== -1) {
                                                                setCurrentIndex(validIndex);
                                                                setShowModal(true);
                                                            }
                                                        }}
                                                    />
                                                ) : (
                                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] text-slate-400 bg-slate-100 dark:bg-slate-800">
                                                        {t('no_image')}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2.5 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleDeleteClick(item)}
                                                    className="h-7 w-7 p-0 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400"
                                                    title={t('delete', 'O‘chirish')}
                                                >
                                                    <TrashIcon className="w-3.5 h-3.5" />
                                                </Button>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>

                        {/* Pass selected worker to the DeleteWorkerModal */}
                        {selectedHikvisionAccessEvent && openDelete && (
                            <DeleteItemModal
                                item={selectedHikvisionAccessEvent}
                                open={openDelete}
                                setOpen={setOpenDelete}
                                onDelete={handleDelete}
                            />
                        )}
                    </table>
                </div>
            </div>

            {/* Pagination */}
            <div className="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400 px-1 pt-1">
                <div>
                    {t('showing', {
                        from: hikvision_access_events.from || 0,
                        to: hikvision_access_events.to || 0,
                        total: hikvision_access_events.total || 0,
                    })}
                </div>
                <div className="flex items-center gap-1">
                    {hikvision_access_events.links.map((link, index) => (
                        <Link
                            key={index}
                            href={`${link.url ?? '?'}&search=${searchData.search}&per_page=${searchData.per_page}&from=${searchData.from ?? ''}&to=${searchData.to ?? ''}`}
                            className={`rounded-lg px-2.5 py-1 text-xs font-medium transition-colors ${
                                link.active
                                    ? 'bg-indigo-600 text-white shadow-xs'
                                    : !link.url
                                        ? 'cursor-not-allowed opacity-40 text-slate-400'
                                        : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>

            {/* Modal Preview */}
            {showModal && currentIndex !== null && validImages[currentIndex] && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm"
                    onClick={() => setShowModal(false)}
                >
                    <div
                        className="relative max-w-4xl max-h-[90vh] flex flex-col items-center bg-gray-900/80 p-4 rounded-xl shadow-2xl border border-gray-700"
                        onClick={e => e.stopPropagation()}
                    >
                        {/* Close button */}
                        <button
                            onClick={() => setShowModal(false)}
                            className="absolute -top-3 -right-3 text-white bg-gray-800 hover:bg-gray-700 rounded-full p-1.5 shadow-lg border border-gray-600 transition"
                            aria-label="Close"
                        >
                            ✕
                        </button>

                        {/* Previous button */}
                        {validImages.length > 1 && (
                            <button
                                onClick={() =>
                                    setCurrentIndex(prev => {
                                        if (prev === null) return 0;
                                        return prev > 0 ? prev - 1 : validImages.length - 1;
                                    })
                                }
                                className="absolute left-2 top-1/2 transform -translate-y-1/2 bg-black/60 text-white p-3 rounded-full hover:bg-black/90 transition text-lg"
                                aria-label="Previous image"
                            >
                                ‹
                            </button>
                        )}

                        {/* Image */}
                        <img
                            src={validImages[currentIndex].src}
                            alt={validImages[currentIndex].title || 'Full Image'}
                            className="max-w-[85vw] max-h-[75vh] rounded-lg shadow-lg object-contain"
                        />

                        {/* Caption info */}
                        <div className="mt-3 text-center text-xs text-gray-300">
                            {validImages[currentIndex].title && (
                                <span className="font-semibold text-gray-200">{validImages[currentIndex].title}</span>
                            )}
                            {validImages.length > 1 && (
                                <span className="ml-3 text-gray-400">
                                    {currentIndex + 1} / {validImages.length}
                                </span>
                            )}
                        </div>

                        {/* Next button */}
                        {validImages.length > 1 && (
                            <button
                                onClick={() =>
                                    setCurrentIndex(prev => {
                                        if (prev === null) return 0;
                                        return prev < validImages.length - 1 ? prev + 1 : 0;
                                    })
                                }
                                className="absolute right-2 top-1/2 transform -translate-y-1/2 bg-black/60 text-white p-3 rounded-full hover:bg-black/90 transition text-lg"
                                aria-label="Next image"
                            >
                                ›
                            </button>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};

export default HikvisionAccessEventTable;

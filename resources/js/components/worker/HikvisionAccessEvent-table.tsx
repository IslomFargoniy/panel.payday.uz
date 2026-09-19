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
        <div>
            <h3 className={'capitalize text-center py-2'}>{t('hikvisionAccessEvent')}</h3>

            {/* Table */}
            <div className="overflow-x-auto">
                <table className="border-collapse w-full text-sm text-left text-gray-800 dark:text-gray-100">
                    <thead className="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">{t('n')}</td>
                            <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">{t('datetime')}</td>
                            <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">{t('shortSerialNumber')}</td>
                            <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">{t('mac_address')}</td>
                            <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">{t('attendanceStatus')}</td>
                            <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">{t('label')}</td>
                            <td className="border border-gray-300 dark:border-gray-600 px-4 py-2 text-center">{t('image')}</td>
                            <th className="border border-gray-300 dark:border-gray-600 px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody className="bg-white dark:bg-gray-800">
                        {hikvision_access_events.data.map((item, index) => {
                            const globalIndex = (hikvision_access_events.current_page - 1) * hikvision_access_events.per_page + index + 1;
                            const imgUrl = getImageUrl(item);

                            return (
                                <tr key={item.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">{globalIndex}</td>
                                    <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">{item.hikvision_access?.dateTime}</td>
                                    <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">{item.hikvision_access?.shortSerialNumber}</td>
                                    <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">{item.hikvision_access?.macAddress}</td>
                                    <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">{item.attendanceStatus}</td>
                                    <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">{item.label}</td>
                                    <td className="border border-gray-300 dark:border-gray-600 px-4 py-2 text-center">
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
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-700/50">
                                                {t('no_image')}
                                            </span>
                                        )}
                                    </td>

                                    <td className="border border-gray-300 dark:border-gray-600 px-4 py-2">
                                        <div className="inline-flex shadow-sm">
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => handleDeleteClick(item)}
                                            >
                                                <TrashIcon className="w-4 h-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
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

                {/* Pagination */}
                <div className="mt-4 flex justify-between items-center text-sm text-gray-600 dark:text-gray-300">
                    <div>
                        {t('showing', {
                            from: hikvision_access_events.from,
                            to: hikvision_access_events.to,
                            total: hikvision_access_events.total,
                        })}
                    </div>
                    <div className="flex gap-1">
                        {hikvision_access_events.links.map((link, index) => (
                            <Link
                                key={index}
                                href={`${link.url ?? '?'}&search=${searchData.search}&per_page=${searchData.per_page}`}
                                className={`px-3 py-1 rounded-md text-sm transition ${
                                    link.active
                                        ? 'bg-blue-600 text-white'
                                        : !link.url
                                          ? 'text-gray-400 dark:text-gray-500 cursor-not-allowed'
                                          : 'bg-white dark:bg-gray-800 dark:text-gray-200 text-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
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

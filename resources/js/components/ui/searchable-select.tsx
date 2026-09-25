import React, { useState, useRef, useEffect, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { Search, ChevronDown, Check, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export interface SelectOption {
    value: string | number;
    label: string;
    sublabel?: string;
    disabled?: boolean;
}

export interface SearchableSelectProps {
    options: SelectOption[];
    value?: string | number | null;
    onChange: (value: string | number) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyOptionLabel?: string;
    className?: string;
    triggerClassName?: string;
    disabled?: boolean;
    allowClear?: boolean;
    icon?: React.ReactNode;
    name?: string;
    id?: string;
}

export const SearchableSelect: React.FC<SearchableSelectProps> = ({
    options,
    value,
    onChange,
    placeholder = 'Tanlang...',
    searchPlaceholder,
    emptyOptionLabel,
    className = '',
    triggerClassName = '',
    disabled = false,
    allowClear = false,
    icon,
    id,
}) => {
    const { t } = useTranslation();
    const [isOpen, setIsOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [highlightedIndex, setHighlightedIndex] = useState<number>(-1);
    const [dropdownPosition, setDropdownPosition] = useState<{
        top: number;
        left: number;
        width: number;
        placeAbove: boolean;
    }>({ top: 0, left: 0, width: 0, placeAbove: false });

    const triggerRef = useRef<HTMLButtonElement>(null);
    const dropdownRef = useRef<HTMLDivElement>(null);
    const searchInputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLDivElement>(null);

    // Selected option lookup
    const selectedOption = useMemo(() => {
        if (value === undefined || value === null || value === '' || value === 0 || value === '0') {
            return null;
        }
        return options.find((opt) => String(opt.value) === String(value)) || null;
    }, [options, value]);

    // Filtered options based on search query
    const filteredOptions = useMemo(() => {
        if (!searchTerm.trim()) {
            return options;
        }
        const term = searchTerm.toLowerCase().trim();
        return options.filter((opt) => {
            const labelMatch = opt.label.toLowerCase().includes(term);
            const sublabelMatch = opt.sublabel?.toLowerCase().includes(term) ?? false;
            return labelMatch || sublabelMatch;
        });
    }, [options, searchTerm]);

    // Recalculate dropdown position
    const updatePosition = () => {
        if (!triggerRef.current) return;
        const rect = triggerRef.current.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        const estimatedHeight = 280;
        const placeAbove = spaceBelow < estimatedHeight && rect.top > estimatedHeight;
        const width = Math.max(rect.width, 200);

        let left = rect.left;
        if (left + width > window.innerWidth - 8) {
            left = Math.max(8, window.innerWidth - width - 8);
        }

        setDropdownPosition({
            top: placeAbove ? rect.top - 4 : rect.bottom + 4,
            left,
            width,
            placeAbove,
        });
    };

    // Toggle dropdown open/close
    const handleToggle = () => {
        if (disabled) return;
        if (!isOpen) {
            updatePosition();
            setSearchTerm('');
            setHighlightedIndex(-1);
            setIsOpen(true);
        } else {
            setIsOpen(false);
        }
    };

    // Handle option selection
    const handleSelect = (val: string | number) => {
        onChange(val);
        setIsOpen(false);
        setSearchTerm('');
        triggerRef.current?.focus();
    };

    // Handle clear
    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        onChange(emptyOptionLabel ? (typeof options[0]?.value === 'number' ? 0 : '') : '');
        setSearchTerm('');
    };

    // Auto-focus search input when opened
    useEffect(() => {
        if (isOpen) {
            updatePosition();
            const timer = setTimeout(() => {
                searchInputRef.current?.focus();
            }, 50);

            const handleScrollOrResize = () => {
                updatePosition();
            };

            window.addEventListener('resize', handleScrollOrResize);
            window.addEventListener('scroll', handleScrollOrResize, true);

            return () => {
                clearTimeout(timer);
                window.removeEventListener('resize', handleScrollOrResize);
                window.removeEventListener('scroll', handleScrollOrResize, true);
            };
        }
    }, [isOpen]);

    // Click outside listener
    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (
                triggerRef.current &&
                !triggerRef.current.contains(e.target as Node) &&
                dropdownRef.current &&
                !dropdownRef.current.contains(e.target as Node)
            ) {
                setIsOpen(false);
            }
        };

        if (isOpen) {
            document.addEventListener('mousedown', handleClickOutside);
        }
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [isOpen]);

    // Keyboard navigation
    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (!isOpen) {
            if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleToggle();
            }
            return;
        }

        const totalItems = (emptyOptionLabel ? 1 : 0) + filteredOptions.length;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlightedIndex((prev) => (prev + 1 < totalItems ? prev + 1 : 0));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlightedIndex((prev) => (prev - 1 >= 0 ? prev - 1 : totalItems - 1));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (emptyOptionLabel && highlightedIndex === 0) {
                handleSelect(typeof options[0]?.value === 'number' ? 0 : '');
            } else {
                const optIndex = emptyOptionLabel ? highlightedIndex - 1 : highlightedIndex;
                if (optIndex >= 0 && optIndex < filteredOptions.length) {
                    handleSelect(filteredOptions[optIndex].value);
                }
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            setIsOpen(false);
            triggerRef.current?.focus();
        }
    };

    // Scroll highlighted item into view
    useEffect(() => {
        if (highlightedIndex >= 0 && listRef.current) {
            const elements = listRef.current.querySelectorAll('[data-option-item]');
            const targetEl = elements[highlightedIndex] as HTMLElement;
            if (targetEl) {
                targetEl.scrollIntoView({ block: 'nearest' });
            }
        }
    }, [highlightedIndex]);

    return (
        <div className={`relative inline-block text-left ${className}`} id={id}>
            {/* Trigger Button */}
            <button
                ref={triggerRef}
                type="button"
                disabled={disabled}
                onClick={handleToggle}
                onKeyDown={handleKeyDown}
                className={`group flex h-9 w-full min-w-0 items-center justify-between gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800/80 ${triggerClassName}`}
                aria-haspopup="listbox"
                aria-expanded={isOpen}
            >
                <div className="flex min-w-0 items-center gap-1.5 truncate">
                    {icon && <span className="shrink-0 text-slate-400">{icon}</span>}
                    <span className={`truncate ${selectedOption ? 'font-medium text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500'}`}>
                        {selectedOption ? selectedOption.label : placeholder}
                    </span>
                </div>

                <div className="flex shrink-0 items-center gap-0.5 ml-1">
                    {allowClear && selectedOption && (
                        <span
                            role="button"
                            tabIndex={-1}
                            onClick={handleClear}
                            className="rounded-md p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                            title={t('clear', 'Tozalash')}
                        >
                            <X className="h-3 w-3" />
                        </span>
                    )}
                    <ChevronDown className={`h-3.5 w-3.5 text-slate-400 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`} />
                </div>
            </button>

            {/* Portal Dropdown Popover */}
            {isOpen &&
                createPortal(
                    <div
                        ref={dropdownRef}
                        style={{
                            position: 'fixed',
                            top: dropdownPosition.placeAbove ? 'auto' : `${dropdownPosition.top}px`,
                            bottom: dropdownPosition.placeAbove ? `${window.innerHeight - dropdownPosition.top}px` : 'auto',
                            left: `${dropdownPosition.left}px`,
                            minWidth: `${dropdownPosition.width}px`,
                            maxWidth: '90vw',
                            zIndex: 99999,
                        }}
                        className="animate-in fade-in-0 zoom-in-95 duration-100 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-800 dark:bg-slate-900 text-xs"
                    >
                        {/* Search Input */}
                        <div className="relative mb-1 px-1">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                            <input
                                ref={searchInputRef}
                                type="text"
                                value={searchTerm}
                                onChange={(e) => {
                                    setSearchTerm(e.target.value);
                                    setHighlightedIndex(0);
                                }}
                                onKeyDown={handleKeyDown}
                                placeholder={searchPlaceholder || t('search', 'Qidirish...')}
                                className="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-2.5 text-xs text-slate-700 outline-hidden placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:focus:bg-slate-900"
                            />
                        </div>

                        {/* Options List */}
                        <div ref={listRef} className="max-h-56 overflow-y-auto scrollbar-thin py-0.5 space-y-0.5">
                            {/* Empty / All option if configured */}
                            {emptyOptionLabel && !searchTerm && (
                                <div
                                    data-option-item
                                    onClick={() => handleSelect(typeof options[0]?.value === 'number' ? 0 : '')}
                                    onMouseEnter={() => setHighlightedIndex(0)}
                                    className={`flex items-center justify-between rounded-lg px-2.5 py-1.5 cursor-pointer transition-colors ${
                                        !selectedOption
                                            ? 'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'
                                            : highlightedIndex === 0
                                            ? 'bg-slate-100 dark:bg-slate-800/70 text-slate-900 dark:text-slate-100'
                                            : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50'
                                    }`}
                                >
                                    <span className="truncate">{emptyOptionLabel}</span>
                                    {!selectedOption && <Check className="h-3.5 w-3.5 shrink-0 text-indigo-600 dark:text-indigo-400" />}
                                </div>
                            )}

                            {filteredOptions.length === 0 ? (
                                <div className="py-4 text-center text-[11px] text-slate-400 dark:text-slate-500">
                                    {t('no_results', 'Natija topilmadi')}
                                </div>
                            ) : (
                                filteredOptions.map((opt, index) => {
                                    const itemIndex = emptyOptionLabel && !searchTerm ? index + 1 : index;
                                    const isSelected = selectedOption?.value === opt.value;
                                    const isHighlighted = highlightedIndex === itemIndex;

                                    return (
                                        <div
                                            key={opt.value}
                                            data-option-item
                                            onClick={() => handleSelect(opt.value)}
                                            onMouseEnter={() => setHighlightedIndex(itemIndex)}
                                            className={`flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 cursor-pointer transition-colors ${
                                                isSelected
                                                    ? 'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'
                                                    : isHighlighted
                                                    ? 'bg-slate-100 dark:bg-slate-800/70 text-slate-900 dark:text-slate-100'
                                                    : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/50'
                                            }`}
                                        >
                                            <div className="flex flex-col min-w-0">
                                                <span className="truncate">{opt.label}</span>
                                                {opt.sublabel && (
                                                    <span className="text-[10px] text-slate-400 dark:text-slate-500 truncate">
                                                        {opt.sublabel}
                                                    </span>
                                                )}
                                            </div>
                                            {isSelected && <Check className="h-3.5 w-3.5 shrink-0 text-indigo-600 dark:text-indigo-400" />}
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>,
                    document.body
                )}
        </div>
    );
};

export default SearchableSelect;

import React from 'react';
import { Button } from '@/components/ui/button';
import { Download, FileSpreadsheet } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface ExcelExportButtonProps {
    onClick: () => void | Promise<void>;
    disabled?: boolean;
    className?: string;
}

export function ExcelExportButton({ onClick, disabled = false, className = '' }: ExcelExportButtonProps) {
    const { t } = useTranslation();

    return (
        <Button
            type="button"
            onClick={onClick}
            disabled={disabled}
            size="sm"
            className={`bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 disabled:opacity-50 text-white font-medium h-9 px-3.5 rounded-xl shadow-2xs flex items-center gap-1.5 text-xs transition-colors shrink-0 cursor-pointer ${className}`}
        >
            <FileSpreadsheet className="w-4 h-4" />
            <span>{t('excel', 'Excel')}</span>
            <Download className="w-3.5 h-3.5 opacity-80" />
        </Button>
    );
}

export default ExcelExportButton;

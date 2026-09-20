import React, { forwardRef, useState, useEffect } from 'react';

export interface MaskedDateInputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    value?: string;
    onValueChange?: (val: string) => void;
}

/**
 * MaskedDateInput restricts input to numbers only and automatically formats as YYYY-MM-DD.
 * e.g., typing '2026' automatically adds '-' -> '2026-'
 * typing '08' -> '2026-08-'
 * typing '30' -> '2026-08-30'
 * Non-digit characters are blocked completely.
 */
export const MaskedDateInput = forwardRef<HTMLInputElement, MaskedDateInputProps>(
    ({ value, onChange, onKeyDown, onValueChange, className, ...props }, ref) => {
        const [prevVal, setPrevVal] = useState<string>(typeof value === 'string' ? value : '');

        useEffect(() => {
            if (typeof value === 'string') {
                setPrevVal(value);
            }
        }, [value]);

        const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
            // Allow control and navigation keys
            if (
                [
                    'Backspace',
                    'Delete',
                    'ArrowLeft',
                    'ArrowRight',
                    'ArrowUp',
                    'ArrowDown',
                    'Tab',
                    'Enter',
                    'Escape',
                    'Home',
                    'End',
                ].includes(e.key) ||
                e.ctrlKey ||
                e.metaKey ||
                e.altKey
            ) {
                if (onKeyDown) onKeyDown(e);
                return;
            }

            // Block any non-numeric character
            if (!/^\d$/.test(e.key)) {
                e.preventDefault();
                return;
            }

            if (onKeyDown) onKeyDown(e);
        };

        const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            const currentRaw = e.target.value;
            const isDeleting = currentRaw.length < prevVal.length;
            const digits = currentRaw.replace(/\D/g, '').slice(0, 8);

            let masked = '';
            if (digits.length === 0) {
                masked = '';
            } else if (digits.length <= 4) {
                masked = digits.length === 4 && !isDeleting ? `${digits}-` : digits;
            } else if (digits.length <= 6) {
                const year = digits.slice(0, 4);
                const month = digits.slice(4);
                masked = month.length === 2 && !isDeleting ? `${year}-${month}-` : `${year}-${month}`;
            } else {
                const year = digits.slice(0, 4);
                const month = digits.slice(4, 6);
                const day = digits.slice(6, 8);
                masked = `${year}-${month}-${day}`;
            }

            setPrevVal(masked);
            e.target.value = masked;

            if (onChange) {
                onChange(e);
            }
            if (onValueChange) {
                onValueChange(masked);
            }
        };

        return (
            <input
                {...props}
                ref={ref}
                type="text"
                inputMode="numeric"
                value={value}
                onChange={handleChange}
                onKeyDown={handleKeyDown}
                maxLength={10}
                className={className}
            />
        );
    }
);

MaskedDateInput.displayName = 'MaskedDateInput';

export interface MaskedTimeInputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    value?: string;
    onValueChange?: (val: string) => void;
}

/**
 * MaskedTimeInput restricts input to numbers only and automatically formats as HH:mm.
 * e.g., typing '15' automatically adds ':' -> '15:'
 * typing '30' -> '15:30'
 * Non-digit characters are blocked completely.
 */
export const MaskedTimeInput = forwardRef<HTMLInputElement, MaskedTimeInputProps>(
    ({ value, onChange, onKeyDown, onValueChange, className, ...props }, ref) => {
        const [prevVal, setPrevVal] = useState<string>(typeof value === 'string' ? value : '');

        useEffect(() => {
            if (typeof value === 'string') {
                setPrevVal(value);
            }
        }, [value]);

        const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
            if (
                [
                    'Backspace',
                    'Delete',
                    'ArrowLeft',
                    'ArrowRight',
                    'ArrowUp',
                    'ArrowDown',
                    'Tab',
                    'Enter',
                    'Escape',
                ].includes(e.key) ||
                e.ctrlKey ||
                e.metaKey
            ) {
                if (onKeyDown) onKeyDown(e);
                return;
            }

            if (!/^\d$/.test(e.key)) {
                e.preventDefault();
                return;
            }

            if (onKeyDown) onKeyDown(e);
        };

        const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            const currentRaw = e.target.value;
            const isDeleting = currentRaw.length < prevVal.length;
            const digits = currentRaw.replace(/\D/g, '').slice(0, 4);

            let masked = '';
            if (digits.length === 0) {
                masked = '';
            } else if (digits.length <= 2) {
                masked = digits.length === 2 && !isDeleting ? `${digits}:` : digits;
            } else {
                const hh = digits.slice(0, 2);
                const mm = digits.slice(2, 4);
                masked = `${hh}:${mm}`;
            }

            setPrevVal(masked);
            e.target.value = masked;

            if (onChange) {
                onChange(e);
            }
            if (onValueChange) {
                onValueChange(masked);
            }
        };

        return (
            <input
                {...props}
                ref={ref}
                type="text"
                inputMode="numeric"
                value={value}
                onChange={handleChange}
                onKeyDown={handleKeyDown}
                maxLength={5}
                className={className}
            />
        );
    }
);

MaskedTimeInput.displayName = 'MaskedTimeInput';

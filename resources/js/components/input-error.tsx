import { cn } from '@/lib/utils';
import { type HTMLAttributes } from 'react';
import { useTranslation } from 'react-i18next';

export default function InputError({ message, className = '', ...props }: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    const { i18n } = useTranslation();

    if (!message) return null;

    const translateMessage = (msg: string): string => {
        if (!msg) return '';

        const lang = (i18n.language || 'uz').toLowerCase().startsWith('ru')
            ? 'ru'
            : (i18n.language || 'uz').toLowerCase().startsWith('en')
            ? 'en'
            : 'uz';

        if (lang === 'en') return msg;

        const attrMap: Record<string, { uz: string; ru: string }> = {
            name: { uz: 'Ism / Nomi', ru: 'Имя / Название' },
            title: { uz: 'Sarlavha', ru: 'Заголовок' },
            date: { uz: 'Sana', ru: 'Дата' },
            phone: { uz: 'Telefon raqami', ru: 'Номер телефона' },
            email: { uz: 'Elektron pochta', ru: 'Электронная почта' },
            password: { uz: 'Parol', ru: 'Пароль' },
            current_password: { uz: 'Joriy parol', ru: 'Текущий пароль' },
            new_password: { uz: 'Yangi parol', ru: 'Новый пароль' },
            avatar: { uz: 'Rasm', ru: 'Фото' },
            comment: { uz: 'Izoh', ru: 'Примечание' },
            work_time: { uz: 'Ish boshlanishi', ru: 'Время начала работы' },
            end_time: { uz: 'Ish tugashi', ru: 'Время окончания работы' },
            hour_price: { uz: 'Soatbay narx', ru: 'Почасовая ставка' },
            fine_price: { uz: 'Jarima narxi', ru: 'Размер штрафа' },
            branch_id: { uz: 'Filial', ru: 'Филиал' },
            firm_id: { uz: 'Firma', ru: 'Фирма' },
            amount: { uz: 'Summa', ru: 'Сумма' },
            status: { uz: 'Holati', ru: 'Статус' },
        };

        // Match "The <attribute> field is required."
        const requiredMatch = msg.match(/^The\s+(.+?)\s+field is required\.?$/i);
        if (requiredMatch) {
            const rawAttr = requiredMatch[1].toLowerCase().replace(/\s+/g, '_');
            const attrLabel = attrMap[rawAttr] ? attrMap[rawAttr][lang] : requiredMatch[1];
            return lang === 'ru'
                ? `Поле ${attrLabel} обязательно для заполнения.`
                : `${attrLabel} maydoni to‘ldirilishi shart.`;
        }

        // Match "The <attribute> has already been taken."
        const uniqueMatch = msg.match(/^The\s+(.+?)\s+has already been taken\.?$/i);
        if (uniqueMatch) {
            const rawAttr = uniqueMatch[1].toLowerCase().replace(/\s+/g, '_');
            const attrLabel = attrMap[rawAttr] ? attrMap[rawAttr][lang] : uniqueMatch[1];
            return lang === 'ru'
                ? `Такое значение ${attrLabel} уже занято.`
                : `Bunday ${attrLabel} allaqachon mavjud.`;
        }

        // Match "The <attribute> must be a valid email address."
        const emailMatch = msg.match(/^The\s+(.+?)\s+must be a valid email address\.?$/i);
        if (emailMatch) {
            const rawAttr = emailMatch[1].toLowerCase().replace(/\s+/g, '_');
            const attrLabel = attrMap[rawAttr] ? attrMap[rawAttr][lang] : emailMatch[1];
            return lang === 'ru'
                ? `Поле ${attrLabel} должно быть действительным адресом электронной почты.`
                : `${attrLabel} haqiqiy elektron pochta manzili bo‘lishi kerak.`;
        }

        return msg;
    };

    return (
        <p {...props} className={cn('text-xs font-medium text-rose-600 dark:text-rose-400 mt-1', className)}>
            {translateMessage(message)}
        </p>
    );
}

import { SearchData, WorkerPaginate } from '@/types';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { format } from 'date-fns';
import ExcelJS from 'exceljs';
import { saveAs } from 'file-saver';
import { Download, FileSpreadsheet } from 'lucide-react';
import { useTranslation } from 'react-i18next';

type WorkerTableProps = {
    worker: WorkerPaginate;
    branch_id?: number;
    searchData: SearchData;
};

const WorkerMonthlyAttendanceTable = ({ worker, searchData }: WorkerTableProps) => {
    const { t } = useTranslation();

    // Converts HH:mm:ss string to minutes
    const timeToMinutes = (time?: string | null): number => {
        if (!time) return 0;
        const [h, m, s] = time.split(':').map(Number);
        return h * 60 + m + s / 60;
    };

    // Converts minutes to "HH:mm" format
    const minutesToHHMM = (minutes: number): string => {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return `${hours}:${String(mins).padStart(2, '0')}`;
    };

    // Calculates worked time based on work_time, end_time, and number of days
    const calculateWorkedTime = (startTime?: string | null, endTime?: string | null, days: number = 1): string => {
        if (timeToMinutes(endTime) < timeToMinutes(startTime)) {
            return '0:00';
        }

        let diff = timeToMinutes(endTime) - timeToMinutes(startTime);

        // Overnight shift support
        if (diff < 0) diff += 24 * 60;

        diff *= days;

        return minutesToHHMM(Math.round(diff));
    };

    const exportToExcel = async () => {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet('Attendance');

        const headers = [
            t('n', '№'),
            t('name', 'F.I.SH'),
            t('firm', 'Firma'),
            t('phone', 'Telefon'),
            t('late_hours', 'Kechikkan soat'),
            t('break_hours', 'Tanaffus'),
            t('worked_hours', 'Ishlagan soat'),
            t('common_worked_hours', 'Sof ishlagan soat'),
            t('late_days', 'Kechikkan kun'),
            t('worked_days', 'Ishlagan kun'),
            t('work_days', 'Ish kuni'),
            t('work_hours', 'Grafik soati'),
        ];

        // Add header row
        const headerRow = worksheet.addRow(headers);
        headerRow.eachCell((cell, colNumber) => {
            const headerText = headers[colNumber - 1];

            if (headerText === t('late_hours') || headerText === t('late_days')) {
                cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
                cell.fill = {
                    type: 'pattern',
                    pattern: 'solid',
                    fgColor: { argb: 'FFFF0000' },
                };
            } else {
                cell.font = { bold: true };
                cell.fill = {
                    type: 'pattern',
                    pattern: 'solid',
                    fgColor: { argb: 'D9E1F2' },
                };
            }

            cell.alignment = { vertical: 'middle', horizontal: 'center' };
            worksheet.getColumn(colNumber).width = headerText.length + 4;
        });

        // Add data rows
        worker.data.forEach((item, rowIndex) => {
            const row = worksheet.addRow([
                (worker.current_page - 1) * worker.per_page + rowIndex + 1,
                item.name,
                `${item.branch?.firm?.name || ''} ( ${item.branch?.name || ''} )`,
                item.phone,
                `${~~(item.late_minutes! / 60)}:${String(item.late_minutes! % 60).padStart(2, '0')}`,
                `${~~(item.break_minutes! / 60)}:${String(item.break_minutes! % 60).padStart(2, '0')}`,
                `${~~(item.worked_minutes! / 60)}:${String(item.worked_minutes! % 60).padStart(2, '0')}`,
                `${~~((item.worked_minutes! - item.break_minutes!) / 60)}:${String((item.worked_minutes! - item.break_minutes!) % 60).padStart(2, '0')}`,
                item.late_days,
                item.worked_days,
                item.work_days,

                calculateWorkedTime(item.work_time, item.end_time, item.work_days ?? 0),
            ]);

            row.getCell(4).font = { color: { argb: 'FFFF0000' } };
            row.getCell(8).font = { color: { argb: 'FFFF0000' } };
        });

        const buffer = await workbook.xlsx.writeBuffer();
        saveAs(new Blob([buffer]), `${searchData.month}_${t('attendance')}_${format(new Date(), 'yyyy-MM-dd_HH:mm:ss')}.xlsx`);
    };

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <Button
                    onClick={exportToExcel}
                    size="sm"
                    className="bg-emerald-600 hover:bg-emerald-700 text-white font-medium h-8 px-3 rounded-lg shadow-xs flex items-center gap-1.5 text-xs"
                >
                    <FileSpreadsheet className="w-4 h-4" />
                    <span>{t('excel', 'Excel yuklab olish')}</span>
                    <Download className="w-3.5 h-3.5 opacity-80" />
                </Button>
            </div>

            {/* Table Card */}
            <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead className="border-b border-slate-200/80 bg-slate-50/80 font-semibold text-slate-500 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-3.5 py-3 text-center w-10 font-mono">{t('n', '№')}</th>
                                <th className="px-3.5 py-3 min-w-[140px]">{t('name', 'F.I.SH')}</th>
                                <th className="px-3.5 py-3 min-w-[120px]">{t('firm', 'Filial')}</th>
                                <th className="px-3.5 py-3">{t('phone', 'Telefon')}</th>
                                <th className="px-3.5 py-3 text-rose-600 dark:text-rose-400">{t('late_hours', 'Kechikish (soat)')}</th>
                                <th className="px-3.5 py-3">{t('break_hours', 'Tanaffus')}</th>
                                <th className="px-3.5 py-3">{t('worked_hours', 'Ishlagan soat')}</th>
                                <th className="px-3.5 py-3 text-indigo-600 dark:text-indigo-400">{t('common_worked_hours', 'Sof soat')}</th>
                                <th className="px-3.5 py-3 text-rose-600 dark:text-rose-400">{t('late_days', 'Kechikkan kun')}</th>
                                <th className="px-3.5 py-3 text-emerald-600 dark:text-emerald-400">{t('worked_days', 'Ishlagan kun')}</th>
                                <th className="px-3.5 py-3">{t('work_days', 'Ish kuni')}</th>
                                <th className="px-3.5 py-3">{t('work_hours', 'Grafik soati')}</th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-900">
                            {(!worker.data || worker.data.length === 0) ? (
                                <tr>
                                    <td colSpan={12} className="px-4 py-8 text-center text-slate-400 dark:text-slate-500">
                                        {t('no_data', 'Ma’lumot mavjud emas')}
                                    </td>
                                </tr>
                            ) : (
                                worker.data.map((item, rowIndex) => {
                                    const globalIndex = (worker.current_page - 1) * worker.per_page + rowIndex + 1;

                                    return (
                                        <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-3.5 py-2.5 text-center font-mono text-slate-400 dark:text-slate-500">{globalIndex}</td>
                                            <td className="px-3.5 py-2.5 font-medium text-slate-900 dark:text-slate-100">
                                                <Link href={`/worker/${item.id}`} className="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                    {item.name}
                                                </Link>
                                            </td>
                                            <td className="px-3.5 py-2.5 text-slate-500 truncate max-w-[140px]">
                                                {item.branch?.firm?.name} ({item.branch?.name})
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-500">{item.phone || '—'}</td>
                                            <td className="px-3.5 py-2.5 font-mono font-semibold text-rose-600 dark:text-rose-400">
                                                {~~(item.late_minutes! / 60)}:{String(item.late_minutes! % 60).padStart(2, '0')}
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-600 dark:text-slate-300">
                                                {~~(item.break_minutes! / 60)}:{String(item.break_minutes! % 60).padStart(2, '0')}
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-700 dark:text-slate-200">
                                                {~~(item.worked_minutes! / 60)}:{String(item.worked_minutes! % 60).padStart(2, '0')}
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                                                {~~((item.worked_minutes! - item.break_minutes!) / 60)}:{String((item.worked_minutes! - item.break_minutes!) % 60).padStart(2, '0')}
                                            </td>
                                            <td className="px-3.5 py-2.5 font-mono font-semibold text-rose-600 dark:text-rose-400">{item.late_days}</td>
                                            <td className="px-3.5 py-2.5 font-mono font-semibold text-emerald-600 dark:text-emerald-400">{item.worked_days}</td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-600 dark:text-slate-300">{item.work_days}</td>
                                            <td className="px-3.5 py-2.5 font-mono text-slate-600 dark:text-slate-300">
                                                {calculateWorkedTime(item.work_time, item.end_time, item.work_days ?? 0)}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                <div className="flex items-center justify-between px-4 py-3 border-t border-slate-200/80 dark:border-slate-800 text-xs text-slate-500">
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
                                href={`${link.url ?? '?'}&search=${searchData.search || ''}&per_page=${searchData.per_page || 10}&firm_id=${searchData.firm_id || 0}&branch_id=${searchData.branch_id || 0}&month=${searchData.month || ''}`}
                                className={`rounded-lg px-2.5 py-1 text-xs font-medium transition-colors ${
                                    link.active
                                        ? 'bg-indigo-600 text-white shadow-xs'
                                        : !link.url
                                            ? 'cursor-not-allowed opacity-40'
                                            : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
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

export default WorkerMonthlyAttendanceTable;

